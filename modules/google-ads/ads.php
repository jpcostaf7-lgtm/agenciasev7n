<?php
/**
 * Módulo Google Ads — lógica
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_gads_aw(): string
{
    $id = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) cms_module_get('google-ads', 'conversion_id', '')));
    return preg_match('/^AW-\d+$/', $id) ? $id : '';
}

function s7x_gads_boot(): void
{
    cms_add_filter('gtag_ids', function (array $ids) {
        if ($aw = s7x_gads_aw()) {
            $ids[] = $aw;
        }
        return $ids;
    });
    // Conversões: o formulário dispara "lead"; links tel:/wa.me disparam "ligação"
    cms_add_action('body_close', function () {
        $aw = s7x_gads_aw();
        if ($aw === '') {
            return;
        }
        $lead = trim((string) cms_module_get('google-ads', 'lead_label', ''));
        $call = trim((string) cms_module_get('google-ads', 'call_label', ''));
        $cfg = json_encode(['lead' => $lead ? $aw . '/' . $lead : '', 'call' => $call ? $aw . '/' . $call : ''], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
        echo "<script>window.s7xAds=$cfg;document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href^=\"tel:\"],a[href*=\"wa.me/\"]');if(a&&window.gtag&&window.s7xAds.call){gtag('event','conversion',{send_to:window.s7xAds.call});}});</script>\n";
    });
    cms_register_admin_page('google-ads', [
        'label'     => 'Google Ads',
        'order'     => 40,
        'icon'      => 'calendario',
        'group'     => 'Google',
        'perm_help' => 'Ver campanhas, cliques, custo e conversões do Google Ads.',
        'render'    => 's7x_gads_render',
        'handle'    => 's7x_gads_handle',
    ]);
    cms_add_action('ai_tools', function () {
        cms_register_ai_tool('google_ads_report', [
            'description'  => 'Relatório do Google Ads: custo, cliques, impressões e conversões por campanha e por dia.',
            'input_schema' => ['type' => 'object', 'properties' => ['days' => ['type' => 'integer', 'enum' => [7, 28, 90]]]],
            'perm'         => fn () => cms_can('page.google-ads') && s7x_google_connected(),
            'run'          => function (array $a) {
                try {
                    return s7x_gads_report(in_array((int) ($a['days'] ?? 28), [7, 28, 90], true) ? (int) $a['days'] : 28);
                } catch (RuntimeException $e) {
                    throw new CmsAiError($e->getMessage());
                }
            },
        ]);
    });
}

function s7x_gads_search(string $gaql): array
{
    $cid = preg_replace('/\D/', '', (string) cms_module_get('google-ads', 'customer_id', ''));
    $dev = cms_secret_get('google_ads_developer_token');
    if ($cid === '' || $dev === '') {
        throw new RuntimeException('Informe o ID da conta e o developer token do Google Ads.');
    }
    $ver = preg_replace('/[^v0-9]/', '', (string) cms_module_get('google-ads', 'api_version', 'v26')) ?: 'v26';
    $headers = ['developer-token: ' . $dev];
    $login = preg_replace('/\D/', '', (string) cms_module_get('google-ads', 'login_customer_id', ''));
    if ($login !== '') {
        $headers[] = 'login-customer-id: ' . $login;
    }
    $rows = [];
    $token = null;
    do {
        $body = ['query' => $gaql];
        if ($token) {
            $body['pageToken'] = $token;
        }
        $res = s7x_google_api('POST', 'https://googleads.googleapis.com/' . $ver . '/customers/' . $cid . '/googleAds:search', $body, $headers);
        $rows = array_merge($rows, (array) ($res['results'] ?? []));
        $token = $res['nextPageToken'] ?? null;
    } while ($token && count($rows) < 2000);
    return $rows;
}

function s7x_gads_report(int $days, bool $fresh = false): array
{
    $cid = preg_replace('/\D/', '', (string) cms_module_get('google-ads', 'customer_id', ''));
    return s7x_google_cached('gads:' . $cid . ':' . $days, function () use ($days) {
        $from = date('Y-m-d', strtotime('-' . $days . ' days'));
        $to   = date('Y-m-d', strtotime('-1 day'));
        $where = "segments.date BETWEEN '$from' AND '$to'";
        $camps = s7x_gads_search("SELECT campaign.name, campaign.status, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions FROM campaign WHERE $where AND campaign.status != 'REMOVED' ORDER BY metrics.cost_micros DESC LIMIT 25");
        $daily = s7x_gads_search("SELECT segments.date, metrics.clicks, metrics.cost_micros, metrics.conversions FROM customer WHERE $where ORDER BY segments.date");
        $c = array_map(fn ($r) => [
            'name' => $r['campaign']['name'] ?? '', 'status' => $r['campaign']['status'] ?? '',
            'impressions' => (float) ($r['metrics']['impressions'] ?? 0), 'clicks' => (float) ($r['metrics']['clicks'] ?? 0),
            'cost' => ((float) ($r['metrics']['costMicros'] ?? 0)) / 1e6, 'conversions' => (float) ($r['metrics']['conversions'] ?? 0),
        ], $camps);
        $d = array_map(fn ($r) => [
            'date' => $r['segments']['date'] ?? '', 'value' => ((float) ($r['metrics']['costMicros'] ?? 0)) / 1e6,
            'clicks' => (float) ($r['metrics']['clicks'] ?? 0), 'conversions' => (float) ($r['metrics']['conversions'] ?? 0),
        ], $daily);
        $cost = array_sum(array_column($d, 'value'));
        $conv = array_sum(array_column($d, 'conversions'));
        return [
            'period' => $days . ' dias', 'cost' => $cost, 'clicks' => array_sum(array_column($d, 'clicks')),
            'conversions' => $conv, 'cost_per_conversion' => $conv ? $cost / $conv : null,
            'daily' => $d, 'campaigns' => $c,
        ];
    }, $fresh);
}

function s7x_gads_handle(string $action): void
{
    if ($action === 'token' && cms_is_admin()) {
        $t = trim((string) ($_POST['developer_token'] ?? ''));
        if ($t !== '') {
            cms_secret_set('google_ads_developer_token', $t);
            cms_flash('ok', 'Developer token salvo.');
        }
    }
}

function s7x_gads_render(): void
{
    $aw = s7x_gads_aw();
    if ($aw === '') {
        echo '<div class="notice notice--warn">A tag do Google Ads ainda não está no site. ' . (cms_is_admin() ? 'Informe o ID (AW-...) em <a href="' . e(admin_url('module', ['m' => 'google-ads'])) . '">Módulos → Google Ads</a>.' : '') . '</div>';
    } else {
        $lead = trim((string) cms_module_get('google-ads', 'lead_label', ''));
        echo '<div class="notice notice--ok">Tag ' . e($aw) . ' ativa. Conversão de lead: ' . ($lead ? 'ligada' : 'sem rótulo (configure para contar os leads)') . '.</div>';
    }
    if (cms_is_admin()) {
        $has = cms_secret_get('google_ads_developer_token') !== '';
        ?>
        <section class="panel">
          <h2>Acesso à API (relatórios)</h2>
          <p class="muted">Os relatórios usam a API do Google Ads, que exige um <strong>developer token</strong> (Google Ads → Ferramentas → Central de API). Contas novas recebem acesso de teste; para ler contas reais, solicite o acesso Básico ao Google.</p>
          <form method="post" class="row">
            <?= cms_csrf_field() ?><input type="hidden" name="action" value="token">
            <label class="sr" for="dt">Developer token</label>
            <input id="dt" name="developer_token" type="password" autocomplete="off" placeholder="<?= $has ? 'salvo (deixe vazio para manter)' : 'Developer token' ?>" style="flex:1;min-width:240px">
            <button class="btn">Salvar token</button>
          </form>
        </section>
        <?php
    }
    if (!s7x_google_ready_notice()) {
        return;
    }
    $days = s7x_report_days();
    try {
        $r = s7x_gads_report($days, !empty($_GET['fresh']));
    } catch (RuntimeException $e) {
        s7x_report_error($e);
        return;
    }
    $brl = fn ($v) => 'R$ ' . s7x_num($v, 2);
    ?>
    <section class="panel">
      <div class="rhead"><h2>Conta <?= e((string) cms_module_get('google-ads', 'customer_id')) ?></h2><?= s7x_report_period_nav('google-ads') ?></div>
      <?= s7x_report_stats([
          ['Investimento', $brl($r['cost'])],
          ['Cliques', s7x_num($r['clicks'])],
          ['Conversões', s7x_num($r['conversions'], 1)],
          ['Custo por conversão', $r['cost_per_conversion'] === null ? '—' : $brl($r['cost_per_conversion'])],
      ]) ?>
      <?= s7x_report_bars($r['daily'], 'Investimento por dia', $brl) ?>
      <?= s7x_report_table($r['campaigns'], [
          'Campanha' => 'name', 'Impressões' => fn ($x) => s7x_num($x['impressions']), 'Cliques' => fn ($x) => s7x_num($x['clicks']),
          'Custo' => fn ($x) => $brl($x['cost']), 'Conversões' => fn ($x) => s7x_num($x['conversions'], 1),
      ], 'Campanhas') ?>
    </section>
    <?php
}
