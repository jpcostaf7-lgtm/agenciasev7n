<?php
/**
 * Tema Agência Sev7n — funções auxiliares
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}
if (defined('SV_FUNCTIONS')) {
    return;
}
define('SV_FUNCTIONS', true);

/** Linhas não vazias de um campo de texto. */
function sv_lines($s): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $s) ?: []), fn ($l) => $l !== ''));
}

/** Linhas "Texto | link" → [['label'=>..,'link'=>..]] */
function sv_pairs($s): array
{
    $out = [];
    foreach (sv_lines($s) as $l) {
        $p = array_map('trim', explode('|', $l, 2));
        $out[] = ['label' => $p[0], 'link' => $p[1] ?? ''];
    }
    return $out;
}

function sv_wa_url(string $message = ''): string
{
    if ($message === '') {
        $p = $GLOBALS['cms_current_page'] ?? [];
        $name = !empty($p['home']) ? 'página inicial' : (string) ($p['title'] ?? 'site');
        $message = str_replace('{pagina}', $name, (string) c('contato.whatsapp_message', 'Olá! Vim pelo site da Agência Sev7n ({pagina}) e gostaria de um orçamento.'));
    }
    return cms_whatsapp_link((string) c('contato.whatsapp', ''), $message);
}

/**
 * Link digitado no painel:
 *   wa  /  wa: mensagem  → WhatsApp
 *   /pagina  #secao  https://…  tel:  mailto:  → link normal
 */
function sv_href($link): array
{
    $link = trim((string) $link);
    if ($link === '') {
        return ['href' => '', 'ext' => false, 'wa' => false];
    }
    if (preg_match('/^(wa|whatsapp)\s*(:\s*(.*))?$/isu', $link, $m)) {
        return ['href' => sv_wa_url(trim((string) ($m[3] ?? ''))), 'ext' => true, 'wa' => true];
    }
    if (preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $link)) {
        $ext = (bool) preg_match('#^https?://#i', $link) && strpos($link, cms_site_url()) !== 0;
        return ['href' => cms_link($link), 'ext' => $ext, 'wa' => false];
    }
    return ['href' => cms_link('/' . ltrim($link, '/')), 'ext' => false, 'wa' => false];
}

/** <a> com o link do painel (WhatsApp e externos abrem em nova aba). */
function sv_a(string $label, $link, string $class = '', string $icon = ''): void
{
    $h = sv_href($link);
    if ($h['href'] === '' || trim($label) === '') {
        return;
    }
    $ic = $icon !== '' ? sv_icon($icon) : ($h['wa'] && $class !== '' ? sv_icon('whatsapp') : '');
    echo '<a href="' . e($h['href']) . '"' . ($class !== '' ? ' class="' . e($class) . '"' : '') . ($h['ext'] ? ' target="_blank" rel="noopener"' : '') . ($h['wa'] ? ' data-wa' : '') . '>' . $ic . '<span>' . e($label) . '</span></a>';
}

function sv_tel(string $phone): string
{
    return cms_tel_link($phone);
}

/** <img> de um campo de imagem. */
function sv_img($field, string $class = '', array $attrs = []): void
{
    $v = cms_resolve_image($field);
    if ($v['src'] === '') {
        return;
    }
    $attrs += ['loading' => 'lazy', 'decoding' => 'async'];
    $alt = $attrs['alt'] ?? $v['alt'];
    unset($attrs['alt']);
    $file = CMS_ROOT . '/' . ltrim($v['src'], '/');
    if (!isset($attrs['width']) && is_file($file) && ($sz = @getimagesize($file))) {
        $attrs['width'] = $sz[0];
        $attrs['height'] = $sz[1];
    }
    $html = '<img src="' . e(cms_url($v['src'])) . '" alt="' . e((string) $alt) . '"' . ($class !== '' ? ' class="' . e($class) . '"' : '');
    foreach ($attrs as $k => $val) {
        $html .= ' ' . e($k) . '="' . e((string) $val) . '"';
    }
    echo $html . '>';
}

/** Ícones de traço (24×24). */
function sv_icons(): array
{
    return [
        'site'       => 'Site', 'sistema' => 'Sistema', 'pincel' => 'Design', 'celular' => 'Celular', 'carrinho' => 'Loja',
        'grafico'    => 'Gráfico', 'lupa' => 'Lupa', 'raio' => 'Velocidade', 'escudo' => 'Segurança', 'alvo' => 'Alvo',
        'relogio'    => 'Prazo', 'conversa' => 'Conversa', 'moeda' => 'Custo', 'faisca' => 'IA', 'mapa' => 'Mapa',
    ];
}

