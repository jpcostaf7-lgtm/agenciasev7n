<?php
/**
 * Sev7n Xpress — retorno do login Google (OAuth)
 * Autor: João Costa
 */
declare(strict_types=1);

define('CMS_ENTRY_DEPTH', 1);
require __DIR__ . '/../cms/admin_lib.php';
cms_session_start();
if (!cms_logged_in() || !cms_is_admin() || !function_exists('s7x_google_callback')) {
    header('Location: ' . cms_url('admin/'));
    exit;
}
s7x_google_callback();
header('Location: ' . cms_url('admin/?p=google'));
