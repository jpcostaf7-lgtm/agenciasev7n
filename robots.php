<?php
/** robots.txt automático — Sev7n Xpress | Autor: João Costa */
declare(strict_types=1);
define('CMS_ENTRY_DEPTH', 0);
require __DIR__ . '/cms/core.php';
header('Content-Type: text/plain; charset=utf-8');
if (!cms_seo('index', true)) {
    echo "User-agent: *\nDisallow: /\n";
    exit;
}
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /data/\n";
echo "Disallow: /cms/\n";
echo "Allow: /\n\n";
echo 'Sitemap: ' . cms_site_url() . "/sitemap.xml\n";
