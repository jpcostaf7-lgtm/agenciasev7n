<?php
/**
 * Módulo Claude — servidor OAuth 2.1 (PKCE S256 + registro dinâmico) e tokens pessoais
 * Autor: João Costa
 *
 * Permite que o Claude.ai / Claude Desktop / Claude Code se conectem ao conector MCP
 * com o login do painel. Cada acesso age com as permissões do usuário que autorizou.
 */
if (!defined('CMS_ROOT')) {
    exit;
}

const S7X_ACCESS_TTL  = 3600;          // 1 hora
const S7X_REFRESH_TTL = 30 * 86400;    // 30 dias
const S7X_CODE_TTL    = 300;           // 5 minutos

function s7x_oauth_file(): string
{
    return cms_module_data_file('claude', 'oauth');
}

function s7x_oauth_db(): array
{
    $db = cms_read_json(s7x_oauth_file(), []);
    $db += ['clients' => [], 'codes' => [], 'tokens' => []];
    // Limpeza de itens vencidos
    $now = time();
    foreach ($db['codes'] as $k => $c) {
        if (($c['exp'] ?? 0) < $now) {
            unset($db['codes'][$k]);
        }
    }
    foreach ($db['tokens'] as $k => $t) {
        if (!empty($t['exp']) && $t['exp'] < $now) {
            unset($db['tokens'][$k]);
        }
    }
    return $db;
}

function s7x_oauth_save(array $db): void
{
    cms_write_json(s7x_oauth_file(), $db);
    @chmod(s7x_oauth_file(), 0600);
}

function s7x_hash(string $token): string
{
    return hash('sha256', $token);
}

function s7x_issuer(): string
{
    return cms_site_url();
}

function s7x_json(array $data, int $code = 200, array $headers = []): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    foreach ($headers as $h) {
        header($h);
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* -------------------------------------------------------------------------
 * Roteamento das URLs públicas
 * ---------------------------------------------------------------------- */

function s7x_claude_route(): void
{
    $path = cms_request_path();
    if (!cms_module_get('claude', 'mcp_enabled', true)) {
        if ($path === '/mcp' || strpos($path, '/oauth/') === 0) {
            s7x_json(['error' => 'O conector do Claude está desligado neste site.'], 404);
        }
        return;
    }
    $meth = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    switch (true) {
        case $path === '/mcp':
            s7x_mcp_handle();
            break;
        case strpos($path, '/.well-known/oauth-protected-resource') === 0:
            s7x_json([
                'resource'                 => cms_site_url() . '/mcp',
                'authorization_servers'    => [s7x_issuer()],
                'scopes_supported'         => ['mcp'],
                'bearer_methods_supported' => ['header'],
                'resource_name'            => cms_site_name() . ' — Sev7n Xpress',
            ]);
            break;
        case strpos($path, '/.well-known/oauth-authorization-server') === 0:
        case $path === '/.well-known/openid-configuration':
            $base = s7x_issuer();
            s7x_json([
                'issuer'                                => $base,
                'authorization_endpoint'                => $base . '/oauth/authorize',
                'token_endpoint'                        => $base . '/oauth/token',
                'registration_endpoint'                 => $base . '/oauth/register',
                'response_types_supported'              => ['code'],
                'grant_types_supported'                 => ['authorization_code', 'refresh_token'],
                'code_challenge_methods_supported'      => ['S256'],
                'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
                'scopes_supported'                      => ['mcp'],
                'authorization_response_iss_parameter_supported' => true,
            ]);
            break;
        case $path === '/oauth/register' && $meth === 'POST':
            s7x_oauth_register();
            break;
        case $path === '/oauth/authorize':
            s7x_oauth_authorize();
            break;
        case $path === '/oauth/token' && $meth === 'POST':
            s7x_oauth_token();
            break;
    }
}

/* -------------------------------------------------------------------------
 * Registro dinâmico de cliente (RFC 7591)
 * ---------------------------------------------------------------------- */

function s7x_oauth_register(): void
{
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) {
        s7x_json(['error' => 'invalid_client_metadata', 'error_description' => 'JSON inválido'], 400);
    }
    $uris = array_values(array_filter((array) ($in['redirect_uris'] ?? []), function ($u) {
        $p = parse_url((string) $u);
        if (!$p || empty($p['scheme']) || empty($p['host'])) {
            return false;
        }
        return $p['scheme'] === 'https' || in_array($p['host'], ['localhost', '127.0.0.1', '[::1]'], true);
    }));
    if (!$uris) {
        s7x_json(['error' => 'invalid_redirect_uri', 'error_description' => 'Informe redirect_uris com https ou localhost.'], 400);
    }
    $db = s7x_oauth_db();
    if (count($db['clients']) > 300) {
        // Remove clientes antigos sem tokens
        $used = array_column($db['tokens'], 'client');
        foreach ($db['clients'] as $cid => $c) {
            if (!in_array($cid, $used, true) && ($c['created'] ?? 0) < time() - 30 * 86400) {
                unset($db['clients'][$cid]);
            }
        }
    }
    $cid = 's7xc_' . bin2hex(random_bytes(12));
    $method = in_array($in['token_endpoint_auth_method'] ?? 'none', ['none', 'client_secret_post', 'client_secret_basic'], true) ? ($in['token_endpoint_auth_method'] ?? 'none') : 'none';
    $client = [
        'name'          => mb_substr(strip_tags((string) ($in['client_name'] ?? 'Cliente MCP')), 0, 80),
        'redirect_uris' => $uris,
        'auth'          => $method,
        'created'       => time(),
    ];
    $out = [
        'client_id'                  => $cid,
        'client_id_issued_at'        => time(),
        'client_name'                => $client['name'],
        'redirect_uris'              => $uris,
        'grant_types'                => ['authorization_code', 'refresh_token'],
        'response_types'             => ['code'],
        'token_endpoint_auth_method' => $method,
    ];
    if ($method !== 'none') {
        $secret = bin2hex(random_bytes(24));
        $client['secret'] = s7x_hash($secret);
        $out['client_secret'] = $secret;
        $out['client_secret_expires_at'] = 0;
    }
    $db['clients'][$cid] = $client;
    s7x_oauth_save($db);
    s7x_json($out, 201);
}

