<?php
/**
 * Sev7n Xpress — ferramentas para o Claude (usadas pelo chat do painel e pelo conector MCP)
 * Autor: João Costa
 *
 * Cada ferramenta respeita as permissões do usuário que está agindo
 * (o mesmo Admin ou Editor que entrou no painel ou autorizou o conector).
 * Toda alteração passa por cms_save_content(), então gera revisão e pode ser desfeita.
 */
declare(strict_types=1);

$GLOBALS['cms_ai_tools'] = [];

/**
 * Registra uma ferramenta.
 * $def: description, input_schema (JSON Schema), perm (string ou callable), run (callable(array $args)), write (bool)
 */
function cms_register_ai_tool(string $name, array $def): void
{
    $def += ['description' => '', 'input_schema' => ['type' => 'object', 'properties' => new stdClass()], 'perm' => null, 'write' => false];
    $GLOBALS['cms_ai_tools'][$name] = $def;
}

/** Ferramentas que o usuário atual pode usar, em ordem estável. */
function cms_ai_tools(): array
{
    if (empty($GLOBALS['cms_ai_tools_core'])) {
        $GLOBALS['cms_ai_tools_core'] = true;
        cms_ai_register_core_tools();
        cms_do_action('ai_tools');
    }
    $out = [];
    foreach ($GLOBALS['cms_ai_tools'] as $name => $t) {
        if (cms_ai_tool_allowed($t)) {
            $out[$name] = $t;
        }
    }
    ksort($out);
    return $out;
}

function cms_ai_tool_allowed(array $t): bool
{
    $p = $t['perm'];
    if ($p === null) {
        return cms_acting_user() !== null;
    }
    if (is_callable($p)) {
        return (bool) $p();
    }
    return cms_can((string) $p);
}

class CmsAiError extends RuntimeException
{
}

/** Executa uma ferramenta. Retorna ['ok' => bool, 'result' => mixed]. */
function cms_ai_execute(string $name, array $args): array
{
    require_once CMS_ROOT . '/cms/admin_lib.php';
    $tools = cms_ai_tools();
    if (!isset($tools[$name])) {
        return ['ok' => false, 'result' => 'Ferramenta indisponível ou sem permissão: ' . $name];
    }
    try {
        $res = ($tools[$name]['run'])($args);
        return ['ok' => true, 'result' => $res];
    } catch (CmsAiError $e) {
        return ['ok' => false, 'result' => $e->getMessage()];
    } catch (Throwable $e) {
        return ['ok' => false, 'result' => 'Erro interno: ' . $e->getMessage()];
    }
}

/** Instruções gerais para o modelo (chat e MCP). */
function cms_ai_instructions(): string
{
    $u = cms_acting_user();
    $site = cms_site_name();
    $role = $u ? cms_role_label($u['role']) . ' (' . $u['name'] . ')' : 'visitante';
    return "Você gerencia o site \"$site\" pelo Sev7n Xpress, um CMS de blocos. Responda em português do Brasil.\n"
        . "Você está agindo como $role; só use ferramentas disponíveis e respeite as permissões.\n"
        . "Fluxo recomendado: site_overview → get_page para ver blocos e ids → list_block_types para conhecer os campos → altere com update_block/add_block.\n"
        . "Em update_block, envie só os campos que mudam; repetidores (listas) são substituídos por inteiro, então envie a lista completa.\n"
        . "Campos richtext aceitam HTML simples (p, strong, em, ul, ol, li, a, h3, h4). Imagens usam caminhos da biblioteca (list_media).\n"
        . "Toda alteração gera uma revisão e pode ser desfeita no painel em Revisões. Antes de excluir páginas ou blocos, confirme com a pessoa.\n"
        . "Para conteúdo médico ou jurídico, não prometa resultados; mantenha o tom informativo.\n"
        . "Ao terminar, diga em poucas linhas o que mudou e em qual página.";
}

/* -------------------------------------------------------------------------
 * Auxiliares
 * ---------------------------------------------------------------------- */

