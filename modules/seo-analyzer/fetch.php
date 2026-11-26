<?php
/**
 * Analisador de SEO — acesso seguro a sites de terceiros
 * Autor: João Costa
 *
 * Só http/https, só portas 80/443, nunca endereços internos (127.x, 10.x, 192.168.x,
 * 169.254.x, ::1…). O IP é resolvido aqui e fixado na conexão (CURLOPT_RESOLVE), e cada
 * redirecionamento é conferido de novo — assim um domínio não consegue apontar para a
 * rede interna do servidor no meio do caminho.
 */
if (!defined('CMS_ROOT')) {
    exit;
}

const S7X_SEO_UA = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36 Sev7nSEO/1.0';

/** Normaliza o que o visitante digitou ("www.site.com.br", "site.com/pagina") em uma URL. */
function s7x_seo_normalize_url(string $in): string
{
    $in = trim(preg_replace('/\s+/', '', $in));
    if ($in === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $in)) {
        $in = 'https://' . ltrim($in, '/');
    }
    $p = parse_url($in);
    if (!$p || empty($p['host'])) {
        return '';
    }
    $host = strtolower($p['host']);
    if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host)) {
        $host = (string) idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46);
    }
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host) && !s7x_seo_allow_local()) {
        return '';
    }
    $scheme = strtolower($p['scheme'] ?? 'https');
    $port = isset($p['port']) ? ':' . (int) $p['port'] : '';
    $path = $p['path'] ?? '/';
    $query = isset($p['query']) ? '?' . $p['query'] : '';
    return $scheme . '://' . $host . $port . ($path === '' ? '/' : $path) . $query;
}

/** Endereços locais só em ambiente de teste (config.php: 'seo_allow_local' => true). */
function s7x_seo_allow_local(): bool
{
    return (bool) cms_config('seo_allow_local', false);
}

/** IP público? (bloqueia rede interna, loopback, link-local, reservados) */
function s7x_seo_public_ip(string $ip): bool
{
    if (s7x_seo_allow_local()) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        && !preg_match('/^(0\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|169\.254\.|fc|fd|fe80:)/i', $ip);
}

