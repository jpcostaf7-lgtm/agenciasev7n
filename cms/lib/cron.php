<?php
/**
 * Sev7n Xpress — tarefas agendadas (artigos automáticos, importações, limpeza)
 * Roda de duas formas: pelo Cron Jobs do cPanel (cron.php) ou, se não houver,
 * depois que uma visita recebe a página (sem atrasar o visitante).
 * Autor: João Costa
 */
declare(strict_types=1);

function cms_cron_state(): array
{
    return cms_read_json(CMS_DATA . '/cron.json', []);
}

/** Chave secreta para chamar cron.php pela web. */
function cms_cron_key(): string
{
    return substr(hash_hmac('sha256', 'cron', cms_secret()), 0, 24);
}

/** Endereço que o cPanel deve chamar. */
function cms_cron_url(): string
{
    return cms_site_url() . '/cron.php?key=' . cms_cron_key();
}

function cms_cron_due(): bool
{
    $interval = max(60, (int) cms_config('cron_interval', 300));
    return (time() - (int) (cms_cron_state()['last_run'] ?? 0)) >= $interval;
}

/** Há tarefas registradas? (sem módulos que usem o cron, nada roda) */
function cms_cron_has_tasks(): bool
{
    return !empty($GLOBALS['cms_hooks']['cron']);
}

/**
 * Executa as tarefas. Uma execução por vez (trava em data/cron.lock).
 * Retorna o resumo gravado em data/cron.json.
 */
function cms_cron_run(string $origin = 'visita'): array
{
    $lockFile = CMS_DATA . '/cron.lock';
    $fp = @fopen($lockFile, 'c');
    if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
        return ['ok' => false, 'error' => 'Outra execução já está em andamento.'];
    }
    $state = cms_cron_state();
    $state['last_run'] = time();
    $state['last_origin'] = $origin;
    cms_write_json(CMS_DATA . '/cron.json', $state);

    @ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(900);
    }
    $started = microtime(true);
    $error = '';
    try {
        cms_do_action('cron', $origin);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        error_log('[Sev7n Xpress] Erro no cron: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    }
    $state = cms_cron_state();
    $state['last_done'] = time();
    $state['last_seconds'] = round(microtime(true) - $started, 1);
    $state['last_error'] = $error;
    if ($origin === 'cpanel') {
        $state['cpanel_seen'] = time();
    }
    cms_write_json(CMS_DATA . '/cron.json', $state);
    flock($fp, LOCK_UN);
    fclose($fp);
    return ['ok' => $error === '', 'error' => $error, 'seconds' => $state['last_seconds']];
}

/** Depois de entregar a página ao visitante, roda as tarefas se estiver na hora. */
function cms_cron_after_response(): void
{
    if (!cms_config('cron_on_visit', true) || PHP_SAPI === 'cli' || !cms_cron_has_tasks() || !cms_cron_due()) {
        return;
    }
    // Se o Cron Jobs do cPanel está ativo (chamou nos últimos 20 min), deixa com ele.
    if (time() - (int) (cms_cron_state()['cpanel_seen'] ?? 0) < 1200) {
        return;
    }
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    } elseif (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        return; // sem como liberar o visitante: só o cron do cPanel roda as tarefas
    }
    cms_cron_run('visita');
}