function cms_ai_page_ref(array $args, string $key = 'page'): array
{
    $ref = trim((string) ($args[$key] ?? ''));
    $pages = cms_pages();
    if ($ref === '' || in_array(strtolower($ref), ['/', 'home', 'inicial', 'pagina inicial', 'página inicial'], true)) {
        $p = cms_home_page();
    } else {
        $p = $pages[$ref] ?? null;
        if (!$p) {
            foreach ($pages as $pg) {
                if (($pg['slug'] ?? '') === trim($ref, '/')) {
                    $p = $pg;
                }
            }
        }
    }
    if (!$p) {
        throw new CmsAiError("Página \"$ref\" não encontrada. Use site_overview para ver as páginas.");
    }
    return $p;
}

function cms_ai_require(string $perm, ?string $pageId = null): void
{
    if (!cms_can($perm, $pageId)) {
        throw new CmsAiError('Sem permissão para esta ação (' . $perm . ').');
    }
}

function cms_ai_field_schema(array $fields): array
{
    $out = [];
    foreach ($fields as $fid => $f) {
        $type = $f['type'] ?? 'text';
        $row = ['type' => $type, 'label' => $f['label'] ?? $fid];
        if (!empty($f['help'])) {
            $row['help'] = $f['help'];
        }
        if ($type === 'select') {
            $row['options'] = array_keys($f['options'] ?? []);
        }
        if ($type === 'icon') {
            $row['options'] = array_keys(cms_icons());
        }
        if ($type === 'image') {
            $row['format'] = '{"src": "uploads/... ou themes/<tema>/assets/...", "alt": "texto alternativo"}';
        }
        if ($type === 'repeater') {
            $row['item_fields'] = cms_ai_field_schema($f['fields'] ?? []);
        }
        $out[$fid] = $row;
    }
    return $out;
}

function cms_ai_block_view(array $bl): array
{
    return [
        'id'      => $bl['id'],
        'type'    => $bl['type'],
        'label'   => cms_block_type((string) $bl['type'])['label'] ?? $bl['type'],
        'visible' => !empty($bl['enabled']),
        'anchor'  => $bl['anchor'] ?? '',
        'data'    => cms_block_data($bl),
    ];
}

/** Valida e mescla dados de bloco usando as regras do painel. */
function cms_ai_clean_block_data(string $type, array $current, array $patch): array
{
    $def = cms_block_type($type);
    if (!$def) {
        throw new CmsAiError("Tipo de bloco \"$type\" não existe (ou o módulo dele está desativado).");
    }
    foreach ($patch as $fid => $value) {
        if (!isset($def['fields'][$fid])) {
            throw new CmsAiError("O bloco \"$type\" não tem o campo \"$fid\". Campos: " . implode(', ', array_keys($def['fields'])));
        }
        $current[$fid] = cms_clean_value($def['fields'][$fid], $value);
    }
    return $current;
}

function cms_ai_save_page(array $page, string $note): void
{
    $content = cms_content();
    $page['updated'] = date('c');
    $content['pages'][$page['id']] = $page;
    if (!cms_save_content($content, 'Claude: ' . $note)) {
        throw new CmsAiError('Não foi possível gravar (permissão da pasta data/).');
    }
}

/* -------------------------------------------------------------------------
 * Ferramentas do núcleo
 * ---------------------------------------------------------------------- */

