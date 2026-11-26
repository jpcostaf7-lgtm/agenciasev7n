<?php
/**
 * Módulo Claude — assistente no painel e telas de conexão
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

const S7X_ANTHROPIC_VERSION = '2023-06-01';

function s7x_claude_boot(array $mod): void
{
    // Rotas públicas: /mcp, /oauth/*, /.well-known/*
    cms_add_action('request', 's7x_claude_route', 1);

    cms_register_admin_page('assistente', [
        'label'     => 'Assistente Claude',
        'order'     => 20,
        'icon'      => 'neuronio',
        'group'     => 'Conteúdo',
        'perm_help' => 'Conversar com o Claude para editar o site. Ele só faz o que este usuário já pode fazer.',
        'render'    => 's7x_claude_chat_render',
    ]);
    cms_register_admin_page('claude', [
        'label'     => 'Conectar o Claude',
        'icon'      => 'escudo',
        'group'     => 'Configurações',
        'perm_help' => 'Conectar o Claude.ai, Claude Desktop ou Claude Code a este site com o próprio usuário (tokens e autorizações).',
        'render'    => 's7x_claude_connect_render',
        'handle'    => 's7x_claude_connect_handle',
    ]);

    cms_add_action('admin_api:claude_chat', 's7x_claude_chat_api');
    cms_add_action('admin_api:claude_undo', 's7x_claude_undo_api');
    cms_add_action('admin_head', function () {
        echo '<link rel="stylesheet" href="' . e(cms_module_url('claude', 'assets/claude.css')) . '">';
    });
}

function s7x_claude_api_base(): string
{
    // Pode ser trocado por proxy corporativo definindo 'anthropic_base' em cms/config.php
    return rtrim((string) cms_config('anthropic_base', 'https://api.anthropic.com'), '/');
}

/* -------------------------------------------------------------------------
 * Chat
 * ---------------------------------------------------------------------- */

function s7x_claude_tools_payload(): array
{
    $tools = [];
    foreach (cms_ai_tools() as $name => $t) {
        $tools[] = ['name' => $name, 'description' => $t['description'], 'input_schema' => $t['input_schema']];
    }
    if ($tools) {
        $tools[count($tools) - 1]['cache_control'] = ['type' => 'ephemeral'];
    }
    return $tools;
}

/** Valida o histórico enviado pelo navegador. */
function s7x_claude_clean_messages($raw): array
{
    $msgs = [];
    foreach ((array) $raw as $m) {
        if (!is_array($m) || !in_array($m['role'] ?? '', ['user', 'assistant'], true)) {
            continue;
        }
        $content = $m['content'] ?? '';
        if (is_string($content)) {
            $content = mb_substr($content, 0, 20000);
        } elseif (is_array($content)) {
            $ok = [];
            foreach ($content as $blk) {
                if (is_array($blk) && in_array($blk['type'] ?? '', ['text', 'tool_use', 'tool_result'], true)) {
                    $ok[] = $blk;
                }
            }
            $content = $ok;
        } else {
            continue;
        }
        $msgs[] = ['role' => $m['role'], 'content' => $content];
    }
    return array_slice($msgs, -60);
}

function s7x_claude_error_text(array $r): string
{
    $st  = (int) $r['status'];
    $msg = (string) ($r['json']['error']['message'] ?? '');
    if ($st === 0) {
        return 'Não foi possível falar com a API da Anthropic (' . ($r['error'] ?: 'sem resposta') . '). O servidor precisa ter saída para api.anthropic.com.';
    }
    if ($st === 401) {
        return 'A chave da API da Anthropic foi recusada. Confira em Conectar o Claude.';
    }
    if ($st === 429) {
        return 'Limite de uso da API atingido. Aguarde um pouco e tente de novo.';
    }
    if ($st === 529 || $st >= 500) {
        return 'A API da Anthropic está sobrecarregada no momento. Tente de novo em instantes.';
    }
    return 'Erro da API (' . $st . ')' . ($msg ? ': ' . $msg : '.');
}

