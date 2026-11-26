<?php
/**
 * Analisador de SEO — HTML do relatório (o mesmo na ferramenta, no link compartilhado e no PDF)
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_seo_status_label(string $s): string
{
    return ['ok' => 'Certo', 'aviso' => 'Atenção', 'erro' => 'Corrigir', 'info' => 'Info'][$s] ?? $s;
}

function s7x_seo_icon(string $name): string
{
    $p = [
        'ok' => '<path d="M5 12.5l4.2 4.2L19 7"/>',
        'aviso' => '<path d="M12 8v5"/><circle cx="12" cy="16.6" r=".6" fill="currentColor"/><path d="M10.3 3.9L2.6 17.5A2 2 0 0 0 4.3 20.5h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'erro' => '<path d="M7 7l10 10M17 7L7 17"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="8" r=".6" fill="currentColor"/>',
        'lupa' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'texto' => '<path d="M5 6h14M5 10h14M5 14h9M5 18h11"/>',
        'raio' => '<path d="M13 2.5L5 13.5h6l-1 8 8-11h-6z"/>',
        'celular' => '<rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M11 18.5h2"/>',
        'imagem' => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8.5 8.5"/>',
        'link' => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>',
        'escudo' => '<path d="M12 2.8l7.5 3v5.6c0 4.7-3.2 8.3-7.5 9.8-4.3-1.5-7.5-5.1-7.5-9.8V5.8z"/>',
        'olho' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'faisca' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 15.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z"/>',
        'alvo' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r=".8" fill="currentColor"/>',
        'cadeado' => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'pdf' => '<path d="M14 2.5H7a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7.5z"/><path d="M14 2.5v5h5M9 13h6M9 17h6"/>',
        'compartilhar' => '<circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="M8.2 10.8l7.6-4.1M8.2 13.2l7.6 4.1"/>',
        'whats' => '<path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.5L3.5 20.5l1.4-4.2a8.5 8.5 0 1 1 15.6-4.7z"/><path d="M9 8.8c.2 2.9 2.4 5.3 5.3 5.8l1.1-1.2-1.8-.9-.8.8c-1-.4-1.8-1.2-2.2-2.2l.8-.8-.9-1.8z"/>',
        'refazer' => '<path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v4.5h-4.5"/>',
    ];
    return '<svg class="seo-i" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($p[$name] ?? $p['info']) . '</svg>';
}

/** Anel de nota. */
function s7x_seo_ring(int $score, string $size = 'g', string $label = ''): string
{
    $r = 52;
    $c = 2 * M_PI * $r;
    $off = $c * (1 - max(0, min(100, $score)) / 100);
    $k = $score >= 90 ? 'otimo' : ($score >= 75 ? 'bom' : ($score >= 50 ? 'medio' : 'ruim'));
    return '<div class="seo-ring seo-ring--' . $size . ' is-' . $k . '" role="img" aria-label="Nota ' . $score . ' de 100' . ($label ? ' — ' . e($label) : '') . '">'
        . '<svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="' . $r . '" class="seo-ring__bg"/><circle cx="60" cy="60" r="' . $r . '" class="seo-ring__fg" stroke-dasharray="' . round($c, 2) . '" stroke-dashoffset="' . round($off, 2) . '" style="--seo-dash:' . round($c, 2) . '"/></svg>'
        . '<span class="seo-ring__n">' . $score . '</span>' . ($label ? '<span class="seo-ring__l">' . e($label) . '</span>' : '') . '</div>';
}

function s7x_seo_band(?string $cat): string
{
    return ['fast' => 'bom', 'good' => 'bom', 'average' => 'medio', 'needs-improvement' => 'medio', 'slow' => 'ruim', 'poor' => 'ruim'][$cat ?? ''] ?? '';
}

/**
 * Renderiza o relatório.
 * $full = false mostra só a nota, as categorias e os 3 principais problemas, com o formulário de contato.
 * $where: ferramenta (dentro do bloco) | pagina (link compartilhado)
 */