/** Resolve o host e devolve um IP público, ou lança erro em português. */
function s7x_seo_resolve(string $host): string
{
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!s7x_seo_public_ip($host)) {
            throw new RuntimeException('Esse endereço não pode ser analisado.');
        }
        return $host;
    }
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips && function_exists('dns_get_record')) {
        foreach ((array) @dns_get_record($host, DNS_AAAA) as $r) {
            if (!empty($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }
    }
    if (!$ips) {
        throw new RuntimeException('Não encontramos o domínio "' . $host . '". Confira se o endereço está certo.');
    }
    foreach ($ips as $ip) {
        if (!s7x_seo_public_ip($ip)) {
            throw new RuntimeException('Esse endereço não pode ser analisado.');
        }
    }
    return $ips[0];
}

/**
 * Busca uma URL com segurança. Segue até $maxRedirects redirecionamentos (cada um conferido).
 * Retorna: url final, status, headers (minúsculos), body, chain [{url,status}], tempos (ms), bytes,
 * encoding, http_version, ssl_ok, erro.
 */
function s7x_seo_fetch(string $url, array $opt = []): array
{
    $opt += ['method' => 'GET', 'max_bytes' => 3 * 1024 * 1024, 'timeout' => 20, 'redirects' => 6, 'ua' => S7X_SEO_UA, 'body' => true];
    $chain = [];
    $current = $url;
    $res = ['url' => $url, 'status' => 0, 'headers' => [], 'body' => '', 'chain' => [], 'ttfb' => 0, 'total' => 0,
        'dns' => 0, 'connect' => 0, 'tls' => 0, 'bytes' => 0, 'encoding' => '', 'http_version' => '', 'ssl_ok' => null, 'error' => '', 'ip' => ''];
    $mock = cms_apply_filters('seo_fetch', null, $url, $opt);
    if (is_array($mock)) {
        return $mock + $res;
    }
    for ($hop = 0; $hop <= $opt['redirects']; $hop++) {
        $p = parse_url($current);
        $scheme = strtolower($p['scheme'] ?? '');
        $host = strtolower($p['host'] ?? '');
        $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            $res['error'] = 'Endereço inválido.';
            break;
        }
        if (!in_array($port, [80, 443], true) && !s7x_seo_allow_local()) {
            $res['error'] = 'Só analisamos sites nas portas padrão (80 e 443).';
            break;
        }
        try {
            $ip = s7x_seo_resolve($host);
        } catch (Throwable $e) {
            $res['error'] = $e->getMessage();
            break;
        }
        $res['ip'] = $ip;
        $headers = [];
        $body = '';
        $ch = curl_init($current);
        $limit = (int) $opt['max_bytes'];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $opt['method'],
            CURLOPT_NOBODY         => $opt['method'] === 'HEAD',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => (int) $opt['timeout'],
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_ENCODING       => '', // aceita gzip/br e registra o que o servidor mandou
            CURLOPT_USERAGENT      => $opt['ua'],
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language: pt-BR,pt;q=0.9,en;q=0.6'],
            CURLOPT_RESOLVE        => [$host . ':' . $port . ':' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip)],
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                $len = strlen($line);
                if (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $k = strtolower(trim($k));
                    $v = trim($v);
                    $headers[$k] = isset($headers[$k]) ? $headers[$k] . ', ' . $v : $v;
                } elseif (preg_match('#^HTTP/#', $line)) {
                    $headers = [];
                }
                return $len;
            },
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, $limit, $opt) {
                if (!$opt['body']) {
                    return -1;
                }
                $body .= $chunk;
                return strlen($body) > $limit ? -1 : strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // Certificado inválido: registra e tenta de novo sem verificar, só para continuar a análise
        if (!$ok && in_array($errno, [35, 51, 58, 60], true) && $scheme === 'https' && $res['ssl_ok'] !== false) {
            $res['ssl_ok'] = false;
            $res['ssl_error'] = $err;
            $hop--;
            $opt['_insecure'] = true;
            // repete a mesma URL sem verificação
            $ch = curl_init($current);
            $headers = [];
            $body = '';
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $opt['method'], CURLOPT_NOBODY => $opt['method'] === 'HEAD', CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => (int) $opt['timeout'], CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => $opt['ua'], CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip], CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                    if (strpos($line, ':') !== false) {
                        [$k, $v] = explode(':', $line, 2);
                        $headers[strtolower(trim($k))] = trim($v);
                    } elseif (preg_match('#^HTTP/#', $line)) {
                        $headers = [];
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, $limit) {
                    $body .= $chunk;
                    return strlen($body) > $limit ? -1 : strlen($chunk);
                },
            ]);
            $ok = curl_exec($ch);
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            $err = curl_error($ch);
            curl_close($ch);
            $hop++;
        } elseif ($scheme === 'https' && $res['ssl_ok'] === null && $ok) {
            $res['ssl_ok'] = true;
        }

        $status = (int) ($info['http_code'] ?? 0);
        if (!$ok && $errno !== 23 && $status === 0) { // 23 = paramos de ler de propósito (limite)
            $res['error'] = s7x_seo_curl_error($errno, $err);
            break;
        }
        $chain[] = ['url' => $current, 'status' => $status];
        if ($hop === 0) {
            $res['dns'] = (int) round(($info['namelookup_time'] ?? 0) * 1000);
            $res['connect'] = (int) round(($info['connect_time'] ?? 0) * 1000);
            $res['tls'] = (int) round(max(0, ($info['appconnect_time'] ?? 0) - ($info['connect_time'] ?? 0)) * 1000);
        }
        $res['ttfb'] = (int) round(($info['starttransfer_time'] ?? 0) * 1000);
        $res['total'] = (int) round(($info['total_time'] ?? 0) * 1000);
        $res['status'] = $status;
        $res['headers'] = $headers;
        $res['url'] = $current;
        $res['http_version'] = (string) ($info['http_version'] ?? '');
        if (in_array($status, [301, 302, 303, 307, 308], true) && !empty($headers['location'])) {
            $next = s7x_seo_abs_url($headers['location'], $current);
            if ($next === '' || $next === $current) {
                break;
            }
            $current = $next;
            continue;
        }
        $res['body'] = $body;
        $res['bytes'] = (int) ($info['size_download'] ?? strlen($body));
        $res['encoding'] = strtolower((string) ($headers['content-encoding'] ?? ''));
        break;
    }
    $res['chain'] = $chain;
    if ($res['status'] >= 300 && $res['status'] < 400 && !$res['error']) {
        $res['error'] = 'O site redirecionou muitas vezes seguidas.';
    }
    return $res;
}

