<?php
/**
 * Sev7n Xpress — funções do painel (autenticação, uploads, formulários)
 * Autor: João Costa
 */
declare(strict_types=1);

require_once __DIR__ . '/core.php';

/* -------------------------------------------------------------------------
 * Sessão, login e CSRF
 * ---------------------------------------------------------------------- */

function cms_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('s7x_sid');
    $params = [
        'lifetime' => 0,
        'path'     => (cms_base_path() ?: '') . '/',
        'secure'   => cms_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params(0, $params['path'] . '; samesite=Lax', '', $params['secure'], true);
    }
    // Sessões ficam dentro de data/ quando possível (evita /tmp compartilhado)
    $sessDir = CMS_DATA . '/sessions';
    if ((is_dir($sessDir) || @mkdir($sessDir, 0700, true)) && is_writable($sessDir)) {
        session_save_path($sessDir);
        // Limpeza ocasional de sessões antigas (alguns servidores não fazem isso sozinhos)
        if (random_int(1, 50) === 1) {
            $limit = time() - (int) cms_config('session_minutes', 240) * 60;
            foreach (glob($sessDir . '/sess_*') ?: [] as $f) {
                if (@filemtime($f) < $limit) {
                    @unlink($f);
                }
            }
        }
    }
    session_start();

    $timeout = (int) cms_config('session_minutes', 240) * 60;
    if (isset($_SESSION['cms_last']) && time() - (int) $_SESSION['cms_last'] > $timeout) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['cms_last'] = time();
}

function cms_needs_setup(): bool
{
    return count(cms_users()) === 0;
}

function cms_logged_in(): bool
{
    $ok = !empty($_SESSION['cms_user']) && isset(cms_users()[$_SESSION['cms_user']]);
    if ($ok) {
        cms_set_acting_user((string) $_SESSION['cms_user']);
    }
    return $ok;
}

function cms_client_key(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0') . '|s7x');
}

function cms_login_locked(): int
{
    $all = cms_read_json(CMS_DATA . '/login-attempts.json', []);
    $rec = $all[cms_client_key()] ?? null;
    if ($rec && !empty($rec['locked_until']) && $rec['locked_until'] > time()) {
        return (int) ceil(($rec['locked_until'] - time()) / 60);
    }
    return 0;
}

function cms_login_attempt(bool $success): void
{
    $file = CMS_DATA . '/login-attempts.json';
    $all  = cms_read_json($file, []);
    $key  = cms_client_key();
    // Limpa registros antigos
    foreach ($all as $k => $r) {
        if (($r['last'] ?? 0) < time() - 86400) {
            unset($all[$k]);
        }
    }
    if ($success) {
        unset($all[$key]);
    } else {
        $rec = $all[$key] ?? ['count' => 0];
        $rec['count'] = (int) $rec['count'] + 1;
        $rec['last'] = time();
        if ($rec['count'] >= (int) cms_config('login_max_attempts', 5)) {
            $rec['locked_until'] = time() + (int) cms_config('login_lock_minutes', 15) * 60;
            $rec['count'] = 0;
        }
        $all[$key] = $rec;
    }
    cms_write_json($file, $all);
}

function cms_login(string $user, string $pass): bool
{
    $users = cms_users();
    $user  = strtolower(trim($user));
    if (!isset($users[$user]) || !password_verify($pass, $users[$user]['hash'])) {
        // Atraso fixo dificulta força bruta
        usleep(400000);
        return false;
    }
    if (password_needs_rehash($users[$user]['hash'], PASSWORD_DEFAULT)) {
        $users[$user]['hash'] = password_hash($pass, PASSWORD_DEFAULT);
    }
    $users[$user]['last_login'] = date('c');
    cms_save_users($users);
    session_regenerate_id(true);
    $_SESSION['cms_user'] = $user;
    $_SESSION['cms_csrf'] = bin2hex(random_bytes(32));
    return true;
}

function cms_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function cms_csrf_token(): string
{
    if (empty($_SESSION['cms_csrf'])) {
        $_SESSION['cms_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cms_csrf'];
}

function cms_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(cms_csrf_token()) . '">';
}

function cms_csrf_ok(): bool
{
    $sent = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return $sent !== '' && hash_equals(cms_csrf_token(), $sent);
}

function cms_password_error(string $pass): string
{
    if (mb_strlen($pass) < 10) {
        return 'A senha precisa ter pelo menos 10 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        return 'Use letras e números na senha.';
    }
    return '';
}

/* -------------------------------------------------------------------------
 * Mensagens rápidas (flash)
 * ---------------------------------------------------------------------- */

function cms_flash(string $type, string $msg): void
{
    $_SESSION['cms_flash'][] = [$type, $msg];
}

function cms_take_flash(): array
{
    $f = $_SESSION['cms_flash'] ?? [];
    unset($_SESSION['cms_flash']);
    return $f;
}

/* -------------------------------------------------------------------------
 * Mídia
 * ---------------------------------------------------------------------- */

function cms_media_meta(): array
{
    return cms_read_json(CMS_DATA . '/media.json', []);
}

function cms_save_media_meta(array $meta): bool
{
    return cms_write_json(CMS_DATA . '/media.json', $meta);
}

/** Caminho relativo seguro dentro de uploads/ ou do tema. */
function cms_safe_rel_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = ltrim($path, '/');
    // Aceita URL completa do próprio site
    $base = ltrim(cms_base_path() . '/', '/');
    if ($base !== '' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    if ($path === '' || strpos($path, '..') !== false || preg_match('#^[a-z]+:#i', $path)) {
        return '';
    }
    if (strpos($path, 'uploads/') !== 0 && !preg_match('#^themes/[a-z0-9][a-z0-9_-]*/assets/#', $path)) {
        return '';
    }
    if (!preg_match('/\.(webp|png|jpe?g|gif|ico)$/i', $path)) {
        return '';
    }
    return $path;
}

