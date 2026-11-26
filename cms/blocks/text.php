<?php if (!defined('CMS_ROOT')) { exit; } ?>
<section class="blk blk-text blk--<?= e($b['background']) ?>"<?= $id ?>>
  <div class="wrap">
    <div class="blk-text__inner">
      <?php if (trim((string) $b['title']) !== ''): ?><h2><?= e($b['title']) ?></h2><?php endif; ?>
      <div class="prose"><?= cms_rich($b['text']) ?></div>
    </div>
  </div>
</section>
