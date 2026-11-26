<?php
/**
 * Módulo Blog — armazenamento, termos, HTML seguro e imagens
 * Posts ficam em data/blog/: index.json (lista leve), posts/<id>.json (conteúdo),
 * terms.json (categorias e tags), views.json (visualizações) e redirects.json.
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

define('S7X_BLOG_DIR', CMS_DATA . '/blog');

function s7x_blog_opt(string $key, $fallback = null)
{
    return cms_module_get('blog', $key, $fallback);
}

/* -------------------------------------------------------------------------
 * Índice e posts
 * ---------------------------------------------------------------------- */

function s7x_blog_dir(string $sub = ''): string
{
    $dir = S7X_BLOG_DIR . ($sub !== '' ? '/' . $sub : '');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        if ($sub === '') {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
            @file_put_contents($dir . '/index.html', '');
        }
    }
    return $dir;
}

/** Índice: ['posts' => [id => resumo], 'slugs' => [slug => id], 'wp' => [wp_id => id]] */
function s7x_blog_index(bool $fresh = false): array
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = cms_read_json(S7X_BLOG_DIR . '/index.json', []);
        $cache += ['posts' => [], 'slugs' => [], 'wp' => [], 'next' => 1];
    }
    return $cache;
}

function s7x_blog_save_index(array $index): bool
{
    s7x_blog_dir();
    $index['slugs'] = [];
    $index['wp'] = [];
    foreach ($index['posts'] as $id => $p) {
        if (($p['status'] ?? '') !== 'trash') {
            $index['slugs'][$p['slug']] = $id;
        }
        if (!empty($p['wp_id'])) {
            $index['wp'][(string) $p['wp_id']] = $id;
        }
    }
    $ok = cms_write_json(S7X_BLOG_DIR . '/index.json', $index);
    s7x_blog_index(true);
    return $ok;
}

/** Post completo (com conteúdo) ou null. */
function s7x_blog_get(string $id): ?array
{
    if (!preg_match('/^p\d+$/', $id)) {
        return null;
    }
    $index = s7x_blog_index();
    if (!isset($index['posts'][$id])) {
        return null;
    }
    $full = cms_read_json(S7X_BLOG_DIR . '/posts/' . $id . '.json', []);
    return array_merge(s7x_blog_defaults(), $index['posts'][$id], $full, ['id' => $id]);
}

function s7x_blog_by_slug(string $slug): ?array
{
    $id = s7x_blog_index()['slugs'][$slug] ?? null;
    return $id ? s7x_blog_get($id) : null;
}

function s7x_blog_defaults(): array
{
    return [
        'id' => '', 'slug' => '', 'title' => '', 'status' => 'draft', 'date' => '', 'modified' => '',
        'cats' => [], 'tags' => [], 'excerpt' => '', 'image' => ['src' => '', 'alt' => ''], 'image_credit' => '',
        'content' => '', 'seo' => [], 'faq' => [], 'source' => '', 'source_url' => '', 'wp_id' => 0, 'words' => 0,
        'hide_ads' => false, 'author' => '',
    ];
}

/** Campos que ficam no índice (o resto vai só no arquivo do post). */
function s7x_blog_summary_keys(): array
{
    return ['slug', 'title', 'status', 'date', 'modified', 'cats', 'tags', 'excerpt', 'image', 'source', 'wp_id', 'words', 'source_url'];
}

