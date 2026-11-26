<?php
/**
 * Módulo Formulário e leads — lógica
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_forms_boot(array $mod): void
{
    cms_register_block('contact-form', [
        'label'       => 'Formulário de contato',
        'icon'        => 'telefone',
        'group'       => 'Formulário e leads',
        'description' => 'Nome, telefone, e-mail e mensagem, com aceite da LGPD. Cada envio vira um lead no painel.',
        'template'    => __DIR__ . '/block.php',
        'fields'      => [
            'title'          => ['type' => 'text', 'label' => 'Título (H2)', 'default' => 'Fale com a gente'],
            'text'           => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2, 'default' => 'Preencha os dados e retornaremos em breve.'],
            'phone_required' => ['type' => 'toggle', 'label' => 'Telefone obrigatório', 'default' => true],
            'show_email'     => ['type' => 'toggle', 'label' => 'Mostrar campo de e-mail', 'default' => true],
            'email_required' => ['type' => 'toggle', 'label' => 'E-mail obrigatório', 'default' => false],
            'show_message'   => ['type' => 'toggle', 'label' => 'Mostrar campo de mensagem', 'default' => true],
            'consent_text'   => ['type' => 'text', 'label' => 'Texto do aceite (LGPD)', 'default' => 'Concordo com o uso dos meus dados para retorno deste contato, conforme a Política de Privacidade.'],
            'privacy_link'   => ['type' => 'url', 'label' => 'Link da Política de Privacidade', 'default' => '/politica-de-privacidade'],
            'button_label'   => ['type' => 'text', 'label' => 'Texto do botão', 'default' => 'Enviar'],
            'success'        => ['type' => 'textarea', 'label' => 'Mensagem de sucesso', 'rows' => 2, 'default' => 'Recebemos seu contato. Em breve retornaremos.'],
            'background'     => ['type' => 'select', 'label' => 'Fundo', 'options' => ['branco' => 'Branco', 'claro' => 'Claro', 'escuro' => 'Escuro (cor principal)', 'destaque' => 'Cor de destaque'], 'default' => 'claro'],
        ],
    ]);

    cms_register_block('lead-download', [
        'label'       => 'Material para download (e-book)',
        'icon'        => 'folha',
        'group'       => 'Formulário e leads',
        'description' => 'Capa, benefícios e formulário curto: a pessoa deixa nome e e-mail e baixa o arquivo (e-book, guia, checklist). Cada download vira um lead.',
        'template'    => __DIR__ . '/download.php',
        'fields'      => [
            'eyebrow'      => ['type' => 'text', 'label' => 'Rótulo acima do título', 'default' => 'E-book gratuito'],
            'title'        => ['type' => 'text', 'label' => 'Título', 'default' => 'Baixe o material gratuito'],
            'heading'      => ['type' => 'select', 'label' => 'Nível do título', 'default' => 'h2', 'options' => ['h1' => 'H1 (título principal da página)', 'h2' => 'H2']],
            'text'         => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 3, 'default' => ''],
            'bullets'      => ['type' => 'textarea', 'label' => 'O que a pessoa vai aprender (um por linha)', 'rows' => 5, 'default' => ''],
            'cover'        => ['type' => 'image', 'label' => 'Capa do material', 'default' => ''],
            'file'         => ['type' => 'text', 'label' => 'Arquivo para download', 'default' => '', 'placeholder' => 'uploads/materiais/meu-ebook.pdf', 'help' => 'Caminho do arquivo no site (envie o PDF pela hospedagem ou use um endereço completo https://).'],
            'file_name'    => ['type' => 'text', 'label' => 'Nome do arquivo baixado', 'default' => '', 'placeholder' => 'Meu-E-book.pdf'],
            'details'      => ['type' => 'text', 'label' => 'Detalhes do arquivo', 'default' => '', 'placeholder' => 'PDF · 20 páginas · leitura de 15 min'],
            'ask_phone'    => ['type' => 'select', 'label' => 'Pedir WhatsApp', 'default' => 'opcional', 'options' => ['nao' => 'Não', 'opcional' => 'Opcional', 'sim' => 'Obrigatório']],
            'button_label' => ['type' => 'text', 'label' => 'Texto do botão', 'default' => 'Baixar agora'],
            'success'      => ['type' => 'textarea', 'label' => 'Mensagem depois do cadastro', 'rows' => 2, 'default' => 'Pronto! O download começou. Se não começar, use o botão abaixo.'],
            'consent_text' => ['type' => 'text', 'label' => 'Texto do aceite (LGPD)', 'default' => 'Aceito receber conteúdos e contato por e-mail e WhatsApp. Posso cancelar quando quiser.'],
            'privacy_link' => ['type' => 'url', 'label' => 'Link da Política de Privacidade', 'default' => '/politica-de-privacidade'],
        ],
    ]);

    cms_add_action('request:form', 's7x_forms_handle');
    cms_add_action('request:download', 's7x_forms_download_handle');

    cms_add_action('body_close', function ($page) {
        if (cms_page_has_block($page, 'contact-form') || cms_page_has_block($page, 'lead-download')) {
            echo '<script src="' . e(cms_module_url('forms', 'assets/form.js')) . '" defer></script>' . "\n";
        }
    });

    cms_add_action('ai_tools', function () {
        cms_register_ai_tool('list_leads', [
            'description' => 'Lista os leads do formulário de contato (mais recentes primeiro).',
            'input_schema' => ['type' => 'object', 'properties' => [
                'unread_only' => ['type' => 'boolean', 'description' => 'Só os não lidos.'],
                'limit' => ['type' => 'integer', 'description' => 'Máximo (padrão 20).'],
            ]],
            'perm' => 'page.leads',
            'run' => function (array $a) {
                $leads = s7x_leads();
                if (!empty($a['unread_only'])) {
                    $leads = array_filter($leads, fn ($l) => empty($l['read']));
                }
                $leads = array_slice(array_values($leads), 0, max(1, min(200, (int) ($a['limit'] ?? 20))));
                return array_map(fn ($l) => array_intersect_key($l, array_flip(['id', 'date', 'name', 'phone', 'email', 'message', 'page', 'read'])), $leads);
            },
        ]);
        cms_register_ai_tool('mark_lead_read', [
            'description' => 'Marca um lead como lido (ou não lido com read=false).',
            'input_schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'read' => ['type' => 'boolean']], 'required' => ['id']],
            'perm' => 'page.leads', 'write' => true,
            'run' => function (array $a) {
                $leads = s7x_leads();
                foreach ($leads as $i => $l) {
                    if ($l['id'] === ($a['id'] ?? '')) {
                        $leads[$i]['read'] = !array_key_exists('read', $a) || (bool) $a['read'];
                        s7x_save_leads($leads);
                        return ['id' => $l['id'], 'read' => $leads[$i]['read']];
                    }
                }
                throw new CmsAiError('Lead não encontrado.');
            },
        ]);
    });

    cms_register_admin_page('leads', [
        'label'  => 'Leads',
        'order'     => 10,
        'icon'   => 'telefone',
        'perm_help' => 'Ver, marcar como lido, excluir e exportar os leads do formulário.',
        'badge'  => fn () => count(array_filter(s7x_leads(), fn ($l) => empty($l['read']))),
        'handle' => 's7x_leads_admin_handle',
        'render' => 's7x_leads_admin_render',
    ]);
}

/* ---------- Dados ---------- */

