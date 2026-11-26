<?php
/**
 * Bloco "Analisador de SEO". O tema pode substituir criando blocks/seo-analyzer.php.
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) { exit; }
$tag = $b['heading'] === 'h2' ? 'h2' : 'h1';
$mode = (string) s7x_seo_opt('lead_mode', 'depois');
$fid = 'seo' . e($block['id']);
?>
<section class="seo-tool"<?= $id ?> data-seo-tool data-endpoint="<?= e(cms_url('?s7x_seo=')) ?>">
  <div class="seo-wrap">
    <div class="seo-tool__intro">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="seo-tool__eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <<?= $tag ?> class="seo-tool__title"><?= e($b['title']) ?></<?= $tag ?>>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="seo-tool__text"><?= e($b['text']) ?></p><?php endif; ?>
    </div>
    <form class="seo-form" data-seo-form novalidate>
      <input type="hidden" name="_t" value="<?= e(s7x_seo_token()) ?>">
      <input type="hidden" name="utm" value="">
      <div class="seo-form__row">
        <label class="seo-field seo-field--url" for="<?= $fid ?>-url">
          <span>Endereço do site</span>
          <input id="<?= $fid ?>-url" name="url" type="text" inputmode="url" autocomplete="url" spellcheck="false" autocapitalize="off" placeholder="<?= e($b['placeholder']) ?>" required maxlength="300">
        </label>
        <?php if ($b['keyword']): ?>
          <label class="seo-field" for="<?= $fid ?>-kw">
            <span>Palavra-chave <small>(opcional)</small></span>
            <input id="<?= $fid ?>-kw" name="palavra" type="text" placeholder="ex.: dentista em Botafogo" maxlength="80">
          </label>
        <?php endif; ?>
      </div>
      <?php if ($mode === 'antes'): ?><?= s7x_seo_lead_fields() ?><?php else: ?><input type="text" name="website" tabindex="-1" autocomplete="off" class="seo-hp" aria-hidden="true"><?php endif; ?>
      <div class="seo-form__go">
        <button class="seo-btn seo-btn--primary seo-btn--lg" type="submit"><?= s7x_seo_icon('lupa') ?> <?= e($b['button']) ?></button>
        <?php if (trim((string) $b['note']) !== ''): ?><p class="seo-form__note"><?= e($b['note']) ?></p><?php endif; ?>
      </div>
      <p class="seo-msg" role="alert" aria-live="polite"></p>
    </form>
    <div class="seo-progress" data-seo-progress hidden>
      <div class="seo-progress__head">
        <span class="seo-progress__spin" aria-hidden="true"></span>
        <div><strong data-seo-host>Analisando…</strong><span data-seo-pagetitle></span></div>
      </div>
      <ol class="seo-steps" data-seo-steps aria-live="polite"></ol>
      <p class="seo-progress__tip" data-seo-tip>Isso leva cerca de 1 minuto. Pode deixar esta aba aberta enquanto o Google mede a velocidade.</p>
    </div>
    <div class="seo-result" data-seo-result tabindex="-1"></div>
  </div>
</section>
