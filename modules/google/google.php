<?php
/**
 * Módulo Conexão Google — OAuth, chamadas às APIs, cache e componentes de relatório
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_google_boot(): void
{
    cms_register_admin_page('google', [
        'label'      => 'Conexão Google',
        'order'     => 90,
        'icon'       => 'pulso',
        'group'      => 'Google',
        'admin_only' => true,
        'render'     => 's7x_google_admin_render',
        'handle'     => 's7x_google_admin_handle',
    ]);
    cms_add_action('admin_head', function () {
        echo '<link rel="stylesheet" href="' . e(cms_module_url('google', 'assets/reports.css')) . '">';
    });
    cms_add_action('admin_footer', function () {
        echo '<script src="' . e(cms_module_url('google', 'assets/reports.js')) . '" defer></script>';
    });

    // Tag do Google (gtag.js) carregada uma vez para Analytics e Google Ads
    cms_add_filter('tracking_html', function (string $html, string $place) {
        if ($place !== 'head') {
            return $html;
        }
        $ids = array_values(array_unique(array_filter((array) cms_apply_filters('gtag_ids', []))));
        if (!$ids) {
            return $html;
        }
        $js = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . e($ids[0]) . '"></script>' . "\n"
            . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());";
        foreach ($ids as $id) {
            $js .= "gtag('config','" . e($id) . "');";
        }
        return $js . "</script>\n" . $html;
    }, 5);
}

/* -------------------------------------------------------------------------
 * OAuth Google
 * ---------------------------------------------------------------------- */

function s7x_google_redirect_uri(): string
{
    return cms_site_url() . '/admin/google-callback.php';
}

/** Escopos pedidos: os dos módulos Google ativos. */
function s7x_google_scopes(): array
{
    $scopes = ['openid', 'email'];
    foreach (cms_available_modules() as $slug => $m) {
        if (cms_module_active($slug)) {
            $scopes = array_merge($scopes, (array) ($m['google_scopes'] ?? []));
        }
    }
    return array_values(array_unique($scopes));
}

function s7x_google_connected(): bool
{
    return cms_secret_get('google_refresh_token') !== '';
}

/** Escopos que faltam autorizar (módulo ativado depois da conexão). */
function s7x_google_missing_scopes(): array
{
    $granted = explode(' ', cms_secret_get('google_scopes'));
    return array_values(array_diff(s7x_google_scopes(), $granted, ['openid', 'email']));
}

function s7x_google_auth_url(): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['s7x_google_state'] = $state;
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'              => (string) cms_module_get('google', 'client_id', ''),
        'redirect_uri'           => s7x_google_redirect_uri(),
        'response_type'          => 'code',
        'scope'                  => implode(' ', s7x_google_scopes()),
        'access_type'            => 'offline',
        'prompt'                 => 'consent',
        'include_granted_scopes' => 'true',
        'state'                  => $state,
    ]);
}

/** Recebe o retorno do Google (admin/google-callback.php). */
function s7x_google_callback(): void
{
    $state = (string) ($_GET['state'] ?? '');
    if ($state === '' || !hash_equals((string) ($_SESSION['s7x_google_state'] ?? ''), $state)) {
        cms_flash('error', 'A conexão com o Google expirou ou veio de outra janela. Clique em Conectar de novo.');
        return;
    }
    unset($_SESSION['s7x_google_state']);
    if (!empty($_GET['error'])) {
        cms_flash('error', 'O Google não autorizou a conexão (' . e((string) $_GET['error']) . ').');
        return;
    }
    $r = cms_http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'code'          => (string) ($_GET['code'] ?? ''),
        'client_id'     => (string) cms_module_get('google', 'client_id', ''),
        'client_secret' => cms_secret_get('google_client_secret'),
        'redirect_uri'  => s7x_google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]));
    $j = $r['json'] ?? [];
    if ($r['status'] !== 200 || empty($j['access_token'])) {
        cms_flash('error', 'O Google recusou a troca do código: ' . ($j['error_description'] ?? $j['error'] ?? ('HTTP ' . $r['status'])) . '. Confira o Client ID, o Client Secret e o endereço de retorno.');
        return;
    }
    if (!empty($j['refresh_token'])) {
        cms_secret_set('google_refresh_token', (string) $j['refresh_token']);
    }
    cms_secret_set('google_access_token', (string) $j['access_token']);
    cms_secret_set('google_access_exp', (string) (time() + (int) ($j['expires_in'] ?? 3600) - 60));
    cms_secret_set('google_scopes', (string) ($j['scope'] ?? ''));
    $email = '';
    if (!empty($j['id_token'])) {
        $parts = explode('.', (string) $j['id_token']);
        $payload = json_decode((string) base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
        $email = (string) ($payload['email'] ?? '');
    }
    cms_secret_set('google_email', $email);
    s7x_google_cache_clear();
    cms_flash('ok', 'Conta Google conectada' . ($email ? ' (' . $email . ')' : '') . '.');
}

