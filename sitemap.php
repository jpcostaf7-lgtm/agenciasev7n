<?php
/** Sitemap automático — Sev7n Xpress | Autor: João Costa */
declare(strict_types=1);
define('CMS_ENTRY_DEPTH', 0);
require __DIR__ . '/cms/core.php';
header('Content-Type: application/xml; charset=utf-8');
$c = cms_content();
$mod = $c['updated_at'] ? date('Y-m-d', strtotime($c['updated_at'])) : date('Y-m-d', (int) filemtime(__FILE__));
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
if (cms_seo('index', true)) {
    foreach (cms_pages() as $p) {
        $m = cms_page_meta($p);
        if (!$m['index']) {
            continue;
        }
        echo "  <url>\n    <loc>" . e($m['url']) . "</loc>\n    <lastmod>" . e(isset($p['updated']) ? date('Y-m-d', strtotime($p['updated'])) : $mod) . "</lastmod>\n";
        if ($m['image']['src']) {
            echo '    <image:image><image:loc>' . e(cms_abs_url($m['image']['src'])) . "</image:loc></image:image>\n";
        }
        echo "  </url>\n";
    }
    // Endereços extras de módulos (posts do blog, categorias...): [loc, lastmod, imagem]
    foreach ((array) cms_apply_filters('sitemap_urls', []) as $u) {
        echo "  <url>\n    <loc>" . e((string) $u['loc']) . "</loc>\n";
        if (!empty($u['lastmod'])) {
            echo '    <lastmod>' . e(date('Y-m-d', strtotime((string) $u['lastmod']))) . "</lastmod>\n";
        }
        if (!empty($u['image'])) {
            echo '    <image:image><image:loc>' . e((string) $u['image']) . "</image:loc></image:image>\n";
        }
        echo "  </url>\n";
    }
}
echo "</urlset>\n";