/** Confere o redirect_uri; endereços locais (Claude Code) aceitam qualquer porta. */
function s7x_redirect_ok(array $client, string $uri): bool
{
    if (in_array($uri, $client['redirect_uris'], true)) {
        return true;
    }
    $p = parse_url($uri);
    if (!$p || !in_array($p['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)) {
        return false;
    }
    foreach ($client['redirect_uris'] as $reg) {
        $r = parse_url($reg);
        if (($r['scheme'] ?? '') === ($p['scheme'] ?? '') && ($r['host'] ?? '') === ($p['host'] ?? '') && ($r['path'] ?? '/') === ($p['path'] ?? '/')) {
            return true;
        }
    }
    return false;
}

/* -------------------------------------------------------------------------
 * Autorização (login no painel + consentimento)
 * ---------------------------------------------------------------------- */

function s7x_oauth_authorize(): void
{
    require_once CMS_ROOT . '/cms/admin_lib.php';
    cms_session_start();
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');

    $q = array_merge($_GET, $_POST);
    $p = [
        'client_id'             => (string) ($q['client_id'] ?? ''),
        'redirect_uri'          => (string) ($q['redirect_uri'] ?? ''),
        'response_type'         => (string) ($q['response_type'] ?? ''),
        'state'                 => (string) ($q['state'] ?? ''),
        'code_challenge'        => (string) ($q['code_challenge'] ?? ''),
        'code_challenge_method' => (string) ($q['code_challenge_method'] ?? ''),
        'scope'                 => (string) ($q['scope'] ?? 'mcp'),
        'resource'              => (string) ($q['resource'] ?? ''),
    ];
    $db = s7x_oauth_db();
    $client = $db['clients'][$p['client_id']] ?? null;
    if (!$client || !s7x_redirect_ok($client, $p['redirect_uri'])) {
        s7x_oauth_page('Conexão inválida', '<p>O aplicativo que pediu acesso não está registrado ou usou um endereço de retorno diferente. Remova o conector no Claude e adicione de novo.</p>');
    }
    $back = function (array $params) use ($p) {
        $params['state'] = $p['state'];
        $params['iss']   = s7x_issuer();
        $sep = strpos($p['redirect_uri'], '?') === false ? '?' : '&';
        header('Location: ' . $p['redirect_uri'] . $sep . http_build_query($params), true, 302);
        exit;
    };
    if ($p['response_type'] !== 'code' || $p['code_challenge'] === '' || $p['code_challenge_method'] !== 'S256') {
        $back(['error' => 'invalid_request', 'error_description' => 'PKCE S256 é obrigatório.']);
    }

    $hidden = '';
    foreach ($p as $k => $v) {
        $hidden .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    $error = '';

    // Login
    if (!cms_logged_in()) {
        if (($_POST['step'] ?? '') === 'login') {
            if (($locked = cms_login_locked())) {
                $error = "Muitas tentativas. Tente de novo em $locked minuto(s).";
            } elseif (!cms_csrf_ok()) {
                $error = 'A sessão expirou. Tente de novo.';
            } elseif (cms_login((string) ($_POST['user'] ?? ''), (string) ($_POST['pass'] ?? ''))) {
                cms_login_attempt(true);
            } else {
                cms_login_attempt(false);
                $error = 'Usuário ou senha incorretos.';
            }
        }
        if (!cms_logged_in()) {
            s7x_oauth_page('Entrar para conectar o Claude', ($error ? '<div class="notice notice--error" role="alert">' . e($error) . '</div>' : '')
                . '<p class="muted"><strong>' . e($client['name']) . '</strong> quer acessar o painel de <strong>' . e(cms_site_name()) . '</strong>. Entre com o seu usuário.</p>'
                . '<form method="post">' . cms_csrf_field() . $hidden . '<input type="hidden" name="step" value="login">'
                . '<div class="field"><label for="u">Usuário</label><input id="u" name="user" required autocomplete="username" autofocus></div>'
                . '<div class="field"><label for="pw">Senha</label><input id="pw" type="password" name="pass" required autocomplete="current-password"></div>'
                . '<button class="btn btn--primary btn--block">Entrar</button></form>');
        }
    }

    $u = cms_acting_user();
    if (!cms_can('page.claude')) {
        s7x_oauth_page('Sem permissão', '<p>O seu usuário não tem permissão para conectar o Claude. Peça ao administrador para liberar “Conectar o Claude” no seu perfil.</p>');
    }

    // Consentimento
    if (($_POST['step'] ?? '') === 'consent' && cms_csrf_ok()) {
        if (($_POST['decision'] ?? '') !== 'allow') {
            $back(['error' => 'access_denied', 'error_description' => 'A pessoa recusou o acesso.']);
        }
        $code = bin2hex(random_bytes(24));
        $db = s7x_oauth_db();
        $db['codes'][s7x_hash($code)] = [
            'client' => $p['client_id'], 'user' => $u['name'], 'redirect_uri' => $p['redirect_uri'],
            'challenge' => $p['code_challenge'], 'scope' => 'mcp', 'exp' => time() + S7X_CODE_TTL,
        ];
        s7x_oauth_save($db);
        $back(['code' => $code]);
    }

    $host = (string) parse_url($p['redirect_uri'], PHP_URL_HOST);
    $local = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    $body = '<p><strong>' . e($client['name']) . '</strong> quer gerenciar <strong>' . e(cms_site_name()) . '</strong> em seu nome.</p>'
        . '<ul class="consent">'
        . '<li>Vai agir como <strong>' . e($u['name']) . '</strong> (' . e(cms_role_label($u['role'])) . '), só com as permissões desse usuário.</li>'
        . '<li>Pode ler e alterar páginas, blocos, configurações, SEO e leads que o seu perfil já acessa.</li>'
        . '<li>Toda alteração gera uma revisão e pode ser desfeita.</li>'
        . '<li>O acesso pode ser revogado a qualquer momento em <em>Conectar o Claude</em>.</li>'
        . '</ul>'
        . '<p class="muted small">Retorno para: <strong>' . e($host) . '</strong>' . ($local ? ' — endereço local deste computador (Claude Code). Só autorize se foi você que iniciou a conexão agora.' : '') . '</p>'
        . '<form method="post" class="row">' . cms_csrf_field() . $hidden . '<input type="hidden" name="step" value="consent">'
        . '<button class="btn btn--primary" name="decision" value="allow">Autorizar acesso</button>'
        . '<button class="btn btn--ghost" name="decision" value="deny">Cancelar</button></form>';
    s7x_oauth_page('Autorizar o Claude', $body);
}

function s7x_oauth_page(string $title, string $body): void
{
    $cms = e(cms_config('cms_name', 'Sev7n Xpress'));
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . e($title) . ' · ' . $cms . '</title>'
        . '<link rel="stylesheet" href="' . e(cms_url('admin/assets/admin.css')) . '?v=' . CMS_VERSION . '"></head>'
        . '<body class="auth"><main class="auth__card auth__card--wide"><p class="brand">' . $cms . ' · Conector Claude</p><h1>' . e($title) . '</h1>' . $body . '</main></body></html>';
    exit;
}

/* -------------------------------------------------------------------------
 * Endpoint de token
 * ---------------------------------------------------------------------- */

function s7x_oauth_token(): void
{
    $grant = (string) ($_POST['grant_type'] ?? '');
    $cid   = (string) ($_POST['client_id'] ?? '');
    $sec   = (string) ($_POST['client_secret'] ?? '');
    if (!$cid && !empty($_SERVER['PHP_AUTH_USER'])) {
        $cid = (string) $_SERVER['PHP_AUTH_USER'];
        $sec = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
    }
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!$cid && stripos($auth, 'basic ') === 0) {
        [$cid, $sec] = array_pad(explode(':', (string) base64_decode(substr($auth, 6)), 2), 2, '');
        $cid = rawurldecode($cid);
        $sec = rawurldecode($sec);
    }
    $db = s7x_oauth_db();
    $client = $db['clients'][$cid] ?? null;
    if (!$client) {
        s7x_json(['error' => 'invalid_client'], 401);
    }
    if (($client['auth'] ?? 'none') !== 'none' && !hash_equals((string) ($client['secret'] ?? ''), s7x_hash($sec))) {
        s7x_json(['error' => 'invalid_client'], 401);
    }

    if ($grant === 'authorization_code') {
        $code = (string) ($_POST['code'] ?? '');
        $rec  = $db['codes'][s7x_hash($code)] ?? null;
        unset($db['codes'][s7x_hash($code)]); // uso único
        $verifier = (string) ($_POST['code_verifier'] ?? '');
        $calc = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if (!$rec || $rec['client'] !== $cid || $rec['redirect_uri'] !== (string) ($_POST['redirect_uri'] ?? '') || !hash_equals($rec['challenge'], $calc)) {
            s7x_oauth_save($db);
            s7x_json(['error' => 'invalid_grant', 'error_description' => 'Código inválido, vencido ou PKCE incorreto.'], 400);
        }
        $user = $rec['user'];
    } elseif ($grant === 'refresh_token') {
        $rt  = (string) ($_POST['refresh_token'] ?? '');
        $rec = $db['tokens'][s7x_hash($rt)] ?? null;
        if (!$rec || $rec['type'] !== 'refresh' || $rec['client'] !== $cid) {
            s7x_json(['error' => 'invalid_grant'], 400);
        }
        unset($db['tokens'][s7x_hash($rt)]); // rotação do refresh token
        $user = $rec['user'];
        // Remove o access token antigo da mesma autorização
        foreach ($db['tokens'] as $k => $t) {
            if (($t['grant'] ?? '') === ($rec['grant'] ?? '-') && $t['type'] === 'access') {
                unset($db['tokens'][$k]);
            }
        }
    } else {
        s7x_json(['error' => 'unsupported_grant_type'], 400);
    }
    if (!isset(cms_users()[$user])) {
        s7x_oauth_save($db);
        s7x_json(['error' => 'invalid_grant', 'error_description' => 'Usuário não existe mais.'], 400);
    }
    $grantId = $rec['grant'] ?? ('g' . bin2hex(random_bytes(6)));
    $access  = 's7xa_' . bin2hex(random_bytes(24));
    $refresh = 's7xr_' . bin2hex(random_bytes(24));
    $created = $rec['created'] ?? time();
    $db['tokens'][s7x_hash($access)]  = ['type' => 'access', 'user' => $user, 'client' => $cid, 'grant' => $grantId, 'exp' => time() + S7X_ACCESS_TTL, 'created' => $created];
    $db['tokens'][s7x_hash($refresh)] = ['type' => 'refresh', 'user' => $user, 'client' => $cid, 'grant' => $grantId, 'exp' => time() + S7X_REFRESH_TTL, 'created' => $created, 'label' => $client['name']];
    s7x_oauth_save($db);
    s7x_json([
        'access_token'  => $access,
        'token_type'    => 'Bearer',
        'expires_in'    => S7X_ACCESS_TTL,
        'refresh_token' => $refresh,
        'scope'         => 'mcp',
    ]);
}

/* -------------------------------------------------------------------------
 * Tokens pessoais (Claude Code) e validação de Bearer
 * ---------------------------------------------------------------------- */

function s7x_oauth_issue_personal_token(string $user, string $label): string
{
    $tok = 's7xp_' . bin2hex(random_bytes(24));
    $db = s7x_oauth_db();
    $db['tokens'][s7x_hash($tok)] = ['type' => 'personal', 'user' => $user, 'client' => '', 'grant' => 'p' . bin2hex(random_bytes(6)), 'exp' => 0, 'created' => time(), 'label' => $label];
    s7x_oauth_save($db);
    return $tok;
}

/** Usuário dono do Bearer token (ou null). Atualiza "último uso". */
function s7x_oauth_user_from_bearer(): ?string
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && function_exists('getallheaders')) {
        foreach ((array) getallheaders() as $k => $v) {
            if (strtolower((string) $k) === 'authorization') {
                $auth = (string) $v;
            }
        }
    }
    if (stripos($auth, 'bearer ') !== 0) {
        return null;
    }
    $tok = trim(substr($auth, 7));
    $db  = s7x_oauth_db();
    $h   = s7x_hash($tok);
    $rec = $db['tokens'][$h] ?? null;
    if (!$rec || !in_array($rec['type'], ['access', 'personal'], true) || !isset(cms_users()[$rec['user']])) {
        return null;
    }
    // Grava o último uso no máximo a cada 5 minutos
    if (($rec['last_used'] ?? 0) < time() - 300) {
        $grant = $rec['grant'] ?? '';
        foreach ($db['tokens'] as $k => $t) {
            if (($t['grant'] ?? '') === $grant) {
                $db['tokens'][$k]['last_used'] = time();
            }
        }
        s7x_oauth_save($db);
    }
    return $rec['user'];
}

