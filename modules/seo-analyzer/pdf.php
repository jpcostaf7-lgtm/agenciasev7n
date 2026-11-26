<?php
/**
 * Analisador de SEO — relatório em PDF para enviar ao cliente (só no painel)
 * Autor: João Costa
 *
 * /analisador-seo/relatorio/{id}/pdf/ abre um documento A4 já paginado: capa, resumo,
 * plano de ação, prévias, velocidade, todos os itens, páginas internas e contato.
 * O navegador salva em PDF (Ctrl+P → Salvar como PDF), com texto selecionável e
 * links clicáveis. Visitantes sem login no painel vão para o relatório normal.
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_seo_pdf_url(string $id): string
{
    return cms_url(s7x_seo_base() . '/relatorio/' . $id . '/pdf/');
}

/** Atende /…/relatorio/{id}/pdf/ antes das páginas. */
function s7x_seo_pdf_request(): void
{
    $path = rtrim(cms_request_path(), '/');
    if (!preg_match('#^/' . preg_quote(s7x_seo_base(), '#') . '/relatorio/([a-z0-9]{12})/pdf$#', $path, $m)) {
        return;
    }
    $r = s7x_seo_get($m[1]);
    if (!$r) {
        return; // segue para o 404 do site
    }
    if (!s7x_seo_is_admin()) {
        header('Location: ' . s7x_seo_report_url($r['id']), true, 302);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    echo s7x_seo_render_pdf($r);
    exit;
}

function s7x_seo_pdf_kind(int $n): string
{
    return $n >= 90 ? 'otimo' : ($n >= 75 ? 'bom' : ($n >= 50 ? 'medio' : 'ruim'));
}

function s7x_seo_render_pdf(array $r): string
{
    $agency = trim((string) s7x_seo_opt('agency_name', '')) ?: cms_site_name();
    $site = preg_replace('#^https?://#', '', rtrim(cms_site_url(), '/'));
    $brand = function_exists('cms_brand') ? cms_brand() : [];
    $accent = $brand['accent'] ?? '#1f5eff';
    $dark = $brand['dark'] ?? '#14171f';
    $logo = function_exists('c') ? c('geral.logo', '') : '';
    $logoSrc = is_array($logo) ? (string) ($logo['src'] ?? '') : (string) $logo;
    $date = cms_format_date($r['created'], 'd/m/Y');
    $title = 'Relatorio-SEO-' . preg_replace('/[^a-z0-9.-]+/i', '-', $r['host']) . '-' . cms_format_date($r['created'], 'Y-m-d');
    $phone = trim((string) s7x_seo_opt('whatsapp', ''));
    if ($phone === '') {
        foreach (['contato.whatsapp', 'contato.phone', 'geral.whatsapp'] as $k) {
            if (strlen(cms_digits((string) c($k, ''))) >= 10) {
                $phone = (string) c($k, '');
                break;
            }
        }
    }
    $email = (string) c('contato.email', '');
    $wa = s7x_seo_whatsapp($r);
    $ready = $r['status'] === 'pronto';

    $sc = $r['scores'] ?? [];
    $total = (int) ($sc['total'] ?? 0);
    $items = $r['items'] ?? [];
    $byKey = [];
    foreach ($items as $it) {
        $byKey[$it['cat'] . ':' . $it['id']] = $it;
    }
    $plan = array_values(array_filter(array_map(fn ($k) => $byKey[$k] ?? null, (array) ($r['plan'] ?? []))));
    $counts = ['erro' => 0, 'aviso' => 0, 'ok' => 0];
    foreach ($items as $it) {
        if (isset($counts[$it['status']])) {
            $counts[$it['status']]++;
        }
    }
    $ai = is_array($r['ai'] ?? null) ? $r['ai'] : [];
    $lead = $r['lead'] ?? [];
    $url = $r['final_url'] ?: $r['url'];
    $shot = $r['psi']['mobile']['screenshot'] ?? '';
    $imp = ['alto' => 'alto', 'medio' => 'médio', 'baixo' => 'baixo'];
    $esf = ['facil' => 'baixo', 'medio' => 'médio', 'dificil' => 'alto'];
    $order = ['erro' => 0, 'aviso' => 1, 'info' => 2, 'ok' => 3];

    ob_start(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(cms_module_url('seo-analyzer', 'assets/seo.css')) ?>?v=<?= e(s7x_seo_asset_v()) ?>">
<link rel="stylesheet" href="<?= e(cms_module_url('seo-analyzer', 'assets/pdf.css')) ?>?v=<?= e(s7x_seo_asset_v()) ?>">
<?php $q = fn (string $s) => '"' . str_replace(['\\', '"', '<'], ['', "'", ''], $s) . '"'; ?>
<style>:root{--pdf-accent:<?= e($accent) ?>;--pdf-dark:<?= e($dark) ?>}.pdf-doc{--seo-accent:<?= e($accent) ?>}
@page{@bottom-left{content:<?= $q($agency . ' · ' . $site) ?>;font:8pt system-ui,sans-serif;color:#5a6070}@bottom-right{content:<?= $q('Relatório de SEO · ' . $r['host'] . ' · página ') ?> counter(page) " de " counter(pages);font:8pt system-ui,sans-serif;color:#5a6070}}
@page:first{@bottom-left{content:none}@bottom-right{content:none}}</style>
</head>
<body class="pdf">

<div class="pdf-bar" data-pdf-bar>
  <div>
    <strong>Relatório de <?= e($r['host']) ?> pronto para PDF</strong>
    <span>Na janela de impressão, escolha <b>Salvar como PDF</b> em "Destino". Deixe as margens no padrão e ative <b>Gráficos de plano de fundo</b>.</span>
  </div>
  <button type="button" onclick="window.print()">Salvar PDF</button>
</div>


<main class="pdf-doc">
<?php if (!$ready): ?>
  <section class="pdf-page"><h1 class="pdf-h">Este relatório não está pronto</h1><p><?= $r['status'] === 'erro' ? 'A análise terminou com erro: ' . e((string) $r['error']) : 'A análise ainda está em andamento.' ?></p></section>
<?php else: ?>

  <!-- Capa -->
  <section class="pdf-cover">
    <div class="pdf-cover__band">
      <span class="pdf-cover__brand"><?php if ($logoSrc !== ''): ?><img src="<?= e(cms_url($logoSrc)) ?>" alt=""><?php endif; ?><b><?= e($agency) ?></b></span>
      <span>Relatório de SEO</span>
    </div>
    <div class="pdf-cover__body">
      <div class="pdf-cover__info">
        <p class="pdf-cover__k">Análise do site</p>
        <h1 class="pdf-cover__host"><?= e($r['host']) ?></h1>
        <p class="pdf-cover__url"><a href="<?= e($url) ?>"><?= e(mb_strimwidth($url, 0, 80, '…')) ?></a></p>
        <dl class="pdf-cover__meta">
          <div><dt>Data da análise</dt><dd><?= e(cms_format_date($r['created'], 'd/m/Y \à\s H:i')) ?></dd></div>
          <?php if (($r['keyword'] ?? '') !== ''): ?><div><dt>Palavra-chave</dt><dd><?= e($r['keyword']) ?></dd></div><?php endif; ?>
          <?php if (!empty($lead['name'])): ?><div><dt>Preparado para</dt><dd><?= e($lead['name']) ?></dd></div><?php endif; ?>
          <div><dt>Preparado por</dt><dd><?= e($agency) ?></dd></div>
        </dl>
      </div>
      <div class="pdf-cover__score">
        <?= s7x_seo_ring($total, 'g', 'de 100') ?>
        <p class="pdf-verdict is-<?= e($sc['grade']['key'] ?? '') ?>"><strong><?= e($sc['grade']['label'] ?? '') ?></strong><span><?= e($sc['grade']['text'] ?? '') ?></span></p>
        <div class="pdf-capnote"><?= s7x_seo_score_notes($sc) ?></div>
      </div>
    </div>
    <ul class="pdf-tally">
      <li class="is-erro"><b><?= $counts['erro'] ?></b><span>para corrigir</span></li>
      <li class="is-aviso"><b><?= $counts['aviso'] ?></b><span>de atenção</span></li>
      <li class="is-ok"><b><?= $counts['ok'] ?></b><span>certos</span></li>
    </ul>
    <h2 class="pdf-h2">Nota por área</h2>
    <ol class="pdf-cats">
      <?php foreach ((array) ($sc['cats'] ?? []) as $c): ?>
        <li class="is-<?= s7x_seo_pdf_kind((int) $c['score']) ?>"><span class="pdf-cats__ic"><?= s7x_seo_icon($c['icon']) ?></span><span class="pdf-cats__n"><?= e($c['label']) ?></span><span class="pdf-cats__bar"><i style="width:<?= (int) $c['score'] ?>%"></i></span><b><?= (int) $c['score'] ?></b></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <!-- Resumo e plano -->
  <section class="pdf-sec pdf-break">
    <?php if (!empty($ai['resumo'])): ?>
      <h2 class="pdf-h">Resumo</h2>
      <p class="pdf-lead"><?= e($ai['resumo']) ?></p>
      <?php if (!empty($ai['pontos_fortes'])): ?>
        <div class="pdf-good"><strong>O que já está bom</strong><ul><?php foreach ($ai['pontos_fortes'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
    <?php endif; ?>

    <h2 class="pdf-h">Plano de ação</h2>
    <p class="pdf-sub">Do que mais traz resultado para o que menos traz. Comece pelo topo.</p>
    <?php if (!empty($ai['prioridades'])): ?>
      <ol class="pdf-steps">
        <?php foreach ($ai['prioridades'] as $n => $p): ?>
          <li><span class="pdf-steps__n"><?= $n + 1 ?></span><div><strong><?= e($p['titulo']) ?></strong><p><?= e($p['por_que']) ?></p><p class="pdf-how"><b>Como fazer:</b> <?= e($p['como']) ?></p></div></li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <ol class="pdf-steps">
        <?php foreach (array_slice($plan, 0, 5) as $n => $it): ?>
          <li class="is-<?= e($it['status']) ?>"><span class="pdf-steps__n"><?= $n + 1 ?></span><div><strong><?= e($it['titulo']) ?></strong><?php if ($it['detalhe'] !== ''): ?><p><?= e($it['detalhe']) ?></p><?php endif; ?><p class="pdf-how"><b>Como resolver:</b> <?= e($it['como']) ?></p></div></li>
        <?php endforeach; ?>
        <?php if (!$plan): ?><li class="is-ok"><span class="pdf-steps__n">✓</span><div><strong>Nada urgente para corrigir.</strong><p>O site passou em todos os itens com peso.</p></div></li><?php endif; ?>
      </ol>
    <?php endif; ?>
  </section>

  <?php if (count($plan) > (empty($ai['prioridades']) ? 5 : 0)): ?>
  <section class="pdf-sec">
    <h2 class="pdf-h">Lista de correções</h2>
    <p class="pdf-sub"><?= count($plan) ?> itens em ordem de prioridade, com o impacto no Google e o esforço para corrigir.</p>
    <table class="pdf-table pdf-fix">
      <thead><tr><th>#</th><th>O que corrigir</th><th>Impacto</th><th>Esforço</th></tr></thead>
      <tbody>
        <?php foreach ($plan as $n => $it): ?>
          <tr class="is-<?= e($it['status']) ?>">
            <td class="pdf-fix__n"><?= $n + 1 ?></td>
            <td><strong><?= e($it['titulo']) ?></strong><span class="pdf-fix__cat"><?= e(s7x_seo_categories()[$it['cat']]['short'] ?? '') ?></span><?php if ($it['como'] !== ''): ?><p><?= e($it['como']) ?></p><?php endif; ?></td>
            <td><span class="pdf-pill is-imp-<?= e($it['impacto']) ?>"><?= e($imp[$it['impacto']] ?? $it['impacto']) ?></span></td>
            <td><span class="pdf-pill"><?= e($esf[$it['esforco']] ?? $it['esforco']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>

  <div class="pdf-break pdf-reuse">
    <?= s7x_seo_render_serp($r) ?>
    <?= s7x_seo_render_speed($r) ?>
  </div>

  <!-- Todos os itens, abertos -->
  <section class="pdf-sec pdf-break">
    <h2 class="pdf-h">Todos os itens verificados</h2>
    <p class="pdf-sub">Cada área do site, com o que está certo, o que pede atenção e o que precisa ser corrigido.</p>
    <?php foreach ((array) ($sc['cats'] ?? []) as $k => $c):
        $list = array_values(array_filter($items, fn ($i) => $i['cat'] === $k));
        usort($list, fn ($a, $b) => ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9)); ?>
      <div class="pdf-group">
        <h3 class="pdf-group__h"><span class="pdf-cats__ic"><?= s7x_seo_icon($c['icon']) ?></span><?= e($c['label']) ?><b class="is-<?= s7x_seo_pdf_kind((int) $c['score']) ?>"><?= (int) $c['score'] ?></b></h3>
        <ul class="pdf-items">
          <?php foreach ($list as $it): ?>
            <li class="is-<?= e($it['status']) ?>">
              <span class="pdf-items__st"><?= s7x_seo_icon($it['status']) ?></span>
              <div><strong><?= e($it['titulo']) ?></strong><?php if ($it['detalhe'] !== ''): ?><p><?= e($it['detalhe']) ?></p><?php endif; ?><?php if ($it['como'] !== '' && $it['status'] !== 'ok'): ?><p class="pdf-how"><b>Como resolver:</b> <?= e($it['como']) ?></p><?php endif; ?></div>
              <span class="pdf-items__lbl"><?= e(s7x_seo_status_label($it['status'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </section>

  <div class="pdf-reuse">
    <?= s7x_seo_render_pages($r) ?>
    <?= s7x_seo_render_tech($r) ?>
  </div>

  <!-- Contato -->
  <section class="pdf-end pdf-break">
    <div class="pdf-end__band">
      <span class="pdf-cover__brand"><?php if ($logoSrc !== ''): ?><img src="<?= e(cms_url($logoSrc)) ?>" alt=""><?php endif; ?><b><?= e($agency) ?></b></span>
    </div>
    <h2 class="pdf-end__t"><?= e((string) s7x_seo_opt('cta_title', 'Quer que a gente resolva isso para você?')) ?></h2>
    <?php if (trim((string) s7x_seo_opt('cta_text', '')) !== ''): ?><p class="pdf-lead"><?= e((string) s7x_seo_opt('cta_text', '')) ?></p><?php endif; ?>
    <ul class="pdf-contact">
      <?php if ($phone !== ''): ?><li><span>WhatsApp</span><?php if ($wa !== ''): ?><a href="<?= e($wa) ?>"><?= e($phone) ?></a><?php else: ?><?= e($phone) ?><?php endif; ?></li><?php endif; ?>
      <?php if ($email !== ''): ?><li><span>E-mail</span><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
      <li><span>Site</span><a href="<?= e(cms_site_url()) ?>"><?= e($site) ?></a></li>
      <li><span>Relatório on-line</span><a href="<?= e(s7x_seo_report_url($r['id'], true)) ?>"><?= e(preg_replace('#^https?://#', '', s7x_seo_report_url($r['id'], true))) ?></a></li>
    </ul>
    <p class="pdf-note">Relatório gerado em <?= e($date) ?> pelo analisador de SEO da <?= e($agency) ?>, com dados do próprio site e do Google PageSpeed Insights. Os resultados mudam conforme o site é atualizado.</p>
  </section>
<?php endif; ?>
</main>

<script>
(function () {
  // Abre a janela de impressão sozinho quando a página terminar de carregar (só na primeira vez)
  if (!/[?&]auto=0/.test(location.search)) {
    window.addEventListener('load', function () {
      var imgs = Array.prototype.slice.call(document.images);
      Promise.all(imgs.map(function (i) { return i.complete ? 1 : new Promise(function (r) { i.onload = i.onerror = r; }); }))
        .then(function () { return document.fonts ? document.fonts.ready : 1; })
        .then(function () { setTimeout(function () { window.print(); }, 300); });
    });
  }
})();
</script>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
