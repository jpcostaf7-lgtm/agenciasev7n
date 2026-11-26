<?php
/**
 * Módulo: Conexão Google — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Conexão Google',
    'description' => 'Conecta o site a uma conta Google (OAuth) para os módulos Search Console, Analytics, AdSense e Google Ads mostrarem relatórios no painel. Também carrega a tag do Google (gtag) uma única vez para todos eles.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'pulso',
    'settings'    => [
        'client_id' => ['type' => 'text', 'label' => 'Client ID do OAuth (Google Cloud)', 'default' => '', 'placeholder' => '1234567890-abc.apps.googleusercontent.com', 'help' => 'O Client Secret é informado na tela Conexão Google.'],
        'cache_minutes' => ['type' => 'number', 'label' => 'Guardar relatórios por (minutos)', 'default' => 60, 'help' => 'Evita consultar o Google a cada clique.'],
    ],
    'boot'        => function (array $mod) {
        require_once __DIR__ . '/google.php';
        s7x_google_boot();
    },
];