function s7x_leads(): array
{
    return cms_read_json(cms_module_data_file('forms', 'leads'), []);
}

function s7x_save_leads(array $leads): bool
{
    return cms_write_json(cms_module_data_file('forms', 'leads'), array_values($leads));
}

function s7x_form_token(): string
{
    $t = (string) time();
    return $t . '.' . substr(hash_hmac('sha256', $t, cms_secret()), 0, 24);
}

function s7x_form_token_ok(string $tok): bool
{
    $parts = explode('.', $tok);
    if (count($parts) !== 2 || !ctype_digit($parts[0])) {
        return false;
    }
    if (!hash_equals(substr(hash_hmac('sha256', $parts[0], cms_secret()), 0, 24), $parts[1])) {
        return false;
    }
    $age = time() - (int) $parts[0];
    return $age >= 3 && $age <= 6 * 3600; // envio em menos de 3s = robô
}

/** Limite: 5 envios a cada 10 minutos por IP. */
function s7x_form_rate_ok(): bool
{
    $file = cms_module_data_file('forms', 'rate');
    $all  = cms_read_json($file, []);
    $key  = hash('sha256', cms_client_ip() . cms_secret());
    $now  = time();
    foreach ($all as $k => $times) {
        $all[$k] = array_values(array_filter((array) $times, fn ($t) => $t > $now - 600));
        if (!$all[$k]) {
            unset($all[$k]);
        }
    }
    $count = count($all[$key] ?? []);
    if ($count >= 5) {
        cms_write_json($file, $all);
        return false;
    }
    $all[$key][] = $now;
    cms_write_json($file, $all);
    return true;
}

