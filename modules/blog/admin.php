<?php
/**
 * Módulo Blog — telas do painel: Posts (lista e editor) e Categorias e tags
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_blog_admin_boot(): void
{
    cms_register_admin_page('blog', [
        'label' => 'Posts do blog', 'icon' => 'folha', 'order' => 5,
        'perm_help' => 'Criar, editar e publicar posts do blog.',
        'render' => 's7x_blog_posts_render', 'handle' => 's7x_blog_posts_handle',
        'badge' => fn () => count(s7x_blog_query(['status' => 'pending'])),
    ]);
    cms_register_admin_page('blog-terms', [
        'label' => 'Categorias e tags', 'icon' => 'neuronio', 'order' => 7,
        'perm_help' => 'Criar, renomear e excluir categorias e tags do blog.',
        'render' => 's7x_blog_terms_render', 'handle' => 's7x_blog_terms_handle',
    ]);
    cms_add_action('admin_head', function () {
        if (strpos((string) ($_GET['p'] ?? ''), 'blog') === 0) {
            echo '<link rel="stylesheet" href="' . e(cms_module_url('blog', 'assets/blog-admin.css')) . '">';
        }
    });
    cms_add_action('admin_footer', function () {
        if (strpos((string) ($_GET['p'] ?? ''), 'blog') === 0) {
            echo '<script src="' . e(cms_module_url('blog', 'assets/blog-admin.js')) . '"></script>';
        }
    });
    cms_add_action('admin_dashboard', function () {
        if (!cms_can('page.blog')) {
            return;
        }
        $pub = count(s7x_blog_query());
        $draft = count(s7x_blog_query(['status' => 'draft']));
        $fut = count(s7x_blog_query(['status' => 'future']));
        echo '<section class="panel"><div class="row row--between"><h2>Blog</h2><a class="btn btn--small" href="' . e(admin_url('blog', ['edit' => 'novo'])) . '">+ Novo post</a></div>'
            . '<p class="muted">' . $pub . ' publicados · ' . $draft . ' rascunhos' . ($fut ? ' · ' . $fut . ' agendados' : '') . '</p>';
        $last = array_slice(s7x_blog_query(['status' => 'any']), 0, 5, true);
        if ($last) {
            echo '<ul class="blog-mini">';
            foreach ($last as $id => $p) {
                echo '<li><a href="' . e(admin_url('blog', ['edit' => $id])) . '">' . e($p['title']) . '</a> <span class="muted small">' . e(s7x_blog_status_label($p)) . ' · ' . e(cms_format_date($p['date'], 'd/m H:i')) . '</span></li>';
            }
            echo '</ul>';
        }
        echo '</section>';
    });
}

function s7x_blog_status_label(array $p): string
{
    $st = (string) ($p['status'] ?? 'draft');
    if ($st === 'future' && strtotime((string) $p['date']) <= time()) {
        $st = 'publish';
    }
    return ['publish' => 'Publicado', 'draft' => 'Rascunho', 'future' => 'Agendado', 'trash' => 'Na lixeira', 'pending' => 'Para revisar'][$st] ?? $st;
}

function s7x_blog_source_label(string $s): string
{
    return ['ia' => 'IA', 'wordpress' => 'Importado', 'rede' => 'Rede'][$s] ?? '';
}

/* -------------------------------------------------------------------------
 * Ações
 * ---------------------------------------------------------------------- */

