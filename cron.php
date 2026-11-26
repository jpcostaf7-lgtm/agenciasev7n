<?php
/**
 * Sev7n Xpress — tarefas agendadas
 * No cPanel (Cron Jobs), a cada 5 ou 10 minutos:
 *   php /home/USUARIO/public_html/cron.php
 * ou, se o servidor não permitir php na linha de comando:
 *   curl -s "https://seudominio.com.br/cron.php?key=CHAVE" > /dev/null
 * A chave aparece no painel (Artigos automáticos → Automação).
 * Autor: João Costa
 */
declare(strict_types=1);
define('CMS_ENTRY_DEPTH', 0);
require __DIR__ . '/cms/core.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
if (PHP_SAPI !== 'cli' && !hash_equals(cms_cron_key(), (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo "Chave inválida.\n";
    exit;
}
$r = cms_cron_run('cpanel');
echo $r['ok'] ? 'OK (' . ($r['seconds'] ?? 0) . " s)\n" : 'Erro: ' . $r['error'] . "\n";
