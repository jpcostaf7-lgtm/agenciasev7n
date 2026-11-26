<?php
/**
 * Módulo Blog — endereços, páginas do site, RSS, sitemap e dados estruturados
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_blog_base(): string
{
    return trim(cms_slug((string) s7x_blog_opt('base', 'blog')), '/') ?: 'blog';
}

function s7x_blog_url(int $page = 1, bool $abs = false): string
{
    $path = '/' . s7x_blog_base() . '/' . ($page > 1 ? 'page/' . $page . '/' : '');
    return $abs ? cms_site_url() . $path : cms_url($path);
}

function s7x_blog_post_path(array $post): string
{
    $prefix = s7x_blog_opt('post_prefix', 'root') === 'base' ? '/' . s7x_blog_base() : '';
    return $prefix . '/' . $post['slug'] . '/';
}

function s7x_blog_post_url(array $post, bool $abs = false): string
{
    $path = s7x_blog_post_path($post);
    return $abs ? cms_site_url() . $path : cms_url($path);
}

function s7x_blog_term_url(string $tax, string $slug, int $page = 1, bool $abs = false): string
{
    $base = $tax === 'tags' ? (s7x_blog_opt('tag_base', 'tag') ?: 'tag') : (s7x_blog_opt('category_base', 'category') ?: 'category');
    $path = '/' . trim($base, '/') . '/' . $slug . '/' . ($page > 1 ? 'page/' . $page . '/' : '');
    return $abs ? cms_site_url() . $path : cms_url($path);
}

function s7x_blog_search_url(string $q = ''): string
{
    return s7x_blog_url() . ($q !== '' ? '?q=' . rawurlencode($q) : '');
}

/** "15 de novembro de 2026" */
function s7x_blog_date(?string $iso, bool $short = false): string
{
    $t = $iso ? strtotime($iso) : false;
    if (!$t) {
        return '';
    }
    $m = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][(int) date('n', $t) - 1];
    return $short ? date('d', $t) . ' ' . mb_substr($m, 0, 3) . ' ' . date('Y', $t) : (int) date('j', $t) . ' de ' . $m . ' de ' . date('Y', $t);
}

function s7x_blog_reading(int $words): string
{
    return max(1, (int) round($words / 220)) . ' min de leitura';
}

function s7x_blog_author(): string
{
    return trim((string) s7x_blog_opt('author_name', '')) ?: (trim((string) cms_seo('org_name')) ?: cms_site_name());
}

/* -------------------------------------------------------------------------
 * Boot
 * ---------------------------------------------------------------------- */

function s7x_blog_boot(): void
{
    cms_register_block('blog-latest', [
        'label'       => 'Últimos posts do blog',
        'icon'        => 'folha',
        'group'       => 'Blog',
        'description' => 'Os posts mais recentes (de todo o blog ou de uma categoria) em cards, com botão para ver todos.',
        'template'    => __DIR__ . '/templates/latest.php',
        'fields'      => [
            'eyebrow'  => ['type' => 'text', 'label' => 'Rótulo acima do título', 'default' => ''],
            'title'    => ['type' => 'text', 'label' => 'Título', 'default' => 'Últimas do blog'],
            'text'     => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2, 'default' => ''],
            'count'    => ['type' => 'select', 'label' => 'Quantidade', 'default' => '3', 'options' => ['3' => '3 posts', '4' => '4 posts', '6' => '6 posts', '8' => '8 posts']],
            'category' => ['type' => 'text', 'label' => 'Só da categoria (slug, opcional)', 'default' => '', 'placeholder' => 'pre-sal'],
            'layout'   => ['type' => 'select', 'label' => 'Formato', 'default' => 'destaque', 'options' => ['destaque' => 'Um grande + os outros ao lado', 'grade' => 'Grade de cards iguais']],
            'button'   => ['type' => 'text', 'label' => 'Botão', 'default' => 'Ver todos os posts'],
        ],
    ]);
    // Blocos internos das páginas do blog (não aparecem na biblioteca)
    cms_register_block('blog-post', ['label' => 'Post do blog', 'hidden' => true, 'group' => 'Blog', 'template' => __DIR__ . '/templates/post.php']);
    cms_register_block('blog-archive', ['label' => 'Lista do blog', 'hidden' => true, 'group' => 'Blog', 'template' => __DIR__ . '/templates/archive.php']);

    cms_add_filter('reserved_slugs', function (array $s) {
        $s[] = s7x_blog_base();
        $s[] = (string) s7x_blog_opt('category_base', 'category');
        $s[] = (string) s7x_blog_opt('tag_base', 'tag');
        $s[] = 'feed';
        return $s;
    });
    cms_add_filter('route', 's7x_blog_route', 10);
    cms_add_action('request', 's7x_blog_early_request', 1);
    cms_add_filter('sitemap_urls', 's7x_blog_sitemap');
    cms_add_filter('structured_data', 's7x_blog_schema');
    cms_add_action('head', 's7x_blog_head');
    cms_add_action('cron', function () {
        s7x_blog_publish_due();
    }, 1);
    s7x_blog_admin_boot();
    s7x_blog_import_boot();
}

