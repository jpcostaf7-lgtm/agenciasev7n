<?php
/**
 * Sev7n Xpress — entrada pública do site (roteador)
 * Autor: João Costa
 */
declare(strict_types=1);

define('CMS_ENTRY_DEPTH', 0);
require __DIR__ . '/cms/core.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Ações enviadas por formulários de módulos (ex.: formulário de contato)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_POST['_s7x_action'])) {
    $action = preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $_POST['_s7x_action']));
    cms_do_action('request:' . $action);
}
cms_do_action('request');

$path = cms_request_path();
$page = null;

// Pré-visualização de rascunho (somente com login no painel)
if (isset($_GET['preview'])) {
    require_once __DIR__ . '/cms/admin_lib.php';
    cms_session_start();
    if (cms_logged_in()) {
        $page = cms_page((string) $_GET['preview']);
    }
}
if (!$page) {
    $page = cms_page_by_slug(trim($path, '/'));
}
// Módulos podem responder por outros endereços (blog, categorias, feed...)
if (!$page) {
    $page = cms_apply_filters('route', null, $path);
}
if (!$page) {
    http_response_code(404);
    $page = [
        'id' => '404', 'title' => 'Página não encontrada', 'slug' => '404', 'home' => false, 'status' => 'published',
        'seo' => ['noindex' => true],
        'blocks' => [
            ['id' => 'nf', 'type' => 'page-header', 'enabled' => true, 'anchor' => '', 'data' => [
                'title' => 'Página não encontrada',
                'text'  => 'O endereço pode ter mudado ou sido digitado com erro.',
            ]],
            ['id' => 'nf2', 'type' => 'cta', 'enabled' => true, 'anchor' => '', 'data' => [
                'title' => 'Volte para a página inicial', 'text' => '', 'button_label' => 'Ir para o início', 'button_link' => '/', 'background' => 'claro',
            ]],
        ],
    ];
}

$GLOBALS['cms_current_page'] = $page;
header('Content-Type: text/html; charset=utf-8');

$layout = cms_active_theme() !== '' ? cms_theme_dir() . '/layout.php' : '';
if ($layout === '' || !is_file($layout) || $GLOBALS['cms_theme_error'] !== '') {
    http_response_code(503);
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Site em configuração</title>'
        . '<style>body{font:16px/1.6 system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f4f6f5;color:#1d2421}main{max-width:460px;padding:2rem;text-align:center}</style></head>'
        . '<body><main><h1>Site em configuração</h1><p>Volte em alguns minutos.</p>'
        . '<p style="color:#6c7672;font-size:.9rem">Administrador: entre no <a href="' . e(cms_url('admin/?p=themes')) . '">painel</a> para instalar ou corrigir o tema.</p></main></body></html>';
    exit;
}
ob_start();
try {
    require $layout;
    ob_end_flush();
    cms_cron_after_response();
} catch (Throwable $e) {
    ob_end_clean();
    error_log('[Sev7n Xpress] Erro no tema ' . cms_active_theme() . ': ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Erro temporário</title></head><body style="font:16px system-ui;padding:3rem;text-align:center">'
        . '<h1>Erro temporário</h1><p>O site está passando por um ajuste. Tente de novo em instantes.</p></body></html>';
}