function cms_upload_error_text(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'O arquivo é maior que o limite do servidor (' . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_PARTIAL:
            return 'O envio foi interrompido. Tente de novo.';
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo foi escolhido.';
        default:
            return 'O servidor recusou o arquivo (código ' . $code . ').';
    }
}

/**
 * Recebe um arquivo de $_FILES, valida, redimensiona e converte para WebP.
 * Retorna ['ok'=>true,'path'=>'uploads/2026/11/arquivo.webp'] ou ['ok'=>false,'error'=>...]
 */
function cms_handle_upload(array $file, string $alt = ''): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => cms_upload_error_text((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }
    $max = (int) cms_config('max_upload_mb', 8) * 1024 * 1024;
    if ($file['size'] > $max) {
        return ['ok' => false, 'error' => 'Arquivo acima de ' . cms_config('max_upload_mb', 8) . ' MB. Reduza a imagem e envie de novo.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Envio inválido.'];
    }
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $file['tmp_name']);
        finfo_close($fi);
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = $mime ?: (string) ($info['mime'] ?? '');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
    if (!isset($allowed[$mime]) || (!$info && $allowed[$mime] !== 'ico')) {
        return ['ok' => false, 'error' => 'Formato não aceito. Envie JPG, PNG, WebP ou GIF.'];
    }
    $ext  = $allowed[$mime];
    $name = cms_slug(pathinfo((string) $file['name'], PATHINFO_FILENAME)) ?: 'imagem';
    $name = substr($name, 0, 60) . '-' . bin2hex(random_bytes(3));
    $sub  = date('Y') . '/' . date('m');
    $dir  = CMS_UPLOADS . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Não foi possível criar a pasta uploads/' . $sub . '. Verifique as permissões (755).'];
    }

    $saved = false;
    $finalExt = $ext;
    $canGd = function_exists('imagecreatetruecolor') && in_array($ext, ['jpg', 'png', 'webp'], true);
    if ($canGd) {
        $src = null;
        if ($ext === 'jpg' && function_exists('imagecreatefromjpeg')) {
            $src = @imagecreatefromjpeg($file['tmp_name']);
        } elseif ($ext === 'png' && function_exists('imagecreatefrompng')) {
            $src = @imagecreatefrompng($file['tmp_name']);
        } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($file['tmp_name']);
        }
        if ($src) {
            // Corrige rotação de fotos de celular
            if ($ext === 'jpg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($file['tmp_name']);
                $o = (int) ($exif['Orientation'] ?? 1);
                if ($o === 3) { $src = imagerotate($src, 180, 0); }
                if ($o === 6) { $src = imagerotate($src, -90, 0); }
                if ($o === 8) { $src = imagerotate($src, 90, 0); }
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $maxW = (int) cms_config('image_max_width', 2000);
            if ($w > $maxW) {
                $nh  = (int) round($h * $maxW / $w);
                $dst = imagecreatetruecolor($maxW, $nh);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
                imagedestroy($src);
                $src = $dst;
            }
            imagealphablending($src, false);
            imagesavealpha($src, true);
            $toWebp = cms_config('convert_webp', true) && function_exists('imagewebp');
            if ($toWebp) {
                $finalExt = 'webp';
                $saved = @imagewebp($src, $dir . '/' . $name . '.webp', (int) cms_config('webp_quality', 82));
            } elseif ($ext === 'jpg') {
                $saved = @imagejpeg($src, $dir . '/' . $name . '.jpg', 85);
            } elseif ($ext === 'png') {
                $saved = @imagepng($src, $dir . '/' . $name . '.png', 8);
            } else {
                $saved = @imagewebp($src, $dir . '/' . $name . '.webp', 85);
            }
            imagedestroy($src);
        }
    }
    if (!$saved) {
        $finalExt = $ext;
        $saved = move_uploaded_file($file['tmp_name'], $dir . '/' . $name . '.' . $ext);
    }
    if (!$saved) {
        return ['ok' => false, 'error' => 'Não foi possível gravar o arquivo. Verifique as permissões da pasta uploads/.'];
    }
    $rel = 'uploads/' . $sub . '/' . $name . '.' . $finalExt;
    @chmod(CMS_ROOT . '/' . $rel, 0644);

    $meta = cms_media_meta();
    $size = @getimagesize(CMS_ROOT . '/' . $rel);
    $meta[$rel] = [
        'alt'  => trim(strip_tags($alt)),
        'name' => (string) $file['name'],
        'w'    => $size[0] ?? 0,
        'h'    => $size[1] ?? 0,
        'size' => (int) filesize(CMS_ROOT . '/' . $rel),
        'date' => date('c'),
    ];
    cms_save_media_meta($meta);
    return ['ok' => true, 'path' => $rel];
}

