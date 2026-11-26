<?php
/**
 * Módulo: Blog — Sev7n Xpress
 * Posts, categorias, tags, busca, RSS, sitemap e importação do WordPress.
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Blog',
    'description' => 'Blog completo: posts com editor, categorias, tags, busca, RSS, sitemap, dados estruturados de artigo, posts mais lidos e importação de um site WordPress com imagens.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'folha',
    'settings'    => [
        'title'          => ['type' => 'text', 'label' => 'Nome do blog', 'default' => 'Blog', 'help' => 'Aparece no topo da página do blog e no RSS.'],
        'description'    => ['type' => 'textarea', 'label' => 'Descrição do blog', 'rows' => 2, 'default' => '', 'help' => 'Texto abaixo do título e meta descrição da página do blog.'],
        'base'           => ['type' => 'text', 'label' => 'Endereço da página do blog', 'default' => 'blog', 'placeholder' => 'blog', 'help' => 'Ex.: blog ou noticias → seusite.com.br/noticias/'],
        'post_prefix'    => ['type' => 'select', 'label' => 'Endereço de cada post', 'default' => 'root', 'options' => [
            'root' => 'Direto: seusite.com.br/titulo-do-post/ (igual ao WordPress)',
            'base' => 'Dentro do blog: seusite.com.br/blog/titulo-do-post/',
        ]],
        'category_base'  => ['type' => 'text', 'label' => 'Prefixo das categorias', 'default' => 'category', 'help' => 'O WordPress usa "category". Mantenha igual para não perder as posições no Google.'],
        'tag_base'       => ['type' => 'text', 'label' => 'Prefixo das tags', 'default' => 'tag'],
        'per_page'       => ['type' => 'number', 'label' => 'Posts por página', 'default' => 12],
        'author_name'    => ['type' => 'text', 'label' => 'Autor exibido nos posts', 'default' => '', 'help' => 'Vazio = nome do site.'],
        'reading_time'   => ['type' => 'toggle', 'label' => 'Mostrar tempo de leitura', 'default' => true],
        'share'          => ['type' => 'toggle', 'label' => 'Botões de compartilhar (WhatsApp, LinkedIn, Facebook, X)', 'default' => true],
        'related'        => ['type' => 'number', 'label' => 'Posts relacionados no fim do artigo', 'default' => 3],
        'sidebar'        => ['type' => 'toggle', 'label' => 'Barra lateral nos posts (busca, categorias, mais lidos)', 'default' => true],
        'views'          => ['type' => 'toggle', 'label' => 'Contar visualizações (para "Mais lidos")', 'default' => true],
    ],
    'boot'        => function (array $mod) {
        require_once __DIR__ . '/blog.php';
        require_once __DIR__ . '/front.php';
        require_once __DIR__ . '/admin.php';
        require_once __DIR__ . '/import.php';
        s7x_blog_boot();
    },
];
