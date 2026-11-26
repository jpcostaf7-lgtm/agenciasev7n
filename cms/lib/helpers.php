<?php
/**
 * Sev7n Xpress — funções utilitárias
 * Autor: João Costa
 */
declare(strict_types=1);

function cms_config(string $key, $default = null)
{
    return $GLOBALS['cms_config'][$key] ?? $default;
}


/** Caminho base do site na URL (ex.: "" ou "/subpasta"). */
function cms_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $depth  = defined('CMS_ENTRY_DEPTH') ? (int) CMS_ENTRY_DEPTH : 0;
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir    = dirname($script);
    for ($i = 0; $i < $depth; $i++) {
        $dir = dirname($dir);
    }
    $dir  = str_replace('\\', '/', $dir);
    $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    return $base;
}

function cms_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
}

/** URL absoluta do site, sem barra final. */
function cms_site_url(): string
{
    $seoUrl = trim((string) cms_seo('site_url'));
    if ($seoUrl !== '') {
        return rtrim($seoUrl, '/');
    }
    $cfg = trim((string) cms_config('site_url', ''));
    if ($cfg !== '') {
        return rtrim($cfg, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);
    return (cms_is_https() ? 'https://' : 'http://') . $host . cms_base_path();
}

/** URL relativa à raiz do site (para assets, uploads, links internos). */
function cms_url(string $path = ''): string
{
    if ($path !== '' && preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    return cms_base_path() . '/' . ltrim($path, '/');
}

function cms_abs_url(string $path = ''): string
{
    if ($path !== '' && preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return cms_site_url() . '/' . ltrim($path, '/');
}


function cms_read_json(string $file, $default = [])
{
    if (!is_file($file)) {
        return $default;
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

/** Gravação atômica: escreve em arquivo temporário e renomeia. */
function cms_write_json(string $file, $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $file)) {
        // Alguns servidores Windows não sobrescrevem com rename
        @unlink($file);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
    }
    @chmod($file, 0644);
    return true;
}


function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/** Normaliza valor de campo imagem para ['src'=>..,'alt'=>..]. */
function cms_image_value($value): array
{
    if (is_string($value)) {
        return ['src' => $value, 'alt' => ''];
    }
    if (is_array($value)) {
        return ['src' => (string) ($value['src'] ?? ''), 'alt' => (string) ($value['alt'] ?? '')];
    }
    return ['src' => '', 'alt' => ''];
}

/** Aceita "secao.campo" (lê do conteúdo) ou um valor de imagem já pronto. */
function cms_resolve_image($field): array
{
    if (is_string($field) && strpos($field, '/') === false && !preg_match('/\.(webp|png|jpe?g|gif|svg|ico)$/i', $field)) {
        return cms_image_value(c($field));
    }
    return cms_image_value($field);
}

function img_src($field): string
{
    $v = cms_resolve_image($field);
    return $v['src'] !== '' ? cms_url($v['src']) : '';
}

function img_alt($field): string
{
    return cms_resolve_image($field)['alt'];
}

/**
 * Imprime uma tag <img> otimizada a partir de um campo imagem.
 * $field: "secao.campo" ou valor de imagem (array/string).
 */
function img($field, array $attrs = []): void
{
    $v = cms_resolve_image($field);
    if ($v['src'] === '') {
        return;
    }
    $attrs = array_merge(['loading' => 'lazy', 'decoding' => 'async'], $attrs);
    if (!isset($attrs['alt'])) {
        $attrs['alt'] = $v['alt'];
    }
    $file = CMS_ROOT . '/' . ltrim($v['src'], '/');
    if (!isset($attrs['width']) && is_file($file)) {
        $size = @getimagesize($file);
        if ($size) {
            $attrs['width']  = $size[0];
            $attrs['height'] = $size[1];
        }
    }
    $html = '<img src="' . e(cms_url($v['src'])) . '"';
    foreach ($attrs as $k => $val) {
        if ($val === false || $val === null) {
            continue;
        }
        $html .= ' ' . e($k) . '="' . e($val) . '"';
    }
    echo $html . '>';
}


/* -------------------------------------------------------------------------
 * Sanitização de HTML (richtext)
 * ---------------------------------------------------------------------- */

function cms_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        return nl2br(e(strip_tags($html)), false);
    }
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'blockquote'];
    $drop    = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'button', 'svg', 'math', 'template', 'noscript', 'link', 'meta'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="cms-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('cms-root');
    if (!$root) {
        return nl2br(e(strip_tags($html)), false);
    }

    $walk = function (DOMNode $node) use (&$walk, $allowed, $drop, $doc) {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, $drop, true)) {
                $node->removeChild($child);
                continue;
            }
            $walk($child);
            if ($tag === 'div') {
                // div vira parágrafo (contenteditable do Chrome cria divs)
                $p = $doc->createElement('p');
                while ($child->firstChild) {
                    $p->appendChild($child->firstChild);
                }
                $node->replaceChild($p, $child);
                continue;
            }
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            // Limpa atributos
            $keep = [];
            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                if (preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $href)) {
                    $keep['href'] = $href;
                    if (preg_match('#^https?:#i', $href)) {
                        $keep['target'] = '_blank';
                        $keep['rel'] = 'noopener';
                    }
                }
            }
            $attrs = [];
            foreach ($child->attributes as $a) {
                $attrs[] = $a->name;
            }
            foreach ($attrs as $a) {
                $child->removeAttribute($a);
            }
            foreach ($keep as $k => $v) {
                $child->setAttribute($k, $v);
            }
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    // Remove parágrafos vazios
    $out = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#i', '', $out);
    return trim((string) $out);
}

