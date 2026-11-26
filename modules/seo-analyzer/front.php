<?php
/**
 * Analisador de SEO — site público: bloco, endereços dos relatórios e pedidos do navegador
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_seo_boot(): void
{
    cms_register_block('seo-analyzer', [
        'label'       => 'Analisador de SEO',
        'icon'        => 'lupa',
        'group'       => 'Ferramentas',
        'description' => 'Campo para o visitante digitar o endereço do site e receber a análise completa (nota, problemas, velocidade, plano de ação e PDF).',
        'template'    => __DIR__ . '/templates/tool.php',
        'fields'      => [
            'eyebrow'     => ['type' => 'text', 'label' => 'Rótulo acima do título', 'default' => 'Ferramenta gratuita'],
            'title'       => ['type' => 'text', 'label' => 'Título', 'default' => 'Descubra por que seu site não aparece no Google'],
            'text'        => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 3, 'default' => 'Mais de 60 verificações em menos de um minuto: indexação, velocidade real medida pelo Google, celular, conteúdo, segurança e se o ChatGPT consegue ler o seu site. Com plano de ação e relatório em PDF.'],
            'placeholder' => ['type' => 'text', 'label' => 'Exemplo no campo do site', 'default' => 'www.seusite.com.br'],
            'keyword'     => ['type' => 'toggle', 'label' => 'Mostrar o campo de palavra-chave', 'default' => true],
            'button'      => ['type' => 'text', 'label' => 'Botão', 'default' => 'Analisar meu site'],
            'note'        => ['type' => 'text', 'label' => 'Aviso abaixo do botão', 'default' => 'Grátis e sem cadastro para ver a nota. Leva cerca de 1 minuto.'],
            'heading'     => ['type' => 'select', 'label' => 'Nível do título', 'default' => 'h1', 'options' => ['h1' => 'H1 (título principal da página)', 'h2' => 'H2']],
        ],
    ]);
    cms_register_block('seo-report', ['label' => 'Relatório do analisador', 'hidden' => true, 'group' => 'Ferramentas', 'template' => __DIR__ . '/templates/report-page.php']);
    cms_add_filter('route', 's7x_seo_route', 20);
    cms_add_action('request', 's7x_seo_request', 2);
    cms_add_action('request', 's7x_seo_pdf_request', 3);
    cms_add_action('head', function (?array $page) {
        if (cms_page_has_block($page, 'seo-analyzer') || !empty($page['seo_report'])) {
            echo '<link rel="stylesheet" href="' . e(cms_module_url('seo-analyzer', 'assets/seo.css')) . '?v=' . e(s7x_seo_asset_v()) . '">' . "\n";
        }
    });
    cms_add_action('body_close', function (?array $page = null) {
        if (cms_page_has_block($page, 'seo-analyzer') || !empty($page['seo_report'])) {
            echo '<script src="' . e(cms_module_url('seo-analyzer', 'assets/seo.js')) . '?v=' . e(s7x_seo_asset_v()) . '" defer></script>' . "\n";
        }
    });
}

function s7x_seo_asset_v(): string
{
    return (string) @filemtime(__DIR__ . '/assets/seo.js') . (string) @filemtime(__DIR__ . '/assets/seo.css');
}

function s7x_seo_base(): string
{
    return trim(cms_slug((string) s7x_seo_opt('base', 'analisador-seo')), '/') ?: 'analisador-seo';
}

function s7x_seo_report_url(string $id, bool $absolute = false): string
{
    $p = s7x_seo_base() . '/relatorio/' . $id . '/';
    return $absolute ? cms_abs_url($p) : cms_url($p);
}

function s7x_seo_whatsapp(array $r = []): string
{
    $phone = trim((string) s7x_seo_opt('whatsapp', ''));
    if ($phone === '') {
        foreach (['contato', 'contact', 'geral'] as $sec) {
            foreach (['whatsapp', 'phone', 'telefone'] as $f) {
                $v = (string) c($sec . '.' . $f, '');
                if ($v !== '' && strlen(cms_digits($v)) >= 10) {
                    $phone = $v;
                    break 2;
                }
            }
        }
    }
    if ($phone === '') {
        return '';
    }
    $msg = strtr((string) s7x_seo_opt('wa_message', ''), [
        '{site}' => $r ? ($r['host'] ?? '') : '',
        '{nota}' => isset($r['scores']['total']) ? $r['scores']['total'] . '/100' : '',
    ]);
    return cms_whatsapp_link($phone, $msg);
}

/** /analisador-seo/relatorio/{id}/ */
function s7x_seo_route($page, string $path)
{
    if ($page) {
        return $page;
    }
    if (!preg_match('#^/' . preg_quote(s7x_seo_base(), '#') . '/relatorio/([a-z0-9]{12})/?$#', $path, $m)) {
        return null;
    }
    $r = s7x_seo_get($m[1]);
    if (!$r) {
        return null;
    }
    if ($r['status'] === 'pronto' && !s7x_seo_is_admin()) {
        $r['views'] = (int) ($r['views'] ?? 0) + 1;
        s7x_seo_save($r);
    }
    $title = 'Relatório de SEO: ' . $r['host'];
    return [
        'id' => 'seo-report-' . $r['id'], 'title' => $title, 'slug' => trim(s7x_seo_base() . '/relatorio/' . $r['id'], '/'), 'home' => false, 'status' => 'published',
        'seo' => [
            'title' => $title . (isset($r['scores']['total']) ? ' — nota ' . $r['scores']['total'] . '/100' : '') . ' | ' . cms_site_name(),
            'description' => 'Análise de SEO de ' . $r['host'] . ': indexação, velocidade, celular, conteúdo, segurança e plano de ação.',
            'noindex' => true,
        ],
        'meta' => ['url' => s7x_seo_report_url($r['id'], true)],
        'breadcrumb' => [['Analisador de SEO', cms_abs_url(s7x_seo_base() . '/')], [$r['host'], s7x_seo_report_url($r['id'], true)]],
        'seo_report' => $r,
        'blocks' => [['id' => 'seoreport', 'type' => 'seo-report', 'enabled' => true, 'anchor' => '', 'data' => []]],
    ];
}

