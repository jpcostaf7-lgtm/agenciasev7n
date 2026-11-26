<?php
/**
 * Analisador de SEO — etapas da análise, armazenamento, limite de uso e resumo com IA
 * Autor: João Costa
 *
 * A análise roda em etapas curtas (cada uma é um pedido separado do navegador), para
 * caber no tempo máximo das hospedagens compartilhadas:
 *   1. pagina     — página principal, robots.txt, sitemap, http/www, llms.txt
 *   2. subpaginas — até N páginas do menu + links quebrados + peso das imagens
 *   3. celular    — PageSpeed Insights no celular (velocidade, acessibilidade, SEO)
 *   4. computador — PageSpeed Insights no computador
 *   5. final      — notas, plano de ação e resumo em linguagem simples (Claude)
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_seo_opt(string $key, $fallback = null)
{
    return cms_module_get('seo-analyzer', $key, $fallback);
}

/** Quem está logado no painel (sem abrir sessão para os visitantes comuns). */
function s7x_seo_is_admin(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (empty($_COOKIE['s7x_sid'])) {
        return $ok = false;
    }
    require_once CMS_ROOT . '/cms/admin_lib.php';
    cms_session_start();
    return $ok = cms_logged_in();
}

function s7x_seo_dir(): string
{
    $d = CMS_ROOT . '/data/seo-analyzer';
    if (!is_dir($d . '/reports')) {
        @mkdir($d . '/reports', 0755, true);
    }
    return $d;
}

function s7x_seo_valid_id(string $id): bool
{
    return (bool) preg_match('/^[a-z0-9]{12}$/', $id);
}

function s7x_seo_get(string $id): ?array
{
    if (!s7x_seo_valid_id($id)) {
        return null;
    }
    $r = cms_read_json(s7x_seo_dir() . '/reports/' . $id . '.json', null);
    if (!is_array($r)) {
        return null;
    }
    // Régua de notas nova: recalcula análises antigas com os mesmos itens
    if (($r['status'] ?? '') === 'pronto' && !empty($r['items']) && (int) ($r['scores']['v'] ?? 1) < S7X_SEO_SCORE_V) {
        $r['scores'] = s7x_seo_score($r['items']);
        s7x_seo_save($r);
    }
    return $r;
}

function s7x_seo_save(array $r): void
{
    $r['updated'] = date('c');
    cms_write_json(s7x_seo_dir() . '/reports/' . $r['id'] . '.json', $r);
    s7x_seo_index_put($r);
}

/** Índice leve para a lista do painel. */
function s7x_seo_index(): array
{
    $idx = cms_read_json(s7x_seo_dir() . '/index.json', []);
    // Análises feitas com a régua antiga: recalcula (até 100 por vez) para a lista mostrar a nota nova
    $old = array_filter($idx, fn ($i) => ($i['status'] ?? '') === 'pronto' && (int) ($i['v'] ?? 1) < S7X_SEO_SCORE_V);
    if ($old) {
        foreach (array_slice(array_keys($old), 0, 100) as $id) {
            s7x_seo_get((string) $id);
        }
        $idx = cms_read_json(s7x_seo_dir() . '/index.json', []);
    }
    return $idx;
}

