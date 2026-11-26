<?php
/**
 * Sev7n Xpress — painel administrativo
 * Autor: João Costa
 */
declare(strict_types=1);

define('CMS_ENTRY_DEPTH', 1);
require __DIR__ . '/../cms/admin_lib.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

cms_session_start();

$page   = preg_replace('/[^a-z_-]/', '', (string) ($_GET['p'] ?? 'dashboard'));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$self   = cms_url('admin/');

function admin_url(string $p = '', array $q = []): string
{
    global $self;
    $q = $p !== '' ? array_merge(['p' => $p], $q) : $q;
    return $self . ($q ? '?' . http_build_query($q) : '');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Bloqueia a ação se o usuário não tiver a permissão. */
function require_perm(string $perm, ?string $pageId = null): void
{
    global $page;
    if (cms_can($perm, $pageId)) {
        return;
    }
    if ($page === 'api') {
        json_out(['ok' => false, 'error' => 'Seu perfil não tem acesso a esta função.'], 403);
    }
    cms_flash('error', 'Seu perfil não tem acesso a essa área. Fale com o administrador.');
    redirect(admin_url());
}

/* -------------------------------------------------------------------------
 * Ambiente
 * ---------------------------------------------------------------------- */
$envErrors = [];
foreach (['data' => CMS_DATA, 'uploads' => CMS_UPLOADS] as $label => $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_writable($dir)) {
        $envErrors[] = "A pasta <code>$label/</code> precisa de permissão de escrita (755 ou 775).";
    }
}

/* -------------------------------------------------------------------------
 * Primeiro acesso
 * ---------------------------------------------------------------------- */
