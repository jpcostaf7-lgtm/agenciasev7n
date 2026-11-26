<?php
/**
 * Módulo: Anúncios MGID — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Anúncios MGID',
    'description' => 'Anúncios da MGID nos posts do blog (topo, depois de um parágrafo, meio e final), bloco "Anúncio MGID" para as páginas e linhas da MGID no ads.txt.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'gota',
    'settings'    => [
        'site_id'       => ['type' => 'text', 'label' => 'ID do site na MGID', 'default' => '', 'placeholder' => '1117844', 'help' => 'O número do script do site: jsc.mgid.com/site/<strong>NÚMERO</strong>.js'],
        'widget_top'    => ['type' => 'text', 'label' => 'Widget no topo do post (ID)', 'default' => '', 'placeholder' => '2103511', 'help' => 'Vazio = sem anúncio nesta posição.'],
        'widget_para'   => ['type' => 'text', 'label' => 'Widget depois do parágrafo N (ID)', 'default' => ''],
        'para_n'        => ['type' => 'number', 'label' => 'Depois de qual parágrafo', 'default' => 3],
        'widget_middle' => ['type' => 'text', 'label' => 'Widget no meio do post (ID)', 'default' => ''],
        'widget_end'    => ['type' => 'text', 'label' => 'Widget no fim do post (ID)', 'default' => ''],
        'min_paras'     => ['type' => 'number', 'label' => 'Mínimo de parágrafos para o anúncio do meio', 'default' => 6],
        'label'         => ['type' => 'text', 'label' => 'Rótulo acima dos anúncios', 'default' => 'Publicidade', 'help' => 'Vazio = sem rótulo.'],
        'skip_logged'   => ['type' => 'toggle', 'label' => 'Não mostrar anúncios para quem está logado no painel', 'default' => true],
        'exclude_cats'  => ['type' => 'text', 'label' => 'Categorias sem anúncio (slugs separados por vírgula)', 'default' => ''],
        'ads_txt'       => ['type' => 'textarea', 'label' => 'Linhas da MGID no ads.txt', 'rows' => 6, 'default' => '', 'help' => 'Cole as linhas que a MGID indicar no painel dela.'],
    ],
    'boot'        => function (array $mod) {
        require_once __DIR__ . '/mgid.php';
        s7x_mgid_boot();
    },
];
