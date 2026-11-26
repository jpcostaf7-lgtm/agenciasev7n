<?php if (!defined('CMS_ROOT')) { exit; }
/** Depoimentos — Autor: João Costa */
$items = array_values(array_filter((array) $b['items'], fn ($i) => trim((string) ($i['text'] ?? '')) !== ''));
?>
<section class="sec sec--<?= e($b['background']) ?> quotes"<?= $id ?>>
  <div class="wrap">
    <div class="sec__head">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <h2 class="sec__title"><?= e($b['title']) ?></h2>
    </div>
    <ul class="quotes__list">
      <?php foreach ($items as $i => $it): ?>
        <li class="quotes__item<?= $i === 0 ? ' quotes__item--lead' : '' ?>">
          <figure>
            <?= sv_icon('aspas', 'quotes__mark') ?>
            <blockquote><p><?= e($it['text']) ?></p></blockquote>
            <figcaption><b><?= e($it['name'] ?? '') ?></b><?php if (!empty($it['company'])): ?><span><?= e($it['company']) ?></span><?php endif; ?></figcaption>
          </figure>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