/** Lista acessos (um por autorização) para a tela de conexão. */
function s7x_oauth_list_access(string $user, bool $all): array
{
    $db = s7x_oauth_db();
    $out = [];
    foreach ($db['tokens'] as $t) {
        if (!in_array($t['type'], ['refresh', 'personal'], true) || (!$all && $t['user'] !== $user)) {
            continue;
        }
        $gid = $t['grant'] ?? '';
        $out[$gid] = [
            'id'        => $gid,
            'kind'      => $t['type'] === 'personal' ? 'personal' : 'oauth',
            'label'     => $t['label'] ?? ($db['clients'][$t['client']]['name'] ?? 'Claude'),
            'user'      => $t['user'],
            'created'   => date('c', (int) ($t['created'] ?? time())),
            'last_used' => !empty($t['last_used']) ? date('c', (int) $t['last_used']) : null,
        ];
    }
    return array_values($out);
}

function s7x_oauth_revoke(string $grant, string $user, bool $isAdmin): void
{
    $db = s7x_oauth_db();
    foreach ($db['tokens'] as $k => $t) {
        if (($t['grant'] ?? '') === $grant && ($isAdmin || $t['user'] === $user)) {
            unset($db['tokens'][$k]);
        }
    }
    s7x_oauth_save($db);
}