function s7x_google_access_token(): string
{
    $tok = cms_secret_get('google_access_token');
    if ($tok !== '' && (int) cms_secret_get('google_access_exp') > time()) {
        return $tok;
    }
    $refresh = cms_secret_get('google_refresh_token');
    if ($refresh === '') {
        throw new RuntimeException('A conta Google não está conectada. Um administrador conecta em Conexão Google.');
    }
    $r = cms_http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
        'client_id'     => (string) cms_module_get('google', 'client_id', ''),
        'client_secret' => cms_secret_get('google_client_secret'),
        'refresh_token' => $refresh,
        'grant_type'    => 'refresh_token',
    ]));
    $j = $r['json'] ?? [];
    if ($r['status'] !== 200 || empty($j['access_token'])) {
        if (($j['error'] ?? '') === 'invalid_grant') {
            cms_secret_set('google_refresh_token', null);
            throw new RuntimeException('A autorização do Google expirou ou foi revogada. Conecte a conta de novo.');
        }
        throw new RuntimeException('Não foi possível renovar o acesso ao Google (HTTP ' . $r['status'] . ').');
    }
    cms_secret_set('google_access_token', (string) $j['access_token']);
    cms_secret_set('google_access_exp', (string) (time() + (int) ($j['expires_in'] ?? 3600) - 60));
    return (string) $j['access_token'];
}

/** Chamada autenticada a uma API do Google. Lança exceção com mensagem em português. */
function s7x_google_api(string $method, string $url, $body = null, array $headers = []): array
{
    $headers[] = 'Authorization: Bearer ' . s7x_google_access_token();
    $r = cms_http($method, $url, $headers, $body, 30);
    if ($r['status'] >= 200 && $r['status'] < 300) {
        return (array) ($r['json'] ?? []);
    }
    $err = $r['json']['error'] ?? [];
    $msg = is_array($err) ? (string) ($err['message'] ?? '') : (string) $err;
    if (is_array($r['json']) && isset($r['json'][0]['error']['message'])) {
        $msg = (string) $r['json'][0]['error']['message'];
    }
    if ($r['status'] === 0) {
        throw new RuntimeException('Sem conexão com o Google: ' . ($r['error'] ?: 'tempo esgotado') . '.');
    }
    if ($r['status'] === 403 && stripos($msg, 'has not been used') !== false) {
        $msg = 'Esta API não está ativada no seu projeto do Google Cloud. Ative-a em APIs e serviços. (' . $msg . ')';
    } elseif ($r['status'] === 403) {
        $msg = 'A conta Google conectada não tem acesso a este recurso. ' . $msg;
    } elseif ($r['status'] === 401) {
        cms_secret_set('google_access_exp', '0');
        $msg = 'O Google recusou o acesso. Tente de novo ou reconecte a conta. ' . $msg;
    }
    throw new RuntimeException(trim('Google respondeu ' . $r['status'] . ': ' . $msg));
}

/* -------------------------------------------------------------------------
 * Cache dos relatórios
 * ---------------------------------------------------------------------- */

function s7x_google_cache_file(): string
{
    return cms_module_data_file('google', 'cache');
}

