<?php
/**
 * Sev7n Xpress — conteúdo: configurações, páginas, blocos e módulos
 * Autor: João Costa
 */
declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Tema
 * ---------------------------------------------------------------------- */

function cms_theme(): array
{
    return $GLOBALS['cms_theme'];
}

/** Nome do site: o "Nome do negócio" do SEO, ou o nome definido em cms/config.php. */
function cms_site_name(): string
{
    $n = trim((string) cms_seo('org_name'));
    return $n !== '' ? $n : ((string) cms_config('project_name', '') ?: 'Meu site');
}

/** Pastas de temas instalados (themes/<pasta>/theme.php). */
function cms_theme_slugs(): array
{
    $out = [];
    foreach (glob(CMS_ROOT . '/themes/*/theme.php') ?: [] as $f) {
        $slug = basename(dirname($f));
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,60}$/', $slug)) {
            $out[] = $slug;
        }
    }
    sort($out);
    return $out;
}

/** Tema ativo (gravado em data/theme.json). */
function cms_active_theme(): string
{
    static $active = null;
    if ($active !== null) {
        return $active;
    }
    $slugs  = cms_theme_slugs();
    $saved  = (string) (cms_read_json(CMS_DATA . '/theme.json', [])['active'] ?? '');
    $config = trim((string) cms_config('theme', 'inicial'), '/');
    foreach ([$saved, $config] as $s) {
        if ($s !== '' && in_array($s, $slugs, true)) {
            return $active = $s;
        }
    }
    return $active = ($slugs[0] ?? '');
}

/** Caminho relativo do tema ativo: themes/<pasta> */
function cms_theme_rel(): string
{
    return 'themes/' . cms_active_theme();
}

function cms_theme_dir(): string
{
    return CMS_ROOT . '/' . cms_theme_rel();
}

