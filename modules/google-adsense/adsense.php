<?php
/**
 * Módulo Google AdSense — lógica
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_adsense_pub(): string
{
    $id = strtolower(trim((string) cms_module_get('google-adsense', 'publisher_id', '')));
    $id = preg_replace('/^pub-/', 'ca-pub-', $id);
    return preg_match('/^ca-pub-\d{10,20}$/', $id) ? $id : '';
}

/** Linhas extras do ads.txt (configuração + outros módulos de anúncio), sem repetir a do Google. */
function s7x_ads_txt_extra(): string
{
    $lines = preg_split('/\R/', (string) cms_module_get('google-adsense', 'ads_txt_extra', '')) ?: [];
    $lines = array_merge($lines, (array) cms_apply_filters('ads_txt', []));
    $pub = substr(s7x_adsense_pub(), 3);
    $out = [];
    foreach ($lines as $l) {
        $l = trim(strip_tags((string) $l));
        $key = strtolower(preg_replace('/\s+/', '', $l));
        if ($l === '' || isset($out[$key]) || ($pub !== '' && stripos($key, 'google.com,' . $pub . ',direct') === 0)) {
            continue;
        }
        $out[$key] = $l;
    }
    return $out ? implode("\n", $out) . "\n" : '';
}

function s7x_adsense_boot(): void
{
    // Código do AdSense no <head> (passa pelo aviso de cookies, se o módulo estiver ativo)
    cms_add_filter('tracking_html', function (string $html, string $place) {
        $pub = s7x_adsense_pub();
        if ($place !== 'head' || $pub === '') {
            return $html;
        }
        $page = $GLOBALS['cms_current_page'] ?? null;
        if (!cms_module_get('google-adsense', 'auto_ads', true) && !cms_page_has_block($page, 'adsense')) {
            return $html;
        }
        return $html . '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . e($pub) . '" crossorigin="anonymous"></script>' . "\n";
    }, 6);

    // ads.txt automático
    cms_add_action('request', function () {
        if (cms_request_path() !== '/ads.txt') {
            return;
        }
        $pub = s7x_adsense_pub();
        header('Content-Type: text/plain; charset=utf-8');
        if ($pub === '') {
            http_response_code(404);
            echo "# Informe o ID de editor do AdSense no painel.\n";
            exit;
        }
        echo 'google.com, ' . substr($pub, 3) . ", DIRECT, f08c47fec0942fa0\n";
        echo s7x_ads_txt_extra();
        exit;
    }, 2);

    cms_register_block('adsense', [
        'label'       => 'Anúncio AdSense',
        'icon'        => 'gota',
        'group'       => 'Google AdSense',
        'description' => 'Espaço de anúncio do AdSense. Crie o bloco de anúncio no AdSense e cole o ID do slot.',
        'template'    => __DIR__ . '/block.php',
        'fields'      => [
            'slot'   => ['type' => 'text', 'label' => 'ID do bloco de anúncio (data-ad-slot)', 'placeholder' => '1234567890'],
            'format' => ['type' => 'select', 'label' => 'Formato', 'options' => ['auto' => 'Automático (responsivo)', 'horizontal' => 'Horizontal', 'rectangle' => 'Retângulo', 'fluid' => 'No conteúdo (fluid)'], 'default' => 'auto'],
            'label'  => ['type' => 'text', 'label' => 'Rótulo acima do anúncio', 'default' => 'Publicidade'],
        ],
    ]);

    cms_register_admin_page('adsense', [
        'label'     => 'AdSense',
        'order'     => 30,
        'icon'      => 'gota',
        'group'     => 'Google',
        'perm_help' => 'Ver os ganhos e métricas do AdSense.',
        'render'    => 's7x_adsense_render',
        'handle'    => 's7x_adsense_handle',
    ]);
    cms_add_action('ai_tools', function () {
        cms_register_ai_tool('adsense_report', [
            'description'  => 'Relatório do AdSense: ganhos estimados, visualizações de página, cliques e RPM por dia.',
            'input_schema' => ['type' => 'object', 'properties' => ['days' => ['type' => 'integer', 'enum' => [7, 28, 90]]]],
            'perm'         => fn () => cms_can('page.adsense') && s7x_google_connected(),
            'run'          => function (array $a) {
                try {
                    return s7x_adsense_report(in_array((int) ($a['days'] ?? 28), [7, 28, 90], true) ? (int) $a['days'] : 28);
                } catch (RuntimeException $e) {
                    throw new CmsAiError($e->getMessage());
                }
            },
        ]);
    });
}

function s7x_adsense_account(): string
{
    return trim((string) cms_module_get('google-adsense', 'account', ''));
}