/* -------------------------------------------------------------------------
 * Pedidos antes da página: visualização, feed, redirecionamentos antigos
 * ---------------------------------------------------------------------- */

function s7x_blog_early_request(): void
{
    $path = cms_request_path();
    // Contador de visualizações (chamado pelo blog.js depois que a página carrega)
    if ($path === '/' && isset($_GET['s7x_blog_view']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        s7x_blog_count_view(preg_replace('/[^p0-9]/', '', (string) $_GET['s7x_blog_view']));
        http_response_code(204);
        exit;
    }
    // Busca do WordPress (?s=) e posts por id (?p=)
    if (isset($_GET['s']) && is_string($_GET['s'])) {
        s7x_blog_redirect(s7x_blog_search_url(trim($_GET['s'])));
    }
    if ($path === '/' && isset($_GET['p']) && ctype_digit((string) $_GET['p'])) {
        $id = s7x_blog_index()['wp'][(string) $_GET['p']] ?? null;
        $post = $id ? s7x_blog_get($id) : null;
        if ($post && $post['status'] === 'publish') {
            s7x_blog_redirect(s7x_blog_post_url($post));
        }
    }
    // Feeds
    $base = s7x_blog_base();
    $cb = trim((string) s7x_blog_opt('category_base', 'category'), '/');
    if (in_array($path, ['/feed', '/' . $base . '/feed', '/rss'], true)) {
        s7x_blog_feed();
    }
    if (preg_match('#^/' . preg_quote($cb, '#') . '/([a-z0-9-]+)/feed$#', $path, $m)) {
        s7x_blog_feed($m[1]);
    }
    // Endereços antigos (imagens e páginas do WordPress)
    $red = s7x_blog_redirects();
    if (isset($red[$path]) || isset($red[$path . '/'])) {
        s7x_blog_redirect(cms_url((string) ($red[$path] ?? $red[$path . '/'])));
    }
    if (strpos($path, '/wp-content/uploads/') === 0) {
        // Miniaturas do WordPress (foto-300x200.jpg) apontam para a imagem original
        $orig = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $path);
        foreach ([$orig, preg_replace('/\.\w+$/', '', $orig)] as $try) {
            if (isset($red[$try])) {
                s7x_blog_redirect(cms_url((string) $red[$try]));
            }
        }
    }
    if (preg_match('#^/(wp-admin|wp-login\.php|xmlrpc\.php|wp-json)#', $path)) {
        http_response_code(410);
        header('X-Robots-Tag: noindex');
        echo 'Este site não usa WordPress.';
        exit;
    }
}

function s7x_blog_redirect(string $to, int $code = 301): void
{
    header('Location: ' . $to, true, $code);
    exit;
}

/* -------------------------------------------------------------------------
 * Roteamento das páginas do blog
 * ---------------------------------------------------------------------- */

