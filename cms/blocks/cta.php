<?php if (!defined('CMS_ROOT')) { exit; }
$link = trim((string) $b['button_link']);
$ext  = false;
if (strtolower($link) === 'whatsapp') {
    $link = cms_whatsapp_link((string) c('contato.whatsapp'), (string) c('contato.whatsapp_message')) ?: cms_tel_link((string) c('contato.phone'));
    $ext  = strpos($link, 'https://') === 0;
} else {
    $ext  = (bool) preg_match('#^https?://#i', $link);
    $link = cms_link($link);
}
?>
<section class="blk blk-cta blk--<?= e($b['background']) ?>"<?= $id ?>>
  <div class="wrap blk-cta__inner">
    <div>
      <h2><?= e($b['title']) ?></h2>
      <?php if (trim((string) $b['text']) !== ''): ?><p><?= nl($b['text']) ?></p><?php endif; ?>
    </div>
    <?php if (trim((string) $b['button_label']) !== '' && $link !== '' && $link !== '#'): ?>
      <a class="btn btn--cta btn--lg" href="<?= e($link) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>><?= e($b['button_label']) ?></a>
    <?php endif; ?>
  </div>
</section>
