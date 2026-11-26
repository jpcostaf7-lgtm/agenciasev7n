<?php
/**
 * Módulo Artigos com IA — telas do painel
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_ia_admin_boot(): void
{
    cms_register_admin_page('blog-ia', [
        'label' => 'Artigos com IA', 'icon' => 'neuronio', 'order' => 6,
        'perm_help' => 'Gerar artigos com o Claude e configurar a automação.',
        'render' => 's7x_ia_render', 'handle' => 's7x_ia_handle',
    ]);
    $api = function (string $name, callable $fn) {
        cms_add_action('admin_api:' . $name, function () use ($fn) {
            require_perm('page.blog-ia');
            if (function_exists('set_time_limit')) {
                @set_time_limit(400);
            }
            @ignore_user_abort(true);
            try {
                json_out(['ok' => true] + $fn());
            } catch (Throwable $e) {
                s7x_ia_log($e->getMessage(), 'erro');
                json_out(['ok' => false, 'error' => $e->getMessage()], 400);
            }
        });
    };
    $api('ia_generate', function () {
        $cat = cms_slug((string) ($_POST['category'] ?? ''));
        $res = s7x_ia_generate((string) ($_POST['topic'] ?? ''), [
            'keywords' => (string) ($_POST['keywords'] ?? ''), 'words' => (int) ($_POST['words'] ?? 0),
            'extra' => (string) ($_POST['extra'] ?? ''), 'category' => $cat, 'base' => (string) ($_POST['base'] ?? ''),
        ]);
        $id = s7x_ia_create_post($res, $cat, (string) ($_POST['status'] ?? 'draft'), 'painel');
        $p = s7x_blog_get($id);
        s7x_ia_log('Artigo gerado: ' . $p['title'], 'ok');
        return ['id' => $id, 'title' => $p['title'], 'status' => s7x_blog_status_label($p), 'edit' => admin_url('blog', ['edit' => $id]), 'url' => s7x_blog_post_url($p), 'image' => cms_image_value($p['image'])['src'] !== '' ? cms_url($p['image']['src']) : '', 'words' => (int) $p['words']];
    });
    $api('ia_suggest', function () {
        return ['topics' => s7x_ia_suggest(cms_slug((string) ($_POST['category'] ?? '')), (int) ($_POST['count'] ?? 10))];
    });
    $api('ia_test', function () {
        $t = s7x_ia_request('Responda apenas: OK', 'Teste de conexão.', 10);
        return ['message' => 'Conectado ao ' . s7x_ia_models()[(string) s7x_ia('model')] . '. Resposta: ' . trim($t)];
    });
    $api('ia_pixabay', function () {
        $hits = s7x_ia_pixabay_search('ocean ship');
        return ['message' => 'Pixabay funcionando: ' . count($hits) . ' fotos encontradas no teste.'];
    });
    $api('ia_auto_now', function () {
        $id = s7x_ia_auto_one('botão');
        $p = s7x_blog_get($id);
        return ['message' => 'Artigo criado: ' . $p['title'], 'edit' => admin_url('blog', ['edit' => $id])];
    });
    $api('ia_import_now', function () {
        [$imp, $skip] = s7x_ia_import_run();
        return ['message' => $imp . ' artigo(s) importado(s), ' . $skip . ' já existiam.'];
    });
    $api('ia_net_test', function () {
        $items = s7x_ia_net_posts(max(1, (int) s7x_ia('net_count')));
        return ['message' => $items ? count($items) . ' artigo(s) da rede: ' . implode(' · ', array_map(fn ($i) => $i['site'] . ': ' . $i['title'], $items)) : 'Nenhum artigo encontrado. Confira os endereços dos sites.'];
    });
    $api('ia_images', function () {
        $id = (string) ($_POST['id'] ?? '');
        $post = s7x_blog_get($id);
        if (!$post) {
            throw new RuntimeException('Post não encontrado.');
        }
        $q = s7x_ia_queries_for($post);
        $n = s7x_ia_attach_images($id, $q, '', true, !empty($_POST['inline']));
        return ['message' => $n ? $n . ' imagem(ns) adicionada(s). Recarregando…' : 'Nenhuma imagem nova (o post já tem imagem ou não achei fotos novas).', 'added' => $n];
    });
    cms_add_action('blog_editor_side', function (array $post) {
        if (!$post['id'] || s7x_ia_pixabay_key() === '' || !cms_can('page.blog-ia')) {
            return;
        }
        echo '<section class="panel"><h2>Fotos do Pixabay</h2><p class="small muted">Busca uma foto que combine com o título, com o crédito do fotógrafo.</p>'
            . '<label class="blog-checkline small"><input type="checkbox" data-ia-inline> Também uma foto no meio do texto</label>'
            . '<p><button type="button" class="btn btn--small" data-ia-images="' . e($post['id']) . '">Buscar imagem</button></p><p class="small" data-ia-images-msg aria-live="polite"></p></section>';
    });
    cms_add_action('admin_head', function () {
        if (in_array((string) ($_GET['p'] ?? ''), ['blog-ia', 'blog'], true)) {
            echo '<link rel="stylesheet" href="' . e(cms_module_url('blog-ia', 'assets/ia.css')) . '">';
            if (($_GET['p'] ?? '') === 'blog-ia') {
                echo '<link rel="stylesheet" href="' . e(cms_module_url('blog', 'assets/blog-admin.css')) . '">';
            }
        }
    });
    cms_add_action('admin_footer', function () {
        if (in_array((string) ($_GET['p'] ?? ''), ['blog-ia', 'blog'], true)) {
            echo '<script src="' . e(cms_module_url('blog-ia', 'assets/ia.js')) . '"></script>';
        }
    });
}

/* -------------------------------------------------------------------------
 * Salvar configurações
 * ---------------------------------------------------------------------- */

