<?php
/**
 * Sev7n Xpress — SEO: campos, <head>, rastreamento e dados estruturados
 * Autor: João Costa
 */
declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Campos de SEO (iguais em todos os projetos; padrões vêm do tema)
 * ---------------------------------------------------------------------- */

function cms_seo_fields(): array
{
    $d = cms_theme()['seo_defaults'] ?? [];
    $g = function (string $k, $fallback = '') use ($d) {
        return $d[$k] ?? $fallback;
    };
    return [
        'google' => [
            'label' => 'Aparência no Google',
            'help'  => 'Padrão do site e da página inicial. Cada página pode ter título e descrição próprios no editor da página.',
            'fields' => [
                'title'       => ['type' => 'text', 'label' => 'Título SEO', 'max' => 60, 'default' => $g('title'), 'help' => 'Ideal entre 50 e 60 caracteres. Coloque a palavra-chave principal no início.'],
                'description' => ['type' => 'textarea', 'label' => 'Meta descrição', 'max' => 160, 'rows' => 3, 'default' => $g('description'), 'help' => 'Ideal entre 120 e 160 caracteres. É o texto que convence o clique.'],
                'focus_keyword' => ['type' => 'text', 'label' => 'Palavra-chave foco', 'default' => $g('focus_keyword'), 'help' => 'Usada só na análise abaixo. Não é enviada ao Google.'],
                'site_url'    => ['type' => 'url', 'label' => 'URL oficial do site (canonical)', 'default' => $g('site_url'), 'placeholder' => 'https://www.seudominio.com.br', 'help' => 'Evita conteúdo duplicado entre www e sem www. Deixe vazio para detectar.'],
                'index'       => ['type' => 'toggle', 'label' => 'Permitir que buscadores indexem o site', 'default' => $g('index', true), 'help' => 'Desligue enquanto o site estiver em construção.'],
                'lang'        => ['type' => 'text', 'label' => 'Idioma', 'default' => $g('lang', 'pt-BR')],
            ],
        ],
        'social' => [
            'label' => 'Compartilhamento (WhatsApp, Facebook, LinkedIn)',
            'help'  => 'Prévia mostrada quando o link é enviado em redes sociais.',
            'fields' => [
                'og_title'       => ['type' => 'text', 'label' => 'Título para redes sociais', 'max' => 70, 'default' => $g('og_title'), 'help' => 'Vazio = usa o Título SEO.'],
                'og_description' => ['type' => 'textarea', 'label' => 'Descrição para redes sociais', 'max' => 200, 'rows' => 2, 'default' => $g('og_description'), 'help' => 'Vazio = usa a meta descrição.'],
                'og_image'       => ['type' => 'image', 'label' => 'Imagem de compartilhamento', 'default' => $g('og_image'), 'help' => 'Tamanho recomendado: 1200 × 630 px.'],
                'favicon'        => ['type' => 'image', 'label' => 'Favicon (ícone da aba)', 'default' => $g('favicon'), 'help' => 'PNG quadrado, mínimo 64 × 64 px.'],
                'theme_color'    => ['type' => 'color', 'label' => 'Cor do navegador no celular', 'default' => $g('theme_color', '#1E5631')],
            ],
        ],
        'schema' => [
            'label' => 'Dados estruturados (Google Meu Negócio / Rich results)',
            'help'  => 'Ajuda o Google a entender quem é o negócio, onde fica e como contatar.',
            'fields' => [
                'schema_type'    => ['type' => 'select', 'label' => 'Tipo de negócio', 'default' => $g('schema_type', 'LocalBusiness'), 'options' => [
                    'LocalBusiness' => 'Negócio local', 'Organization' => 'Empresa / Organização', 'Physician' => 'Médico(a)',
                    'MedicalClinic' => 'Clínica médica', 'Dentist' => 'Dentista', 'LegalService' => 'Advocacia / Serviço jurídico',
                    'ProfessionalService' => 'Serviço profissional', 'Store' => 'Loja', 'Restaurant' => 'Restaurante',
                    'EducationalOrganization' => 'Escola / Cursos', 'Person' => 'Pessoa (marca pessoal)',
                ]],
                'org_name'       => ['type' => 'text', 'label' => 'Nome do negócio', 'default' => $g('org_name')],
                'org_phone'      => ['type' => 'text', 'label' => 'Telefone (com DDD)', 'default' => $g('org_phone'), 'placeholder' => '+55 11 3085-0656'],
                'org_email'      => ['type' => 'email', 'label' => 'E-mail', 'default' => $g('org_email')],
                'org_street'     => ['type' => 'text', 'label' => 'Endereço (rua e número)', 'default' => $g('org_street')],
                'org_district'   => ['type' => 'text', 'label' => 'Bairro', 'default' => $g('org_district')],
                'org_city'       => ['type' => 'text', 'label' => 'Cidade', 'default' => $g('org_city')],
                'org_state'      => ['type' => 'text', 'label' => 'Estado (sigla)', 'default' => $g('org_state')],
                'org_zip'        => ['type' => 'text', 'label' => 'CEP', 'default' => $g('org_zip')],
                'org_lat'        => ['type' => 'text', 'label' => 'Latitude (opcional)', 'default' => $g('org_lat')],
                'org_lng'        => ['type' => 'text', 'label' => 'Longitude (opcional)', 'default' => $g('org_lng')],
                'org_hours'      => ['type' => 'text', 'label' => 'Horário (formato Google)', 'default' => $g('org_hours'), 'placeholder' => 'Mo-Fr 08:00-18:00', 'help' => 'Ex.: Mo-Fr 08:00-18:00, Sa 08:00-12:00'],
                'org_same_as'    => ['type' => 'textarea', 'label' => 'Perfis oficiais (um link por linha)', 'rows' => 3, 'default' => $g('org_same_as'), 'help' => 'Instagram, Facebook, LinkedIn, Doctoralia, Google Meu Negócio…'],
                'faq_schema'     => ['type' => 'toggle', 'label' => 'Gerar FAQ estruturado a partir das perguntas frequentes', 'default' => $g('faq_schema', true)],
            ],
        ],
        'tracking' => [
            'label' => 'Rastreamento e códigos extras',
            'help'  => 'Search Console, Analytics, AdSense e Google Ads ficam nos módulos do Google. Aqui ficam o Tag Manager, o Meta Pixel e códigos extras.',
            'fields' => [
                'gtm_id'           => ['type' => 'text', 'label' => 'Google Tag Manager (ID)', 'default' => '', 'placeholder' => 'GTM-XXXXXXX'],
                'meta_pixel_id'    => ['type' => 'text', 'label' => 'Meta Pixel (ID)', 'default' => '', 'placeholder' => '123456789012345'],
                'head_code'        => ['type' => 'code', 'label' => 'Código extra no <head>', 'rows' => 4, 'default' => ''],
                'body_code'        => ['type' => 'code', 'label' => 'Código extra antes de </body>', 'rows' => 4, 'default' => ''],
            ],
        ],
    ];
}