function cms_ai_register_core_tools(): void
{
    $obj = fn (array $props, array $req = []) => ['type' => 'object', 'properties' => $props ?: new stdClass(), 'required' => $req];
    $str = fn (string $d) => ['type' => 'string', 'description' => $d];
    $pageArg = $str('Id da página, endereço (ex.: politica-de-privacidade) ou "home" para a inicial.');

    cms_register_ai_tool('site_overview', [
        'description' => 'Visão geral do site: páginas (id, título, endereço, status, blocos), configurações, módulos ativos e o que o usuário atual pode fazer.',
        'input_schema' => $obj([]),
        'run' => function () {
            $pages = [];
            foreach (cms_pages() as $p) {
                $pages[] = [
                    'id' => $p['id'], 'title' => $p['title'], 'path' => cms_page_path($p), 'status' => $p['status'] ?? 'published',
                    'can_edit' => cms_can('pages.edit', (string) $p['id']),
                    'blocks' => array_map(fn ($b) => ($b['type'] ?? '') . '#' . ($b['id'] ?? '') . (empty($b['enabled']) ? ' (oculto)' : ''), (array) ($p['blocks'] ?? [])),
                ];
            }
            $u = cms_acting_user();
            return [
                'site' => cms_site_name(), 'url' => cms_site_url(),
                'user' => ['name' => $u['name'] ?? '', 'role' => $u['role'] ?? ''],
                'permissions' => $u && $u['role'] === 'admin' ? 'todas' : ($u['perms']['areas'] ?? []),
                'multi_pages' => cms_multi_pages(),
                'pages' => $pages,
                'settings_sections' => array_map(fn ($s) => $s['label'], cms_settings_sections()),
                'active_modules' => array_values(array_map(fn ($m) => $m['name'], array_filter(cms_available_modules(), fn ($m) => cms_module_active($m['slug'])))),
            ];
        },
    ]);

    cms_register_ai_tool('list_block_types', [
        'description' => 'Lista os tipos de bloco disponíveis com seus campos (tipo, rótulo, opções). Use antes de criar ou alterar blocos.',
        'input_schema' => $obj(['type' => $str('Opcional: um tipo específico.')]),
        'perm' => 'pages.edit',
        'run' => function (array $a) {
            $out = [];
            foreach (cms_block_types() as $t => $def) {
                if (!empty($a['type']) && $a['type'] !== $t) {
                    continue;
                }
                $out[$t] = ['label' => $def['label'], 'description' => $def['description'], 'group' => $def['group'], 'fields' => cms_ai_field_schema($def['fields'])];
            }
            return $out;
        },
    ]);

    cms_register_ai_tool('get_page', [
        'description' => 'Lê uma página com todos os blocos (id, tipo, visível, âncora e dados de cada campo) e o SEO dela.',
        'input_schema' => $obj(['page' => $pageArg], ['page']),
        'perm' => 'pages.edit',
        'run' => function (array $a) {
            $p = cms_ai_page_ref($a);
            cms_ai_require('pages.edit', (string) $p['id']);
            return [
                'id' => $p['id'], 'title' => $p['title'], 'path' => cms_page_path($p), 'status' => $p['status'] ?? 'published',
                'seo' => (array) ($p['seo'] ?? []),
                'blocks' => array_map('cms_ai_block_view', (array) ($p['blocks'] ?? [])),
            ];
        },
    ]);

    cms_register_ai_tool('update_block', [
        'description' => 'Altera um bloco: campos em "data" (só os que mudam), visibilidade ("visible") e âncora ("anchor").',
        'input_schema' => $obj([
            'page' => $pageArg, 'block_id' => $str('Id do bloco (veja get_page).'),
            'data' => ['type' => 'object', 'description' => 'Campos a alterar, ex.: {"title": "Novo título"}.'],
            'visible' => ['type' => 'boolean'], 'anchor' => $str('Âncora sem #, ex.: contato'),
        ], ['page', 'block_id']),
        'perm' => 'pages.edit', 'write' => true,
        'run' => function (array $a) {
            $p = cms_ai_page_ref($a);
            cms_ai_require('pages.edit', (string) $p['id']);
            foreach ($p['blocks'] as $i => $bl) {
                if (($bl['id'] ?? '') !== ($a['block_id'] ?? '')) {
                    continue;
                }
                if (isset($a['data']) && is_array($a['data'])) {
                    $p['blocks'][$i]['data'] = cms_ai_clean_block_data((string) $bl['type'], cms_block_data($bl), $a['data']);
                }
                if (array_key_exists('visible', $a)) {
                    $p['blocks'][$i]['enabled'] = (bool) $a['visible'];
                }
                if (array_key_exists('anchor', $a)) {
                    $p['blocks'][$i]['anchor'] = cms_slug((string) $a['anchor']);
                }
                cms_ai_save_page($p, 'bloco ' . $bl['type'] . ' em ' . $p['title']);
                return ['updated' => cms_ai_block_view($p['blocks'][$i]), 'page_url' => cms_page_url($p, true)];
            }
            throw new CmsAiError('Bloco não encontrado nesta página. Use get_page para ver os ids.');
        },
    ]);

    cms_register_ai_tool('add_block', [
        'description' => 'Adiciona um bloco novo a uma página. Sem "position", entra no final. Campos não informados usam o padrão do tipo.',
        'input_schema' => $obj([
            'page' => $pageArg, 'type' => $str('Tipo do bloco (veja list_block_types).'),
            'data' => ['type' => 'object'], 'anchor' => $str('Âncora opcional.'),
            'position' => ['type' => 'integer', 'description' => 'Posição (0 = primeiro).'],
            'visible' => ['type' => 'boolean'],
        ], ['page', 'type']),
        'perm' => 'pages.edit', 'write' => true,
        'run' => function (array $a) {
            $p = cms_ai_page_ref($a);
            cms_ai_require('pages.edit', (string) $p['id']);
            $type = (string) $a['type'];
            $data = cms_ai_clean_block_data($type, cms_block_data(['type' => $type, 'data' => []]), (array) ($a['data'] ?? []));
            $bl = cms_new_block($type, $data, cms_slug((string) ($a['anchor'] ?? '')));
            $bl['enabled'] = !array_key_exists('visible', $a) || (bool) $a['visible'];
            $pos = isset($a['position']) ? max(0, min((int) $a['position'], count($p['blocks']))) : count($p['blocks']);
            array_splice($p['blocks'], $pos, 0, [$bl]);
            cms_ai_save_page($p, 'novo bloco ' . $type . ' em ' . $p['title']);
            return ['added' => cms_ai_block_view($bl), 'position' => $pos, 'page_url' => cms_page_url($p, true)];
        },
    ]);

    cms_register_ai_tool('move_block', [
        'description' => 'Muda a posição de um bloco na página (0 = primeiro).',
        'input_schema' => $obj(['page' => $pageArg, 'block_id' => $str('Id do bloco.'), 'position' => ['type' => 'integer']], ['page', 'block_id', 'position']),
        'perm' => 'pages.edit', 'write' => true,
        'run' => function (array $a) {
            $p = cms_ai_page_ref($a);
            cms_ai_require('pages.edit', (string) $p['id']);
            foreach ($p['blocks'] as $i => $bl) {
                if ($bl['id'] === ($a['block_id'] ?? '')) {
                    array_splice($p['blocks'], $i, 1);
                    $pos = max(0, min((int) $a['position'], count($p['blocks'])));
                    array_splice($p['blocks'], $pos, 0, [$bl]);
                    cms_ai_save_page($p, 'mover bloco em ' . $p['title']);
                    return ['order' => array_map(fn ($b) => $b['type'] . '#' . $b['id'], $p['blocks'])];
                }
            }
            throw new CmsAiError('Bloco não encontrado.');
        },
    ]);

    cms_register_ai_tool('delete_block', [
        'description' => 'Exclui um bloco da página. Para só esconder, prefira update_block com visible=false. Confirme com a pessoa antes.',
        'input_schema' => $obj(['page' => $pageArg, 'block_id' => $str('Id do bloco.')], ['page', 'block_id']),
        'perm' => 'pages.edit', 'write' => true,
        'run' => function (array $a) {
            $p = cms_ai_page_ref($a);
            cms_ai_require('pages.edit', (string) $p['id']);
            foreach ($p['blocks'] as $i => $bl) {
                if ($bl['id'] === ($a['block_id'] ?? '')) {
                    array_splice($p['blocks'], $i, 1);
                    cms_ai_save_page($p, 'excluir bloco ' . $bl['type'] . ' de ' . $p['title']);
                    return ['deleted' => $bl['type'] . '#' . $bl['id']];
                }
            }
            throw new CmsAiError('Bloco não encontrado.');
        },
    ]);

    cms_register_ai_tool('update_page', [
        'description' => 'Altera título, SEO (title, description, noindex), endereço (slug) ou status (published/draft) de uma página.',
        'input_schema' => $obj([
            'page' => $pageArg, 'title' => ['type' => 'string'], 'slug' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['published', 'draft']],
            'seo' => ['type' => 'object', 'description' => '{"title": "...", "description": "...", "noindex": false}'],
        ], ['page']),
        'perm' => 'pages.edit', 'write' => true,
        'run' => function (array $a) {
            $p = cms_ai_page_ref($a);
            cms_ai_require('pages.edit', (string) $p['id']);
            if (isset($a['title'])) {
                $p['title'] = cms_clean_text((string) $a['title']) ?: $p['title'];
            }
            if ((isset($a['slug']) || isset($a['status'])) && empty($p['home'])) {
                cms_ai_require('pages.manage');
                if (isset($a['slug'])) {
                    $slug = cms_slug((string) $a['slug']);
                    foreach (cms_pages(true) as $o) {
                        if ($o['id'] !== $p['id'] && ($o['slug'] ?? '') === $slug) {
                            throw new CmsAiError('Já existe uma página com o endereço /' . $slug);
                        }
                    }
                    if ($slug === '' || in_array($slug, cms_reserved_slugs(), true)) {
                        throw new CmsAiError('Endereço inválido.');
                    }
                    $p['slug'] = $slug;
                }
                if (isset($a['status'])) {
                    $p['status'] = $a['status'] === 'draft' ? 'draft' : 'published';
                }
            }
            if (isset($a['seo']) && is_array($a['seo'])) {
                foreach (cms_page_seo_fields() as $fid => $def) {
                    if (array_key_exists($fid, $a['seo'])) {
                        $p['seo'][$fid] = cms_clean_value($def, $a['seo'][$fid]);
                    }
                }
            }
            cms_ai_save_page($p, 'página ' . $p['title']);
            return ['page' => ['id' => $p['id'], 'title' => $p['title'], 'path' => cms_page_path($p), 'status' => $p['status'] ?? 'published', 'seo' => $p['seo'] ?? []]];
        },
    ]);

    cms_register_ai_tool('create_page', [
        'description' => 'Cria uma página nova (requer o módulo Páginas extras). Entra como rascunho com um bloco de título e um de texto, a menos que "status" seja published.',
        'input_schema' => $obj(['title' => ['type' => 'string'], 'slug' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['published', 'draft']]], ['title']),
        'perm' => fn () => cms_multi_pages() && cms_can('pages.manage'), 'write' => true,
        'run' => function (array $a) {
            $title = cms_clean_text((string) $a['title']) ?: 'Nova página';
            $slug  = cms_slug((string) ($a['slug'] ?? '')) ?: cms_slug($title);
            $taken = array_map(fn ($p) => $p['slug'] ?? '', cms_pages(true));
            $base = $slug;
            $n = 2;
            while (in_array($slug, $taken, true) || in_array($slug, cms_reserved_slugs(), true)) {
                $slug = $base . '-' . $n++;
            }
            $id = 'p' . bin2hex(random_bytes(4));
            $page = [
                'id' => $id, 'title' => $title, 'slug' => $slug, 'home' => false,
                'status' => ($a['status'] ?? '') === 'published' ? 'published' : 'draft', 'seo' => [], 'created' => date('c'),
                'blocks' => [cms_new_block('page-header', ['title' => $title]), cms_new_block('text')],
            ];
            cms_ai_save_page($page, 'nova página ' . $title);
            return ['created' => ['id' => $id, 'path' => '/' . $slug, 'status' => $page['status']], 'blocks' => array_map('cms_ai_block_view', $page['blocks'])];
        },
    ]);

    cms_register_ai_tool('get_settings', [
        'description' => 'Lê uma seção de configurações globais (ex.: contato, geral) com os campos e valores.',
        'input_schema' => $obj(['section' => $str('Id da seção: ' . implode(', ', array_keys(cms_settings_sections())))], ['section']),
        'perm' => fn () => (bool) array_filter(array_keys(cms_settings_sections()), fn ($s) => cms_can('settings.' . $s)),
        'run' => function (array $a) {
            $sid = (string) $a['section'];
            $sec = cms_settings_sections()[$sid] ?? null;
            if (!$sec) {
                throw new CmsAiError('Seção não existe.');
            }
            cms_ai_require('settings.' . $sid);
            $vals = [];
            foreach ($sec['fields'] as $fid => $f) {
                $vals[$fid] = cms_settings_value($sid, $fid, $f);
            }
            return ['section' => $sid, 'label' => $sec['label'], 'fields' => cms_ai_field_schema($sec['fields']), 'values' => $vals];
        },
    ]);

    cms_register_ai_tool('update_settings', [
        'description' => 'Altera campos de uma seção de configurações globais (envie só os campos que mudam).',
        'input_schema' => $obj(['section' => ['type' => 'string'], 'values' => ['type' => 'object']], ['section', 'values']),
        'perm' => fn () => (bool) array_filter(array_keys(cms_settings_sections()), fn ($s) => cms_can('settings.' . $s)), 'write' => true,
        'run' => function (array $a) {
            $sid = (string) $a['section'];
            $sec = cms_settings_sections()[$sid] ?? null;
            if (!$sec) {
                throw new CmsAiError('Seção não existe.');
            }
            cms_ai_require('settings.' . $sid);
            $content = cms_content();
            $cur = [];
            foreach ($sec['fields'] as $fid => $f) {
                $cur[$fid] = cms_settings_value($sid, $fid, $f);
            }
            foreach ((array) $a['values'] as $fid => $v) {
                if (!isset($sec['fields'][$fid])) {
                    throw new CmsAiError("Campo \"$fid\" não existe em $sid.");
                }
                $cur[$fid] = cms_clean_value($sec['fields'][$fid], $v);
            }
            $content['settings'][$sid] = $cur;
            cms_save_content($content, 'Claude: ' . $sec['label']);
            return ['saved' => array_keys((array) $a['values'])];
        },
    ]);

    cms_register_ai_tool('get_seo', [
        'description' => 'Lê o SEO geral do site e a análise com sugestões.',
        'input_schema' => $obj([]),
        'perm' => 'seo',
        'run' => function () {
            $vals = [];
            foreach (cms_seo_fields() as $g) {
                foreach ($g['fields'] as $fid => $f) {
                    if (($f['type'] ?? '') !== 'code') {
                        $vals[$fid] = cms_seo($fid);
                    }
                }
            }
            return ['values' => $vals, 'checks' => array_map(fn ($c) => ['status' => $c[0], 'message' => $c[1]], cms_seo_checks())];
        },
    ]);

    cms_register_ai_tool('update_seo', [
        'description' => 'Altera campos do SEO geral (ex.: title, description, focus_keyword, og_title, og_description, org_phone…).',
        'input_schema' => $obj(['values' => ['type' => 'object']], ['values']),
        'perm' => 'seo', 'write' => true,
        'run' => function (array $a) {
            $defs = [];
            foreach (cms_seo_fields() as $g) {
                $defs += $g['fields'];
            }
            $content = cms_content();
            foreach ((array) $a['values'] as $fid => $v) {
                if (!isset($defs[$fid]) || ($defs[$fid]['type'] ?? '') === 'code') {
                    throw new CmsAiError("Campo de SEO \"$fid\" não pode ser alterado por aqui.");
                }
                $content['seo'][$fid] = cms_clean_value($defs[$fid], $v);
            }
            cms_save_content($content, 'Claude: SEO');
            return ['saved' => array_keys((array) $a['values']), 'checks' => array_map(fn ($c) => $c[1], cms_seo_checks())];
        },
    ]);

    cms_register_ai_tool('list_media', [
        'description' => 'Lista as imagens da biblioteca (caminho, tamanho, texto alternativo).',
        'input_schema' => $obj([]),
        'perm' => fn () => cms_can('media') || cms_can('pages.edit'),
        'run' => fn () => array_map(fn ($m) => ['path' => $m['path'], 'alt' => $m['alt'], 'size' => $m['w'] . 'x' . $m['h']], cms_media_list()),
    ]);
}
