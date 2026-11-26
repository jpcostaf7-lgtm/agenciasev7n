<?php if (!defined('CMS_ROOT')) { exit; }
/** Chamada para o e-book — Autor: João Costa */
$im = cms_resolve_image($b['image']);
?>
<section class="sec sec--<?= e($b['background']) ?> book"<?= $id ?>>
  <div class="wrap book__grid">
    <?php if ($im['src'] !== ''): ?><figure class="book__cover"><?php sv_img($b['image'], '', ['alt' => $im['alt'] ?: $b['title']]); ?></figure><?php endif; ?>
    <div class="book__copy">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <h2 class="sec__title"><?= e($b['title']) ?></h2>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="sec__text"><?= e($b['text']) ?></p><?php endif; ?>
      <p><?php sv_a((string) $b['button_label'], $b['button_link'], 'btn btn--fire btn--lg'); ?></p>
    </div>
  </div>
</section>
