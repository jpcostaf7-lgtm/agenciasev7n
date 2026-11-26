<?php if (!defined('CMS_ROOT')) { exit; } ?>
<section class="blk blk-page-header"<?= $id ?>>
  <div class="wrap">
    <h1><?= e($b['title']) ?></h1>
    <?php if (trim((string) $b['text']) !== ''): ?><p class="blk-page-header__lead"><?= nl($b['text']) ?></p><?php endif; ?>
  </div>
</section>