/** Slug único entre posts, páginas e endereços reservados. */
function s7x_blog_unique_slug(string $slug, string $id = ''): string
{
    $slug = cms_slug($slug) ?: 'post';
    $slug = substr($slug, 0, 90);
    $index = s7x_blog_index();
    $pages = array_map(fn ($p) => (string) $p['slug'], cms_pages(true));
    $taken = function (string $s) use ($index, $id, $pages) {
        $owner = $index['slugs'][$s] ?? null;
        return ($owner !== null && $owner !== $id) || in_array($s, $pages, true) || in_array($s, cms_reserved_slugs(), true)
            || in_array($s, [s7x_blog_base(), s7x_blog_opt('category_base', 'category'), s7x_blog_opt('tag_base', 'tag'), 'feed', 'page'], true);
    };
    $base = $slug;
    $n = 2;
    while ($taken($slug)) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

/**
 * Cria ou atualiza um post. $data pode ter qualquer campo de s7x_blog_defaults().
 * Retorna o id.
 */
function s7x_blog_save(array $data, string $id = ''): string
{
    $index = s7x_blog_index(true);
    $old = $id !== '' ? s7x_blog_get($id) : null;
    if ($id === '' || !$old) {
        $id = 'p' . (int) $index['next'];
        $index['next'] = (int) $index['next'] + 1;
        $old = s7x_blog_defaults();
    }
    $post = array_merge($old, $data, ['id' => $id]);
    $post['title'] = trim(strip_tags((string) $post['title'])) ?: 'Sem título';
    $post['slug'] = s7x_blog_unique_slug((string) ($post['slug'] ?: $post['title']), $id);
    if (!in_array($post['status'], ['publish', 'draft', 'future', 'trash', 'pending'], true)) {
        $post['status'] = 'draft';
    }
    if ($post['date'] === '' || strtotime((string) $post['date']) === false) {
        $post['date'] = date('c');
    } else {
        $post['date'] = date('c', strtotime((string) $post['date']));
    }
    if ($post['status'] === 'publish' && strtotime($post['date']) > time() + 60) {
        $post['status'] = 'future';
    }
    $post['modified'] = $data['modified'] ?? date('c');
    $post['cats'] = array_values(array_unique(array_filter(array_map('strval', (array) $post['cats']))));
    $post['tags'] = array_values(array_unique(array_filter(array_map('strval', (array) $post['tags']))));
    $post['content'] = s7x_blog_clean_html((string) $post['content']);
    $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('#<(/?)(p|h\d|li|div|br|tr|td|th|figcaption|blockquote|nav|ol|ul)\b[^>]*>#i', ' ', $post['content'])), ENT_QUOTES, 'UTF-8')));
    $post['words'] = $plain === '' ? 0 : count(preg_split('/\s+/u', $plain));
    if (trim((string) $post['excerpt']) === '') {
        $post['excerpt'] = mb_strimwidth($plain, 0, 220, '…');
    }
    $post['excerpt'] = trim(strip_tags((string) $post['excerpt']));
    $post['image'] = cms_image_value($post['image']);

    $summary = array_intersect_key($post, array_flip(s7x_blog_summary_keys()));
    $index['posts'][$id] = $summary;
    s7x_blog_dir('posts');
    cms_write_json(S7X_BLOG_DIR . '/posts/' . $id . '.json', array_diff_key($post, array_flip(['slug', 'title', 'status', 'date', 'cats', 'tags', 'image', 'wp_id', 'excerpt', 'words', 'source', 'source_url'])));
    s7x_blog_save_index($index);
    cms_do_action('blog_saved', $id, $post);
    return $id;
}

function s7x_blog_delete(string $id): void
{
    $index = s7x_blog_index(true);
    unset($index['posts'][$id]);
    @unlink(S7X_BLOG_DIR . '/posts/' . $id . '.json');
    s7x_blog_save_index($index);
}

/** Publica posts agendados cuja hora chegou (chamado pelo cron e pelas visitas). */
function s7x_blog_publish_due(): int
{
    $index = s7x_blog_index();
    $n = 0;
    foreach ($index['posts'] as $id => $p) {
        if (($p['status'] ?? '') === 'future' && strtotime((string) $p['date']) <= time()) {
            $index['posts'][$id]['status'] = 'publish';
            $n++;
        }
    }
    if ($n) {
        s7x_blog_save_index($index);
    }
    return $n;
}

/**
 * Lista de resumos filtrada e ordenada (mais novos primeiro).
 * $q: status (publish|draft|future|trash|any), cat, tag, search, exclude, source
 */
