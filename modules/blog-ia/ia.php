<?php
/**
 * Módulo Artigos com IA — geração com o Claude, imagens do Pixabay, SEO,
 * publicação automática, rede de blogs e importação de artigos da rede.
 * Versão Sev7n Xpress do plugin "Shelter Consultoria Hub".
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

/* -------------------------------------------------------------------------
 * Configurações
 * ---------------------------------------------------------------------- */

function s7x_ia_models(): array
{
    return [
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (recomendado: qualidade e custo equilibrados)',
        'claude-opus-5-5'   => 'Claude Opus 5.5 (máxima qualidade, mais caro)',
        'claude-haiku-5-5'  => 'Claude Haiku 5.5 (mais rápido e barato)',
    ];
}

function s7x_ia_default_instructions(): string
{
    $name = trim((string) cms_seo('org_name')) ?: cms_site_name();
    return "Você escreve para o blog de {$name}.\n"
        . "Escreva em português do Brasil, com tom profissional, informativo e acessível, explicando siglas na primeira vez que aparecem.\n"
        . "Escreva conteúdo explicativo e atemporal (guias, panoramas, como funciona). NÃO invente notícias, datas, valores, números, leis ou normas. Se não tiver certeza de um dado, explique o conceito sem citar o número. Só trate de fatos recentes se eles estiverem no texto-base fornecido.\n"
        . "Quando fizer sentido, mencione de forma natural os serviços de {$name}, sem exageros comerciais.\n"
        . "Estruture com subtítulos (h2/h3), parágrafos curtos e listas quando ajudarem a leitura. Termine com uma conclusão prática.";
}

/** Campos das configurações, por aba. */
function s7x_ia_fields(): array
{
    return [
        'ia' => [
            'label' => 'IA (Claude)',
            'fields' => [
                'model'        => ['type' => 'select', 'label' => 'Modelo', 'default' => 'claude-sonnet-5-5', 'options' => s7x_ia_models()],
                'words'        => ['type' => 'number', 'label' => 'Tamanho padrão dos artigos (palavras)', 'default' => 1200],
                'instructions' => ['type' => 'textarea', 'label' => 'Instruções para a IA (perfil do blog)', 'rows' => 10, 'wide' => true, 'default' => s7x_ia_default_instructions(), 'help' => 'Quem é a empresa, público, temas e o que a IA nunca deve fazer. Vale para todos os artigos.'],
            ],
        ],
        'automacao' => [
            'label' => 'Automação',
            'fields' => [
                'auto_enabled' => ['type' => 'toggle', 'label' => 'Gerar artigos automaticamente todos os dias', 'default' => false],
                'auto_times'   => ['type' => 'text', 'label' => 'Horários', 'default' => '08:00, 13:00, 18:00', 'help' => 'Um artigo por horário, separados por vírgula. Ex.: 07:00, 11:30, 15:00, 19:30 = 4 por dia (horário de Brasília).'],
                'auto_status'  => ['type' => 'select', 'label' => 'Situação dos artigos gerados', 'default' => 'publish', 'options' => ['publish' => 'Publicar direto', 'draft' => 'Rascunho (revisar antes)', 'pending' => 'Para revisar']],
                'auto_words'   => ['type' => 'number', 'label' => 'Tamanho (palavras)', 'default' => 1200],
                'auto_topics'  => ['type' => 'textarea', 'label' => 'Fila de pautas (opcional)', 'rows' => 6, 'wide' => true, 'default' => '', 'placeholder' => "pre-sal|Como funciona a reinjeção de CO2 no pré-sal\nempregos|Como montar um currículo para vagas offshore\nO que faz um operador de VTS", 'help' => 'Usadas primeiro, de cima para baixo, e saem da lista depois de usadas. Formato: slug-da-categoria|tema ou só o tema.'],
            ],
        ],
        'imagens' => [
            'label' => 'Imagens (Pixabay)',
            'fields' => [
                'img_featured'    => ['type' => 'toggle', 'label' => 'Imagem de destaque automática', 'default' => true],
                'img_inline'      => ['type' => 'toggle', 'label' => 'Uma imagem no meio do texto', 'default' => true],
                'img_credit'      => ['type' => 'toggle', 'label' => 'Mostrar o crédito do fotógrafo', 'default' => true, 'help' => 'A licença do Pixabay não obriga, mas é uma boa prática.'],
                'img_credit_text' => ['type' => 'text', 'label' => 'Texto do crédito', 'default' => 'Imagem: {autor} / Pixabay'],
                'img_min_width'   => ['type' => 'number', 'label' => 'Largura mínima das fotos (px)', 'default' => 1200],
                'img_backfill'    => ['type' => 'number', 'label' => 'Completar imagens de posts antigos (por dia)', 'default' => 0, 'help' => 'Posts sem imagem de destaque recebem uma foto aos poucos, em segundo plano. 0 = desligado.'],
                'img_queries'     => ['type' => 'textarea', 'label' => 'Termos de busca por categoria (em inglês)', 'rows' => 8, 'wide' => true, 'default' => '', 'placeholder' => "pre-sal|offshore oil platform, fpso ship\nportos|container port, harbor cranes", 'help' => 'Usados quando a IA não sugere boas fotos. Formato: slug-da-categoria|termo 1, termo 2. Última linha sem categoria = termos gerais.'],
            ],
        ],
        'seo' => [
            'label' => 'SEO',
            'fields' => [
                'seo_mode'     => ['type' => 'toggle', 'label' => 'Modo SEO máximo', 'default' => true, 'help' => 'Palavra-chave principal, título SEO, meta descrição, endereço curto, palavra-chave no começo e nos subtítulos, sumário, links internos, FAQ com dados estruturados.'],
                'seo_toc'      => ['type' => 'toggle', 'label' => 'Sumário clicável no começo do artigo', 'default' => true],
                'seo_faq'      => ['type' => 'toggle', 'label' => 'Perguntas frequentes no fim (FAQ)', 'default' => true],
                'seo_internal' => ['type' => 'number', 'label' => 'Links para outros posts do blog', 'default' => 3],
                'seo_external' => ['type' => 'toggle', 'label' => 'Link para fonte oficial (só da lista abaixo)', 'default' => true],
                'seo_sources'  => ['type' => 'textarea', 'label' => 'Fontes oficiais permitidas', 'rows' => 6, 'wide' => true, 'default' => '', 'placeholder' => "https://www.gov.br/anp/pt-br | ANP\nhttps://www.marinha.mil.br | Marinha do Brasil", 'help' => 'Um site por linha (endereço | nome). A IA só pode criar links para estes endereços; qualquer outro link externo é removido.'],
            ],
        ],
        'rede' => [
            'label' => 'Rede de blogs',
            'fields' => [
                'net_sites'  => ['type' => 'textarea', 'label' => 'Sites da rede', 'rows' => 4, 'wide' => true, 'default' => '', 'placeholder' => "SEV7N IA|https://sev7nia.com.br\nShelter Cursos|https://sheltermar.com.br", 'help' => 'Um por linha: Nome|endereço. Precisam ser WordPress com a API pública (wp-json) ou outro Sev7n Xpress com blog.'],
                'net_box'    => ['type' => 'toggle', 'label' => 'Mostrar “Leia também na nossa rede” no fim dos posts', 'default' => true],
                'net_title'  => ['type' => 'text', 'label' => 'Título do quadro', 'default' => 'Leia também na nossa rede'],
                'net_count'  => ['type' => 'number', 'label' => 'Quantidade de artigos', 'default' => 3],
                'net_search' => ['type' => 'text', 'label' => 'Filtrar por palavra (opcional)', 'default' => '', 'help' => 'Ex.: offshore. Vazio = os mais recentes de cada site.'],
            ],
        ],
        'importar' => [
            'label' => 'Importar da rede',
            'fields' => [
                'imp_enabled'  => ['type' => 'toggle', 'label' => 'Importar artigos de outro site da rede automaticamente', 'default' => false],
                'imp_source'   => ['type' => 'url', 'label' => 'Site de origem', 'default' => '', 'placeholder' => 'https://sev7nia.com.br'],
                'imp_mode'     => ['type' => 'select', 'label' => 'Como escolher os artigos', 'default' => 'search', 'options' => ['search' => 'Por palavras (busca)', 'category' => 'Por categoria (slugs)', 'tag' => 'Por tag (slugs)']],
                'imp_terms'    => ['type' => 'text', 'label' => 'Palavras ou slugs (separados por vírgula)', 'default' => 'offshore'],
                'imp_per_run'  => ['type' => 'number', 'label' => 'Artigos por execução', 'default' => 5],
                'imp_every'    => ['type' => 'select', 'label' => 'Frequência', 'default' => '12', 'options' => ['3' => 'A cada 3 horas', '6' => 'A cada 6 horas', '12' => 'Duas vezes por dia', '24' => 'Uma vez por dia', '168' => 'Uma vez por semana']],
                'imp_status'   => ['type' => 'select', 'label' => 'Situação dos importados', 'default' => 'draft', 'options' => ['draft' => 'Rascunho (revisar antes)', 'publish' => 'Publicar direto']],
                'imp_category' => ['type' => 'text', 'label' => 'Categoria padrão (slug)', 'default' => 'offshore'],
                'imp_content'  => ['type' => 'select', 'label' => 'Conteúdo', 'default' => 'full', 'options' => ['full' => 'Artigo completo', 'excerpt' => 'Só o resumo + link para o original']],
                'imp_canonical' => ['type' => 'toggle', 'label' => 'Canonical apontando para o original (evita conteúdo duplicado no Google)', 'default' => true],
                'imp_credit'   => ['type' => 'toggle', 'label' => 'Aviso de crédito no fim do artigo', 'default' => true],
                'imp_credit_text' => ['type' => 'text', 'label' => 'Texto do crédito', 'default' => 'Este artigo foi publicado originalmente no {fonte}. Leia o original: {link}'],
                'imp_cat_auto' => ['type' => 'toggle', 'label' => 'Classificar nas categorias pelas palavras do título', 'default' => true],
                'imp_cat_keep' => ['type' => 'toggle', 'label' => 'Manter também a categoria padrão', 'default' => false],
                'imp_cat_rules' => ['type' => 'textarea', 'label' => 'Regras de categoria', 'rows' => 8, 'wide' => true, 'default' => '', 'placeholder' => "pre-sal|pré-sal, búzios, tupi\nportos|porto, portos, terminal portuário", 'help' => 'slug-da-categoria|palavras-chave separadas por vírgula.'],
            ],
        ],
    ];
}