function s7x_google_cached(string $key, callable $fn, bool $fresh = false)
{
    $all = cms_read_json(s7x_google_cache_file(), []);
    $ttl = max(1, (int) cms_module_get('google', 'cache_minutes', 60)) * 60;
    if (!$fresh && isset($all[$key]) && ($all[$key]['t'] ?? 0) > time() - $ttl) {
        return $all[$key]['v'];
    }
    $v = $fn();
    $all[$key] = ['t' => time(), 'v' => $v];
    if (count($all) > 80) {
        uasort($all, fn ($a, $b) => $b['t'] <=> $a['t']);
        $all = array_slice($all, 0, 60, true);
    }
    cms_write_json(s7x_google_cache_file(), $all);
    return $v;
}

function s7x_google_cache_clear(): void
{
    @unlink(s7x_google_cache_file());
}

/* -------------------------------------------------------------------------
 * Componentes de relatório (usados pelos módulos Google)
 * ---------------------------------------------------------------------- */

/** Período escolhido na tela (7, 28 ou 90 dias). */
function s7x_report_days(): int
{
    $d = (int) ($_GET['d'] ?? 28);
    return in_array($d, [7, 28, 90], true) ? $d : 28;
}

function s7x_report_period_nav(string $page): string
{
    $cur = s7x_report_days();
    $html = '<div class="tabs" role="group" aria-label="Período">';
    foreach ([7 => '7 dias', 28 => '28 dias', 90 => '90 dias'] as $d => $l) {
        $html .= '<a href="' . e(admin_url($page, ['d' => $d])) . '" class="' . ($d === $cur ? 'is-active' : '') . '"' . ($d === $cur ? ' aria-current="true"' : '') . '>' . $l . '</a>';
    }
    $html .= '<a href="' . e(admin_url($page, ['d' => $cur, 'fresh' => 1])) . '" title="Buscar de novo no Google">Atualizar</a>';
    return $html . '</div>';
}

function s7x_num($v, int $dec = 0): string
{
    return number_format((float) $v, $dec, ',', '.');
}

/** Blocos de números principais. $items: [[rótulo, valor formatado, detalhe opcional]] */
function s7x_report_stats(array $items): string
{
    $h = '<div class="rstats">';
    foreach ($items as $it) {
        $h .= '<div class="rstat"><span class="rstat__label">' . e($it[0]) . '</span><span class="rstat__value">' . e($it[1]) . '</span>'
            . (!empty($it[2]) ? '<span class="rstat__note">' . e($it[2]) . '</span>' : '') . '</div>';
    }
    return $h . '</div>';
}

/**
 * Gráfico de barras de uma série por dia (SVG acessível, com dica ao passar o mouse).
 * $points: [['date' => 'Y-m-d', 'value' => float]]
 */
function s7x_report_bars(array $points, string $title, callable $fmt): string
{
    if (!$points) {
        return '<p class="muted">Sem dados no período.</p>';
    }
    $w = 760;
    $h = 200;
    $padL = 44;
    $padB = 26;
    $padT = 10;
    $n = count($points);
    $max = max(1e-9, max(array_map(fn ($p) => (float) $p['value'], $points)));
    // arredonda o topo do eixo para um número "bonito"
    $mag = pow(10, floor(log10($max)));
    $nice = ceil($max / $mag * 2) / 2 * $mag;
    $slot = ($w - $padL) / $n;
    $bw = max(2, $slot - 2);
    $svg = '<svg class="rbars" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($title) . '" preserveAspectRatio="none">';
    foreach ([0, .5, 1] as $g) {
        $y = $padT + ($h - $padT - $padB) * (1 - $g);
        $svg .= '<line x1="' . $padL . '" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '" class="rbars__grid"/>';
        $svg .= '<text x="' . ($padL - 6) . '" y="' . ($y + 4) . '" class="rbars__axis" text-anchor="end">' . e($fmt($nice * $g)) . '</text>';
    }
    foreach ($points as $i => $p) {
        $v = (float) $p['value'];
        $bh = ($h - $padT - $padB) * ($v / $nice);
        $x = $padL + $i * $slot + 1;
        $y = $h - $padB - $bh;
        $r = min(4, $bw / 2, $bh);
        $date = date('d/m', strtotime($p['date']));
        $tip = $date . ': ' . $fmt($v);
        // barra com cantos arredondados só no topo
        $path = $bh <= 0 ? '' : 'M' . $x . ',' . ($h - $padB) . 'V' . ($y + $r) . 'Q' . $x . ',' . $y . ' ' . ($x + $r) . ',' . $y . 'H' . ($x + $bw - $r) . 'Q' . ($x + $bw) . ',' . $y . ' ' . ($x + $bw) . ',' . ($y + $r) . 'V' . ($h - $padB) . 'Z';
        $svg .= '<g class="rbars__bar" tabindex="0" data-tip="' . e($tip) . '"><title>' . e($tip) . '</title>'
            . '<rect x="' . ($padL + $i * $slot) . '" y="' . $padT . '" width="' . $slot . '" height="' . ($h - $padT - $padB) . '" class="rbars__hit"/>'
            . ($path ? '<path d="' . $path . '"/>' : '') . '</g>';
    }
    $labels = [0, (int) floor(($n - 1) / 2), $n - 1];
    foreach (array_unique($labels) as $i) {
        $x = $padL + $i * $slot + $slot / 2;
        $svg .= '<text x="' . $x . '" y="' . ($h - 6) . '" class="rbars__axis" text-anchor="middle">' . e(date('d/m', strtotime($points[$i]['date']))) . '</text>';
    }
    $svg .= '</svg>';
    return '<figure class="rchart"><figcaption>' . e($title) . '</figcaption>' . $svg . '<div class="rchart__tip" hidden></div></figure>';
}

