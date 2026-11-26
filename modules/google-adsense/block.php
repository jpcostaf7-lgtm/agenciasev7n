<?php if (!defined('CMS_ROOT')) { exit; }
$pub = s7x_adsense_pub();
if ($pub === '' || trim((string) $b['slot']) === '') {
    return;
}
$fmt = (string) $b['format'];
?>
<aside class="blk-ad"<?= $id ?> aria-label="<?= e($b['label'] ?: 'Publicidade') ?>">
  <div class="wrap">
    <?php if (trim((string) $b['label']) !== ''): ?><p class="blk-ad__label"><?= e($b['label']) ?></p><?php endif; ?>
    <ins class="adsbygoogle" style="display:block" data-ad-client="<?= e($pub) ?>" data-ad-slot="<?= e(preg_replace('/\D/', '', (string) $b['slot'])) ?>"<?= $fmt === 'fluid' ? ' data-ad-format="fluid" data-ad-layout="in-article"' : ' data-ad-format="' . e($fmt) . '" data-full-width-responsive="true"' ?>></ins>
    <script>(window.adsbygoogle = window.adsbygoogle || []).push({});</script>
  </div>
</aside>
