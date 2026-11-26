<?php if (!defined('CMS_ROOT')) { exit; }
/** Etapas do trabalho — Autor: João Costa */
$items = array_values(array_filter((array) $b['items'], fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
?>
<section class="sec sec--<?= e($b['background']) ?> steps"<?= $id ?>>
  <div class="wrap">
    <div class="sec__head">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <?php if (trim((string) $b['title']) !== ''): ?><h2 class="sec__title"><?= e($b['title']) ?></h2><?php endif; ?>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="sec__text"><?= e($b['text']) ?></p><?php endif; ?>
    </div>
    <ol class="steps__list">
      <?php foreach ($items as $n => $it): ?>
        <li class="steps__item">
          <span class="steps__n" aria-hidden="true"><?= $n + 1 ?></span>
          <h3 class="steps__title"><?= e($it['title']) ?></h3>
          <?php if (!empty($it['time'])): ?><p class="steps__time"><?= e($it['time']) ?></p><?php endif; ?>
          <p><?= e($it['text'] ?? '') ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
