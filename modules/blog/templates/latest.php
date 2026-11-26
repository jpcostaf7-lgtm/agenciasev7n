<?php
/**
 * Bloco "Últimos posts do blog". O tema pode substituir criando blocks/blog-latest.php.
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) { exit; }
$n = max(1, min(12, (int) $b['count']));
$items = array_slice(s7x_blog_query(['cat' => trim((string) $b['category'])]), 0, $n, true);
if (!$items) { return; }
$lead = $b['layout'] === 'destaque' && count($items) >= 3 ? array_shift($items) : null;
?>
<section class="blog-latest"<?= $id ?>>
  <div class="blog-wrap">
    <div class="blog-latest__head">
      <div>
        <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="blog-latest__eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
        <?php if (trim((string) $b['title']) !== ''): ?><h2 class="blog-latest__title"><?= e($b['title']) ?></h2><?php endif; ?>
        <?php if (trim((string) $b['text']) !== ''): ?><p class="blog-latest__text"><?= nl($b['text']) ?></p><?php endif; ?>
      </div>
      <?php if (trim((string) $b['button']) !== ''): ?><a class="blog-btn blog-btn--ghost" href="<?= e(trim((string) $b['category']) !== '' ? s7x_blog_term_url('categories', trim((string) $b['category'])) : s7x_blog_url()) ?>"><?= e($b['button']) ?></a><?php endif; ?>
    </div>
    <?php if ($lead): ?>
    <div class="blog-top blog-top--home">
      <?= s7x_blog_card($lead, 'destaque') ?>
      <div class="blog-top__list"><?php foreach ($items as $p): ?><?= s7x_blog_card($p, 'linha') ?><?php endforeach; ?></div>
    </div>
    <?php else: ?>
    <div class="blog-grid blog-grid--<?= count($items) % 4 === 0 ? 4 : 3 ?>"><?php foreach ($items as $p): ?><?= s7x_blog_card($p, 'card') ?><?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</section>