function s7x_ia_handle(string $action): void
{
    if ($action !== 'save') {
        return;
    }
    $groups = array_intersect((array) ($_POST['groups'] ?? []), array_keys(s7x_ia_fields()));
    $c = cms_content();
    $in = (array) ($_POST['ia'] ?? []);
    foreach ($groups as $g) {
        foreach (s7x_ia_fields()[$g]['fields'] as $k => $def) {
            $c['modules']['blog-ia'][$k] = cms_clean_value($def, $in[$k] ?? (($def['type'] ?? '') === 'toggle' ? false : ''));
        }
    }
    if (in_array('automacao', $groups, true)) {
        $c['modules']['blog-ia']['auto_cats'] = array_values(array_filter(array_map('cms_slug', (array) ($_POST['auto_cats'] ?? []))));
    }
    cms_save_content($c, 'Artigos com IA: configurações');
    foreach (['anthropic_api_key' => 'key', 'anthropic_workspace_id' => 'ws', 'pixabay_key' => 'pxb'] as $secret => $field) {
        if (!isset($_POST['secret'][$field])) {
            continue;
        }
        $v = trim((string) $_POST['secret'][$field]);
        if (!empty($_POST['secret_clear'][$field])) {
            cms_secret_set($secret, null);
        } elseif ($v !== '' && strpos($v, '•') === false) {
            cms_secret_set($secret, $v);
        }
    }
    cms_flash('ok', 'Configurações salvas.');
}

/* -------------------------------------------------------------------------
 * Telas
 * ---------------------------------------------------------------------- */

function s7x_ia_fields_html(string $group): string
{
    $h = '<input type="hidden" name="groups[]" value="' . e($group) . '"><div class="grid">';
    foreach (s7x_ia_fields()[$group]['fields'] as $k => $def) {
        $h .= cms_render_field('ia[' . $k . ']', $def, s7x_ia($k));
    }
    return $h . '</div>';
}

function s7x_ia_secret_field(string $field, string $label, string $value, string $help, string $placeholder = ''): string
{
    $id = 'sec-' . $field;
    return '<div class="field"><label for="' . $id . '">' . e($label) . '</label>'
        . '<input id="' . $id . '" type="password" autocomplete="off" name="secret[' . e($field) . ']" value="' . ($value !== '' ? e(cms_mask($value)) : '') . '" placeholder="' . e($placeholder) . '">'
        . '<span class="help">' . $help . '</span>'
        . ($value !== '' ? '<label class="blog-checkline small"><input type="checkbox" name="secret_clear[' . e($field) . ']" value="1"> Apagar a chave salva</label>' : '')
        . '</div>';
}

function s7x_ia_cat_select(string $name, string $sel = '', string $empty = 'Sem categoria'): string
{
    $h = '<select name="' . e($name) . '" id="' . e(cms_field_id($name)) . '"><option value="">' . e($empty) . '</option>';
    foreach (s7x_ia_categories() as $c) {
        $h .= '<option value="' . e($c['slug']) . '"' . ($sel === $c['slug'] ? ' selected' : '') . '>' . e($c['name']) . '</option>';
    }
    return $h . '</select>';
}

