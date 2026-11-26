<?php if (!defined('CMS_ROOT')) { exit; }
/**
 * Bloco "Material para download". O tema pode substituir criando blocks/lead-download.php.
 * Autor: João Costa
 */
$fid   = 'd' . e($block['id']);
$tag   = $b['heading'] === 'h1' ? 'h1' : 'h2';
$cover = cms_resolve_image($b['cover']);
$file  = trim((string) $b['file']);
$fileUrl = $file === '' ? '' : (preg_match('#^https?://#', $file) ? $file : cms_url(ltrim($file, '/')));
$done  = isset($_GET['baixar']);
$error = isset($_GET['erro']) ? (string) $_GET['erro'] : '';
$priv  = trim((string) $b['privacy_link']);
$privOk = $priv !== '' && ($priv[0] !== '/' || cms_page_by_slug(trim(strtok($priv, '#?'), '/')));
$items = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $b['bullets']))));
?>
<section class="blk blk-download"<?= $id ?>>
  <div class="wrap blk-download__grid">
    <div class="blk-download__intro">
      <?php if (trim((string) $b['eyebrow']) !== ''): ?><p class="blk-download__eyebrow"><?= e($b['eyebrow']) ?></p><?php endif; ?>
      <<?= $tag ?> class="blk-download__title"><?= e($b['title']) ?></<?= $tag ?>>
      <?php if (trim((string) $b['text']) !== ''): ?><p class="blk-download__text"><?= nl($b['text']) ?></p><?php endif; ?>
      <?php if ($items): ?>
        <ul class="blk-download__list">
          <?php foreach ($items as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="blk-download__side">
      <?php if ($cover['src'] !== ''): ?>
        <figure class="blk-download__cover"><img src="<?= e(cms_url($cover['src'])) ?>" alt="<?= e($cover['alt'] ?: 'Capa: ' . $b['title']) ?>" width="600" height="848" loading="eager"></figure>
      <?php endif; ?>
      <form class="s7x-form blk-download__form" method="post" action="<?= e(cms_page_url($page)) ?>" data-s7x-form novalidate>
        <input type="hidden" name="_s7x_action" value="download">
        <input type="hidden" name="_page" value="<?= e($page['id']) ?>">
        <input type="hidden" name="_block" value="<?= e($block['id']) ?>">
        <input type="hidden" name="_t" value="<?= e(s7x_form_token()) ?>">
        <input type="hidden" name="utm" value="" data-utm>
        <div class="s7x-form__hp" aria-hidden="true"><label>Não preencha<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="s7x-field">
          <label for="<?= $fid ?>-nome">Nome</label>
          <input id="<?= $fid ?>-nome" name="nome" type="text" required autocomplete="name" maxlength="120">
        </div>
        <div class="s7x-field">
          <label for="<?= $fid ?>-email">Seu melhor e-mail</label>
          <input id="<?= $fid ?>-email" name="email" type="email" required autocomplete="email" maxlength="160">
        </div>
        <?php if ($b['ask_phone'] !== 'nao'): ?>
        <div class="s7x-field">
          <label for="<?= $fid ?>-tel">WhatsApp<?= $b['ask_phone'] === 'sim' ? '' : ' <span>(opcional)</span>' ?></label>
          <input id="<?= $fid ?>-tel" name="telefone" type="tel" inputmode="tel" autocomplete="tel" <?= $b['ask_phone'] === 'sim' ? 'required' : '' ?> maxlength="20" placeholder="(21) 90000-0000" data-mask-phone>
        </div>
        <?php endif; ?>
        <label class="s7x-consent">
          <input type="checkbox" name="aceite" value="1" required>
          <span><?= e($b['consent_text']) ?><?php if ($privOk): ?> <a href="<?= e(cms_link($priv)) ?>" target="_blank">Ler a política</a><?php endif; ?></span>
        </label>
        <div class="s7x-form__foot">
          <button type="submit" class="btn btn--solid btn--lg" data-label="<?= e($b['button_label']) ?>"><?= e($b['button_label']) ?></button>
          <?php if (trim((string) $b['details']) !== ''): ?><p class="blk-download__details"><?= e($b['details']) ?></p><?php endif; ?>
          <p class="s7x-form__msg<?= $done ? ' is-ok' : ($error ? ' is-error' : '') ?>" role="status" aria-live="polite" data-msg><?= $done ? e($b['success']) : e($error) ?></p>
          <p class="blk-download__again" data-download <?= $done && $fileUrl !== '' ? '' : 'hidden' ?>><a class="btn btn--outline" href="<?= e($fileUrl) ?>" <?= trim((string) $b['file_name']) !== '' ? 'download="' . e($b['file_name']) . '"' : '' ?>>Baixar o arquivo de novo</a></p>
        </div>
      </form>
    </div>
  </div>
</section>
<script>(function(){try{var q=new URLSearchParams(location.search),o=[];['utm_source','utm_medium','utm_campaign'].forEach(function(k){if(q.get(k))o.push(k.replace('utm_','')+'='+q.get(k));});document.querySelectorAll('[data-utm]').forEach(function(i){i.value=o.join('&')||(document.referrer?'ref='+new URL(document.referrer).hostname:'');});}catch(e){}})();</script>
