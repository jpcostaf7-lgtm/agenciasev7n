<?php if (!defined('CMS_ROOT')) { exit; }
/** Texto longo — Autor: João Costa */
$wa = sv_href('wa');
?>
<section class="article"<?= $id ?>>
  <div class="wrap article__grid<?= $b['aside'] ? '' : ' article__grid--solo' ?>">
    <div class="article__body prose"><?= cms_rich($b['text']) ?></div>
    <?php if ($b['aside']): ?>
      <aside class="article__aside">
        <div class="article__card">
          <?= sv_logo('mini') ?>
          <h2><?= e($b['aside_title']) ?></h2>
          <p><?= e($b['aside_text']) ?></p>
          <?php if ($wa['href']): ?><a class="btn btn--fire" href="<?= e($wa['href']) ?>" target="_blank" rel="noopener" data-wa><?= sv_icon('whatsapp') ?><span>Chamar no WhatsApp</span></a><?php endif; ?>
          <a class="article__tool" href="<?= e(cms_url((function_exists('s7x_seo_base') ? s7x_seo_base() : 'analisador-seo') . '/')) ?>"><?= sv_icon('lupa') ?><span>Ou veja a nota do seu site atual</span></a>
        </div>
      </aside>
    <?php endif; ?>
  </div>
</section>