function s7x_seo_index_put(array $r): void
{
    $file = s7x_seo_dir() . '/index.json';
    $fp = fopen($file . '.lock', 'c');
    if ($fp) {
        flock($fp, LOCK_EX);
    }
    $idx = cms_read_json($file, []);
    $idx[$r['id']] = [
        'id' => $r['id'], 'url' => $r['final_url'] ?: $r['url'], 'host' => $r['host'], 'keyword' => $r['keyword'],
        'date' => $r['created'], 'status' => $r['status'], 'score' => $r['scores']['total'] ?? null, 'v' => (int) ($r['scores']['v'] ?? 1),
        'lead' => $r['lead'] ? ['name' => $r['lead']['name'], 'phone' => $r['lead']['phone'], 'email' => $r['lead']['email']] : null,
        'origin' => $r['origin'] ?? 'site', 'utm' => $r['utm'] ?? '', 'views' => (int) ($r['views'] ?? 0),
    ];
    // Mantém as 3.000 análises mais recentes no índice
    if (count($idx) > 3000) {
        uasort($idx, fn ($a, $b) => strcmp($b['date'], $a['date']));
        foreach (array_slice(array_keys($idx), 3000) as $old) {
            unset($idx[$old]);
            @unlink(s7x_seo_dir() . '/reports/' . $old . '.json');
        }
    }
    cms_write_json($file, $idx);
    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function s7x_seo_delete(string $id): void
{
    if (!s7x_seo_valid_id($id)) {
        return;
    }
    @unlink(s7x_seo_dir() . '/reports/' . $id . '.json');
    foreach (glob(CMS_ROOT . '/uploads/analisador/' . $id . '-*') ?: [] as $f) {
        @unlink($f);
    }
    $file = s7x_seo_dir() . '/index.json';
    $idx = cms_read_json($file, []);
    unset($idx[$id]);
    cms_write_json($file, $idx);
}

/** Limite de análises por IP (por hora) — evita abuso e estouro da cota do Google. */
function s7x_seo_rate_ok(): bool
{
    if (s7x_seo_is_admin()) {
        return true;
    }
    $max = max(1, (int) s7x_seo_opt('rate_limit', 8));
    $file = s7x_seo_dir() . '/rate.json';
    $key = substr(hash('sha256', cms_client_ip() . cms_secret()), 0, 16);
    $now = time();
    $fp = fopen($file . '.lock', 'c');
    if ($fp) {
        flock($fp, LOCK_EX);
    }
    $rate = cms_read_json($file, []);
    foreach ($rate as $k => $ts) {
        $rate[$k] = array_values(array_filter((array) $ts, fn ($t) => $t > $now - 3600));
        if (!$rate[$k]) {
            unset($rate[$k]);
        }
    }
    $ok = count($rate[$key] ?? []) < $max;
    if ($ok) {
        $rate[$key][] = $now;
    }
    cms_write_json($file, $rate);
    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    return $ok;
}

/** Cria a análise (ainda sem coletar nada). */
function s7x_seo_create(string $url, string $keyword, array $lead = [], string $origin = 'site'): array
{
    $norm = s7x_seo_normalize_url($url);
    if ($norm === '') {
        throw new RuntimeException('Digite um endereço válido, como www.seusite.com.br.');
    }
    $host = (string) parse_url($norm, PHP_URL_HOST);
    s7x_seo_resolve($host); // falha cedo com mensagem clara (domínio inexistente ou interno)
    $id = '';
    do {
        $id = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12);
        $id = strtolower(preg_replace('/[^a-z0-9]/i', '', $id));
    } while (strlen($id) !== 12 || is_file(s7x_seo_dir() . '/reports/' . $id . '.json'));
    $r = [
        'id' => $id, 'url' => $norm, 'final_url' => '', 'host' => preg_replace('/^www\./', '', $host),
        'keyword' => mb_substr(trim(strip_tags($keyword)), 0, 80), 'created' => date('c'), 'updated' => date('c'),
        'status' => 'rodando', 'steps' => [], 'error' => '', 'origin' => $origin,
        'utm' => mb_substr(trim((string) ($_POST['utm'] ?? '')), 0, 160),
        'lead' => $lead ?: null, 'fetch' => [], 'page' => [], 'site' => [], 'subpages' => [], 'broken' => null, 'links_checked' => 0,
        'image_sizes' => [], 'psi' => ['mobile' => null, 'desktop' => null], 'items' => [], 'scores' => [], 'plan' => [], 'ai' => null, 'views' => 0,
    ];
    s7x_seo_save($r);
    return $r;
}