function s7x_blog_query(array $q = []): array
{
    $q += ['status' => 'publish', 'cat' => '', 'tag' => '', 'search' => '', 'exclude' => [], 'source' => ''];
    $out = [];
    $now = time();
    $terms = $q['search'] !== '' ? array_filter(preg_split('/\s+/', mb_strtolower(cms_ascii(trim((string) $q['search']))))) : [];
    foreach (s7x_blog_index()['posts'] as $id => $p) {
        $st = (string) ($p['status'] ?? 'draft');
        if ($q['status'] === 'publish') {
            if (!($st === 'publish' || ($st === 'future' && strtotime((string) $p['date']) <= $now))) {
                continue;
            }
        } elseif ($q['status'] === 'any') {
            if ($st === 'trash') {
                continue;
            }
        } elseif ($st !== $q['status']) {
            continue;
        }
        if ($q['cat'] !== '' && !in_array($q['cat'], (array) $p['cats'], true)) {
            continue;
        }
        if ($q['tag'] !== '' && !in_array($q['tag'], (array) $p['tags'], true)) {
            continue;
        }
        if ($q['source'] !== '' && ($p['source'] ?? '') !== $q['source']) {
            continue;
        }
        if (in_array($id, (array) $q['exclude'], true)) {
            continue;
        }
        if ($terms) {
            $hay = mb_strtolower(cms_ascii($p['title'] . ' ' . $p['excerpt'] . ' ' . implode(' ', (array) $p['tags'])));
            foreach ($terms as $t) {
                if (strpos($hay, $t) === false) {
                    continue 2;
                }
            }
        }
        $out[$id] = $p + ['id' => $id];
    }
    uasort($out, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
    return $out;
}

/* -------------------------------------------------------------------------
 * Categorias e tags
 * ---------------------------------------------------------------------- */

function s7x_blog_terms(bool $fresh = false): array
{
    if (!isset($GLOBALS['s7x_blog_terms']) || $fresh) {
        $GLOBALS['s7x_blog_terms'] = cms_read_json(S7X_BLOG_DIR . '/terms.json', []) + ['categories' => [], 'tags' => []];
    }
    return $GLOBALS['s7x_blog_terms'];
}

function s7x_blog_save_terms(array $terms): bool
{
    s7x_blog_dir();
    ksort($terms['tags']);
    $ok = cms_write_json(S7X_BLOG_DIR . '/terms.json', $terms);
    s7x_blog_terms(true);
    return $ok;
}

/** Garante a categoria/tag e devolve o slug. */
function s7x_blog_ensure_term(string $tax, string $name, string $slug = ''): string
{
    $name = trim(html_entity_decode(strip_tags($name), ENT_QUOTES, 'UTF-8'));
    $slug = cms_slug($slug !== '' ? $slug : $name);
    if ($slug === '') {
        return '';
    }
    $terms = s7x_blog_terms(true);
    if (!isset($terms[$tax][$slug])) {
        $terms[$tax][$slug] = ['name' => $name !== '' ? $name : $slug, 'description' => ''];
        s7x_blog_save_terms($terms);
    }
    return $slug;
}

function s7x_blog_term_name(string $tax, string $slug): string
{
    return (string) (s7x_blog_terms()[$tax][$slug]['name'] ?? $slug);
}

/** Categorias com contagem de posts publicados, em ordem alfabética. */
function s7x_blog_categories(bool $withEmpty = false): array
{
    $terms = s7x_blog_terms()['categories'];
    $count = [];
    foreach (s7x_blog_query() as $p) {
        foreach ((array) $p['cats'] as $c) {
            $count[$c] = ($count[$c] ?? 0) + 1;
        }
    }
    $out = [];
    foreach ($terms as $slug => $t) {
        $n = $count[$slug] ?? 0;
        if ($n || $withEmpty) {
            $out[$slug] = ['slug' => $slug, 'name' => $t['name'], 'description' => $t['description'] ?? '', 'count' => $n, 'hidden' => !empty($t['hidden'])];
        }
    }
    uasort($out, fn ($a, $b) => strcoll(cms_ascii($a['name']), cms_ascii($b['name'])));
    return $out;
}

/** Categoria principal de um post (a primeira que não seja genérica). */
function s7x_blog_primary_cat(array $post): string
{
    $generic = ['uncategorized', 'sem-categoria', 'noticias', 'novidades', 'blog'];
    foreach ((array) $post['cats'] as $c) {
        if (!in_array($c, $generic, true)) {
            return $c;
        }
    }
    return (string) ($post['cats'][0] ?? '');
}

/* -------------------------------------------------------------------------
 * HTML seguro para artigos
 * ---------------------------------------------------------------------- */

function s7x_blog_clean_html(string $html): string
{
    $html = trim($html);
    if ($html === '' || !class_exists('DOMDocument')) {
        return $html === '' ? '' : nl2br(e(strip_tags($html)), false);
    }
    // Comentários do Gutenberg e do Elementor
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [], 'mark' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [], 'a' => ['href', 'title', 'target', 'rel'], 'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'h5' => ['id'],
        'blockquote' => [], 'figure' => [], 'figcaption' => [], 'img' => ['src', 'alt', 'width', 'height', 'title'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'hr' => [], 'pre' => [], 'code' => [], 'nav' => ['aria-label'], 'div' => [], 'span' => [], 'iframe' => ['src', 'width', 'height', 'title', 'allowfullscreen'],
        'details' => [], 'summary' => [], 'cite' => [], 'small' => [], 'aside' => [],
    ];
    $drop = ['script', 'style', 'object', 'embed', 'form', 'input', 'textarea', 'select', 'button', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'video', 'audio', 'canvas', 'ins'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="s7x-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('s7x-root');
    if (!$root) {
        return '';
    }
    $classOk = function (string $c): bool {
        return (bool) preg_match('/^(s7x|blog|shc)-[a-z0-9_-]+$/', $c) || in_array($c, ['aligncenter', 'alignleft', 'alignright', 'alignwide'], true);
    };
    $walk = function (DOMNode $node) use (&$walk, $allowed, $drop, $classOk) {
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
            // Blocos de anúncio e da rede antiga: são gerados na hora pelo site novo
            $cls = ' ' . $child->getAttribute('class') . ' ';
            if (preg_match('/ (shc-ad|shc-network|shc-featured-credit|sharedaddy|jp-relatedposts|wp-block-buttons) /', $cls)) {
                $node->removeChild($child);
                continue;
            }
            if ($tag === 'iframe') {
                $src = $child->getAttribute('src');
                if (!preg_match('#^https://(www\.)?(youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/|www\.google\.com/maps/embed)#', $src)) {
                    $node->removeChild($child);
                    continue;
                }
            }
            $walk($child);
            if (!isset($allowed[$tag])) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $keep = [];
            foreach ($allowed[$tag] as $a) {
                if ($child->hasAttribute($a)) {
                    $keep[$a] = $child->getAttribute($a);
                }
            }
            if ($tag === 'a') {
                $href = trim($keep['href'] ?? '');
                if (!preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $href)) {
                    unset($keep['href']);
                }
                if (isset($keep['href']) && preg_match('#^https?:#i', $keep['href']) && strpos($keep['href'], cms_site_url()) !== 0) {
                    $keep['target'] = '_blank';
                    $keep['rel'] = trim(($keep['rel'] ?? '') . ' noopener');
                } else {
                    unset($keep['target'], $keep['rel']);
                }
            }
            if ($tag === 'img') {
                $src = trim($keep['src'] ?? '');
                if ($src === '' || preg_match('#^(javascript|data):#i', $src)) {
                    $node->removeChild($child);
                    continue;
                }
                // Lazy-load das imagens do WordPress (data-src)
                if ($child->hasAttribute('data-src') && preg_match('#^(https?:|/)#', $child->getAttribute('data-src'))) {
                    $keep['src'] = $child->getAttribute('data-src');
                }
            }
            if (in_array($tag, ['h2', 'h3', 'h4', 'h5'], true) && isset($keep['id'])) {
                $keep['id'] = preg_replace('/[^a-z0-9_-]/', '', strtolower($keep['id']));
            }
            $classes = array_filter(preg_split('/\s+/', trim($child->getAttribute('class'))), $classOk);
            $attrs = [];
            foreach ($child->attributes as $a) {
                $attrs[] = $a->name;
            }
            foreach ($attrs as $a) {
                $child->removeAttribute($a);
            }
            foreach ($keep as $k => $v) {
                if ($v !== '') {
                    $child->setAttribute($k, $v);
                }
            }
            if ($classes) {
                $child->setAttribute('class', implode(' ', $classes));
            }
            // div/span sem classe útil: desembrulha
            if (in_array($tag, ['div', 'span', 'aside'], true) && !$classes) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    $out = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#i', '', (string) $out);
    // h1 dentro do texto vira h2 (o h1 é o título do post)
    $out = preg_replace('#<(/?)h1([^>]*)>#i', '<$1h2$2>', $out);
    return trim($out);
}

/* -------------------------------------------------------------------------
 * Imagens
 * ---------------------------------------------------------------------- */

/** Baixa um arquivo (segue redirecionamentos). Retorna o conteúdo ou ''. */
function s7x_blog_download(string $url, int $timeout = 40, int $maxBytes = 20971520): string
{
    if (!preg_match('#^https?://#i', $url)) {
        return '';
    }
    $pre = cms_apply_filters('http_request', null, 'GET', $url, [], null);
    if (is_array($pre)) {
        return (string) ($pre['body'] ?? '');
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Sev7nXpress/' . CMS_VERSION, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ($code >= 200 && $code < 300 && strlen($body) <= $maxBytes) ? $body : '';
    }
    $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => $timeout, 'follow_location' => 1, 'user_agent' => 'Sev7nXpress']]));
    return is_string($body) ? $body : '';
}

