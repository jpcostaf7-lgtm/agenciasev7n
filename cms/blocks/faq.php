<?php if (!defined('CMS_ROOT')) { exit; } ?>
<section class="blk blk-faq"<?= $id ?>>
  <div class="wrap blk-faq__grid">
    <h2><?= e($b['title']) ?></h2>
    <div class="blk-faq__list">
      <?php foreach ((array) $b['items'] as $q): if (trim((string) ($q['question'] ?? '')) === '') { continue; } ?>
      <details>
        <summary><?= e($q['question']) ?></summary>
        <div class="blk-faq__answer"><p><?= nl($q['answer'] ?? '') ?></p></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