function s7x_blog_route($page, string $path)
{
    if ($page) {
        return $page;
    }
    $base = s7x_blog_base();
    $cb = trim((string) s7x_blog_opt('category_base', 'category'), '/');
    $tb = trim((string) s7x_blog_opt('tag_base', 'tag'), '/');
    $parts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $n = 1;
    // .../page/N
    if (count($parts) >= 2 && $parts[count($parts) - 2] === 'page' && ctype_digit($parts[count($parts) - 1])) {
        $n = max(1, (int) array_pop($parts));
        array_pop($parts);
    }
    if (!$parts && $n > 1) {
        s7x_blog_redirect(s7x_blog_url($n)); // /page/2/ do WordPress
    }
    if (!$parts) {
        return null;
    }
    s7x_blog_publish_due();

    // Página do blog
    if ($parts === [$base]) {
        $q = trim((string) ($_GET['q'] ?? ''));
        return s7x_blog_archive_page('blog', '', $n, $q);
    }
    // Categoria e tag
    if (count($parts) === 2 && ($parts[0] === $cb || $parts[0] === $tb)) {
        $tax = $parts[0] === $cb ? 'categories' : 'tags';
        $terms = s7x_blog_terms();
        if (!isset($terms[$tax][$parts[1]])) {
            return null;
        }
        return s7x_blog_archive_page($tax, $parts[1], $n);
    }
    // /blog/categoria-x/ (páginas de categoria antigas) → categoria
    if (count($parts) === 2 && $parts[0] === $base) {
        $terms = s7x_blog_terms();
        if (isset($terms['categories'][$parts[1]])) {
            s7x_blog_redirect(s7x_blog_term_url('categories', $parts[1], $n));
        }
        if (s7x_blog_opt('post_prefix', 'root') === 'base' && ($post = s7x_blog_by_slug($parts[1]))) {
            return s7x_blog_post_page($post);
        }
    }
    // Post
    if (count($parts) === 1 && $n === 1) {
        $post = s7x_blog_by_slug($parts[0]);
        if ($post) {
            if (s7x_blog_opt('post_prefix', 'root') === 'base') {
                s7x_blog_redirect(s7x_blog_post_url($post));
            }
            return s7x_blog_post_page($post);
        }
    }
    return null;
}

/** O post pode ser visto? (publicado, ou rascunho para quem está logado no painel) */
function s7x_blog_visible(array $post): bool
{
    if ($post['status'] === 'publish' || ($post['status'] === 'future' && strtotime($post['date']) <= time())) {
        return true;
    }
    if ($post['status'] === 'trash') {
        return false;
    }
    require_once CMS_ROOT . '/cms/admin_lib.php';
    cms_session_start();
    return cms_logged_in();
}

function s7x_blog_post_page(array $post): ?array
{
    if (!s7x_blog_visible($post)) {
        return null;
    }
    $GLOBALS['s7x_blog_post'] = $post;
    $seo = (array) $post['seo'];
    $cat = s7x_blog_primary_cat($post);
    $crumbs = [[s7x_blog_opt('title', 'Blog'), s7x_blog_url(1, true)]];
    // Categoria com o mesmo nome do blog (ex.: "Notícias" dentro de Notícias) não se repete na trilha
    if ($cat !== '' && mb_strtolower(s7x_blog_term_name('categories', $cat)) !== mb_strtolower((string) s7x_blog_opt('title', 'Blog'))) {
        $crumbs[] = [s7x_blog_term_name('categories', $cat), s7x_blog_term_url('categories', $cat, 1, true)];
    }
    $crumbs[] = [$post['title'], s7x_blog_post_url($post, true)];
    $canonical = trim((string) ($seo['canonical'] ?? ''));
    return [
        'id' => 'blog-' . $post['id'], 'title' => $post['title'], 'slug' => $post['slug'], 'home' => false,
        'status' => $post['status'] === 'publish' || $post['status'] === 'future' ? 'published' : 'draft',
        'seo' => [
            'title'       => trim((string) ($seo['title'] ?? '')) ?: $post['title'] . ' | ' . cms_site_name(),
            'description' => trim((string) ($seo['description'] ?? '')) ?: mb_strimwidth((string) $post['excerpt'], 0, 158, '…'),
            'og_image'    => $post['image'],
            'noindex'     => !empty($seo['noindex']),
        ],
        'meta' => ['url' => $canonical !== '' ? $canonical : s7x_blog_post_url($post, true), 'og_type' => 'article'],
        'breadcrumb' => $crumbs,
        'blog' => ['type' => 'post', 'post' => $post],
        'blocks' => [['id' => 'blogpost', 'type' => 'blog-post', 'enabled' => true, 'anchor' => '', 'data' => []]],
    ];
}