/* -------------------------------------------------------------------------
 * Utilidades
 * ---------------------------------------------------------------------- */

function cms_ascii(string $s): string
{
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C','Ñ'=>'N'];
    return strtr($s, $map);
}

function cms_slug(string $s): string
{
    $s = strtolower(cms_ascii($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string) $s, '-');
}

/** Só dígitos — para links tel: e wa.me */
function cms_digits(string $s): string
{
    return preg_replace('/\D+/', '', $s) ?? '';
}

function cms_tel_link(string $phone): string
{
    $d = cms_digits($phone);
    if ($d === '') {
        return '';
    }
    if (strlen($d) <= 11) {
        $d = '55' . $d;
    }
    return 'tel:+' . $d;
}

function cms_whatsapp_link(string $phone, string $message = ''): string
{
    $d = cms_digits($phone);
    if ($d === '') {
        return '';
    }
    if (strlen($d) <= 11) {
        $d = '55' . $d;
    }
    return 'https://wa.me/' . $d . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

/** Ícones SVG de linha usados pelo tema e pelo campo "icon" */
function cms_icons(): array
{
    return [
        'dor'       => '<path d="M12 21s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 5.5-7 10-7 10z"/><path d="M8.5 12h2l1-2 1.5 4 1-2h1.5"/>',
        'mente'     => '<path d="M9 3a3 3 0 0 0-3 3v.2A3 3 0 0 0 4 9c0 .9.4 1.7 1 2.2-.6.6-1 1.4-1 2.3a3 3 0 0 0 2 2.8V17a3 3 0 0 0 6 0V3.9A3 3 0 0 0 9 3z"/><path d="M15 3a3 3 0 0 1 3 3v.2A3 3 0 0 1 20 9c0 .9-.4 1.7-1 2.2.6.6 1 1.4 1 2.3a3 3 0 0 1-2 2.8V17a3 3 0 0 1-6 0"/>',
        'neuronio'  => '<circle cx="12" cy="12" r="3"/><path d="M12 3v6M12 15v6M3 12h6M15 12h6M5.6 5.6l4.3 4.3M14.1 14.1l4.3 4.3M18.4 5.6l-4.3 4.3M9.9 14.1l-4.3 4.3"/>',
        'sono'      => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/><path d="M15 4h3l-3 3h3"/>',
        'inflamacao'=> '<path d="M12 22c4 0 7-2.8 7-6.8 0-3.6-2.6-5.7-4-8.2-.7 1.8-1.8 2.8-3 3.3C12.6 7.6 11.6 4.6 9 2c0 4-4 6.3-4 11.2C5 19.2 8 22 12 22z"/>',
        'escudo'    => '<path d="M12 3l7 3v6c0 4.4-3 7.7-7 9-4-1.3-7-4.6-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
        'folha'     => '<path d="M5 19c0-8 5-14 15-15-1 10-7 15-15 15z"/><path d="M5 19l8-8"/>',
        'gota'      => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>',
        'coracao'   => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
        'pulso'     => '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
        'estetoscopio' => '<path d="M6 3v6a4 4 0 0 0 8 0V3"/><path d="M10 13v2a5 5 0 0 0 10 0v-2"/><circle cx="20" cy="11" r="2"/>',
        'calendario'=> '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'telefone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'local'     => '<path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
        'whatsapp'  => '<path d="M4 20l1.3-4A8.5 8.5 0 1 1 8.6 19L4 20z"/><path d="M9 8.5c0 3.5 3 6.5 6.5 6.5l1-1.6-2-1-1 .8c-1-.4-2-1.4-2.4-2.4l.8-1-1-2L9 8.5z"/>',
        'relogio'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'lupa'      => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'check'     => '<path d="M5 12l4 4 10-10"/>',
    ];
}

function cms_icon(string $name, string $class = 'icon'): string
{
    $icons = cms_icons();
    $path  = $icons[$name] ?? $icons['folha'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}


/* -------------------------------------------------------------------------
 * Ganchos (actions e filters), no estilo do WordPress
 * ---------------------------------------------------------------------- */

$GLOBALS['cms_hooks'] = [];

/** Registra uma função para rodar em um gancho. Prioridade menor roda antes. */
function cms_add_action(string $hook, callable $cb, int $priority = 10): void
{
    $GLOBALS['cms_hooks'][$hook][$priority][] = $cb;
}

/** Executa todas as funções ligadas a um gancho. */
function cms_do_action(string $hook, ...$args): void
{
    if (empty($GLOBALS['cms_hooks'][$hook])) {
        return;
    }
    ksort($GLOBALS['cms_hooks'][$hook]);
    foreach ($GLOBALS['cms_hooks'][$hook] as $cbs) {
        foreach ($cbs as $cb) {
            $cb(...$args);
        }
    }
}

/** Filtro: cada função recebe o valor e devolve o valor alterado. */
function cms_add_filter(string $hook, callable $cb, int $priority = 10): void
{
    cms_add_action('filter:' . $hook, $cb, $priority);
}

function cms_apply_filters(string $hook, $value, ...$args)
{
    $key = 'filter:' . $hook;
    if (empty($GLOBALS['cms_hooks'][$key])) {
        return $value;
    }
    ksort($GLOBALS['cms_hooks'][$key]);
    foreach ($GLOBALS['cms_hooks'][$key] as $cbs) {
        foreach ($cbs as $cb) {
            $value = $cb($value, ...$args);
        }
    }
    return $value;
}

/** Captura o HTML impresso por um gancho. */
function cms_capture_action(string $hook, ...$args): string
{
    ob_start();
    cms_do_action($hook, ...$args);
    return (string) ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Outros utilitários para temas e módulos
 * ---------------------------------------------------------------------- */

/** Texto escapado com quebras de linha em <br>. */
function nl($value): string
{
    return nl2br(e($value), false);
}

/** HTML de um campo richtext, sempre sanitizado. */
function cms_rich($html): string
{
    return cms_sanitize_html((string) $html);
}

/**
 * Normaliza links digitados no painel:
 *  "/sobre"     -> caminho dentro do site (funciona em subpasta)
 *  "#secao"     -> âncora; fora da página inicial vira "/#secao"
 *  "https://..." e "tel:"/"mailto:" ficam como estão
 */
function cms_link(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '#';
    }
    if ($url[0] === '#') {
        $page = $GLOBALS['cms_current_page'] ?? null;
        if ($page && empty($page['home'])) {
            return cms_url('') . $url;
        }
        return $url;
    }
    if ($url[0] === '/' && (strlen($url) < 2 || $url[1] !== '/')) {
        return cms_url($url);
    }
    return $url;
}

/** Chave secreta do site (gerada no primeiro uso, fica em data/). */
function cms_secret(): string
{
    $file = CMS_DATA . '/secret.key';
    if (is_file($file)) {
        $k = trim((string) file_get_contents($file));
        if (strlen($k) >= 32) {
            return $k;
        }
    }
    $k = bin2hex(random_bytes(32));
    @file_put_contents($file, $k, LOCK_EX);
    @chmod($file, 0600);
    return $k;
}

function cms_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function cms_format_date(?string $iso, string $format = 'd/m/Y H:i'): string
{
    if (!$iso) {
        return '—';
    }
    $t = strtotime($iso);
    return $t ? date($format, $t) : '—';
}


/* -------------------------------------------------------------------------
 * Cores da identidade visual (aviso de cookies e painel)
 * ---------------------------------------------------------------------- */

/** "#abc" / "#aabbcc" → [r, g, b] ou null. */
function cms_hex_rgb(string $hex): ?array
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
        return null;
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** Mistura duas cores; $t = quanto de $b entra (0 a 1). */
function cms_hex_mix(string $a, string $b, float $t): string
{
    $x = cms_hex_rgb($a) ?? [0, 0, 0];
    $y = cms_hex_rgb($b) ?? [0, 0, 0];
    $o = '#';
    for ($i = 0; $i < 3; $i++) {
        $o .= str_pad(dechex((int) round($x[$i] + ($y[$i] - $x[$i]) * $t)), 2, '0', STR_PAD_LEFT);
    }
    return $o;
}

/** Luminância relativa (WCAG). */
function cms_hex_luminance(string $hex): float
{
    $c = cms_hex_rgb($hex) ?? [0, 0, 0];
    $l = array_map(function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }, $c);
    return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

/**
 * Cores da marca do site, usadas no aviso de cookies e no painel.
 * Vêm do tema ('brand' => ['accent' => '#...', 'dark' => '#...', 'ink' => '#fff'])
 * ou, se o tema não tiver, da "Cor do navegador no celular" do SEO.
 * Sem nenhuma das duas, devolve [] e tudo segue com as cores padrão.
 */
function cms_brand(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $t = $GLOBALS['cms_theme'] ?? [];
    $b = (array) ($t['brand'] ?? []);
    $accent = (string) ($b['accent'] ?? '');
    if (!cms_hex_rgb($accent)) {
        $tc = function_exists('cms_seo') ? (string) cms_seo('theme_color', '') : '';
        $accent = (cms_hex_rgb($tc) && strtolower($tc) !== '#1e5631') ? $tc : '';
    }
    if ($accent === '') {
        return $cache = [];
    }
    $accent = '#' . ltrim(strtolower($accent), '#');
    $lum = cms_hex_luminance($accent);
    // Cor clara demais para botão: escurece até ler bem sobre branco
    $btn = $accent;
    for ($i = 0; $i < 8 && cms_hex_luminance($btn) > 0.32; $i++) {
        $btn = cms_hex_mix($btn, '#000000', 0.15);
    }
    $ink = (string) ($b['ink'] ?? '');
    if (!cms_hex_rgb($ink)) {
        $ink = cms_hex_luminance($btn) > 0.4 ? '#111111' : '#ffffff';
    }
    $dark = (string) ($b['dark'] ?? '');
    if (!cms_hex_rgb($dark)) {
        $dark = cms_hex_mix($accent, '#0d0d0f', $lum < 0.05 ? 0.35 : 0.8);
    }
    return $cache = [
        'accent' => $btn,
        'ink'    => $ink,
        'soft'   => cms_hex_mix($accent, '#ffffff', 0.9),
        'line'   => cms_hex_mix($accent, '#ffffff', 0.7),
        'focus'  => cms_hex_mix($accent, '#ffffff', 0.45),
        'dark'   => $dark,
        'raw'    => $accent,
    ];
}

/** <style> com as cores da marca para o painel (vazio se o site não tiver cor própria). */
function cms_brand_admin_css(): string
{
    $b = cms_brand();
    if (!$b) {
        return '';
    }
    return '<style>:root{--accent:' . $b['accent'] . ';--accent-ink:' . $b['ink'] . ';--accent-soft:' . $b['soft']
        . ';--accent-line:' . $b['line'] . ';--focus:' . $b['focus'] . ';--side:' . $b['dark'] . '}</style>';
}