function s7x_blog_posts_handle(string $action): void
{
    if ($action === 'save') {
        $in = (array) ($_POST['post'] ?? []);
        $id = (string) ($_POST['id'] ?? '');
        $old = $id !== '' ? s7x_blog_get($id) : null;
        $cats = array_values(array_filter(array_map('strval', (array) ($in['cats'] ?? []))));
        foreach (array_filter(array_map('trim', explode(',', (string) ($in['new_cat'] ?? '')))) as $nc) {
            $cats[] = s7x_blog_ensure_term('categories', $nc);
        }
        $tags = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($in['tags'] ?? '')))) as $t) {
            $tags[] = s7x_blog_ensure_term('tags', $t);
        }
        $status = in_array($in['status'] ?? '', ['publish', 'draft', 'pending'], true) ? $in['status'] : 'draft';
        if (($_POST['go'] ?? '') === 'publish') {
            $status = 'publish';
        }
        $date = trim((string) ($in['date'] ?? ''));
        $faqDef = ['type' => 'repeater', 'fields' => ['q' => ['type' => 'text'], 'a' => ['type' => 'textarea']]];
        $data = [
            'title'        => cms_clean_text((string) ($in['title'] ?? '')),
            'slug'         => cms_slug((string) ($in['slug'] ?? '')),
            'content'      => (string) ($in['content'] ?? ''),
            'excerpt'      => cms_clean_text((string) ($in['excerpt'] ?? '')),
            'cats'         => $cats,
            'tags'         => $tags,
            'status'       => $status,
            'date'         => $date !== '' ? $date : ($old['date'] ?? date('c')),
            'image'        => cms_clean_value(['type' => 'image'], $in['image'] ?? []),
            'image_credit' => cms_sanitize_html((string) ($in['image_credit'] ?? '')),
            'hide_ads'     => !empty($in['hide_ads']),
            'seo'          => [
                'title'         => cms_clean_text((string) ($in['seo']['title'] ?? '')),
                'description'   => cms_clean_text((string) ($in['seo']['description'] ?? '')),
                'focus_keyword' => cms_clean_text((string) ($in['seo']['focus_keyword'] ?? '')),
                'canonical'     => cms_clean_url((string) ($in['seo']['canonical'] ?? '')),
                'noindex'       => !empty($in['seo']['noindex']),
            ],
            'faq'          => cms_clean_value($faqDef, $in['faq'] ?? []),
        ];
        if ($data['slug'] === '' && $old) {
            $data['slug'] = $old['slug'];
        }
        $newId = s7x_blog_save($data, $old ? $id : '');
        $saved = s7x_blog_get($newId);
        $msg = $saved['status'] === 'publish' ? 'Post publicado.' : ($saved['status'] === 'future' ? 'Post agendado para ' . cms_format_date($saved['date']) . '.' : 'Post salvo como ' . mb_strtolower(s7x_blog_status_label($saved)) . '.');
        cms_flash('ok', $msg);
        redirect(admin_url('blog', ['edit' => $newId]));
    }
    if ($action === 'duplicate') {
        $p = s7x_blog_get((string) ($_POST['id'] ?? ''));
        if ($p) {
            $p['title'] .= ' (cópia)';
            $p['slug'] = '';
            $p['status'] = 'draft';
            $p['wp_id'] = 0;
            $p['date'] = date('c');
            unset($p['id']);
            $nid = s7x_blog_save($p);
            cms_flash('ok', 'Cópia criada como rascunho.');
            redirect(admin_url('blog', ['edit' => $nid]));
        }
    }
    if ($action === 'bulk' || in_array($action, ['trash', 'restore', 'delete'], true)) {
        $ids = $action === 'bulk' ? array_map('strval', (array) ($_POST['ids'] ?? [])) : [(string) ($_POST['id'] ?? '')];
        $do = $action === 'bulk' ? (string) ($_POST['do'] ?? '') : $action;
        $cat = (string) ($_POST['cat'] ?? '');
        $n = 0;
        $index = s7x_blog_index(true);
        foreach ($ids as $id) {
            if (!isset($index['posts'][$id])) {
                continue;
            }
            if ($do === 'delete') {
                unset($index['posts'][$id]);
                @unlink(S7X_BLOG_DIR . '/posts/' . $id . '.json');
            } elseif ($do === 'trash') {
                $index['posts'][$id]['status'] = 'trash';
            } elseif ($do === 'restore' || $do === 'draft') {
                $index['posts'][$id]['status'] = 'draft';
            } elseif ($do === 'publish') {
                $index['posts'][$id]['status'] = strtotime($index['posts'][$id]['date']) > time() ? 'future' : 'publish';
            } elseif ($do === 'addcat' && $cat !== '') {
                $index['posts'][$id]['cats'] = array_values(array_unique(array_merge((array) $index['posts'][$id]['cats'], [$cat])));
            } elseif ($do === 'delcat' && $cat !== '') {
                $index['posts'][$id]['cats'] = array_values(array_diff((array) $index['posts'][$id]['cats'], [$cat]));
            } else {
                continue;
            }
            $n++;
        }
        s7x_blog_save_index($index);
        $labels = ['delete' => 'excluído(s) definitivamente', 'trash' => 'enviado(s) para a lixeira', 'restore' => 'restaurado(s) como rascunho', 'draft' => 'virou(aram) rascunho', 'publish' => 'publicado(s)', 'addcat' => 'com a categoria adicionada', 'delcat' => 'sem a categoria'];
        $n ? cms_flash('ok', $n . ' post(s) ' . ($labels[$do] ?? 'atualizado(s)') . '.') : cms_flash('error', 'Escolha os posts e a ação.');
        $back = array_intersect_key($_GET, array_flip(['st', 'cat', 'q', 'src', 'pg']));
        redirect(admin_url('blog', $back));
    }
}