function s7x_seo_render_report(array $r, bool $full, string $where = 'pagina'): string
{
    if ($r['status'] !== 'pronto') {
        return '<div class="seo-empty">' . ($r['status'] === 'erro' ? 'Não foi possível concluir esta análise: ' . e($r['error']) : 'Esta análise ainda está em andamento.') . '</div>';
    }
    $sc = $r['scores'];
    $total = (int) $sc['total'];
    $items = $r['items'];
    $byKey = [];
    foreach ($items as $it) {
        $byKey[$it['cat'] . ':' . $it['id']] = $it;
    }
    $plan = array_values(array_filter(array_map(fn ($k) => $byKey[$k] ?? null, $r['plan'])));
    $counts = ['erro' => 0, 'aviso' => 0, 'ok' => 0];
    foreach ($items as $it) {
        if (isset($counts[$it['status']])) {
            $counts[$it['status']]++;
        }
    }
    $agency = trim((string) s7x_seo_opt('agency_name', '')) ?: cms_site_name();
    $url = $r['final_url'] ?: $r['url'];
    $shot = $r['psi']['mobile']['screenshot'] ?? '';
    $wa = s7x_seo_whatsapp($r);
    $reportUrl = s7x_seo_report_url($r['id'], true);
    $logo = function_exists('c') ? c('geral.logo', '') : '';
    $logoSrc = is_array($logo) ? ($logo['src'] ?? '') : (string) $logo;

    ob_start(); ?>
<article class="seo-report<?= $full ? '' : ' is-locked' ?>" data-seo-report="<?= e($r['id']) ?>" data-where="<?= e($where) ?>">
  <header class="seo-cover">
    <div class="seo-cover__brand">
      <span class="seo-cover__id"><?php if ($logoSrc !== ''): ?><img src="<?= e(cms_url($logoSrc)) ?>" alt="" class="seo-cover__logo"><?php endif; ?><strong><?= e($agency) ?></strong></span>
      <span>Relatório de SEO</span>
    </div>
    <div class="seo-cover__main">
      <div class="seo-cover__info">
        <p class="seo-cover__eyebrow">Análise de <?= e(cms_format_date($r['created'], 'd/m/Y \à\s H:i')) ?></p>
        <h2 class="seo-cover__host"><?= e($r['host']) ?></h2>
        <p class="seo-cover__url"><a href="<?= e($url) ?>" rel="nofollow noopener" target="_blank"><?= e(mb_strimwidth($url, 0, 70, '…')) ?></a><?= $r['keyword'] !== '' ? ' · palavra-chave: <strong>' . e($r['keyword']) . '</strong>' : '' ?></p>
        <div class="seo-verdict is-<?= e($sc['grade']['key']) ?>">
          <strong><?= e($sc['grade']['label']) ?></strong>
          <span><?= e($sc['grade']['text']) ?></span>
        </div>
        <?= s7x_seo_score_notes($sc) ?>
        <ul class="seo-tally">
          <li class="is-erro"><b><?= $counts['erro'] ?></b> para corrigir</li>
          <li class="is-aviso"><b><?= $counts['aviso'] ?></b> de atenção</li>
          <li class="is-ok"><b><?= $counts['ok'] ?></b> certos</li>
        </ul>
      </div>
      <div class="seo-cover__score">
        <?= s7x_seo_ring($total, 'g', 'de 100') ?>
        <?php if ($shot !== ''): ?>
          <figure class="seo-phone"><img src="<?= e(cms_url($shot)) ?>" alt="Como o site aparece no celular" loading="lazy"></figure>
        <?php endif; ?>
      </div>
    </div>
    <ol class="seo-cats">
      <?php foreach ($sc['cats'] as $k => $c): $kk = $c['score'] >= 90 ? 'otimo' : ($c['score'] >= 75 ? 'bom' : ($c['score'] >= 50 ? 'medio' : 'ruim')); ?>
        <li class="seo-cat is-<?= $kk ?>">
          <a href="#seo-<?= e($r['id'] . '-' . $k) ?>" <?= $full ? '' : 'tabindex="-1"' ?>>
            <span class="seo-cat__ic"><?= s7x_seo_icon($c['icon']) ?></span>
            <span class="seo-cat__name"><?= e($c['short']) ?></span>
            <span class="seo-cat__bar"><i style="width:<?= (int) $c['score'] ?>%"></i></span>
            <span class="seo-cat__n"><?= (int) $c['score'] ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  </header>

  <?php if (!$full): ?>
    <section class="seo-sec seo-top3">
      <h3 class="seo-h">Os problemas que mais pesam no seu site</h3>
      <ol class="seo-plan">
        <?php foreach (array_slice($plan, 0, 3) as $n => $it): ?>
          <li class="seo-plan__item is-<?= e($it['status']) ?>"><span class="seo-plan__n"><?= $n + 1 ?></span><div><strong><?= e($it['titulo']) ?></strong><p><?= e(mb_strimwidth($it['detalhe'], 0, 180, '…')) ?></p></div></li>
        <?php endforeach; ?>
      </ol>
    </section>
    <section class="seo-sec seo-gate" id="seo-gate-<?= e($r['id']) ?>">
      <div class="seo-gate__blur" aria-hidden="true">
        <?php foreach (array_slice($plan, 3, 4) as $it): ?><p><strong><?= e($it['titulo']) ?></strong> — <?= e(mb_strimwidth($it['como'], 0, 120, '…')) ?></p><?php endforeach; ?>
        <p>Velocidade no celular e no computador · Core Web Vitals · prévia no Google e no WhatsApp · páginas internas · links quebrados · plano de ação completo</p>
      </div>
      <form class="seo-gate__form" data-seo-lead novalidate>
        <h3><?= s7x_seo_icon('cadeado') ?> Receba o relatório completo grátis</h3>
        <p>São mais <?= max(0, count($plan) - 3) ?> itens para corrigir, com o passo a passo de cada um, a velocidade medida pelo Google e o PDF para enviar a quem cuida do seu site.</p>
        <?= s7x_seo_lead_fields() ?>
        <input type="hidden" name="id" value="<?= e($r['id']) ?>"><input type="hidden" name="onde" value="<?= e($where) ?>">
        <button class="seo-btn seo-btn--primary" type="submit">Ver relatório completo</button>
        <p class="seo-msg" role="alert" aria-live="polite"></p>
      </form>
    </section>
  <?php else: ?>

    <?php $ai = $r['ai'] ?? null; if (is_array($ai) && !empty($ai['resumo'])): ?>
      <section class="seo-sec seo-ai">
        <h3 class="seo-h"><?= s7x_seo_icon('faisca') ?> Resumo para você</h3>
        <p class="seo-ai__txt"><?= e($ai['resumo']) ?></p>
        <?php if (!empty($ai['pontos_fortes'])): ?>
          <p class="seo-ai__good"><strong>O que já está bom:</strong> <?= e(implode(' · ', $ai['pontos_fortes'])) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="seo-sec">
      <h3 class="seo-h">Plano de ação</h3>
      <p class="seo-sub">Do que mais traz resultado para o que menos traz. Comece pelo topo.</p>
      <?php if (is_array($ai) && !empty($ai['prioridades'])): ?>
        <ol class="seo-plan seo-plan--ai">
          <?php foreach ($ai['prioridades'] as $n => $p): ?>
            <li class="seo-plan__item"><span class="seo-plan__n"><?= $n + 1 ?></span><div><strong><?= e($p['titulo']) ?></strong><p><?= e($p['por_que']) ?></p><p class="seo-plan__how"><b>Como fazer:</b> <?= e($p['como']) ?></p></div></li>
          <?php endforeach; ?>
        </ol>
        <details class="seo-more"><summary>Ver todos os <?= count($plan) ?> itens para corrigir</summary>
      <?php endif; ?>
      <?php $noAi = !(is_array($ai) && !empty($ai['prioridades'])); $cut = 10; ?>
      <ol class="seo-plan">
        <?php foreach ($plan as $n => $it): if ($noAi && $n === $cut): ?>
      </ol>
      <details class="seo-more"><summary>Ver mais <?= count($plan) - $cut ?> itens</summary>
      <ol class="seo-plan" start="<?= $cut + 1 ?>">
        <?php endif; ?>
          <li class="seo-plan__item is-<?= e($it['status']) ?>">
            <span class="seo-plan__n"><?= $n + 1 ?></span>
            <div>
              <strong><?= e($it['titulo']) ?></strong>
              <?php if ($it['detalhe'] !== ''): ?><p><?= e($it['detalhe']) ?></p><?php endif; ?>
              <p class="seo-plan__how"><b>Como resolver:</b> <?= e($it['como']) ?></p>
              <span class="seo-tags"><span class="seo-tag is-imp-<?= e($it['impacto']) ?>">Impacto <?= e(['alto' => 'alto', 'medio' => 'médio', 'baixo' => 'baixo'][$it['impacto']]) ?></span><span class="seo-tag">Esforço <?= e(['facil' => 'baixo', 'medio' => 'médio', 'dificil' => 'alto'][$it['esforco']]) ?></span><span class="seo-tag"><?= e(s7x_seo_categories()[$it['cat']]['short']) ?></span></span>
            </div>
          </li>
        <?php endforeach; ?>
        <?php if (!$plan): ?><li class="seo-plan__item is-ok"><span class="seo-plan__n">✓</span><div><strong>Nada urgente para corrigir.</strong><p>Parabéns: o site passou em todos os itens com peso.</p></div></li><?php endif; ?>
      </ol>
      <?php if ($noAi && count($plan) > $cut): ?></details><?php endif; ?>
      <?php if (is_array($ai) && !empty($ai['prioridades'])): ?></details><?php endif; ?>
    </section>

    <?= s7x_seo_render_serp($r) ?>
    <?= s7x_seo_render_speed($r) ?>

    <section class="seo-sec">
      <h3 class="seo-h">Todos os itens verificados</h3>
      <div class="seo-groups">
        <?php foreach ($sc['cats'] as $k => $c): $list = array_values(array_filter($items, fn ($i) => $i['cat'] === $k)); usort($list, fn ($a, $b) => ['erro' => 0, 'aviso' => 1, 'info' => 2, 'ok' => 3][$a['status']] <=> ['erro' => 0, 'aviso' => 1, 'info' => 2, 'ok' => 3][$b['status']]); ?>
          <details class="seo-group" id="seo-<?= e($r['id'] . '-' . $k) ?>" <?= $c['count']['erro'] ? 'open' : '' ?>>
            <summary>
              <span class="seo-cat__ic"><?= s7x_seo_icon($c['icon']) ?></span>
              <span class="seo-group__t"><?= e($c['label']) ?></span>
              <span class="seo-group__c"><?= $c['count']['erro'] ? '<b class="is-erro">' . $c['count']['erro'] . '</b>' : '' ?><?= $c['count']['aviso'] ? '<b class="is-aviso">' . $c['count']['aviso'] . '</b>' : '' ?><b class="is-ok"><?= $c['count']['ok'] ?></b></span>
              <span class="seo-group__n is-<?= $c['score'] >= 90 ? 'otimo' : ($c['score'] >= 75 ? 'bom' : ($c['score'] >= 50 ? 'medio' : 'ruim')) ?>"><?= (int) $c['score'] ?></span>
            </summary>
            <ul class="seo-items">
              <?php foreach ($list as $it): ?>
                <li class="seo-it is-<?= e($it['status']) ?>">
                  <span class="seo-it__st" title="<?= e(s7x_seo_status_label($it['status'])) ?>"><?= s7x_seo_icon($it['status']) ?><span class="sr-only"><?= e(s7x_seo_status_label($it['status'])) ?>:</span></span>
                  <div><strong><?= e($it['titulo']) ?></strong><?php if ($it['detalhe'] !== ''): ?><p><?= e($it['detalhe']) ?></p><?php endif; ?><?php if ($it['como'] !== '' && $it['status'] !== 'ok'): ?><p class="seo-plan__how"><b>Como resolver:</b> <?= e($it['como']) ?></p><?php endif; ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endforeach; ?>
      </div>
    </section>

    <?= s7x_seo_render_pages($r) ?>
    <?= s7x_seo_render_tech($r) ?>
  <?php endif; ?>

  <section class="seo-sec seo-cta">
    <div>
      <h3><?= e((string) s7x_seo_opt('cta_title', 'Quer que a gente resolva isso para você?')) ?></h3>
      <p><?= e((string) s7x_seo_opt('cta_text', '')) ?></p>
    </div>
    <div class="seo-cta__actions">
      <?php if ($wa !== ''): ?><a class="seo-btn seo-btn--primary" href="<?= e($wa) ?>" target="_blank" rel="noopener" data-seo-wa><?= s7x_seo_icon('whats') ?> <?= e((string) s7x_seo_opt('cta_button', 'Falar com um especialista')) ?></a><?php endif; ?>
      <?php if ($full): ?>
        <?php if (s7x_seo_is_admin() && function_exists('s7x_seo_pdf_url')): ?><a class="seo-btn" href="<?= e(s7x_seo_pdf_url($r['id'])) ?>" target="_blank" rel="noopener"><?= s7x_seo_icon('pdf') ?> PDF para o cliente</a><?php else: ?>
        <button type="button" class="seo-btn" data-seo-print><?= s7x_seo_icon('pdf') ?> Baixar PDF</button><?php endif; ?>
        <button type="button" class="seo-btn" data-seo-share data-url="<?= e($reportUrl) ?>" data-title="Relatório de SEO de <?= e($r['host']) ?>"><?= s7x_seo_icon('compartilhar') ?> Compartilhar</button>
      <?php endif; ?>
      <?php if ($where === 'ferramenta'): ?><button type="button" class="seo-btn seo-btn--ghost" data-seo-again><?= s7x_seo_icon('refazer') ?> Analisar outro site</button><?php endif; ?>
    </div>
  </section>
  <footer class="seo-foot">
    <span><?= e($agency) ?> · <?= e(preg_replace('#^https?://#', '', cms_site_url())) ?></span>
    <span>Relatório <?= e($r['id']) ?> · <?= e(cms_format_date($r['created'], 'd/m/Y')) ?></span>
  </footer>
</article>
<?php
    return (string) ob_get_clean();
}