function s7x_ia_render(): void
{
    $tab = in_array($_GET['tab'] ?? '', ['gerar', 'lote', 'automacao', 'config', 'registro'], true) ? $_GET['tab'] : 'gerar';
    $hasKey = s7x_ia_key() !== '';
    $tabs = ['gerar' => 'Gerar artigo', 'lote' => 'Gerar em lote', 'automacao' => 'Automação', 'config' => 'Configurações', 'registro' => 'Registro'];
    echo '<nav class="tabs" aria-label="Seções">';
    foreach ($tabs as $k => $l) {
        echo '<a href="' . e(admin_url('blog-ia', ['tab' => $k])) . '" class="' . ($tab === $k ? 'is-active' : '') . '">' . e($l) . '</a>';
    }
    echo '</nav>';
    if (!$hasKey && $tab !== 'config') {
        echo '<div class="notice notice--warn">Falta a chave da API do Claude. Cadastre em <a href="' . e(admin_url('blog-ia', ['tab' => 'config'])) . '">Configurações</a>.</div>';
    }
    if (!s7x_ia_categories()) {
        echo '<div class="notice notice--warn">O blog ainda não tem categorias. Crie em <a href="' . e(admin_url('blog-terms')) . '">Categorias e tags</a> ou importe os posts do WordPress.</div>';
    }
    $fn = 's7x_ia_tab_' . $tab;
    $fn();
}

function s7x_ia_tab_gerar(): void
{
    ?>
    <section class="panel">
      <h2>Gerar um artigo</h2>
      <p class="muted">A IA lê os títulos que já existem na categoria para não repetir assunto. O artigo entra no blog com imagem, SEO, links internos e perguntas frequentes.</p>
      <form class="form" data-ia-gen>
        <div class="grid">
          <div class="field"><label for="ia-cat">Categoria</label><?= s7x_ia_cat_select('category') ?></div>
          <div class="field"><label for="ia-status">Salvar como</label><select id="ia-status" name="status"><option value="draft">Rascunho (revisar antes)</option><option value="publish">Publicar direto</option></select></div>
          <div class="field field--wide"><label for="ia-topic">Tema do artigo</label><input id="ia-topic" type="text" name="topic" placeholder="Ex.: Como funciona o licenciamento ambiental offshore" data-ia-topic><span class="help">Sem ideia? <button type="button" class="linklike" data-ia-suggest>Sugerir pautas para a categoria</button></span><ul class="ia-sug" data-ia-sug hidden></ul></div>
          <div class="field"><label for="ia-kw">Palavras-chave (opcional)</label><input id="ia-kw" type="text" name="keywords" placeholder="licenciamento ambiental, ibama"></div>
          <div class="field"><label for="ia-words">Tamanho (palavras)</label><input id="ia-words" type="number" name="words" min="300" max="4000" step="100" value="<?= (int) s7x_ia('words') ?>"></div>
          <div class="field field--wide"><label for="ia-extra">Orientações extras (opcional)</label><textarea id="ia-extra" name="extra" rows="2" placeholder="Ex.: foque em quem está começando na carreira offshore"></textarea></div>
          <div class="field field--wide"><label for="ia-base">Texto-base (opcional)</label><textarea id="ia-base" name="base" rows="5" placeholder="Cole uma notícia ou release: a IA reescreve com palavras próprias usando só os fatos do texto."></textarea></div>
        </div>
        <div class="row" style="gap:.6rem;margin-top:1rem"><button class="btn btn--primary" data-ia-go>Gerar artigo</button><span class="muted small">Leva de 1 a 3 minutos.</span></div>
      </form>
    </section>
    <section class="panel" data-ia-result hidden><h2>Resultado</h2><div data-ia-out aria-live="polite"></div></section>
    <?php
}

