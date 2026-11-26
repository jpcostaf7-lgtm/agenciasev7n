<?php
/**
 * Sev7n Xpress — cliente HTTP e cofre de segredos
 * Autor: João Costa
 */
declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Cofre de segredos (chaves de API, tokens). Fica em data/secrets.json,
 * fora do backup e das revisões.
 * ---------------------------------------------------------------------- */

function cms_secrets(): array
{
    return cms_read_json(CMS_DATA . '/secrets.json', []);
}

function cms_secret_get(string $key, string $fallback = ''): string
{
    return (string) (cms_secrets()[$key] ?? $fallback);
}

function cms_secret_set(string $key, ?string $value): bool
{
    $all = cms_secrets();
    if ($value === null || $value === '') {
        unset($all[$key]);
    } else {
        $all[$key] = $value;
    }
    $ok = cms_write_json(CMS_DATA . '/secrets.json', $all);
    @chmod(CMS_DATA . '/secrets.json', 0600);
    return $ok;
}

/** Mostra só o fim de uma chave: ••••••a1b2 */
function cms_mask(string $secret): string
{
    return $secret === '' ? '' : str_repeat('•', 8) . substr($secret, -4);
}

/* -------------------------------------------------------------------------
 * HTTP
 * ---------------------------------------------------------------------- */

/**
 * Faz uma requisição HTTP. Usa cURL quando existe; senão, streams do PHP.
 * Retorna ['status' => int, 'body' => string, 'json' => array|null, 'error' => string]
 * O filtro "http_request" permite interceptar chamadas (testes e depuração).
 */
function cms_http(string $method, string $url, array $headers = [], $body = null, int $timeout = 30): array
{
    $pre = cms_apply_filters('http_request', null, $method, $url, $headers, $body);
    if (is_array($pre)) {
        return $pre + ['status' => 200, 'body' => '', 'json' => null, 'error' => ''];
    }
    if (is_array($body)) {
        $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
    }
    $headers[] = 'User-Agent: Sev7nXpress/' . CMS_VERSION;
    $status = 0;
    $resp   = '';
    $error  = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($body !== null && $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => strtoupper($method),
            'header'        => implode("\r\n", $headers),
            'content'       => (string) $body,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            $resp  = '';
            $error = 'Falha de conexão';
        }
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
    }
    $json = json_decode($resp, true);
    return ['status' => $status, 'body' => $resp, 'json' => is_array($json) ? $json : null, 'error' => $error];
}