/** Uma etapa da conversa: chama o modelo uma vez e executa as ferramentas pedidas. */
function s7x_claude_chat_api(): void
{
    if (!cms_can('page.assistente') || !cms_module_get('claude', 'chat_enabled', true)) {
        json_out(['ok' => false, 'error' => 'O assistente não está liberado para o seu usuário.'], 403);
    }
    $key = cms_secret_get('anthropic_api_key');
    if ($key === '') {
        json_out(['ok' => false, 'error' => 'Falta a chave da API da Anthropic. O administrador configura em Conectar o Claude.'], 400);
    }
    @set_time_limit(150);
    $messages = s7x_claude_clean_messages(json_decode((string) ($_POST['messages'] ?? '[]'), true));
    if (!$messages || end($messages)['role'] !== 'user') {
        json_out(['ok' => false, 'error' => 'Mensagem vazia.'], 400);
    }

    $u = cms_acting_user();
    $system = cms_ai_instructions() . "\nData de hoje: " . date('d/m/Y') . '. Endereço do site: ' . cms_site_url() . '.';
    $ctx = trim((string) ($_POST['context'] ?? ''));
    if ($ctx !== '') {
        $system .= "\nA pessoa está no painel, na tela: " . mb_substr(strip_tags($ctx), 0, 200) . '.';
    }
    $body = [
        'model'      => (string) cms_module_get('claude', 'model', 'claude-sonnet-5-5'),
        'max_tokens' => max(512, min(32000, (int) cms_module_get('claude', 'max_tokens', 4096))),
        'system'     => [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]],
        'tools'      => s7x_claude_tools_payload(),
        'messages'   => $messages,
        'metadata'   => ['user_id' => hash('sha256', ($u['name'] ?? '') . cms_secret())],
    ];
    $r = cms_http('POST', s7x_claude_api_base() . '/v1/messages', [
        'x-api-key: ' . $key,
        'anthropic-version: ' . S7X_ANTHROPIC_VERSION,
    ], $body, 120);
    if ($r['status'] !== 200 || !is_array($r['json'])) {
        json_out(['ok' => false, 'error' => s7x_claude_error_text($r)], 502);
    }
    $resp = $r['json'];
    $assistant = array_values(array_filter((array) ($resp['content'] ?? []), fn ($b) => in_array($b['type'] ?? '', ['text', 'tool_use'], true)));
    $messages[] = ['role' => 'assistant', 'content' => $assistant];

    $actions = [];
    $undo    = null;
    if (($resp['stop_reason'] ?? '') === 'tool_use') {
        $before  = cms_revisions()[0]['file'] ?? '';
        $results = [];
        foreach ($assistant as $blk) {
            if (($blk['type'] ?? '') !== 'tool_use') {
                continue;
            }
            $name = (string) $blk['name'];
            $out  = cms_ai_execute($name, (array) ($blk['input'] ?? []));
            $results[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $blk['id'],
                'content'     => mb_substr(json_encode($out['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 0, 60000),
                'is_error'    => !$out['ok'],
            ];
            $actions[] = ['tool' => $name, 'ok' => $out['ok'], 'write' => !empty(cms_ai_tools()[$name]['write']), 'label' => s7x_claude_tool_label($name, (array) ($blk['input'] ?? []), $out)];
        }
        $messages[] = ['role' => 'user', 'content' => $results];
        // Revisão mais antiga criada nesta etapa = estado antes das mudanças
        $new = [];
        foreach (cms_revisions() as $rev) {
            if ($rev['file'] === $before) {
                break;
            }
            $new[] = $rev['file'];
        }
        $undo = $new ? end($new) : null;
    }
    json_out([
        'ok'       => true,
        'messages' => $messages,
        'continue' => ($resp['stop_reason'] ?? '') === 'tool_use',
        'actions'  => $actions,
        'undo'     => $undo,
        'usage'    => $resp['usage'] ?? null,
    ]);
}

/** Texto curto para mostrar no chat o que a ferramenta fez. */
function s7x_claude_tool_label(string $name, array $in, array $out): string
{
    $labels = [
        'site_overview' => 'Leu a visão geral do site', 'list_block_types' => 'Consultou os tipos de bloco',
        'get_page' => 'Leu a página', 'update_block' => 'Alterou um bloco', 'add_block' => 'Adicionou um bloco',
        'move_block' => 'Mudou a ordem dos blocos', 'delete_block' => 'Excluiu um bloco', 'update_page' => 'Alterou a página',
        'create_page' => 'Criou uma página', 'get_settings' => 'Leu configurações', 'update_settings' => 'Alterou configurações',
        'get_seo' => 'Leu o SEO', 'update_seo' => 'Alterou o SEO', 'list_media' => 'Listou as imagens',
        'list_leads' => 'Leu os leads', 'mark_lead_read' => 'Marcou lead como lido',
    ];
    $l = $labels[$name] ?? ('Usou ' . $name);
    if (!empty($in['page']) && is_string($in['page'])) {
        $l .= ' (' . $in['page'] . ')';
    }
    if (!$out['ok']) {
        $l .= ' — não foi possível: ' . mb_strimwidth(is_string($out['result']) ? $out['result'] : 'erro', 0, 120, '…');
    }
    return $l;
}

/** Desfaz as alterações do assistente (restaura a revisão anterior a elas). */
function s7x_claude_undo_api(): void
{
    $file = basename((string) ($_POST['revision'] ?? ''));
    $u    = cms_acting_user();
    $meta = cms_read_json(CMS_DATA . '/revisions/index.json', []);
    $info = $meta[$file] ?? null;
    if (!$u || !$info || ($info['user'] ?? '') !== $u['name'] || strpos((string) ($info['note'] ?? ''), 'Claude:') !== 0 || strtotime((string) $info['date']) < time() - 86400) {
        json_out(['ok' => false, 'error' => 'Não é possível desfazer esta alteração por aqui. Um administrador pode restaurar em Revisões.'], 400);
    }
    $data = cms_read_json(CMS_DATA . '/revisions/' . $file, null);
    if (!is_array($data)) {
        json_out(['ok' => false, 'error' => 'Revisão não encontrada.'], 404);
    }
    cms_save_content($data, 'Desfazer assistente');
    json_out(['ok' => true]);
}

function s7x_claude_chat_render(): void
{
    $hasKey  = cms_secret_get('anthropic_api_key') !== '';
    $enabled = (bool) cms_module_get('claude', 'chat_enabled', true);
    $model   = (string) cms_module_get('claude', 'model', 'claude-sonnet-5-5');
    if (!$enabled) {
        echo '<div class="notice notice--warn">O assistente está desligado nas configurações do módulo Claude.</div>';
        return;
    }
    if (!$hasKey) {
        echo '<div class="notice notice--warn">Falta a chave da API da Anthropic. '
            . (cms_is_admin() ? '<a href="' . e(admin_url('claude')) . '">Configurar agora</a>' : 'Peça ao administrador para configurar.') . '</div>';
    }
    $suggest = [
        'Revise o SEO da página inicial e sugira melhorias',
        'Quais leads chegaram e ainda não foram lidos?',
        'Adicione uma pergunta frequente sobre o valor da consulta',
        'Deixe o texto do topo mais curto e direto',
    ];
    ?>
    <section class="panel chat" data-chat data-model="<?= e($model) ?>" data-site="<?= e(cms_url('')) ?>">
      <div class="chat__log" data-chat-log aria-live="polite">
        <div class="chat__empty" data-chat-empty>
          <p><strong>Peça o que precisa mudar no site.</strong> O Claude lê as páginas, altera blocos, textos e SEO e consulta os leads. Ele só faz o que o seu usuário pode fazer, e toda alteração pode ser desfeita.</p>
          <div class="chat__suggest">
            <?php foreach ($suggest as $sg): ?><button type="button" class="btn btn--small btn--ghost" data-chat-suggest><?= e($sg) ?></button><?php endforeach; ?>
          </div>
        </div>
      </div>
      <form class="chat__form" data-chat-form>
        <label class="sr" for="chat-input">Mensagem para o Claude</label>
        <textarea id="chat-input" rows="2" placeholder="Ex.: troque o título da seção de segurança por “Cuidado do início ao fim”" data-chat-input <?= $hasKey ? '' : 'disabled' ?>></textarea>
        <button class="btn btn--primary" data-chat-send <?= $hasKey ? '' : 'disabled' ?>>Enviar</button>
      </form>
      <div class="chat__foot">
        <span class="muted small">Modelo: <?= e($model) ?> · Enter envia, Shift+Enter quebra linha</span>
        <button type="button" class="btn btn--small btn--ghost" data-chat-reset>Nova conversa</button>
      </div>
    </section>
    <?php
    echo '<script src="' . e(cms_module_url('claude', 'assets/chat.js')) . '" defer></script>';
}

/* -------------------------------------------------------------------------
 * Tela "Conectar o Claude"
 * ---------------------------------------------------------------------- */

function s7x_claude_connect_handle(string $action): void
{
    $u = cms_acting_user();
    if ($action === 'save_key' && cms_is_admin()) {
        $key = trim((string) ($_POST['api_key'] ?? ''));
        if ($key === '' && !empty($_POST['remove'])) {
            cms_secret_set('anthropic_api_key', null);
            cms_flash('ok', 'Chave removida.');
        } elseif ($key !== '') {
            if (!preg_match('/^sk-ant-[A-Za-z0-9_\-]{20,}$/', $key)) {
                cms_flash('error', 'Isso não parece uma chave da Anthropic (começa com sk-ant-).');
                return;
            }
            cms_secret_set('anthropic_api_key', $key);
            cms_flash('ok', 'Chave da API salva. O assistente já pode ser usado.');
        }
    }
    if ($action === 'create_token') {
        $name = cms_clean_text((string) ($_POST['name'] ?? '')) ?: 'Claude Code';
        $tok  = s7x_oauth_issue_personal_token($u['name'], $name);
        $_SESSION['s7x_new_token'] = $tok;
        cms_flash('ok', 'Token criado. Copie agora: ele não será mostrado de novo.');
    }
    if ($action === 'revoke') {
        s7x_oauth_revoke((string) ($_POST['id'] ?? ''), $u['name'], cms_is_admin());
        cms_flash('ok', 'Acesso revogado.');
    }
}

function s7x_claude_connect_render(): void
{
    $u      = cms_acting_user();
    $mcpUrl = cms_site_url() . '/mcp';
    $newTok = $_SESSION['s7x_new_token'] ?? '';
    unset($_SESSION['s7x_new_token']);
    $key = cms_secret_get('anthropic_api_key');
    $grants = s7x_oauth_list_access($u['name'], cms_is_admin());
    ?>
    <?php if (cms_is_admin()): ?>
    <section class="panel">
      <h2>Chave da API (assistente do painel)</h2>
      <p class="muted">O assistente usa a API da Anthropic, cobrada na sua conta em <a href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a>. A chave fica em <code>data/secrets.json</code>, fora dos backups.</p>
      <form method="post" class="row">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="save_key">
        <label class="sr" for="ak">Chave da API</label>
        <input id="ak" name="api_key" type="password" autocomplete="off" placeholder="<?= $key ? e(cms_mask($key)) . ' (salva)' : 'sk-ant-...' ?>" style="flex:1;min-width:260px">
        <button class="btn btn--primary">Salvar chave</button>
      </form>
      <?php if ($key): ?>
      <form method="post" data-confirm="Remover a chave? O assistente para de funcionar." style="margin-top:.5rem">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="save_key"><input type="hidden" name="remove" value="1">
        <button class="btn btn--small btn--danger">Remover chave</button>
      </form>
      <?php endif; ?>
      <p class="help">Modelo, limite de resposta e liga/desliga ficam em <a href="<?= e(admin_url('module', ['m' => 'claude'])) ?>">Módulos → Claude → Configurar</a>.</p>
    </section>
    <?php endif; ?>

    <?php if (!cms_module_get('claude', 'mcp_enabled', true)): ?>
      <div class="notice notice--warn">O conector está desligado nas configurações do módulo Claude.</div>
    <?php else: ?>
    <section class="panel">
      <h2>Claude.ai e Claude Desktop</h2>
      <ol class="steps-list">
        <li>No Claude, abra <strong>Personalizar → Conectores</strong> e clique em <strong>Adicionar conector personalizado</strong>.</li>
        <li>Cole o endereço abaixo e confirme. Não precisa preencher Client ID.</li>
        <li>O Claude abre a tela de login deste painel. Entre com <strong>o seu usuário</strong> e autorize.</li>
      </ol>
      <div class="copybox"><code><?= e($mcpUrl) ?></code><button type="button" class="btn btn--small" data-copy="<?= e($mcpUrl) ?>">Copiar</button></div>
      <p class="help">O Claude passa a agir com as permissões de quem autorizou. Um Editor só altera o que o perfil dele permite.</p>
    </section>

    <section class="panel">
      <h2>Claude Code (terminal)</h2>
      <p class="muted">Crie um token pessoal e rode o comando no terminal. O token age com as permissões do seu usuário.</p>
      <?php if ($newTok): ?>
        <div class="notice notice--ok">Seu token (copie agora, ele não aparece de novo):</div>
        <div class="copybox"><code><?= e($newTok) ?></code><button type="button" class="btn btn--small" data-copy="<?= e($newTok) ?>">Copiar token</button></div>
        <?php $cmd = 'claude mcp add --transport http ' . cms_slug(cms_site_name()) . ' ' . $mcpUrl . ' --header "Authorization: Bearer ' . $newTok . '"'; ?>
        <div class="copybox"><code><?= e($cmd) ?></code><button type="button" class="btn btn--small" data-copy="<?= e($cmd) ?>">Copiar comando</button></div>
      <?php endif; ?>
      <form method="post" class="row">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="create_token">
        <label class="sr" for="tn">Nome do token</label>
        <input id="tn" name="name" placeholder="Nome (ex.: notebook do João)" style="flex:1;min-width:220px">
        <button class="btn">Criar token</button>
      </form>
    </section>

    <section class="panel">
      <h2>Acessos ativos</h2>
      <?php if (!$grants): ?><p class="muted">Nenhum Claude conectado ainda.</p><?php else: ?>
      <table class="table">
        <thead><tr><th>Acesso</th><th>Usuário</th><th>Criado</th><th>Último uso</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($grants as $g): ?>
          <tr><td><?= e($g['label']) ?> <em class="tag"><?= $g['kind'] === 'personal' ? 'token' : 'OAuth' ?></em></td><td><?= e($g['user']) ?></td>
            <td><?= e(cms_format_date($g['created'])) ?></td><td><?= e(cms_format_date($g['last_used'] ?? null)) ?></td>
            <td><form method="post" data-confirm="Revogar este acesso? O Claude conectado perde o acesso na hora."><?= cms_csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= e($g['id']) ?>"><button class="btn btn--small btn--danger">Revogar</button></form></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php
}
