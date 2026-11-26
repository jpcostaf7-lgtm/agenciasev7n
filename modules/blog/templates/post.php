<?php
/**
 * Post do blog (página do artigo). O tema pode substituir criando blocks/blog-post.php.
 * Variáveis: $page (com $page['blog']['post'])
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) { exit; }
$post = $page['blog']['post'];
$img  = cms_image_value($post['image']);
$side = (bool) s7x_blog_opt('sidebar', true);
$related = s7x_blog_related($post, (int) s7x_blog_opt('related', 3));
?>
<div class="blog-progress" aria-hidden="true"><span></span></div>
<article class="blog-post<?= $side ? ' blog-post--side' : '' ?>">
  <header class="blog-post__head">
    <div class="blog-wrap">
      <?= s7x_blog_breadcrumb($page) ?>
      <?php if ($post['status'] !== 'publish' && $post['status'] !== 'future'): ?><p class="blog-draft">Rascunho — só aparece para quem está no painel.</p><?php endif; ?>
      <div class="blog-post__meta-top"><?= s7x_blog_cat_chip($post, 'blog-chip blog-chip--solid') ?></div>
      <h1 class="blog-post__title"><?= e($post['title']) ?></h1>
      <?php if (trim((string) $post['excerpt']) !== ''): ?><p class="blog-post__lead"><?= e($post['excerpt']) ?></p><?php endif; ?>
      <div class="blog-post__meta">
        <span class="blog-post__author">Por <?= e(s7x_blog_author()) ?></span>
        <time datetime="<?= e(date('c', strtotime($post['date']))) ?>"><?= e(s7x_blog_date($post['date'])) ?></time>
        <?php if (!empty($post['modified']) && strtotime($post['modified']) - strtotime($post['date']) > 86400 * 2): ?><span>Atualizado em <?= e(s7x_blog_date($post['modified'])) ?></span><?php endif; ?>
        <?php if (s7x_blog_opt('reading_time', true) && $post['words']): ?><span><?= e(s7x_blog_reading((int) $post['words'])) ?></span><?php endif; ?>
      </div>
    </div>
  </header>
  <div class="blog-wrap blog-post__grid">
    <div class="blog-post__main">
      <?php if ($img['src'] !== ''): ?>
      <figure class="blog-post__cover">
        <?= s7x_blog_img($img, '', true, '(max-width: 760px) 100vw, 760px') ?>
        <?php if (trim((string) $post['image_credit']) !== ''): ?><figcaption><?= cms_sanitize_html((string) $post['image_credit']) ?></figcaption><?php endif; ?>
      </figure>
      <?php endif; ?>
      <?= s7x_blog_share($post) ?>
      <div class="blog-content">
        <?= s7x_blog_render_content($post) ?>
      </div>
      <?php if ($post['tags']): ?>
      <ul class="blog-tags" aria-label="Tags">
        <?php foreach (array_slice((array) $post['tags'], 0, 12) as $t): ?><li><a href="<?= e(s7x_blog_term_url('tags', $t)) ?>">#<?= e(s7x_blog_term_name('tags', $t)) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?= s7x_blog_share($post) ?>
      <?= cms_capture_action('blog_after_content', $post) ?>
    </div>
    <?php if ($side): ?><?= s7x_blog_sidebar($post) ?><?php endif; ?>
  </div>
  <?php if ($related): ?>
  <section class="blog-related" aria-labelledby="blog-rel-t">
    <div class="blog-wrap">
      <h2 id="blog-rel-t" class="blog-section-title">Leia também</h2>
      <div class="blog-grid blog-grid--3">
        <?php foreach ($related as $p): ?><?= s7x_blog_card($p, 'card') ?><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
</article>