/** Lista imagens enviadas + imagens do tema. */
function cms_media_list(): array
{
    $meta  = cms_media_meta();
    $items = [];
    $scan  = function (string $dir, string $rel, bool $theme) use (&$scan, &$items, $meta) {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f[0] === '.') {
                continue;
            }
            $full = $dir . '/' . $f;
            if (is_dir($full)) {
                $scan($full, $rel . '/' . $f, $theme);
            } elseif (preg_match('/\.(webp|png|jpe?g|gif|ico)$/i', $f)) {
                $path = ltrim($rel . '/' . $f, '/');
                $m = $meta[$path] ?? [];
                $size = $m['w'] ?? null ? [$m['w'], $m['h']] : (@getimagesize($full) ?: [0, 0]);
                $items[] = [
                    'path'  => $path,
                    'url'   => cms_url($path),
                    'alt'   => $m['alt'] ?? '',
                    'name'  => $m['name'] ?? $f,
                    'w'     => (int) $size[0],
                    'h'     => (int) $size[1],
                    'size'  => (int) filesize($full),
                    'date'  => $m['date'] ?? date('c', (int) filemtime($full)),
                    'theme' => $theme,
                ];
            }
        }
    };
    $scan(CMS_UPLOADS, 'uploads', false);
    if (cms_active_theme() !== '') {
        $scan(cms_theme_dir() . '/assets/img', cms_theme_rel() . '/assets/img', true);
    }
    usort($items, function ($a, $b) {
        if ($a['theme'] !== $b['theme']) {
            return $a['theme'] ? 1 : -1;
        }
        return strcmp($b['date'], $a['date']);
    });
    return $items;
}

/** Lista onde uma imagem está em uso (para avisar antes de excluir). */
function cms_media_usage(string $path): array
{
    $used = [];
    $content = cms_content();
    $has = function ($v) use ($path) {
        $j = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $j !== false && strpos($j, $path) !== false;
    };
    foreach (cms_settings_sections() as $sid => $sec) {
        if ($has($content['settings'][$sid] ?? ($sec['fields'] ?? []))) {
            $used[] = $sec['label'];
        }
    }
    foreach ((array) $content['pages'] as $pg) {
        $blocks = array_map('cms_block_data', (array) ($pg['blocks'] ?? []));
        if ($has($blocks) || $has($pg['seo'] ?? [])) {
            $used[] = $pg['title'];
        }
    }
    if ($has($content['seo']) || $has(cms_seo('og_image')) || $has(cms_seo('favicon'))) {
        $used[] = 'SEO';
    }
    return array_values(array_unique($used));
}

function cms_format_bytes(int $b): string
{
    if ($b >= 1048576) {
        return number_format($b / 1048576, 1, ',', '.') . ' MB';
    }
    return max(1, (int) round($b / 1024)) . ' KB';
}

/* -------------------------------------------------------------------------
 * Limpeza dos dados enviados pelos formulários
 * ---------------------------------------------------------------------- */

function cms_clean_text(string $s): string
{
    $s = str_replace("\r\n", "\n", $s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return trim(strip_tags($s));
}

function cms_clean_url(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    if (preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $s)) {
        return filter_var($s, FILTER_SANITIZE_URL) ?: '';
    }
    if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}#i', $s)) {
        return 'https://' . $s;
    }
    return '';
}

function cms_clean_value(array $def, $raw)
{
    $type = $def['type'] ?? 'text';
    switch ($type) {
        case 'toggle':
            return !empty($raw) && $raw !== '0';
        case 'number':
            return is_numeric($raw) ? $raw + 0 : ($def['default'] ?? 0);
        case 'richtext':
            return cms_sanitize_html((string) $raw);
        case 'url':
            return cms_clean_url((string) $raw);
        case 'email':
            $v = trim((string) $raw);
            return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
        case 'color':
            $v = trim((string) $raw);
            return preg_match('/^#[0-9a-f]{3,8}$/i', $v) ? $v : ($def['default'] ?? '');
        case 'select':
        case 'icon':
            $opts = $type === 'icon' ? cms_icons() : ($def['options'] ?? []);
            return array_key_exists((string) $raw, $opts) ? (string) $raw : ($def['default'] ?? '');
        case 'code':
            return str_replace("\r\n", "\n", (string) $raw);
        case 'image':
            $raw = is_array($raw) ? $raw : ['src' => (string) $raw];
            return [
                'src' => cms_safe_rel_path((string) ($raw['src'] ?? '')),
                'alt' => cms_clean_text((string) ($raw['alt'] ?? '')),
            ];
        case 'repeater':
            $items = [];
            foreach ((array) $raw as $key => $item) {
                if ($key === '__INDEX__' || !is_array($item)) {
                    continue;
                }
                $clean = [];
                foreach ($def['fields'] as $fid => $fdef) {
                    $clean[$fid] = cms_clean_value($fdef, $item[$fid] ?? ($fdef['type'] === 'toggle' ? false : ''));
                }
                $items[] = $clean;
            }
            return $items;
        case 'textarea':
        case 'text':
        case 'tel':
        default:
            return cms_clean_text((string) $raw);
    }
}

/* -------------------------------------------------------------------------
 * Renderização dos campos no painel
 * ---------------------------------------------------------------------- */

function cms_field_id(string $name): string
{
    return 'f_' . trim((string) preg_replace('/[^a-z0-9_]+/i', '_', $name), '_');
}