function s7x_ia(string $key)
{
    static $defaults = null;
    if ($defaults === null) {
        $defaults = [];
        foreach (s7x_ia_fields() as $g) {
            foreach ($g['fields'] as $k => $f) {
                $defaults[$k] = $f['default'] ?? '';
            }
        }
        $defaults['auto_cats'] = [];
    }
    return cms_module_get('blog-ia', $key, $defaults[$key] ?? '');
}

function s7x_ia_key(): string
{
    return cms_secret_get('anthropic_api_key');
}

function s7x_ia_pixabay_key(): string
{
    return cms_secret_get('pixabay_key');
}

/* -------------------------------------------------------------------------
 * Estado (horários feitos, rodízio, fila, histórico) e registro
 * ---------------------------------------------------------------------- */

function s7x_ia_state(): array
{
    return cms_read_json(cms_module_data_file('blog-ia', 'state'), []) + ['done' => [], 'rot' => 0, 'queue' => [], 'history' => [], 'used_img' => [], 'last_import' => 0, 'backfill' => []];
}

function s7x_ia_save_state(array $s): void
{
    cms_write_json(cms_module_data_file('blog-ia', 'state'), $s);
}

function s7x_ia_log(string $msg, string $type = 'info'): void
{
    $file = cms_module_data_file('blog-ia', 'log');
    $log = cms_read_json($file, []);
    $log[] = ['t' => date('c'), 'type' => $type, 'msg' => mb_substr($msg, 0, 600)];
    cms_write_json($file, array_slice($log, -300));
}

/* -------------------------------------------------------------------------
 * Chamada à API do Claude
 * ---------------------------------------------------------------------- */

