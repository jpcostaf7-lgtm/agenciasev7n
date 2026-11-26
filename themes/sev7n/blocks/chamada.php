<?php if (!defined('CMS_ROOT')) { exit; }
/** Chamada final — Autor: João Costa */
?>
<section class="final"<?= $id ?>>
  <div class="wrap final__in">
    <h2 class="final__title"><?= e($b['title']) ?></h2>
    <?php if (trim((string) $b['text']) !== ''): ?><p class="final__text"><?= e($b['text']) ?></p><?php endif; ?>
    <div class="final__actions">
      <?php sv_a((string) $b['button_label'], $b['button_link'], 'btn btn--fire btn--lg'); ?>
      <?php sv_a((string) $b['button2_label'], $b['button2_link'], 'btn btn--line btn--lg'); ?>
    </div>
  </div>
</section>