function cms_render_field(string $name, array $def, $value): string
{
    $type  = $def['type'] ?? 'text';
    $id    = cms_field_id($name);
    $label = e($def['label'] ?? '');
    $help  = !empty($def['help']) ? '<p class="help">' . e($def['help']) . '</p>' : '';
    $max   = !empty($def['max']) ? ' data-max="' . (int) $def['max'] . '"' : '';
    $ph    = !empty($def['placeholder']) ? ' placeholder="' . e($def['placeholder']) . '"' : '';
    $wide  = !empty($def['wide']) || in_array($type, ['richtext', 'repeater', 'code'], true) ? ' field--wide' : '';
    $html  = '<div class="field field--' . e($type) . $wide . '">';

    switch ($type) {
        case 'toggle':
            $html .= '<label class="toggle"><input type="hidden" name="' . e($name) . '" value="0">'
                . '<input type="checkbox" id="' . $id . '" name="' . e($name) . '" value="1"' . ($value ? ' checked' : '') . '>'
                . '<span class="toggle__track" aria-hidden="true"></span><span>' . $label . '</span></label>' . $help;
            break;

        case 'textarea':
        case 'code':
            $rows = (int) ($def['rows'] ?? 4);
            $html .= '<label for="' . $id . '">' . $label . '</label>'
                . '<textarea id="' . $id . '" name="' . e($name) . '" rows="' . $rows . '"' . $max . $ph
                . ($type === 'code' ? ' class="code" spellcheck="false"' : '') . '>' . e($value) . '</textarea>' . $help;
            break;

        case 'richtext':
            $html .= '<label id="' . $id . '_l">' . $label . '</label>'
                . '<div class="rte" data-rte>'
                . '<div class="rte__bar" role="toolbar" aria-label="Formatação">'
                . '<button type="button" data-cmd="bold" title="Negrito (Ctrl+B)"><b>N</b></button>'
                . '<button type="button" data-cmd="italic" title="Itálico (Ctrl+I)"><i>I</i></button>'
                . '<button type="button" data-cmd="insertUnorderedList" title="Lista">• Lista</button>'
                . '<button type="button" data-cmd="insertOrderedList" title="Lista numerada">1. Lista</button>'
                . '<button type="button" data-cmd="createLink" title="Inserir link">Link</button>'
                . '<button type="button" data-cmd="unlink" title="Remover link">Sem link</button>'
                . '<button type="button" data-cmd="removeFormat" title="Limpar formatação">Limpar</button>'
                . '</div>'
                . '<div class="rte__area" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="' . $id . '_l">' . cms_sanitize_html((string) $value) . '</div>'
                . '<textarea name="' . e($name) . '" hidden>' . e(cms_sanitize_html((string) $value)) . '</textarea>'
                . '</div>' . $help;
            break;

        case 'image':
            $v   = cms_image_value($value);
            $src = $v['src'] ? cms_url($v['src']) : '';
            $html .= '<span class="label">' . $label . '</span>'
                . '<div class="imgfield" data-imgfield>'
                . '<div class="imgfield__preview">' . ($src ? '<img src="' . e($src) . '" alt="">' : '<span>Sem imagem</span>') . '</div>'
                . '<div class="imgfield__side">'
                . '<input type="hidden" name="' . e($name) . '[src]" value="' . e($v['src']) . '" data-src>'
                . '<div class="imgfield__actions">'
                . '<button type="button" class="btn btn--small" data-pick>Escolher imagem</button>'
                . '<button type="button" class="btn btn--small btn--ghost" data-clear' . ($src ? '' : ' hidden') . '>Remover</button>'
                . '</div>'
                . '<label for="' . $id . '_alt" class="sublabel">Texto alternativo (alt) — descreva a imagem para Google e leitores de tela</label>'
                . '<input type="text" id="' . $id . '_alt" name="' . e($name) . '[alt]" value="' . e($v['alt']) . '" data-alt>'
                . '</div></div>' . $help;
            break;

        case 'select':
        case 'icon':
            $opts = $type === 'icon' ? array_combine(array_keys(cms_icons()), array_map('ucfirst', array_keys(cms_icons()))) : ($def['options'] ?? []);
            $html .= '<label for="' . $id . '">' . $label . '</label>';
            if ($type === 'icon') {
                $html .= '<div class="iconpick">' . '<span class="iconpick__preview" data-icon-preview>' . cms_icon((string) $value ?: 'folha') . '</span>';
            }
            $html .= '<select id="' . $id . '" name="' . e($name) . '"' . ($type === 'icon' ? ' data-icon-select' : '') . '>';
            foreach ($opts as $k => $lab) {
                $html .= '<option value="' . e($k) . '"' . ((string) $value === (string) $k ? ' selected' : '') . '>' . e($lab) . '</option>';
            }
            $html .= '</select>' . ($type === 'icon' ? '</div>' : '') . $help;
            break;

        case 'color':
            $html .= '<label for="' . $id . '">' . $label . '</label>'
                . '<div class="colorfield"><input type="color" id="' . $id . '" value="' . e($value ?: '#000000') . '" data-color>'
                . '<input type="text" name="' . e($name) . '" value="' . e($value) . '" data-color-text maxlength="9"></div>' . $help;
            break;

        case 'repeater':
            $items = is_array($value) ? array_values($value) : [];
            $itemLabel = $def['item_label'] ?? '';
            $html .= '<div class="repeater" data-repeater data-name="' . e($name) . '" data-item-label="' . e($itemLabel) . '"'
                . (isset($def['max_items']) ? ' data-max-items="' . (int) $def['max_items'] . '"' : '') . '>'
                . '<div class="repeater__head"><span class="label">' . $label . '</span><span class="repeater__count" data-count>' . count($items) . ' itens</span></div>'
                . $help
                . '<ol class="repeater__list" data-list>';
            foreach ($items as $i => $item) {
                $html .= cms_render_repeater_item($name, $def, (string) $i, (array) $item);
            }
            $html .= '</ol>'
                . '<template data-template>' . cms_render_repeater_item($name, $def, '__INDEX__', []) . '</template>'
                . '<button type="button" class="btn btn--small" data-add>+ Adicionar ' . e(mb_strtolower($def['add_label'] ?? 'item')) . '</button>'
                . '</div>';
            break;

        case 'number':
            $html .= '<label for="' . $id . '">' . $label . '</label>'
                . '<input type="number" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"' . $ph . '>' . $help;
            break;

        default: // text, url, email, tel
            $inputType = in_array($type, ['url', 'email', 'tel'], true) ? $type : 'text';
            if ($inputType === 'url') {
                $inputType = 'text'; // aceita "#secao" e "tel:"
            }
            $html .= '<label for="' . $id . '">' . $label . '</label>'
                . '<input type="' . $inputType . '" id="' . $id . '" name="' . e($name) . '" value="' . e($value) . '"' . $max . $ph . '>' . $help;
    }
    return $html . '</div>';
}