function s7x_ia_request(string $system, string $user, int $maxTokens = 8000): string
{
    $key = s7x_ia_key();
    if ($key === '') {
        throw new RuntimeException('Cadastre a chave da API do Claude em Artigos com IA → Configurações.');
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(400);
    }
    $headers = ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01', 'content-type: application/json'];
    if (($ws = cms_secret_get('anthropic_workspace_id')) !== '') {
        $headers[] = 'anthropic-workspace-id: ' . $ws;
    }
    $base = rtrim((string) cms_config('anthropic_base', 'https://api.anthropic.com'), '/');
    $r = cms_http('POST', $base . '/v1/messages', $headers, [
        'model' => (string) s7x_ia('model'), 'max_tokens' => $maxTokens, 'system' => $system,
        'messages' => [['role' => 'user', 'content' => $user]],
    ], 300);
    if ($r['status'] !== 200) {
        $msg = (string) ($r['json']['error']['message'] ?? ($r['error'] ?: 'erro desconhecido'));
        $map = [401 => 'Chave da API inválida.', 403 => 'A chave não tem permissão para este modelo.', 429 => 'Limite de uso atingido. Aguarde um pouco e tente de novo.', 529 => 'A API está sobrecarregada. Tente de novo em alguns minutos.'];
        $friendly = $map[$r['status']] ?? '';
        if (stripos($msg, 'workspace') !== false) {
            $friendly = 'Esta chave precisa do ID do workspace. Preencha o campo "Workspace ID" nas configurações.';
        }
        if (stripos($msg, 'credit balance') !== false) {
            $friendly = 'Sem créditos na conta da API. Adicione saldo em platform.claude.com → Settings → Billing.';
        }
        throw new RuntimeException(trim($friendly . ' (HTTP ' . ($r['status'] ?: 'sem conexão') . ': ' . $msg . ')'));
    }
    $text = '';
    foreach ((array) ($r['json']['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= (string) $block['text'];
        }
    }
    if (($r['json']['stop_reason'] ?? '') === 'max_tokens') {
        s7x_ia_log('Resposta cortada pelo limite de tamanho. Reduza o número de palavras.', 'aviso');
    }
    return $text;
}

function s7x_ia_json(string $text): ?array
{
    $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
    $data = json_decode($text, true);
    if (is_array($data)) {
        return $data;
    }
    $a = strpos($text, '{');
    $b = strrpos($text, '}');
    if ($a !== false && $b !== false && $b > $a) {
        $data = json_decode(substr($text, $a, $b - $a + 1), true);
    }
    return is_array($data) ? $data : null;
}

/* -------------------------------------------------------------------------
 * Categorias e contexto
 * ---------------------------------------------------------------------- */

/** Categorias que fazem sentido para gerar artigos. */
function s7x_ia_categories(): array
{
    $skip = ['uncategorized', 'sem-categoria', 'teste', 'blog', 'noticias', 'novidades'];
    return array_filter(s7x_blog_categories(true), fn ($c) => !in_array($c['slug'], $skip, true));
}

function s7x_ia_category_context(string $cat): string
{
    if ($cat === '') {
        return '';
    }
    $txt = 'Categoria do blog: ' . s7x_blog_term_name('categories', $cat) . ".\n";
    $titles = array_map(fn ($p) => $p['title'], array_slice(s7x_blog_query(['status' => 'any', 'cat' => $cat]), 0, 25));
    if ($titles) {
        $txt .= "Títulos já publicados nesta categoria (não repita estes temas; use-os para entender o assunto e o vocabulário da categoria):\n- " . implode("\n- ", $titles) . "\n";
    }
    return $txt;
}

/* -------------------------------------------------------------------------
 * SEO: regras para a IA e pós-processamento
 * ---------------------------------------------------------------------- */

function s7x_ia_sources(): array
{
    $out = [];
    foreach (preg_split('/\R/', (string) s7x_ia('seo_sources')) ?: [] as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        if (preg_match('#^https?://#', $parts[0])) {
            $out[rtrim($parts[0], '/')] = $parts[1] ?? $parts[0];
        }
    }
    return $out;
}

function s7x_ia_seo_rules(): string
{
    if (!s7x_ia('seo_mode')) {
        return '';
    }
    $r = "\n\nREGRAS DE SEO (obrigatórias — o artigo precisa atingir nota máxima em ferramentas como Rank Math / Yoast):\n";
    $r .= "- Escolha UMA palavra-chave principal (focus_keyword) de 2 a 4 palavras, como as pessoas pesquisam no Google, em minúsculas.\n";
    $r .= "- seo_title: até 60 caracteres, começando pela palavra-chave, com um número (ex.: 5 dicas, 7 passos, 10 erros) e uma palavra poderosa (guia, completo, essencial, definitivo, prático).\n";
    $r .= "- title (título do post, H1): pode ser igual ao seo_title ou um pouco mais longo, contendo a palavra-chave no início.\n";
    $r .= "- excerpt (meta description): entre 130 e 155 caracteres, contendo a palavra-chave e um convite à leitura.\n";
    $r .= "- slug: curto (até 5 palavras), só a palavra-chave em minúsculas com hífens, sem acentos e sem stopwords.\n";
    $r .= "- A palavra-chave deve aparecer na PRIMEIRA frase do primeiro parágrafo, em pelo menos 2 subtítulos <h2> ou <h3>, e com densidade de cerca de 1% a 1,5% no texto (sem exagero). Use também variações e sinônimos (secondary_keywords).\n";
    $r .= "- Parágrafos curtos (no máximo 3 frases), frases de até 20 palavras, voz ativa, palavras de transição (além disso, portanto, por exemplo, ou seja, em resumo, por outro lado).\n";
    $r .= "- Use pelo menos 5 subtítulos <h2>, com <h3> quando fizer sentido, e ao menos uma lista <ul> ou <ol>.\n";
    $r .= "- Termine com um <h2>Conclusão</h2> que use a palavra-chave.\n";
    if (s7x_ia('seo_faq')) {
        $r .= "- Inclua no JSON um campo faq com 4 a 5 perguntas frequentes reais sobre o tema (pergunta e resposta de 2 a 3 frases). NÃO escreva a seção de FAQ dentro de content; ela é adicionada automaticamente.\n";
    }
    $src = s7x_ia_sources();
    if (s7x_ia('seo_external') && $src) {
        $list = [];
        foreach ($src as $u => $n) {
            $list[] = $u . ' (' . $n . ')';
        }
        $r .= "- Inclua no content 1 ou 2 links externos (<a href=\"...\">) para fontes oficiais, usando SOMENTE estes endereços, exatamente como estão: " . implode('; ', $list) . ". Não crie nenhum outro link externo.\n";
    } else {
        $r .= "- Não crie links externos.\n";
    }
    $r .= "- Não insira links internos, imagens nem sumário; isso é feito automaticamente.\n";
    return $r;
}

function s7x_ia_json_fields(): string
{
    if (!s7x_ia('seo_mode')) {
        return '';
    }
    return ', "focus_keyword": "palavra-chave principal", "secondary_keywords": ["variação 1", "variação 2"], "seo_title": "título SEO até 60 caracteres", "slug": "slug-curto"'
        . (s7x_ia('seo_faq') ? ', "faq": [{"q": "pergunta", "a": "resposta"}]' : '');
}

/** Remove links externos que não estejam na lista de fontes. */
function s7x_ia_clean_links(string $html): string
{
    $allowed = array_keys(s7x_ia_sources());
    $home = strtolower((string) parse_url(cms_site_url(), PHP_URL_HOST));
    return (string) preg_replace_callback('#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function ($m) use ($allowed, $home) {
        $url = $m[1];
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($url[0] === '/' || $url[0] === '#' || ($host !== '' && $host === $home)) {
            return $m[0];
        }
        foreach ($allowed as $ok) {
            if (strpos(rtrim($url, '/'), $ok) === 0) {
                return '<a href="' . e($url) . '" target="_blank" rel="noopener">' . $m[2] . '</a>';
            }
        }
        return $m[2];
    }, $html);
}

/** IDs nos H2 e sumário clicável depois do primeiro parágrafo. */
function s7x_ia_add_toc(string $html): string
{
    $items = [];
    $used = [];
    $html = (string) preg_replace_callback('#<h2([^>]*)>(.*?)</h2>#is', function ($m) use (&$items, &$used) {
        $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8'));
        if (preg_match('/\bid=["\']([^"\']+)["\']/i', $m[1], $idm)) {
            $used[$idm[1]] = true;
            $items[] = [$idm[1], $text];
            return $m[0];
        }
        $id = cms_slug($text) ?: 'secao';
        $base = $id;
        $n = 2;
        while (isset($used[$id])) {
            $id = $base . '-' . $n++;
        }
        $used[$id] = true;
        $items[] = [$id, $text];
        return '<h2' . $m[1] . ' id="' . e($id) . '">' . $m[2] . '</h2>';
    }, $html);
    if (!s7x_ia('seo_toc') || count($items) < 3) {
        return $html;
    }
    $toc = '<nav class="shc-toc" aria-label="Sumário"><p class="shc-toc__title"><strong>Neste artigo</strong></p><ol>';
    foreach ($items as $it) {
        $toc .= '<li><a href="#' . e($it[0]) . '">' . e($it[1]) . '</a></li>';
    }
    $toc .= '</ol></nav>';
    $pos = stripos($html, '</p>');
    return $pos === false ? $toc . $html : substr($html, 0, $pos + 4) . "\n" . $toc . "\n" . substr($html, $pos + 4);
}

