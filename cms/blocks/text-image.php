<?php if (!defined('CMS_ROOT')) { exit; } ?>
<section class="blk blk-textimg blk--<?= e($b['background']) ?> blk-textimg--img-<?= e($b['image_side']) ?>"<?= $id ?>>
  <div class="wrap blk-textimg__grid">
    <?php if (cms_image_value($b['image'])['src']): ?>
      <div class="blk-textimg__media"><?php img($b['image'], ['sizes' => '(max-width: 860px) 90vw, 40vw']); ?></div>
    <?php endif; ?>
    <div class="blk-textimg__text">
      <h2><?= e($b['title']) ?></h2>
      <div class="prose"><?= cms_rich($b['text']) ?></div>
      <?php if (trim((string) $b['highlight']) !== ''): ?><p class="pull"><?= nl($b['highlight']) ?></p><?php endif; ?>
    </div>
  </div>
</section>