function cms_render_repeater_item(string $name, array $def, string $index, array $item): string
{
    $first = $def['item_label'] ?? array_key_first($def['fields']);
    $title = trim(strip_tags((string) ($item[$first] ?? '')));
    $html  = '<li class="repeater__item" data-item>'
        . '<div class="repeater__bar">'
        . '<button type="button" class="draghandle" data-drag-handle title="Arraste para mudar a ordem" aria-label="Mudar a ordem: arraste ou use as setas do teclado">'
        . '<svg viewBox="0 0 12 18" width="12" height="18" aria-hidden="true"><g fill="currentColor"><circle cx="3" cy="3" r="1.6"/><circle cx="9" cy="3" r="1.6"/><circle cx="3" cy="9" r="1.6"/><circle cx="9" cy="9" r="1.6"/><circle cx="3" cy="15" r="1.6"/><circle cx="9" cy="15" r="1.6"/></g></svg></button>'
        . '<button type="button" class="repeater__toggle" data-toggle aria-expanded="' . ($index === '__INDEX__' ? 'true' : 'false') . '">'
        . '<span class="repeater__title" data-title>' . e($title !== '' ? $title : 'Novo item') . '</span></button>'
        . '<span class="repeater__tools">'
        . '<button type="button" data-up title="Mover para cima" aria-label="Mover para cima">↑</button>'
        . '<button type="button" data-down title="Mover para baixo" aria-label="Mover para baixo">↓</button>'
        . '<button type="button" data-remove title="Excluir item" aria-label="Excluir item" class="danger">Excluir</button>'
        . '</span></div>'
        . '<div class="repeater__body"' . ($index === '__INDEX__' ? '' : ' hidden') . '><div class="grid">';
    foreach ($def['fields'] as $fid => $fdef) {
        $val = array_key_exists($fid, $item) ? $item[$fid] : ($fdef['default'] ?? ($fdef['type'] === 'toggle' ? false : ''));
        $html .= cms_render_field($name . '[' . $index . '][' . $fid . ']', $fdef, $val);
    }
    return $html . '</div></div></li>';
}

/** Valor atual de uma configuração global (salvo ou padrão). */
function cms_settings_value(string $section, string $field, array $def)
{
    $content = cms_content();
    if (isset($content['settings'][$section]) && array_key_exists($field, $content['settings'][$section])) {
        return $content['settings'][$section][$field];
    }
    return cms_field_default($def);
}

/* -------------------------------------------------------------------------
 * Editor de blocos
 * ---------------------------------------------------------------------- */

/** Card de um bloco no editor de página. $bid = id do bloco (ou __BID__ no modelo). */
function cms_render_block_editor(string $bid, array $block, bool $open = false): string
{
    $type = (string) ($block['type'] ?? '');
    $def  = cms_block_type($type);
    $base = 'blocks[' . $bid . ']';
    $enabled = !array_key_exists('enabled', $block) || !empty($block['enabled']);
    $summary = $def ? cms_block_summary($block) : '';

    $html  = '<li class="blockitem' . ($def ? '' : ' blockitem--locked') . ($enabled ? '' : ' is-hidden') . '" data-block data-type="' . e($type) . '">';
    $html .= '<div class="blockitem__bar">';
    $html .= '<button type="button" class="draghandle" data-drag-handle title="Arraste para mudar a ordem" aria-label="Mudar a ordem: arraste ou use as setas do teclado">'
        . '<svg viewBox="0 0 12 18" width="12" height="18" aria-hidden="true"><g fill="currentColor"><circle cx="3" cy="3" r="1.6"/><circle cx="9" cy="3" r="1.6"/><circle cx="3" cy="9" r="1.6"/><circle cx="9" cy="9" r="1.6"/><circle cx="3" cy="15" r="1.6"/><circle cx="9" cy="15" r="1.6"/></g></svg></button>';
    $html .= '<button type="button" class="blockitem__toggle" data-block-toggle aria-expanded="' . ($open ? 'true' : 'false') . '">'
        . cms_icon($def['icon'] ?? 'folha')
        . '<span class="blockitem__name"><strong>' . e($def['label'] ?? $type) . '</strong>'
        . '<small data-block-summary>' . e($summary !== '' ? $summary : ($def ? ($def['description'] ?? '') : 'Bloco de um módulo desativado. Ative o módulo para editar.')) . '</small></span></button>';
    $html .= '<label class="minitoggle" title="Mostrar este bloco no site"><input type="hidden" name="' . e($base) . '[enabled]" value="0">'
        . '<input type="checkbox" name="' . e($base) . '[enabled]" value="1"' . ($enabled ? ' checked' : '') . ' data-block-visible><span class="minitoggle__track" aria-hidden="true"></span><span class="minitoggle__label">' . ($enabled ? 'Visível' : 'Oculto') . '</span></label>';
    $html .= '<span class="blockitem__tools">'
        . '<button type="button" data-block-up title="Mover para cima" aria-label="Mover para cima">↑</button>'
        . '<button type="button" data-block-down title="Mover para baixo" aria-label="Mover para baixo">↓</button>'
        . ($def ? '<button type="button" data-block-dup title="Duplicar bloco">Duplicar</button>' : '')
        . '<button type="button" data-block-remove class="danger" title="Excluir bloco">Excluir</button>'
        . '</span></div>';
    $html .= '<div class="blockitem__body"' . ($open ? '' : ' hidden') . '>';
    $html .= '<input type="hidden" name="' . e($base) . '[type]" value="' . e($type) . '">';
    if ($def) {
        $data = cms_block_data($block);
        $html .= '<div class="grid">';
        $html .= cms_render_field($base . '[anchor]', ['type' => 'text', 'label' => 'ID da seção (âncora)', 'help' => 'Opcional. Permite links do menu como #' . (trim((string) ($block['anchor'] ?? '')) ?: 'contato') . '.', 'placeholder' => 'ex.: contato'], (string) ($block['anchor'] ?? ''));
        $html .= '<div class="field"></div>';
        foreach ($def['fields'] as $fid => $fdef) {
            $html .= cms_render_field($base . '[data][' . $fid . ']', $fdef, $data[$fid] ?? cms_field_default($fdef));
        }
        $html .= '</div>';
    } else {
        $html .= '<input type="hidden" name="' . e($base) . '[anchor]" value="' . e($block['anchor'] ?? '') . '">';
        $html .= '<p class="muted">O conteúdo deste bloco está guardado e volta a aparecer quando o módulo for ativado.</p>';
    }
    return $html . '</div></li>';
}