/** Por que a nota parou onde parou (tetos) e aviso de velocidade não medida. */
function s7x_seo_score_notes(array $sc): string
{
    $h = '';
    if (!empty($sc['caps'])) {
        $h .= '<p class="seo-capnote"><b>O que mais segura a nota:</b> ' . e(implode('; ', array_unique($sc['caps']))) . '.</p>';
    }
    if (!empty($sc['partial'])) {
        $h .= '<p class="seo-capnote">A velocidade não foi medida pelo Google PageSpeed nesta análise. Por isso a nota vai no máximo até 84 e pode ficar menor quando a velocidade for medida.</p>';
    }
    return $h;
}

function s7x_seo_lead_fields(): string
{
    $fields = explode('_', (string) s7x_seo_opt('lead_fields', 'nome_whatsapp_email'));
    $h = '<div class="seo-fields">';
    $h .= '<label class="seo-field"><span>Seu nome</span><input name="nome" autocomplete="name" required maxlength="120"></label>';
    if (in_array('whatsapp', $fields, true)) {
        $h .= '<label class="seo-field"><span>WhatsApp</span><input name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" placeholder="(21) 99999-9999" required maxlength="40"></label>';
    }
    if (in_array('email', $fields, true)) {
        $h .= '<label class="seo-field"><span>E-mail</span><input name="email" type="email" autocomplete="email" required maxlength="160"></label>';
    }
    $h .= '</div><input type="text" name="website" tabindex="-1" autocomplete="off" class="seo-hp" aria-hidden="true">';
    $h .= '<label class="seo-check"><input type="checkbox" name="aceite" value="1" required> <span>' . e((string) s7x_seo_opt('consent_text', 'Aceito receber o relatório e o contato de um especialista.')) . '</span></label>';
    return $h;
}