function cms_theme_url(string $path = ''): string
{
    $rel  = cms_theme_rel() . '/' . ltrim($path, '/');
    $file = CMS_ROOT . '/' . $rel;
    return cms_url($rel) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Lê o manifesto (theme.php) de qualquer tema instalado, sem ativar. */
function cms_theme_manifest(string $slug): ?array
{
    $file = CMS_ROOT . '/themes/' . $slug . '/theme.php';
    if (!is_file($file)) {
        return null;
    }
    try {
        $def = (static function (string $__f) {
            return include $__f;
        })($file);
    } catch (Throwable $e) {
        return ['name' => $slug, 'error' => $e->getMessage() . ' (linha ' . $e->getLine() . ')'];
    }
    if (!is_array($def)) {
        return ['name' => $slug, 'error' => 'O arquivo theme.php precisa terminar com "return [ ... ];".'];
    }
    return $def;
}

/* -------------------------------------------------------------------------
 * Arquivo de conteúdo
 * ---------------------------------------------------------------------- */

function cms_content(): array
{
    if ($GLOBALS['cms_content'] === null) {
        $c = cms_read_json(CMS_DATA . '/content.json', []);
        $c += ['settings' => [], 'pages' => null, 'seo' => [], 'modules' => [], 'active_modules' => null, 'updated_at' => null];
        if (!is_array($c['pages'])) {
            $c['pages'] = cms_default_pages();
        }
        if (!is_array($c['active_modules'])) {
            $c['active_modules'] = cms_theme()['default_modules'] ?? [];
        }
        $GLOBALS['cms_content'] = $c;
    }
    return $GLOBALS['cms_content'];
}

/** Salva o conteúdo e guarda a versão anterior como revisão. */
function cms_save_content(array $content, string $note = ''): bool
{
    $file = CMS_DATA . '/content.json';
    if (is_file($file)) {
        cms_make_revision($note);
    }
    $content['version']    = 2;
    $content['updated_at'] = date('c');
    $ok = cms_write_json($file, $content);
    if ($ok) {
        $GLOBALS['cms_content'] = null;
        cms_content();
    }
    return $ok;
}

function cms_make_revision(string $note = ''): void
{
    $file = CMS_DATA . '/content.json';
    $dir  = CMS_DATA . '/revisions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $user = $_SESSION['cms_user'] ?? 'sistema';
    $slug = substr(cms_slug($note), 0, 40);
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . ($slug ? '-' . $slug : '') . '.json';
    @copy($file, $dir . '/' . $name);
    $meta = cms_read_json($dir . '/index.json', []);
    $meta[$name] = ['date' => date('c'), 'user' => $user, 'note' => $note];
    $files = glob($dir . '/2*.json') ?: [];
    rsort($files);
    foreach (array_slice($files, (int) cms_config('max_revisions', 30)) as $old) {
        unset($meta[basename($old)]);
        @unlink($old);
    }
    cms_write_json($dir . '/index.json', $meta);
}

function cms_revisions(): array
{
    $dir   = CMS_DATA . '/revisions';
    $meta  = cms_read_json($dir . '/index.json', []);
    $files = glob($dir . '/2*.json') ?: [];
    rsort($files);
    $list = [];
    foreach ($files as $f) {
        $b = basename($f);
        $list[] = [
            'file' => $b,
            'date' => $meta[$b]['date'] ?? date('c', (int) filemtime($f)),
            'user' => $meta[$b]['user'] ?? '',
            'note' => $meta[$b]['note'] ?? '',
        ];
    }
    return $list;
}

/* -------------------------------------------------------------------------
 * Configurações globais (seções definidas em theme.php -> 'settings')
 * ---------------------------------------------------------------------- */

function cms_settings_sections(): array
{
    return cms_apply_filters('settings_sections', cms_theme()['settings'] ?? []);
}

/**
 * Valor de configuração global: c('contato.phone'), c('geral.menu').
 * Usa o "default" do tema enquanto o campo nunca foi salvo.
 */
function c(string $path, $fallback = null)
{
    $parts   = explode('.', $path);
    $section = array_shift($parts);
    $field   = array_shift($parts);
    if ($field === null) {
        return $fallback;
    }
    $content = cms_content();
    if (isset($content['settings'][$section]) && array_key_exists($field, $content['settings'][$section])) {
        $value = $content['settings'][$section][$field];
    } else {
        $value = cms_settings_sections()[$section]['fields'][$field]['default'] ?? $fallback;
    }
    foreach ($parts as $p) {
        if (is_array($value) && array_key_exists($p, $value)) {
            $value = $value[$p];
        } else {
            return $fallback;
        }
    }
    return $value ?? $fallback;
}

/** Imprime configuração escapada (com quebras de linha). */
function t(string $path, $fallback = ''): void
{
    echo nl(c($path, $fallback));
}

/* -------------------------------------------------------------------------
 * Módulos
 * ---------------------------------------------------------------------- */

/** Todos os módulos instalados na pasta /modules. */
function cms_available_modules(): array
{
    static $mods = null;
    if ($mods !== null) {
        return $mods;
    }
    $mods = [];
    foreach (glob(CMS_ROOT . '/modules/*/module.php') ?: [] as $file) {
        $slug = basename(dirname($file));
        if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
            continue;
        }
        $def = include $file;
        if (!is_array($def) || empty($def['name'])) {
            continue;
        }
        $def += ['description' => '', 'version' => '1.0.0', 'author' => '', 'icon' => 'folha', 'settings' => [], 'requires' => [], 'boot' => null];
        $def['slug'] = $slug;
        $def['dir']  = dirname($file);
        $mods[$slug] = $def;
    }
    ksort($mods);
    return $mods;
}

function cms_module_active(string $slug): bool
{
    return in_array($slug, (array) cms_content()['active_modules'], true) && isset(cms_available_modules()[$slug]);
}

/** Liga os módulos ativos (chamado uma vez no carregamento). */
function cms_boot_modules(): void
{
    foreach (cms_available_modules() as $slug => $mod) {
        if (cms_module_active($slug) && is_callable($mod['boot'])) {
            $GLOBALS['cms_booting_module'] = $slug;
            $mod['boot']($mod);
        }
    }
    $GLOBALS['cms_booting_module'] = null;
}

/** Configuração salva de um módulo, com o default definido no módulo. */
function cms_module_get(string $module, string $key, $fallback = null)
{
    $content = cms_content();
    if (isset($content['modules'][$module]) && array_key_exists($key, $content['modules'][$module])) {
        return $content['modules'][$module][$key];
    }
    // O tema pode definir os valores iniciais dos módulos ('module_defaults' no theme.php)
    $theme = cms_theme()['module_defaults'][$module] ?? [];
    if (array_key_exists($key, $theme)) {
        return $theme[$key];
    }
    return cms_available_modules()[$module]['settings'][$key]['default'] ?? $fallback;
}

