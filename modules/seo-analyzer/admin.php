<?php
/**
 * Analisador de SEO — tela do painel: análises feitas, contatos e chave do Google
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_seo_admin_boot(): void
{
    cms_register_admin_page('seo-analyzer', [
        'label' => 'Analisador de SEO', 'icon' => 'lupa', 'order' => 11,
        'perm_help' => 'Ver as análises de SEO feitas no site e os contatos deixados.',
        'badge' => function () {
            $since = date('c', time() - 86400);
            return count(array_filter(s7x_seo_index(), fn ($i) => !empty($i['lead']) && $i['date'] >= $since));
        },
        'handle' => 's7x_seo_admin_handle',
        'render' => 's7x_seo_admin_render',
    ]);
    cms_add_action('admin_head', function () {
        if (($_GET['p'] ?? '') === 'seo-analyzer') {
            echo '<style>
.seo-adm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;margin-bottom:1rem}
.seo-adm-stats div{padding:1rem 1.1rem;border:1px solid var(--line,#e3e6ec);border-radius:12px;background:var(--card,#fff)}
.seo-adm-stats b{display:block;font-size:1.7rem;line-height:1.1}
.seo-adm-stats span{font-size:.85rem;opacity:.7}
.seo-adm-table{width:100%;border-collapse:collapse;font-size:.92rem}
.seo-adm-table th,.seo-adm-table td{padding:.6rem .55rem;border-bottom:1px solid var(--line,#e3e6ec);text-align:left;vertical-align:top}
.seo-adm-table th{font-size:.8rem;opacity:.7}
.seo-adm-score{display:inline-grid;place-items:center;min-width:2.6rem;padding:.15rem .4rem;border-radius:999px;font-weight:800;color:#fff}
.seo-adm-score.is-otimo,.seo-adm-score.is-bom{background:#13874b}.seo-adm-score.is-medio{background:#b86e00}.seo-adm-score.is-ruim{background:#d23c33}.seo-adm-score.is-x{background:#8a909c}
.seo-adm-table small{display:block;opacity:.7;word-break:break-all}
.seo-adm-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}@media(max-width:900px){.seo-adm-grid{grid-template-columns:1fr}}
.seo-adm-wrap{overflow-x:auto}
</style>';
        }
    });
    cms_add_action('admin_dashboard', function () {
        if (!cms_can('page.seo-analyzer')) {
            return;
        }
        $idx = s7x_seo_index();
        $since = date('c', time() - 30 * 86400);
        $m = array_filter($idx, fn ($i) => $i['date'] >= $since);
        $leads = array_filter($m, fn ($i) => !empty($i['lead']));
        echo '<section class="panel"><h2>Analisador de SEO</h2><p>' . count($m) . ' análise(s) e ' . count($leads) . ' contato(s) nos últimos 30 dias.</p><p><a class="btn btn--small" href="' . e(admin_url('seo-analyzer')) . '">Ver análises</a></p></section>';
    });
}

function s7x_seo_admin_handle(string $action): void
{
    if ($action === 'seo_delete') {
        s7x_seo_delete((string) ($_POST['id'] ?? ''));
        cms_flash('ok', 'Análise excluída.');
    } elseif ($action === 'seo_keys') {
        foreach (['pagespeed_api_key' => 'psi'] as $secret => $field) {
            $v = trim((string) ($_POST[$field] ?? ''));
            if (!empty($_POST[$field . '_clear'])) {
                cms_secret_set($secret, null);
            } elseif ($v !== '' && strpos($v, '•') === false) {
                cms_secret_set($secret, $v);
            }
        }
        cms_flash('ok', 'Chave salva.');
    } elseif ($action === 'seo_test_psi') {
        $r = s7x_seo_psi(cms_site_url() . '/', 'mobile', 'teste' . substr(md5((string) microtime(true)), 0, 8));
        if (!empty($r['ok'])) {
            cms_flash('ok', 'PageSpeed funcionando: nota ' . ($r['scores']['performance'] ?? '—') . '/100 no celular para este site.');
        } else {
            cms_flash('error', 'PageSpeed: ' . ($r['error'] ?? 'sem resposta'));
        }
        foreach (glob(CMS_ROOT . '/uploads/analisador/teste*') ?: [] as $f) {
            @unlink($f);
        }
    }
}

function s7x_seo_admin_csv(array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analises-seo-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Data', 'Site', 'Palavra-chave', 'Nota', 'Nome', 'WhatsApp', 'E-mail', 'Origem', 'Visualizações', 'Relatório'], ';');
    foreach ($rows as $i) {
        fputcsv($out, [cms_format_date($i['date']), $i['url'], $i['keyword'], $i['score'] ?? '', $i['lead']['name'] ?? '', $i['lead']['phone'] ?? '', $i['lead']['email'] ?? '', $i['utm'], $i['views'], s7x_seo_report_url($i['id'], true)], ';');
    }
    fclose($out);
    exit;
}

function s7x_seo_admin_render(): void
{
    $idx = s7x_seo_index();
    uasort($idx, fn ($a, $b) => strcmp($b['date'], $a['date']));
    $f = (string) ($_GET['f'] ?? '');
    $q = trim((string) ($_GET['q'] ?? ''));
    $rows = array_filter($idx, function ($i) use ($f, $q) {
        if ($f === 'contato' && empty($i['lead'])) {
            return false;
        }
        if ($f === 'erro' && $i['status'] !== 'erro') {
            return false;
        }
        if ($q !== '' && stripos($i['url'] . ' ' . ($i['lead']['name'] ?? '') . ' ' . ($i['lead']['email'] ?? '') . ' ' . $i['keyword'], $q) === false) {
            return false;
        }
        return true;
    });
    if (($_GET['export'] ?? '') === 'csv') {
        s7x_seo_admin_csv($rows);
    }
    $since = date('c', time() - 30 * 86400);
    $month = array_filter($idx, fn ($i) => $i['date'] >= $since);
    $done = array_filter($month, fn ($i) => $i['score'] !== null);
    $avg = $done ? (int) round(array_sum(array_column($done, 'score')) / count($done)) : null;
    $leadsN = count(array_filter($month, fn ($i) => !empty($i['lead'])));
    $page = max(1, (int) ($_GET['pg'] ?? 1));
    $per = 40;
    $pages = max(1, (int) ceil(count($rows) / $per));
    $show = array_slice($rows, ($page - 1) * $per, $per, true);
    $psiKey = cms_secret_get('pagespeed_api_key', '');
    $aiOn = s7x_seo_opt('ai', true) && s7x_seo_ai_key() !== '';
    $toolUrl = cms_url(s7x_seo_base() . '/');
    $hasPage = (bool) cms_page_by_slug(s7x_seo_base());
    ?>
    <?php if (!$hasPage): ?>
      <div class="notice notice--warn">Ainda não existe a página <strong>/<?= e(s7x_seo_base()) ?>/</strong>. Crie uma página com esse endereço e adicione o bloco <strong>Analisador de SEO</strong> para os visitantes usarem a ferramenta.</div>
    <?php endif; ?>
    <div class="seo-adm-stats">
      <div><b><?= count($month) ?></b><span>análises nos últimos 30 dias</span></div>
      <div><b><?= $leadsN ?></b><span>contatos deixados (30 dias)</span></div>
      <div><b><?= $month ? round($leadsN / count($month) * 100) . '%' : '—' ?></b><span>das análises viraram contato</span></div>
      <div><b><?= $avg ?? '—' ?></b><span>nota média dos sites</span></div>
    </div>
    <section class="panel">
      <div class="row row--between">
        <div class="tabs">
          <a href="<?= e(admin_url('seo-analyzer')) ?>" class="<?= $f === '' ? 'is-active' : '' ?>">Todas (<?= count($idx) ?>)</a>
          <a href="<?= e(admin_url('seo-analyzer', ['f' => 'contato'])) ?>" class="<?= $f === 'contato' ? 'is-active' : '' ?>">Com contato (<?= count(array_filter($idx, fn ($i) => !empty($i['lead']))) ?>)</a>
          <a href="<?= e(admin_url('seo-analyzer', ['f' => 'erro'])) ?>" class="<?= $f === 'erro' ? 'is-active' : '' ?>">Com erro</a>
        </div>
        <div class="row">
          <form method="get" class="row"><input type="hidden" name="p" value="seo-analyzer"><?php if ($f): ?><input type="hidden" name="f" value="<?= e($f) ?>"><?php endif; ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar site, nome ou e-mail" aria-label="Buscar"><button class="btn btn--small btn--ghost">Buscar</button></form>
          <a class="btn btn--small" href="<?= e($toolUrl) ?>" target="_blank" rel="noopener">Fazer uma análise</a>
          <a class="btn btn--small btn--ghost" href="<?= e(admin_url('seo-analyzer', array_filter(['export' => 'csv', 'f' => $f, 'q' => $q]))) ?>">Exportar planilha (CSV)</a>
        </div>
      </div>
      <?php if (!$show): ?>
        <p class="muted empty"><?= $idx ? 'Nada encontrado com esse filtro.' : 'Nenhuma análise ainda. Quando alguém usar o analisador, ela aparece aqui.' ?></p>
      <?php else: ?>
        <div class="seo-adm-wrap">
        <table class="seo-adm-table">
          <thead><tr><th>Data</th><th>Site</th><th>Nota</th><th>Contato</th><th>Origem</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($show as $i): $s = $i['score']; $k = $s === null ? 'x' : ($s >= 90 ? 'otimo' : ($s >= 75 ? 'bom' : ($s >= 50 ? 'medio' : 'ruim'))); ?>
            <tr>
              <td><?= e(cms_format_date($i['date'])) ?></td>
              <td><a href="<?= e($i['url']) ?>" target="_blank" rel="noopener nofollow"><?= e($i['host']) ?></a><?php if ($i['keyword']): ?><small>palavra-chave: <?= e($i['keyword']) ?></small><?php endif; ?></td>
              <td><span class="seo-adm-score is-<?= $k ?>"><?= $s === null ? ($i['status'] === 'erro' ? 'erro' : '…') : (int) $s ?></span></td>
              <td><?php if (!empty($i['lead'])): ?><strong><?= e($i['lead']['name']) ?></strong>
                <?php if ($i['lead']['phone']): ?><small><a href="<?= e(cms_whatsapp_link($i['lead']['phone'], 'Olá, ' . $i['lead']['name'] . '! Vi que você analisou o site ' . $i['host'] . ' no nosso analisador de SEO.')) ?>" target="_blank" rel="noopener">WhatsApp <?= e($i['lead']['phone']) ?></a></small><?php endif; ?>
                <?php if ($i['lead']['email']): ?><small><a href="mailto:<?= e($i['lead']['email']) ?>"><?= e($i['lead']['email']) ?></a></small><?php endif; ?>
              <?php else: ?><span class="muted">—</span><?php endif; ?></td>
              <td><small><?= e($i['utm'] ?: 'direto') ?></small><?php if ($i['views']): ?><small><?= (int) $i['views'] ?> visualização(ões) do link</small><?php endif; ?></td>
              <td class="row">
                <?php if ($i['status'] === 'pronto'): ?><a class="btn btn--small" href="<?= e(s7x_seo_report_url($i['id'])) ?>" target="_blank" rel="noopener">Relatório</a><a class="btn btn--small" href="<?= e(s7x_seo_pdf_url($i['id'])) ?>" target="_blank" rel="noopener" title="Abre o relatório formatado para salvar em PDF e enviar ao cliente">PDF</a><?php endif; ?>
                <a class="btn btn--small btn--ghost" href="<?= e($toolUrl . '?site=' . rawurlencode($i['url'])) ?>" target="_blank" rel="noopener">Refazer</a>
                <form method="post" data-confirm="Excluir a análise de <?= e($i['host']) ?>?"><?= cms_csrf_field() ?><input type="hidden" name="action" value="seo_delete"><input type="hidden" name="id" value="<?= e($i['id']) ?>"><button class="btn btn--small btn--danger" aria-label="Excluir análise de <?= e($i['host']) ?>">Excluir</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <?php if ($pages > 1): ?>
          <p class="row"><?php for ($n = 1; $n <= $pages; $n++): ?><a class="btn btn--small <?= $n === $page ? '' : 'btn--ghost' ?>" href="<?= e(admin_url('seo-analyzer', array_filter(['f' => $f, 'q' => $q, 'pg' => $n]))) ?>"><?= $n ?></a><?php endfor; ?></p>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <div class="seo-adm-grid">
      <section class="panel">
        <h2>Google PageSpeed</h2>
        <p class="small muted">Mede a velocidade real (Core Web Vitals) no celular e no computador. Funciona sem chave, mas o Google limita as consultas; com a chave gratuita, são até 25.000 por dia.</p>
        <ol class="small muted">
          <li>Entre em <a href="https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com" target="_blank" rel="noopener">console.cloud.google.com</a> e ative a <strong>PageSpeed Insights API</strong>.</li>
          <li>Em <strong>Credenciais → Criar credenciais → Chave de API</strong>, copie a chave e cole abaixo.</li>
        </ol>
        <form method="post">
          <?= cms_csrf_field() ?><input type="hidden" name="action" value="seo_keys">
          <div class="field"><label for="psi">Chave da API PageSpeed</label><input id="psi" name="psi" type="password" autocomplete="off" placeholder="<?= $psiKey !== '' ? e(cms_mask($psiKey)) : 'AIza...' ?>"></div>
          <?php if ($psiKey !== ''): ?><label class="small"><input type="checkbox" name="psi_clear" value="1"> Remover a chave salva</label><?php endif; ?>
          <p class="row"><button class="btn btn--primary btn--small">Salvar</button></p>
        </form>
        <form method="post"><?= cms_csrf_field() ?><input type="hidden" name="action" value="seo_test_psi"><button class="btn btn--small btn--ghost">Testar com este site</button></form>
      </section>
      <section class="panel">
        <h2>Resumo com IA e contato</h2>
        <p><?= $aiOn ? '✅ O resumo em linguagem simples está ativo (Claude).' : (s7x_seo_ai_key() === '' ? 'O resumo com IA está desligado porque o Claude não está conectado ao site.' : 'O resumo com IA está desligado nas configurações do módulo.') ?></p>
        <p class="small muted">Pedido de contato: <strong><?= e(['depois' => 'depois da análise', 'antes' => 'antes da análise', 'nunca' => 'não pedir'][(string) s7x_seo_opt('lead_mode', 'depois')] ?? '') ?></strong>. Cada contato também entra em <a href="<?= e(admin_url('leads')) ?>">Leads</a> com o link do relatório.</p>
        <p><a class="btn btn--small" href="<?= e(admin_url('module', ['m' => 'seo-analyzer'])) ?>">Configurações do analisador</a><?php if (s7x_seo_ai_key() === '' && cms_module_active('claude')): ?> <a class="btn btn--small btn--ghost" href="<?= e(admin_url('claude')) ?>">Conectar o Claude</a><?php endif; ?></p>
      </section>
    </div>
    <?php
}
