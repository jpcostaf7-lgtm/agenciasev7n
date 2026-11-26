<?php
/**
 * Módulo Claude — servidor MCP (Streamable HTTP)
 * Autor: João Costa
 *
 * Compatível com o protocolo atual (2026-07-28, sem sessão) e com clientes que
 * ainda usam o handshake "initialize" (2025-11-25 e anteriores).
 */
if (!defined('CMS_ROOT')) {
    exit;
}

const S7X_MCP_MODERN = ['2026-07-28'];
const S7X_MCP_LEGACY = ['2025-11-25', '2025-06-18', '2025-03-26'];

function s7x_mcp_server_info(): array
{
    return ['name' => 'sev7n-xpress', 'title' => cms_site_name() . ' (Sev7n Xpress)', 'version' => CMS_VERSION];
}

function s7x_mcp_handle(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'OPTIONS') {
        http_response_code(204);
        header('Allow: POST');
        exit;
    }
    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: text/plain; charset=utf-8');
        echo "Conector MCP do Sev7n Xpress. Use POST (Streamable HTTP).";
        exit;
    }
    // Proteção contra DNS rebinding: Origin, se vier, tem que ser deste site
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $site = parse_url(cms_site_url(), PHP_URL_HOST);
        if (parse_url($origin, PHP_URL_HOST) !== $site) {
            s7x_json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => 'Origin não permitida']], 403);
        }
    }

    $user = s7x_oauth_user_from_bearer();
    if (!$user) {
        $meta = cms_site_url() . '/.well-known/oauth-protected-resource';
        s7x_json(['error' => 'invalid_token', 'error_description' => 'Conecte-se com OAuth ou envie Authorization: Bearer <token>.'], 401, [
            'WWW-Authenticate: Bearer resource_metadata="' . $meta . '", scope="mcp"',
        ]);
    }
    cms_set_acting_user($user);
    if (!cms_can('page.claude')) {
        s7x_json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => 'Este usuário não tem permissão para usar o conector.']], 403);
    }
    require_once CMS_ROOT . '/cms/admin_lib.php';

    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        s7x_json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'JSON inválido']], 400);
    }
    $headerVersion = (string) ($_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ?? '');
    $batch = array_is_list_compat($raw) ? $raw : [$raw];
    $out = [];
    foreach ($batch as $msg) {
        $res = s7x_mcp_dispatch((array) $msg, $headerVersion);
        if ($res !== null) {
            $out[] = $res;
        }
    }
    if (!$out) {
        http_response_code(202); // só notificações
        exit;
    }
    $status = 200;
    if (count($out) === 1 && isset($out[0]['error']) && ($out[0]['error']['code'] ?? 0) === -32022) {
        $status = 400;
    }
    s7x_json(array_is_list_compat($raw) ? $out : $out[0], $status);
}

function array_is_list_compat(array $a): bool
{
    return $a === [] || array_keys($a) === range(0, count($a) - 1);
}

/** Processa uma mensagem JSON-RPC. Retorna a resposta ou null (notificação). */
function s7x_mcp_dispatch(array $msg, string $headerVersion): ?array
{
    $id     = $msg['id'] ?? null;
    $method = (string) ($msg['method'] ?? '');
    $params = (array) ($msg['params'] ?? []);
    $isNotification = !array_key_exists('id', $msg);
    if ($isNotification) {
        return null;
    }
    $reply = fn (array $result) => ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    $fail  = fn (int $code, string $m, $data = null) => ['jsonrpc' => '2.0', 'id' => $id, 'error' => array_filter(['code' => $code, 'message' => $m, 'data' => $data], fn ($v) => $v !== null)];

    $metaVersion = (string) ($params['_meta']['io.modelcontextprotocol/protocolVersion'] ?? '');
    $version = $metaVersion ?: $headerVersion;
    $modern = in_array($version, S7X_MCP_MODERN, true) || $method === 'server/discover';
    $supported = array_merge(S7X_MCP_MODERN, S7X_MCP_LEGACY);
    if ($metaVersion !== '' && !in_array($metaVersion, $supported, true)) {
        return $fail(-32022, 'Unsupported protocol version', ['supported' => $supported, 'requested' => $metaVersion]);
    }

    $instructions = cms_ai_instructions();
    $info = s7x_mcp_server_info();
    $finish = function (array $result) use ($modern, $info) {
        if ($modern) {
            $result['resultType'] = 'complete';
            $result['_meta'] = ['io.modelcontextprotocol/serverInfo' => $info];
        }
        return $result;
    };

    switch ($method) {
        case 'initialize': // clientes antigos
            $req = (string) ($params['protocolVersion'] ?? '');
            return $reply([
                'protocolVersion' => in_array($req, S7X_MCP_LEGACY, true) ? $req : S7X_MCP_LEGACY[0],
                'capabilities'    => ['tools' => ['listChanged' => false]],
                'serverInfo'      => $info,
                'instructions'    => $instructions,
            ]);
        case 'server/discover':
            return $reply($finish([
                'supportedVersions' => $supported,
                'capabilities'      => ['tools' => ['listChanged' => false]],
                'instructions'      => $instructions,
                'ttlMs'             => 0,
                'cacheScope'        => 'private',
            ]));
        case 'ping':
            return $reply([]);
        case 'tools/list':
            $tools = [];
            foreach (cms_ai_tools() as $name => $t) {
                $tools[] = [
                    'name'        => $name,
                    'description' => $t['description'],
                    'inputSchema' => $t['input_schema'],
                    'annotations' => [
                        'readOnlyHint'    => empty($t['write']),
                        'destructiveHint' => strpos($name, 'delete') === 0,
                        'idempotentHint'  => empty($t['write']),
                        'openWorldHint'   => false,
                    ],
                ];
            }
            return $reply($finish(['tools' => $tools, 'ttlMs' => 0, 'cacheScope' => 'private']));
        case 'tools/call':
            $name = (string) ($params['name'] ?? '');
            $args = (array) ($params['arguments'] ?? []);
            if (!isset(cms_ai_tools()[$name])) {
                return $fail(-32602, 'Ferramenta desconhecida ou sem permissão: ' . $name);
            }
            $r = cms_ai_execute($name, $args);
            $text = is_string($r['result']) ? $r['result'] : json_encode($r['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $result = ['content' => [['type' => 'text', 'text' => (string) $text]], 'isError' => !$r['ok']];
            if ($r['ok'] && is_array($r['result']) && !array_is_list_compat($r['result'])) {
                $result['structuredContent'] = $r['result'];
            }
            return $reply($finish($result));
        case 'resources/list':
            return $reply($finish(['resources' => [], 'ttlMs' => 0, 'cacheScope' => 'private']));
        case 'prompts/list':
            return $reply($finish(['prompts' => [], 'ttlMs' => 0, 'cacheScope' => 'private']));
        default:
            return $fail(-32601, 'Método não suportado: ' . $method);
    }
}
