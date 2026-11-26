<?php
/**
 * Módulo Google Search Console — lógica
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_gsc_boot(): void
{
    cms_add_action('head', function () {
        $v = trim((string) cms_module_get('google-search-console', 'verification', ''));
        if (preg_match('/content="([^"]+)"/', $v, $m)) {
            $v = $m[1];
        }
        if ($v !== '') {
            echo '<meta name="google-site-verification" content="' . e($v) . '">' . "\n";
        }
    });
    cms_register_admin_page('search-console', [
        'label'     => 'Search Console',
        'order'     => 10,
        'icon'      => 'check',
        'group'     => 'Google',
        'perm_help' => 'Ver o relatório de busca do Google (cliques, buscas e páginas).',
        'render'    => 's7x_gsc_render',
        'handle'    => 's7x_gsc_handle',
    ]);
    cms_add_action('ai_tools', function () {
        cms_register_ai_tool('search_console_report', [
            'description'  => 'Relatório do Google Search Console: cliques, impressões, CTR, posição média, principais buscas e páginas.',
            'input_schema' => ['type' => 'object', 'properties' => ['days' => ['type' => 'integer', 'enum' => [7, 28, 90]]]],
            'perm'         => fn () => cms_can('page.search-console') && s7x_google_connected(),
            'run'          => function (array $a) {
                try {
                    return s7x_gsc_report(in_array((int) ($a['days'] ?? 28), [7, 28, 90], true) ? (int) $a['days'] : 28);
                } catch (RuntimeException $e) {
                    throw new CmsAiError($e->getMessage());
                }
            },
        ]);
    });
}

function s7x_gsc_property(): string
{
    return trim((string) cms_module_get('google-search-console', 'property', ''));
}

function s7x_gsc_url(string $suffix): string
{
    return 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode(s7x_gsc_property()) . $suffix;
}

function s7x_gsc_query(int $days, array $dims, int $limit = 10): array
{
    $end   = date('Y-m-d', strtotime('-1 day'));
    $start = date('Y-m-d', strtotime('-' . $days . ' days'));
    $res = s7x_google_api('POST', s7x_gsc_url('/searchAnalytics/query'), [
        'startDate' => $start, 'endDate' => $end, 'dimensions' => $dims, 'rowLimit' => $limit, 'dataState' => 'all',
    ]);
    return (array) ($res['rows'] ?? []);
}

function s7x_gsc_report(int $days, bool $fresh = false): array
{
    if (s7x_gsc_property() === '') {
        throw new RuntimeException('Escolha a propriedade do Search Console primeiro.');
    }
    return s7x_google_cached('gsc:' . s7x_gsc_property() . ':' . $days, function () use ($days) {
        $daily = s7x_gsc_query($days, ['date'], 1000);
        $tot = ['clicks' => 0, 'impressions' => 0, 'pos_sum' => 0];
        foreach ($daily as $r) {
            $tot['clicks'] += $r['clicks'];
            $tot['impressions'] += $r['impressions'];
            $tot['pos_sum'] += $r['position'] * $r['impressions'];
        }
        return [
            'period'      => $days . ' dias',
            'clicks'      => $tot['clicks'],
            'impressions' => $tot['impressions'],
            'ctr'         => $tot['impressions'] ? $tot['clicks'] / $tot['impressions'] : 0,
            'position'    => $tot['impressions'] ? $tot['pos_sum'] / $tot['impressions'] : 0,
            'daily'       => array_map(fn ($r) => ['date' => $r['keys'][0], 'value' => $r['clicks'], 'impressions' => $r['impressions']], $daily),
            'queries'     => array_map(fn ($r) => ['query' => $r['keys'][0], 'clicks' => $r['clicks'], 'impressions' => $r['impressions'], 'position' => round($r['position'], 1)], s7x_gsc_query($days, ['query'], 15)),
            'pages'       => array_map(fn ($r) => ['page' => $r['keys'][0], 'clicks' => $r['clicks'], 'impressions' => $r['impressions']], s7x_gsc_query($days, ['page'], 10)),
        ];
    }, $fresh);
}

function s7x_gsc_handle(string $action): void
{
    if (!cms_is_admin()) {
        return;
    }
    if ($action === 'property') {
        $content = cms_content();
        $content['modules']['google-search-console']['property'] = trim((string) ($_POST['property'] ?? ''));
        cms_save_content($content, 'Search Console');
        cms_flash('ok', 'Propriedade escolhida.');
    }
    if ($action === 'sitemap') {
        try {
            $feed = cms_site_url() . '/sitemap.xml';
            s7x_google_api('PUT', s7x_gsc_url('/sitemaps/' . rawurlencode($feed)));
            cms_flash('ok', 'Sitemap enviado ao Google: ' . $feed);
        } catch (RuntimeException $e) {
            cms_flash('error', $e->getMessage());
        }
    }
}

function s7x_gsc_render(): void
{
    if (!s7x_google_ready_notice()) {
        return;
    }
    if (s7x_gsc_property() === '' || isset($_GET['trocar'])) {
        if (!cms_is_admin()) {
            echo '<div class="notice notice--warn">O administrador ainda não escolheu a propriedade do Search Console.</div>';
            return;
        }
        try {
            $sites = s7x_google_api('GET', 'https://www.googleapis.com/webmasters/v3/sites')['siteEntry'] ?? [];
        } catch (RuntimeException $e) {
            s7x_report_error($e);
            return;
        }
        echo '<section class="panel"><h2>Escolha a propriedade</h2>';
        if (!$sites) {
            echo '<p class="muted">A conta conectada não tem propriedades no Search Console. Adicione o site em <a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a>.</p></section>';
            return;
        }
        echo '<form method="post" class="row">' . cms_csrf_field() . '<input type="hidden" name="action" value="property"><select name="property" aria-label="Propriedade" style="flex:1">';
        foreach ($sites as $s) {
            echo '<option value="' . e($s['siteUrl']) . '"' . ($s['siteUrl'] === s7x_gsc_property() ? ' selected' : '') . '>' . e($s['siteUrl']) . ' (' . e($s['permissionLevel']) . ')</option>';
        }
        echo '</select><button class="btn btn--primary">Usar esta propriedade</button></form></section>';
        return;
    }
    $days = s7x_report_days();
    try {
        $r = s7x_gsc_report($days, !empty($_GET['fresh']));
    } catch (RuntimeException $e) {
        s7x_report_error($e);
        return;
    }
    $int = fn ($v) => s7x_num($v);
    ?>
    <section class="panel">
      <div class="rhead"><h2><?= e(s7x_gsc_property()) ?></h2><?= s7x_report_period_nav('search-console') ?></div>
      <?= s7x_report_stats([
          ['Cliques', s7x_num($r['clicks']), 'vindos da busca do Google'],
          ['Impressões', s7x_num($r['impressions']), 'vezes que o site apareceu'],
          ['CTR', s7x_num($r['ctr'] * 100, 1) . '%', 'cliques ÷ impressões'],
          ['Posição média', s7x_num($r['position'], 1), 'quanto menor, melhor'],
      ]) ?>
      <?= s7x_report_bars($r['daily'], 'Cliques por dia', $int) ?>
      <div class="rgrid">
        <?= s7x_report_table($r['queries'], ['Busca' => 'query', 'Cliques' => fn ($x) => s7x_num($x['clicks']), 'Impressões' => fn ($x) => s7x_num($x['impressions']), 'Posição' => fn ($x) => s7x_num($x['position'], 1)], 'Principais buscas') ?>
        <?= s7x_report_table($r['pages'], ['Página' => fn ($x) => preg_replace('#^https?://[^/]+#', '', $x['page']) ?: '/', 'Cliques' => fn ($x) => s7x_num($x['clicks']), 'Impressões' => fn ($x) => s7x_num($x['impressions'])], 'Páginas mais clicadas') ?>
      </div>
      <p class="muted small">Os dados do Search Console chegam com cerca de 2 dias de atraso.</p>
      <?php if (cms_is_admin()): ?>
      <div class="row">
        <form method="post"><?= cms_csrf_field() ?><input type="hidden" name="action" value="sitemap"><button class="btn">Enviar sitemap ao Google</button></form>
        <a class="btn btn--ghost" href="<?= e(admin_url('search-console', ['trocar' => 1])) ?>">Trocar propriedade</a>
      </div>
      <?php endif; ?>
    </section>
    <?php
}