/** Prévia no Google e no WhatsApp. */
function s7x_seo_render_serp(array $r): string
{
    $f = $r['page'];
    $url = $r['final_url'] ?: $r['url'];
    $p = parse_url($url);
    $crumb = ($p['host'] ?? '') . (trim($p['path'] ?? '', '/') !== '' ? ' › ' . implode(' › ', array_slice(explode('/', trim($p['path'], '/')), 0, 2)) : '');
    $title = $f['title'] !== '' ? $f['title'] : ($f['h1'][0] ?? $r['host']);
    $desc = $f['description'] !== '' ? $f['description'] : mb_strimwidth((string) ($f['first_words'] ?? ''), 0, 158, '…');
    $og = $f['og'] ?? [];
    $ai = $r['ai'] ?? [];
    $fav = $f['favicon'] ?? '';
    ob_start(); ?>
    <section class="seo-sec">
      <h3 class="seo-h">Como o site aparece</h3>
      <div class="seo-previews">
        <figure class="seo-serp">
          <figcaption>No Google</figcaption>
          <div class="seo-serp__card">
            <div class="seo-serp__site"><span class="seo-serp__fav"><?php if ($fav): ?><img src="<?= e($fav) ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()"><?php endif; ?></span><span><b><?= e($og['site_name'] ?? $r['host']) ?></b><small><?= e($crumb) ?></small></span></div>
            <div class="seo-serp__title"><?= e(mb_strimwidth($title, 0, 62, '…')) ?></div>
            <div class="seo-serp__desc"><?= e(mb_strimwidth($desc, 0, 160, '…')) ?></div>
          </div>
          <?php if (!empty($ai['titulo_sugerido']) || !empty($ai['descricao_sugerida'])): ?>
            <div class="seo-suggest">
              <strong>Sugestão para clicar mais</strong>
              <?php if (!empty($ai['titulo_sugerido'])): ?><p><span>Título</span><?= e($ai['titulo_sugerido']) ?> <small>(<?= mb_strlen($ai['titulo_sugerido']) ?>)</small></p><?php endif; ?>
              <?php if (!empty($ai['descricao_sugerida'])): ?><p><span>Descrição</span><?= e($ai['descricao_sugerida']) ?> <small>(<?= mb_strlen($ai['descricao_sugerida']) ?>)</small></p><?php endif; ?>
            </div>
          <?php endif; ?>
        </figure>
        <figure class="seo-share">
          <figcaption>Quando alguém compartilha no WhatsApp</figcaption>
          <div class="seo-share__bubble">
            <div class="seo-share__card">
              <?php if (!empty($og['image'])): ?><img src="<?= e(s7x_seo_abs_url($og['image'], $url)) ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.parentNode.classList.add('no-img');this.remove()"><?php else: ?><div class="seo-share__noimg">Sem imagem de compartilhamento</div><?php endif; ?>
              <div class="seo-share__txt"><b><?= e(mb_strimwidth($og['title'] ?? $title, 0, 70, '…')) ?></b><span><?= e(mb_strimwidth($og['description'] ?? $desc, 0, 110, '…')) ?></span><small><?= e($r['host']) ?></small></div>
            </div>
            <span class="seo-share__link"><?= e($url) ?></span>
          </div>
        </figure>
      </div>
    </section>
    <?php
    return (string) ob_get_clean();
}