/* ---------- Envio do formulário ---------- */

function s7x_forms_reply(bool $ok, string $msg, string $back, string $anchor = ''): void
{
    $ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
    if ($ajax) {
        http_response_code($ok ? 200 : 422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $sep = strpos($back, '?') === false ? '?' : '&';
    header('Location: ' . $back . $sep . ($ok ? 'enviado=1' : 'erro=' . rawurlencode($msg)) . $anchor, true, 303);
    exit;
}

function s7x_forms_handle(): void
{
    $pageId  = (string) ($_POST['_page'] ?? '');
    $blockId = (string) ($_POST['_block'] ?? '');
    $page    = cms_page($pageId) ?? cms_home_page();
    $block   = null;
    foreach ((array) $page['blocks'] as $bl) {
        if (($bl['id'] ?? '') === $blockId && ($bl['type'] ?? '') === 'contact-form') {
            $block = $bl;
        }
    }
    $anchor = $block && !empty($block['anchor']) ? '#' . $block['anchor'] : '';
    $back   = cms_page_url($page);
    if (!$block) {
        s7x_forms_reply(false, 'Formulário não encontrado. Recarregue a página e tente de novo.', $back);
    }
    $cfg = cms_block_data($block);

    // Anti-spam
    if (trim((string) ($_POST['website'] ?? '')) !== '' || !s7x_form_token_ok((string) ($_POST['_t'] ?? ''))) {
        s7x_forms_reply(false, 'Não foi possível enviar. Recarregue a página e tente de novo.', $back, $anchor);
    }
    if (!s7x_form_rate_ok()) {
        s7x_forms_reply(false, 'Muitos envios seguidos. Aguarde alguns minutos e tente de novo.', $back, $anchor);
    }

    $name    = trim(strip_tags((string) ($_POST['nome'] ?? '')));
    $phone   = trim(strip_tags((string) ($_POST['telefone'] ?? '')));
    $email   = trim((string) ($_POST['email'] ?? ''));
    $message = trim(strip_tags((string) ($_POST['mensagem'] ?? '')));
    $consent = !empty($_POST['aceite']);

    $err = '';
    if (mb_strlen($name) < 2) {
        $err = 'Informe seu nome.';
    } elseif (($cfg['phone_required'] || $phone !== '') && strlen(cms_digits($phone)) < 10) {
        $err = 'Informe um telefone com DDD.';
    } elseif ($cfg['show_email'] && ($cfg['email_required'] || $email !== '') && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Informe um e-mail válido.';
    } elseif (!$cfg['phone_required'] && $phone === '' && $email === '') {
        $err = 'Informe um telefone ou e-mail para retorno.';
    } elseif (!$consent) {
        $err = 'Marque o aceite para enviar.';
    } elseif (mb_strlen($message) > 3000 || mb_strlen($name) > 120) {
        $err = 'O texto ficou muito longo. Resuma e envie de novo.';
    }
    if ($err) {
        s7x_forms_reply(false, $err, $back, $anchor);
    }

    $ip = cms_client_ip();
    $lead = [
        'id'      => bin2hex(random_bytes(6)),
        'date'    => date('c'),
        'name'    => mb_substr($name, 0, 120),
        'phone'   => mb_substr($phone, 0, 40),
        'email'   => $cfg['show_email'] ? mb_substr($email, 0, 160) : '',
        'message' => $cfg['show_message'] ? $message : '',
        'page'    => (string) ($page['title'] ?? ''),
        'url'     => cms_page_url($page, true),
        'form'    => (string) $cfg['title'],
        'ip'      => preg_replace('/(\d+)\.(\d+)\.\d+\.\d+/', '$1.$2.x.x', $ip),
        'consent' => (string) $cfg['consent_text'],
        'read'    => false,
        'mail'    => null,
    ];
    $lead['mail'] = s7x_forms_mail($lead);
    $leads = s7x_leads();
    array_unshift($leads, $lead);
    s7x_save_leads($leads);

    s7x_forms_reply(true, (string) $cfg['success'], $back, $anchor);
}

/** Material para download: guarda o lead e devolve o link do arquivo. */
function s7x_forms_download_handle(): void
{
    $pageId  = (string) ($_POST['_page'] ?? '');
    $blockId = (string) ($_POST['_block'] ?? '');
    $page    = cms_page($pageId) ?? cms_home_page();
    $block   = null;
    foreach ((array) $page['blocks'] as $bl) {
        if (($bl['id'] ?? '') === $blockId && ($bl['type'] ?? '') === 'lead-download') {
            $block = $bl;
        }
    }
    $back = cms_page_url($page);
    $anchor = $block && !empty($block['anchor']) ? '#' . $block['anchor'] : '';
    if (!$block) {
        s7x_forms_reply(false, 'Formulário não encontrado. Recarregue a página e tente de novo.', $back);
    }
    $cfg = cms_block_data($block);
    if (trim((string) ($_POST['website'] ?? '')) !== '' || !s7x_form_token_ok((string) ($_POST['_t'] ?? ''))) {
        s7x_forms_reply(false, 'Não foi possível enviar. Recarregue a página e tente de novo.', $back, $anchor);
    }
    if (!s7x_form_rate_ok()) {
        s7x_forms_reply(false, 'Muitos envios seguidos. Aguarde alguns minutos e tente de novo.', $back, $anchor);
    }
    $name  = trim(strip_tags((string) ($_POST['nome'] ?? '')));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim(strip_tags((string) ($_POST['telefone'] ?? '')));
    $err = '';
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $err = 'Informe seu nome.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Informe um e-mail válido.';
    } elseif (($cfg['ask_phone'] === 'sim' || $phone !== '') && $cfg['ask_phone'] !== 'nao' && strlen(cms_digits($phone)) < 10) {
        $err = 'Informe o WhatsApp com DDD.';
    } elseif (empty($_POST['aceite'])) {
        $err = 'Marque o aceite para baixar.';
    }
    if ($err) {
        s7x_forms_reply(false, $err, $back, $anchor);
    }
    $lead = [
        'id' => bin2hex(random_bytes(6)), 'date' => date('c'),
        'name' => mb_substr($name, 0, 120), 'phone' => $cfg['ask_phone'] === 'nao' ? '' : mb_substr($phone, 0, 40), 'email' => mb_substr($email, 0, 160),
        'message' => 'Baixou: ' . $cfg['title'] . (!empty($_POST['utm']) ? ' (origem: ' . mb_substr(strip_tags((string) $_POST['utm']), 0, 120) . ')' : ''),
        'page' => (string) ($page['title'] ?? ''), 'url' => cms_page_url($page, true), 'form' => 'Download: ' . $cfg['title'],
        'ip' => preg_replace('/(\d+)\.(\d+)\.\d+\.\d+/', '$1.$2.x.x', cms_client_ip()),
        'consent' => (string) $cfg['consent_text'], 'read' => false, 'mail' => null,
    ];
    $lead['mail'] = s7x_forms_mail($lead);
    $leads = s7x_leads();
    array_unshift($leads, $lead);
    s7x_save_leads($leads);
    $file = trim((string) $cfg['file']);
    $url = $file === '' ? '' : (preg_match('#^https?://#', $file) ? $file : cms_url(ltrim($file, '/')));
    $ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'message' => (string) $cfg['success'], 'download' => $url, 'filename' => (string) $cfg['file_name']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    header('Location: ' . $back . (strpos($back, '?') === false ? '?' : '&') . 'baixar=1' . $anchor, true, 303);
    exit;
}

/** Envia o lead por e-mail. Retorna true/false, ou null se não há destinatário. */
function s7x_forms_mail(array $lead): ?bool
{
    $to = array_filter(array_map('trim', explode(',', (string) cms_module_get('forms', 'recipients', ''))), fn ($m) => filter_var($m, FILTER_VALIDATE_EMAIL));
    if (!$to || !function_exists('mail')) {
        return $to ? false : null;
    }
    $host = preg_replace('/^www\./', '', (string) parse_url(cms_site_url(), PHP_URL_HOST));
    $from = (string) cms_module_get('forms', 'from_email', '');
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'no-reply@' . ($host ?: 'localhost');
    }
    $fromName = (string) cms_module_get('forms', 'from_name', 'Site');
    $subject  = (string) cms_module_get('forms', 'subject', 'Novo contato pelo site') . ' — ' . $lead['name'];
    $lines = [
        'Novo contato pelo site',
        '',
        'Nome: ' . $lead['name'],
        'Telefone: ' . ($lead['phone'] ?: '—'),
        'E-mail: ' . ($lead['email'] ?: '—'),
        'Página: ' . $lead['page'] . ' (' . $lead['url'] . ')',
        'Data: ' . date('d/m/Y H:i'),
        '',
        'Mensagem:',
        $lead['message'] ?: '—',
        '',
        '---',
        'Todos os leads ficam no painel: ' . cms_site_url() . '/admin/?p=leads',
    ];
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        'X-Mailer: Sev7n Xpress',
    ];
    if ($lead['email']) {
        $headers[] = 'Reply-To: ' . $lead['email'];
    }
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $body = implode("\r\n", $lines);
    $ok = @mail(implode(', ', $to), $subj, $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) {
        $ok = @mail(implode(', ', $to), $subj, $body, implode("\r\n", $headers));
    }
    return (bool) $ok;
}