function s7x_blog_archive_page(string $tax, string $slug, int $n, string $search = ''): ?array
{
    $per = max(1, min(60, (int) s7x_blog_opt('per_page', 12)));
    $q = ['status' => 'publish'];
    if ($tax === 'categories') {
        $q['cat'] = $slug;
    } elseif ($tax === 'tags') {
        $q['tag'] = $slug;
    }
    if ($search !== '') {
        $q['search'] = mb_substr($search, 0, 80);
    }
    $all = s7x_blog_query($q);
    $pages = max(1, (int) ceil(count($all) / $per));
    if ($n > $pages) {
        return null;
    }
    $items = array_slice($all, ($n - 1) * $per, $per, true);
    $terms = s7x_blog_terms();
    if ($tax === 'blog') {
        $title = $search !== '' ? 'Busca: ' . $search : (string) s7x_blog_opt('title', 'Blog');
        $desc = $search !== '' ? count($all) . ' resultado(s) no blog.' : (string) s7x_blog_opt('description', '');
        $url = s7x_blog_url($n, true);
        $crumbs = [[s7x_blog_opt('title', 'Blog'), s7x_blog_url(1, true)]];
    } else {
        $t = $terms[$tax][$slug] ?? ['name' => $slug];
        $title = (string) $t['name'];
        $desc = trim((string) ($t['description'] ?? '')) ?: ($tax === 'tags' ? 'Posts sobre ' . $title . '.' : 'Notícias e artigos sobre ' . $title . '.');
        $url = s7x_blog_term_url($tax, $slug, $n, true);
        $crumbs = [[s7x_blog_opt('title', 'Blog'), s7x_blog_url(1, true)], [$title, s7x_blog_term_url($tax, $slug, 1, true)]];
    }
    return [
        'id' => 'blog-' . $tax . '-' . $slug, 'title' => $title, 'slug' => ltrim(parse_url($url, PHP_URL_PATH) ?: '', '/'), 'home' => false, 'status' => 'published',
        'seo' => [
            'title'       => $title . ($n > 1 ? ' — página ' . $n : '') . ' | ' . cms_site_name(),
            'description' => mb_strimwidth(strip_tags($desc), 0, 158, '…') ?: (string) cms_seo('description'),
            'noindex'     => $search !== '' || ($tax === 'tags' && count($all) < 2),
        ],
        'meta' => ['url' => $url],
        'breadcrumb' => $crumbs,
        'blog' => ['type' => 'archive', 'tax' => $tax, 'slug' => $slug, 'page' => $n, 'pages' => $pages, 'items' => $items, 'total' => count($all), 'search' => $search, 'title' => $title, 'description' => $desc],
        'blocks' => [['id' => 'blogarchive', 'type' => 'blog-archive', 'enabled' => true, 'anchor' => '', 'data' => []]],
    ];
}

/* -------------------------------------------------------------------------
 * <head>: CSS do blog, datas do artigo e paginação
 * ---------------------------------------------------------------------- */

function s7x_blog_head(?array $page): void
{
    $isBlog = !empty($page['blog']) || cms_page_has_block($page, 'blog-latest');
    if ($isBlog && !cms_apply_filters('blog_skip_css', false)) {
        echo '<link rel="stylesheet" href="' . e(cms_module_url('blog', 'assets/blog.css')) . '">' . "\n";
    }
    echo '<link rel="alternate" type="application/rss+xml" title="' . e(s7x_blog_opt('title', 'Blog') . ' — ' . cms_site_name()) . '" href="' . e(cms_site_url() . '/feed/') . '">' . "\n";
    if (($page['blog']['type'] ?? '') === 'post') {
        $p = $page['blog']['post'];
        echo '<meta property="article:published_time" content="' . e(date('c', strtotime($p['date']))) . '">' . "\n";
        echo '<meta property="article:modified_time" content="' . e(date('c', strtotime($p['modified'] ?: $p['date']))) . '">' . "\n";
        foreach (array_slice((array) $p['tags'], 0, 6) as $t) {
            echo '<meta property="article:tag" content="' . e(s7x_blog_term_name('tags', $t)) . '">' . "\n";
        }
        if (s7x_blog_opt('views', true) && $p['status'] === 'publish') {
            echo '<script>window.S7X_BLOG_VIEW=' . json_encode(cms_url('') . '?s7x_blog_view=' . $p['id']) . ';</script>' . "\n";
        }
    }
    if (($page['blog']['type'] ?? '') === 'archive') {
        $b = $page['blog'];
        $u = fn (int $i) => $b['tax'] === 'blog' ? s7x_blog_url($i, true) : s7x_blog_term_url($b['tax'], $b['slug'], $i, true);
        if ($b['page'] > 1) {
            echo '<link rel="prev" href="' . e($u($b['page'] - 1)) . '">' . "\n";
        }
        if ($b['page'] < $b['pages']) {
            echo '<link rel="next" href="' . e($u($b['page'] + 1)) . '">' . "\n";
        }
    }
    if ($isBlog) {
        echo '<script src="' . e(cms_module_url('blog', 'assets/blog.js')) . '" defer></script>' . "\n";
    }
}