function s7x_seo_steps(): array
{
    $s = ['pagina' => 'Lendo a página principal', 'subpaginas' => 'Conferindo páginas internas e links'];
    if (s7x_seo_opt('pagespeed', true)) {
        $s['celular'] = 'Medindo a velocidade no celular (Google)';
        $s['computador'] = 'Medindo a velocidade no computador (Google)';
    }
    $s['final'] = 'Calculando a nota e montando o plano';
    return $s;
}

/** Executa uma etapa. Devolve o relatório atualizado. */
function s7x_seo_run_step(array $r, string $step): array
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(150);
    }
    @ignore_user_abort(true);
    if (!empty($r['steps'][$step]) && $r['steps'][$step] === 'ok') {
        return $r;
    }
    try {
        switch ($step) {
            case 'pagina':
                $r = s7x_seo_step_page($r);
                break;
            case 'subpaginas':
                $r = s7x_seo_step_subpages($r);
                break;
            case 'celular':
            case 'computador':
                $strategy = $step === 'celular' ? 'mobile' : 'desktop';
                $r['psi'][$strategy] = s7x_seo_psi($r['final_url'] ?: $r['url'], $strategy, $r['id']);
                break;
            case 'final':
                $r = s7x_seo_finish($r);
                break;
        }
        $r['steps'][$step] = 'ok';
    } catch (Throwable $e) {
        $r['steps'][$step] = 'erro';
        if ($step === 'pagina') {
            $r['status'] = 'erro';
            $r['error'] = $e->getMessage();
        }
    }
    s7x_seo_save($r);
    return $r;
}

function s7x_seo_step_page(array $r): array
{
    $res = s7x_seo_fetch($r['url'], ['timeout' => 25]);
    if ($res['error'] !== '' && $res['status'] === 0) {
        throw new RuntimeException($res['error']);
    }
    $r['final_url'] = $res['url'];
    $body = $res['body'];
    $res['body'] = '';
    $r['fetch'] = $res;
    $ctype = strtolower((string) ($res['headers']['content-type'] ?? 'text/html'));
    if ($res['status'] >= 400 && trim($body) === '') {
        throw new RuntimeException('O site respondeu com erro ' . $res['status'] . '. Confira se o endereço abre no navegador.');
    }
    if (strpos($ctype, 'html') === false && strpos($ctype, 'xml') === false && $ctype !== '') {
        throw new RuntimeException('Esse endereço não é uma página de site (' . $ctype . ').');
    }
    $r['page'] = s7x_seo_parse($body, $res['url'], $res['headers']);
    $p = parse_url($res['url']);
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $robots = s7x_seo_robots($origin, (string) ($p['path'] ?? '/'));
    $r['site'] = [
        'robots' => $robots,
        'sitemap' => s7x_seo_sitemap($origin, $robots['sitemaps']),
        'variants' => s7x_seo_variants($res['url']),
        'llms' => s7x_seo_llms($origin),
        'security' => stripos($res['url'], 'https://') === 0 ? s7x_seo_security_headers($res['headers']) : [],
    ];
    return $r;
}

