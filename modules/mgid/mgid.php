<?php
/**
 * Módulo MGID — lógica
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_mgid_get(string $k, $d = '')
{
    return cms_module_get('mgid', $k, $d);
}

function s7x_mgid_widget(string $id, string $pos): string
{
    $id = preg_replace('/\D/', '', $id);
    if ($id === '') {
        return '';
    }
    $label = trim((string) s7x_mgid_get('label', 'Publicidade'));
    return '<div class="s7x-ad s7x-ad--mgid s7x-ad--' . e($pos) . '">' . ($label !== '' ? '<span class="s7x-ad__label">' . e($label) . '</span>' : '')
        . '<div data-type="_mgwidget" data-widget-id="' . e($id) . '"></div><script>(function(w,q){w[q]=w[q]||[];w[q].push(["_mgc.load"])})(window,"_mgq");</script></div>';
}

/** Anúncios aparecem para este visitante? */
function s7x_mgid_allowed(): bool
{
    if (s7x_mgid_get('skip_logged', true) && !empty($_COOKIE[session_name()])) {
        require_once CMS_ROOT . '/cms/admin_lib.php';
        cms_session_start();
        if (cms_logged_in()) {
            return false;
        }
    }
    return preg_replace('/\D/', '', (string) s7x_mgid_get('site_id')) !== '';
}

function s7x_mgid_boot(): void
{
    cms_add_filter('tracking_html', function (string $html, string $place) {
        $site = preg_replace('/\D/', '', (string) s7x_mgid_get('site_id'));
        $page = $GLOBALS['cms_current_page'] ?? null;
        $wants = (($page['blog']['type'] ?? '') === 'post' && empty($page['blog']['post']['hide_ads'])) || cms_page_has_block($page, 'mgid');
        if ($place !== 'head' || $site === '' || !$wants || !s7x_mgid_allowed()) {
            return $html;
        }
        return $html . '<script src="https://jsc.mgid.com/site/' . $site . '.js" async></script>' . "\n";
    }, 7);

    cms_add_filter('blog_content', function (string $html, array $post) {
        if (!empty($post['hide_ads']) || !s7x_mgid_allowed()) {
            return $html;
        }
        $skip = array_filter(array_map('trim', explode(',', (string) s7x_mgid_get('exclude_cats'))));
        if ($skip && array_intersect($skip, (array) $post['cats'])) {
            return $html;
        }
        $top = s7x_mgid_widget((string) s7x_mgid_get('widget_top'), 'topo');
        $para = s7x_mgid_widget((string) s7x_mgid_get('widget_para'), 'paragrafo');
        $mid = s7x_mgid_widget((string) s7x_mgid_get('widget_middle'), 'meio');
        $end = s7x_mgid_widget((string) s7x_mgid_get('widget_end'), 'final');
        $parts = preg_split('#(</p>)#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $total = (int) floor(count($parts) / 2);
        $n = max(1, (int) s7x_mgid_get('para_n', 3));
        $midAt = $total >= (int) s7x_mgid_get('min_paras', 6) ? (int) ceil($total / 2) + 1 : 0;
        if ($midAt && abs($midAt - $n) < 2) {
            $midAt = $n + 2 <= $total ? $n + 2 : 0;
        }
        $out = $top;
        $count = 0;
        foreach ($parts as $piece) {
            $out .= $piece;
            if (strcasecmp($piece, '</p>') === 0) {
                $count++;
                if ($count === $n && $para !== '' && $total > $n) {
                    $out .= $para;
                }
                if ($midAt && $count === $midAt) {
                    $out .= $mid;
                }
            }
        }
        return $out . $end;
    }, 20);

    // Linhas da MGID no ads.txt (o módulo AdSense junta tudo; sem ele, este módulo responde)
    $lines = fn () => array_filter(array_map('trim', preg_split('/\R/', (string) s7x_mgid_get('ads_txt')) ?: []));
    cms_add_filter('ads_txt', fn (array $l) => array_merge($l, $lines()));
    cms_add_action('request', function () use ($lines) {
        if (cms_request_path() !== '/ads.txt' || cms_module_active('google-adsense')) {
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", $lines()) . "\n";
        exit;
    }, 3);

    cms_register_block('mgid', [
        'label'       => 'Anúncio MGID',
        'icon'        => 'gota',
        'group'       => 'Anúncios',
        'description' => 'Um widget da MGID em qualquer página.',
        'template'    => __DIR__ . '/block.php',
        'fields'      => ['widget' => ['type' => 'text', 'label' => 'ID do widget', 'placeholder' => '2103511']],
    ]);
    cms_add_action('head', function ($page) {
        if (!((($page['blog']['type'] ?? '') === 'post') || cms_page_has_block($page, 'mgid'))) {
            return;
        }
        echo '<style>.s7x-ad{margin:2em auto;clear:both;text-align:center;max-width:100%;overflow:hidden}.s7x-ad__label{display:block;font-size:11px;letter-spacing:.08em;text-transform:uppercase;opacity:.5;margin-bottom:6px}</style>' . "\n";
    });
}