/** "Leia também" no meio e "Veja mais" antes da conclusão, com posts do próprio blog. */
function s7x_ia_internal_links(string $html, string $postId, string $keyword, string $cat): string
{
    $limit = (int) s7x_ia('seo_internal');
    if ($limit < 1) {
        return $html;
    }
    $found = [];
    if ($keyword !== '') {
        $found = array_keys(array_slice(s7x_blog_query(['search' => $keyword, 'exclude' => [$postId]]), 0, $limit, true));
    }
    if (count($found) < $limit && $cat !== '') {
        foreach (s7x_blog_query(['cat' => $cat, 'exclude' => array_merge([$postId], $found)]) as $id => $p) {
            $found[] = $id;
            if (count($found) >= $limit) {
                break;
            }
        }
    }
    if (!$found) {
        return $html;
    }
    $index = s7x_blog_index();
    $links = [];
    foreach ($found as $id) {
        $p = $index['posts'][$id];
        $links[] = '<a href="' . e(s7x_blog_post_path($p)) . '">' . e($p['title']) . '</a>';
    }
    $first = '<p class="shc-related"><strong>Leia também:</strong> ' . array_shift($links) . '</p>';
    $rest = $links ? '<p class="shc-related"><strong>Veja mais:</strong> ' . implode(' · ', $links) . '</p>' : '';
    $parts = preg_split('#(</p>)#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $total = (int) floor(count($parts) / 2);
    $at = max(2, (int) floor($total * 0.35));
    $out = '';
    $count = 0;
    foreach ($parts as $piece) {
        $out .= $piece;
        if (strcasecmp($piece, '</p>') === 0 && ++$count === $at) {
            $out .= "\n" . $first . "\n";
        }
    }
    if ($rest !== '') {
        $pos = strripos($out, '<h2');
        $out = $pos === false ? $out . $rest : substr($out, 0, $pos) . $rest . "\n" . substr($out, $pos);
    }
    return $out;
}

function s7x_ia_faq_html(array $faq): string
{
    if (!$faq) {
        return '';
    }
    $h = '<h2 id="perguntas-frequentes">Perguntas frequentes</h2><div class="shc-faq">';
    foreach ($faq as $f) {
        $h .= '<h3>' . e($f['q']) . '</h3><p>' . e($f['a']) . '</p>';
    }
    return $h . '</div>';
}

/* -------------------------------------------------------------------------
 * Gerar um artigo
 * ---------------------------------------------------------------------- */

/**
 * Gera o artigo. $args: keywords, words, extra, category (slug), base (texto-base)
 * Retorna title, excerpt, tags, content, seo, images.
 */
function s7x_ia_generate(string $topic, array $args = []): array
{
    $args += ['keywords' => '', 'words' => (int) s7x_ia('words'), 'extra' => '', 'category' => '', 'base' => ''];
    $topic = trim(strip_tags($topic));
    $base = trim(strip_tags((string) $args['base']));
    if ($topic === '' && $base === '') {
        throw new RuntimeException('Informe o tema do artigo (ou cole um texto-base).');
    }
    $system = (trim((string) s7x_ia('instructions')) ?: s7x_ia_default_instructions())
        . "\n\nResponda SOMENTE com um objeto JSON válido, sem texto antes ou depois, sem blocos de código, no formato:\n"
        . '{"title": "título com até 65 caracteres", "excerpt": "meta description com até 155 caracteres", "tags": ["tag1", "tag2", "tag3"], "image_queries": ["2 a 4 termos de busca EM INGLÊS para fotos de banco de imagens que ilustrem o artigo, do mais específico ao mais genérico"], "content": "corpo do artigo em HTML"' . s7x_ia_json_fields() . '}' . "\n"
        . 'No campo content use apenas as tags <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a> e <blockquote>. Não repita o título no corpo e não use <h1>.'
        . s7x_ia_seo_rules();
    $user = s7x_ia_category_context((string) $args['category']);
    $user .= $topic !== '' ? 'Tema do artigo: ' . $topic . "\n" : "Tema do artigo: defina a partir do texto-base.\n";
    if (trim((string) $args['keywords']) !== '') {
        $user .= 'Palavras-chave para SEO (use de forma natural): ' . trim(strip_tags((string) $args['keywords'])) . "\n";
    }
    $words = max(300, min(4000, (int) $args['words'] ?: 1200));
    $user .= 'Tamanho aproximado: ' . $words . " palavras.\n";
    if (trim((string) $args['extra']) !== '') {
        $user .= 'Orientações adicionais: ' . trim(strip_tags((string) $args['extra'])) . "\n";
    }
    if ($base !== '') {
        $user .= "\nTexto-base (fonte dos fatos; reescreva com palavras próprias, não copie frases, e não acrescente fatos que não estejam nele):\n<<<\n" . mb_substr($base, 0, 30000) . "\n>>>\n";
    }
    $json = s7x_ia_json(s7x_ia_request($system, $user, $words > 1800 ? 16000 : 8000));
    if (!$json || empty($json['title']) || empty($json['content'])) {
        throw new RuntimeException('A IA respondeu num formato inesperado. Tente gerar de novo.');
    }
    $faq = [];
    foreach ((array) ($json['faq'] ?? []) as $f) {
        if (!empty($f['q']) && !empty($f['a'])) {
            $faq[] = ['q' => trim(strip_tags((string) $f['q'])), 'a' => trim(strip_tags((string) $f['a']))];
        }
    }
    return [
        'title'   => trim(strip_tags((string) $json['title'])),
        'excerpt' => trim(strip_tags((string) ($json['excerpt'] ?? ''))),
        'tags'    => array_slice(array_filter(array_map(fn ($t) => trim(strip_tags((string) $t)), (array) ($json['tags'] ?? []))), 0, 8),
        'content' => (string) $json['content'],
        'images'  => array_slice(array_values(array_filter(array_map(fn ($t) => trim(strip_tags((string) $t)), (array) ($json['image_queries'] ?? [])))), 0, 4),
        'seo'     => [
            'focus_keyword' => mb_strtolower(trim(strip_tags((string) ($json['focus_keyword'] ?? '')))),
            'secondary'     => array_slice(array_map('strval', (array) ($json['secondary_keywords'] ?? [])), 0, 5),
            'seo_title'     => trim(strip_tags((string) ($json['seo_title'] ?? ''))),
            'slug'          => cms_slug((string) ($json['slug'] ?? '')),
            'faq'           => array_slice($faq, 0, 6),
        ],
    ];
}

/** Cria o post a partir do resultado da IA (SEO, links, FAQ, sumário e imagens). Retorna o id. */
function s7x_ia_create_post(array $res, string $cat = '', string $status = 'draft', string $origin = 'painel'): string
{
    $tags = array_values(array_filter(array_map(fn ($t) => s7x_blog_ensure_term('tags', $t), $res['tags'])));
    $seo = $res['seo'] ?? [];
    $content = s7x_ia_clean_links((string) $res['content']);
    $id = s7x_blog_save([
        'title' => $res['title'], 'slug' => (string) ($seo['slug'] ?? '') ?: $res['title'], 'content' => $content, 'excerpt' => $res['excerpt'],
        'status' => 'draft', 'cats' => $cat !== '' ? [$cat] : [], 'tags' => $tags, 'source' => 'ia',
        'seo' => ['title' => (string) ($seo['seo_title'] ?? ''), 'description' => $res['excerpt'], 'focus_keyword' => (string) ($seo['focus_keyword'] ?? '')],
        'faq' => s7x_ia('seo_mode') && s7x_ia('seo_faq') ? (array) ($seo['faq'] ?? []) : [],
    ]);
    // Pós-processamento que depende do id (links internos sem apontar para si mesmo)
    if (s7x_ia('seo_mode')) {
        $post = s7x_blog_get($id);
        $html = (string) $post['content'];
        if (s7x_ia('seo_faq') && !empty($seo['faq']) && strpos($html, 'shc-faq') === false) {
            $faq = s7x_ia_faq_html($seo['faq']);
            $pos = strripos($html, '<h2');
            $html = $pos === false ? $html . $faq : substr($html, 0, $pos) . $faq . "\n" . substr($html, $pos);
        }
        $html = s7x_ia_internal_links($html, $id, (string) ($seo['focus_keyword'] ?? ''), $cat);
        $html = s7x_ia_add_toc($html);
        s7x_blog_save(['content' => $html], $id);
    }
    s7x_ia_attach_images($id, $res['images'] ?? [], $cat);
    $final = in_array($status, ['publish', 'draft', 'pending'], true) ? $status : 'draft';
    if ($final !== 'draft') {
        s7x_blog_save(['status' => $final, 'date' => date('c')], $id);
    }
    $state = s7x_ia_state();
    $state['history'][] = ['t' => date('c'), 'id' => $id, 'origin' => $origin];
    $state['history'] = array_slice($state['history'], -60);
    s7x_ia_save_state($state);
    return $id;
}

/** Sugere pautas novas para uma categoria. */
function s7x_ia_suggest(string $cat, int $count = 10): array
{
    $count = max(1, min(30, $count));
    $ctx = s7x_ia_category_context($cat) ?: "Categoria: temas gerais do blog.\n";
    $system = (trim((string) s7x_ia('instructions')) ?: s7x_ia_default_instructions()) . "\n\nResponda SOMENTE com JSON válido no formato {\"topics\": [\"tema 1\", \"tema 2\"]}.";
    $user = $ctx . "\nSugira {$count} pautas de artigos explicativos e atemporais para esta categoria, que ainda não foram cobertas pelos títulos acima e que interessem ao público do blog. Cada pauta em uma frase curta, pronta para virar título. Não sugira notícias nem fatos com data.";
    $json = s7x_ia_json(s7x_ia_request($system, $user, 2000));
    if (empty($json['topics']) || !is_array($json['topics'])) {
        throw new RuntimeException('Não foi possível ler as sugestões. Tente de novo.');
    }
    return array_slice(array_values(array_filter(array_map(fn ($t) => trim(strip_tags((string) $t)), $json['topics']))), 0, $count);
}

/* -------------------------------------------------------------------------
 * Imagens do Pixabay
 * ---------------------------------------------------------------------- */

function s7x_ia_category_queries(string $cat): array
{
    $general = [];
    $out = [];
    foreach (preg_split('/\R/', (string) s7x_ia('img_queries')) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (strpos($line, '|') === false) {
            $general = array_merge($general, array_map('trim', explode(',', $line)));
            continue;
        }
        [$slug, $terms] = array_map('trim', explode('|', $line, 2));
        if ($slug === $cat) {
            $out = array_merge($out, array_map('trim', explode(',', $terms)));
        }
    }
    return array_values(array_filter(array_merge($out, $general)));
}

function s7x_ia_pixabay_search(string $q): array
{
    $q = trim(mb_substr($q, 0, 100));
    if ($q === '' || s7x_ia_pixabay_key() === '') {
        return [];
    }
    $cacheFile = cms_module_data_file('blog-ia', 'pxb');
    $cache = cms_read_json($cacheFile, []);
    $key = md5(mb_strtolower($q) . '|' . (int) s7x_ia('img_min_width'));
    if (isset($cache[$key]) && $cache[$key]['t'] > time() - 86400) {
        return $cache[$key]['hits']; // o Pixabay pede cache de 24 h
    }
    $url = 'https://pixabay.com/api/?' . http_build_query([
        'key' => s7x_ia_pixabay_key(), 'q' => $q, 'image_type' => 'photo', 'orientation' => 'horizontal', 'safesearch' => 'true',
        'min_width' => max(640, (int) s7x_ia('img_min_width')), 'per_page' => 30, 'lang' => 'en',
    ]);
    $r = cms_http('GET', $url, [], null, 20);
    if ($r['status'] !== 200) {
        throw new RuntimeException($r['status'] === 400 && stripos($r['body'], 'key') !== false ? 'Chave do Pixabay inválida.' : 'Pixabay respondeu HTTP ' . ($r['status'] ?: 'sem conexão') . '.');
    }
    $hits = [];
    foreach ((array) ($r['json']['hits'] ?? []) as $h) {
        if (!empty($h['id']) && !empty($h['largeImageURL'])) {
            $hits[] = ['id' => (int) $h['id'], 'url' => (string) $h['largeImageURL'], 'page' => (string) ($h['pageURL'] ?? ''), 'user' => (string) ($h['user'] ?? ''), 'user_id' => (int) ($h['user_id'] ?? 0)];
        }
    }
    foreach ($cache as $k => $v) {
        if ($v['t'] < time() - 86400) {
            unset($cache[$k]);
        }
    }
    $cache[$key] = ['t' => time(), 'hits' => $hits];
    cms_write_json($cacheFile, $cache);
    return $hits;
}

/** Até $count fotos diferentes e ainda não usadas no site. */
function s7x_ia_pick_images(array $queries, int $count): array
{
    $state = s7x_ia_state();
    $used = array_map('intval', (array) $state['used_img']);
    $picked = [];
    foreach (array_unique(array_filter($queries)) as $q) {
        foreach (s7x_ia_pixabay_search($q) as $h) {
            if (in_array($h['id'], $used, true) || isset($picked[$h['id']])) {
                continue;
            }
            $picked[$h['id']] = $h;
            if (count($picked) >= $count) {
                break 2;
            }
        }
    }
    if ($picked) {
        $state = s7x_ia_state();
        $state['used_img'] = array_slice(array_merge((array) $state['used_img'], array_keys($picked)), -1000);
        s7x_ia_save_state($state);
    }
    return array_values($picked);
}

function s7x_ia_credit(array $hit): string
{
    $user = '<a href="' . e('https://pixabay.com/users/' . rawurlencode($hit['user']) . '-' . $hit['user_id'] . '/') . '" target="_blank" rel="noopener nofollow">' . e($hit['user'] ?: 'Pixabay') . '</a>';
    $text = e((string) s7x_ia('img_credit_text'));
    $text = str_replace('{autor}', $user, $text);
    return str_replace('Pixabay', '<a href="' . e($hit['page'] ?: 'https://pixabay.com/') . '" target="_blank" rel="noopener nofollow">Pixabay</a>', $text);
}

/** Coloca a imagem de destaque e/ou a do meio do texto. Retorna quantas entraram. */
function s7x_ia_attach_images(string $id, array $queries = [], string $cat = '', ?bool $featured = null, ?bool $inline = null): int
{
    if (s7x_ia_pixabay_key() === '') {
        return 0;
    }
    $post = s7x_blog_get($id);
    if (!$post) {
        return 0;
    }
    $featured = $featured ?? (bool) s7x_ia('img_featured');
    $inline = $inline ?? (bool) s7x_ia('img_inline');
    $needF = $featured && cms_image_value($post['image'])['src'] === '';
    $needI = $inline && strpos((string) $post['content'], 'shc-figure') === false;
    $count = ($needF ? 1 : 0) + ($needI ? 1 : 0);
    if (!$count) {
        return 0;
    }
    $cat = $cat ?: s7x_blog_primary_cat($post);
    try {
        $hits = s7x_ia_pick_images(array_merge($queries, s7x_ia_category_queries($cat)), $count);
    } catch (RuntimeException $e) {
        s7x_ia_log('Pixabay: ' . $e->getMessage(), 'erro');
        return 0;
    }
    if (!$hits) {
        s7x_ia_log('Pixabay: nenhuma foto nova encontrada para "' . $post['title'] . '".', 'aviso');
        return 0;
    }
    $kw = (string) ($post['seo']['focus_keyword'] ?? '');
    $alt = $kw !== '' && mb_stripos($post['title'], $kw) === false ? mb_strtoupper(mb_substr($kw, 0, 1)) . mb_substr($kw, 1) . ' — ' . $post['title'] : $post['title'];
    $added = 0;
    $update = [];
    if ($needF) {
        $hit = array_shift($hits);
        $src = s7x_blog_sideload($hit['url'], $post['slug'] . '-destaque', $alt);
        if ($src !== '') {
            $update['image'] = ['src' => $src, 'alt' => $alt];
            $update['image_credit'] = s7x_ia('img_credit') ? s7x_ia_credit($hit) : '';
            $added++;
        }
    }
    if ($needI && $hits) {
        $hit = array_shift($hits);
        $src = s7x_blog_sideload($hit['url'], $post['slug'] . '-imagem', $alt);
        if ($src !== '') {
            $fig = '<figure class="shc-figure"><img src="/' . e($src) . '" alt="' . e($alt) . '">' . (s7x_ia('img_credit') ? '<figcaption>' . s7x_ia_credit($hit) . '</figcaption>' : '') . '</figure>';
            $html = (string) $post['content'];
            $n = preg_match_all('#<h2[\s>]#i', $html, $m, PREG_OFFSET_CAPTURE);
            if ($n >= 2) {
                $at = $m[0][(int) floor($n / 2)][1];
                $html = substr($html, 0, $at) . $fig . "\n" . substr($html, $at);
            } else {
                $html .= "\n" . $fig;
            }
            $update['content'] = $html;
            $added++;
        }
    }
    if ($update) {
        s7x_blog_save($update, $id);
        s7x_ia_log('Pixabay: ' . $added . ' imagem(ns) em "' . $post['title'] . '".', 'ok');
    }
    return $added;
}

/** Termos de busca de foto a partir do título (para posts antigos). */
function s7x_ia_queries_for(array $post): array
{
    if (s7x_ia_key() === '') {
        return [];
    }
    try {
        $json = s7x_ia_json(s7x_ia_request(
            'Você escolhe fotos de banco de imagens. Responda SOMENTE com JSON válido no formato {"queries": ["termo 1", "termo 2", "termo 3"]}.',
            "Título do artigo: {$post['title']}\nDê 3 termos de busca EM INGLÊS, curtos (2 a 4 palavras), do mais específico ao mais genérico, para encontrar uma foto que ilustre este artigo no Pixabay. Não use nomes de empresas nem de pessoas.",
            200
        ));
    } catch (RuntimeException $e) {
        return [];
    }
    return array_slice(array_values(array_filter(array_map('strval', (array) ($json['queries'] ?? [])))), 0, 3);
}

/* -------------------------------------------------------------------------
 * Automação diária
 * ---------------------------------------------------------------------- */

function s7x_ia_slots(): array
{
    preg_match_all('/\b([01]?\d|2[0-3])[:h]([0-5]\d)\b/', (string) s7x_ia('auto_times'), $m, PREG_SET_ORDER);
    $slots = [];
    foreach ($m as $x) {
        $slots[] = sprintf('%02d:%02d', (int) $x[1], (int) $x[2]);
    }
    $slots = array_values(array_unique($slots));
    sort($slots);
    return $slots;
}

function s7x_ia_done_today(): array
{
    return (array) (s7x_ia_state()['done'][date('Y-m-d')] ?? []);
}

function s7x_ia_mark(string $slot, string $status): void
{
    $s = s7x_ia_state();
    $today = date('Y-m-d');
    $done = [$today => (array) ($s['done'][$today] ?? [])];
    $prev = $done[$today][$slot] ?? ['tries' => 0];
    $done[$today][$slot] = ['status' => $status, 'tries' => (int) $prev['tries'] + ($status === 'erro' ? 1 : 0), 't' => date('H:i')];
    $s['done'] = $done;
    s7x_ia_save_state($s);
}

function s7x_ia_due_slot(): ?string
{
    $now = date('H:i');
    $done = s7x_ia_done_today();
    foreach (s7x_ia_slots() as $slot) {
        if ($slot > $now) {
            break;
        }
        $st = $done[$slot] ?? null;
        if (!$st || ($st['status'] === 'erro' && $st['tries'] < 3) || ($st['status'] === 'rodando' && ($st['t'] ?? '') < date('H:i', time() - 1800))) {
            return $slot;
        }
    }
    return null;
}

function s7x_ia_auto_cats(): array
{
    $sel = array_values(array_filter((array) s7x_ia('auto_cats')));
    $all = array_keys(s7x_ia_categories());
    $sel = array_values(array_intersect($sel, $all));
    return $sel ?: $all;
}

/** Próxima pauta: fila manual primeiro; depois rodízio de categorias com sugestões da IA. */
function s7x_ia_next_topic(): array
{
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) s7x_ia('auto_topics')) ?: [])));
    if ($lines) {
        $line = array_shift($lines);
        $c = cms_content();
        $c['modules']['blog-ia']['auto_topics'] = implode("\n", $lines);
        cms_save_content($c, 'Artigos com IA: pauta usada');
        $cat = '';
        if (strpos($line, '|') !== false) {
            [$slug, $line] = array_map('trim', explode('|', $line, 2));
            $cat = isset(s7x_blog_terms()['categories'][cms_slug($slug)]) ? cms_slug($slug) : '';
        }
        return ['topic' => $line, 'cat' => $cat !== '' ? $cat : s7x_ia_next_cat()];
    }
    $cat = s7x_ia_next_cat();
    $s = s7x_ia_state();
    if (empty($s['queue'][$cat])) {
        $s['queue'][$cat] = s7x_ia_suggest($cat, 8);
    }
    $topic = array_shift($s['queue'][$cat]);
    s7x_ia_save_state($s);
    return ['topic' => (string) $topic, 'cat' => $cat];
}