function s7x_seo_step_subpages(array $r): array
{
    $f = $r['page'];
    $base = $r['final_url'];
    $max = max(0, min(12, (int) s7x_seo_opt('subpages', 6)));
    $skip = '/\.(pdf|jpe?g|png|gif|webp|svg|zip|rar|mp4|mp3|docx?|xlsx?|pptx?)(\?|$)|\/(wp-admin|wp-login|login|entrar|carrinho|cart|checkout|minha-conta|my-account|feed|tag|author)\b|[?&](add-to-cart|replytocom|s)=/i';
    // Prioriza links do menu/cabeçalho: os primeiros da página costumam ser os do menu
    $seen = [rtrim(preg_replace('/[?#].*$/', '', $base), '/') => true];
    $pick = [];
    foreach ($f['links'] as $l) {
        if (!$l['internal'] || preg_match($skip, $l['url'])) {
            continue;
        }
        $k = rtrim(preg_replace('/[?#].*$/', '', $l['url']), '/');
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $pick[] = $l['url'];
        if (count($pick) >= $max) {
            break;
        }
    }
    $subs = [];
    $deadline = microtime(true) + 60;
    foreach ($pick as $u) {
        if (microtime(true) > $deadline) {
            break;
        }
        $res = s7x_seo_fetch($u, ['timeout' => 12, 'max_bytes' => 1536 * 1024]);
        $row = ['url' => $u, 'status' => (int) $res['status'], 'ms' => (int) $res['total'], 'title' => '', 'description' => '', 'h1' => 0, 'words' => 0, 'noindex' => false, 'canonical' => '', 'img_noalt' => 0];
        if ($res['status'] === 200 && $res['body'] !== '') {
            $pf = s7x_seo_parse($res['body'], $res['url'], $res['headers']);
            $row = array_merge($row, [
                'url' => $res['url'], 'title' => $pf['title'], 'description' => $pf['description'], 'h1' => count($pf['h1']), 'words' => $pf['words'],
                'noindex' => stripos($pf['robots'], 'noindex') !== false, 'canonical' => $pf['canonical'],
                'img_noalt' => count(array_filter($pf['images'], fn ($i) => $i['alt'] === null)),
            ]);
        }
        $subs[] = $row;
    }
    $r['subpages'] = $subs;

    // Links quebrados: até 30 internos e 15 externos da página principal
    $int = [];
    $ext = [];
    foreach ($f['links'] as $l) {
        $u = preg_replace('/#.*$/', '', $l['url']);
        if (preg_match($skip, $u)) {
            continue;
        }
        if ($l['internal']) {
            $int[$u] = true;
        } elseif (!preg_match('#(facebook|instagram|linkedin|twitter|x\.com|tiktok|youtube|wa\.me|whatsapp|google\.com/maps|goo\.gl)#i', $u)) {
            $ext[$u] = true;
        }
    }
    $done = array_column($subs, 'status', 'url');
    $check = array_slice(array_diff(array_keys($int), array_keys($done)), 0, 30);
    $check = array_merge($check, array_slice(array_keys($ext), 0, 15));
    $st = $check ? s7x_seo_check_links($check) : [];
    $broken = [];
    foreach ($st as $u => $s) {
        $code = is_array($s) ? $s['s'] : (int) $s;
        if (in_array($code, [404, 410], true) || $code >= 500) {
            $broken[] = ['url' => $u, 'status' => $code];
        }
    }
    foreach ($subs as $s) {
        if (in_array($s['status'], [404, 410], true) || $s['status'] >= 500) {
            $broken[] = ['url' => $s['url'], 'status' => $s['status']];
        }
    }
    $r['broken'] = $broken;
    $r['links_checked'] = count($st) + count($subs);

    // Peso das imagens (até 15)
    $imgs = array_slice(array_values(array_filter(array_column($f['images'], 'src'), fn ($s) => $s !== '' && !preg_match('/\.svg(\?|$)/i', $s))), 0, 15);
    $sizes = [];
    if ($imgs) {
        foreach (s7x_seo_check_links($imgs) as $u => $s) {
            if (is_array($s) && $s['s'] === 200 && $s['b'] > 0) {
                $sizes[$u] = (int) $s['b'];
            }
        }
    }
    $r['image_sizes'] = $sizes;
    return $r;
}

function s7x_seo_finish(array $r): array
{
    $r['items'] = s7x_seo_evaluate($r);
    $r['scores'] = s7x_seo_score($r['items']);
    $r['plan'] = array_map(fn ($i) => $i['cat'] . ':' . $i['id'], s7x_seo_plan($r['items']));
    if (s7x_seo_opt('ai', true) && s7x_seo_ai_key() !== '') {
        try {
            $r['ai'] = s7x_seo_ai_summary($r);
        } catch (Throwable $e) {
            $r['ai'] = ['error' => $e->getMessage()];
        }
    }
    $r['status'] = 'pronto';
    // O texto da página não precisa ficar guardado (só serviu para a avaliação)
    $r['page']['text'] = '';
    $r['page']['links'] = array_slice($r['page']['links'], 0, 120);
    return $r;
}