/** Lê os blocos enviados pelo editor, na ordem da tela. */
function cms_collect_blocks(array $posted, array $existing): array
{
    $old = [];
    foreach ($existing as $bl) {
        $old[$bl['id'] ?? ''] = $bl;
    }
    $out = [];
    foreach ($posted as $bid => $raw) {
        if (!is_array($raw) || $bid === '__BID__') {
            continue;
        }
        $bid  = preg_match('/^[a-z0-9]{4,20}$/', (string) $bid) ? (string) $bid : 'b' . bin2hex(random_bytes(4));
        $type = preg_replace('/[^a-z0-9_-]/', '', (string) ($raw['type'] ?? ''));
        $def  = cms_block_type($type);
        $block = [
            'id'      => $bid,
            'type'    => $type,
            'enabled' => !empty($raw['enabled']) && $raw['enabled'] !== '0',
            'anchor'  => cms_slug((string) ($raw['anchor'] ?? '')),
            'data'    => [],
        ];
        if ($def) {
            foreach ($def['fields'] as $fid => $fdef) {
                $v = $raw['data'][$fid] ?? (($fdef['type'] ?? '') === 'repeater' ? [] : (($fdef['type'] ?? '') === 'toggle' ? false : ''));
                $block['data'][$fid] = cms_clean_value($fdef, $v);
            }
        } elseif (isset($old[$bid])) {
            $block['data'] = $old[$bid]['data'] ?? []; // módulo desativado: preserva os dados
        } else {
            continue;
        }
        $out[] = $block;
    }
    return $out;
}

/** Formulário genérico de configurações de módulo. */
function cms_module_settings_save(string $slug): bool
{
    $mod = cms_available_modules()[$slug] ?? null;
    if (!$mod) {
        return false;
    }
    $content = cms_content();
    $input   = $_POST['set'] ?? [];
    foreach ($mod['settings'] as $fid => $def) {
        $content['modules'][$slug][$fid] = cms_clean_value($def, $input[$fid] ?? (($def['type'] ?? '') === 'toggle' ? false : ''));
    }
    return cms_save_content($content, 'Módulo ' . $mod['name']);
}

/* -------------------------------------------------------------------------
 * Análise de SEO simples (estilo Yoast)
 * ---------------------------------------------------------------------- */

/** Texto do H1 de uma página (primeiro campo marcado com 'h1' => true). */
function cms_page_h1(array $page): string
{
    foreach ((array) ($page['blocks'] ?? []) as $bl) {
        $def = cms_block_type((string) ($bl['type'] ?? ''));
        if (!$def || empty($bl['enabled'])) {
            continue;
        }
        $data = cms_block_data($bl);
        foreach ($def['fields'] as $fid => $f) {
            if (!empty($f['h1'])) {
                return trim(strip_tags((string) ($data[$fid] ?? '')));
            }
        }
    }
    return '';
}

