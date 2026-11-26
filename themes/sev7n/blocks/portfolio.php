<?php if (!defined('CMS_ROOT')) { exit; }
/** Portfólio — Autor: João Costa */
$items = array_values(array_filter((array) $b['items'], fn ($i) => trim((string) ($i['name'] ?? '')) !== ''));
$vis = max(1, (int) $b['visible']);
$more = count($items) > $vis;
$gid = 'pf' . e($block['id']);
?>
<section class="sec sec--<?= e($b['background']) ?> pf"<?= $id ?>>
  <div class="wrap">
    <div class="sec__head">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <?php if (trim((string) $b['title']) !== ''): ?><h2 class="sec__title"><?= e($b['title']) ?></h2><?php endif; ?>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="sec__text"><?= e($b['text']) ?></p><?php endif; ?>
    </div>
    <ul class="pf__grid" id="<?= $gid ?>">
      <?php foreach ($items as $i => $it): $im = cms_resolve_image($it['image'] ?? ''); $host = preg_replace('#^https?://(www\.)?#', '', rtrim((string) ($it['url'] ?? ''), '/')); ?>
        <li class="pf__item"<?= $more && $i >= $vis ? ' hidden data-more' : '' ?>>
          <a class="pf__frame" href="<?= e($it['url'] ?? '#') ?>" target="_blank" rel="noopener" aria-label="Abrir o site <?= e($it['name']) ?> (nova aba)">
            <span class="pf__bar" aria-hidden="true"><i></i><i></i><i></i><span><?= e($host) ?></span></span>
            <span class="pf__shot"><?php if ($im['src'] !== ''): ?><img src="<?= e(cms_url($im['src'])) ?>" alt="<?= e($im['alt'] ?: 'Site ' . $it['name']) ?>" loading="lazy" decoding="async" width="900" height="2700"><?php endif; ?></span>
          </a>
          <div class="pf__info">
            <p class="pf__cat"><?= e($it['category'] ?? '') ?></p>
            <h3 class="pf__name"><?= e($it['name']) ?></h3>
            <?php if (!empty($it['text'])): ?><p class="pf__text"><?= e($it['text']) ?></p><?php endif; ?>
            <?php $tags = array_filter(array_map('trim', explode(',', (string) ($it['tags'] ?? '')))); if ($tags): ?>
              <ul class="pf__tags"><?php foreach ($tags as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($more): ?>
      <p class="pf__morewrap"><button type="button" class="btn btn--line" data-pf-more aria-controls="<?= $gid ?>" aria-expanded="false">Ver mais <?= count($items) - $vis ?> projetos</button></p>
    <?php endif; ?>
  </div>
</section>
