<?php if (!defined('CMS_ROOT')) { exit; }
/** Acesso ao projeto do cliente — Autor: João Costa */
$domain = preg_replace('/[^a-z0-9.-]/', '', strtolower((string) $b['domain'])) ?: 'agenciasev7n.com.br';
$wa = sv_href('wa: Olá! Preciso de ajuda para acessar o meu projeto.');
?>
<section class="access"<?= $id ?>>
  <div class="wrap access__in">
    <form class="access__card" data-project data-domain="<?= e($domain) ?>">
      <?= sv_logo('mini') ?>
      <h1 class="access__title"><?= e($b['title']) ?></h1>
      <p><?= e($b['text']) ?></p>
      <label for="proj-name">Nome do projeto</label>
      <div class="access__field">
        <input id="proj-name" name="projeto" type="text" autocomplete="off" spellcheck="false" autocapitalize="off" placeholder="nome-do-projeto" required maxlength="60" data-project-input>
        <span class="access__suffix" aria-hidden="true">.<?= e($domain) ?></span>
      </div>
      <p class="access__preview" aria-live="polite" data-project-preview></p>
      <button class="btn btn--fire btn--lg" type="submit"><?= e($b['button']) ?></button>
      <p class="access__help"><?= e($b['help']) ?><?php if ($wa['href']): ?> <a href="<?= e($wa['href']) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a><?php endif; ?></p>
    </form>
  </div>
</section>