function s7x_ia_next_cat(): string
{
    $cats = s7x_ia_auto_cats();
    if (!$cats) {
        return '';
    }
    $s = s7x_ia_state();
    $i = (int) $s['rot'];
    $s['rot'] = ($i + 1) % count($cats);
    s7x_ia_save_state($s);
    return $cats[$i % count($cats)];
}

/** Gera e publica um artigo da automação. */
function s7x_ia_auto_one(string $origin): string
{
    $pick = s7x_ia_next_topic();
    $res = s7x_ia_generate($pick['topic'], ['category' => $pick['cat'], 'words' => (int) s7x_ia('auto_words')]);
    $id = s7x_ia_create_post($res, $pick['cat'], (string) s7x_ia('auto_status'), $origin);
    s7x_ia_log('Automação: "' . $res['title'] . '" criado (' . s7x_blog_status_label(s7x_blog_get($id)) . ', ' . s7x_blog_term_name('categories', $pick['cat']) . ').', 'ok');
    return $id;
}

/* -------------------------------------------------------------------------
 * Rede de blogs: "Leia também na nossa rede"
 * ---------------------------------------------------------------------- */

function s7x_ia_net_sites(): array
{
    $out = [];
    foreach (preg_split('/\R/', (string) s7x_ia('net_sites')) ?: [] as $line) {
        $p = array_map('trim', explode('|', $line, 2));
        if (count($p) === 2 && preg_match('#^https?://#', $p[1]) && stripos($p[1], (string) parse_url(cms_site_url(), PHP_URL_HOST)) === false) {
            $out[] = ['name' => $p[0], 'url' => rtrim($p[1], '/')];
        }
    }
    return $out;
}