/** Velocidade (PageSpeed Insights). */
function s7x_seo_render_speed(array $r): string
{
    $m = $r['psi']['mobile'] ?? null;
    $d = $r['psi']['desktop'] ?? null;
    if (!$m || empty($m['ok'])) {
        return '';
    }
    $fd = $m['field'] ?? null;
    $metric = function (string $label, string $help, ?array $field, ?array $lab, string $kind) {
        $v = $field['v'] ?? ($lab['v'] ?? null);
        if ($v === null) {
            return '';
        }
        $band = $field ? s7x_seo_band($field['c'] ?? '') : '';
        if ($band === '') {
            $lim = ['lcp' => [2500, 4000], 'inp' => [200, 500], 'cls' => [0.1, 0.25], 'fcp' => [1800, 3000], 'ttfb' => [800, 1800], 'tbt' => [200, 600], 'si' => [3400, 5800]][$kind];
            $band = $v <= $lim[0] ? 'bom' : ($v <= $lim[1] ? 'medio' : 'ruim');
        }
        $txt = $kind === 'cls' ? number_format((float) $v, 2, ',', '') : s7x_seo_ms((float) $v);
        return '<li class="seo-metric is-' . $band . '"><span class="seo-metric__l">' . e($label) . '</span><b>' . e($txt) . '</b><small>' . e($help) . '</small></li>';
    };
    ob_start(); ?>
    <section class="seo-sec">
      <h3 class="seo-h">Velocidade medida pelo Google</h3>
      <p class="seo-sub">Google PageSpeed Insights<?= $fd ? ', com dados reais de quem visitou ' . ($m['field_scope'] === 'página' ? 'esta página' : 'o site') . ' nos últimos 28 dias' : '' ?>.</p>
      <div class="seo-speed">
        <div class="seo-speed__rings">
          <?= s7x_seo_ring((int) ($m['scores']['performance'] ?? 0), 'm', 'Celular') ?>
          <?php if ($d && !empty($d['ok'])): ?><?= s7x_seo_ring((int) ($d['scores']['performance'] ?? 0), 'm', 'Computador') ?><?php endif; ?>
          <?php if (isset($m['scores']['seo'])): ?><?= s7x_seo_ring((int) $m['scores']['seo'], 'm', 'SEO técnico') ?><?php endif; ?>
          <?php if (isset($m['scores']['accessibility'])): ?><?= s7x_seo_ring((int) $m['scores']['accessibility'], 'm', 'Acessibilidade') ?><?php endif; ?>
        </div>
        <ul class="seo-metrics">
          <?= $metric('LCP — conteúdo principal', 'meta: até 2,5 s', $fd['lcp'] ?? null, $m['lab']['lcp'] ?? null, 'lcp') ?>
          <?= $fd && !empty($fd['inp']) ? $metric('INP — resposta ao toque', 'meta: até 200 ms', $fd['inp'], null, 'inp') : $metric('Tempo de bloqueio', 'meta: até 200 ms', null, $m['lab']['tbt'] ?? null, 'tbt') ?>
          <?= $metric('CLS — estabilidade', 'meta: até 0,1', $fd['cls'] ?? null, $m['lab']['cls'] ?? null, 'cls') ?>
          <?= $metric('Primeira imagem na tela', 'meta: até 1,8 s', $fd['fcp'] ?? null, $m['lab']['fcp'] ?? null, 'fcp') ?>
          <?= $metric('Resposta do servidor', 'meta: até 0,8 s', $fd['ttfb'] ?? null, $m['lab']['ttfb'] ?? null, 'ttfb') ?>
        </ul>
      </div>
      <?php if (!empty($m['opportunities'])): ?>
        <h4 class="seo-h4">O que mais deixa o site lento</h4>
        <ul class="seo-ops">
          <?php foreach ($m['opportunities'] as $o): ?>
            <li><strong><?= e($o['title']) ?></strong><?php if ($o['saving_ms'] >= 100 || $o['display'] !== ''): ?> <span class="seo-op__gain"><?= $o['saving_ms'] >= 100 ? 'economia de ~' . e(s7x_seo_ms($o['saving_ms'])) : e($o['display']) ?></span><?php endif; ?><?php if ($o['desc'] !== ''): ?><p><?= e($o['desc']) ?></p><?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
}