/** Valor de SEO global. */
function cms_seo(string $key, $fallback = '')
{
    $content = cms_content();
    if (array_key_exists($key, $content['seo'])) {
        return $content['seo'][$key];
    }
    foreach (cms_seo_fields() as $group) {
        if (isset($group['fields'][$key]) && array_key_exists('default', $group['fields'][$key])) {
            return $group['fields'][$key]['default'];
        }
    }
    return $fallback;
}

/** Campos de SEO próprios de cada página (editor da página). */
function cms_page_seo_fields(): array
{
    return [
        'title'       => ['type' => 'text', 'label' => 'Título SEO desta página', 'max' => 60, 'help' => 'Vazio = usa o título da página + nome do site (na inicial, o título SEO geral).'],
        'description' => ['type' => 'textarea', 'label' => 'Meta descrição desta página', 'max' => 160, 'rows' => 2, 'help' => 'Vazio = usa a meta descrição geral.'],
        'og_image'    => ['type' => 'image', 'label' => 'Imagem de compartilhamento desta página', 'help' => 'Vazio = usa a imagem geral. 1200 × 630 px.'],
        'noindex'     => ['type' => 'toggle', 'label' => 'Esconder esta página do Google (noindex)'],
    ];
}

/** SEO de uma página com as regras de reserva. */
function cms_page_meta(array $page): array
{
    $seo   = (array) ($page['seo'] ?? []);
    $home  = !empty($page['home']);
    $brand = cms_site_name();
    $title = trim((string) ($seo['title'] ?? ''));
    if ($title === '') {
        $title = $home ? trim((string) cms_seo('title')) : trim((string) ($page['title'] ?? '')) . ' | ' . $brand;
    }
    if ($title === '') {
        $title = cms_site_name();
    }
    $desc = trim((string) ($seo['description'] ?? '')) ?: trim((string) cms_seo('description'));
    $img  = cms_image_value($seo['og_image'] ?? '');
    if ($img['src'] === '') {
        $img = cms_image_value(cms_seo('og_image'));
    }
    $index = (bool) cms_seo('index', true) && empty($seo['noindex']) && ($page['status'] ?? 'published') === 'published';
    $meta = [
        'title'    => $title,
        'desc'     => $desc,
        'og_title' => $home ? (trim((string) cms_seo('og_title')) ?: $title) : $title,
        'og_desc'  => $home ? (trim((string) cms_seo('og_description')) ?: $desc) : $desc,
        'image'    => $img,
        'index'    => $index,
        'url'      => cms_page_url($page, true),
        'og_type'  => 'website',
    ];
    // Páginas virtuais (posts do blog, categorias) trazem endereço e tipo próprios
    foreach (['url', 'og_type'] as $k) {
        if (!empty($page['meta'][$k])) {
            $meta[$k] = (string) $page['meta'][$k];
        }
    }
    return cms_apply_filters('page_meta', $meta, $page);
}