function s7x_adsense_report(int $days, bool $fresh = false): array
{
    if (s7x_adsense_account() === '') {
        throw new RuntimeException('Escolha a conta do AdSense primeiro.');
    }
    return s7x_google_cached('ads:' . s7x_adsense_account() . ':' . $days, function () use ($days) {
        $s = strtotime('-' . $days . ' days');
        $e = strtotime('-1 day');
        $q = 'dateRange=CUSTOM'
            . '&startDate.year=' . date('Y', $s) . '&startDate.month=' . (int) date('m', $s) . '&startDate.day=' . (int) date('d', $s)
            . '&endDate.year=' . date('Y', $e) . '&endDate.month=' . (int) date('m', $e) . '&endDate.day=' . (int) date('d', $e)
            . '&dimensions=DATE&metrics=ESTIMATED_EARNINGS&metrics=PAGE_VIEWS&metrics=CLICKS&metrics=PAGE_VIEWS_RPM&currencyCode=BRL&languageCode=pt-BR';
        $res = s7x_google_api('GET', 'https://adsense.googleapis.com/v2/' . s7x_adsense_account() . '/reports:generate?' . $q);
        $daily = [];
        foreach ((array) ($res['rows'] ?? []) as $row) {
            $c = $row['cells'];
            $daily[] = ['date' => $c[0]['value'], 'value' => (float) $c[1]['value'], 'views' => (float) $c[2]['value'], 'clicks' => (float) $c[3]['value']];
        }
        $tot = (array) ($res['totals']['cells'] ?? []);
        return [
            'period'   => $days . ' dias',
            'earnings' => (float) ($tot[1]['value'] ?? array_sum(array_column($daily, 'value'))),
            'views'    => (float) ($tot[2]['value'] ?? array_sum(array_column($daily, 'views'))),
            'clicks'   => (float) ($tot[3]['value'] ?? array_sum(array_column($daily, 'clicks'))),
            'rpm'      => (float) ($tot[4]['value'] ?? 0),
            'currency' => 'BRL',
            'daily'    => $daily,
        ];
    }, $fresh);
}

function s7x_adsense_handle(string $action): void
{
    if ($action === 'account' && cms_is_admin()) {
        $content = cms_content();
        $content['modules']['google-adsense']['account'] = preg_replace('/[^a-z0-9\/\-]/', '', (string) ($_POST['account'] ?? ''));
        cms_save_content($content, 'AdSense');
        cms_flash('ok', 'Conta escolhida.');
    }
}

function s7x_adsense_render(): void
{
    $pub = s7x_adsense_pub();
    if ($pub === '') {
        echo '<div class="notice notice--warn">O código do AdSense ainda não está no site. ' . (cms_is_admin() ? 'Informe o ID de editor em <a href="' . e(admin_url('module', ['m' => 'google-adsense'])) . '">Módulos → Google AdSense</a>.' : '') . '</div>';
    } else {
        echo '<div class="notice notice--ok">Código ativo para ' . e($pub) . '. Arquivo ads.txt: <a href="' . e(cms_url('ads.txt')) . '" target="_blank" rel="noopener">' . e(cms_site_url()) . '/ads.txt</a></div>';
    }
    if (!s7x_google_ready_notice()) {
        return;
    }
    if (s7x_adsense_account() === '' || isset($_GET['trocar'])) {
        if (!cms_is_admin()) {
            echo '<div class="notice notice--warn">O administrador ainda não escolheu a conta do AdSense.</div>';
            return;
        }
        try {
            $acc = s7x_google_api('GET', 'https://adsense.googleapis.com/v2/accounts')['accounts'] ?? [];
        } catch (RuntimeException $e) {
            s7x_report_error($e);
            return;
        }
        echo '<section class="panel"><h2>Escolha a conta do AdSense</h2>';
        if (!$acc) {
            echo '<p class="muted">A conta Google conectada não tem AdSense.</p></section>';
            return;
        }
        echo '<form method="post" class="row">' . cms_csrf_field() . '<input type="hidden" name="action" value="account"><select name="account" aria-label="Conta" style="flex:1">';
        foreach ($acc as $a) {
            echo '<option value="' . e($a['name']) . '">' . e(($a['displayName'] ?? '') . ' (' . $a['name'] . ')') . '</option>';
        }
        echo '</select><button class="btn btn--primary">Usar esta conta</button></form></section>';
        return;
    }
    $days = s7x_report_days();
    try {
        $r = s7x_adsense_report($days, !empty($_GET['fresh']));
    } catch (RuntimeException $e) {
        s7x_report_error($e);
        return;
    }
    $brl = fn ($v) => 'R$ ' . s7x_num($v, 2);
    ?>
    <section class="panel">
      <div class="rhead"><h2><?= e(s7x_adsense_account()) ?></h2><?= s7x_report_period_nav('adsense') ?></div>
      <?= s7x_report_stats([
          ['Ganhos estimados', $brl($r['earnings']), 'valor ainda não finalizado'],
          ['Visualizações de página', s7x_num($r['views'])],
          ['Cliques', s7x_num($r['clicks'])],
          ['RPM', $brl($r['rpm']), 'ganho a cada mil visualizações'],
      ]) ?>
      <?= s7x_report_bars($r['daily'], 'Ganhos estimados por dia', $brl) ?>
      <?php if (cms_is_admin()): ?><a class="btn btn--ghost" href="<?= e(admin_url('adsense', ['trocar' => 1])) ?>">Trocar conta</a><?php endif; ?>
    </section>
    <?php
}