/**
 * Grava uma imagem (bytes) em uploads/AAAA/MM/ como WebP, com largura máxima.
 * Retorna o caminho relativo (uploads/...) ou ''.
 */
function s7x_blog_store_image(string $bytes, string $name, string $alt = '', ?string $date = null, int $maxW = 0): string
{
    if ($bytes === '' || !function_exists('imagecreatefromstring')) {
        return '';
    }
    $info = @getimagesizefromstring($bytes);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        return '';
    }
    $src = @imagecreatefromstring($bytes);
    if (!$src) {
        return '';
    }
    $maxW = $maxW ?: (int) cms_config('blog_image_max_width', 1200);
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w > $maxW) {
        $nh = (int) round($h * $maxW / $w);
        $dst = imagecreatetruecolor($maxW, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
        $w = $maxW;
        $h = $nh;
    }
    $ts = $date ? strtotime($date) : time();
    $sub = date('Y', $ts ?: time()) . '/' . date('m', $ts ?: time());
    $dir = CMS_UPLOADS . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($src);
        return '';
    }
    $base = substr(cms_slug($name) ?: 'imagem', 0, 70);
    $file = $base;
    $n = 2;
    while (is_file($dir . '/' . $file . '.webp')) {
        $file = $base . '-' . $n++;
    }
    imagealphablending($src, false);
    imagesavealpha($src, true);
    $ok = function_exists('imagewebp') ? @imagewebp($src, $dir . '/' . $file . '.webp', (int) cms_config('webp_quality', 80)) : false;
    $ext = 'webp';
    if (!$ok) {
        $ext = 'jpg';
        $ok = @imagejpeg($src, $dir . '/' . $file . '.jpg', 84);
    }
    imagedestroy($src);
    if (!$ok) {
        return '';
    }
    $rel = 'uploads/' . $sub . '/' . $file . '.' . $ext;
    @chmod(CMS_ROOT . '/' . $rel, 0644);
    $metaFile = CMS_DATA . '/media.json';
    $meta = cms_read_json($metaFile, []);
    $meta[$rel] = ['alt' => trim(strip_tags($alt)), 'name' => $file . '.' . $ext, 'w' => $w, 'h' => $h, 'size' => (int) @filesize(CMS_ROOT . '/' . $rel), 'date' => date('c')];
    cms_write_json($metaFile, $meta);
    return $rel;
}

