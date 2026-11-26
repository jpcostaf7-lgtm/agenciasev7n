<?php
/**
 * Analisador de SEO — itens do site como um todo
 * robots.txt, sitemap, http→https, www, llms.txt, cabeçalhos de segurança e PageSpeed Insights.
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

/** Robôs de IA mais comuns (busca e treinamento). */
function s7x_seo_ai_bots(): array
{
    return [
        'GPTBot' => 'ChatGPT (OpenAI)', 'OAI-SearchBot' => 'Busca do ChatGPT', 'ChatGPT-User' => 'ChatGPT ao navegar',
        'ClaudeBot' => 'Claude (Anthropic)', 'Claude-SearchBot' => 'Busca do Claude', 'PerplexityBot' => 'Perplexity',
        'Google-Extended' => 'Gemini (Google)', 'Applebot-Extended' => 'Apple Intelligence', 'CCBot' => 'Common Crawl', 'Bingbot' => 'Bing e Copilot',
    ];
}

/** Lê o robots.txt: grupos por robô, sitemaps e se a página analisada está bloqueada. */
function s7x_seo_robots(string $origin, string $path): array
{
    $r = s7x_seo_fetch($origin . '/robots.txt', ['max_bytes' => 300000, 'timeout' => 10, 'redirects' => 3]);
    $out = ['exists' => false, 'status' => $r['status'], 'sitemaps' => [], 'blocks_all' => false, 'blocks_page' => false, 'ai_blocked' => [], 'lines' => 0, 'error' => $r['error']];
    $ctype = strtolower((string) ($r['headers']['content-type'] ?? ''));
    if ($r['status'] !== 200 || $r['body'] === '' || stripos($r['body'], '<html') !== false || ($ctype && strpos($ctype, 'text/plain') === false && strpos($ctype, 'text/') === false)) {
        return $out;
    }
    $out['exists'] = true;
    $groups = [];
    $agents = [];
    $lastWasAgent = false;
    foreach (preg_split('/\r?\n/', $r['body']) as $line) {
        $line = trim(preg_replace('/#.*$/', '', $line));
        if ($line === '' || strpos($line, ':') === false) {
            continue;
        }
        $out['lines']++;
        [$k, $v] = array_map('trim', explode(':', $line, 2));
        $k = strtolower($k);
        if ($k === 'sitemap') {
            $out['sitemaps'][] = $v;
            continue;
        }
        if ($k === 'user-agent') {
            if (!$lastWasAgent) {
                $agents = [];
            }
            $agents[] = strtolower($v);
            foreach ($agents as $a) {
                $groups[$a] = $groups[$a] ?? ['allow' => [], 'disallow' => []];
            }
            $lastWasAgent = true;
            continue;
        }
        $lastWasAgent = false;
        if (in_array($k, ['allow', 'disallow'], true)) {
            foreach ($agents as $a) {
                if ($v !== '' || $k === 'allow') {
                    $groups[$a][$k][] = $v;
                }
            }
        }
    }
    $blocked = function (string $agent, string $p) use ($groups): bool {
        $g = $groups[strtolower($agent)] ?? $groups['*'] ?? null;
        if (!$g) {
            return false;
        }
        $bestLen = -1;
        $allow = true;
        foreach (['disallow' => false, 'allow' => true] as $kind => $isAllow) {
            foreach ($g[$kind] as $rule) {
                if ($rule === '') {
                    continue;
                }
                $re = '#^' . str_replace(['\\*', '\\$'], ['.*', '$'], preg_quote($rule, '#')) . '#';
                $len = strlen($rule);
                if (preg_match($re, $p) && ($len > $bestLen || ($len === $bestLen && $isAllow))) {
                    $bestLen = $len;
                    $allow = $isAllow;
                }
            }
        }
        return !$allow;
    };
    $out['blocks_all'] = $blocked('Googlebot', '/');
    $out['blocks_page'] = $blocked('Googlebot', $path ?: '/');
    foreach (s7x_seo_ai_bots() as $bot => $label) {
        if (isset($groups[strtolower($bot)]) || isset($groups['*'])) {
            if ($blocked($bot, '/')) {
                $out['ai_blocked'][$bot] = $label;
            }
        }
    }
    $out['sitemaps'] = array_values(array_unique($out['sitemaps']));
    return $out;
}

