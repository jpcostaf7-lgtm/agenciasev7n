<?php
/**
 * Sev7n Xpress — núcleo
 * CMS leve, sem banco de dados, com construtor de blocos e módulos.
 * Feito para sites hospedados em cPanel (PHP 7.4+ / 8.x).
 *
 * Autor: João Costa
 * Versão: 3.4.0
 */
declare(strict_types=1);

if (defined('CMS_ROOT')) {
    return;
}

define('CMS_VERSION', '3.5.1');
define('CMS_ROOT', dirname(__DIR__));
define('CMS_DATA', CMS_ROOT . '/data');
define('CMS_UPLOADS', CMS_ROOT . '/uploads');

$GLOBALS['cms_config']       = require __DIR__ . '/config.php';
$GLOBALS['cms_content']      = null;
$GLOBALS['cms_current_page'] = null;
$GLOBALS['cms_acting_user']  = null;

require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/content.php';
require __DIR__ . '/lib/seo.php';
require __DIR__ . '/lib/users.php';
require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/ai_tools.php';
require __DIR__ . '/lib/cron.php';

date_default_timezone_set((string) cms_config('timezone', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

// 1. Blocos básicos
require __DIR__ . '/blocks/registry.php';

// 2. Tema ativo (configurações, blocos próprios, páginas iniciais).
//    Se o tema tiver erro, o painel continua abrindo para trocar ou corrigir.
$GLOBALS['cms_theme'] = [];
$GLOBALS['cms_theme_error'] = '';
if (cms_active_theme() !== '') {
    try {
        $__def = (static function (string $__f) {
            return include $__f;
        })(cms_theme_dir() . '/theme.php');
        $GLOBALS['cms_theme'] = is_array($__def) ? $__def : [];
        foreach ((array) (cms_theme()['blocks'] ?? []) as $__type => $__def2) {
            $__def2 += ['group' => 'Tema', 'template' => cms_theme_dir() . '/blocks/' . $__type . '.php'];
            cms_register_block($__type, $__def2);
        }
    } catch (Throwable $__e) {
        $GLOBALS['cms_theme'] = [];
        $GLOBALS['cms_theme_error'] = $__e->getMessage() . ' em ' . str_replace(CMS_ROOT . '/', '', $__e->getFile()) . ':' . $__e->getLine();
    }
    unset($__def, $__def2, $__type, $__e);
}

// 3. Módulos ativos
cms_boot_modules();
cms_do_action('init');