/** Tabela simples. $cols: [rótulo => callable(linha) ou chave]. */
function s7x_report_table(array $rows, array $cols, string $caption): string
{
    if (!$rows) {
        return '<p class="muted">Sem dados no período.</p>';
    }
    $h = '<table class="table rtable"><caption>' . e($caption) . '</caption><thead><tr>';
    foreach ($cols as $label => $_) {
        $h .= '<th>' . e($label) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $h .= '<tr>';
        foreach ($cols as $label => $get) {
            $v = is_callable($get) ? $get($row) : ($row[$get] ?? '');
            $h .= '<td>' . e($v) . '</td>';
        }
        $h .= '</tr>';
    }
    return $h . '</tbody></table>';
}

/** Aviso padrão quando a conexão não está pronta. Retorna true se pode seguir. */
function s7x_google_ready_notice(): bool
{
    if (!cms_module_active('google') || !s7x_google_connected()) {
        echo '<div class="notice notice--warn">A conta Google ainda não está conectada. '
            . (cms_is_admin() ? '<a href="' . e(admin_url('google')) . '">Conectar agora</a>' : 'Peça ao administrador para conectar.') . '</div>';
        return false;
    }
    $missing = s7x_google_missing_scopes();
    if ($missing && cms_is_admin()) {
        echo '<div class="notice notice--warn">Um módulo Google foi ativado depois da conexão. <a href="' . e(admin_url('google')) . '">Reconecte a conta</a> para liberar o acesso.</div>';
    }
    return true;
}

function s7x_report_error(Throwable $e): void
{
    echo '<div class="notice notice--error">' . e($e->getMessage()) . '</div>';
}

/* -------------------------------------------------------------------------
 * Tela Conexão Google
 * ---------------------------------------------------------------------- */

function s7x_google_admin_handle(string $action): void
{
    if ($action === 'save') {
        $content = cms_content();
        $content['modules']['google']['client_id'] = trim((string) ($_POST['client_id'] ?? ''));
        cms_save_content($content, 'Conexão Google');
        $sec = trim((string) ($_POST['client_secret'] ?? ''));
        if ($sec !== '') {
            cms_secret_set('google_client_secret', $sec);
        }
        cms_flash('ok', 'Credenciais salvas. Agora clique em Conectar conta Google.');
    }
    if ($action === 'connect') {
        if (!cms_module_get('google', 'client_id') || cms_secret_get('google_client_secret') === '') {
            cms_flash('error', 'Informe o Client ID e o Client Secret primeiro.');
            return;
        }
        header('Location: ' . s7x_google_auth_url());
        exit;
    }
    if ($action === 'disconnect') {
        $tok = cms_secret_get('google_refresh_token');
        if ($tok !== '') {
            cms_http('POST', 'https://oauth2.googleapis.com/revoke', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['token' => $tok]), 10);
        }
        foreach (['google_refresh_token', 'google_access_token', 'google_access_exp', 'google_scopes', 'google_email'] as $k) {
            cms_secret_set($k, null);
        }
        s7x_google_cache_clear();
        cms_flash('ok', 'Conta Google desconectada.');
    }
}