function s7x_blog_schema(array $out, array $page): array
{
    if (($page['blog']['type'] ?? '') !== 'post') {
        return $out;
    }
    $p = $page['blog']['post'];
    $url = s7x_blog_post_url($p, true);
    $org = cms_site_url() . '/#negocio';
    $art = [
        '@context' => 'https://schema.org', '@type' => cms_apply_filters('blog_schema_type', 'BlogPosting', $p),
        '@id' => $url . '#artigo', 'mainEntityOfPage' => $url, 'headline' => mb_substr($p['title'], 0, 110),
        'description' => (string) $p['excerpt'], 'datePublished' => date('c', strtotime($p['date'])),
        'dateModified' => date('c', strtotime($p['modified'] ?: $p['date'])), 'inLanguage' => (string) cms_seo('lang', 'pt-BR'),
        'author' => ['@type' => 'Organization', 'name' => s7x_blog_author(), 'url' => cms_site_url() . '/'],
        'publisher' => ['@id' => $org], 'wordCount' => (int) $p['words'],
    ];
    $img = cms_image_value($p['image']);
    if ($img['src'] !== '') {
        $art['image'] = cms_abs_url($img['src']);
    }
    $cat = s7x_blog_primary_cat($p);
    if ($cat !== '') {
        $art['articleSection'] = s7x_blog_term_name('categories', $cat);
    }
    if ($p['tags']) {
        $art['keywords'] = implode(', ', array_map(fn ($t) => s7x_blog_term_name('tags', $t), array_slice((array) $p['tags'], 0, 10)));
    }
    $out[] = $art;
    $faq = [];
    foreach ((array) $p['faq'] as $f) {
        if (!empty($f['q']) && !empty($f['a'])) {
            $faq[] = ['@type' => 'Question', 'name' => (string) $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $f['a']]];
        }
    }
    if ($faq) {
        $out[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faq];
    }
    return $out;
}

function s7x_blog_sitemap(array $urls): array
{
    $urls[] = ['loc' => s7x_blog_url(1, true), 'lastmod' => date('c')];
    foreach (s7x_blog_query() as $p) {
        if (!empty($p['noindex'])) {
            continue;
        }
        $img = cms_image_value($p['image']);
        $urls[] = ['loc' => s7x_blog_post_url($p, true), 'lastmod' => $p['modified'] ?: $p['date'], 'image' => $img['src'] !== '' ? cms_abs_url($img['src']) : ''];
    }
    foreach (s7x_blog_categories() as $c) {
        $urls[] = ['loc' => s7x_blog_term_url('categories', $c['slug'], 1, true)];
    }
    return $urls;
}

/* -------------------------------------------------------------------------
 * RSS
 * ---------------------------------------------------------------------- */

function s7x_blog_feed(string $cat = ''): void
{
    $items = array_slice(s7x_blog_query(['cat' => $cat]), 0, 20, true);
    header('Content-Type: application/rss+xml; charset=utf-8');
    $title = s7x_blog_opt('title', 'Blog') . ' — ' . cms_site_name() . ($cat !== '' ? ' — ' . s7x_blog_term_name('categories', $cat) : '');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel>' . "\n";
    echo '<title>' . e($title) . '</title><link>' . e(s7x_blog_url(1, true)) . '</link>';
    echo '<description>' . e((string) s7x_blog_opt('description', '') ?: (string) cms_seo('description')) . '</description>';
    echo '<language>' . e(strtolower((string) cms_seo('lang', 'pt-BR'))) . '</language>';
    echo '<atom:link href="' . e(cms_site_url() . cms_request_path() . '/') . '" rel="self" type="application/rss+xml"/>' . "\n";
    foreach ($items as $p) {
        $url = s7x_blog_post_url($p, true);
        echo '<item><title>' . e($p['title']) . '</title><link>' . e($url) . '</link><guid isPermaLink="true">' . e($url) . '</guid>';
        echo '<pubDate>' . e(date(DATE_RSS, strtotime($p['date']))) . '</pubDate>';
        foreach ((array) $p['cats'] as $c) {
            echo '<category>' . e(s7x_blog_term_name('categories', $c)) . '</category>';
        }
        echo '<description>' . e($p['excerpt']) . '</description>';
        $img = cms_image_value($p['image']);
        if ($img['src'] !== '') {
            echo '<media:content url="' . e(cms_abs_url($img['src'])) . '" medium="image"/>';
        }
        echo "</item>\n";
    }
    echo '</channel></rss>';
    exit;
}