function s7x_ia_net_posts(int $count, string $search = ''): array
{
    $sites = s7x_ia_net_sites();
    if (!$sites) {
        return [];
    }
    $file = cms_module_data_file('blog-ia', 'net');
    $cache = cms_read_json($file, []);
    $key = md5(json_encode([$sites, $count, $search]));
    if (isset($cache[$key]) && $cache[$key]['until'] > time()) {
        return $cache[$key]['items'];
    }
    $items = [];
    foreach ($sites as $site) {
        $url = $site['url'] . '/wp-json/wp/v2/posts?_embed=wp:featuredmedia&orderby=date&order=desc&per_page=' . max(3, $count) . ($search !== '' ? '&search=' . rawurlencode($search) : '');
        $r = cms_http('GET', $url, ['Accept: application/json'], null, 8);
        if ($r['status'] !== 200 || !is_array($r['json'])) {
            continue;
        }
        foreach ($r['json'] as $p) {
            if (empty($p['link'])) {
                continue;
            }
            $m = $p['_embedded']['wp:featuredmedia'][0] ?? [];
            $img = $m['media_details']['sizes']['medium_large']['source_url'] ?? $m['media_details']['sizes']['medium']['source_url'] ?? $m['source_url'] ?? '';
            $items[$site['name']][] = ['title' => html_entity_decode(strip_tags((string) ($p['title']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8'), 'link' => (string) $p['link'], 'date' => (string) ($p['date_gmt'] ?? ''), 'image' => (string) $img, 'site' => $site['name']];
        }
    }
    // Intercala os sites
    $mixed = [];
    while (count($mixed) < $count && $items) {
        foreach ($items as $k => &$list) {
            if (!$list) {
                unset($items[$k]);
                continue;
            }
            $mixed[] = array_shift($list);
            if (count($mixed) >= $count) {
                break;
            }
        }
        unset($list);
    }
    $cache[$key] = ['until' => time() + ($mixed ? 3600 : 600), 'items' => $mixed];
    cms_write_json($file, array_slice($cache, -20, null, true));
    return $mixed;
}

function s7x_ia_net_box(): string
{
    $count = max(1, min(12, (int) s7x_ia('net_count')));
    $items = s7x_ia_net_posts($count, trim((string) s7x_ia('net_search')));
    if (!$items && trim((string) s7x_ia('net_search')) !== '') {
        $items = s7x_ia_net_posts($count);
    }
    if (!$items) {
        return '';
    }
    $h = '<section class="blog-net"><h2 class="blog-net__title">' . e((string) s7x_ia('net_title')) . '</h2><div class="blog-net__grid">';
    foreach ($items as $it) {
        $h .= '<a class="blog-net__card" href="' . e($it['link']) . '" target="_blank" rel="noopener">'
            . ($it['image'] !== '' ? '<span class="blog-net__img"><img src="' . e($it['image']) . '" alt="" loading="lazy"></span>' : '')
            . '<span class="blog-net__site">' . e($it['site']) . '</span><span class="blog-net__name">' . e($it['title']) . '</span></a>';
    }
    return $h . '</div></section>';
}

/* -------------------------------------------------------------------------
 * Importar artigos de outro site da rede
 * ---------------------------------------------------------------------- */

function s7x_ia_import_fetch(): array
{
    $src = rtrim((string) s7x_ia('imp_source'), '/');
    if (!preg_match('#^https?://#', $src)) {
        throw new RuntimeException('Informe o site de origem.');
    }
    $api = $src . '/wp-json/wp/v2';
    $terms = array_filter(array_map('trim', explode(',', (string) s7x_ia('imp_terms')))) ?: ['offshore'];
    $per = max(1, min(50, (int) s7x_ia('imp_per_run') * 2));
    $base = $api . '/posts?_embed=1&orderby=date&order=desc&per_page=' . $per;
    $urls = [];
    $mode = (string) s7x_ia('imp_mode');
    if ($mode === 'search') {
        foreach ($terms as $t) {
            $urls[] = $base . '&search=' . rawurlencode($t);
        }
    } else {
        $ep = $mode === 'tag' ? 'tags' : 'categories';
        $ids = [];
        foreach ($terms as $slug) {
            $r = cms_http('GET', $api . '/' . $ep . '?slug=' . rawurlencode(cms_slug($slug)), [], null, 20);
            foreach ((array) $r['json'] as $t) {
                if (isset($t['id'])) {
                    $ids[] = (int) $t['id'];
                }
            }
        }
        if (!$ids) {
            throw new RuntimeException('Nenhuma ' . ($mode === 'tag' ? 'tag' : 'categoria') . ' encontrada na origem com: ' . implode(', ', $terms));
        }
        $urls[] = $base . '&' . $ep . '=' . implode(',', array_unique($ids));
    }
    $posts = [];
    foreach ($urls as $u) {
        $r = cms_http('GET', $u, ['Accept: application/json'], null, 30);
        if ($r['status'] !== 200 || !is_array($r['json'])) {
            throw new RuntimeException('A origem respondeu ' . ($r['status'] ?: 'sem conexão') . '.');
        }
        foreach ($r['json'] as $p) {
            if (isset($p['id'], $p['link'])) {
                $posts[$p['id']] = $p;
            }
        }
    }
    uasort($posts, fn ($a, $b) => strcmp((string) ($b['date_gmt'] ?? ''), (string) ($a['date_gmt'] ?? '')));
    return array_values($posts);
}

function s7x_ia_import_cats(string $text): array
{
    $default = cms_slug((string) s7x_ia('imp_category'));
    if ($default !== '') {
        s7x_blog_ensure_term('categories', ucfirst(str_replace('-', ' ', $default)), $default);
    }
    if (!s7x_ia('imp_cat_auto')) {
        return array_filter([$default]);
    }
    $hay = ' ' . preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(cms_ascii(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')))) . ' ';
    $found = [];
    $cats = s7x_blog_terms()['categories'];
    foreach (preg_split('/\R/', (string) s7x_ia('imp_cat_rules')) ?: [] as $line) {
        $p = explode('|', $line, 2);
        if (count($p) !== 2 || !isset($cats[trim($p[0])])) {
            continue;
        }
        foreach (explode(',', $p[1]) as $kw) {
            $kw = trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(cms_ascii($kw))));
            if ($kw !== '' && strpos($hay, ' ' . $kw . ' ') !== false) {
                $found[] = trim($p[0]);
                break;
            }
        }
    }
    if (!$found || s7x_ia('imp_cat_keep')) {
        $found[] = $default;
    }
    return array_values(array_unique(array_filter($found)));
}

/** Importa os artigos novos da origem. Retorna [importados, pulados]. */
function s7x_ia_import_run(): array
{
    $remote = s7x_ia_import_fetch();
    $known = [];
    foreach (s7x_blog_index()['posts'] as $p) {
        if (!empty($p['source_url'])) {
            $known[$p['source_url']] = true;
        }
    }
    $limit = max(1, (int) s7x_ia('imp_per_run'));
    $srcName = (string) parse_url((string) s7x_ia('imp_source'), PHP_URL_HOST);
    foreach (s7x_ia_net_sites() as $site) {
        if (rtrim($site['url'], '/') === rtrim((string) s7x_ia('imp_source'), '/')) {
            $srcName = $site['name'];
        }
    }
    $imported = 0;
    $skipped = 0;
    foreach ($remote as $item) {
        if ($imported >= $limit) {
            break;
        }
        $link = (string) $item['link'];
        if (isset($known[$link])) {
            $skipped++;
            continue;
        }
        $title = html_entity_decode(strip_tags((string) ($item['title']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8');
        $excerpt = trim(html_entity_decode(strip_tags((string) ($item['excerpt']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8'));
        $content = s7x_ia('imp_content') === 'excerpt'
            ? '<p>' . e($excerpt) . '</p><p><a href="' . e($link) . '" target="_blank" rel="noopener">Continue lendo no ' . e($srcName) . ' →</a></p>'
            : (string) ($item['content']['rendered'] ?? '');
        if (s7x_ia('imp_credit')) {
            $txt = e((string) s7x_ia('imp_credit_text'));
            $txt = str_replace(['{fonte}', '{link}'], [e($srcName), '<a href="' . e($link) . '" target="_blank" rel="noopener">' . e($link) . '</a>'], $txt);
            $content .= '<aside class="shc-credit">' . $txt . '</aside>';
        }
        $image = ['src' => '', 'alt' => ''];
        $m = $item['_embedded']['wp:featuredmedia'][0] ?? null;
        if (is_array($m) && !empty($m['source_url'])) {
            $src = s7x_blog_sideload((string) $m['source_url'], cms_slug($title), $title);
            if ($src !== '') {
                $image = ['src' => $src, 'alt' => trim((string) ($m['alt_text'] ?? '')) ?: $title];
            }
        }
        $id = s7x_blog_save([
            'title' => $title, 'slug' => (string) ($item['slug'] ?? $title), 'content' => $content, 'excerpt' => $excerpt,
            'status' => (string) s7x_ia('imp_status') === 'publish' ? 'publish' : 'draft',
            'cats' => s7x_ia_import_cats($title . ' ' . $excerpt), 'image' => $image, 'source' => 'rede', 'source_url' => $link,
            'seo' => s7x_ia('imp_canonical') ? ['canonical' => $link] : [],
        ]);
        $known[$link] = true;
        $imported++;
        s7x_ia_log('Importado da rede: ' . $title . ' (' . $id . ')', 'ok');
    }
    $s = s7x_ia_state();
    $s['last_import'] = time();
    s7x_ia_save_state($s);
    if (!$imported) {
        s7x_ia_log('Importação da rede: nenhum artigo novo.', 'info');
    }
    return [$imported, $skipped];
}

/* -------------------------------------------------------------------------
 * Tarefas agendadas
 * ---------------------------------------------------------------------- */

function s7x_ia_cron(): void
{
    // 1. Publicação automática (um artigo por horário que já passou)
    if (s7x_ia('auto_enabled') && s7x_ia_key() !== '' && ($slot = s7x_ia_due_slot())) {
        s7x_ia_mark($slot, 'rodando');
        try {
            s7x_ia_auto_one('automação ' . $slot);
            s7x_ia_mark($slot, 'ok');
        } catch (Throwable $e) {
            s7x_ia_mark($slot, 'erro');
            s7x_ia_log('Automação (' . $slot . '): ' . $e->getMessage(), 'erro');
        }
        return; // um trabalho pesado por execução
    }
    // 2. Importação da rede
    if (s7x_ia('imp_enabled') && time() - (int) s7x_ia_state()['last_import'] >= (int) s7x_ia('imp_every') * 3600) {
        try {
            s7x_ia_import_run();
        } catch (Throwable $e) {
            $s = s7x_ia_state();
            $s['last_import'] = time() - (int) s7x_ia('imp_every') * 3600 + 1800; // tenta de novo em 30 min
            s7x_ia_save_state($s);
            s7x_ia_log('Importação da rede: ' . $e->getMessage(), 'erro');
        }
        return;
    }
    // 3. Imagens para posts antigos
    $perDay = (int) s7x_ia('img_backfill');
    if ($perDay > 0 && s7x_ia_pixabay_key() !== '') {
        $s = s7x_ia_state();
        $today = date('Y-m-d');
        $doneToday = (int) ($s['backfill'][$today] ?? 0);
        if ($doneToday >= $perDay) {
            return;
        }
        $tried = (array) ($s['backfill']['tried'] ?? []);
        foreach (s7x_blog_query() as $id => $p) {
            if (cms_image_value($p['image'])['src'] === '' && !in_array($id, $tried, true)) {
                $post = s7x_blog_get($id);
                $n = s7x_ia_attach_images($id, s7x_ia_queries_for($post), '', true, false);
                $s = s7x_ia_state();
                $s['backfill'] = ['tried' => array_slice(array_merge($tried, [$id]), -2000), $today => $doneToday + ($n ? 1 : 0)];
                s7x_ia_save_state($s);
                break;
            }
        }
    }
}