/* -------------------------------------------------------------------------
 * Resumo em linguagem simples (Claude)
 * ---------------------------------------------------------------------- */

function s7x_seo_ai_key(): string
{
    return cms_secret_get('anthropic_api_key', '');
}

function s7x_seo_ai_summary(array $r): array
{
    $items = array_values(array_filter($r['items'], fn ($i) => $i['status'] !== 'info'));
    $compact = array_map(fn ($i) => ['cat' => $i['cat'], 'status' => $i['status'], 'item' => $i['titulo'], 'detalhe' => mb_substr($i['detalhe'], 0, 180)], $items);
    $cats = [];
    foreach ($r['scores']['cats'] as $k => $c) {
        $cats[$c['label']] = $c['score'];
    }
    $data = [
        'site' => $r['final_url'] ?: $r['url'], 'palavra_chave' => $r['keyword'], 'nota_geral' => $r['scores']['total'], 'notas' => $cats,
        'titulo' => $r['page']['title'] ?? '', 'descricao' => $r['page']['description'] ?? '', 'h1' => $r['page']['h1'][0] ?? '',
        'plataforma' => array_values(array_filter($r['page']['tech'] ?? [], 'is_string')), 'itens' => $compact,
    ];
    $agency = trim((string) s7x_seo_opt('agency_name', '')) ?: cms_site_name();
    $system = 'Você é consultor de SEO da ' . $agency . ' e escreve para donos de pequenas e médias empresas no Brasil, que não são técnicos. '
        . 'Use português do Brasil correto e revisado, frases curtas, sem jargão (quando precisar de um termo técnico, explique em poucas palavras). '
        . 'Baseie-se SOMENTE nos dados recebidos; não invente números, concorrentes nem resultados. Não prometa primeira posição no Google. '
        . 'Responda apenas com JSON válido, sem texto fora dele, no formato: '
        . '{"resumo":"3 a 4 frases: como o site está, o que mais atrapalha e o que ganharia corrigindo",'
        . '"pontos_fortes":["até 3 itens curtos"],'
        . '"prioridades":[{"titulo":"ação em até 8 palavras","por_que":"1 frase sobre o impacto para o negócio","como":"1 a 2 frases práticas"}],'
        . '"titulo_sugerido":"sugestão de title com até 60 caracteres (use a palavra-chave se houver)",'
        . '"descricao_sugerida":"sugestão de meta description com 140 a 158 caracteres e chamada para ação"} '
        . 'Inclua de 3 a 5 prioridades, da mais importante para a menos importante. Se a página não tiver informação suficiente para saber o que a empresa faz, deixe titulo_sugerido e descricao_sugerida vazios em vez de inventar.';
    $body = [
        'model' => (string) s7x_seo_opt('ai_model', 'claude-haiku-5-5'),
        'max_tokens' => 2500,
        'thinking' => ['type' => 'disabled'],
        'system' => $system,
        'messages' => [['role' => 'user', 'content' => "Dados da análise:\n" . json_encode($data, JSON_UNESCAPED_UNICODE)]],
    ];
    $res = cms_http('POST', 'https://api.anthropic.com/v1/messages', ['x-api-key: ' . s7x_seo_ai_key(), 'anthropic-version: 2023-06-01'], $body, 60);
    if ($res['status'] !== 200 || !is_array($res['json'])) {
        throw new RuntimeException('Resumo com IA indisponível (HTTP ' . $res['status'] . ').');
    }
    $txt = '';
    foreach ($res['json']['content'] ?? [] as $c) {
        if (($c['type'] ?? '') === 'text') {
            $txt .= $c['text'];
        }
    }
    if (preg_match('/\{.*\}/s', $txt, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j) && !empty($j['resumo'])) {
            $clean = fn ($s) => mb_substr(trim(strip_tags((string) $s)), 0, 600);
            return [
                'resumo' => $clean($j['resumo']),
                'pontos_fortes' => array_slice(array_map($clean, (array) ($j['pontos_fortes'] ?? [])), 0, 3),
                'prioridades' => array_slice(array_values(array_filter(array_map(fn ($p) => is_array($p) ? ['titulo' => $clean($p['titulo'] ?? ''), 'por_que' => $clean($p['por_que'] ?? ''), 'como' => $clean($p['como'] ?? '')] : null, (array) ($j['prioridades'] ?? [])), fn ($p) => $p && $p['titulo'] !== '')), 0, 5),
                'titulo_sugerido' => mb_substr($clean($j['titulo_sugerido'] ?? ''), 0, 70),
                'descricao_sugerida' => mb_substr($clean($j['descricao_sugerida'] ?? ''), 0, 170),
            ];
        }
    }
    if (getenv('S7X_DEBUG')) {
        fwrite(STDERR, "IA respondeu: " . $txt . "\n" . json_encode($res['json']) . "\n");
    }
    throw new RuntimeException('A IA não devolveu um resumo válido.');
}