if (cms_needs_setup()) {
    $error = '';
    if ($method === 'POST' && !$envErrors) {
        if (!cms_csrf_ok()) {
            $error = 'A sessão expirou. Recarregue a página e tente de novo.';
        } else {
            $user  = strtolower(trim((string) ($_POST['user'] ?? '')));
            $pass  = (string) ($_POST['pass'] ?? '');
            if (!preg_match('/^[a-z0-9._-]{3,40}$/', $user)) {
                $error = 'O usuário deve ter de 3 a 40 caracteres: letras, números, ponto, hífen ou sublinhado.';
            } elseif ($pass !== (string) ($_POST['pass2'] ?? '')) {
                $error = 'As senhas não são iguais.';
            } elseif ($msg = cms_password_error($pass)) {
                $error = $msg;
            } else {
                cms_save_users([$user => ['hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => 'admin', 'perms' => [], 'created' => date('c')]]);
                $siteName = cms_clean_text((string) ($_POST['site_name'] ?? ''));
                if ($siteName !== '' && !is_file(CMS_DATA . '/content.json')) {
                    // Só o nome: páginas e módulos continuam seguindo o tema até a primeira edição
                    cms_write_json(CMS_DATA . '/content.json', ['settings' => [], 'seo' => ['org_name' => $siteName], 'modules' => []]);
                }
                cms_login($user, $pass);
                cms_flash('ok', 'Administrador criado. Bem-vindo ao painel.');
                redirect(admin_url());
            }
        }
    }
    auth_screen('Criar administrador', 'Primeiro acesso: defina o usuário e a senha do painel.', $error, true, $envErrors);
    exit;
}

/* -------------------------------------------------------------------------
 * Login
 * ---------------------------------------------------------------------- */
if ($page === 'logout') {
    cms_logout();
    redirect(admin_url());
}
if (!cms_logged_in()) {
    $error = '';
    if ($method === 'POST') {
        $locked = cms_login_locked();
        if ($locked) {
            $error = "Muitas tentativas. Tente de novo em $locked minuto(s).";
        } elseif (!cms_csrf_ok()) {
            $error = 'A sessão expirou. Recarregue a página e tente de novo.';
        } elseif (cms_login((string) ($_POST['user'] ?? ''), (string) ($_POST['pass'] ?? ''))) {
            cms_login_attempt(true);
            redirect(admin_url());
        } else {
            cms_login_attempt(false);
            $error = 'Usuário ou senha incorretos.';
        }
    }
    if ($page === 'api') {
        json_out(['ok' => false, 'error' => 'Sua sessão expirou. Entre de novo.'], 401);
    }
    auth_screen('Entrar no painel', cms_site_name(), $error, false, $envErrors);
    exit;
}

$settings    = cms_settings_sections();
$adminPages  = cms_admin_pages();
$multiPages  = cms_multi_pages();

/* -------------------------------------------------------------------------
 * Ações (POST)
 * ---------------------------------------------------------------------- */
if ($method === 'POST') {
    if (!cms_csrf_ok()) {
        if ($page === 'api') {
            json_out(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.'], 403);
        }
        cms_flash('error', 'A sessão expirou e nada foi salvo. Tente de novo.');
        redirect(admin_url($page, array_intersect_key($_GET, ['id' => 1, 'm' => 1])));
    }
    $action = (string) ($_POST['action'] ?? '');

    // Proteção contra limite de campos do PHP (max_input_vars)
    $maxVars = (int) ini_get('max_input_vars');
    if ($maxVars > 0 && count($_POST, COUNT_RECURSIVE) >= $maxVars - 5) {
        cms_flash('error', "Esta página tem campos demais para o limite do servidor (max_input_vars = $maxVars). Nada foi salvo. Aumente esse valor no MultiPHP INI Editor do cPanel (sugestão: 5000).");
        redirect(admin_url($page, array_intersect_key($_GET, ['id' => 1, 'm' => 1])));
    }

    // Salvar configurações globais
    if ($page === 'settings' && $action === 'save') {
        $id = (string) ($_GET['id'] ?? '');
        require_perm('settings.' . $id);
        if (isset($settings[$id])) {
            $content = cms_content();
            $input   = $_POST['f'] ?? [];
            $data    = [];
            foreach ($settings[$id]['fields'] as $fid => $def) {
                $raw = $input[$fid] ?? (($def['type'] ?? '') === 'repeater' ? [] : (($def['type'] ?? '') === 'toggle' ? false : ''));
                $data[$fid] = cms_clean_value($def, $raw);
            }
            $content['settings'][$id] = $data;
            cms_save_content($content, $settings[$id]['label'])
                ? cms_flash('ok', '“' . $settings[$id]['label'] . '” salvo.')
                : cms_flash('error', 'Não foi possível gravar. Verifique a permissão da pasta data/.');
        }
        redirect(admin_url('settings', ['id' => $id]));
    }

    // Salvar página (blocos + SEO)
    if ($page === 'page' && $action === 'save') {
        $id      = (string) ($_GET['id'] ?? '');
        $content = cms_content();
        $pg      = $content['pages'][$id] ?? null;
        if (!$pg || (!$multiPages && empty($pg['home']))) {
            redirect(admin_url());
        }
        require_perm('pages.edit', $id);
        $canManage = cms_can('pages.manage');
        $in    = (array) ($_POST['page'] ?? []);
        $title = cms_clean_text((string) ($in['title'] ?? '')) ?: 'Sem título';
        $pg['title'] = $title;
        if (empty($pg['home']) && $canManage) {
            $slug = cms_slug((string) ($in['slug'] ?? '')) ?: cms_slug($title);
            $slug = $slug ?: 'pagina';
            $base = $slug;
            $n    = 2;
            $taken = array_map(fn ($p) => $p['slug'] ?? '', array_filter($content['pages'], fn ($p) => ($p['id'] ?? '') !== $id));
            while (in_array($slug, $taken, true) || in_array($slug, cms_reserved_slugs(), true)) {
                $slug = $base . '-' . $n++;
            }
            if ($slug !== ($in['slug'] ?? '')) {
                cms_flash('ok', 'Endereço ajustado para /' . $slug . '.');
            }
            $pg['slug']   = $slug;
            $pg['status'] = ($in['status'] ?? '') === 'draft' ? 'draft' : 'published';
        }
        $seo = [];
        foreach (cms_page_seo_fields() as $fid => $def) {
            $seo[$fid] = cms_clean_value($def, $in['seo'][$fid] ?? (($def['type'] ?? '') === 'toggle' ? false : ''));
        }
        $pg['seo']     = $seo;
        $pg['blocks']  = cms_collect_blocks((array) ($_POST['blocks'] ?? []), (array) ($pg['blocks'] ?? []));
        $pg['updated'] = date('c');
        $content['pages'][$id] = $pg;
        cms_save_content($content, $title)
            ? cms_flash('ok', '“' . $title . '” salva. As mudanças já estão no site.')
            : cms_flash('error', 'Não foi possível gravar. Verifique a permissão da pasta data/.');
        redirect(admin_url('page', ['id' => $id]));
    }

    // Páginas: criar, duplicar, excluir
    if ($page === 'pages' && $multiPages) {
        require_perm('pages.manage');
        $content = cms_content();
        if ($action === 'create') {
            $title = cms_clean_text((string) ($_POST['title'] ?? '')) ?: 'Nova página';
            $slug  = cms_slug($title) ?: 'pagina';
            $base  = $slug;
            $n     = 2;
            $taken = array_map(fn ($p) => $p['slug'] ?? '', $content['pages']);
            while (in_array($slug, $taken, true) || in_array($slug, cms_reserved_slugs(), true)) {
                $slug = $base . '-' . $n++;
            }
            $id = 'p' . bin2hex(random_bytes(4));
            $content['pages'][$id] = [
                'id' => $id, 'title' => $title, 'slug' => $slug, 'home' => false, 'status' => 'draft', 'seo' => [], 'created' => date('c'),
                'blocks' => [cms_new_block('page-header', ['title' => $title]), cms_new_block('text')],
            ];
            cms_save_content($content, 'Nova página ' . $title);
            cms_flash('ok', 'Página criada como rascunho. Monte os blocos e publique quando estiver pronta.');
            redirect(admin_url('page', ['id' => $id]));
        }
        $id = (string) ($_POST['id'] ?? '');
        $pg = $content['pages'][$id] ?? null;
        if ($pg && $action === 'duplicate') {
            $new = $pg;
            $new['id']     = 'p' . bin2hex(random_bytes(4));
            $new['home']   = false;
            $new['title']  = $pg['title'] . ' (cópia)';
            $new['slug']   = cms_slug(($pg['slug'] ?: 'inicio') . '-copia-' . substr($new['id'], 1, 4));
            $new['status'] = 'draft';
            foreach ($new['blocks'] as $i => $bl) {
                $new['blocks'][$i]['id'] = 'b' . bin2hex(random_bytes(4));
            }
            $content['pages'][$new['id']] = $new;
            cms_save_content($content, 'Duplicar ' . $pg['title']);
            cms_flash('ok', 'Cópia criada como rascunho.');
        }
        if ($pg && $action === 'delete' && empty($pg['home'])) {
            unset($content['pages'][$id]);
            cms_save_content($content, 'Excluir ' . $pg['title']);
            cms_flash('ok', 'Página “' . $pg['title'] . '” excluída. Ela pode ser recuperada em Revisões.');
        }
        redirect(admin_url('pages'));
    }

    // Temas
    if ($page === 'themes') {
        require_perm('admin');
        if ($action === 'upload') {
            $r = cms_theme_install_zip($_FILES['theme'] ?? [], !empty($_POST['update']));
            if ($r['ok']) {
                cms_flash('ok', 'Tema “' . $r['name'] . '” ' . ($r['updated'] ? 'atualizado (a versão anterior ficou guardada em data/theme-backups).' : 'instalado. Clique em Ativar para usar.'));
            } else {
                cms_flash('error', $r['error']);
            }
        }
        if ($action === 'activate') {
            $r = cms_theme_activate((string) ($_POST['slug'] ?? ''), !empty($_POST['demo_pages']), !empty($_POST['demo_settings']), !empty($_POST['modules']));
            $r['ok'] ? cms_flash('ok', 'Tema “' . $r['name'] . '” ativado. Confira o site.') : cms_flash('error', $r['error']);
        }
        if ($action === 'delete') {
            $slug = (string) ($_POST['slug'] ?? '');
            if (in_array($slug, cms_theme_slugs(), true) && $slug !== cms_active_theme()) {
                cms_rrmdir(CMS_ROOT . '/themes/' . $slug);
                cms_flash('ok', 'Tema excluído.');
            } else {
                cms_flash('error', 'Não é possível excluir o tema ativo.');
            }
        }
        redirect(admin_url('themes'));
    }

    // Módulos: ativar/desativar e configurar
    if ($page === 'modules' && in_array($action, ['activate', 'deactivate'], true)) {
        require_perm('admin');
        $slug = (string) ($_POST['m'] ?? '');
        $mods = cms_available_modules();
        if (isset($mods[$slug])) {
            $content = cms_content();
            $active  = array_values(array_filter((array) $content['active_modules'], fn ($m) => $m !== $slug));
            if ($action === 'activate') {
                $active[] = $slug;
                // Ativa junto os módulos de que este depende
                foreach ((array) ($mods[$slug]['requires'] ?? []) as $req) {
                    if (isset($mods[$req]) && !in_array($req, $active, true)) {
                        $active[] = $req;
                        cms_flash('ok', 'O módulo “' . $mods[$req]['name'] . '” também foi ativado, porque é necessário.');
                    }
                }
            } else {
                // Desativa junto os que dependem deste
                foreach ($mods as $ms => $md) {
                    if (in_array($slug, (array) ($md['requires'] ?? []), true) && in_array($ms, $active, true)) {
                        $active = array_values(array_diff($active, [$ms]));
                        cms_flash('ok', 'O módulo “' . $md['name'] . '” também foi desativado, porque depende deste.');
                    }
                }
            }
            $content['active_modules'] = $active;
            cms_save_content($content, ($action === 'activate' ? 'Ativar ' : 'Desativar ') . $mods[$slug]['name']);
            cms_flash('ok', 'Módulo “' . $mods[$slug]['name'] . '” ' . ($action === 'activate' ? 'ativado.' : 'desativado. As configurações e os dados dele foram mantidos.'));
        }
        redirect(admin_url('modules'));
    }
    if ($page === 'module' && $action === 'save') {
        require_perm('admin');
        $slug = (string) ($_GET['m'] ?? '');
        cms_module_settings_save($slug)
            ? cms_flash('ok', 'Configurações do módulo salvas.')
            : cms_flash('error', 'Não foi possível salvar.');
        redirect(admin_url('module', ['m' => $slug]));
    }

    // SEO
    if ($page === 'seo' && $action === 'save') {
        require_perm('seo');
        $content = cms_content();
        $input   = $_POST['seo'] ?? [];
        foreach (cms_seo_fields() as $group) {
            foreach ($group['fields'] as $fid => $def) {
                $content['seo'][$fid] = cms_clean_value($def, $input[$fid] ?? (($def['type'] ?? '') === 'toggle' ? false : ''));
            }
        }
        cms_save_content($content, 'SEO') ? cms_flash('ok', 'Configurações de SEO salvas.') : cms_flash('error', 'Não foi possível gravar.');
        redirect(admin_url('seo'));
    }

    // Mídia
    if ($page === 'media') {
        require_perm('media');
    }
    if ($page === 'media' && $action === 'delete') {
        $path = cms_safe_rel_path((string) ($_POST['path'] ?? ''));
        if ($path && strpos($path, 'uploads/') === 0 && is_file(CMS_ROOT . '/' . $path)) {
            @unlink(CMS_ROOT . '/' . $path);
            $meta = cms_media_meta();
            unset($meta[$path]);
            cms_save_media_meta($meta);
            cms_flash('ok', 'Imagem excluída.');
        } else {
            cms_flash('error', 'Só é possível excluir imagens enviadas pelo painel.');
        }
        redirect(admin_url('media'));
    }
    if ($page === 'media' && $action === 'alt') {
        $path = cms_safe_rel_path((string) ($_POST['path'] ?? ''));
        if ($path) {
            $meta = cms_media_meta();
            $meta[$path] = array_merge($meta[$path] ?? [], ['alt' => cms_clean_text((string) ($_POST['alt'] ?? ''))]);
            cms_save_media_meta($meta);
            cms_flash('ok', 'Texto alternativo salvo. Ele será sugerido ao escolher esta imagem.');
        }
        redirect(admin_url('media'));
    }
    if ($page === 'media' && $action === 'upload') {
        $files = $_FILES['files'] ?? null;
        $ok = 0;
        $errs = [];
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $n) {
                $r = cms_handle_upload(['name' => $n, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]]);
                if ($r['ok']) {
                    $ok++;
                } else {
                    $errs[] = $n . ': ' . $r['error'];
                }
            }
        }
        if ($ok) {
            cms_flash('ok', $ok . ' imagem(ns) enviada(s).');
        }
        foreach ($errs as $er) {
            cms_flash('error', $er);
        }
        if (!$ok && !$errs) {
            cms_flash('error', 'Nenhum arquivo recebido. Verifique o limite de upload do servidor (' . ini_get('upload_max_filesize') . ').');
        }
        redirect(admin_url('media'));
    }
    if ($page === 'api' && $action !== 'upload') {
        cms_do_action('admin_api:' . preg_replace('/[^a-z0-9_]/', '', $action));
        json_out(['ok' => false, 'error' => 'Ação desconhecida.'], 404);
    }
    if ($page === 'api' && $action === 'upload') {
        require_perm('media');
        $r = cms_handle_upload($_FILES['file'] ?? [], (string) ($_POST['alt'] ?? ''));
        if (!$r['ok']) {
            json_out($r, 400);
        }
        json_out(['ok' => true, 'path' => $r['path'], 'url' => cms_url($r['path'])]);
    }

    // Revisões e backup
    if ($page === 'revisions') {
        require_perm('admin');
    }
    if ($page === 'revisions' && $action === 'restore') {
        $file = basename((string) ($_POST['file'] ?? ''));
        $full = CMS_DATA . '/revisions/' . $file;
        if (preg_match('/^[0-9a-z\-]+\.json$/', $file) && is_file($full)) {
            $data = cms_read_json($full, null);
            if (is_array($data)) {
                cms_save_content($data, 'Antes de restaurar');
                cms_flash('ok', 'Versão restaurada. A versão anterior foi guardada nas revisões.');
            }
        }
        redirect(admin_url('revisions'));
    }
    if ($page === 'revisions' && $action === 'import') {
        $f = $_FILES['backup'] ?? null;
        $data = $f && $f['error'] === UPLOAD_ERR_OK ? json_decode((string) file_get_contents($f['tmp_name']), true) : null;
        if (is_array($data) && (isset($data['pages']) || isset($data['settings']))) {
            unset($data['exported_at'], $data['cms']);
            cms_save_content($data, 'Antes de importar');
            cms_flash('ok', 'Backup importado.');
        } else {
            cms_flash('error', 'Arquivo inválido. Envie um .json exportado pelo Sev7n Xpress 2.');
        }
        redirect(admin_url('revisions'));
    }

    // Conta
    if ($page === 'account') {
        $users = cms_users();
        $me    = $_SESSION['cms_user'];
        if ($action === 'password') {
            $new = (string) ($_POST['new'] ?? '');
            if (!password_verify((string) ($_POST['current'] ?? ''), $users[$me]['hash'])) {
                cms_flash('error', 'A senha atual está incorreta.');
            } elseif ($new !== (string) ($_POST['new2'] ?? '')) {
                cms_flash('error', 'A confirmação não é igual à nova senha.');
            } elseif ($msg = cms_password_error($new)) {
                cms_flash('error', $msg);
            } else {
                $users[$me]['hash'] = password_hash($new, PASSWORD_DEFAULT);
                cms_save_users($users);
                session_regenerate_id(true);
                cms_flash('ok', 'Senha alterada.');
            }
        }
        if (in_array($action, ['add_user', 'remove_user', 'save_user'], true)) {
            require_perm('admin');
        }
        if ($action === 'save_user') {
            $u = (string) ($_POST['user'] ?? '');
            if (isset($users[$u])) {
                $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'editor';
                $admins = array_filter($users, fn ($x) => $x['role'] === 'admin');
                if ($role === 'editor' && $users[$u]['role'] === 'admin' && count($admins) <= 1) {
                    cms_flash('error', 'O site precisa de pelo menos um administrador.');
                    redirect(admin_url('user', ['u' => $u]));
                }
                $catalog = cms_permission_catalog();
                $areas = array_values(array_intersect(array_keys($catalog), (array) ($_POST['areas'] ?? [])));
                $pagesSel = ($_POST['pages_mode'] ?? 'all') === 'some'
                    ? array_values(array_intersect(array_keys(cms_pages(true)), (array) ($_POST['pages'] ?? [])))
                    : 'all';
                $users[$u]['role']  = $role;
                $users[$u]['perms'] = ['areas' => $areas, 'pages' => $pagesSel];
                cms_save_users($users);
                cms_flash('ok', 'Perfil de “' . $u . '” salvo.');
            }
            redirect(admin_url('user', ['u' => $u]));
        }
        if ($action === 'add_user') {
            $u = strtolower(trim((string) ($_POST['user'] ?? '')));
            $p = (string) ($_POST['pass'] ?? '');
            if (!preg_match('/^[a-z0-9._-]{3,40}$/', $u)) {
                cms_flash('error', 'Usuário inválido. Use de 3 a 40 caracteres: letras, números, ponto, hífen ou sublinhado.');
            } elseif (isset($users[$u])) {
                cms_flash('error', 'Este usuário já existe.');
            } elseif ($msg = cms_password_error($p)) {
                cms_flash('error', $msg);
            } else {
                $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'editor';
                $users[$u] = ['hash' => password_hash($p, PASSWORD_DEFAULT), 'role' => $role, 'perms' => ['areas' => ['pages.edit', 'media'], 'pages' => 'all'], 'created' => date('c')];
                cms_save_users($users);
                cms_flash('ok', "Usuário “{$u}” criado como " . cms_role_label($role) . ($role === 'editor' ? '. Ajuste o que ele pode acessar.' : '.'));
                if ($role === 'editor') {
                    redirect(admin_url('user', ['u' => $u]));
                }
            }
        }
        if ($action === 'remove_user') {
            $u = (string) ($_POST['user'] ?? '');
            if ($u === $me) {
                cms_flash('error', 'Você não pode excluir o próprio usuário.');
            } elseif (isset($users[$u])) {
                unset($users[$u]);
                cms_save_users($users);
                cms_flash('ok', "Usuário “{$u}” excluído.");
            }
        }
        redirect(admin_url('account'));
    }

    // Telas de módulos
    if (isset($adminPages[$page]) && is_callable($adminPages[$page]['handle'])) {
        require_perm($adminPages[$page]['admin_only'] ? 'admin' : $adminPages[$page]['perm']);
        $adminPages[$page]['handle']($action);
        redirect(admin_url($page, array_diff_key($_GET, ['p' => 1])));
    }
}

/* -------------------------------------------------------------------------
 * GET: API e downloads
 * ---------------------------------------------------------------------- */
cms_do_action('admin_get', $page);
if ($page === 'api' && ($_GET['action'] ?? '') === 'media') {
    if (!cms_can('media') && !cms_can('pages.edit')) {
        json_out(['ok' => false, 'error' => 'Sem acesso à biblioteca.'], 403);
    }
    json_out(['ok' => true, 'items' => cms_media_list()]);
}
if ($page === 'export') {
    require_perm('admin');
    $c = cms_content();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup-conteudo-' . date('Y-m-d-His') . '.json"');
    $c['exported_at'] = date('c');
    $c['cms'] = CMS_VERSION;
    echo json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* -------------------------------------------------------------------------
 * Layout do painel
 * ---------------------------------------------------------------------- */

function auth_screen(string $title, string $sub, string $error, bool $setup, array $envErrors): void
{
    $cms = e(cms_config('cms_name', 'Sev7n Xpress'));
    ?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title><?= e($title) ?> · <?= $cms ?></title>
<link rel="stylesheet" href="<?= e(cms_url('admin/assets/admin.css')) ?>?v=<?= CMS_VERSION ?>"><?= cms_brand_admin_css() ?></head>
<body class="auth">
<main class="auth__card">
  <p class="brand"><?= $cms ?></p>
  <h1><?= e($title) ?></h1>
  <p class="muted"><?= e($sub) ?></p>
  <?php foreach ($envErrors as $er): ?><div class="notice notice--error"><?= $er ?></div><?php endforeach; ?>
  <?php if ($error): ?><div class="notice notice--error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= cms_csrf_field() ?>
    <?php if ($setup): ?>
      <div class="field"><label for="sn">Nome do site</label><input id="sn" name="site_name" required placeholder="Ex.: Clínica Exemplo" value="<?= e($_POST['site_name'] ?? '') ?>"><p class="help">Aparece no painel e no Google. Dá para mudar depois em SEO e Google.</p></div>
    <?php endif; ?>
    <div class="field"><label for="u">Usuário</label><input id="u" name="user" required autocomplete="username" <?= $setup ? '' : 'autofocus' ?> value="<?= e($_POST['user'] ?? '') ?>"></div>
    <div class="field"><label for="p">Senha</label><input id="p" type="password" name="pass" required autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>"></div>
    <?php if ($setup): ?>
      <div class="field"><label for="p2">Repita a senha</label><input id="p2" type="password" name="pass2" required autocomplete="new-password"><p class="help">Mínimo de 10 caracteres, com letras e números.</p></div>
    <?php endif; ?>
    <button class="btn btn--primary btn--block"><?= $setup ? 'Criar administrador' : 'Entrar' ?></button>
  </form>
  <a class="auth__back" href="<?= e(cms_url()) ?>">Voltar para o site</a>
</main>
</body></html><?php
}

function nav_link(string $href, string $icon, string $label, bool $active, string $extra = ''): string
{
    return '<a href="' . e($href) . '" class="' . ($active ? 'is-active' : '') . '"' . ($active ? ' aria-current="page"' : '') . '>' . cms_icon($icon) . '<span>' . e($label) . '</span>' . $extra . '</a>';
}

function layout_start(string $title, string $active, string $actionsHtml = ''): void
{
    global $settings, $adminPages, $multiPages;
    $cms = e(cms_config('cms_name', 'Sev7n Xpress'));
    $home = cms_home_page();
    ?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><meta name="csrf" content="<?= e(cms_csrf_token()) ?>">
<title><?= e($title) ?> · <?= $cms ?></title>
<link rel="stylesheet" href="<?= e(cms_url('admin/assets/admin.css')) ?>?v=<?= CMS_VERSION ?>"><?= cms_brand_admin_css() ?>
<?php cms_do_action('admin_head'); ?></head>
<body data-api="<?= e(admin_url('api')) ?>">
<a class="skip" href="#main">Pular para o conteúdo</a>
<div class="shell">
<aside class="side" id="side">
  <div class="side__brand"><strong><?= $cms ?></strong><span><?= e(cms_site_name()) ?></span></div>
  <nav class="side__nav" aria-label="Painel">
    <?= nav_link(admin_url(), 'pulso', 'Visão geral', $active === 'dashboard') ?>
    <?php
    $groups = ['Conteúdo' => [], 'Google' => [], 'Configurações' => []];
    if (cms_can('pages.edit')) {
        $groups['Conteúdo'][] = $multiPages
            ? nav_link(admin_url('pages'), 'calendario', 'Páginas', $active === 'pages' || strpos($active, 'page:') === 0)
            : nav_link(admin_url('page', ['id' => $home['id']]), 'calendario', 'Página inicial', $active === 'page:' . $home['id']);
    }
    if (cms_can('media')) {
        $groups['Conteúdo'][] = nav_link(admin_url('media'), 'folha', 'Biblioteca de mídia', $active === 'media');
    }
    foreach ($adminPages as $slug => $ap) {
        if (!empty($ap['hidden']) || !cms_can($ap['admin_only'] ? 'admin' : $ap['perm'])) {
            continue;
        }
        $badge = is_callable($ap['badge']) ? (int) $ap['badge']() : 0;
        $groups[$ap['group']][] = nav_link(admin_url($slug), $ap['icon'], $ap['label'], $active === $slug, $badge ? '<em class="count">' . $badge . '</em>' : '');
    }
    foreach ($settings as $sid => $sec) {
        if (cms_can('settings.' . $sid)) {
            $groups['Configurações'][] = nav_link(admin_url('settings', ['id' => $sid]), $sec['icon'] ?? 'folha', $sec['label'], $active === 'settings:' . $sid);
        }
    }
    if (cms_can('seo')) {
        $groups['Configurações'][] = nav_link(admin_url('seo'), 'check', 'SEO e Google', $active === 'seo');
    }
    if (cms_is_admin()) {
        $groups['Configurações'][] = nav_link(admin_url('themes'), 'gota', 'Temas', $active === 'themes');
        $groups['Configurações'][] = nav_link(admin_url('modules'), 'neuronio', 'Módulos', $active === 'modules' || strpos($active, 'module:') === 0);
        $groups['Configurações'][] = nav_link(admin_url('revisions'), 'relogio', 'Revisões e backup', $active === 'revisions');
    }
    $groups['Configurações'][] = nav_link(admin_url('account'), 'escudo', cms_is_admin() ? 'Usuários e senha' : 'Minha senha', $active === 'account' || $active === 'user');
    foreach ($groups as $gname => $links) {
        if (!$links) {
            continue;
        }
        echo '<p class="side__group">' . e($gname) . '</p>' . implode('', $links);
    }
    ?>
  </nav>
  <div class="side__foot">
    <a href="<?= e(cms_url()) ?>" target="_blank" rel="noopener">Ver site</a>
    <a href="<?= e(admin_url('logout')) ?>">Sair (<?= e($_SESSION['cms_user']) ?> · <?= e(cms_role_label((string) (cms_acting_user()['role'] ?? 'editor'))) ?>)</a>
  </div>
</aside>
<div class="main" id="main">
  <header class="topbar">
    <button class="topbar__menu" type="button" data-menu aria-controls="side" aria-expanded="false">Menu</button>
    <h1><?= e($title) ?></h1>
    <?= $actionsHtml ?: '<a class="btn btn--ghost btn--small topbar__site" href="' . e(cms_url()) . '" target="_blank" rel="noopener">Ver site</a>' ?>
  </header>
  <?php foreach (cms_take_flash() as [$type, $msg]): ?>
    <div class="notice notice--<?= e($type) ?>" role="status"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?php if (cms_is_admin() && !is_file(CMS_ROOT . '/.htaccess')): ?>
    <div class="notice notice--error">O arquivo <code>.htaccess</code> não está na pasta do site. Sem ele, só a página inicial abre e as outras páginas dão erro 404. Envie o <code>.htaccess</code> que vem no pacote para a mesma pasta do <code>index.php</code> (no Gerenciador de Arquivos do cPanel, ative “Mostrar arquivos ocultos”).</div>
  <?php endif; ?>
  <?php if (cms_is_admin() && ($GLOBALS['cms_theme_error'] !== '' || cms_active_theme() === '') && $active !== 'themes'): ?>
    <div class="notice notice--error"><?= cms_active_theme() === '' ? 'Nenhum tema instalado: o site mostra “em configuração”.' : 'O tema ativo tem um erro: ' . e($GLOBALS['cms_theme_error']) ?> <a href="<?= e(admin_url('themes')) ?>">Abrir Temas</a></div>
  <?php endif; ?>
<?php
}

function layout_end(): void
{
    ?>
  <footer class="credit"><?= e(cms_config('cms_name', 'Sev7n Xpress')) ?> v<?= CMS_VERSION ?> · desenvolvido por João Costa</footer>
</div></div>
<div class="modal" id="mediaModal" hidden>
  <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="mediaModalTitle">
    <div class="modal__head"><h2 id="mediaModalTitle">Escolher imagem</h2><button type="button" class="btn btn--ghost btn--small" data-close>Fechar</button></div>
    <div class="modal__upload">
      <label class="drop" data-drop>
        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-modal-file>
        <span><strong>Enviar nova imagem</strong> ou arraste o arquivo para cá. JPG, PNG ou WebP, até <?= (int) cms_config('max_upload_mb', 8) ?> MB.</span>
      </label>
      <p class="modal__status" data-status aria-live="polite"></p>
    </div>
    <div class="modal__grid" data-grid></div>
  </div>
</div>
<script type="application/json" id="cmsIcons"><?= json_encode(array_map(fn ($k) => cms_icon($k), array_combine(array_keys(cms_icons()), array_keys(cms_icons()))), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= e(cms_url('admin/assets/admin.js')) ?>?v=<?= CMS_VERSION ?>"></script>
<?php cms_do_action('admin_footer'); ?>
</body></html><?php
}

/* ---------- Visão geral ---------- */
if ($page === 'dashboard' || $page === '') {
    $content = cms_content();
    $checks  = cms_seo_checks();
    $warns   = count(array_filter($checks, fn ($c) => $c[0] !== 'ok' && $c[0] !== 'info'));
    $pages   = cms_editable_pages();
    layout_start('Visão geral', 'dashboard');
    foreach ($envErrors as $er) {
        echo '<div class="notice notice--error">' . $er . '</div>';
    }
    ?>
    <section class="panel">
      <div class="stats">
        <div><span class="stats__n"><?= count($pages) ?></span><span><?= count($pages) === 1 ? 'página' : 'páginas' ?></span></div>
        <?php if (cms_is_admin()): ?><div><span class="stats__n"><?= count(array_filter(cms_available_modules(), fn ($m) => cms_module_active($m['slug']))) ?></span><span>módulos ativos</span></div><?php endif; ?>
        <?php foreach ($adminPages as $slug => $ap): if (!is_callable($ap['badge']) || !cms_can($ap['admin_only'] ? 'admin' : $ap['perm'])) { continue; } $n = (int) $ap['badge'](); ?>
          <div><a class="stats__n <?= $n ? 'is-new' : '' ?>" href="<?= e(admin_url($slug)) ?>"><?= $n ?></a><span><?= e(mb_strtolower($ap['label'])) ?> não lidos</span></div>
        <?php endforeach; ?>
        <?php if (cms_can('seo')): ?><div><span class="stats__n <?= $warns ? 'is-warn' : 'is-ok' ?>"><?= $warns ?></span><span>ajustes de SEO sugeridos</span></div><?php endif; ?>
        <div><span class="stats__n stats__n--small"><?= e(cms_format_date($content['updated_at'])) ?></span><span>última alteração</span></div>
      </div>
    </section>
    <section class="panel">
      <?php if ($pages): ?>
      <div class="row row--between"><h2>Páginas</h2><?php if ($multiPages): ?><a class="btn btn--small" href="<?= e(admin_url('pages')) ?>">Gerenciar páginas</a><?php endif; ?></div>
      <ul class="cards">
        <?php foreach ($pages as $pg): ?>
        <li><a href="<?= e(admin_url('page', ['id' => $pg['id']])) ?>">
          <?= cms_icon(!empty($pg['home']) ? 'gota' : 'calendario') ?>
          <span><strong><?= e($pg['title']) ?></strong><small><?= e(cms_page_path($pg)) ?> · <?= count($pg['blocks'] ?? []) ?> blocos</small></span>
          <?php if (($pg['status'] ?? '') === 'draft'): ?><em class="tag">rascunho</em><?php endif; ?>
        </a></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?><p class="muted">Use o menu ao lado para acessar as áreas liberadas para você.</p><?php endif; ?>
    </section>
    <?php cms_do_action('admin_dashboard'); ?>
    <?php if (cms_can('seo')): ?>
    <section class="panel">
      <h2>Saúde do SEO</h2>
      <ul class="checks"><?php foreach ($checks as [$st, $msg]): ?><li class="check check--<?= e($st) ?>"><?= e($msg) ?></li><?php endforeach; ?></ul>
      <a class="btn" href="<?= e(admin_url('seo')) ?>">Abrir SEO e Google</a>
    </section>
    <?php endif; ?>
    <?php
    layout_end();
    exit;
}

/* ---------- Lista de páginas ---------- */
if ($page === 'pages') {
    if (!$multiPages) {
        redirect(admin_url('page', ['id' => cms_home_page()['id']]));
    }
    require_perm('pages.edit');
    $pages = cms_editable_pages();
    $canManage = cms_can('pages.manage');
    uasort($pages, fn ($a, $b) => !empty($b['home']) <=> !empty($a['home']) ?: strcmp((string) $a['title'], (string) $b['title']));
    layout_start('Páginas', 'pages');
    ?>
    <?php if ($canManage): ?>
    <section class="panel">
      <form method="post" class="row newpage">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="create">
        <label class="sr" for="np-title">Título da nova página</label>
        <input id="np-title" name="title" placeholder="Título da nova página (ex.: Sobre o consultório)" required>
        <button class="btn btn--primary">Criar página</button>
      </form>
    </section>
    <?php endif; ?>
    <section class="panel">
      <table class="table">
        <thead><tr><th>Página</th><th>Endereço</th><th>Status</th><th>Alterada em</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pages as $pg): ?>
          <tr>
            <td><a href="<?= e(admin_url('page', ['id' => $pg['id']])) ?>"><strong><?= e($pg['title']) ?></strong></a><?= !empty($pg['home']) ? ' <em class="tag tag--home">inicial</em>' : '' ?></td>
            <td><a href="<?= e(cms_page_url($pg)) ?>" target="_blank" rel="noopener"><?= e(cms_page_path($pg)) ?></a></td>
            <td><?= ($pg['status'] ?? '') === 'draft' ? '<em class="tag">rascunho</em>' : 'Publicada' ?></td>
            <td><?= e(cms_format_date($pg['updated'] ?? $pg['created'] ?? null)) ?></td>
            <td class="row row--end">
              <a class="btn btn--small" href="<?= e(admin_url('page', ['id' => $pg['id']])) ?>">Editar</a>
              <?php if ($canManage): ?>
              <form method="post"><?= cms_csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= e($pg['id']) ?>"><button class="btn btn--small btn--ghost">Duplicar</button></form>
              <?php if (empty($pg['home'])): ?>
              <form method="post" data-confirm="Excluir a página “<?= e($pg['title']) ?>”? Ela pode ser recuperada em Revisões."><?= cms_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($pg['id']) ?>"><button class="btn btn--small btn--danger">Excluir</button></form>
              <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php
    layout_end();
    exit;
}

/* ---------- Editor de página (blocos) ---------- */
if ($page === 'page') {
    $id = (string) ($_GET['id'] ?? '');
    $pg = cms_page($id);
    if (!$pg || (!$multiPages && empty($pg['home']))) {
        redirect(admin_url());
    }
    require_perm('pages.edit', $id);
    $canManage = cms_can('pages.manage');
    $isHome = !empty($pg['home']);
    $types  = cms_block_types();
    $groups = [];
    foreach ($types as $t => $def) {
        if (empty($def['hidden'])) {
            $groups[$def['group']][$t] = $def;
        }
    }
    $preview = ($pg['status'] ?? '') === 'draft' ? cms_url('') . '?preview=' . rawurlencode($id) : cms_page_url($pg);
    layout_start($pg['title'], 'page:' . $id, '<a class="btn btn--ghost btn--small" href="' . e($preview) . '" target="_blank" rel="noopener">' . (($pg['status'] ?? '') === 'draft' ? 'Pré-visualizar' : 'Ver página') . '</a>');
    $seoVals = (array) ($pg['seo'] ?? []);
    ?>
    <form method="post" class="form" data-dirty-check data-page-form>
      <?= cms_csrf_field() ?><input type="hidden" name="action" value="save">
      <section class="panel">
        <div class="grid">
          <div class="field">
            <label for="pg-title">Título da página</label>
            <input id="pg-title" name="page[title]" value="<?= e($pg['title']) ?>" required data-page-title>
            <p class="help">Nome usado no painel e no título da aba (quando o SEO da página está vazio).</p>
          </div>
          <?php if (!$isHome && !$canManage): ?>
            <div class="field"><span class="label">Endereço</span><p class="static"><?= e(cms_page_path($pg)) ?> · <?= ($pg['status'] ?? '') === 'draft' ? 'rascunho' : 'publicada' ?></p></div>
          <?php elseif ($isHome): ?>
            <div class="field"><span class="label">Endereço</span><p class="static"><?= e(cms_site_url()) ?>/ <em class="tag tag--home">página inicial</em></p></div>
          <?php else: ?>
            <div class="field">
              <label for="pg-slug">Endereço</label>
              <div class="prefix"><span><?= e(cms_base_path()) ?>/</span><input id="pg-slug" name="page[slug]" value="<?= e($pg['slug']) ?>" data-page-slug></div>
              <p class="help">Só letras minúsculas, números e hífen.</p>
            </div>
            <div class="field">
              <label for="pg-status">Status</label>
              <select id="pg-status" name="page[status]">
                <option value="published"<?= ($pg['status'] ?? '') !== 'draft' ? ' selected' : '' ?>>Publicada</option>
                <option value="draft"<?= ($pg['status'] ?? '') === 'draft' ? ' selected' : '' ?>>Rascunho (só você vê)</option>
              </select>
            </div>
          <?php endif; ?>
        </div>
        <details class="seo-box">
          <summary>SEO desta página</summary>
          <div class="grid">
            <?php foreach (cms_page_seo_fields() as $fid => $def): ?>
              <?= cms_render_field('page[seo][' . $fid . ']', $def, $seoVals[$fid] ?? cms_field_default($def)) ?>
            <?php endforeach; ?>
          </div>
        </details>
      </section>

      <section class="panel">
        <div class="row row--between"><h2>Blocos</h2><span class="muted small" data-block-count><?= count($pg['blocks'] ?? []) ?> blocos</span></div>
        <p class="muted">Clique no bloco para editar. Para mudar a ordem, arraste pela alça ⠿ ou use ↑ ↓. Para esconder sem apagar, use a chave “Visível”.</p>
        <ol class="blocks" data-blocks>
          <?php foreach ((array) ($pg['blocks'] ?? []) as $bl): ?>
            <?= cms_render_block_editor((string) $bl['id'], $bl) ?>
          <?php endforeach; ?>
        </ol>
        <p class="blocks__empty muted" data-blocks-empty<?= empty($pg['blocks']) ? '' : ' hidden' ?>>Esta página ainda não tem blocos.</p>
        <button type="button" class="btn btn--add" data-open-library>+ Adicionar bloco</button>
      </section>

      <?php foreach ($types as $t => $def): ?>
        <template data-block-tpl="<?= e($t) ?>"><?= cms_render_block_editor('__BID__', ['type' => $t, 'enabled' => true, 'anchor' => '', 'data' => []], true) ?></template>
      <?php endforeach; ?>

      <div class="savebar savebar--sticky">
        <button class="btn btn--primary">Salvar página</button>
        <a class="btn btn--ghost" href="<?= e($preview) ?>" target="_blank" rel="noopener"><?= ($pg['status'] ?? '') === 'draft' ? 'Pré-visualizar' : 'Ver página' ?></a>
        <span class="savebar__hint" data-dirty-hint hidden>Há alterações não salvas.</span>
      </div>
    </form>

    <div class="modal" id="blockLibrary" hidden>
      <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="libTitle">
        <div class="modal__head"><h2 id="libTitle">Adicionar bloco</h2><button type="button" class="btn btn--ghost btn--small" data-close>Fechar</button></div>
        <div class="library">
          <?php foreach ($groups as $gname => $list): ?>
            <h3><?= e($gname) ?></h3>
            <div class="library__grid">
              <?php foreach ($list as $t => $def): ?>
                <button type="button" class="library__item" data-add-block="<?= e($t) ?>">
                  <span class="library__icon"><?= cms_icon($def['icon']) ?></span>
                  <strong><?= e($def['label']) ?></strong>
                  <small><?= e($def['description']) ?></small>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          <p class="muted small">Mais blocos aparecem aqui quando você ativa módulos em <a href="<?= e(admin_url('modules')) ?>">Módulos</a>.</p>
        </div>
      </div>
    </div>
    <?php
    layout_end();
    exit;
}

/* ---------- Configurações globais ---------- */
if ($page === 'settings') {
    $id = (string) ($_GET['id'] ?? '');
    if (!isset($settings[$id])) {
        redirect(admin_url());
    }
    require_perm('settings.' . $id);
    $sec = $settings[$id];
    layout_start($sec['label'], 'settings:' . $id);
    ?>
    <form method="post" class="panel form" data-dirty-check>
      <?= cms_csrf_field() ?><input type="hidden" name="action" value="save">
      <?php if (!empty($sec['description'])): ?><p class="muted"><?= e($sec['description']) ?></p><?php endif; ?>
      <div class="grid">
        <?php foreach ($sec['fields'] as $fid => $def): ?>
          <?= cms_render_field('f[' . $fid . ']', $def, cms_settings_value($id, $fid, $def)) ?>
        <?php endforeach; ?>
      </div>
      <div class="savebar">
        <button class="btn btn--primary">Salvar alterações</button>
        <span class="savebar__hint" data-dirty-hint hidden>Há alterações não salvas.</span>
      </div>
    </form>
    <?php
    layout_end();
    exit;
}

/* ---------- Temas ---------- */
if ($page === 'themes') {
    require_perm('admin');
    $active = cms_active_theme();
    $raw    = cms_read_json(CMS_DATA . '/content.json', []);
    $followsTheme = !isset($raw['pages']);
    $zipOk  = class_exists('ZipArchive');
    layout_start('Temas', 'themes');
    if ($GLOBALS['cms_theme_error'] !== '') {
        echo '<div class="notice notice--error">O tema ativo tem um erro: ' . e($GLOBALS['cms_theme_error']) . '. Corrija o arquivo ou ative outro tema.</div>';
    }
    ?>
    <section class="panel">
      <h2>Instalar tema</h2>
      <p class="muted">O tema é o visual do site (layout, blocos próprios, CSS e imagens). Envie o .zip do tema; ele fica em <code>themes/</code> e só passa a valer quando você clicar em Ativar.</p>
      <?php if (!$zipOk): ?><div class="notice notice--warn">O PHP deste servidor não tem a extensão <code>zip</code>. Ative-a em Select PHP Version, ou envie a pasta do tema por FTP para <code>themes/</code>.</div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="row">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="upload">
        <label class="drop drop--inline" data-drop>
          <input type="file" name="theme" accept=".zip,application/zip" required data-theme-file>
          <span data-theme-file-label><strong>Escolher o .zip do tema</strong> ou arraste para cá (até <?= (int) cms_config('max_theme_mb', 40) ?> MB)</span>
        </label>
        <label class="chk"><input type="checkbox" name="update" value="1"> Atualizar se já existir</label>
        <button class="btn btn--primary" <?= $zipOk ? '' : 'disabled' ?>>Instalar tema</button>
      </form>
      <p class="help">Envie apenas temas de fonte confiável: um tema contém código PHP que roda no seu servidor.</p>
    </section>

    <section class="panel">
      <h2>Temas instalados</h2>
      <?php $slugs = cms_theme_slugs(); if (!$slugs): ?><p class="muted">Nenhum tema instalado ainda.</p><?php endif; ?>
      <ul class="themes">
        <?php foreach ($slugs as $slug): $m = cms_theme_manifest($slug) ?: []; $shot = cms_theme_screenshot($slug); $isOn = $slug === $active; ?>
        <li class="theme <?= $isOn ? 'is-on' : '' ?>">
          <div class="theme__shot"><?= $shot ? '<img src="' . e($shot) . '" alt="" loading="lazy">' : '<span>Sem imagem de prévia</span>' ?></div>
          <div class="theme__body">
            <h3><?= e($m['name'] ?? $slug) ?> <?= $isOn ? '<em class="tag tag--on">ativo</em>' : '' ?></h3>
            <?php if (!empty($m['error'])): ?><p class="bad small">Erro: <?= e($m['error']) ?></p><?php endif; ?>
            <?php if (!empty($m['description'])): ?><p class="small"><?= e($m['description']) ?></p><?php endif; ?>
            <p class="muted small">Versão <?= e($m['version'] ?? '1.0.0') ?><?= !empty($m['author']) ? ' · por ' . e($m['author']) : '' ?> · pasta <code>themes/<?= e($slug) ?></code></p>
            <?php if (!$isOn && empty($m['error'])): ?>
            <form method="post" class="theme__activate" data-confirm="Ativar o tema “<?= e($m['name'] ?? $slug) ?>”? O visual do site muda na hora.">
              <?= cms_csrf_field() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="slug" value="<?= e($slug) ?>">
              <?php if ($followsTheme): ?>
                <p class="muted small">As páginas ainda não foram editadas: o site vai usar as páginas de demonstração deste tema.</p>
              <?php else: ?>
                <label class="chk"><input type="checkbox" name="demo_pages" value="1"> Trocar as páginas atuais pelas páginas de demonstração do tema</label>
              <?php endif; ?>
              <label class="chk"><input type="checkbox" name="demo_settings" value="1"> Usar logo, menu e rodapé padrão do tema</label>
              <?php if (!empty($m['default_modules'])): ?><label class="chk"><input type="checkbox" name="modules" value="1" checked> Ativar os módulos recomendados</label><?php endif; ?>
              <div class="row">
                <button class="btn btn--primary">Ativar</button>
              </div>
            </form>
            <form method="post" data-confirm="Excluir o tema “<?= e($m['name'] ?? $slug) ?>”? A pasta themes/<?= e($slug) ?> será apagada.">
              <?= cms_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?= e($slug) ?>">
              <button class="btn btn--small btn--danger">Excluir</button>
            </form>
            <?php elseif ($isOn): ?>
              <a class="btn btn--small btn--ghost" href="<?= e(cms_url('')) ?>" target="_blank" rel="noopener">Ver site</a>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="muted small">Ao trocar de tema, a versão anterior do conteúdo fica salva em Revisões. Blocos que só existem no tema antigo ficam guardados e voltam se ele for reativado.</p>
    </section>
    <?php
    layout_end();
    exit;
}

/* ---------- Módulos ---------- */
if ($page === 'modules') {
    require_perm('admin');
    $mods = cms_available_modules();
    layout_start('Módulos', 'modules');
    ?>
    <section class="panel">
      <p class="muted">Módulos adicionam recursos e blocos ao site. Ao desativar, as configurações e os dados ficam guardados. Para instalar um módulo novo, envie a pasta dele para <code>modules/</code>.</p>
      <ul class="modules">
        <?php foreach ($mods as $slug => $m): $on = cms_module_active($slug); ?>
        <li class="module <?= $on ? 'is-on' : '' ?>">
          <span class="module__icon"><?= cms_icon($m['icon']) ?></span>
          <div class="module__info">
            <h2><?= e($m['name']) ?> <?= $on ? '<em class="tag tag--on">ativo</em>' : '' ?></h2>
            <p><?= e($m['description']) ?></p>
            <small>Versão <?= e($m['version']) ?><?= $m['author'] ? ' · por ' . e($m['author']) : '' ?> · pasta <code>modules/<?= e($slug) ?></code></small>
          </div>
          <div class="module__actions">
            <?php if ($on && $m['settings']): ?><a class="btn btn--small btn--ghost" href="<?= e(admin_url('module', ['m' => $slug])) ?>">Configurar</a><?php endif; ?>
            <form method="post"<?= $on ? ' data-confirm="Desativar “' . e($m['name']) . '”? Os blocos e telas dele somem do site e do painel até você ativar de novo."' : '' ?>>
              <?= cms_csrf_field() ?><input type="hidden" name="m" value="<?= e($slug) ?>"><input type="hidden" name="action" value="<?= $on ? 'deactivate' : 'activate' ?>">
              <button class="btn btn--small <?= $on ? 'btn--danger' : 'btn--primary' ?>"><?= $on ? 'Desativar' : 'Ativar' ?></button>
            </form>
          </div>
        </li>
        <?php endforeach; ?>
        <?php if (!$mods): ?><li class="muted">Nenhum módulo na pasta modules/.</li><?php endif; ?>
      </ul>
    </section>
    <?php
    layout_end();
    exit;
}

if ($page === 'module') {
    require_perm('admin');
    $slug = (string) ($_GET['m'] ?? '');
    $m    = cms_available_modules()[$slug] ?? null;
    if (!$m || !cms_module_active($slug)) {
        redirect(admin_url('modules'));
    }
    layout_start($m['name'], 'module:' . $slug);
    ?>
    <form method="post" class="panel form" data-dirty-check>
      <?= cms_csrf_field() ?><input type="hidden" name="action" value="save">
      <p class="muted"><?= e($m['description']) ?></p>
      <div class="grid">
        <?php foreach ($m['settings'] as $fid => $def): ?>
          <?= cms_render_field('set[' . $fid . ']', $def, cms_module_get($slug, $fid, cms_field_default($def))) ?>
        <?php endforeach; ?>
      </div>
      <div class="savebar">
        <button class="btn btn--primary">Salvar configurações</button>
        <a class="btn btn--ghost" href="<?= e(admin_url('modules')) ?>">Voltar para Módulos</a>
        <span class="savebar__hint" data-dirty-hint hidden>Há alterações não salvas.</span>
      </div>
    </form>
    <?php
    layout_end();
    exit;
}

/* ---------- Telas de módulos ---------- */
if (isset($adminPages[$page])) {
    $ap = $adminPages[$page];
    require_perm($ap['admin_only'] ? 'admin' : $ap['perm']);
    ob_start();
    $ap['render']();
    $body = (string) ob_get_clean();
    layout_start($ap['label'], $page);
    echo $body;
    layout_end();
    exit;
}

/* ---------- SEO ---------- */
if ($page === 'seo') {
    require_perm('seo');
    $content = cms_content();
    $val = function (string $k, array $def) use ($content) {
        return array_key_exists($k, $content['seo']) ? $content['seo'][$k] : cms_field_default($def);
    };
    layout_start('SEO e Google', 'seo');
    ?>
    <form method="post" class="form" data-dirty-check>
      <?= cms_csrf_field() ?><input type="hidden" name="action" value="save">
      <section class="panel">
        <h2>Prévia no Google (página inicial)</h2>
        <div class="serp" data-serp>
          <div class="serp__site"><span class="serp__fav"><?php $fv = cms_image_value(cms_seo('favicon')); if ($fv['src']): ?><img src="<?= e(cms_url($fv['src'])) ?>" alt=""><?php endif; ?></span>
            <span><span class="serp__name"><?= e(cms_seo('org_name') ?: cms_site_name()) ?></span><span class="serp__url"><?= e(cms_site_url()) ?></span></span></div>
          <p class="serp__title" data-serp-title></p>
          <p class="serp__desc" data-serp-desc></p>
        </div>
        <ul class="checks"><?php foreach (cms_seo_checks() as [$st, $msg]): ?><li class="check check--<?= e($st) ?>"><?= e($msg) ?></li><?php endforeach; ?></ul>
      </section>
      <?php foreach (cms_seo_fields() as $group): ?>
      <section class="panel">
        <h2><?= e($group['label']) ?></h2>
        <p class="muted"><?= e($group['help']) ?></p>
        <div class="grid">
          <?php foreach ($group['fields'] as $fid => $def): ?><?= cms_render_field('seo[' . $fid . ']', $def, $val($fid, $def)) ?><?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>
      <section class="panel">
        <h2>Arquivos para o Google</h2>
        <p class="muted">Gerados automaticamente, com todas as páginas publicadas. Envie o sitemap no Google Search Console.</p>
        <p><a href="<?= e(cms_url('sitemap.xml')) ?>" target="_blank" rel="noopener"><?= e(cms_site_url()) ?>/sitemap.xml</a><br>
           <a href="<?= e(cms_url('robots.txt')) ?>" target="_blank" rel="noopener"><?= e(cms_site_url()) ?>/robots.txt</a></p>
      </section>
      <div class="savebar savebar--sticky">
        <button class="btn btn--primary">Salvar SEO</button>
        <span class="savebar__hint" data-dirty-hint hidden>Há alterações não salvas.</span>
      </div>
    </form>
    <?php
    layout_end();
    exit;
}

/* ---------- Mídia ---------- */
if ($page === 'media') {
    require_perm('media');
    $items = cms_media_list();
    layout_start('Biblioteca de mídia', 'media');
    ?>
    <section class="panel">
      <form method="post" enctype="multipart/form-data">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="upload">
        <label class="drop" data-drop>
          <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" data-autosubmit>
          <span><strong>Enviar imagens</strong> ou arraste para cá. JPG e PNG são convertidos para WebP e reduzidos para no máximo <?= (int) cms_config('image_max_width', 2000) ?> px de largura.</span>
        </label>
      </form>
    </section>
    <section class="panel">
      <h2>Imagens</h2>
      <?php if (!$items): ?><p class="muted">Nenhuma imagem ainda. Envie a primeira acima.</p><?php endif; ?>
      <ul class="media">
      <?php foreach ($items as $m): $usage = cms_media_usage($m['path']); ?>
        <li class="media__item">
          <a href="<?= e($m['url']) ?>" target="_blank" rel="noopener" class="media__thumb"><img src="<?= e($m['url']) ?>" alt="" loading="lazy"></a>
          <div class="media__info">
            <strong title="<?= e($m['path']) ?>"><?= e(basename($m['path'])) ?></strong>
            <small><?= $m['w'] ?> × <?= $m['h'] ?> px · <?= cms_format_bytes($m['size']) ?><?= $m['theme'] ? ' · imagem do tema' : '' ?></small>
            <?php if ($usage): ?><small class="ok">Em uso: <?= e(implode(', ', $usage)) ?></small><?php endif; ?>
            <form method="post" class="media__alt">
              <?= cms_csrf_field() ?><input type="hidden" name="action" value="alt"><input type="hidden" name="path" value="<?= e($m['path']) ?>">
              <label class="sr" for="alt_<?= e(md5($m['path'])) ?>">Texto alternativo</label>
              <input id="alt_<?= e(md5($m['path'])) ?>" name="alt" value="<?= e($m['alt']) ?>" placeholder="Texto alternativo sugerido">
              <button class="btn btn--small btn--ghost">Salvar alt</button>
            </form>
            <div class="media__actions">
              <button type="button" class="btn btn--small btn--ghost" data-copy="<?= e(cms_abs_url($m['path'])) ?>">Copiar link</button>
              <?php if (!$m['theme']): ?>
              <form method="post" data-confirm="<?= e($usage ? 'Esta imagem está em uso em: ' . implode(', ', $usage) . '. Excluir mesmo assim? O site ficará sem ela.' : 'Excluir esta imagem permanentemente?') ?>">
                <?= cms_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="path" value="<?= e($m['path']) ?>">
                <button class="btn btn--small btn--danger">Excluir</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
      </ul>
    </section>
    <?php
    layout_end();
    exit;
}

/* ---------- Revisões ---------- */
if ($page === 'revisions') {
    require_perm('admin');
    $revs = cms_revisions();
    layout_start('Revisões e backup', 'revisions');
    ?>
    <section class="panel">
      <h2>Backup</h2>
      <p class="muted">Baixe todo o conteúdo (páginas, configurações, SEO e módulos) em um arquivo .json, ou importe um backup. As imagens ficam na pasta <code>uploads/</code> e os leads em <code>data/</code>.</p>
      <div class="row">
        <a class="btn" href="<?= e(admin_url('export')) ?>">Baixar backup</a>
        <form method="post" enctype="multipart/form-data" class="row" data-confirm="Importar este backup vai substituir o conteúdo atual (a versão atual fica salva nas revisões). Continuar?">
          <?= cms_csrf_field() ?><input type="hidden" name="action" value="import">
          <input type="file" name="backup" accept="application/json,.json" required aria-label="Arquivo de backup">
          <button class="btn btn--ghost">Importar backup</button>
        </form>
      </div>
    </section>
    <section class="panel">
      <h2>Revisões</h2>
      <p class="muted">A cada gravação, a versão anterior é guardada. São mantidas as <?= (int) cms_config('max_revisions', 30) ?> mais recentes.</p>
      <?php if (!$revs): ?><p class="muted">Ainda não há revisões. Elas aparecem depois da segunda gravação.</p><?php else: ?>
      <table class="table">
        <thead><tr><th>Data</th><th>Antes de alterar</th><th>Usuário</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($revs as $r): ?>
          <tr>
            <td><?= e(cms_format_date($r['date'], 'd/m/Y H:i:s')) ?></td>
            <td><?= e($r['note'] ?: '—') ?></td>
            <td><?= e($r['user']) ?></td>
            <td><form method="post" data-confirm="Restaurar esta versão? O conteúdo atual será guardado como revisão."><?= cms_csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="file" value="<?= e($r['file']) ?>"><button class="btn btn--small btn--ghost">Restaurar</button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
    <?php
    layout_end();
    exit;
}

/* ---------- Conta ---------- */
if ($page === 'account') {
    $users = cms_users();
    $me    = $_SESSION['cms_user'];
    layout_start(cms_is_admin() ? 'Usuários e senha' : 'Minha senha', 'account');
    ?>
    <section class="panel">
      <h2>Alterar minha senha</h2>
      <form method="post" class="grid grid--narrow">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="field"><label for="cur">Senha atual</label><input id="cur" type="password" name="current" required autocomplete="current-password"></div>
        <div class="field"><label for="n1">Nova senha</label><input id="n1" type="password" name="new" required autocomplete="new-password"><p class="help">Mínimo de 10 caracteres, com letras e números.</p></div>
        <div class="field"><label for="n2">Repita a nova senha</label><input id="n2" type="password" name="new2" required autocomplete="new-password"></div>
        <div><button class="btn btn--primary">Alterar senha</button></div>
      </form>
    </section>
    <?php cms_do_action('admin_account'); ?>
    <?php if (cms_is_admin()): ?>
    <section class="panel">
      <h2>Usuários</h2>
      <p class="muted">O <strong>Administrador</strong> acessa tudo. O <strong>Editor</strong> vê só as áreas e páginas que você liberar em “Permissões”.</p>
      <table class="table">
        <thead><tr><th>Usuário</th><th>Perfil</th><th>Acesso</th><th>Último acesso</th><th></th></tr></thead>
        <tbody>
        <?php $catalog = cms_permission_catalog(); foreach ($users as $u => $info):
            $areas = (array) ($info['perms']['areas'] ?? []);
            $pg = $info['perms']['pages'] ?? 'all'; ?>
          <tr>
            <td><strong><?= e($u) ?></strong><?= $u === $me ? ' <em class="tag">você</em>' : '' ?></td>
            <td><em class="tag <?= $info['role'] === 'admin' ? 'tag--on' : '' ?>"><?= e(cms_role_label($info['role'])) ?></em></td>
            <td class="small"><?php if ($info['role'] === 'admin'): ?>Tudo<?php else: ?>
              <?= e(implode(', ', array_map(fn ($a) => $catalog[$a][0] ?? $a, $areas)) ?: 'Nenhuma área') ?>
              <?php if (in_array('pages.edit', $areas, true)): ?><br><span class="muted"><?= $pg === 'all' ? 'Todas as páginas' : count((array) $pg) . ' página(s)' ?></span><?php endif; ?>
            <?php endif; ?></td>
            <td><?= e(cms_format_date($info['last_login'] ?? null)) ?></td>
            <td class="row row--end">
              <a class="btn btn--small" href="<?= e(admin_url('user', ['u' => $u])) ?>">Permissões</a>
              <?php if ($u !== $me): ?><form method="post" data-confirm="Excluir o usuário <?= e($u) ?>?"><?= cms_csrf_field() ?><input type="hidden" name="action" value="remove_user"><input type="hidden" name="user" value="<?= e($u) ?>"><button class="btn btn--small btn--danger">Excluir</button></form><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <h3>Adicionar usuário</h3>
      <form method="post" class="grid grid--narrow grid--four">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="add_user">
        <div class="field"><label for="nu">Usuário</label><input id="nu" name="user" required autocomplete="off"></div>
        <div class="field"><label for="np">Senha</label><input id="np" type="password" name="pass" required autocomplete="new-password"></div>
        <div class="field"><label for="nr">Perfil</label><select id="nr" name="role"><option value="editor">Editor</option><option value="admin">Administrador</option></select></div>
        <div><button class="btn">Adicionar usuário</button></div>
      </form>
    </section>
    <?php endif; ?>
    <?php
    layout_end();
    exit;
}

/* ---------- Permissões de um usuário ---------- */
if ($page === 'user') {
    require_perm('admin');
    $u     = (string) ($_GET['u'] ?? '');
    $users = cms_users();
    if (!isset($users[$u])) {
        redirect(admin_url('account'));
    }
    $info    = $users[$u];
    $areas   = (array) ($info['perms']['areas'] ?? []);
    $pagesSel = $info['perms']['pages'] ?? 'all';
    $catalog = cms_permission_catalog();
    layout_start('Permissões: ' . $u, 'user');
    ?>
    <form method="post" action="<?= e(admin_url('account')) ?>" class="form" data-dirty-check data-perm-form>
      <?= cms_csrf_field() ?><input type="hidden" name="action" value="save_user"><input type="hidden" name="user" value="<?= e($u) ?>">
      <section class="panel">
        <h2>Perfil</h2>
        <div class="roles">
          <label class="role"><input type="radio" name="role" value="admin" <?= $info['role'] === 'admin' ? 'checked' : '' ?> data-role><span><strong>Administrador</strong><small>Acessa tudo: módulos, usuários, revisões, conexões com Google e Claude.</small></span></label>
          <label class="role"><input type="radio" name="role" value="editor" <?= $info['role'] === 'editor' ? 'checked' : '' ?> data-role><span><strong>Editor</strong><small>Acessa só o que você marcar abaixo. Nunca vê módulos, usuários nem revisões.</small></span></label>
        </div>
      </section>
      <section class="panel" data-editor-only>
        <h2>Áreas liberadas</h2>
        <div class="perms">
          <?php foreach ($catalog as $key => [$label, $help]): ?>
            <label class="perm"><input type="checkbox" name="areas[]" value="<?= e($key) ?>" <?= in_array($key, $areas, true) ? 'checked' : '' ?> <?= $key === 'pages.edit' ? 'data-pages-edit' : '' ?>>
              <span><strong><?= e($label) ?></strong><small><?= e($help) ?></small></span></label>
          <?php endforeach; ?>
        </div>
      </section>
      <section class="panel" data-editor-only data-pages-box>
        <h2>Páginas que pode editar</h2>
        <div class="roles">
          <label class="role"><input type="radio" name="pages_mode" value="all" <?= $pagesSel === 'all' ? 'checked' : '' ?> data-pages-mode><span><strong>Todas as páginas</strong><small>Inclui páginas criadas no futuro.</small></span></label>
          <label class="role"><input type="radio" name="pages_mode" value="some" <?= $pagesSel !== 'all' ? 'checked' : '' ?> data-pages-mode><span><strong>Só as páginas escolhidas</strong><small>Marque abaixo.</small></span></label>
        </div>
        <div class="perms perms--pages" data-pages-list>
          <?php foreach (cms_pages(true) as $pgx): ?>
            <label class="perm"><input type="checkbox" name="pages[]" value="<?= e($pgx['id']) ?>" <?= $pagesSel !== 'all' && in_array($pgx['id'], (array) $pagesSel, true) ? 'checked' : '' ?>>
              <span><strong><?= e($pgx['title']) ?></strong><small><?= e(cms_page_path($pgx)) ?></small></span></label>
          <?php endforeach; ?>
        </div>
      </section>
      <div class="savebar savebar--sticky">
        <button class="btn btn--primary">Salvar permissões</button>
        <a class="btn btn--ghost" href="<?= e(admin_url('account')) ?>">Voltar</a>
        <span class="savebar__hint" data-dirty-hint hidden>Há alterações não salvas.</span>
      </div>
    </form>
    <?php
    layout_end();
    exit;
}

redirect(admin_url());
