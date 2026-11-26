<?php
/**
 * Módulo: Google Analytics 4 — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'          => 'Google Analytics 4',
    'description'   => 'Instala a tag do GA4 (respeitando o aviso de cookies), registra cada envio do formulário como evento "generate_lead" e mostra visitantes, sessões, páginas e origens do tráfego no painel.',
    'version'       => '1.0.0',
    'author'        => 'João Costa',
    'icon'          => 'pulso',
    'requires'      => ['google'],
    'google_scopes' => ['https://www.googleapis.com/auth/analytics.readonly'],
    'settings'      => [
        'measurement_id' => ['type' => 'text', 'label' => 'ID de medição (tag)', 'default' => '', 'placeholder' => 'G-XXXXXXXXXX'],
        'property_id'    => ['type' => 'text', 'label' => 'ID da propriedade (relatórios)', 'default' => '', 'placeholder' => '123456789', 'help' => 'Pode ser escolhida na tela Analytics depois de conectar a conta Google.'],
    ],
    'boot'          => function (array $mod) {
        require_once __DIR__ . '/ga.php';
        s7x_ga_boot();
    },
];
