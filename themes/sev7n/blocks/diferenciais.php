<?php if (!defined('CMS_ROOT')) { exit; }
/** Sobre e diferenciais — Autor: João Costa */
$items = array_values(array_filter((array) $b['items'], fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
$im = cms_resolve_image($b['image']);
?>
<section class="sec sec--<?= e($b['background']) ?> why"<?= $id ?>>
  <div class="wrap why__grid">
    <div class="why__intro">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <h2 class="sec__title"><?= e($b['title']) ?></h2>
      <div class="why__text"><?= cms_rich($b['text']) ?></div>
      <?php if ($im['src'] !== ''): ?><figure class="why__img"><?php sv_img($b['image']); ?></figure><?php endif; ?>
    </div>
    <ul class="why__list">
      <?php foreach ($items as $it): ?>
        <li>
          <?php if (!empty($it['icon'])): ?><span class="why__ic"><?= sv_icon($it['icon']) ?></span><?php endif; ?>
          <div><h3><?= e($it['title']) ?></h3><p><?= e($it['text'] ?? '') ?></p></div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