/** O visitante pode ver o relatório completo? */
function s7x_seo_unlocked(array $r): bool
{
    return s7x_seo_is_admin() || s7x_seo_opt('lead_mode', 'depois') !== 'depois' || !empty($r['lead']);
}

/* -------------------------------------------------------------------------
 * Pedidos do navegador: POST /?s7x_seo=iniciar|etapa|contato
 * ---------------------------------------------------------------------- */

function s7x_seo_token(): string
{
    $t = (string) time();
    return $t . '.' . substr(hash_hmac('sha256', 'seo|' . $t, cms_secret()), 0, 20);
}

function s7x_seo_token_ok(string $tok): bool
{
    [$t, $h] = array_pad(explode('.', $tok, 2), 2, '');
    $age = time() - (int) $t;
    return $age >= 1 && $age < 6 * 3600 && hash_equals(substr(hash_hmac('sha256', 'seo|' . $t, cms_secret()), 0, 20), $h);
}

function s7x_seo_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function s7x_seo_request(): void
{
    $action = (string) ($_GET['s7x_seo'] ?? '');
    if ($action === '' || cms_request_path() !== '/') {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        s7x_seo_json(['ok' => false, 'error' => 'Método inválido.'], 405);
    }
    try {
        switch ($action) {
            case 'iniciar':
                if (trim((string) ($_POST['website'] ?? '')) !== '' || !s7x_seo_token_ok((string) ($_POST['_t'] ?? ''))) {
                    throw new RuntimeException('Recarregue a página e tente de novo.');
                }
                $mode = (string) s7x_seo_opt('lead_mode', 'depois');
                $lead = s7x_seo_lead_from_post($mode === 'antes');
                if (!s7x_seo_rate_ok()) {
                    throw new RuntimeException('Você fez muitas análises na última hora. Aguarde um pouco e tente de novo.');
                }
                $r = s7x_seo_create((string) ($_POST['url'] ?? ''), (string) ($_POST['palavra'] ?? ''), $lead);
                if ($lead) {
                    s7x_seo_save($r);
                }
                s7x_seo_json(['ok' => true, 'id' => $r['id'], 'steps' => s7x_seo_steps(), 'host' => $r['host']]);
                // no break
            case 'etapa':
                $r = s7x_seo_get((string) ($_POST['id'] ?? ''));
                $step = (string) ($_POST['etapa'] ?? '');
                if (!$r || !isset(s7x_seo_steps()[$step])) {
                    throw new RuntimeException('Análise não encontrada. Comece de novo.');
                }
                if ($r['status'] === 'rodando' && strtotime($r['created']) < time() - 1800) {
                    throw new RuntimeException('Essa análise expirou. Comece de novo.');
                }
                if ($r['status'] === 'erro') {
                    s7x_seo_json(['ok' => false, 'error' => $r['error']]);
                }
                $r = s7x_seo_run_step($r, $step);
                if ($r['status'] === 'erro') {
                    s7x_seo_json(['ok' => false, 'error' => $r['error']]);
                }
                $out = ['ok' => true, 'status' => $r['status']];
                if ($step === 'pagina') {
                    $out['preview'] = ['title' => $r['page']['title'] ?? '', 'url' => $r['final_url'], 'tech' => array_values(array_filter($r['page']['tech'] ?? [], 'is_string'))];
                }
                if ($r['status'] === 'pronto') {
                    if ($r['lead'] && empty($r['lead_registered'])) {
                        s7x_seo_register_lead($r);
                        $r['lead_registered'] = true;
                        s7x_seo_save($r);
                    }
                    $out['html'] = s7x_seo_render_report($r, s7x_seo_unlocked($r), 'ferramenta');
                    $out['url'] = s7x_seo_report_url($r['id']);
                    $out['score'] = $r['scores']['total'];
                }
                s7x_seo_json($out);
                // no break
            case 'contato':
                $r = s7x_seo_get((string) ($_POST['id'] ?? ''));
                if (!$r || $r['status'] !== 'pronto') {
                    throw new RuntimeException('Análise não encontrada.');
                }
                if (trim((string) ($_POST['website'] ?? '')) !== '') {
                    throw new RuntimeException('Recarregue a página e tente de novo.');
                }
                $r['lead'] = s7x_seo_lead_from_post(true);
                s7x_seo_save($r);
                s7x_seo_register_lead($r);
                $r['lead_registered'] = true;
                s7x_seo_save($r);
                s7x_seo_json(['ok' => true, 'html' => s7x_seo_render_report($r, true, (string) ($_POST['onde'] ?? 'ferramenta') === 'pagina' ? 'pagina' : 'ferramenta'), 'url' => s7x_seo_report_url($r['id'])]);
        }
        s7x_seo_json(['ok' => false, 'error' => 'Ação inválida.'], 400);
    } catch (Throwable $e) {
        s7x_seo_json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}