function sv_icon(string $name, string $class = 'ic'): string
{
    $p = [
        'site'      => '<rect x="2.5" y="4" width="19" height="15" rx="2"/><path d="M2.5 8.5h19M6 6.3h.01M8.5 6.3h.01"/>',
        'sistema'   => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><path d="M17.2 13.5v7.5M13.5 17.2H21"/>',
        'pincel'    => '<path d="M14.5 4.5l5 5L10 19a3.5 3.5 0 0 1-5-5z"/><path d="M12.5 6.5l5 5"/>',
        'celular'   => '<rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M11 18.5h2"/>',
        'carrinho'  => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h3l2.4 11.2a2 2 0 0 0 2 1.6h8a2 2 0 0 0 2-1.5l1.6-6.8H6.6"/>',
        'grafico'   => '<path d="M3 20.5h18"/><path d="M5.5 16l4.5-5 3.5 3 6-7"/><path d="M15 7h4.5v4.5"/>',
        'lupa'      => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'raio'      => '<path d="M13 2.5L5 13.5h6l-1 8 8-11h-6z"/>',
        'escudo'    => '<path d="M12 2.8l7.5 3v5.6c0 4.7-3.2 8.3-7.5 9.8-4.3-1.5-7.5-5.1-7.5-9.8V5.8z"/><path d="M8.8 12l2.2 2.2 4.4-4.4"/>',
        'alvo'      => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r=".9" fill="currentColor"/>',
        'relogio'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'conversa'  => '<path d="M4 5.5h16v10H9l-5 4z"/><path d="M8 9.5h8M8 12.5h5"/>',
        'moeda'     => '<circle cx="12" cy="12" r="8.5"/><path d="M14.8 9.2c-.5-1-1.6-1.6-2.8-1.6-1.6 0-2.8.9-2.8 2.1 0 2.9 5.6 1.5 5.6 4.4 0 1.2-1.2 2.2-2.8 2.2-1.3 0-2.5-.7-2.9-1.8M12 6v1.6M12 16.3V18"/>',
        'faisca'    => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 15.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z"/>',
        'mapa'      => '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/>',
        'whatsapp'  => '<path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.5L3.5 20.5l1.4-4.2a8.5 8.5 0 1 1 15.6-4.7z"/><path d="M9 8.8c.2 2.9 2.4 5.3 5.3 5.8l1.1-1.2-1.8-.9-.8.8c-1-.4-1.8-1.2-2.2-2.2l.8-.8-.9-1.8z"/>',
        'telefone'  => '<path d="M5 3.5h3.2l1.6 4.2-2.1 1.3a11 11 0 0 0 5.3 5.3l1.3-2.1 4.2 1.6V17a2 2 0 0 1-2 2A15.5 15.5 0 0 1 3 5.5a2 2 0 0 1 2-2z"/>',
        'email'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6l8.5 7 8.5-7"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".9" fill="currentColor"/>',
        'tiktok'    => '<path d="M14 3.5v11.2a3.3 3.3 0 1 1-3.3-3.3"/><path d="M14 3.5c.4 2.6 2.3 4.4 5 4.6"/>',
        'linkedin'  => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M8 10.5v6M8 7.5v.01M11.5 16.5v-3.5a2.3 2.3 0 0 1 4.6 0v3.5M11.5 10.5v6"/>',
        'seta'      => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'externo'   => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'check'     => '<path d="M5 12.5l4.2 4.2L19 7"/>',
        'aspas'     => '<path d="M9.5 6.5C6.5 7.5 5 10 5 13.5v4h5v-5H7.5c0-2.2 1-3.6 2.8-4.3zM18.5 6.5c-3 1-4.5 3.5-4.5 7v4h5v-5h-2.5c0-2.2 1-3.6 2.8-4.3z"/>',
        'cadeado'   => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($p[$name] ?? $p['check']) . '</svg>';
}

/** Marca (V7) + "sev7n" em texto, para o cabeçalho e o rodapé. */
function sv_logo(string $class = 'logo'): string
{
    $mark = cms_resolve_image(c('geral.logo', ''));
    $img = $mark['src'] !== '' ? '<img src="' . e(cms_url($mark['src'])) . '" alt="" width="46" height="40" class="' . e($class) . '__mark">' : '';
    return '<span class="' . e($class) . '">' . $img . '<span class="' . e($class) . '__word">sev<b>7</b>n</span></span>';
}

/** Dados da empresa no schema: redes, área de atendimento e especialidades. */
cms_add_filter('structured_data_business', function (array $biz) {
    $sameAs = array_values(array_filter([c('contato.instagram', ''), c('contato.tiktok', ''), c('contato.linkedin', '')]));
    if ($sameAs) {
        $biz['sameAs'] = $sameAs;
    }
    $biz['areaServed'] = [['@type' => 'City', 'name' => 'Rio de Janeiro'], ['@type' => 'Country', 'name' => 'Brasil']];
    $biz['knowsAbout'] = ['Criação de sites', 'SEO', 'Lojas virtuais', 'Sistemas web', 'Google Ads', 'Busca por IA'];
    $biz['foundingDate'] = '2017';
    $biz['founder'] = ['@type' => 'Person', 'name' => 'João Costa'];
    return $biz;
});

/** Endereços antigos (Configurações → Cabeçalho e rodapé → Redirecionamentos) e /llms.txt. */
cms_add_action('request', function () {
    $path = rtrim(cms_request_path(), '/') ?: '/';
    if ($path === '/llms.txt') {
        $txt = trim((string) c('geral.llms', ''));
        if ($txt !== '') {
            $base = rtrim(cms_site_url(), '/');
            $txt = preg_replace('#\]\(/#', '](' . $base . '/', $txt);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            echo $txt . "\n";
            exit;
        }
    }
    foreach (sv_pairs(c('geral.redirects', '')) as $r) {
        $from = rtrim($r['label'], '/') ?: '/';
        if ($from === '/' || $r['link'] === '') {
            continue;
        }
        if ($path === $from || strpos($path . '/', $from . '/') === 0) {
            header('Location: ' . sv_href($r['link'])['href'], true, 301);
            exit;
        }
    }
}, 0);


/* -------------------------------------------------------------------------
 * Portfólio: projetos novos (aparecem primeiro)
 * ---------------------------------------------------------------------- */

/** Projetos mais recentes, com os prints dos layouts novos. */
function sv_portfolio_novos(): array
{
    $port = 'themes/' . basename(__DIR__) . '/assets/fotos/portfolio/';
    return [
        ['name' => 'Assertiva Contábil', 'category' => 'Site institucional', 'url' => 'https://assertivacontabil.com.br', 'image' => ['src' => $port . 'assertiva.webp', 'alt' => 'Site da Assertiva Contábil'], 'text' => 'Contabilidade consultiva no Rio com visual premium, uma página para cada serviço, troca de contador e reforma tributária.', 'tags' => 'Contabilidade, Sev7n Xpress'],
        ['name' => 'CTVM Training Club', 'category' => 'Site institucional', 'url' => 'https://ctvmtrainingclub.com.br', 'image' => ['src' => $port . 'ctvm.webp', 'alt' => 'Site do CTVM Training Club'], 'text' => 'Box de treino na Vila Madalena (SP) com vídeo no topo, uma página por modalidade, horários e aula experimental pelo WhatsApp.', 'tags' => 'Fitness, Sev7n Xpress'],
        ['name' => 'Shelter Consultoria', 'category' => 'Site + blog com IA', 'url' => 'https://shelterconsultoria.com.br', 'image' => ['src' => $port . 'shelter-consultoria.webp', 'alt' => 'Site da Shelter Consultoria'], 'text' => 'Consultoria marítima e offshore com mais de 700 notícias importadas e artigos novos escritos com IA toda semana.', 'tags' => 'Marítimo, Blog com IA'],
        ['name' => 'Ambiental Cupim', 'category' => 'Site com SEO local', 'url' => 'https://ambiental-cupim.com.br', 'image' => ['src' => $port . 'ambiental.webp', 'alt' => 'Site da Ambiental Cupim'], 'text' => 'Dedetização no Rio, Niterói e São Gonçalo, com serviços, segmentos atendidos e orçamento direto no WhatsApp.', 'tags' => 'Serviços, SEO local'],
    ];
}

/**
 * Atualização única do conteúdo salvo no servidor: quando as páginas já foram salvas pelo
 * painel, o portfólio salvo não recebe os projetos novos do tema. Na primeira visita depois
 * da publicação, os que faltam entram no começo da lista (pelo nome; nada é apagado).
 * Guarda uma revisão antes e só roda uma vez (data/tema-atualizacoes.json).
 */
cms_add_action('request', function () {
    $mark = CMS_DATA . '/tema-atualizacoes.json';
    $done = cms_read_json($mark, []);
    $key = 'portfolio-2026-11-26';
    if (!empty($done[$key])) {
        return;
    }
    $file = CMS_DATA . '/content.json';
    $saved = is_file($file) ? cms_read_json($file, []) : [];
    if (is_array($saved['pages'] ?? null)) {
        $novos = sv_portfolio_novos();
        $porPagina = ['criacao-sites-rj' => ['Assertiva Contábil', 'Ambiental Cupim']];
        $c = cms_content();
        $mudou = false;
        foreach ($c['pages'] as $pid => $pg) {
            foreach ((array) ($pg['blocks'] ?? []) as $bi => $bl) {
                if (($bl['type'] ?? '') !== 'portfolio') {
                    continue;
                }
                $itens = (array) ($bl['data']['items'] ?? []);
                $tem = array_map(fn ($i) => mb_strtolower(trim((string) ($i['name'] ?? ''))), $itens);
                $quais = $porPagina[$pid] ?? null;
                $add = array_values(array_filter($novos, fn ($n) => !in_array(mb_strtolower($n['name']), $tem, true) && ($quais === null || in_array($n['name'], $quais, true))));
                if ($add) {
                    $c['pages'][$pid]['blocks'][$bi]['data']['items'] = array_merge($add, $itens);
                    $mudou = true;
                }
            }
        }
        if ($mudou && !cms_save_content($c, 'Portfólio: projetos novos')) {
            return; // tenta de novo na próxima visita
        }
    }
    $done[$key] = date('c');
    cms_write_json($mark, $done);
}, 1);

