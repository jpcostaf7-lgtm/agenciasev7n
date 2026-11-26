<?php
/**
 * Página do relatório compartilhado (/analisador-seo/relatorio/código/).
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) { exit; }
$r = $page['seo_report'];
?>
<section class="seo-tool seo-tool--page" data-seo-tool data-endpoint="<?= e(cms_url('?s7x_seo=')) ?>">
  <div class="seo-wrap">
    <h1 class="sr-only">Relatório de SEO de <?= e($r['host']) ?></h1>
    <div class="seo-result" data-seo-result>
      <?= s7x_seo_render_report($r, s7x_seo_unlocked($r), 'pagina') ?>
    </div>
    <p class="seo-again-link"><a href="<?= e(cms_url(s7x_seo_base() . '/')) ?>">Analisar outro site gratuitamente</a></p>
  </div>
</section>