/** Baixa e grava uma imagem remota. */
function s7x_blog_sideload(string $url, string $name, string $alt = '', ?string $date = null): string
{
    return s7x_blog_store_image(s7x_blog_download($url), $name, $alt, $date);
}

/** Largura/altura de uma imagem local (para evitar saltos de layout). */
function s7x_blog_img_size(string $src): array
{
    static $meta = null;
    if ($meta === null) {
        $meta = cms_read_json(CMS_DATA . '/media.json', []);
    }
    $rel = ltrim($src, '/');
    if (!empty($meta[$rel]['w'])) {
        return [(int) $meta[$rel]['w'], (int) $meta[$rel]['h']];
    }
    $file = CMS_ROOT . '/' . $rel;
    if (strpos($rel, 'uploads/') === 0 && is_file($file) && ($s = @getimagesize($file))) {
        return [(int) $s[0], (int) $s[1]];
    }
    return [0, 0];
}

/* -------------------------------------------------------------------------
 * Visualizações
 * ---------------------------------------------------------------------- */

function s7x_blog_views(): array
{
    return cms_read_json(S7X_BLOG_DIR . '/views.json', []);
}

function s7x_blog_count_view(string $id): void
{
    if (!s7x_blog_opt('views', true) || !isset(s7x_blog_index()['posts'][$id])) {
        return;
    }
    $file = s7x_blog_dir() . '/views.json';
    $fp = @fopen($file, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        return;
    }
    $raw = stream_get_contents($fp);
    $data = json_decode((string) $raw, true) ?: [];
    $data[$id] = ['t' => (int) ($data[$id]['t'] ?? 0) + 1, 'w' => (int) ($data[$id]['w'] ?? 0) + 1, 'wk' => date('oW')] + ($data[$id] ?? []);
    if (($data[$id]['wk'] ?? '') !== date('oW')) {
        $data[$id]['w'] = 1;
        $data[$id]['wk'] = date('oW');
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/** Mais lidos (total), só publicados. */
function s7x_blog_popular(int $n = 5): array
{
    $views = s7x_blog_views();
    $pub = s7x_blog_query();
    uksort($views, fn ($a, $b) => ((int) ($views[$b]['t'] ?? 0)) <=> ((int) ($views[$a]['t'] ?? 0)));
    $out = [];
    foreach ($views as $id => $v) {
        if (isset($pub[$id])) {
            $out[$id] = $pub[$id] + ['views' => (int) $v['t']];
            if (count($out) >= $n) {
                break;
            }
        }
    }
    // Sem visualizações ainda: completa com os mais recentes
    foreach ($pub as $id => $p) {
        if (count($out) >= $n) {
            break;
        }
        $out[$id] = $out[$id] ?? $p + ['views' => 0];
    }
    return $out;
}

/* -------------------------------------------------------------------------
 * Redirecionamentos (endereços antigos do WordPress)
 * ---------------------------------------------------------------------- */

function s7x_blog_redirects(): array
{
    return cms_read_json(S7X_BLOG_DIR . '/redirects.json', []);
}

function s7x_blog_add_redirects(array $map): void
{
    if (!$map) {
        return;
    }
    $all = s7x_blog_redirects();
    foreach ($map as $from => $to) {
        $all['/' . ltrim((string) $from, '/')] = (string) $to;
    }
    s7x_blog_dir();
    cms_write_json(S7X_BLOG_DIR . '/redirects.json', $all);
}
