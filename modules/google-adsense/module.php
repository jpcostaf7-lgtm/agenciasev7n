<?php
/**
 * Módulo: Google AdSense — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'          => 'Google AdSense',
    'description'   => 'Código do AdSense (anúncios automáticos), arquivo ads.txt gerado sozinho, bloco "Anúncio AdSense" para posicionar anúncios e relatório de ganhos no painel.',
    'version'       => '1.0.0',
    'author'        => 'João Costa',
    'icon'          => 'gota',
    'requires'      => ['google'],
    'google_scopes' => ['https://www.googleapis.com/auth/adsense.readonly'],
    'settings'      => [
        'publisher_id' => ['type' => 'text', 'label' => 'ID de editor', 'default' => '', 'placeholder' => 'ca-pub-1234567890123456'],
        'auto_ads'     => ['type' => 'toggle', 'label' => 'Carregar o código em todas as páginas (anúncios automáticos)', 'default' => true, 'help' => 'Desligado: o código só carrega em páginas com o bloco Anúncio AdSense.'],
        'ads_txt_extra' => ['type' => 'textarea', 'label' => 'Linhas extras do ads.txt', 'rows' => 6, 'default' => '', 'help' => 'Uma linha por parceiro (ex.: as linhas que a MGID, Ezoic ou outra rede pedem). A linha do Google é colocada sozinha.'],
        'account'      => ['type' => 'text', 'label' => 'Conta para relatórios', 'default' => '', 'placeholder' => 'accounts/pub-1234567890123456', 'help' => 'Preenchida sozinha na tela AdSense depois de conectar a conta Google.'],
    ],
    'boot'          => function (array $mod) {
        require_once __DIR__ . '/adsense.php';
        s7x_adsense_boot();
    },
];
