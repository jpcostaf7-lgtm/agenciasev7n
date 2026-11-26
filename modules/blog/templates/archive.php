<?php
/**
 * Página do blog, de categoria, de tag e de busca. O tema pode substituir criando blocks/blog-archive.php.
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) { exit; }
$b = $page['blog'];
$items = $b['items'];
$url = fn (int $i) => $b['tax'] === 'blog' ? s7x_blog_url($i) . ($b['search'] !== '' ? '?q=' . rawurlencode($b['search']) : '') : s7x_blog_term_url($b['tax'], $b['slug'], $i);
$lead = ($b['tax'] === 'blog' && $b['page'] === 1 && $b['search'] === '' && count($items) >= 4) ? array_shift($items) : null;
$side = ($b['tax'] === 'blog' && $b['page'] === 1 && $b['search'] === '') ? array_splice($items, 0, $lead ? 3 : 0) : [];
?>
<section class="blog-archive">
  <header class="blog-archive__head">
    <div class="blog-wrap">
      <?= s7x_blog_breadcrumb($page) ?>
      <div class="blog-archive__titles">
        <div>
          <?php if ($b['tax'] === 'tags'): ?><p class="blog-archive__kicker">Tag</p><?php elseif ($b['tax'] === 'categories'): ?><p class="blog-archive__kicker">Categoria</p><?php endif; ?>
          <h1 class="blog-archive__title"><?= e($b['title']) ?></h1>
          <?php if (trim((string) $b['description']) !== ''): ?><p class="blog-archive__desc"><?= e($b['description']) ?></p><?php endif; ?>
        </div>
        <?= s7x_blog_search_form($b['search']) ?>
      </div>
      <?= s7x_blog_cat_bar($b['tax'] === 'categories' ? $b['slug'] : '') ?>
    </div>
  </header>
  <div class="blog-wrap blog-archive__body">
    <?php if (!$b['items']): ?>
      <div class="blog-empty">
        <h2>Nada encontrado</h2>
        <p><?= $b['search'] !== '' ? 'Nenhum post tem “' . e($b['search']) . '”. Tente outra palavra ou veja as categorias acima.' : 'Ainda não há posts aqui.' ?></p>
        <a class="blog-btn" href="<?= e(s7x_blog_url()) ?>">Ver todos os posts</a>
      </div>
    <?php else: ?>
      <?php if ($lead): ?>
      <div class="blog-top">
        <?= s7x_blog_card($lead, 'destaque', 'h2') ?>
        <?php if ($side): ?><div class="blog-top__list"><?php foreach ($side as $p): ?><?= s7x_blog_card($p, 'linha', 'h2') ?><?php endforeach; ?></div><?php endif; ?>
      </div>
      <?php if ($items): ?><h2 class="blog-section-title">Mais recentes</h2><?php endif; ?>
      <?php endif; ?>
      <div class="blog-grid blog-grid--3">
        <?php foreach ($items as $p): ?><?= s7x_blog_card($p, 'card', $lead ? 'h3' : 'h2') ?><?php endforeach; ?>
      </div>
      <?= s7x_blog_pagination($b['page'], $b['pages'], $url) ?>
    <?php endif; ?>
  </div>
</section>