/* ---------- Painel: tela Leads ---------- */

function s7x_leads_admin_handle(string $action): void
{
    $leads = s7x_leads();
    $id    = (string) ($_POST['id'] ?? '');
    foreach ($leads as $i => $l) {
        if ($l['id'] !== $id) {
            continue;
        }
        if ($action === 'read') {
            $leads[$i]['read'] = true;
        } elseif ($action === 'unread') {
            $leads[$i]['read'] = false;
        } elseif ($action === 'delete') {
            unset($leads[$i]);
            cms_flash('ok', 'Lead excluído.');
        }
    }
    if ($action === 'read_all') {
        foreach ($leads as $i => $l) {
            $leads[$i]['read'] = true;
        }
        cms_flash('ok', 'Todos os leads foram marcados como lidos.');
    }
    s7x_save_leads($leads);
}

function s7x_leads_export_csv(): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel abrir os acentos certo
    fputcsv($out, ['Data', 'Nome', 'Telefone', 'E-mail', 'Mensagem', 'Página', 'Lido'], ';');
    foreach (s7x_leads() as $l) {
        fputcsv($out, [cms_format_date($l['date']), $l['name'], $l['phone'], $l['email'], $l['message'], $l['page'], empty($l['read']) ? 'Não' : 'Sim'], ';');
    }
    fclose($out);
    exit;
}