function s7x_seo_render_pages(array $r): string
{
    if (empty($r['subpages'])) {
        return '';
    }
    ob_start(); ?>
    <section class="seo-sec">
      <h3 class="seo-h">Páginas internas conferidas</h3>
      <div class="seo-table-wrap">
        <table class="seo-table">
          <thead><tr><th scope="col">Página</th><th scope="col">Situação</th><th scope="col">Título</th><th scope="col">Descrição</th><th scope="col">H1</th><th scope="col">Palavras</th></tr></thead>
          <tbody>
          <?php foreach ($r['subpages'] as $s): $okS = $s['status'] === 200; $tl = mb_strlen($s['title']); $dl = mb_strlen($s['description']); ?>
            <tr>
              <th scope="row"><a href="<?= e($s['url']) ?>" target="_blank" rel="nofollow noopener"><?= e(s7x_seo_short_url($s['url'])) ?></a></th>
              <td class="<?= $okS && !$s['noindex'] ? 'is-ok' : 'is-erro' ?>"><?= $okS ? ($s['noindex'] ? 'noindex' : 'OK') : e((string) ($s['status'] ?: 'sem resposta')) ?></td>
              <td class="<?= $tl >= 25 && $tl <= 65 ? 'is-ok' : 'is-aviso' ?>" title="<?= e($s['title']) ?>"><?= $tl ? $tl . ' car.' : '—' ?></td>
              <td class="<?= $dl >= 90 && $dl <= 165 ? 'is-ok' : 'is-aviso' ?>"><?= $dl ? $dl . ' car.' : 'falta' ?></td>
              <td class="<?= $s['h1'] === 1 ? 'is-ok' : 'is-aviso' ?>"><?= (int) $s['h1'] ?></td>
              <td class="<?= $s['words'] >= 250 ? 'is-ok' : 'is-aviso' ?>"><?= number_format((int) $s['words'], 0, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php
    return (string) ob_get_clean();
}

function s7x_seo_render_tech(array $r): string
{
    $f = $r['page'];
    $tech = array_values(array_filter($f['tech'] ?? [], 'is_string'));
    $tools = $f['tools'] ?? [];
    $names = ['ga4' => 'Google Analytics 4', 'gtm' => 'Google Tag Manager', 'ua' => 'Universal Analytics (desativado)', 'ads' => 'Google Ads', 'meta_pixel' => 'Pixel da Meta', 'adsense' => 'Google AdSense', 'clarity' => 'Microsoft Clarity', 'hotjar' => 'Hotjar', 'tiktok' => 'Pixel do TikTok', 'linkedin' => 'LinkedIn Insight', 'rdstation' => 'RD Station'];
    $tl = array_map(fn ($k) => $names[$k] ?? $k, array_keys($tools));
    $fetch = $r['fetch'];
    $rows = [
        'Plataforma' => $tech ? implode(', ', $tech) : 'Não identificada',
        'Ferramentas instaladas' => $tl ? implode(', ', $tl) : 'Nenhuma encontrada',
        'Verificações de propriedade' => !empty($f['verification']) ? implode(', ', $f['verification']) : '—',
        'Servidor' => trim(($fetch['headers']['server'] ?? '') . (isset(['1' => 1, '2' => 1, '3' => 1, '30' => 1][(string) ($fetch['http_version'] ?? '')]) ? ' · HTTP/' . ['1' => '1.0', '2' => '1.1', '3' => '2', '30' => '3'][(string) $fetch['http_version']] : ''), ' ·') ?: '—',
        'Tamanho do HTML' => s7x_seo_kb((int) ($f['html_bytes'] ?? 0)) . (!empty($fetch['encoding']) ? ' (' . s7x_seo_kb((int) $fetch['bytes']) . ' comprimido)' : ''),
        'Imagens / links na página' => count($f['images'] ?? []) . ' imagens · ' . count($f['links'] ?? []) . ' links',
        'Sitemap' => !empty($r['site']['sitemap']['found']) ? $r['site']['sitemap']['url'] : 'Não encontrado',
    ];
    ob_start(); ?>
    <section class="seo-sec">
      <h3 class="seo-h">Ficha técnica</h3>
      <dl class="seo-dl">
        <?php foreach ($rows as $k => $v): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?>
      </dl>
    </section>
    <?php
    return (string) ob_get_clean();
}
