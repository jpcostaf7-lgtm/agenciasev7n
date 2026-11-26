<?php
/**
 * Módulo: Páginas extras — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Páginas extras',
    'description' => 'Crie quantas páginas quiser além da inicial (Sobre, Serviços, Política de Privacidade…), cada uma com endereço próprio, blocos, SEO e entrada no sitemap.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'calendario',
    'boot'        => function (array $mod) {
        cms_add_filter('multi_pages', fn () => true);
    },
];