/* -------------------------------------------------------------------------
 * Peças de layout usadas pelos templates (temas podem reaproveitar)
 * ---------------------------------------------------------------------- */

/** <img> de uma imagem do post com tamanho e lazy-load. */
function s7x_blog_img(array $img, string $class = '', bool $eager = false, string $sizes = '(max-width: 760px) 100vw, 720px'): string
{
    $img = cms_image_value($img);
    if ($img['src'] === '') {
        return '';
    }
    [$w, $h] = s7x_blog_img_size($img['src']);
    return '<img src="' . e(cms_url($img['src'])) . '" alt="' . e($img['alt']) . '"' . ($w ? ' width="' . $w . '" height="' . $h . '"' : '')
        . ($class !== '' ? ' class="' . e($class) . '"' : '') . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async" sizes="' . e($sizes) . '">';
}

function s7x_blog_cat_chip(array $post, string $class = 'blog-chip'): string
{
    $cat = s7x_blog_primary_cat($post);
    if ($cat === '') {
        return '';
    }
    return '<a class="' . e($class) . '" href="' . e(s7x_blog_term_url('categories', $cat)) . '">' . e(s7x_blog_term_name('categories', $cat)) . '</a>';
}

/** Capa para post sem imagem: fundo da marca com o nome da categoria. */
function s7x_blog_noimg(array $p): string
{
    $cat = s7x_blog_primary_cat($p);
    $name = $cat !== '' ? s7x_blog_term_name('categories', $cat) : (string) s7x_blog_opt('title', 'Blog');
    return '<span class="blog-card__noimg" data-cat="' . e($cat) . '"><span class="blog-card__noimg-label">' . e($name) . '</span></span>';
}

/** Card de post. $variant: card | destaque | linha | mini */
function s7x_blog_card(array $p, string $variant = 'card', string $headingTag = 'h3'): string
{
    $url = s7x_blog_post_url($p);
    $img = cms_image_value($p['image']);
    $h = '<article class="blog-card blog-card--' . e($variant) . '">';
    if ($variant !== 'mini' || $img['src'] !== '') {
        $h .= '<a class="blog-card__img" href="' . e($url) . '" tabindex="-1" aria-hidden="true">'
            . ($img['src'] !== '' ? s7x_blog_img($img, '', false, $variant === 'destaque' ? '(max-width: 760px) 100vw, 760px' : '(max-width: 760px) 100vw, 400px') : s7x_blog_noimg($p))
            . '</a>';
    }
    $h .= '<div class="blog-card__body">';
    if ($variant !== 'mini') {
        $h .= '<div class="blog-card__meta">' . s7x_blog_cat_chip($p) . '<time datetime="' . e(date('Y-m-d', strtotime($p['date']))) . '">' . e(s7x_blog_date($p['date'], true)) . '</time></div>';
    }
    $h .= '<' . $headingTag . ' class="blog-card__title"><a href="' . e($url) . '">' . e($p['title']) . '</a></' . $headingTag . '>';
    if (in_array($variant, ['card', 'destaque', 'linha'], true) && $p['excerpt'] !== '') {
        $h .= '<p class="blog-card__excerpt">' . e(mb_strimwidth((string) $p['excerpt'], 0, $variant === 'destaque' ? 220 : 150, '…')) . '</p>';
    }
    if ($variant === 'mini') {
        $h .= '<time class="blog-card__date" datetime="' . e(date('Y-m-d', strtotime($p['date']))) . '">' . e(s7x_blog_date($p['date'], true)) . '</time>';
    }
    return $h . '</div></article>';
}

