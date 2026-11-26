<?php
/**
 * Módulo: Google Ads — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'          => 'Google Ads',
    'description'   => 'Tag do Google Ads com conversões automáticas (envio do formulário e cliques em telefone/WhatsApp) e relatório de campanhas no painel pela API do Google Ads.',
    'version'       => '1.0.0',
    'author'        => 'João Costa',
    'icon'          => 'calendario',
    'requires'      => ['google'],
    'google_scopes' => ['https://www.googleapis.com/auth/adwords'],
    'settings'      => [
        'conversion_id'     => ['type' => 'text', 'label' => 'ID da tag do Google Ads', 'default' => '', 'placeholder' => 'AW-123456789'],
        'lead_label'        => ['type' => 'text', 'label' => 'Rótulo da conversão "lead do formulário"', 'default' => '', 'placeholder' => 'AbCdEfGhIjk', 'help' => 'Em Metas → Conversões, abra a ação e veja o send_to: AW-123/ESTE_RÓTULO.'],
        'call_label'        => ['type' => 'text', 'label' => 'Rótulo da conversão "clique para ligar/WhatsApp" (opcional)', 'default' => ''],
        'customer_id'       => ['type' => 'text', 'label' => 'ID da conta (relatórios)', 'default' => '', 'placeholder' => '123-456-7890'],
        'login_customer_id' => ['type' => 'text', 'label' => 'ID da conta de administrador (MCC), se usar', 'default' => '', 'placeholder' => '987-654-3210'],
        'api_version'       => ['type' => 'text', 'label' => 'Versão da API do Google Ads', 'default' => 'v26', 'help' => 'Atualize quando o Google desativar a versão (a cada ~1 ano).'],
    ],
    'boot'          => function (array $mod) {
        require_once __DIR__ . '/ads.php';
        s7x_gads_boot();
    },
];