function s7x_google_admin_render(): void
{
    $cid  = (string) cms_module_get('google', 'client_id', '');
    $hasS = cms_secret_get('google_client_secret') !== '';
    $conn = s7x_google_connected();
    $missing = $conn ? s7x_google_missing_scopes() : [];
    $apis = [
        'google-search-console' => ['Google Search Console API', 'searchconsole.googleapis.com'],
        'google-analytics'      => ['Google Analytics Data API + Google Analytics Admin API', 'analyticsdata.googleapis.com'],
        'google-adsense'        => ['AdSense Management API', 'adsense.googleapis.com'],
        'google-ads'            => ['Google Ads API', 'googleads.googleapis.com'],
    ];
    ?>
    <section class="panel">
      <h2>Status</h2>
      <?php if ($conn): ?>
        <p><span class="tag tag--on">conectado</span> <?= e(cms_secret_get('google_email') ?: 'Conta Google') ?></p>
        <?php if ($missing): ?><div class="notice notice--warn">Faltam permissões para módulos ativados depois da conexão. Clique em Reconectar.</div><?php endif; ?>
        <div class="row">
          <form method="post"><?= cms_csrf_field() ?><input type="hidden" name="action" value="connect"><button class="btn<?= $missing ? ' btn--primary' : '' ?>">Reconectar</button></form>
          <form method="post" data-confirm="Desconectar a conta Google? Os relatórios param de aparecer até conectar de novo. As tags do site continuam."><?= cms_csrf_field() ?><input type="hidden" name="action" value="disconnect"><button class="btn btn--danger">Desconectar</button></form>
        </div>
      <?php else: ?>
        <p class="muted">Não conectado. As tags (Analytics, AdSense, Google Ads) já funcionam sem conexão; a conexão serve para mostrar os relatórios aqui no painel.</p>
        <?php if ($cid && $hasS): ?>
          <form method="post"><?= cms_csrf_field() ?><input type="hidden" name="action" value="connect"><button class="btn btn--primary">Conectar conta Google</button></form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="panel">
      <h2>Credenciais do Google Cloud</h2>
      <ol class="steps-list">
        <li>Acesse <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> e crie um projeto (ex.: “Site <?= e(cms_site_name()) ?>”).</li>
        <li>Em <strong>APIs e serviços → Biblioteca</strong>, ative as APIs dos módulos que você usa:
          <ul><?php foreach ($apis as $slug => [$name, $host]): if (!cms_module_active($slug)) { continue; } ?><li><?= e($name) ?></li><?php endforeach; ?></ul></li>
        <li>Em <strong>Tela de consentimento OAuth</strong>, escolha “Externo”, preencha o nome do app e adicione o seu e-mail como usuário de teste (ou publique o app).</li>
        <li>Em <strong>Credenciais → Criar credenciais → ID do cliente OAuth</strong>, escolha “Aplicativo da Web” e adicione este URI de redirecionamento autorizado:</li>
      </ol>
      <div class="copybox"><code><?= e(s7x_google_redirect_uri()) ?></code><button type="button" class="btn btn--small" data-copy="<?= e(s7x_google_redirect_uri()) ?>">Copiar</button></div>
      <form method="post" class="grid">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="save">
        <div class="field"><label for="gcid">Client ID</label><input id="gcid" name="client_id" value="<?= e($cid) ?>" placeholder="....apps.googleusercontent.com"></div>
        <div class="field"><label for="gcs">Client Secret</label><input id="gcs" name="client_secret" type="password" autocomplete="off" placeholder="<?= $hasS ? 'salvo (deixe vazio para manter)' : 'GOCSPX-...' ?>"><p class="help">Fica em data/secrets.json, fora dos backups.</p></div>
        <div><button class="btn btn--primary">Salvar credenciais</button></div>
      </form>
    </section>
    <?php
}