function cms_seo_checks(): array
{
    $title = trim((string) cms_seo('title'));
    $desc  = trim((string) cms_seo('description'));
    $kw    = mb_strtolower(trim((string) cms_seo('focus_keyword')));
    $tl    = mb_strlen($title);
    $dl    = mb_strlen($desc);
    $og    = cms_image_value(cms_seo('og_image'));
    $checks = [];

    $checks[] = ($tl >= 30 && $tl <= 60)
        ? ['ok', "Título com $tl caracteres."]
        : ['warn', $tl === 0 ? 'Defina um título SEO.' : "Título com $tl caracteres. O ideal é entre 30 e 60."];
    $checks[] = ($dl >= 120 && $dl <= 160)
        ? ['ok', "Meta descrição com $dl caracteres."]
        : ['warn', $dl === 0 ? 'Escreva uma meta descrição.' : "Meta descrição com $dl caracteres. O ideal é entre 120 e 160."];
    if ($kw !== '') {
        $checks[] = mb_strpos(mb_strtolower($title), $kw) !== false
            ? ['ok', 'A palavra-chave aparece no título.'] : ['warn', 'Inclua a palavra-chave foco no título.'];
        $checks[] = mb_strpos(mb_strtolower($desc), $kw) !== false
            ? ['ok', 'A palavra-chave aparece na meta descrição.'] : ['warn', 'Inclua a palavra-chave foco na meta descrição.'];
        $h1 = mb_strtolower(cms_page_h1(cms_home_page()));
        if ($h1 !== '') {
            $checks[] = mb_strpos($h1, $kw) !== false
                ? ['ok', 'A palavra-chave aparece no título principal (H1) da página inicial.'] : ['warn', 'Use a palavra-chave foco no título principal (H1) da página inicial.'];
        }
    } else {
        $checks[] = ['info', 'Defina uma palavra-chave foco para ver mais sugestões.'];
    }
    $checks[] = $og['src'] ? ['ok', 'Imagem de compartilhamento definida.'] : ['warn', 'Defina uma imagem de compartilhamento (1200 × 630).'];
    $checks[] = cms_seo('index', true) ? ['ok', 'Site liberado para o Google.'] : ['bad', 'O site está bloqueado para buscadores (noindex).'];

    // Imagens sem alt
    $missing = 0;
    $walk = function ($val, $def) use (&$walk, &$missing) {
        if (($def['type'] ?? '') === 'image') {
            $v = cms_image_value($val);
            if ($v['src'] !== '' && trim($v['alt']) === '' && empty($def['decorative'])) {
                $missing++;
            }
        } elseif (($def['type'] ?? '') === 'repeater') {
            foreach ((array) $val as $item) {
                foreach ($def['fields'] as $fid => $fd) {
                    $walk($item[$fid] ?? null, $fd);
                }
            }
        }
    };
    foreach (cms_pages() as $pg) {
        foreach ((array) ($pg['blocks'] ?? []) as $bl) {
            $def = cms_block_type((string) ($bl['type'] ?? ''));
            if (!$def || empty($bl['enabled'])) {
                continue;
            }
            $data = cms_block_data($bl);
            foreach ($def['fields'] as $fid => $fd) {
                $walk($data[$fid] ?? null, $fd);
            }
        }
    }
    $checks[] = $missing === 0 ? ['ok', 'Todas as imagens têm texto alternativo.'] : ['warn', "$missing imagem(ns) sem texto alternativo (alt)."];
    return $checks;
}

/* -------------------------------------------------------------------------
 * Temas: instalar por ZIP, ativar, excluir
 * ---------------------------------------------------------------------- */

