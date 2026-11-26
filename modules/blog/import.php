<?php
/**
 * Módulo Blog — importação de um site WordPress pela API pública (wp-json)
 * Traz categorias, tags, posts e imagens (convertidas para WebP), mantém os
 * endereços dos posts e redireciona os endereços antigos das imagens.
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_blog_import_boot(): void
{
    cms_register_admin_page('blog-import', [
        'label' => 'Importar do WordPress', 'icon' => 'relogio', 'order' => 9, 'admin_only' => true,
        'render' => 's7x_blog_import_render', 'handle' => null,
    ]);
    cms_add_action('admin_api:blogimp_start', 's7x_blog_import_start');
    cms_add_action('admin_api:blogimp_batch', 's7x_blog_import_batch');
    cms_add_action('admin_api:blogimp_reset', function () {
        require_perm('admin');
        @unlink(S7X_BLOG_DIR . '/import.json');
        json_out(['ok' => true]);
    });
}

function s7x_blog_import_state(): array
{
    return cms_read_json(S7X_BLOG_DIR . '/import.json', []);
}

function s7x_blog_import_save(array $s): void
{
    s7x_blog_dir();
    cms_write_json(S7X_BLOG_DIR . '/import.json', $s);
}

/** GET JSON na API do WordPress de origem. */
function s7x_blog_wp_get(string $base, string $path, int $timeout = 40): array
{
    $url = rtrim($base, '/') . '/wp-json/wp/v2/' . ltrim($path, '/');
    $r = cms_http('GET', $url, ['Accept: application/json'], null, $timeout);
    if ($r['status'] === 400 && is_array($r['json']) && ($r['json']['code'] ?? '') === 'rest_post_invalid_page_number') {
        return [];
    }
    if ($r['status'] !== 200 || !is_array($r['json'])) {
        throw new RuntimeException('O site de origem respondeu ' . ($r['status'] ?: 'sem conexão') . ' em ' . $url . ($r['error'] ? ' (' . $r['error'] . ')' : ''));
    }
    return $r['json'];
}

/* -------------------------------------------------------------------------
 * Passo 1: termos e fila de posts
 * ---------------------------------------------------------------------- */

function s7x_blog_import_start(): void
{
    require_perm('admin');
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $src = rtrim(trim((string) ($_POST['source'] ?? '')), '/');
    if (!preg_match('#^https?://[^/\s]+(/[^\s]*)?$#i', $src)) {
        json_out(['ok' => false, 'error' => 'Informe o endereço do site WordPress, começando com https://'], 400);
    }
    try {
        // Categorias e tags (a API entrega até 100 por página)
        $map = ['categories' => [], 'tags' => []];
        foreach (['categories', 'tags'] as $tax) {
            for ($page = 1; $page <= 30; $page++) {
                $list = s7x_blog_wp_get($src, $tax . '?per_page=100&page=' . $page . '&_fields=id,name,slug,description,count');
                if (!$list) {
                    break;
                }
                foreach ($list as $t) {
                    $slug = s7x_blog_ensure_term($tax, html_entity_decode((string) $t['name'], ENT_QUOTES, 'UTF-8'), (string) $t['slug']);
                    $map[$tax][(string) $t['id']] = $slug;
                    if ($tax === 'categories' && trim((string) ($t['description'] ?? '')) !== '') {
                        $terms = s7x_blog_terms(true);
                        if (($terms['categories'][$slug]['description'] ?? '') === '') {
                            $terms['categories'][$slug]['description'] = trim(strip_tags((string) $t['description']));
                            s7x_blog_save_terms($terms);
                        }
                    }
                }
                if (count($list) < 100) {
                    break;
                }
            }
        }
        // Lista de posts publicados (só ids), do mais antigo para o mais novo
        $queue = [];
        for ($page = 1; $page <= 200; $page++) {
            $list = s7x_blog_wp_get($src, 'posts?per_page=100&page=' . $page . '&_fields=id&orderby=date&order=asc');
            if (!$list) {
                break;
            }
            foreach ($list as $p) {
                $queue[] = (int) $p['id'];
            }
            if (count($list) < 100) {
                break;
            }
        }
    } catch (RuntimeException $e) {
        json_out(['ok' => false, 'error' => $e->getMessage() . '. Confira se o site antigo está no ar e se a API (wp-json) não está bloqueada.'], 502);
    }
    $already = s7x_blog_index(true)['wp'];
    $skip = empty($_POST['overwrite']);
    $todo = array_values(array_filter($queue, fn ($id) => !($skip && isset($already[(string) $id]))));
    $hosts = array_values(array_unique(array_filter(array_merge(
        [strtolower((string) parse_url($src, PHP_URL_HOST))],
        array_map(fn ($h) => strtolower(trim(preg_replace('#^https?://#', '', $h), '/ ')), explode(',', (string) ($_POST['hosts'] ?? '')))
    ))));
    $withWww = [];
    foreach ($hosts as $h) {
        $withWww[] = $h;
        $withWww[] = strpos($h, 'www.') === 0 ? substr($h, 4) : 'www.' . $h;
    }
    $state = [
        'source' => $src, 'hosts' => array_values(array_unique($withWww)), 'map' => $map, 'queue' => $todo, 'total' => count($todo),
        'done' => 0, 'errors' => [], 'images' => !empty($_POST['images']), 'local' => trim((string) ($_POST['local'] ?? ''), '/ '),
        'started' => date('c'), 'skipped' => count($queue) - count($todo),
    ];
    s7x_blog_import_save($state);
    json_out(['ok' => true, 'total' => count($todo), 'skipped' => $state['skipped'], 'categories' => count($map['categories']), 'tags' => count($map['tags'])]);
}

