<?php
/**
 * Módulo Google Analytics 4 — lógica
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_ga_boot(): void
{
    cms_add_filter('gtag_ids', function (array $ids) {
        $mid = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) cms_module_get('google-analytics', 'measurement_id', '')));
        if (preg_match('/^G-[A-Z0-9]+$/', $mid)) {
            array_unshift($ids, $mid);
        }
        return $ids;
    });
    cms_register_admin_page('analytics', [
        'label'     => 'Analytics',
        'order'     => 20,
        'icon'      => 'pulso',
        'group'     => 'Google',
        'perm_help' => 'Ver visitantes, sessões, páginas e origens do tráfego (GA4).',
        'render'    => 's7x_ga_render',
        'handle'    => 's7x_ga_handle',
    ]);
    cms_add_action('ai_tools', function () {
        cms_register_ai_tool('analytics_report', [
            'description'  => 'Relatório do Google Analytics 4: usuários, sessões, visualizações, eventos principais, páginas e canais de origem.',
            'input_schema' => ['type' => 'object', 'properties' => ['days' => ['type' => 'integer', 'enum' => [7, 28, 90]]]],
            'perm'         => fn () => cms_can('page.analytics') && s7x_google_connected(),
            'run'          => function (array $a) {
                try {
                    return s7x_ga_report(in_array((int) ($a['days'] ?? 28), [7, 28, 90], true) ? (int) $a['days'] : 28);
                } catch (RuntimeException $e) {
                    throw new CmsAiError($e->getMessage());
                }
            },
        ]);
    });
}

function s7x_ga_property(): string
{
    return preg_replace('/\D/', '', (string) cms_module_get('google-analytics', 'property_id', ''));
}

function s7x_ga_run(array $body): array
{
    return s7x_google_api('POST', 'https://analyticsdata.googleapis.com/v1beta/properties/' . s7x_ga_property() . ':runReport', $body);
}

function s7x_ga_rows(array $res, array $dimNames, array $metNames): array
{
    $out = [];
    foreach ((array) ($res['rows'] ?? []) as $row) {
        $r = [];
        foreach ($dimNames as $i => $d) {
            $r[$d] = $row['dimensionValues'][$i]['value'] ?? '';
        }
        foreach ($metNames as $i => $m) {
            $r[$m] = (float) ($row['metricValues'][$i]['value'] ?? 0);
        }
        $out[] = $r;
    }
    return $out;
}

function s7x_ga_report(int $days, bool $fresh = false): array
{
    if (s7x_ga_property() === '') {
        throw new RuntimeException('Escolha a propriedade do Analytics primeiro.');
    }
    return s7x_google_cached('ga:' . s7x_ga_property() . ':' . $days, function () use ($days) {
        $range = [['startDate' => $days . 'daysAgo', 'endDate' => 'yesterday']];
        $mets  = ['activeUsers', 'sessions', 'screenPageViews', 'keyEvents'];
        $tot   = s7x_ga_rows(s7x_ga_run(['dateRanges' => $range, 'metrics' => array_map(fn ($m) => ['name' => $m], $mets)]), [], $mets)[0] ?? array_fill_keys($mets, 0);
        $daily = s7x_ga_rows(s7x_ga_run(['dateRanges' => $range, 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'sessions']], 'orderBys' => [['dimension' => ['dimensionName' => 'date']]], 'limit' => 400]), ['date'], ['sessions']);
        $pages = s7x_ga_rows(s7x_ga_run(['dateRanges' => $range, 'dimensions' => [['name' => 'pagePath']], 'metrics' => [['name' => 'screenPageViews']], 'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 10]), ['pagePath'], ['screenPageViews']);
        $chan  = s7x_ga_rows(s7x_ga_run(['dateRanges' => $range, 'dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'metrics' => [['name' => 'sessions']], 'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 10]), ['channel'], ['sessions']);
        return [
            'period' => $days . ' dias',
            'users' => $tot['activeUsers'], 'sessions' => $tot['sessions'], 'views' => $tot['screenPageViews'], 'key_events' => $tot['keyEvents'],
            'daily'  => array_map(fn ($r) => ['date' => substr($r['date'], 0, 4) . '-' . substr($r['date'], 4, 2) . '-' . substr($r['date'], 6, 2), 'value' => $r['sessions']], $daily),
            'pages'  => $pages,
            'channels' => $chan,
        ];
    }, $fresh);
}

function s7x_ga_handle(string $action): void
{
    if ($action === 'property' && cms_is_admin()) {
        $content = cms_content();
        $content['modules']['google-analytics']['property_id'] = preg_replace('/\D/', '', (string) ($_POST['property'] ?? ''));
        cms_save_content($content, 'Analytics');
        cms_flash('ok', 'Propriedade escolhida.');
    }
}

function s7x_ga_render(): void
{
    if (trim((string) cms_module_get('google-analytics', 'measurement_id', '')) === '') {
        echo '<div class="notice notice--warn">A tag do GA4 ainda não está no site. ' . (cms_is_admin() ? 'Informe o ID de medição (G-...) em <a href="' . e(admin_url('module', ['m' => 'google-analytics'])) . '">Módulos → Google Analytics 4</a>.' : '') . '</div>';
    }
    if (!s7x_google_ready_notice()) {
        return;
    }
    if (s7x_ga_property() === '' || isset($_GET['trocar'])) {
        if (!cms_is_admin()) {
            echo '<div class="notice notice--warn">O administrador ainda não escolheu a propriedade do Analytics.</div>';
            return;
        }
        try {
            $acc = s7x_google_api('GET', 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=200')['accountSummaries'] ?? [];
        } catch (RuntimeException $e) {
            s7x_report_error($e);
            return;
        }
        echo '<section class="panel"><h2>Escolha a propriedade do GA4</h2>';
        if (!$acc) {
            echo '<p class="muted">A conta conectada não tem propriedades do Google Analytics.</p></section>';
            return;
        }
        echo '<form method="post" class="row">' . cms_csrf_field() . '<input type="hidden" name="action" value="property"><select name="property" aria-label="Propriedade" style="flex:1">';
        foreach ($acc as $a) {
            foreach ((array) ($a['propertySummaries'] ?? []) as $p) {
                $id = preg_replace('/\D/', '', (string) $p['property']);
                echo '<option value="' . e($id) . '"' . ($id === s7x_ga_property() ? ' selected' : '') . '>' . e(($a['displayName'] ?? '') . ' › ' . ($p['displayName'] ?? '') . ' (' . $id . ')') . '</option>';
            }
        }
        echo '</select><button class="btn btn--primary">Usar esta propriedade</button></form></section>';
        return;
    }
    $days = s7x_report_days();
    try {
        $r = s7x_ga_report($days, !empty($_GET['fresh']));
    } catch (RuntimeException $e) {
        s7x_report_error($e);
        return;
    }
    ?>
    <section class="panel">
      <div class="rhead"><h2>Propriedade <?= e(s7x_ga_property()) ?></h2><?= s7x_report_period_nav('analytics') ?></div>
      <?= s7x_report_stats([
          ['Usuários', s7x_num($r['users']), 'pessoas diferentes'],
          ['Sessões', s7x_num($r['sessions']), 'visitas'],
          ['Visualizações', s7x_num($r['views']), 'páginas vistas'],
          ['Eventos principais', s7x_num($r['key_events']), 'ex.: leads do formulário'],
      ]) ?>
      <?= s7x_report_bars($r['daily'], 'Sessões por dia', fn ($v) => s7x_num($v)) ?>
      <div class="rgrid">
        <?= s7x_report_table($r['pages'], ['Página' => 'pagePath', 'Visualizações' => fn ($x) => s7x_num($x['screenPageViews'])], 'Páginas mais vistas') ?>
        <?= s7x_report_table($r['channels'], ['Origem' => 'channel', 'Sessões' => fn ($x) => s7x_num($x['sessions'])], 'De onde vêm as visitas') ?>
      </div>
      <p class="muted small">Para que os leads apareçam em “Eventos principais”, marque o evento <code>generate_lead</code> como evento principal no GA4.</p>
      <?php if (cms_is_admin()): ?><a class="btn btn--ghost" href="<?= e(admin_url('analytics', ['trocar' => 1])) ?>">Trocar propriedade</a><?php endif; ?>
    </section>
    <?php
}