function s7x_ia_tab_lote(): void
{
    ?>
    <section class="panel">
      <h2>Gerar vários artigos</h2>
      <p class="muted">Um tema por linha. Cada tema vira um artigo, um depois do outro. Deixe esta página aberta até terminar.</p>
      <form class="form" data-ia-bulk>
        <div class="grid">
          <div class="field"><label for="ib-cat">Categoria</label><?= s7x_ia_cat_select('category') ?></div>
          <div class="field"><label for="ib-status">Salvar como</label><select id="ib-status" name="status"><option value="draft">Rascunho (revisar antes)</option><option value="publish">Publicar direto</option></select></div>
          <div class="field field--wide"><label for="ib-topics">Temas (um por linha)</label><textarea id="ib-topics" name="topics" rows="8" data-ia-topics></textarea><span class="help"><button type="button" class="linklike" data-ia-suggest-bulk>Sugerir 10 pautas para a categoria</button></span></div>
          <div class="field"><label for="ib-words">Tamanho (palavras)</label><input id="ib-words" type="number" name="words" min="300" max="4000" step="100" value="<?= (int) s7x_ia('words') ?>"></div>
        </div>
        <div class="row" style="gap:.6rem;margin-top:1rem"><button class="btn btn--primary">Gerar todos</button><button type="button" class="btn btn--ghost" data-ia-stop hidden>Parar depois do atual</button></div>
      </form>
    </section>
    <section class="panel" data-ia-bulkres hidden><h2>Andamento</h2><p data-ia-bulkst aria-live="polite"></p><ol class="ia-bulklist" data-ia-bulklist></ol></section>
    <?php
}