/* -------------------------------------------------------------------------
 * Passo 2: posts em lotes pequenos (evita estourar o tempo do servidor)
 * ---------------------------------------------------------------------- */

function s7x_blog_import_batch(): void
{
    require_perm('admin');
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $s = s7x_blog_import_state();
    if (!$s || empty($s['source'])) {
        json_out(['ok' => false, 'error' => 'Comece a importação de novo.'], 400);
    }
    $n = max(1, min(20, (int) ($_POST['n'] ?? 4)));
    $ids = array_splice($s['queue'], 0, $n);
    $log = [];
    if ($ids) {
        try {
            $posts = s7x_blog_wp_get($s['source'], 'posts?include=' . implode(',', $ids) . '&per_page=' . count($ids) . '&_embed=wp:featuredmedia', 90);
        } catch (RuntimeException $e) {
            // Devolve os ids para a fila e tenta de novo no próximo lote
            $s['queue'] = array_merge($ids, $s['queue']);
            s7x_blog_import_save($s);
            json_out(['ok' => false, 'retry' => true, 'error' => $e->getMessage()], 502);
        }
        foreach ($posts as $wp) {
            try {
                $id = s7x_blog_import_one($wp, $s);
                $log[] = '✓ ' . html_entity_decode(strip_tags((string) ($wp['title']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8');
            } catch (Throwable $e) {
                $s['errors'][] = ['id' => $wp['id'] ?? 0, 'error' => $e->getMessage()];
                $log[] = '✗ ' . ($wp['slug'] ?? '?') . ': ' . $e->getMessage();
            }
            $s['done']++;
        }
        // Ids que a API não devolveu (rascunhos, privados)
        $s['done'] += count($ids) - count($posts);
    }
    s7x_blog_import_save($s);
    $finished = !$s['queue'];
    json_out(['ok' => true, 'done' => $s['done'], 'total' => $s['total'], 'finished' => $finished, 'log' => $log, 'errors' => count($s['errors'])]);
}

/** Copia uma imagem do site antigo (pasta local ou download) e registra o redirecionamento. */
function s7x_blog_import_image(string $url, array $s, string $name, string $alt, string $date): string
{
    static $done = [];
    $url = html_entity_decode(trim($url), ENT_QUOTES, 'UTF-8');
    if ($url === '') {
        return '';
    }
    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }
    if ($url[0] === '/') {
        $url = $s['source'] . $url;
    }
    $path = (string) parse_url($url, PHP_URL_PATH);
    $orig = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $path); // original sem o tamanho do WordPress
    if (isset($done[$orig])) {
        return $done[$orig];
    }
    $redirects = s7x_blog_redirects();
    if (isset($redirects[$orig]) && is_file(CMS_ROOT . '/' . $redirects[$orig])) {
        return $done[$orig] = (string) $redirects[$orig];
    }
    $bytes = '';
    $rel = preg_replace('#^.*?/wp-content/uploads/#', '', $orig);
    if ($s['local'] !== '' && $rel !== $orig) {
        $file = CMS_ROOT . '/' . $s['local'] . '/' . $rel;
        if (is_file($file)) {
            $bytes = (string) file_get_contents($file);
        }
    }
    if ($bytes === '' && !empty($s['images'])) {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $base = in_array(strtolower($host), $s['hosts'], true) ? $s['source'] : (string) preg_replace('#(https?://[^/]+).*#', '$1', $url);
        $bytes = s7x_blog_download($base . $orig, 40);
        if ($bytes === '' && $orig !== $path) {
            $bytes = s7x_blog_download($base . $path, 40);
        }
    }
    if ($bytes === '') {
        return '';
    }
    $new = s7x_blog_store_image($bytes, pathinfo($orig, PATHINFO_FILENAME) ?: $name, $alt, $date);
    if ($new !== '') {
        if (strpos($orig, '/wp-content/uploads/') !== false) {
            s7x_blog_add_redirects([substr($orig, strpos($orig, '/wp-content/')) => $new]);
        }
        $done[$orig] = $new;
    }
    return $new;
}

function s7x_blog_import_one(array $wp, array $s): string
{
    $title = html_entity_decode(strip_tags((string) ($wp['title']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8');
    $date = (string) ($wp['date'] ?? date('c'));
    $html = (string) ($wp['content']['rendered'] ?? '');

    // Crédito da imagem destacada que o plugin antigo colocava no fim do texto
    $credit = '';
    if (preg_match('#<p[^>]*class="[^"]*shc-featured-credit[^"]*"[^>]*>(.*?)</p>#is', $html, $m)) {
        $credit = cms_sanitize_html(preg_replace('/^\s*Imagem de destaque\s*[—–-]\s*/u', '', $m[1]));
    }
    // Artigos que vieram da rede (crédito com link para o original)
    $sourceUrl = '';
    if (preg_match('#class="[^"]*shc-credit[^"]*"[^>]*>.*?href="([^"]+)"#is', $html, $m)) {
        $sourceUrl = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    // Imagens do texto: copia para o site novo
    $html = preg_replace_callback('#<img\b[^>]*>#i', function ($m) use ($s, $title, $date) {
        $tag = $m[0];
        $src = '';
        foreach (['data-src', 'data-lazy-src', 'src'] as $a) {
            if (preg_match('#\s' . $a . '="([^"]+)"#i', $tag, $x) && strpos($x[1], 'data:') !== 0) {
                $src = $x[1];
                break;
            }
        }
        if ($src === '') {
            return '';
        }
        preg_match('#\salt="([^"]*)"#i', $tag, $alt);
        $altTxt = html_entity_decode($alt[1] ?? '', ENT_QUOTES, 'UTF-8');
        $host = strtolower((string) parse_url($src, PHP_URL_HOST));
        $new = '';
        if ($host === '' || in_array($host, $s['hosts'], true) || strpos($src, '/wp-content/uploads/') !== false) {
            $new = s7x_blog_import_image($src, $s, cms_slug($title), $altTxt ?: $title, $date);
        }
        $final = $new !== '' ? '/' . $new : $src;
        return '<img src="' . e($final) . '" alt="' . e($altTxt) . '">';
    }, $html);

    // Links para o próprio site antigo viram endereços relativos (funcionam no domínio novo)
    $hosts = $s['hosts'];
    $html = preg_replace_callback('#href="(https?://([^/"]+)(/[^"]*)?)"#i', function ($m) use ($hosts) {
        if (!in_array(strtolower($m[2]), $hosts, true)) {
            return $m[0];
        }
        $p = $m[3] ?? '/';
        $red = s7x_blog_redirects();
        $orig = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', (string) parse_url($p, PHP_URL_PATH));
        if (isset($red[$orig])) {
            return 'href="/' . e($red[$orig]) . '"';
        }
        return 'href="' . e($p) . '"';
    }, $html);

    // Imagem de destaque
    $image = ['src' => '', 'alt' => ''];
    $media = $wp['_embedded']['wp:featuredmedia'][0] ?? null;
    if (is_array($media) && !empty($media['source_url'])) {
        $alt = trim((string) ($media['alt_text'] ?? '')) ?: $title;
        $new = s7x_blog_import_image((string) $media['source_url'], $s, cms_slug($title), $alt, $date);
        if ($new !== '') {
            $image = ['src' => $new, 'alt' => $alt];
        }
    }
    $cats = [];
    foreach ((array) ($wp['categories'] ?? []) as $c) {
        if (isset($s['map']['categories'][(string) $c])) {
            $cats[] = $s['map']['categories'][(string) $c];
        }
    }
    $tags = [];
    foreach ((array) ($wp['tags'] ?? []) as $t) {
        if (isset($s['map']['tags'][(string) $t])) {
            $tags[] = $s['map']['tags'][(string) $t];
        }
    }
    $excerpt = trim(html_entity_decode(strip_tags((string) ($wp['excerpt']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8'));
    $excerpt = preg_replace('/\s*(\[(…|&hellip;|\.\.\.)\]|Leia mais.*|Continue lendo.*)$/u', '', $excerpt);
    $existing = s7x_blog_index()['wp'][(string) $wp['id']] ?? '';
    $data = [
        'title' => $title, 'slug' => (string) $wp['slug'], 'content' => $html, 'excerpt' => mb_strimwidth((string) $excerpt, 0, 300, '…'),
        'status' => 'publish', 'date' => $date, 'modified' => (string) ($wp['modified'] ?? $date), 'cats' => $cats, 'tags' => $tags,
        'image' => $image, 'image_credit' => $credit, 'wp_id' => (int) $wp['id'],
        'source' => $sourceUrl !== '' ? 'rede' : 'wordpress', 'source_url' => $sourceUrl,
        'seo' => $sourceUrl !== '' ? ['canonical' => $sourceUrl] : [],
    ];
    return s7x_blog_save($data, $existing);
}

/* -------------------------------------------------------------------------
 * Tela
 * ---------------------------------------------------------------------- */

function s7x_blog_import_render(): void
{
    $s = s7x_blog_import_state();
    $count = count(s7x_blog_index()['posts']);
    $pending = $s && !empty($s['queue']);
    $hasLocal = is_dir(CMS_ROOT . '/wp-content/uploads');
    ?>
    <section class="panel">
      <h2>Trazer os posts de um site WordPress</h2>
      <ol class="blog-imp-steps">
        <li>O site antigo precisa estar no ar enquanto a importação roda (pode ser num endereço provisório).</li>
        <li>Os posts mantêm o mesmo endereço (ex.: /nome-do-post/), a data, as categorias e as tags.</li>
        <li>As imagens são copiadas, convertidas para WebP e reduzidas para no máximo 1200 px. Os endereços antigos das imagens passam a redirecionar para as novas.</li>
        <li>Pode fechar e voltar depois: a importação continua de onde parou.</li>
      </ol>
      <p class="muted small">Hoje o blog tem <?= $count ?> post(s). Posts já importados são pulados.</p>
      <form class="form" data-blogimp>
        <div class="grid">
          <div class="field field--wide"><label for="impsrc">Endereço do site WordPress</label><input id="impsrc" type="url" name="source" required placeholder="https://www.site-antigo.com.br" value="<?= e($s['source'] ?? '') ?>"></div>
          <div class="field field--wide"><label for="imphosts">Outros endereços do site antigo (opcional)</label><input id="imphosts" type="text" name="hosts" placeholder="seudominio.com.br, www.seudominio.com.br"><span class="help">Se a origem é um endereço provisório, informe aqui o domínio de verdade para os links e imagens dos posts serem reconhecidos.</span></div>
          <div class="field field--wide"><label for="implocal">Pasta com as imagens antigas no servidor (opcional)</label><input id="implocal" type="text" name="local" value="<?= $hasLocal ? 'wp-content/uploads' : '' ?>" placeholder="wp-content/uploads"><span class="help">Se você copiou a pasta uploads do WordPress para dentro do site novo (Gerenciador de Arquivos), as imagens são lidas dali, sem baixar. <?= $hasLocal ? '<strong>A pasta wp-content/uploads foi encontrada.</strong>' : '' ?></span></div>
          <label class="blog-checkline"><input type="checkbox" name="images" value="1" checked> Baixar as imagens que não estiverem na pasta</label>
          <label class="blog-checkline"><input type="checkbox" name="overwrite" value="1"> Importar de novo os posts que já foram importados (atualiza)</label>
        </div>
        <div class="row" style="gap:.5rem;margin-top:1rem">
          <button class="btn btn--primary" data-imp-go><?= $pending ? 'Recomeçar do zero' : 'Começar a importação' ?></button>
          <?php if ($pending): ?><button type="button" class="btn" data-imp-continue>Continuar de onde parou (<?= (int) $s['done'] ?> de <?= (int) $s['total'] ?>)</button><?php endif; ?>
        </div>
      </form>
    </section>
    <section class="panel" data-imp-progress hidden>
      <h2>Progresso</h2>
      <p data-imp-status aria-live="polite">Preparando…</p>
      <div class="blog-imp-bar"><span data-imp-bar></span></div>
      <div class="blog-imp-log" data-imp-log></div>
      <p class="row" style="gap:.5rem;margin-top:1rem"><button type="button" class="btn" data-imp-pause>Pausar</button><a class="btn btn--ghost" href="<?= e(admin_url('blog')) ?>">Ver os posts</a></p>
    </section>
    <?php if (!empty($s['errors'])): ?>
    <section class="panel"><h2>Avisos da última importação</h2><ul class="small"><?php foreach (array_slice($s['errors'], -20) as $er): ?><li>Post <?= (int) $er['id'] ?>: <?= e($er['error']) ?></li><?php endforeach; ?></ul></section>
    <?php endif; ?>
    <script>
    (function () {
      var form = document.querySelector('[data-blogimp]'); if (!form) return;
      var api = document.body.getAttribute('data-api'), csrf = (document.querySelector('meta[name=csrf]') || {}).content || '';
      var box = document.querySelector('[data-imp-progress]'), st = document.querySelector('[data-imp-status]'), bar = document.querySelector('[data-imp-bar]'), log = document.querySelector('[data-imp-log]');
      var paused = false, fails = 0;
      function post(action, extra) {
        var fd = new FormData(); fd.append('action', action); fd.append('_csrf', csrf);
        Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
        return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Resposta inválida do servidor (HTTP ' + r.status + ')', retry: true }; }); });
      }
      function line(t) { log.textContent += t + '\n'; log.scrollTop = log.scrollHeight; }
      function batch() {
        if (paused) { st.textContent = 'Pausado. Clique em Continuar para seguir.'; return; }
        post('blogimp_batch', { n: 4 }).then(function (j) {
          if (!j.ok) {
            line('Aviso: ' + j.error);
            if (j.retry && ++fails < 6) { st.textContent = 'Tentando de novo…'; setTimeout(batch, 4000); return; }
            st.textContent = 'Parou com erro. Clique em Continuar para tentar de novo.'; return;
          }
          fails = 0;
          (j.log || []).forEach(line);
          var pct = j.total ? Math.round(j.done / j.total * 100) : 100;
          bar.style.width = pct + '%';
          st.textContent = j.finished ? 'Pronto! ' + j.done + ' post(s) importados' + (j.errors ? ' (' + j.errors + ' com aviso)' : '') + '.' : 'Importando… ' + j.done + ' de ' + j.total + ' (' + pct + '%)';
          if (!j.finished) batch();
        }).catch(function () { if (++fails < 6) setTimeout(batch, 5000); else st.textContent = 'Sem resposta do servidor. Clique em Continuar.'; });
      }
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        box.hidden = false; log.textContent = ''; st.textContent = 'Lendo categorias, tags e a lista de posts…'; paused = false;
        var d = {}; new FormData(form).forEach(function (v, k) { d[k] = v; });
        post('blogimp_start', d).then(function (j) {
          if (!j.ok) { st.textContent = j.error; return; }
          line(j.categories + ' categorias e ' + j.tags + ' tags. ' + j.total + ' post(s) para importar' + (j.skipped ? ' (' + j.skipped + ' já estavam no blog)' : '') + '.');
          batch();
        });
      });
      var cont = document.querySelector('[data-imp-continue]');
      if (cont) cont.addEventListener('click', function () { box.hidden = false; paused = false; st.textContent = 'Continuando…'; batch(); });
      document.querySelector('[data-imp-pause]').addEventListener('click', function () { paused = !paused; this.textContent = paused ? 'Continuar' : 'Pausar'; if (!paused) batch(); });
    })();
    </script>
    <?php
}