/* -------------------------------------------------------------------------
 * Contato (lead)
 * ---------------------------------------------------------------------- */

function s7x_seo_lead_from_post(bool $required): array
{
    $name = trim(strip_tags((string) ($_POST['nome'] ?? '')));
    $phone = trim(strip_tags((string) ($_POST['whatsapp'] ?? '')));
    $email = trim((string) ($_POST['email'] ?? ''));
    $any = $name !== '' || $phone !== '' || $email !== '';
    if (!$required && !$any) {
        return [];
    }
    $fields = explode('_', (string) s7x_seo_opt('lead_fields', 'nome_whatsapp_email'));
    if (mb_strlen($name) < 2) {
        throw new RuntimeException('Informe seu nome.');
    }
    if (in_array('whatsapp', $fields, true) && strlen(cms_digits($phone)) < 10) {
        throw new RuntimeException('Informe seu WhatsApp com DDD.');
    }
    if (in_array('email', $fields, true) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail válido.');
    }
    if (empty($_POST['aceite'])) {
        throw new RuntimeException('Marque o aceite para receber o relatório.');
    }
    return [
        'name' => mb_substr($name, 0, 120), 'phone' => mb_substr($phone, 0, 40), 'email' => mb_substr($email, 0, 160),
        'company' => mb_substr(trim(strip_tags((string) ($_POST['empresa'] ?? ''))), 0, 120), 'date' => date('c'),
    ];
}

/** Guarda o contato também na tela Leads (módulo Formulário) e avisa por e-mail. */
function s7x_seo_register_lead(array $r): void
{
    if (!$r['lead'] || !function_exists('s7x_leads')) {
        return;
    }
    $score = $r['scores']['total'] ?? null;
    $link = s7x_seo_report_url($r['id'], true);
    $lead = [
        'id' => bin2hex(random_bytes(6)), 'date' => date('c'),
        'name' => $r['lead']['name'], 'phone' => $r['lead']['phone'], 'email' => $r['lead']['email'],
        'message' => 'Analisou o site ' . ($r['final_url'] ?: $r['url']) . ($score !== null ? ' — nota ' . $score . '/100' : '') . ($r['keyword'] ? ' — palavra-chave: ' . $r['keyword'] : '') . ($r['lead']['company'] ? ' — empresa: ' . $r['lead']['company'] : '') . "\nRelatório: " . $link,
        'page' => 'Analisador de SEO', 'url' => $link, 'form' => 'Analisador de SEO',
        'ip' => preg_replace('/(\d+)\.(\d+)\.\d+\.\d+/', '$1.$2.x.x', cms_client_ip()),
        'consent' => (string) s7x_seo_opt('consent_text', ''), 'read' => false, 'mail' => null,
    ];
    if (function_exists('s7x_forms_mail')) {
        $lead['mail'] = s7x_forms_mail($lead);
    }
    $leads = s7x_leads();
    array_unshift($leads, $lead);
    s7x_save_leads($leads);
}
