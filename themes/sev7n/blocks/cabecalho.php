<?php if (!defined('CMS_ROOT')) { exit; }
/** Topo das páginas internas — Autor: João Costa */
$pts = sv_lines($b['points']);
?>
<section class="phead<?= $pts ? ' phead--points' : '' ?>"<?= $id ?>>
  <div class="wrap phead__grid">
    <div class="phead__copy">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <h1 class="phead__title"><?= e($b['title']) ?></h1>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="phead__text"><?= e($b['text']) ?></p><?php endif; ?>
      <?php if (trim((string) $b['button_label']) !== ''): ?><p class="phead__actions"><?php sv_a((string) $b['button_label'], $b['button_link'], 'btn btn--fire btn--lg'); ?></p><?php endif; ?>
    </div>
    <?php if ($pts): ?>
      <ul class="phead__points"><?php foreach ($pts as $p): ?><li><?= sv_icon('check') ?><span><?= e($p) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</section>