function s7x_blog_pagination(int $cur, int $total, callable $url): string
{
    if ($total < 2) {
        return '';
    }
    $h = '<nav class="blog-pages" aria-label="Páginas">';
    if ($cur > 1) {
        $h .= '<a class="blog-pages__nav" href="' . e($url($cur - 1)) . '" rel="prev">← Anteriores</a>';
    }
    $show = array_unique(array_filter([1, 2, $cur - 1, $cur, $cur + 1, $total - 1, $total], fn ($i) => $i >= 1 && $i <= $total));
    sort($show);
    $last = 0;
    foreach ($show as $i) {
        if ($i - $last > 1) {
            $h .= '<span class="blog-pages__gap">…</span>';
        }
        $h .= $i === $cur ? '<span class="blog-pages__cur" aria-current="page">' . $i . '</span>' : '<a href="' . e($url($i)) . '">' . $i . '</a>';
        $last = $i;
    }
    if ($cur < $total) {
        $h .= '<a class="blog-pages__nav" href="' . e($url($cur + 1)) . '" rel="next">Próximos →</a>';
    }
    return $h . '</nav>';
}

function s7x_blog_breadcrumb(array $page): string
{
    $items = [['Início', cms_url('/')]];
    foreach ((array) ($page['breadcrumb'] ?? []) as $c) {
        $items[] = [$c[0], $c[1]];
    }
    $h = '<nav class="blog-crumbs" aria-label="Você está em"><ol>';
    foreach ($items as $i => $c) {
        $last = $i === count($items) - 1;
        $h .= '<li>' . ($last ? '<span aria-current="page">' . e(mb_strimwidth((string) $c[0], 0, 60, '…')) . '</span>' : '<a href="' . e($c[1]) . '">' . e($c[0]) . '</a>') . '</li>';
    }
    return $h . '</ol></nav>';
}

function s7x_blog_search_form(string $value = '', string $class = 'blog-search'): string
{
    return '<form class="' . e($class) . '" action="' . e(s7x_blog_url()) . '" method="get" role="search">'
        . '<label class="blog-sr" for="blogq-' . e($class) . '">Buscar no ' . e(mb_strtolower((string) s7x_blog_opt('title', 'blog'))) . '</label>'
        . '<input id="blogq-' . e($class) . '" type="search" name="q" value="' . e($value) . '" placeholder="Buscar notícias e artigos" maxlength="80">'
        . '<button type="submit" aria-label="Buscar"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M20 20l-4-4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></button></form>';
}

/** Barra de categorias (chips com rolagem no celular). */
function s7x_blog_cat_bar(string $current = ''): string
{
    $cats = array_filter(s7x_blog_categories(), fn ($c) => !$c['hidden'] && !in_array($c['slug'], ['uncategorized', 'sem-categoria', 'noticias', 'novidades', 'blog'], true));
    if (!$cats) {
        return '';
    }
    $h = '<nav class="blog-catbar" aria-label="Categorias"><ul>';
    $h .= '<li><a href="' . e(s7x_blog_url()) . '"' . ($current === '' ? ' aria-current="page"' : '') . '>Todas</a></li>';
    foreach ($cats as $c) {
        $h .= '<li><a href="' . e(s7x_blog_term_url('categories', $c['slug'])) . '"' . ($current === $c['slug'] ? ' aria-current="page"' : '') . '>' . e($c['name']) . '</a></li>';
    }
    return $h . '</ul></nav>';
}

/** Barra lateral do post. */
function s7x_blog_sidebar(array $post): string
{
    $h = '<aside class="blog-side" aria-label="Mais do blog">';
    $h .= '<section class="blog-side__box">' . s7x_blog_search_form('', 'blog-search blog-search--side') . '</section>';
    $pop = s7x_blog_popular(5);
    unset($pop[$post['id']]);
    if ($pop) {
        $h .= '<section class="blog-side__box"><h2 class="blog-side__title">Mais lidas</h2><ol class="blog-rank">';
        foreach (array_slice($pop, 0, 5, true) as $p) {
            $h .= '<li><a href="' . e(s7x_blog_post_url($p)) . '">' . e($p['title']) . '</a></li>';
        }
        $h .= '</ol></section>';
    }
    $cats = array_filter(s7x_blog_categories(), fn ($c) => !$c['hidden'] && !in_array($c['slug'], ['uncategorized', 'sem-categoria', 'noticias', 'novidades'], true));
    if ($cats) {
        $h .= '<section class="blog-side__box"><h2 class="blog-side__title">Categorias</h2><ul class="blog-side__cats">';
        foreach ($cats as $c) {
            $h .= '<li><a href="' . e(s7x_blog_term_url('categories', $c['slug'])) . '"><span>' . e($c['name']) . '</span><small>' . (int) $c['count'] . '</small></a></li>';
        }
        $h .= '</ul></section>';
    }
    $h .= cms_capture_action('blog_sidebar', $post);
    return $h . '</aside>';
}