/** Apaga uma pasta inteira. */
function cms_rrmdir(string $dir): void
{
    if (!is_dir($dir) || is_link($dir)) {
        @unlink($dir);
        return;
    }
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $p = $dir . '/' . $f;
        is_dir($p) && !is_link($p) ? cms_rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

function cms_theme_screenshot(string $slug): string
{
    foreach (['webp', 'png', 'jpg', 'jpeg'] as $ext) {
        $f = 'themes/' . $slug . '/screenshot.' . $ext;
        if (is_file(CMS_ROOT . '/' . $f)) {
            return cms_url($f) . '?v=' . filemtime(CMS_ROOT . '/' . $f);
        }
    }
    return '';
}

/**
 * Instala (ou atualiza) um tema a partir de um ZIP enviado.
 * O ZIP pode ter theme.php na raiz ou dentro de uma única pasta.
 */
function cms_theme_install_zip(array $file, bool $allowUpdate): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'O PHP deste servidor não tem a extensão zip. Ative "zip" em Select PHP Version no cPanel, ou envie a pasta do tema por FTP para themes/.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => cms_upload_error_text((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE))];
    }
    if ($file['size'] > (int) cms_config('max_theme_mb', 40) * 1048576) {
        return ['ok' => false, 'error' => 'O ZIP passa de ' . (int) cms_config('max_theme_mb', 40) . ' MB.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        return ['ok' => false, 'error' => 'Não foi possível abrir o arquivo. Envie um .zip válido.'];
    }

    $allowed = ['php', 'css', 'js', 'mjs', 'json', 'map', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'txt', 'md', 'mp4', 'webm'];
    $names = [];
    $total = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $st = $zip->statIndex($i);
        $name = str_replace('\\', '/', (string) $st['name']);
        if (preg_match('#(^|/)(__MACOSX|\.DS_Store|Thumbs\.db)(/|$)#', $name)) {
            continue;
        }
        if ($name === '' || $name[0] === '/' || strpos($name, '..') !== false || strpos($name, ':') !== false) {
            $zip->close();
            return ['ok' => false, 'error' => 'O ZIP tem um caminho inválido: ' . $name];
        }
        // Links simbólicos não são aceitos
        if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
            $zip->close();
            return ['ok' => false, 'error' => 'O ZIP tem um link simbólico (' . $name . '). Remova e envie de novo.'];
        }
        if (substr($name, -1) === '/') {
            continue;
        }
        $base = basename($name);
        $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if ($base[0] === '.' || !in_array($ext, $allowed, true)) {
            $zip->close();
            return ['ok' => false, 'error' => 'Arquivo não permitido em tema: ' . $name . '. Tipos aceitos: ' . implode(', ', $allowed) . '.'];
        }
        $total += (int) $st['size'];
        $names[$i] = $name;
    }
    if (!$names || count($names) > 3000 || $total > 250 * 1048576) {
        $zip->close();
        return ['ok' => false, 'error' => $names ? 'O tema é grande demais (máx. 3000 arquivos / 250 MB descompactado).' : 'O ZIP está vazio.'];
    }

    // Descobre onde está o theme.php (raiz ou uma pasta)
    $prefix = null;
    foreach ($names as $n) {
        if ($n === 'theme.php') {
            $prefix = '';
            break;
        }
        if (preg_match('#^([^/]+)/theme\.php$#', $n, $m)) {
            $prefix = $m[1] . '/';
        }
    }
    if ($prefix === null) {
        $zip->close();
        return ['ok' => false, 'error' => 'O ZIP não tem o arquivo theme.php (na raiz ou dentro de uma pasta). Veja o guia COMO-CRIAR-UM-TEMA.md.'];
    }
    $slug = cms_slug($prefix !== '' ? rtrim($prefix, '/') : pathinfo((string) $file['name'], PATHINFO_FILENAME));
    $slug = preg_replace('/^tema-/', '', $slug) ?: 'tema';
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,60}$/', $slug)) {
        $zip->close();
        return ['ok' => false, 'error' => 'Nome de pasta inválido para o tema.'];
    }

    $themesDir = CMS_ROOT . '/themes';
    if (!is_dir($themesDir) && !@mkdir($themesDir, 0755, true)) {
        $zip->close();
        return ['ok' => false, 'error' => 'Não foi possível criar a pasta themes/. Verifique as permissões.'];
    }
    if (!is_writable($themesDir)) {
        $zip->close();
        return ['ok' => false, 'error' => 'A pasta themes/ precisa de permissão de escrita (755 ou 775).'];
    }
    $target = $themesDir . '/' . $slug;
    $exists = is_dir($target);
    if ($exists && !$allowUpdate) {
        $zip->close();
        return ['ok' => false, 'error' => 'Já existe um tema na pasta themes/' . $slug . '. Marque “Atualizar se já existir” para substituir.'];
    }

    // Extrai para uma pasta temporária
    $tmp = $themesDir . '/.tmp-' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0755, true);
    foreach ($names as $i => $n) {
        if ($prefix !== '' && strpos($n, $prefix) !== 0) {
            continue; // arquivos fora da pasta do tema são ignorados
        }
        $rel  = substr($n, strlen($prefix));
        $dest = $tmp . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            @mkdir(dirname($dest), 0755, true);
        }
        $in = $zip->getStream($n);
        if (!$in) {
            continue;
        }
        $out = fopen($dest, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        fclose($in);
        @chmod($dest, 0644);
    }
    $zip->close();

    // Confere o tema antes de instalar
    $GLOBALS['__s7x_tmp_theme'] = $tmp;
    try {
        $def = (static function (string $__f) {
            return include $__f;
        })($tmp . '/theme.php');
    } catch (Throwable $e) {
        cms_rrmdir($tmp);
        return ['ok' => false, 'error' => 'O theme.php tem um erro: ' . $e->getMessage() . ' (linha ' . $e->getLine() . '). Nada foi instalado.'];
    }
    if (!is_array($def) || empty($def['name'])) {
        cms_rrmdir($tmp);
        return ['ok' => false, 'error' => 'O theme.php precisa retornar um array com pelo menos "name". Nada foi instalado.'];
    }
    if (!is_file($tmp . '/layout.php')) {
        cms_rrmdir($tmp);
        return ['ok' => false, 'error' => 'O tema não tem o arquivo layout.php. Nada foi instalado.'];
    }

    // Guarda a versão anterior (atualização) em data/theme-backups
    if ($exists) {
        $bk = CMS_DATA . '/theme-backups';
        @mkdir($bk, 0755, true);
        @rename($target, $bk . '/' . $slug . '-' . date('Ymd-His'));
        $old = glob($bk . '/' . $slug . '-*', GLOB_ONLYDIR) ?: [];
        rsort($old);
        foreach (array_slice($old, 3) as $o) {
            cms_rrmdir($o);
        }
    }
    if (!@rename($tmp, $target)) {
        cms_rrmdir($tmp);
        return ['ok' => false, 'error' => 'Não foi possível mover o tema para themes/' . $slug . '.'];
    }
    return ['ok' => true, 'slug' => $slug, 'name' => (string) $def['name'], 'updated' => $exists];
}

/** Ativa um tema. Opções: usar páginas/configurações de demonstração e módulos recomendados. */
function cms_theme_activate(string $slug, bool $demoPages, bool $demoSettings, bool $modules): array
{
    if (!in_array($slug, cms_theme_slugs(), true)) {
        return ['ok' => false, 'error' => 'Tema não encontrado.'];
    }
    $def = cms_theme_manifest($slug);
    if (!$def || !empty($def['error'])) {
        return ['ok' => false, 'error' => 'O tema tem um erro e não foi ativado: ' . ($def['error'] ?? 'theme.php inválido')];
    }
    if (!is_file(CMS_ROOT . '/themes/' . $slug . '/layout.php')) {
        return ['ok' => false, 'error' => 'O tema não tem layout.php.'];
    }
    $raw = cms_read_json(CMS_DATA . '/content.json', null);
    if (is_array($raw) && ($demoPages || $demoSettings || $modules)) {
        $prev = $GLOBALS['cms_theme'];
        $GLOBALS['cms_theme'] = $def;
        if ($demoPages) {
            unset($raw['pages']); // volta a seguir as páginas iniciais do tema
        }
        if ($demoSettings) {
            $raw['settings'] = [];
        }
        if ($modules) {
            $raw['active_modules'] = array_values(array_unique(array_merge((array) ($raw['active_modules'] ?? []), (array) ($def['default_modules'] ?? []))));
        }
        $GLOBALS['cms_theme'] = $prev;
        cms_make_revision('Antes de ativar o tema ' . $def['name']);
        cms_write_json(CMS_DATA . '/content.json', $raw);
    }
    cms_write_json(CMS_DATA . '/theme.json', ['active' => $slug, 'activated_at' => date('c')]);
    return ['ok' => true, 'name' => (string) $def['name']];
}