function s7x_seo_curl_error(int $no, string $err): string
{
    switch ($no) {
        case 6:
            return 'Não encontramos esse domínio. Confira se o endereço está certo.';
        case 7:
            return 'O servidor do site recusou a conexão. Ele pode estar fora do ar.';
        case 28:
            return 'O site demorou demais para responder (mais de 20 segundos).';
        case 35:
        case 60:
            return 'O certificado de segurança (SSL) do site tem problema.';
        default:
            return 'Não foi possível acessar o site (' . ($err ?: 'erro ' . $no) . ').';
    }
}

/** Resolve um link relativo contra a página onde ele aparece. */
function s7x_seo_abs_url(string $href, string $base): string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '' || preg_match('#^(mailto|tel|javascript|data|whatsapp|sms):#i', $href)) {
        return '';
    }
    if (preg_match('#^https?://#i', $href)) {
        return preg_replace('/#.*$/', '', $href);
    }
    $b = parse_url($base);
    $origin = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
    if (strpos($href, '//') === 0) {
        return ($b['scheme'] ?? 'https') . ':' . preg_replace('/#.*$/', '', $href);
    }
    if ($href[0] === '#') {
        return '';
    }
    if ($href[0] === '?') {
        return $origin . ($b['path'] ?? '/') . $href;
    }
    if ($href[0] !== '/') {
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
        $href = $dir . $href;
    }
    // resolve ./ e ../
    $parts = [];
    foreach (explode('/', preg_replace('/[?#].*$/', '', $href)) as $seg) {
        if ($seg === '..') {
            array_pop($parts);
        } elseif ($seg !== '.') {
            $parts[] = $seg;
        }
    }
    $q = strpos($href, '?') !== false ? substr($href, strpos($href, '?')) : '';
    return $origin . implode('/', $parts) . preg_replace('/#.*$/', '', $q);
}

/**
 * Confere vários endereços de uma vez (HEAD; cai para GET quando o servidor não aceita HEAD).
 * Retorna [url => ['s' => status, 'b' => bytes (Content-Length, -1 se não informado), 't' => tipo]]. s = 0: sem resposta.
 */
function s7x_seo_check_links(array $urls, int $timeout = 8): array
{
    $out = [];
    $mock = cms_apply_filters('seo_check_links', null, $urls);
    if (is_array($mock)) {
        return $mock;
    }
    $mh = curl_multi_init();
    $handles = [];
    foreach (array_values(array_unique($urls)) as $u) {
        $p = parse_url($u);
        $host = strtolower($p['host'] ?? '');
        $scheme = strtolower($p['scheme'] ?? '');
        $port = (int) ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        try {
            $ip = s7x_seo_resolve($host);
        } catch (Throwable $e) {
            $out[$u] = ['s' => 0, 'b' => -1, 't' => ''];
            continue;
        }
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => S7X_SEO_UA,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip], CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$u] = $ch;
    }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $st === CURLM_OK);
    $retry = [];
    foreach ($handles as $u => $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $out[$u] = ['s' => $code, 'b' => (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD), 't' => strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE))];
        if (in_array($code, [0, 405, 501], true)) {
            $retry[] = $u;
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    // Alguns servidores não aceitam HEAD: confirma com GET curto
    foreach (array_slice($retry, 0, 8) as $u) {
        $r = s7x_seo_fetch($u, ['max_bytes' => 2048, 'timeout' => $timeout, 'redirects' => 0]);
        $out[$u] = ['s' => (int) $r['status'], 'b' => (int) ($r['headers']['content-length'] ?? -1), 't' => strtolower((string) ($r['headers']['content-type'] ?? ''))];
    }
    return $out;
}
