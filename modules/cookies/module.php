<?php
/**
 * Módulo: Cookies LGPD — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Cookies LGPD',
    'description' => 'Aviso de cookies com Aceitar e Recusar. Google Analytics, Tag Manager, Meta Pixel e códigos extras só carregam depois que o visitante aceita.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'escudo',
    'settings'    => [
        'text'          => ['type' => 'textarea', 'label' => 'Texto do aviso', 'rows' => 3, 'default' => 'Usamos cookies para medir as visitas e melhorar o site. Os cookies de medição só são ativados se você aceitar.'],
        'accept_label'  => ['type' => 'text', 'label' => 'Botão de aceitar', 'default' => 'Aceitar'],
        'reject_label'  => ['type' => 'text', 'label' => 'Botão de recusar', 'default' => 'Recusar'],
        'privacy_label' => ['type' => 'text', 'label' => 'Texto do link da política', 'default' => 'Política de Privacidade'],
        'privacy_link'  => ['type' => 'url', 'label' => 'Link da política', 'default' => '/politica-de-privacidade'],
        'prefs_label'   => ['type' => 'text', 'label' => 'Link no rodapé para mudar a escolha', 'default' => 'Preferências de cookies'],
        'position'      => ['type' => 'select', 'label' => 'Formato do aviso', 'options' => ['cartao' => 'Cartão no canto', 'barra' => 'Barra na base da tela'], 'default' => 'cartao'],
        'days'          => ['type' => 'number', 'label' => 'Dias para lembrar a escolha', 'default' => 180],
    ],
    'boot'        => function (array $mod) {
        // Segura os scripts de rastreamento até o aceite
        cms_add_filter('tracking_html', function (string $html, string $place) {
            if (($_COOKIE['s7x_consent'] ?? '') === 'aceito' || trim($html) === '') {
                return $html;
            }
            if ($place === 'body_open') {
                return ''; // <noscript> do GTM só com consentimento
            }
            $html = preg_replace('/<script(?![^>]*\btype=)([^>]*)>/i', '<script type="text/plain" data-s7x-consent$1>', $html);
            $html = preg_replace('/<script([^>]*)\btype=(["\'])text\/javascript\2([^>]*)>/i', '<script$1type="text/plain" data-s7x-consent$3>', (string) $html);
            return (string) $html;
        });

        cms_add_action('head', function () {
            echo '<link rel="stylesheet" href="' . e(cms_module_url('cookies', 'assets/cookies.css')) . '">' . "\n";
            // Cores da identidade visual do site (o tema ainda pode trocar com --cookie-accent)
            if ($br = cms_brand()) {
                echo '<style>:root{--cookie-accent:' . $br['accent'] . ';--cookie-accent-ink:' . $br['ink'] . ';--cookie-focus:' . $br['focus'] . '}</style>' . "\n";
            }
        });

        cms_add_action('footer_links', function () {
            echo '<a href="#" data-s7x-cookie-prefs>' . e(cms_module_get('cookies', 'prefs_label', 'Preferências de cookies')) . '</a>';
        });

        cms_add_action('body_close', function () {
            $link   = trim((string) cms_module_get('cookies', 'privacy_link', ''));
            $linkOk = $link !== '' && ($link[0] !== '/' || cms_page_by_slug(trim(strtok($link, '#?'), '/')));
            $pos    = cms_module_get('cookies', 'position', 'cartao') === 'barra' ? 'barra' : 'cartao';
            $days   = max(1, (int) cms_module_get('cookies', 'days', 180));
            ?>
<div class="s7x-cookie s7x-cookie--<?= $pos ?>" data-s7x-cookie data-days="<?= $days ?>" role="region" aria-label="Aviso de cookies" hidden>
  <p class="s7x-cookie__text"><?= e(cms_module_get('cookies', 'text')) ?><?php if ($linkOk): ?> <a href="<?= e(cms_link($link)) ?>"><?= e(cms_module_get('cookies', 'privacy_label')) ?></a><?php endif; ?></p>
  <div class="s7x-cookie__actions">
    <button type="button" class="s7x-cookie__btn s7x-cookie__btn--ghost" data-s7x-cookie-reject><?= e(cms_module_get('cookies', 'reject_label')) ?></button>
    <button type="button" class="s7x-cookie__btn" data-s7x-cookie-accept><?= e(cms_module_get('cookies', 'accept_label')) ?></button>
  </div>
</div>
<script src="<?= e(cms_module_url('cookies', 'assets/cookies.js')) ?>" defer></script>
            <?php
        });
    },
];