/** Encontra e lê o sitemap (do robots.txt ou nos endereços mais comuns). */
function s7x_seo_sitemap(string $origin, array $fromRobots): array
{
    $tries = array_merge($fromRobots, [$origin . '/sitemap.xml', $origin . '/sitemap_index.xml', $origin . '/wp-sitemap.xml']);
    $out = ['found' => false, 'url' => '', 'urls' => 0, 'index' => false, 'children' => 0, 'declared' => (bool) $fromRobots, 'lastmod' => '', 'error' => ''];
    foreach (array_slice(array_values(array_unique($tries)), 0, 5) as $u) {
        if (!preg_match('#^https?://#', $u)) {
            continue;
        }
        $r = s7x_seo_fetch($u, ['max_bytes' => 8 * 1024 * 1024, 'timeout' => 15, 'redirects' => 3]);
        if ($r['status'] !== 200 || stripos($r['body'], '<urlset') === false && stripos($r['body'], '<sitemapindex') === false) {
            continue;
        }
        $out['found'] = true;
        $out['url'] = $r['url'];
        if (stripos($r['body'], '<sitemapindex') !== false) {
            $out['index'] = true;
            preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $r['body'], $m);
            $out['children'] = count($m[1]);
            // Soma as URLs dos primeiros sitemaps filhos (o suficiente para dar uma ideia)
            foreach (array_slice($m[1], 0, 4) as $child) {
                $c = s7x_seo_fetch(html_entity_decode($child), ['max_bytes' => 8 * 1024 * 1024, 'timeout' => 15, 'redirects' => 2]);
                $out['urls'] += substr_count(strtolower($c['body']), '<url>');
            }
            $out['urls_partial'] = $out['children'] > 4;
        } else {
            $out['urls'] = substr_count(strtolower($r['body']), '<url>');
        }
        if (preg_match_all('#<lastmod>\s*([^<]+)</lastmod>#i', $r['body'], $lm) && $lm[1]) {
            rsort($lm[1]);
            $out['lastmod'] = substr(trim($lm[1][0]), 0, 10);
        }
        break;
    }
    return $out;
}

/** http→https e www: o site responde num endereço só? */
function s7x_seo_variants(string $finalUrl): array
{
    $p = parse_url($finalUrl);
    $host = strtolower($p['host'] ?? '');
    $bare = preg_replace('/^www\./', '', $host);
    $alt = $host === $bare ? 'www.' . $bare : $bare;
    $out = ['http_to_https' => null, 'http_final' => '', 'alt_host' => $alt, 'alt_ok' => null, 'alt_final' => ''];
    if (($p['scheme'] ?? '') === 'https') {
        $r = s7x_seo_fetch('http://' . $host . '/', ['body' => false, 'timeout' => 10, 'redirects' => 4]);
        $final = $r['chain'] ? end($r['chain'])['url'] : '';
        $out['http_final'] = $final;
        $out['http_to_https'] = $r['error'] === '' || $r['status'] ? stripos($final, 'https://') === 0 : null;
    }
    if (filter_var($host, FILTER_VALIDATE_IP) === false && substr_count($bare, '.') >= 1) {
        $r = s7x_seo_fetch(($p['scheme'] ?? 'https') . '://' . $alt . '/', ['body' => false, 'timeout' => 10, 'redirects' => 4]);
        if ($r['status']) {
            $final = $r['chain'] ? end($r['chain'])['url'] : '';
            $out['alt_final'] = $final;
            $out['alt_ok'] = strtolower((string) parse_url($final, PHP_URL_HOST)) === $host;
        }
    }
    return $out;
}

/** llms.txt (guia do site para IAs). */
function s7x_seo_llms(string $origin): bool
{
    $r = s7x_seo_fetch($origin . '/llms.txt', ['max_bytes' => 200000, 'timeout' => 8, 'redirects' => 2]);
    return $r['status'] === 200 && stripos($r['body'], '<html') === false && strlen(trim($r['body'])) > 20;
}

