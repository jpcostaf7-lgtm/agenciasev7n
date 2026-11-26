<?php
/** Bloco "Anúncio MGID" — Autor: João Costa */
if (!defined('CMS_ROOT')) { exit; }
if (!s7x_mgid_allowed()) { return; }
?>
<div class="s7x-ad-wrap"<?= $id ?>><?= s7x_mgid_widget((string) $b['widget'], 'bloco') ?></div>
