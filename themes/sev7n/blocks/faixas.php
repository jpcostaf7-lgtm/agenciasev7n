<?php if (!defined('CMS_ROOT')) { exit; }
/** Faixas de preço — Autor: João Costa */
$items = array_values(array_filter((array) $b['items'], fn ($i) => trim((string) ($i['name'] ?? '')) !== ''));
?>
<section class="sec sec--grafite prices"<?= $id ?>>
  <div class="wrap">
    <div class="sec__head">
      <h2 class="sec__title"><?= e($b['title']) ?></h2>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="sec__text"><?= e($b['text']) ?></p><?php endif; ?>
    </div>
    <ul class="prices__list">
      <?php foreach ($items as $it): ?>
        <li class="prices__item<?= !empty($it['highlight']) ? ' is-hl' : '' ?>">
          <h3><?= e($it['name']) ?><?php if (!empty($it['highlight'])): ?> <span class="prices__tag">mais procurado</span><?php endif; ?></h3>
          <p class="prices__price"><?= e($it['price'] ?? '') ?></p>
          <?php if (!empty($it['time'])): ?><p class="prices__time"><?= sv_icon('relogio') ?><?= e($it['time']) ?></p><?php endif; ?>
          <p class="prices__text"><?= e($it['text'] ?? '') ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (trim((string) $b['note']) !== ''): ?><p class="prices__note"><?= e($b['note']) ?></p><?php endif; ?>
  </div>
</section>
