<?php if (!defined('CMS_ROOT')) { exit; }
/** Topo da home com o campo do analisador — Autor: João Costa */
$bgImg = cms_resolve_image($b['image']);
$facts = [];
foreach (sv_lines($b['facts']) as $l) {
    $p = array_map('trim', explode('|', $l, 2));
    $facts[] = ['n' => $p[0], 't' => $p[1] ?? ''];
}
$toolUrl = function_exists('s7x_seo_base') ? cms_url(s7x_seo_base() . '/') : cms_url('analisador-seo/');
$video = $b['video'] && is_file(__DIR__ . '/../assets/video/hero.mp4');
?>
<section class="hero"<?= $id ?>>
  <div class="hero__bg" aria-hidden="true">
    <?php if ($bgImg['src'] !== ''): ?><img src="<?= e(cms_url($bgImg['src'])) ?>" alt="" fetchpriority="high" decoding="async" width="1600" height="907"><?php endif; ?>
    <?php if ($video): ?><video muted loop playsinline preload="none" data-src="<?= e(cms_theme_url('assets/video/hero.mp4')) ?>" data-hero-video></video><?php endif; ?>
  </div>
  <div class="wrap hero__grid">
    <div class="hero__copy">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="hero__eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <h1 class="hero__title"><?= e($b['title']) ?></h1>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="hero__text"><?= e($b['text']) ?></p><?php endif; ?>
      <div class="hero__actions">
        <?php sv_a((string) $b['button_label'], $b['button_link'], 'btn btn--fire btn--lg'); ?>
        <?php sv_a((string) $b['button2_label'], $b['button2_link'], 'btn btn--line btn--lg'); ?>
      </div>
    </div>
    <form class="hero__tool" action="<?= e($toolUrl) ?>" method="get" role="search" aria-labelledby="hero-tool-title">
      <p class="hero__tool-tag"><?= sv_icon('lupa') ?> Analisador de SEO grátis</p>
      <h2 class="hero__tool-title" id="hero-tool-title"><?= e($b['tool_title']) ?></h2>
      <p class="hero__tool-text"><?= e($b['tool_text']) ?></p>
      <label class="sr" for="hero-site">Endereço do seu site</label>
      <div class="hero__tool-field">
        <input id="hero-site" name="site" type="text" inputmode="url" autocomplete="url" spellcheck="false" autocapitalize="off" placeholder="www.seusite.com.br" required>
        <input type="hidden" name="auto" value="1">
        <button class="btn btn--fire" type="submit">Analisar</button>
      </div>
      <?php $pts = sv_lines($b['tool_points']); if ($pts): ?>
        <ul class="hero__tool-points"><?php foreach ($pts as $p): ?><li><?= sv_icon('check') ?><?= e($p) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </form>
  </div>
  <?php if ($facts): ?>
    <div class="wrap">
      <ul class="hero__facts">
        <?php foreach ($facts as $f): ?><li><b><?= e($f['n']) ?></b><span><?= e($f['t']) ?></span></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</section>