/** Conteúdo do post pronto para exibir (imagens com lazy-load, tabelas roláveis e filtros de módulos). */
function s7x_blog_render_content(array $post): string
{
    $html = (string) $post['content'];
    $html = preg_replace_callback('#<img\b([^>]*)>#i', function ($m) {
        $a = $m[1];
        if (!preg_match('/\sloading=/i', $a)) {
            $a .= ' loading="lazy"';
        }
        if (!preg_match('/\sdecoding=/i', $a)) {
            $a .= ' decoding="async"';
        }
        if (!preg_match('/\swidth=/i', $a) && preg_match('/\ssrc="([^"]+)"/i', $a, $s)) {
            $rel = preg_replace('#^' . preg_quote(cms_url(''), '#') . '#', '', $s[1]);
            [$w, $h] = s7x_blog_img_size($rel);
            if ($w) {
                $a .= ' width="' . $w . '" height="' . $h . '"';
            }
        }
        return '<img' . $a . '>';
    }, $html);
    $html = preg_replace('#<table\b#i', '<div class="blog-table"><table', $html);
    $html = preg_replace('#</table>#i', '</table></div>', $html);
    $html = preg_replace('#<iframe\b#i', '<div class="blog-embed"><iframe loading="lazy"', $html);
    $html = preg_replace('#</iframe>#i', '</iframe></div>', $html);
    // Caminhos internos /uploads/... funcionam em subpasta
    $html = preg_replace('#(src|href)="/(uploads/[^"]+)"#', '$1="' . cms_url('') . '$2"', $html);
    return (string) cms_apply_filters('blog_content', $html, $post);
}

function s7x_blog_share(array $post): string
{
    if (!s7x_blog_opt('share', true)) {
        return '';
    }
    $u = rawurlencode(s7x_blog_post_url($post, true));
    $t = rawurlencode($post['title']);
    $links = [
        ['WhatsApp', 'https://api.whatsapp.com/send?text=' . $t . '%20' . $u, 'M12 2a10 10 0 0 0-8.6 15l-1.4 5 5.1-1.3A10 10 0 1 0 12 2zm5.2 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.8s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .6l-.4.6-.3.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.7-.1l1-1.2c.2-.3.4-.2.7-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.2z'],
        ['LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u, 'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9h4v12H3zM10 9h3.8v1.7h.1c.5-1 1.8-2 3.8-2 4 0 4.8 2.6 4.8 6.1V21h-4v-5.4c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9V21h-4z'],
        ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $u, 'M14 8h3V4h-3a5 5 0 0 0-5 5v2H7v4h2v7h4v-7h3l1-4h-4V9a1 1 0 0 1 1-1z'],
        ['X', 'https://twitter.com/intent/tweet?url=' . $u . '&text=' . $t, 'M17.8 3h3.1l-6.8 7.7 8 10.3h-6.3l-4.9-6.4L5.3 21H2.2l7.3-8.3L1.8 3h6.4l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z'],
    ];
    $h = '<div class="blog-share"><span class="blog-share__label">Compartilhe</span>';
    foreach ($links as $l) {
        $h .= '<a href="' . e($l[1]) . '" target="_blank" rel="noopener" aria-label="Compartilhar no ' . e($l[0]) . '"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="' . $l[2] . '"/></svg></a>';
    }
    $h .= '<button type="button" data-blog-copy="' . e(s7x_blog_post_url($post, true)) . '" aria-label="Copiar link"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg></button>';
    return $h . '</div>';
}

/** Relacionados: mesma categoria principal, depois tags em comum. */
function s7x_blog_related(array $post, int $n = 3): array
{
    if ($n < 1) {
        return [];
    }
    $cat = s7x_blog_primary_cat($post);
    $pool = s7x_blog_query(['exclude' => [$post['id']]]);
    $score = [];
    foreach ($pool as $id => $p) {
        $s = 0;
        if ($cat !== '' && in_array($cat, (array) $p['cats'], true)) {
            $s += 3;
        }
        $s += count(array_intersect((array) $post['tags'], (array) $p['tags']));
        if ($s > 0) {
            $score[$id] = $s * 100000000 + (int) strtotime($p['date']) / 100;
        }
    }
    arsort($score);
    $out = [];
    foreach (array_slice(array_keys($score), 0, $n) as $id) {
        $out[$id] = $pool[$id];
    }
    return $out;
}