function s7x_ia_tab_automacao(): void
{
    $sel = (array) s7x_ia('auto_cats');
    $done = s7x_ia_done_today();
    $state = s7x_ia_state();
    $cron = cms_cron_state();
    $index = s7x_blog_index();
    ?>
    <div class="ia-cols">
      <form method="post" class="form panel" data-dirty-check>
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="save">
        <h2>Publicação automática</h2>
        <?= s7x_ia_fields_html('automacao') ?>
        <div class="field field--wide">
          <span class="label">Categorias (rodízio)</span>
          <div class="ia-catgrid">
            <?php foreach (s7x_ia_categories() as $c): ?>
              <label class="blog-checkline"><input type="checkbox" name="auto_cats[]" value="<?= e($c['slug']) ?>"<?= in_array($c['slug'], $sel, true) ? ' checked' : '' ?>> <?= e($c['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <span class="help">Cada artigo vai para uma categoria diferente. A IA lê o que já existe nela e sugere pautas novas. Nenhuma marcada = todas.</span>
        </div>
        <div class="savebar"><button class="btn btn--primary">Salvar automação</button><span class="savebar__hint" data-dirty-hint hidden>Há alterações não salvas.</span></div>
      </form>
      <div>
        <section class="panel">
          <h2>Hoje</h2>
          <?php if (!s7x_ia('auto_enabled')): ?><p class="muted">A automação está desligada.</p><?php endif; ?>
          <ul class="ia-slots">
            <?php foreach (s7x_ia_slots() as $slot): $st = $done[$slot]['status'] ?? ($slot <= date('H:i') ? 'pendente' : 'agendado'); ?>
              <li><strong><?= e($slot) ?></strong> <span class="blog-badge ia-st--<?= e($st) ?>"><?= e(['ok' => 'feito', 'erro' => 'erro', 'rodando' => 'gerando', 'pendente' => 'na fila', 'agendado' => 'agendado'][$st] ?? $st) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <p><button type="button" class="btn btn--small" data-ia-auto-now<?= s7x_ia_key() === '' ? ' disabled' : '' ?>>Gerar um artigo agora</button></p>
          <p class="small" data-ia-auto-msg aria-live="polite"></p>
        </section>
        <section class="panel">
          <h2>Últimos gerados</h2>
          <?php $hist = array_reverse(array_slice((array) $state['history'], -10)); ?>
          <?php if (!$hist): ?><p class="muted small">Nenhum ainda.</p><?php else: ?>
          <ul class="small ia-hist">
            <?php foreach ($hist as $h): if (!isset($index['posts'][$h['id']])) { continue; } $p = $index['posts'][$h['id']]; ?>
              <li><a href="<?= e(admin_url('blog', ['edit' => $h['id']])) ?>"><?= e($p['title']) ?></a> <span class="muted"><?= e(cms_format_date($h['t'], 'd/m H:i')) ?> · <?= e($h['origin']) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </section>
        <section class="panel">
          <h2>Como as tarefas rodam</h2>
          <p class="small">Última execução: <strong><?= !empty($cron['last_run']) ? e(date('d/m H:i', (int) $cron['last_run'])) . ' (' . e($cron['last_origin'] ?? '') . ')' : 'ainda não rodou' ?></strong><?= !empty($cron['last_error']) ? ' — erro: ' . e($cron['last_error']) : '' ?></p>
          <p class="small muted">As tarefas rodam sozinhas quando o site recebe visitas. Para não depender delas, crie no cPanel → <strong>Cron Jobs</strong> uma tarefa a cada 5 minutos com o comando:</p>
          <pre class="ia-code">php <?= e(CMS_ROOT) ?>/cron.php</pre>
          <p class="small muted">Se o servidor não aceitar php na linha de comando, use:</p>
          <pre class="ia-code">curl -s "<?= e(cms_cron_url()) ?>" &gt; /dev/null</pre>
        </section>
      </div>
    </div>
    <?php
}

function s7x_ia_tab_config(): void
{
    $key = s7x_ia_key();
    ?>
    <form method="post" class="form" data-dirty-check>
      <?= cms_csrf_field() ?><input type="hidden" name="action" value="save">
      <section class="panel">
        <h2>IA (Claude)</h2>
        <div class="grid">
          <?= s7x_ia_secret_field('key', 'Chave da API do Claude', $key, 'Crie em <a href="https://platform.claude.com/settings/keys" target="_blank" rel="noopener">platform.claude.com → API Keys</a>. É a mesma chave do assistente do painel. Fica guardada fora do conteúdo do site.', 'sk-ant-...') ?>
          <?= s7x_ia_secret_field('ws', 'Workspace ID (só se a chave pedir)', cms_secret_get('anthropic_workspace_id'), 'Deixe vazio, a não ser que o teste avise que a chave precisa dele.', 'wrkspc_...') ?>
        </div>
        <p><button type="button" class="btn btn--small" data-ia-test="ia_test"<?= $key === '' ? ' disabled' : '' ?>>Testar chave</button> <span class="small" data-ia-test-msg aria-live="polite"></span></p>
        <?= s7x_ia_fields_html('ia') ?>
      </section>
      <section class="panel">
        <h2>Imagens (Pixabay)</h2>
        <div class="grid"><?= s7x_ia_secret_field('pxb', 'Chave da API do Pixabay', s7x_ia_pixabay_key(), 'Grátis em <a href="https://pixabay.com/api/docs/" target="_blank" rel="noopener">pixabay.com/api/docs</a> (precisa de conta).') ?></div>
        <p><button type="button" class="btn btn--small" data-ia-test="ia_pixabay"<?= s7x_ia_pixabay_key() === '' ? ' disabled' : '' ?>>Testar Pixabay</button> <span class="small" data-ia-test-msg aria-live="polite"></span></p>
        <?= s7x_ia_fields_html('imagens') ?>
      </section>
      <section class="panel"><h2>SEO dos artigos</h2><?= s7x_ia_fields_html('seo') ?></section>
      <section class="panel">
        <h2>Rede de blogs</h2>
        <?= s7x_ia_fields_html('rede') ?>
        <p><button type="button" class="btn btn--small" data-ia-test="ia_net_test">Testar a rede</button> <span class="small" data-ia-test-msg aria-live="polite"></span></p>
      </section>
      <section class="panel">
        <h2>Importar artigos de outro site da rede</h2>
        <?= s7x_ia_fields_html('importar') ?>
        <p><button type="button" class="btn btn--small" data-ia-test="ia_import_now">Importar agora</button> <span class="small" data-ia-test-msg aria-live="polite"></span></p>
      </section>
      <div class="savebar savebar--sticky"><button class="btn btn--primary">Salvar configurações</button><span class="savebar__hint" data-dirty-hint hidden>Há alterações não salvas.</span></div>
    </form>
    <?php
}

function s7x_ia_tab_registro(): void
{
    $log = array_reverse(cms_read_json(cms_module_data_file('blog-ia', 'log'), []));
    echo '<section class="panel"><h2>Registro</h2>';
    if (!$log) {
        echo '<p class="muted">Nada registrado ainda.</p></section>';
        return;
    }
    echo '<table class="table"><thead><tr><th>Quando</th><th>O que aconteceu</th></tr></thead><tbody>';
    foreach (array_slice($log, 0, 150) as $l) {
        echo '<tr><td class="small" style="white-space:nowrap">' . e(cms_format_date($l['t'], 'd/m H:i')) . '</td><td class="small" style="text-align:left"><span class="blog-badge ia-st--' . e($l['type']) . '">' . e($l['type']) . '</span> ' . e($l['msg']) . '</td></tr>';
    }
    echo '</tbody></table></section>';
}