function s7x_blog_admin_api(): void
{
    // Reservado para ações rápidas do editor (sugestões de tags etc.)
}

/* -------------------------------------------------------------------------
 * Lista de posts
 * ---------------------------------------------------------------------- */

function s7x_blog_posts_render(): void
{
    if (isset($_GET['edit'])) {
        s7x_blog_editor_render((string) $_GET['edit']);
        return;
    }
    $st = in_array($_GET['st'] ?? '', ['any', 'publish', 'draft', 'future', 'pending', 'trash'], true) ? $_GET['st'] : 'any';
    $cat = cms_slug((string) ($_GET['cat'] ?? ''));
    $q = trim((string) ($_GET['q'] ?? ''));
    $src = in_array($_GET['src'] ?? '', ['ia', 'wordpress', 'rede', 'manual'], true) ? $_GET['src'] : '';
    $pg = max(1, (int) ($_GET['pg'] ?? 1));
    $query = ['status' => $st, 'cat' => $cat, 'search' => $q];
    $all = s7x_blog_query($query);
    if ($src !== '') {
        $all = array_filter($all, fn ($p) => ($src === 'manual' ? ($p['source'] ?? '') === '' : ($p['source'] ?? '') === $src));
    }
    $per = 30;
    $pages = max(1, (int) ceil(count($all) / $per));
    $items = array_slice($all, ($pg - 1) * $per, $per, true);
    $views = s7x_blog_views();
    $cats = s7x_blog_categories(true);
    $counts = [];
    foreach (['any', 'publish', 'draft', 'future', 'pending', 'trash'] as $s) {
        $counts[$s] = count(s7x_blog_query(['status' => $s]));
    }
    $keep = array_filter(['st' => $st !== 'any' ? $st : '', 'cat' => $cat, 'q' => $q, 'src' => $src]);
    ?>
    <div class="row row--between blog-adm-top">
      <nav class="tabs" aria-label="Filtrar por situação">
        <?php foreach (['any' => 'Todos', 'publish' => 'Publicados', 'draft' => 'Rascunhos', 'future' => 'Agendados', 'pending' => 'Para revisar', 'trash' => 'Lixeira'] as $k => $lbl): ?>
          <?php if ($k !== 'any' && !$counts[$k] && $st !== $k) { continue; } ?>
          <a href="<?= e(admin_url('blog', array_filter(['st' => $k !== 'any' ? $k : '', 'cat' => $cat, 'q' => $q, 'src' => $src]))) ?>" class="<?= $st === $k ? 'is-active' : '' ?>"><?= e($lbl) ?> <span class="muted">(<?= $counts[$k] ?>)</span></a>
        <?php endforeach; ?>
      </nav>
      <a class="btn btn--primary" href="<?= e(admin_url('blog', ['edit' => 'novo'])) ?>">+ Novo post</a>
    </div>

    <form method="get" class="panel blog-filter">
      <input type="hidden" name="p" value="blog"><?php if ($st !== 'any'): ?><input type="hidden" name="st" value="<?= e($st) ?>"><?php endif; ?>
      <label class="sr" for="bq">Buscar</label><input id="bq" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar pelo título, resumo ou tag">
      <label class="sr" for="bcat">Categoria</label>
      <select id="bcat" name="cat"><option value="">Todas as categorias</option><?php foreach ($cats as $c): ?><option value="<?= e($c['slug']) ?>"<?= $cat === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?> (<?= $c['count'] ?>)</option><?php endforeach; ?></select>
      <label class="sr" for="bsrc">Origem</label>
      <select id="bsrc" name="src"><option value="">Qualquer origem</option><option value="manual"<?= $src === 'manual' ? ' selected' : '' ?>>Escritos no painel</option><option value="ia"<?= $src === 'ia' ? ' selected' : '' ?>>Gerados pela IA</option><option value="wordpress"<?= $src === 'wordpress' ? ' selected' : '' ?>>Importados do WordPress</option><option value="rede"<?= $src === 'rede' ? ' selected' : '' ?>>Vindos da rede de blogs</option></select>
      <button class="btn">Filtrar</button>
      <?php if ($keep): ?><a class="btn btn--ghost" href="<?= e(admin_url('blog')) ?>">Limpar</a><?php endif; ?>
    </form>

    <form method="post" class="panel" data-blog-bulk>
      <?= cms_csrf_field() ?><input type="hidden" name="action" value="bulk">
      <div class="row row--between blog-bulkbar">
        <div class="row">
          <label class="sr" for="bdo">Ação em massa</label>
          <select id="bdo" name="do" data-blog-do>
            <option value="">Ação com os marcados…</option>
            <?php if ($st === 'trash'): ?><option value="restore">Restaurar como rascunho</option><option value="delete">Excluir definitivamente</option>
            <?php else: ?><option value="publish">Publicar</option><option value="draft">Voltar para rascunho</option><option value="addcat">Adicionar categoria…</option><option value="delcat">Tirar categoria…</option><option value="trash">Mandar para a lixeira</option><?php endif; ?>
          </select>
          <select name="cat" data-blog-cat hidden><?php foreach ($cats as $c): ?><option value="<?= e($c['slug']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
          <button class="btn btn--small">Aplicar</button>
        </div>
        <span class="muted small"><?= count($all) ?> post(s)</span>
      </div>
      <?php if (!$items): ?>
        <p class="empty">Nenhum post <?= $q !== '' || $cat !== '' ? 'com esse filtro' : 'ainda' ?>. <a href="<?= e(admin_url('blog', ['edit' => 'novo'])) ?>">Escrever o primeiro</a><?= cms_can('page.blog-import') ? ' ou <a href="' . e(admin_url('blog-import')) . '">importar do WordPress</a>' : '' ?>.</p>
      <?php else: ?>
      <div class="blog-tablewrap">
      <table class="table blog-table-adm">
        <thead><tr><th class="blog-col-chk"><input type="checkbox" data-blog-all aria-label="Marcar todos"></th><th>Título</th><th>Categorias</th><th>Data</th><th class="blog-col-n">Leituras</th></tr></thead>
        <tbody>
        <?php foreach ($items as $id => $p): $img = cms_image_value($p['image']); ?>
          <tr>
            <td class="blog-col-chk"><input type="checkbox" name="ids[]" value="<?= e($id) ?>" aria-label="Marcar <?= e($p['title']) ?>"></td>
            <td>
              <div class="blog-rowtitle">
                <span class="blog-thumb"><?= $img['src'] !== '' ? '<img src="' . e(cms_url($img['src'])) . '" alt="" loading="lazy">' : '' ?></span>
                <span>
                  <a class="blog-rowlink" href="<?= e(admin_url('blog', ['edit' => $id])) ?>"><?= e($p['title']) ?></a>
                  <span class="blog-badges">
                    <?php if ($p['status'] !== 'publish' || ($st === 'any' && false)): ?><em class="blog-badge blog-badge--<?= e($p['status']) ?>"><?= e(s7x_blog_status_label($p)) ?></em><?php endif; ?>
                    <?php if (($l = s7x_blog_source_label((string) ($p['source'] ?? ''))) !== ''): ?><em class="blog-badge"><?= e($l) ?></em><?php endif; ?>
                    <?php if (cms_image_value($p['image'])['src'] === ''): ?><em class="blog-badge blog-badge--warn">sem imagem</em><?php endif; ?>
                  </span>
                  <span class="blog-rowactions">
                    <a href="<?= e(admin_url('blog', ['edit' => $id])) ?>">Editar</a>
                    <?php if ($p['status'] === 'publish'): ?><a href="<?= e(s7x_blog_post_url($p)) ?>" target="_blank" rel="noopener">Ver</a><?php endif; ?>
                    <?php if ($p['status'] === 'trash'): ?>
                      <button formaction="<?= e(admin_url('blog')) ?>" name="id" value="<?= e($id) ?>" data-blog-single="restore">Restaurar</button>
                    <?php else: ?>
                      <button name="id" value="<?= e($id) ?>" data-blog-single="duplicate">Duplicar</button>
                      <button name="id" value="<?= e($id) ?>" data-blog-single="trash" class="danger">Lixeira</button>
                    <?php endif; ?>
                  </span>
                </span>
              </div>
            </td>
            <td class="small"><?= e(implode(', ', array_map(fn ($c) => s7x_blog_term_name('categories', $c), array_slice((array) $p['cats'], 0, 3)))) ?></td>
            <td class="small"><?= e(cms_format_date($p['date'], 'd/m/Y H:i')) ?></td>
            <td class="blog-col-n small"><?= (int) ($views[$id]['t'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </form>
    <?php if ($pages > 1): ?>
      <nav class="row blog-adm-pages" aria-label="Páginas">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <?php if ($i === 1 || $i === $pages || abs($i - $pg) <= 2): ?>
            <a class="btn btn--small<?= $i === $pg ? ' btn--primary' : ' btn--ghost' ?>" href="<?= e(admin_url('blog', $keep + ['pg' => $i])) ?>"><?= $i ?></a>
          <?php elseif (abs($i - $pg) === 3): ?><span class="muted">…</span><?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
    <?php
}

/* -------------------------------------------------------------------------
 * Editor de post
 * ---------------------------------------------------------------------- */

function s7x_blog_editor_render(string $id): void
{
    $p = $id !== 'novo' ? s7x_blog_get($id) : null;
    if ($id !== 'novo' && !$p) {
        echo '<div class="notice notice--error">Post não encontrado. <a href="' . e(admin_url('blog')) . '">Voltar para a lista</a>.</div>';
        return;
    }
    $p = $p ?: s7x_blog_defaults();
    $cats = s7x_blog_categories(true);
    $seo = (array) $p['seo'];
    $tags = implode(', ', array_map(fn ($t) => s7x_blog_term_name('tags', $t), (array) $p['tags']));
    $date = $p['date'] ? date('Y-m-d\TH:i', strtotime($p['date'])) : '';
    $isPub = in_array($p['status'], ['publish', 'future'], true);
    $url = $p['slug'] !== '' ? s7x_blog_post_url($p) : '';
    ?>
    <p><a href="<?= e(admin_url('blog')) ?>">← Todos os posts</a></p>
    <form method="post" class="form blog-editor" data-dirty-check>
      <?= cms_csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
      <div class="blog-editor__grid">
        <div class="blog-editor__main">
          <section class="panel">
            <label class="sr" for="btitle">Título</label>
            <input id="btitle" class="blog-title-input" type="text" name="post[title]" value="<?= e($p['title']) ?>" placeholder="Título do post" required maxlength="160" data-blog-title>
            <div class="blog-slug small muted">
              Endereço: <?= e(cms_site_url()) ?><?= s7x_blog_opt('post_prefix', 'root') === 'base' ? '/' . e(s7x_blog_base()) : '' ?>/<input type="text" name="post[slug]" value="<?= e($p['slug']) ?>" aria-label="Endereço do post" placeholder="gerado-pelo-titulo" data-blog-slug>/
              <?php if ($url && $isPub): ?> · <a href="<?= e($url) ?>" target="_blank" rel="noopener">Ver no site</a><?php elseif ($url): ?> · <a href="<?= e($url) ?>" target="_blank" rel="noopener">Pré-visualizar</a><?php endif; ?>
            </div>
          </section>
          <section class="panel blog-ed">
            <div class="blog-ed__bar" role="toolbar" aria-label="Formatação">
              <button type="button" data-ed="formatBlock:h2" title="Subtítulo">H2</button>
              <button type="button" data-ed="formatBlock:h3" title="Subtítulo menor">H3</button>
              <button type="button" data-ed="formatBlock:p" title="Parágrafo">¶</button>
              <span class="blog-ed__sep"></span>
              <button type="button" data-ed="bold" title="Negrito"><b>B</b></button>
              <button type="button" data-ed="italic" title="Itálico"><i>I</i></button>
              <button type="button" data-ed="link" title="Link">Link</button>
              <button type="button" data-ed="unlink" title="Tirar link">Sem link</button>
              <span class="blog-ed__sep"></span>
              <button type="button" data-ed="insertUnorderedList" title="Lista">• Lista</button>
              <button type="button" data-ed="insertOrderedList" title="Lista numerada">1. Lista</button>
              <button type="button" data-ed="formatBlock:blockquote" title="Citação">“ ”</button>
              <span class="blog-ed__sep"></span>
              <button type="button" data-ed="image" title="Inserir imagem">Imagem</button>
              <button type="button" data-ed="video" title="Inserir vídeo do YouTube">Vídeo</button>
              <button type="button" data-ed="html" class="blog-ed__html" aria-pressed="false" title="Editar o HTML">HTML</button>
            </div>
            <div class="blog-ed__area" contenteditable="true" data-ed-area aria-label="Texto do post" role="textbox" aria-multiline="true"><?= $p['content'] ?></div>
            <textarea name="post[content]" class="blog-ed__src" data-ed-src hidden><?= e($p['content']) ?></textarea>
            <input type="file" accept="image/jpeg,image/png,image/webp" data-ed-file hidden>
            <p class="small muted blog-ed__count" data-ed-count></p>
          </section>
          <section class="panel">
            <h2>Resumo</h2>
            <?= cms_render_field('post[excerpt]', ['type' => 'textarea', 'label' => 'Resumo do post', 'rows' => 3, 'max' => 240, 'help' => 'Aparece nos cards do blog e embaixo do título. Vazio = começo do texto.'], $p['excerpt']) ?>
          </section>
          <section class="panel">
            <h2>Perguntas frequentes</h2>
            <p class="muted small">Viram a seção “Perguntas frequentes” no Google (dados estruturados). Opcional.</p>
            <?= cms_render_field('post[faq]', ['type' => 'repeater', 'label' => 'Perguntas', 'item_label' => 'q', 'add_label' => 'pergunta', 'fields' => ['q' => ['type' => 'text', 'label' => 'Pergunta'], 'a' => ['type' => 'textarea', 'label' => 'Resposta', 'rows' => 3]]], (array) $p['faq']) ?>
          </section>
          <section class="panel">
            <h2>SEO</h2>
            <div class="serp" data-blog-serp>
              <span class="serp__site"><?= e(parse_url(cms_site_url(), PHP_URL_HOST)) ?></span>
              <span class="serp__title" data-serp-t><?= e(($seo['title'] ?? '') ?: $p['title']) ?></span>
              <span class="serp__desc" data-serp-d><?= e(($seo['description'] ?? '') ?: $p['excerpt']) ?></span>
            </div>
            <div class="grid">
              <?= cms_render_field('post[seo][title]', ['type' => 'text', 'label' => 'Título SEO', 'max' => 60, 'help' => 'Vazio = título do post + nome do site.'], (string) ($seo['title'] ?? '')) ?>
              <?= cms_render_field('post[seo][focus_keyword]', ['type' => 'text', 'label' => 'Palavra-chave foco', 'help' => 'Para conferir abaixo se ela está no título, no começo do texto e nos subtítulos.'], (string) ($seo['focus_keyword'] ?? '')) ?>
              <?= cms_render_field('post[seo][description]', ['type' => 'textarea', 'label' => 'Meta descrição', 'max' => 160, 'rows' => 2, 'wide' => true, 'help' => 'Vazio = resumo do post.'], (string) ($seo['description'] ?? '')) ?>
              <?= cms_render_field('post[seo][canonical]', ['type' => 'url', 'label' => 'URL canônica (opcional)', 'help' => 'Só para posts copiados de outro site: aponte para o original.'], (string) ($seo['canonical'] ?? '')) ?>
              <?= cms_render_field('post[seo][noindex]', ['type' => 'toggle', 'label' => 'Esconder este post do Google (noindex)'], !empty($seo['noindex'])) ?>
            </div>
            <ul class="checks blog-kw" data-blog-kw></ul>
          </section>
        </div>
        <aside class="blog-editor__side">
          <section class="panel blog-pubbox">
            <h2>Publicação</h2>
            <p class="small">Situação: <strong><?= e($p['id'] ? s7x_blog_status_label($p) : 'Novo') ?></strong></p>
            <div class="field">
              <label for="bstatus">Salvar como</label>
              <select id="bstatus" name="post[status]">
                <option value="draft"<?= in_array($p['status'], ['draft', 'trash'], true) ? ' selected' : '' ?>>Rascunho</option>
                <option value="pending"<?= $p['status'] === 'pending' ? ' selected' : '' ?>>Para revisar</option>
                <option value="publish"<?= $isPub ? ' selected' : '' ?>>Publicado</option>
              </select>
            </div>
            <div class="field">
              <label for="bdate">Data de publicação</label>
              <input id="bdate" type="datetime-local" name="post[date]" value="<?= e($date) ?>">
              <span class="help">Data no futuro + “Publicado” = agendado.</span>
            </div>
            <div class="row blog-pubbox__btns">
              <button class="btn btn--primary" name="go" value="save">Salvar</button>
              <?php if (!$isPub): ?><button class="btn" name="go" value="publish">Publicar agora</button><?php endif; ?>
            </div>
            <?php if ($p['id']): ?>
            <div class="row blog-pubbox__more small">
              <button class="linklike" form="blogdup">Duplicar</button>
              <?php if ($p['status'] !== 'trash'): ?><button class="linklike danger" form="blogtrash" data-confirm="Mandar este post para a lixeira?">Mandar para a lixeira</button><?php endif; ?>
            </div>
            <?php if (!empty($p['source'])): ?><p class="small muted">Origem: <?= e(s7x_blog_source_label((string) $p['source'])) ?><?= !empty($p['source_url']) ? ' — <a href="' . e($p['source_url']) . '" target="_blank" rel="noopener">original</a>' : '' ?></p><?php endif; ?>
            <?php endif; ?>
          </section>
          <section class="panel">
            <h2>Imagem de destaque</h2>
            <?= cms_render_field('post[image]', ['type' => 'image', 'label' => 'Imagem', 'help' => 'Ideal 1200 × 675 px. Aparece no topo do post, nos cards e no compartilhamento.'], $p['image']) ?>
            <?= cms_render_field('post[image_credit]', ['type' => 'text', 'label' => 'Crédito da imagem (opcional)', 'placeholder' => 'Foto: Nome / Pixabay'], strip_tags((string) $p['image_credit'], '<a>')) ?>
          </section>
          <section class="panel">
            <h2>Categorias</h2>
            <div class="blog-catlist">
              <?php foreach ($cats as $c): ?>
                <label class="blog-checkline"><input type="checkbox" name="post[cats][]" value="<?= e($c['slug']) ?>"<?= in_array($c['slug'], (array) $p['cats'], true) ? ' checked' : '' ?>> <?= e($c['name']) ?></label>
              <?php endforeach; ?>
            </div>
            <div class="field"><label for="bnewcat">Nova categoria</label><input id="bnewcat" type="text" name="post[new_cat]" placeholder="Nome da categoria"></div>
          </section>
          <section class="panel">
            <h2>Tags</h2>
            <div class="field">
              <label for="btags">Tags (separadas por vírgula)</label>
              <input id="btags" type="text" name="post[tags]" value="<?= e($tags) ?>" list="blogtaglist" placeholder="petrobras, fpso, pré-sal">
              <datalist id="blogtaglist"><?php foreach (array_slice(s7x_blog_terms()['tags'], 0, 400) as $t): ?><option value="<?= e($t['name']) ?>"><?php endforeach; ?></datalist>
            </div>
          </section>
          <section class="panel">
            <h2>Anúncios</h2>
            <?= cms_render_field('post[hide_ads]', ['type' => 'toggle', 'label' => 'Esconder os anúncios neste post'], !empty($p['hide_ads'])) ?>
          </section>
          <?= cms_capture_action('blog_editor_side', $p) ?>
        </aside>
      </div>
      <div class="savebar savebar--sticky">
        <button class="btn btn--primary" name="go" value="save">Salvar</button>
        <?php if ($url): ?><a class="btn btn--ghost" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= $isPub ? 'Ver no site' : 'Pré-visualizar' ?></a><?php endif; ?>
        <span class="savebar__hint" data-dirty-hint hidden>Há alterações não salvas.</span>
      </div>
    </form>
    <?php if ($p['id']): ?>
      <form id="blogdup" method="post" hidden><?= cms_csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= e($p['id']) ?>"></form>
      <form id="blogtrash" method="post" hidden><?= cms_csrf_field() ?><input type="hidden" name="action" value="trash"><input type="hidden" name="id" value="<?= e($p['id']) ?>"></form>
    <?php endif; ?>
    <?php
}

/* -------------------------------------------------------------------------
 * Categorias e tags
 * ---------------------------------------------------------------------- */

function s7x_blog_terms_handle(string $action): void
{
    $tax = ($_POST['tax'] ?? '') === 'tags' ? 'tags' : 'categories';
    $terms = s7x_blog_terms(true);
    if ($action === 'add') {
        $name = cms_clean_text((string) ($_POST['name'] ?? ''));
        if ($name !== '') {
            $slug = s7x_blog_ensure_term($tax, $name, (string) ($_POST['slug'] ?? ''));
            cms_flash('ok', ($tax === 'tags' ? 'Tag' : 'Categoria') . ' “' . $name . '” criada (' . $slug . ').');
        }
        return;
    }
    $slug = (string) ($_POST['slug'] ?? '');
    if (!isset($terms[$tax][$slug])) {
        cms_flash('error', 'Item não encontrado.');
        return;
    }
    if ($action === 'edit') {
        $terms[$tax][$slug]['name'] = cms_clean_text((string) ($_POST['name'] ?? '')) ?: $terms[$tax][$slug]['name'];
        $terms[$tax][$slug]['description'] = cms_clean_text((string) ($_POST['description'] ?? ''));
        $terms[$tax][$slug]['hidden'] = !empty($_POST['hidden']);
        s7x_blog_save_terms($terms);
        cms_flash('ok', 'Salvo.');
        return;
    }
    if ($action === 'delete' || $action === 'merge') {
        $into = (string) ($_POST['into'] ?? '');
        if ($action === 'merge' && (!isset($terms[$tax][$into]) || $into === $slug)) {
            cms_flash('error', 'Escolha para onde juntar.');
            return;
        }
        $key = $tax === 'tags' ? 'tags' : 'cats';
        $index = s7x_blog_index(true);
        $n = 0;
        foreach ($index['posts'] as $id => $p) {
            if (in_array($slug, (array) $p[$key], true)) {
                $list = array_values(array_diff((array) $p[$key], [$slug]));
                if ($action === 'merge') {
                    $list[] = $into;
                }
                $index['posts'][$id][$key] = array_values(array_unique($list));
                $n++;
            }
        }
        s7x_blog_save_index($index);
        unset($terms[$tax][$slug]);
        s7x_blog_save_terms($terms);
        cms_flash('ok', $action === 'merge' ? 'Juntado: ' . $n . ' post(s) agora estão em “' . $terms[$tax][$into]['name'] . '”.' : 'Excluído. ' . $n . ' post(s) perderam este item (os posts continuam no site).');
    }
}

function s7x_blog_terms_render(): void
{
    $tax = ($_GET['tax'] ?? '') === 'tags' ? 'tags' : 'categories';
    $terms = s7x_blog_terms()[$tax];
    $count = [];
    $key = $tax === 'tags' ? 'tags' : 'cats';
    foreach (s7x_blog_query(['status' => 'any']) as $p) {
        foreach ((array) $p[$key] as $s) {
            $count[$s] = ($count[$s] ?? 0) + 1;
        }
    }
    uksort($terms, fn ($a, $b) => strcoll(cms_ascii($terms[$a]['name']), cms_ascii($terms[$b]['name'])));
    ?>
    <nav class="tabs" aria-label="Tipo">
      <a href="<?= e(admin_url('blog-terms')) ?>" class="<?= $tax === 'categories' ? 'is-active' : '' ?>">Categorias (<?= count(s7x_blog_terms()['categories']) ?>)</a>
      <a href="<?= e(admin_url('blog-terms', ['tax' => 'tags'])) ?>" class="<?= $tax === 'tags' ? 'is-active' : '' ?>">Tags (<?= count(s7x_blog_terms()['tags']) ?>)</a>
    </nav>
    <section class="panel">
      <h2>Nova <?= $tax === 'tags' ? 'tag' : 'categoria' ?></h2>
      <form method="post" class="row">
        <?= cms_csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="tax" value="<?= e($tax) ?>">
        <label class="sr" for="tname">Nome</label><input id="tname" type="text" name="name" placeholder="Nome" required style="flex:1">
        <label class="sr" for="tslug">Endereço</label><input id="tslug" type="text" name="slug" placeholder="endereco (opcional)">
        <button class="btn btn--primary">Criar</button>
      </form>
    </section>
    <section class="panel">
      <?php if (!$terms): ?><p class="empty">Nenhuma ainda.</p><?php else: ?>
      <table class="table blog-terms">
        <thead><tr><th>Nome</th><th>Endereço</th><th>Posts</th><th><span class="sr">Ações</span></th></tr></thead>
        <tbody>
        <?php foreach ($terms as $slug => $t): ?>
          <tr>
            <td>
              <details>
                <summary><strong><?= e($t['name']) ?></strong><?= !empty($t['hidden']) ? ' <em class="tag">oculta na barra</em>' : '' ?></summary>
                <form method="post" class="form blog-termform">
                  <?= cms_csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="tax" value="<?= e($tax) ?>"><input type="hidden" name="slug" value="<?= e($slug) ?>">
                  <div class="field"><label>Nome<input type="text" name="name" value="<?= e($t['name']) ?>"></label></div>
                  <div class="field"><label>Descrição (aparece na página da categoria e no Google)<textarea name="description" rows="2"><?= e($t['description'] ?? '') ?></textarea></label></div>
                  <?php if ($tax === 'categories'): ?><label class="blog-checkline"><input type="checkbox" name="hidden" value="1"<?= !empty($t['hidden']) ? ' checked' : '' ?>> Não mostrar na barra de categorias do blog</label><?php endif; ?>
                  <button class="btn btn--small btn--primary">Salvar</button>
                </form>
              </details>
            </td>
            <td class="small"><a href="<?= e(s7x_blog_term_url($tax, $slug)) ?>" target="_blank" rel="noopener"><?= e($slug) ?></a></td>
            <td class="small"><?= (int) ($count[$slug] ?? 0) ?></td>
            <td>
              <form method="post" class="row row--end blog-termact">
                <?= cms_csrf_field() ?><input type="hidden" name="tax" value="<?= e($tax) ?>"><input type="hidden" name="slug" value="<?= e($slug) ?>">
                <label class="sr" for="into-<?= e($slug) ?>">Juntar com</label>
                <select id="into-<?= e($slug) ?>" name="into"><option value="">Juntar com…</option><?php foreach ($terms as $s2 => $t2): if ($s2 === $slug) { continue; } ?><option value="<?= e($s2) ?>"><?= e($t2['name']) ?></option><?php endforeach; ?></select>
                <button class="btn btn--small btn--ghost" name="action" value="merge">Juntar</button>
                <button class="btn btn--small btn--ghost danger" name="action" value="delete" data-confirm="Excluir “<?= e($t['name']) ?>”? Os posts continuam no site.">Excluir</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
    <?php
}