function cms_module_url(string $module, string $path = ''): string
{
    $rel  = 'modules/' . $module . '/' . ltrim($path, '/');
    $file = CMS_ROOT . '/' . $rel;
    return cms_url($rel) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/* -------------------------------------------------------------------------
 * Blocos
 * ---------------------------------------------------------------------- */

$GLOBALS['cms_blocks'] = [];

/**
 * Registra um tipo de bloco.
 * $def: label, description, icon, group, fields, template (arquivo .php), wrap (classe CSS)
 */
function cms_register_block(string $type, array $def): void
{
    $def += ['label' => $type, 'description' => '', 'icon' => 'folha', 'group' => 'Básicos', 'fields' => [], 'template' => ''];
    if (!empty($GLOBALS['cms_booting_module'])) {
        $def['module'] = $GLOBALS['cms_booting_module'];
    }
    $GLOBALS['cms_blocks'][$type] = $def;
}

function cms_block_types(): array
{
    return $GLOBALS['cms_blocks'];
}

function cms_block_type(string $type): ?array
{
    return $GLOBALS['cms_blocks'][$type] ?? null;
}

/** Valor padrão de um campo, de acordo com o tipo. */
function cms_field_default(array $def)
{
    if (array_key_exists('default', $def)) {
        return $def['default'];
    }
    switch ($def['type'] ?? 'text') {
        case 'toggle':
            return false;
        case 'repeater':
            return [];
        case 'image':
            return ['src' => '', 'alt' => ''];
        default:
            return '';
    }
}

/** Dados do bloco com os padrões preenchidos. */
function cms_block_data(array $block): array
{
    $def  = cms_block_type((string) ($block['type'] ?? ''));
    $data = (array) ($block['data'] ?? []);
    if (!$def) {
        return $data;
    }
    foreach ($def['fields'] as $fid => $f) {
        if (!array_key_exists($fid, $data)) {
            $data[$fid] = cms_field_default($f);
        }
    }
    return $data;
}

/** Bloco novo com os dados padrão. */
function cms_new_block(string $type, array $data = [], string $anchor = ''): array
{
    return [
        'id'      => 'b' . bin2hex(random_bytes(4)),
        'type'    => $type,
        'enabled' => true,
        'anchor'  => $anchor,
        'data'    => $data,
    ];
}

function cms_block_template(string $type): string
{
    $theme = cms_theme_dir() . '/blocks/' . $type . '.php';
    if (is_file($theme)) {
        return $theme;
    }
    $def = cms_block_type($type);
    return $def['template'] ?? '';
}

/** Renderiza um bloco. As variáveis $b (dados), $block e $page ficam disponíveis no template. */
function cms_render_block(array $block, array $page): void
{
    $type = (string) ($block['type'] ?? '');
    $def  = cms_block_type($type);
    $tpl  = cms_block_template($type);
    if (!$def || $tpl === '' || !is_file($tpl)) {
        return;
    }
    $b      = cms_block_data($block);
    $anchor = trim((string) ($block['anchor'] ?? ''));
    $id     = $anchor !== '' ? ' id="' . e($anchor) . '"' : '';
    $GLOBALS['cms_current_block'] = $block;
    (static function () use ($b, $block, $page, $id, $anchor, $tpl) {
        include $tpl;
    })();
}

function cms_render_blocks(array $page): void
{
    foreach ((array) ($page['blocks'] ?? []) as $block) {
        if (!empty($block['enabled'])) {
            cms_render_block($block, $page);
        }
    }
}

/* -------------------------------------------------------------------------
 * Páginas
 * ---------------------------------------------------------------------- */

/** Páginas iniciais definidas pelo tema (usadas até a primeira gravação). */
function cms_default_pages(): array
{
    $pages = [];
    foreach ((array) (cms_theme()['default_pages'] ?? []) as $p) {
        $id = $p['id'] ?? ('p' . bin2hex(random_bytes(4)));
        $blocks = [];
        foreach ((array) ($p['blocks'] ?? []) as $i => $bl) {
            $blocks[] = [
                'id'      => $bl['id'] ?? ('b' . substr(md5($id . $i . ($bl['type'] ?? '')), 0, 8)),
                'type'    => $bl['type'],
                'enabled' => $bl['enabled'] ?? true,
                'anchor'  => $bl['anchor'] ?? '',
                'data'    => $bl['data'] ?? [],
            ];
        }
        $pages[$id] = [
            'id'      => $id,
            'title'   => $p['title'] ?? 'Página',
            'slug'    => !empty($p['home']) ? '' : cms_slug((string) ($p['slug'] ?? $p['title'] ?? '')),
            'home'    => !empty($p['home']),
            'status'  => $p['status'] ?? 'published',
            'seo'     => $p['seo'] ?? [],
            'blocks'  => $blocks,
            'created' => date('c'),
        ];
    }
    if (!array_filter($pages, fn ($p) => !empty($p['home']))) {
        $pages['home'] = ['id' => 'home', 'title' => 'Página inicial', 'slug' => '', 'home' => true, 'status' => 'published', 'seo' => [], 'blocks' => []];
    }
    return $pages;
}

/** Páginas disponíveis. Sem o módulo "Páginas extras", só a inicial. */
function cms_pages(bool $all = false): array
{
    $pages = (array) cms_content()['pages'];
    if (!$all && !cms_multi_pages()) {
        $pages = array_filter($pages, fn ($p) => !empty($p['home']));
    }
    return $pages;
}

/** O módulo de páginas extras liga isso via filtro. */
function cms_multi_pages(): bool
{
    return (bool) cms_apply_filters('multi_pages', false);
}

function cms_home_page(): array
{
    foreach ((array) cms_content()['pages'] as $p) {
        if (!empty($p['home'])) {
            return $p;
        }
    }
    $d = cms_default_pages();
    return $d['home'] ?? reset($d);
}

function cms_page(string $id): ?array
{
    return cms_content()['pages'][$id] ?? null;
}

function cms_page_by_slug(string $slug, bool $onlyPublished = true): ?array
{
    $slug = trim($slug, '/');
    if ($slug === '') {
        return cms_home_page();
    }
    foreach (cms_pages() as $p) {
        if (empty($p['home']) && ($p['slug'] ?? '') === $slug && (!$onlyPublished || ($p['status'] ?? '') === 'published')) {
            return $p;
        }
    }
    return null;
}

function cms_page_path(array $page): string
{
    return !empty($page['home']) ? '/' : '/' . trim((string) $page['slug'], '/');
}

function cms_page_url(array $page, bool $absolute = false): string
{
    $path = cms_page_path($page);
    return $absolute ? cms_site_url() . $path : cms_url($path);
}

/** Endereços que não podem ser usados por páginas. */
function cms_reserved_slugs(): array
{
    return (array) cms_apply_filters('reserved_slugs', ['admin', 'cms', 'data', 'modules', 'themes', 'uploads', 'ads.txt', 'mcp', 'oauth', 'sitemap.xml', 'robots.txt', 'index.php', 'sitemap', 'robots', 'cron.php']);
}

/** Caminho pedido, relativo à pasta do site. */
function cms_request_path(): string
{
    $uri  = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $uri  = rawurldecode($uri);
    $base = cms_base_path();
    if ($base !== '' && strpos($uri, $base) === 0) {
        $uri = substr($uri, strlen($base));
    }
    $uri = '/' . trim($uri, '/');
    if ($uri === '/index.php') {
        $uri = '/';
    }
    return $uri;
}

/** Primeiro campo de texto preenchido (para títulos e listas no painel). */
function cms_block_summary(array $block): string
{
    $def  = cms_block_type((string) ($block['type'] ?? ''));
    $data = cms_block_data($block);
    if ($def) {
        foreach ($def['fields'] as $fid => $f) {
            if (in_array($f['type'] ?? 'text', ['text', 'textarea'], true) && trim((string) ($data[$fid] ?? '')) !== '') {
                return mb_strimwidth(trim(preg_replace('/\s+/', ' ', (string) $data[$fid])), 0, 70, '…');
            }
        }
    }
    return '';
}

/* -------------------------------------------------------------------------
 * Telas do painel registradas por módulos
 * ---------------------------------------------------------------------- */

$GLOBALS['cms_admin_pages'] = [];

/**
 * Registra uma tela no painel.
 * $def: label, icon, render(callable), handle(callable, opcional), badge(callable, opcional)
 */
function cms_register_admin_page(string $slug, array $def): void
{
    $def += ['label' => $slug, 'icon' => 'folha', 'render' => null, 'handle' => null, 'badge' => null, 'group' => 'Conteúdo', 'admin_only' => false, 'order' => 50];
    $def['perm'] = $def['perm'] ?? 'page.' . $slug;
    $def['module'] = $GLOBALS['cms_booting_module'] ?? '';
    $GLOBALS['cms_admin_pages'][$slug] = $def;
}

function cms_admin_pages(): array
{
    $pages = $GLOBALS['cms_admin_pages'];
    uasort($pages, fn ($a, $b) => $a['order'] <=> $b['order']);
    return $pages;
}

/** Página atual tem um bloco deste tipo visível? */
function cms_page_has_block(?array $page, string $type): bool
{
    foreach ((array) ($page['blocks'] ?? []) as $bl) {
        if (($bl['type'] ?? '') === $type && !empty($bl['enabled'])) {
            return true;
        }
    }
    return false;
}

/** Arquivo de dados próprio de um módulo (data/<modulo>-<nome>.json). */
function cms_module_data_file(string $module, string $name): string
{
    return CMS_DATA . '/' . preg_replace('/[^a-z0-9_-]/', '', $module) . '-' . preg_replace('/[^a-z0-9_-]/', '', $name) . '.json';
}
