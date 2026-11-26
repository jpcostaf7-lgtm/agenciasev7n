<?php
/**
 * Tema Agência Sev7n — estrutura das páginas
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}
require_once __DIR__ . '/functions.php';

$page = $GLOBALS['cms_current_page'];
$path = cms_request_path();
$menu = sv_pairs(c('geral.menu', ''));
$isCurrent = function (string $link) use ($path): bool {
    $l = rtrim((string) parse_url($link, PHP_URL_PATH), '/') ?: '/';
    return $l !== '/' && strpos($link, '#') === false && $l === (rtrim($path, '/') ?: '/');
};
$cta = sv_href(c('geral.header_link', 'wa'));
$tool = trim((string) c('geral.header_tool', ''));
$toolUrl = function_exists('s7x_seo_base') ? cms_url(s7x_seo_base() . '/') : cms_url('analisador-seo/');
$wa = sv_href('wa');
$v = function (string $f) {
    $file = __DIR__ . '/assets/' . $f;
    return cms_theme_url('assets/' . $f) . (is_file($file) ? '?v=' . filemtime($file) : '');
};
$isTool = cms_page_has_block($page, 'seo-analyzer') || !empty($page['seo_report']);
?><!doctype html>
<html lang="<?= e(cms_seo('lang', 'pt-BR')) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preload" href="<?= e(cms_theme_url('assets/fonts/bricolage-grotesque-latin-standard-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(cms_theme_url('assets/fonts/figtree-latin-wght-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php cms_head($page); ?>
<link rel="stylesheet" href="<?= e($v('css/site.css')) ?>">
</head>
<body class="<?= !empty($page['home']) ? 'is-home' : 'is-inner' ?><?= $isTool ? ' is-tool' : '' ?>">
<?php cms_body_open(); ?>
<a class="skip" href="#conteudo">Pular para o conteúdo</a>

<header class="head" data-head>
  <div class="wrap head__in">
    <a class="head__logo" href="<?= e(cms_url('/')) ?>" aria-label="Agência Sev7n — página inicial"><?= sv_logo() ?></a>
    <nav class="nav" id="menu" aria-label="Menu principal" data-nav>
      <ul class="nav__list">
        <?php foreach ($menu as $m): $h = sv_href($m['link']); ?>
          <li><a href="<?= e($h['href']) ?>"<?= $h['ext'] ? ' target="_blank" rel="noopener"' : '' ?><?= $isCurrent($m['link']) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="nav__mobile">
        <?php if ($tool !== ''): ?><a class="btn btn--line" href="<?= e($toolUrl) ?>"><?= sv_icon('lupa') ?><span><?= e($tool) ?></span></a><?php endif; ?>
        <?php if ($wa['href']): ?><a class="btn btn--fire" href="<?= e($wa['href']) ?>" target="_blank" rel="noopener" data-wa><?= sv_icon('whatsapp') ?><span>Chamar no WhatsApp</span></a><?php endif; ?>
      </div>
    </nav>
    <div class="head__actions">
      <?php if ($tool !== '' && !$isTool): ?><a class="head__tool" href="<?= e($toolUrl) ?>"><?= sv_icon('lupa') ?><span><?= e($tool) ?></span></a><?php endif; ?>
      <?php if ($cta['href'] && c('geral.header_cta')): ?><a class="btn btn--fire btn--sm head__cta" href="<?= e($cta['href']) ?>"<?= $cta['ext'] ? ' target="_blank" rel="noopener"' : '' ?><?= $cta['wa'] ? ' data-wa' : '' ?>><?= e(c('geral.header_cta')) ?></a><?php endif; ?>
      <button type="button" class="head__burger" aria-controls="menu" aria-expanded="false" data-burger><span></span><span></span><span class="sr">Abrir menu</span></button>
    </div>
  </div>
</header>

<main id="conteudo">
<?php cms_render_blocks($page); ?>
</main>

<footer class="foot">
  <div class="wrap foot__grid">
    <div class="foot__brand">
      <a href="<?= e(cms_url('/')) ?>" class="foot__logo" aria-label="Agência Sev7n — página inicial"><?= sv_logo() ?></a>
      <?php if (c('geral.footer_text')): ?><p><?= nl(c('geral.footer_text')) ?></p><?php endif; ?>
      <ul class="foot__social">
        <?php foreach (['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn'] as $k => $label): if (!c('contato.' . $k)) { continue; } ?>
          <li><a href="<?= e(c('contato.' . $k)) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>"><?= sv_icon($k) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h2 class="foot__title">Páginas</h2>
      <ul class="foot__links">
        <?php foreach (sv_pairs(c('geral.footer_links', '')) as $l): ?><li><?php sv_a($l['label'], $l['link']); ?></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h2 class="foot__title">Fale com a gente</h2>
      <ul class="foot__contacts">
        <?php if ($wa['href']): ?><li><a href="<?= e($wa['href']) ?>" target="_blank" rel="noopener" data-wa><?= sv_icon('whatsapp') ?><span><?= e(c('contato.whatsapp')) ?> (WhatsApp)</span></a></li><?php endif; ?>
        <?php if (c('contato.email')): ?><li><a href="mailto:<?= e(c('contato.email')) ?>"><?= sv_icon('email') ?><span><?= e(c('contato.email')) ?></span></a></li><?php endif; ?>
        <?php if (c('contato.city')): ?><li><?= sv_icon('mapa') ?><span><?= e(c('contato.city')) ?></span></li><?php endif; ?>
        <?php if (c('contato.hours')): ?><li><?= sv_icon('relogio') ?><span><?= e(c('contato.hours')) ?></span></li><?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="wrap foot__bottom">
    <p>© <?= date('Y') ?> Agência Sev7n · Criação de sites profissionais no Rio de Janeiro</p>
    <p><a href="<?= e(cms_url('meu-projeto/')) ?>">Área do cliente</a></p>
  </div>
</footer>

<?php if (c('contato.whatsapp_float', true) && $wa['href']): ?>
<a class="wa-float" href="<?= e($wa['href']) ?>" target="_blank" rel="noopener" aria-label="Falar no WhatsApp" data-wa><?= sv_icon('whatsapp') ?></a>
<?php endif; ?>
<script src="<?= e($v('js/site.js')) ?>" defer></script>
<?php cms_body_close(); ?>
</body>
</html>
