<?php
/**
 * Módulo: Google Search Console — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'          => 'Google Search Console',
    'description'   => 'Verificação do site, envio do sitemap e relatório de busca no painel: cliques, impressões, posição média, principais buscas e páginas.',
    'version'       => '1.0.0',
    'author'        => 'João Costa',
    'icon'          => 'check',
    'requires'      => ['google'],
    'google_scopes' => ['https://www.googleapis.com/auth/webmasters'],
    'settings'      => [
        'verification' => ['type' => 'text', 'label' => 'Código de verificação (meta tag)', 'default' => '', 'placeholder' => 'Só o valor de content="..."', 'help' => 'Opcional se o domínio já foi verificado por DNS.'],
        'property'     => ['type' => 'text', 'label' => 'Propriedade', 'default' => '', 'placeholder' => 'sc-domain:seudominio.com.br ou https://www.seudominio.com.br/', 'help' => 'Pode ser escolhida na tela Search Console depois de conectar a conta Google.'],
    ],
    'boot'          => function (array $mod) {
        require_once __DIR__ . '/gsc.php';
        s7x_gsc_boot();
    },
];