/** Códigos de rastreamento (GA4, GTM, Pixel e extras). Módulos podem filtrar (ex.: cookies). */
function cms_tracking_html(string $place): string
{
    $html = '';
    $gtm  = preg_replace('/[^A-Z0-9\-]/', '', strtoupper(trim((string) cms_seo('gtm_id'))));
    $px   = preg_replace('/\D/', '', (string) cms_seo('meta_pixel_id'));
    if ($place === 'head') {
        if ($gtm) {
            $html .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . $gtm . "');</script>\n";
        }
        if ($px) {
            $html .= "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . $px . "');fbq('track','PageView');</script>\n";
        }
        $extra = (string) cms_seo('head_code');
        if (trim($extra) !== '') {
            $html .= $extra . "\n";
        }
    } elseif ($place === 'body_open') {
        if ($gtm) {
            $html .= '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
        }
    } elseif ($place === 'body_close') {
        $extra = (string) cms_seo('body_code');
        if (trim($extra) !== '') {
            $html .= $extra . "\n";
        }
    }
    return (string) cms_apply_filters('tracking_html', $html, $place);
}

/** Imprime todo o <head> de SEO da página atual. */
function cms_head(?array $page = null): void
{
    $page = $page ?? ($GLOBALS['cms_current_page'] ?? cms_home_page());
    $m    = cms_page_meta($page);
    $fav  = cms_image_value(cms_seo('favicon'));
    $lang = (string) cms_seo('lang', 'pt-BR');

    echo '<title>' . e($m['title']) . "</title>\n";
    echo '<meta name="description" content="' . e($m['desc']) . "\">\n";
    echo '<meta name="robots" content="' . ($m['index'] ? 'index, follow, max-image-preview:large' : 'noindex, nofollow') . "\">\n";
    echo '<link rel="canonical" href="' . e($m['url']) . "\">\n";
    echo '<meta name="theme-color" content="' . e(cms_seo('theme_color', '#1E5631')) . "\">\n";
    if ($fav['src']) {
        echo '<link rel="icon" type="image/png" href="' . e(cms_url($fav['src'])) . "\">\n";
        echo '<link rel="apple-touch-icon" href="' . e(cms_url($fav['src'])) . "\">\n";
    }
    echo '<meta property="og:type" content="' . e($m['og_type']) . "\">\n";
    echo '<meta property="og:locale" content="' . e(str_replace('-', '_', $lang)) . "\">\n";
    echo '<meta property="og:site_name" content="' . e(cms_seo('org_name') ?: cms_site_name()) . "\">\n";
    echo '<meta property="og:title" content="' . e($m['og_title']) . "\">\n";
    echo '<meta property="og:description" content="' . e($m['og_desc']) . "\">\n";
    echo '<meta property="og:url" content="' . e($m['url']) . "\">\n";
    if ($m['image']['src']) {
        $abs = cms_abs_url($m['image']['src']);
        echo '<meta property="og:image" content="' . e($abs) . "\">\n";
        $file = CMS_ROOT . '/' . ltrim($m['image']['src'], '/');
        if (is_file($file) && ($s = @getimagesize($file))) {
            echo '<meta property="og:image:width" content="' . (int) $s[0] . '">' . "\n";
            echo '<meta property="og:image:height" content="' . (int) $s[1] . '">' . "\n";
        }
        if ($m['image']['alt']) {
            echo '<meta property="og:image:alt" content="' . e($m['image']['alt']) . "\">\n";
        }
        echo '<meta name="twitter:image" content="' . e($abs) . "\">\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . e($m['og_title']) . "\">\n";
    echo '<meta name="twitter:description" content="' . e($m['og_desc']) . "\">\n";

    foreach (cms_structured_data($page) as $ld) {
        echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . "</script>\n";
    }
    echo cms_tracking_html('head');
    cms_do_action('head', $page);
}

function cms_body_open(): void
{
    echo cms_tracking_html('body_open');
    cms_do_action('body_open', $GLOBALS['cms_current_page'] ?? null);
}

function cms_body_close(): void
{
    cms_do_action('body_close', $GLOBALS['cms_current_page'] ?? null);
    echo cms_tracking_html('body_close');
}

function cms_structured_data(array $page): array
{
    $url  = cms_site_url() . '/';
    $type = (string) cms_seo('schema_type', 'LocalBusiness');
    $name = trim((string) cms_seo('org_name')) ?: cms_site_name();
    $out  = [];

    $biz = ['@context' => 'https://schema.org', '@type' => $type, '@id' => $url . '#negocio', 'name' => $name, 'url' => $url];
    if ($d = trim((string) cms_seo('description'))) {
        $biz['description'] = $d;
    }
    $img = cms_image_value(cms_seo('og_image'));
    if ($img['src']) {
        $biz['image'] = cms_abs_url($img['src']);
    }
    if ($p = trim((string) cms_seo('org_phone'))) {
        $biz['telephone'] = $p;
    }
    if ($mail = trim((string) cms_seo('org_email'))) {
        $biz['email'] = $mail;
    }
    if ($type !== 'Person' && ($st = trim((string) cms_seo('org_street')))) {
        $biz['address'] = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => $st . (($b = trim((string) cms_seo('org_district'))) ? ' - ' . $b : ''),
            'addressLocality' => trim((string) cms_seo('org_city')),
            'addressRegion'   => trim((string) cms_seo('org_state')),
            'postalCode'      => trim((string) cms_seo('org_zip')),
            'addressCountry'  => 'BR',
        ]);
    }
    $lat = trim((string) cms_seo('org_lat'));
    $lng = trim((string) cms_seo('org_lng'));
    if ($lat !== '' && $lng !== '' && $type !== 'Person') {
        $biz['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng];
    }
    if (($h = trim((string) cms_seo('org_hours'))) && $type !== 'Person') {
        $biz['openingHours'] = array_values(array_filter(array_map('trim', explode(',', $h))));
    }
    $same = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) cms_seo('org_same_as')) ?: [])));
    if ($same) {
        $biz['sameAs'] = $same;
    }
    $out[] = cms_apply_filters('structured_data_business', $biz);
    $out[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', '@id' => $url . '#site', 'url' => $url, 'name' => $name, 'inLanguage' => (string) cms_seo('lang', 'pt-BR'), 'publisher' => ['@id' => $url . '#negocio']];

    // FAQ: repetidores marcados com 'faq' => true nos blocos visíveis desta página
    if (cms_seo('faq_schema', true)) {
        $q = [];
        foreach ((array) ($page['blocks'] ?? []) as $block) {
            $def = cms_block_type((string) ($block['type'] ?? ''));
            if (!$def || empty($block['enabled'])) {
                continue;
            }
            $data = cms_block_data($block);
            foreach ($def['fields'] as $fid => $f) {
                if (!empty($f['faq'])) {
                    foreach ((array) ($data[$fid] ?? []) as $item) {
                        $question = trim((string) ($item['question'] ?? ''));
                        $answer   = trim(strip_tags((string) ($item['answer'] ?? '')));
                        if ($question !== '' && $answer !== '') {
                            $q[] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
                        }
                    }
                }
            }
        }
        if ($q) {
            $out[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $q];
        }
    }
    if (empty($page['home'])) {
        $crumbs = (array) ($page['breadcrumb'] ?? []);
        if (!$crumbs) {
            $crumbs = [[(string) ($page['title'] ?? ''), cms_page_meta($page)['url']]];
        }
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => $url]];
        foreach ($crumbs as $i => $c) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => (string) $c[0], 'item' => (string) $c[1]];
        }
        $out[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
    return cms_apply_filters('structured_data', $out, $page);
}