/** Cabeçalhos de segurança presentes. */
function s7x_seo_security_headers(array $h): array
{
    $list = [
        'strict-transport-security' => 'HSTS (força HTTPS)',
        'x-content-type-options' => 'X-Content-Type-Options',
        'x-frame-options' => 'Proteção contra clickjacking',
        'content-security-policy' => 'Content-Security-Policy',
        'referrer-policy' => 'Referrer-Policy',
        'permissions-policy' => 'Permissions-Policy',
    ];
    $out = [];
    foreach ($list as $k => $label) {
        $has = isset($h[$k]);
        if ($k === 'x-frame-options' && !$has && isset($h['content-security-policy'])) {
            $has = stripos($h['content-security-policy'], 'frame-ancestors') !== false;
        }
        $out[$k] = ['label' => $label, 'ok' => $has];
    }
    return $out;
}

/* -------------------------------------------------------------------------
 * PageSpeed Insights (Google Lighthouse + dados reais do Chrome)
 * ---------------------------------------------------------------------- */

function s7x_seo_psi(string $url, string $strategy, string $reportId): array
{
    $key = cms_secret_get('pagespeed_api_key', '') ?: cms_secret_get('google_api_key', '');
    $cats = $strategy === 'mobile' ? ['performance', 'accessibility', 'best-practices', 'seo'] : ['performance'];
    $q = 'url=' . rawurlencode($url) . '&strategy=' . $strategy . '&locale=pt_BR';
    foreach ($cats as $c) {
        $q .= '&category=' . $c;
    }
    if ($key !== '') {
        $q .= '&key=' . rawurlencode($key);
    }
    $r = cms_http('GET', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . $q, [], null, 110);
    $j = $r['json'];
    if ($r['status'] !== 200 || !is_array($j) || empty($j['lighthouseResult'])) {
        $msg = (string) ($j['error']['message'] ?? $r['error'] ?? '');
        if ($r['status'] === 429) {
            $msg = 'Limite de consultas do Google atingido. Configure uma chave da API PageSpeed no painel.';
        } elseif ($msg === '') {
            $msg = 'O Google não conseguiu medir a velocidade agora (HTTP ' . $r['status'] . ').';
        }
        return ['ok' => false, 'error' => mb_substr($msg, 0, 300)];
    }
    $lh = $j['lighthouseResult'];
    $a = $lh['audits'] ?? [];
    $num = fn (string $id) => isset($a[$id]['numericValue']) ? (float) $a[$id]['numericValue'] : null;
    $disp = fn (string $id) => (string) ($a[$id]['displayValue'] ?? '');
    $out = [
        'ok' => true,
        'scores' => [],
        'lab' => [
            'fcp' => ['v' => $num('first-contentful-paint'), 'd' => $disp('first-contentful-paint')],
            'lcp' => ['v' => $num('largest-contentful-paint'), 'd' => $disp('largest-contentful-paint')],
            'tbt' => ['v' => $num('total-blocking-time'), 'd' => $disp('total-blocking-time')],
            'cls' => ['v' => $num('cumulative-layout-shift'), 'd' => $disp('cumulative-layout-shift')],
            'si'  => ['v' => $num('speed-index'), 'd' => $disp('speed-index')],
            'ttfb' => ['v' => $num('server-response-time'), 'd' => $disp('server-response-time')],
        ],
        'bytes' => (int) ($num('total-byte-weight') ?? 0),
        'requests' => isset($a['network-requests']['details']['items']) ? count($a['network-requests']['details']['items']) : null,
        'field' => null, 'field_scope' => '',
        'opportunities' => [],
        'failed_seo' => [], 'failed_a11y' => [],
        'screenshot' => '',
        'version' => (string) ($lh['lighthouseVersion'] ?? ''),
    ];
    foreach ($lh['categories'] ?? [] as $k => $c) {
        $out['scores'][$k] = isset($c['score']) ? (int) round($c['score'] * 100) : null;
    }
    // Dados reais dos visitantes (CrUX): da página, ou do site todo se a página tiver pouco tráfego
    foreach (['loadingExperience' => 'página', 'originLoadingExperience' => 'site'] as $k => $scope) {
        $m = $j[$k]['metrics'] ?? [];
        if (!$m) {
            continue;
        }
        $get = function (string $id, float $div = 1) use ($m) {
            if (!isset($m[$id]['percentile'])) {
                return null;
            }
            return ['v' => $m[$id]['percentile'] / $div, 'c' => strtolower((string) ($m[$id]['category'] ?? ''))];
        };
        $out['field'] = [
            'lcp' => $get('LARGEST_CONTENTFUL_PAINT_MS'),
            'inp' => $get('INTERACTION_TO_NEXT_PAINT'),
            'cls' => $get('CUMULATIVE_LAYOUT_SHIFT_SCORE', 100),
            'fcp' => $get('FIRST_CONTENTFUL_PAINT_MS'),
            'ttfb' => $get('EXPERIMENTAL_TIME_TO_FIRST_BYTE'),
            'overall' => strtolower((string) ($j[$k]['overall_category'] ?? '')),
        ];
        $out['field_scope'] = $scope;
        break;
    }
    // Oportunidades (Lighthouse 12+: "insights"; versões anteriores: "opportunity")
    $perfRefs = [];
    foreach ($lh['categories']['performance']['auditRefs'] ?? [] as $ref) {
        $perfRefs[$ref['id']] = $ref['group'] ?? '';
    }
    $ops = [];
    foreach ($a as $id => $au) {
        if (!isset($perfRefs[$id]) || in_array($perfRefs[$id], ['metrics', 'hidden'], true)) {
            continue;
        }
        $score = $au['score'] ?? null;
        if ($score === null || $score >= 0.9 || in_array($au['scoreDisplayMode'] ?? '', ['notApplicable', 'manual', 'informative', 'error'], true)) {
            continue;
        }
        $savingMs = (float) ($au['details']['overallSavingsMs'] ?? 0);
        $metric = $au['metricSavings'] ?? [];
        $impact = $savingMs + (float) ($metric['LCP'] ?? 0) + (float) ($metric['FCP'] ?? 0) + (float) ($metric['TBT'] ?? 0) + (float) ($metric['CLS'] ?? 0) * 5000;
        $ops[] = [
            'id' => $id,
            'title' => str_replace('`', '', (string) ($au['title'] ?? $id)),
            'desc' => mb_substr(trim(preg_replace(['/\[([^\]]+)\]\([^)]+\)/', '/\s*Saiba mais[^.]*\.?\s*$/u'], ['$1', ''], (string) ($au['description'] ?? ''))), 0, 260),
            'display' => (string) ($au['displayValue'] ?? ''),
            'saving_ms' => (int) round($savingMs),
            'saving_bytes' => (int) ($au['details']['overallSavingsBytes'] ?? 0),
            'impact' => $impact,
            'score' => $score,
        ];
    }
    usort($ops, fn ($x, $y) => $y['impact'] <=> $x['impact'] ?: $x['score'] <=> $y['score']);
    $out['opportunities'] = array_slice($ops, 0, 8);
    // Itens de SEO e acessibilidade que o Lighthouse reprovou
    foreach (['seo' => 'failed_seo', 'accessibility' => 'failed_a11y'] as $cat => $key) {
        foreach ($lh['categories'][$cat]['auditRefs'] ?? [] as $ref) {
            $au = $a[$ref['id']] ?? null;
            if ($au && ($au['score'] ?? 1) !== null && ($au['score'] ?? 1) < 1 && ($au['scoreDisplayMode'] ?? '') === 'binary' && ($ref['weight'] ?? 0) > 0) {
                $out[$key][] = ['title' => str_replace('`', '', (string) $au['title']), 'weight' => (int) $ref['weight']];
            }
        }
        usort($out[$key], fn ($x, $y) => $y['weight'] <=> $x['weight']);
        $out[$key] = array_slice($out[$key], 0, 8);
    }
    // Print da tela do celular
    $shot = (string) ($a['final-screenshot']['details']['data'] ?? '');
    if (preg_match('#^data:image/(jpeg|webp|png);base64,(.+)$#', $shot, $m)) {
        $bin = base64_decode($m[2], true);
        if ($bin !== false && strlen($bin) < 2 * 1024 * 1024) {
            $dir = CMS_ROOT . '/uploads/analisador';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = $reportId . '-' . $strategy . '.' . ($m[1] === 'jpeg' ? 'jpg' : $m[1]);
            if (@file_put_contents($dir . '/' . $file, $bin)) {
                $out['screenshot'] = 'uploads/analisador/' . $file;
            }
        }
    }
    return $out;
}
