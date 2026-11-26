<?php
/**
 * Módulo: Artigos com IA — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Artigos com IA',
    'description' => 'Gera artigos para o blog com o Claude (um por vez, em lote ou todos os dias nos horários definidos), com fotos do Pixabay, SEO completo, links internos, FAQ, rede de blogs e importação de artigos de outro site da rede.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'neuronio',
    'requires'    => ['blog'],
    'boot'        => function (array $mod) {
        if (!function_exists('s7x_blog_save')) {
            return; // o módulo Blog precisa estar ativo
        }
        require_once __DIR__ . '/ia.php';
        require_once __DIR__ . '/admin-ia.php';
        cms_add_action('cron', 's7x_ia_cron', 20);
        cms_add_action('blog_after_content', function (array $post) {
            if (s7x_ia('net_box')) {
                echo s7x_ia_net_box();
            }
        });
        s7x_ia_admin_boot();
    },
];
