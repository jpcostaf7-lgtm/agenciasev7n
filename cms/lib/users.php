<?php
/**
 * Sev7n Xpress — usuários, perfis (Admin/Editor) e permissões
 * Autor: João Costa
 */
declare(strict_types=1);

function cms_users(): array
{
    $users = cms_read_json(CMS_DATA . '/users.json', []);
    foreach ($users as $name => $u) {
        // Usuários da versão 1/2 viram administradores
        $users[$name]['role']  = in_array($u['role'] ?? '', ['admin', 'editor'], true) ? $u['role'] : 'admin';
        $users[$name]['perms'] = (array) ($u['perms'] ?? []);
    }
    return $users;
}

function cms_save_users(array $users): bool
{
    return cms_write_json(CMS_DATA . '/users.json', $users);
}

/** Usuário em nome de quem o código está agindo (sessão do painel, token do Claude…). */
function cms_set_acting_user(?string $user): void
{
    $GLOBALS['cms_acting_user'] = $user;
}

function cms_acting_user(): ?array
{
    $name = $GLOBALS['cms_acting_user'] ?? null;
    if (!$name) {
        return null;
    }
    $u = cms_users()[$name] ?? null;
    return $u ? $u + ['name' => $name] : null;
}

function cms_is_admin(): bool
{
    $u = cms_acting_user();
    return $u && $u['role'] === 'admin';
}

/**
 * Áreas que o Admin pode liberar para um Editor.
 * Chave => [rótulo, descrição]. Módulos acrescentam itens pelo filtro "permissions".
 */
function cms_permission_catalog(): array
{
    $cat = [
        'pages.edit'   => ['Editar páginas', 'Alterar textos, imagens e blocos das páginas liberadas abaixo.'],
        'pages.manage' => ['Criar e publicar páginas', 'Criar, duplicar e excluir páginas, mudar endereço e publicar/despublicar.'],
        'media'        => ['Biblioteca de mídia', 'Enviar, renomear o texto alternativo e excluir imagens.'],
        'seo'          => ['SEO e Google', 'Título, descrição, compartilhamento, dados estruturados e códigos de rastreamento.'],
    ];
    foreach (cms_settings_sections() as $sid => $sec) {
        $cat['settings.' . $sid] = [$sec['label'], $sec['description'] ?? ''];
    }
    foreach (cms_admin_pages() as $slug => $ap) {
        if (empty($ap['admin_only'])) {
            $cat[$ap['perm']] = [$ap['label'], $ap['perm_help'] ?? 'Tela ' . $ap['label'] . ' do painel.'];
        }
    }
    return cms_apply_filters('permissions', $cat);
}

/**
 * Pode fazer isso? Admin pode tudo. Editor só o que foi liberado.
 * Para 'pages.edit', informe a página para checar a lista de páginas liberadas.
 */
function cms_can(string $perm, ?string $pageId = null): bool
{
    $u = cms_acting_user();
    if (!$u) {
        return false;
    }
    if ($u['role'] === 'admin') {
        return true;
    }
    if ($perm === 'admin') {
        return false;
    }
    $areas = (array) ($u['perms']['areas'] ?? []);
    if (!in_array($perm, $areas, true)) {
        return false;
    }
    if ($pageId !== null && strpos($perm, 'pages.') === 0) {
        $allowed = $u['perms']['pages'] ?? 'all';
        if ($allowed !== 'all' && !in_array($pageId, (array) $allowed, true)) {
            return false;
        }
    }
    return true;
}

/** Páginas que o usuário atual pode editar. */
function cms_editable_pages(): array
{
    return array_filter(cms_pages(), fn ($p) => cms_can('pages.edit', (string) $p['id']));
}

function cms_role_label(string $role): string
{
    return $role === 'admin' ? 'Administrador' : 'Editor';
}
