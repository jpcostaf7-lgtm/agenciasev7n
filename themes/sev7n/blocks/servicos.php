<?php if (!defined('CMS_ROOT')) { exit; }
/** Serviços — Autor: João Costa */
$items = array_values(array_filter((array) $b['items'], fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
?>
<section class="sec sec--<?= e($b['background']) ?> svc"<?= $id ?>>
  <div class="wrap">
    <div class="sec__head">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <?php if (trim((string) $b['title']) !== ''): ?><h2 class="sec__title"><?= e($b['title']) ?></h2><?php endif; ?>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="sec__text"><?= e($b['text']) ?></p><?php endif; ?>
    </div>
    <ul class="svc__grid<?= count($items) > 6 ? ' svc__grid--many' : '' ?>">
      <?php foreach ($items as $it): $h = sv_href($it['link'] ?? ''); ?>
        <li class="svc__item">
          <?php if (!empty($it['icon'])): ?><span class="svc__ic"><?= sv_icon($it['icon']) ?></span><?php endif; ?>
          <h3 class="svc__title"><?php if ($h['href']): ?><a href="<?= e($h['href']) ?>"<?= $h['ext'] ? ' target="_blank" rel="noopener"' : '' ?>><?= e($it['title']) ?></a><?php else: ?><?= e($it['title']) ?><?php endif; ?></h3>
          <p><?= e($it['text'] ?? '') ?></p>
          <?php if ($h['href']): ?><span class="svc__more" aria-hidden="true">Saiba mais <?= sv_icon('seta') ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
