<?php if (!defined('CMS_ROOT')) { exit; }
$sent  = isset($_GET['enviado']);
$error = isset($_GET['erro']) ? (string) $_GET['erro'] : '';
$fid   = 'f' . e($block['id']);
$priv  = trim((string) $b['privacy_link']);
$privOk = $priv !== '' && ($priv[0] !== '/' || cms_page_by_slug(trim(strtok($priv, '#?'), '/')));
?>
<section class="blk blk-form blk--<?= e($b['background']) ?>"<?= $id ?>>
  <div class="wrap blk-form__grid">
    <div class="blk-form__intro">
      <h2><?= e($b['title']) ?></h2>
      <?php if (trim((string) $b['text']) !== ''): ?><p><?= nl($b['text']) ?></p><?php endif; ?>
    </div>
    <form class="s7x-form" method="post" action="<?= e(cms_page_url($page)) ?>" data-s7x-form novalidate>
      <input type="hidden" name="_s7x_action" value="form">
      <input type="hidden" name="_page" value="<?= e($page['id']) ?>">
      <input type="hidden" name="_block" value="<?= e($block['id']) ?>">
      <input type="hidden" name="_t" value="<?= e(s7x_form_token()) ?>">
      <div class="s7x-form__hp" aria-hidden="true"><label>Não preencha<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

      <div class="s7x-form__row">
        <div class="s7x-field">
          <label for="<?= $fid ?>-nome">Nome</label>
          <input id="<?= $fid ?>-nome" name="nome" type="text" required autocomplete="name" maxlength="120">
        </div>
        <div class="s7x-field">
          <label for="<?= $fid ?>-tel">Telefone com DDD<?= $b['phone_required'] ? '' : ' <span>(opcional)</span>' ?></label>
          <input id="<?= $fid ?>-tel" name="telefone" type="tel" inputmode="tel" autocomplete="tel" <?= $b['phone_required'] ? 'required' : '' ?> maxlength="20" placeholder="(11) 90000-0000" data-mask-phone>
        </div>
      </div>
      <?php if ($b['show_email']): ?>
      <div class="s7x-field">
        <label for="<?= $fid ?>-email">E-mail<?= $b['email_required'] ? '' : ' <span>(opcional)</span>' ?></label>
        <input id="<?= $fid ?>-email" name="email" type="email" autocomplete="email" <?= $b['email_required'] ? 'required' : '' ?> maxlength="160">
      </div>
      <?php endif; ?>
      <?php if ($b['show_message']): ?>
      <div class="s7x-field">
        <label for="<?= $fid ?>-msg">Mensagem <span>(opcional)</span></label>
        <textarea id="<?= $fid ?>-msg" name="mensagem" rows="4" maxlength="3000"></textarea>
      </div>
      <?php endif; ?>
      <label class="s7x-consent">
        <input type="checkbox" name="aceite" value="1" required>
        <span><?= e($b['consent_text']) ?><?php if ($privOk): ?> <a href="<?= e(cms_link($priv)) ?>" target="_blank">Ler a política</a><?php endif; ?></span>
      </label>
      <div class="s7x-form__foot">
        <button type="submit" class="btn btn--solid btn--lg" data-label="<?= e($b['button_label']) ?>"><?= e($b['button_label']) ?></button>
        <p class="s7x-form__msg<?= $sent ? ' is-ok' : ($error ? ' is-error' : '') ?>" role="status" aria-live="polite" data-msg><?= $sent ? e($b['success']) : e($error) ?></p>
      </div>
    </form>
  </div>
</section>