function s7x_leads_admin_render(): void
{
    if (($_GET['export'] ?? '') === 'csv') {
        s7x_leads_export_csv();
    }
    $leads  = s7x_leads();
    $filter = ($_GET['f'] ?? '') === 'novos' ? 'novos' : 'todos';
    $unread = count(array_filter($leads, fn ($l) => empty($l['read'])));
    $show   = $filter === 'novos' ? array_filter($leads, fn ($l) => empty($l['read'])) : $leads;
    $hasMail = trim((string) cms_module_get('forms', 'recipients', '')) !== '';
    ?>
    <?php if (!$hasMail): ?>
      <div class="notice notice--warn">Nenhum e-mail configurado para receber os leads. Eles ficam guardados aqui. <a href="<?= e(admin_url('module', ['m' => 'forms'])) ?>">Configurar e-mails</a></div>
    <?php endif; ?>
    <section class="panel">
      <div class="row row--between">
        <div class="tabs">
          <a href="<?= e(admin_url('leads')) ?>" class="<?= $filter === 'todos' ? 'is-active' : '' ?>">Todos (<?= count($leads) ?>)</a>
          <a href="<?= e(admin_url('leads', ['f' => 'novos'])) ?>" class="<?= $filter === 'novos' ? 'is-active' : '' ?>">Não lidos (<?= $unread ?>)</a>
        </div>
        <div class="row">
          <?php if ($unread): ?>
          <form method="post"><?= cms_csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn--small btn--ghost">Marcar todos como lidos</button></form>
          <?php endif; ?>
          <a class="btn btn--small" href="<?= e(admin_url('leads', ['export' => 'csv'])) ?>">Exportar planilha (CSV)</a>
          <a class="btn btn--small btn--ghost" href="<?= e(admin_url('module', ['m' => 'forms'])) ?>">Configurar e-mails</a>
        </div>
      </div>
      <?php if (!$show): ?>
        <p class="muted empty">Nenhum lead<?= $filter === 'novos' ? ' não lido' : ' ainda' ?>. Os envios do formulário de contato aparecem aqui.</p>
      <?php else: ?>
      <ul class="leads">
        <?php foreach ($show as $l): ?>
        <li class="lead <?= empty($l['read']) ? 'is-new' : '' ?>">
          <details>
            <summary>
              <span class="lead__name"><?= e($l['name']) ?><?= empty($l['read']) ? ' <em class="tag tag--new">novo</em>' : '' ?></span>
              <span class="lead__contact"><?= e($l['phone'] ?: $l['email']) ?></span>
              <span class="lead__date"><?= e(cms_format_date($l['date'])) ?></span>
            </summary>
            <div class="lead__body">
              <dl>
                <dt>Telefone</dt><dd><?php if ($l['phone']): ?><a href="<?= e(cms_tel_link($l['phone'])) ?>"><?= e($l['phone']) ?></a> · <a href="<?= e(cms_whatsapp_link($l['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a><?php else: ?>—<?php endif; ?></dd>
                <dt>E-mail</dt><dd><?= $l['email'] ? '<a href="mailto:' . e($l['email']) . '">' . e($l['email']) . '</a>' : '—' ?></dd>
                <dt>Mensagem</dt><dd><?= $l['message'] !== '' ? nl($l['message']) : '—' ?></dd>
                <dt>Página</dt><dd><?= e($l['page']) ?> · <?= e($l['form']) ?></dd>
                <dt>E-mail de aviso</dt><dd><?= $l['mail'] === null ? 'Não enviado (sem destinatário configurado)' : ($l['mail'] ? 'Enviado' : '<span class="bad">Falhou — verifique o envio de e-mails do servidor</span>') ?></dd>
              </dl>
              <div class="row">
                <form method="post"><?= cms_csrf_field() ?><input type="hidden" name="id" value="<?= e($l['id']) ?>"><input type="hidden" name="action" value="<?= empty($l['read']) ? 'read' : 'unread' ?>"><button class="btn btn--small"><?= empty($l['read']) ? 'Marcar como lido' : 'Marcar como não lido' ?></button></form>
                <form method="post" data-confirm="Excluir o lead de <?= e($l['name']) ?>? Isso não pode ser desfeito."><?= cms_csrf_field() ?><input type="hidden" name="id" value="<?= e($l['id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn btn--small btn--danger">Excluir</button></form>
              </div>
            </div>
          </details>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <?php
}
