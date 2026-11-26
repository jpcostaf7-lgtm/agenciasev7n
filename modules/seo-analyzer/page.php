<?php
/**
 * Analisador de SEO — leitura de uma página HTML
 * Autor: João Costa
 *
 * Transforma o HTML em fatos (título, headings, imagens, links, dados estruturados,
 * ferramentas instaladas…). As notas e os textos de cada item ficam em checks.php.
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_seo_dom(string $html): ?DOMDocument
{
    if (trim($html) === '') {
        return null;
    }
    // Garante UTF-8 para o DOMDocument (ele assume ISO-8859-1 sem a declaração)
    if (!preg_match('//u', $html)) {
        $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1, Windows-1252');
    }
    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT | LIBXML_PARSEHUGE);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    return $dom;
}

function s7x_seo_text(?DOMNode $n): string
{
    return $n ? trim(preg_replace('/\s+/u', ' ', $n->textContent)) : '';
}

/** Lê uma página e devolve os fatos que interessam para SEO. */
function s7x_seo_parse(string $html, string $url, array $headers = []): array
{
    $dom = s7x_seo_dom($html);
    $f = [
        'title' => '', 'description' => '', 'robots' => '', 'canonical' => '', 'lang' => '', 'viewport' => '', 'charset' => '',
        'h1' => [], 'headings' => [], 'heading_skips' => [], 'og' => [], 'twitter' => [], 'jsonld' => [], 'jsonld_types' => [], 'jsonld_errors' => 0,
        'microdata' => false, 'hreflang' => [], 'favicon' => '', 'apple_icon' => false, 'manifest' => false,
        'images' => [], 'links' => [], 'words' => 0, 'text' => '', 'first_words' => '', 'html_bytes' => strlen($html), 'text_ratio' => 0,
        'scripts' => 0, 'scripts_blocking' => 0, 'styles' => 0, 'inline_styles_bytes' => 0, 'iframes' => 0,
        'has_main' => false, 'has_nav' => false, 'has_header' => false, 'has_footer' => false,
        'forms' => 0, 'whatsapp' => false, 'tel' => false, 'mailto' => false, 'address_hint' => false,
        'tools' => [], 'tech' => [], 'generator' => '', 'spa' => false, 'mixed' => [], 'amp' => false,
        'verification' => [],
    ];
    if (!$dom) {
        return $f;
    }
    $xp = new DOMXPath($dom);
    $one = function (string $q) use ($xp) {
        $n = $xp->query($q);
        return $n && $n->length ? $n->item(0) : null;
    };

    $t = $one('//title');
    $f['title'] = s7x_seo_text($t);
    foreach ($xp->query('//meta') as $m) {
        /** @var DOMElement $m */
        $name = strtolower($m->getAttribute('name') ?: $m->getAttribute('property') ?: $m->getAttribute('http-equiv'));
        $content = trim($m->getAttribute('content'));
        if ($m->hasAttribute('charset')) {
            $f['charset'] = strtolower($m->getAttribute('charset'));
        }
        switch ($name) {
            case 'description':
                $f['description'] = $f['description'] ?: $content;
                break;
            case 'robots':
            case 'googlebot':
                $f['robots'] = trim($f['robots'] . ', ' . strtolower($content), ', ');
                break;
            case 'viewport':
                $f['viewport'] = $content;
                break;
            case 'generator':
                $f['generator'] = $f['generator'] ?: $content;
                break;
            case 'google-site-verification':
                $f['verification'][] = 'Google Search Console';
                break;
            case 'facebook-domain-verification':
                $f['verification'][] = 'Meta (Facebook)';
                break;
            case 'msvalidate.01':
                $f['verification'][] = 'Bing';
                break;
        }
        if (strpos($name, 'og:') === 0) {
            $f['og'][substr($name, 3)] = $f['og'][substr($name, 3)] ?? $content;
        } elseif (strpos($name, 'twitter:') === 0) {
            $f['twitter'][substr($name, 8)] = $content;
        }
    }
    $html_el = $one('//html');
    if ($html_el instanceof DOMElement) {
        $f['lang'] = $html_el->getAttribute('lang');
        $f['amp'] = $html_el->hasAttribute('amp') || $html_el->hasAttribute('⚡');
    }
    foreach ($xp->query('//link[@rel]') as $l) {
        /** @var DOMElement $l */
        $rel = strtolower($l->getAttribute('rel'));
        $href = $l->getAttribute('href');
        if ($rel === 'canonical' && $f['canonical'] === '') {
            $f['canonical'] = s7x_seo_abs_url($href, $url);
        } elseif ($rel === 'alternate' && $l->hasAttribute('hreflang')) {
            $f['hreflang'][] = $l->getAttribute('hreflang');
        } elseif (preg_match('/\bicon\b/', $rel) && $f['favicon'] === '') {
            $f['favicon'] = s7x_seo_abs_url($href, $url);
        } elseif (strpos($rel, 'apple-touch-icon') !== false) {
            $f['apple_icon'] = true;
        } elseif ($rel === 'manifest') {
            $f['manifest'] = true;
        } elseif ($rel === 'stylesheet') {
            $f['styles']++;
        }
    }

    // Headings e hierarquia
    $last = 0;
    foreach ($xp->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
        $lvl = (int) substr($h->nodeName, 1);
        $txt = s7x_seo_text($h);
        if ($lvl === 1) {
            $f['h1'][] = $txt;
        }
        if (count($f['headings']) < 60) {
            $f['headings'][] = ['l' => $lvl, 't' => mb_substr($txt, 0, 120)];
        }
        if ($last && $lvl > $last + 1 && count($f['heading_skips']) < 5) {
            $f['heading_skips'][] = 'H' . $last . ' → H' . $lvl;
        }
        $last = $lvl;
    }

    // Dados estruturados
    foreach ($xp->query('//script[@type="application/ld+json"]') as $s) {
        $raw = trim($s->textContent);
        $j = json_decode($raw, true);
        if (!is_array($j)) {
            $f['jsonld_errors']++;
            continue;
        }
        $f['jsonld'][] = $j;
        foreach (s7x_seo_schema_types($j) as $type) {
            $f['jsonld_types'][$type] = true;
        }
    }
    $f['jsonld_types'] = array_keys($f['jsonld_types']);
    $f['microdata'] = $xp->query('//*[@itemtype]')->length > 0;

    // Estrutura
    $f['has_main'] = $xp->query('//main|//*[@role="main"]')->length > 0;
    $f['has_nav'] = $xp->query('//nav')->length > 0;
    $f['has_header'] = $xp->query('//header')->length > 0;
    $f['has_footer'] = $xp->query('//footer')->length > 0;
    $f['forms'] = $xp->query('//form')->length;
    $f['iframes'] = $xp->query('//iframe')->length;

    // Scripts (bloqueantes = no <head>, sem async/defer/module)
    foreach ($xp->query('//script') as $s) {
        /** @var DOMElement $s */
        if ($s->hasAttribute('src')) {
            $f['scripts']++;
            $inHead = $s->parentNode && $s->parentNode->nodeName === 'head';
            if ($inHead && !$s->hasAttribute('async') && !$s->hasAttribute('defer') && strtolower($s->getAttribute('type')) !== 'module') {
                $f['scripts_blocking']++;
            }
            $src = $s->getAttribute('src');
            if (stripos($url, 'https://') === 0 && stripos($src, 'http://') === 0) {
                $f['mixed'][] = $src;
            }
        }
    }

    // Imagens
    $idx = 0;
    foreach ($xp->query('//img') as $img) {
        /** @var DOMElement $img */
        $src = $img->getAttribute('src') ?: $img->getAttribute('data-src') ?: $img->getAttribute('data-lazy-src');
        if ($src === '' && $img->hasAttribute('srcset')) {
            $src = trim(explode(' ', trim(explode(',', $img->getAttribute('srcset'))[0]))[0]);
        }
        if ($src === '' || stripos($src, 'data:image/svg') === 0 && $img->getAttribute('data-src') === '') {
            continue;
        }
        $abs = stripos($src, 'data:') === 0 ? '' : s7x_seo_abs_url($src, $url);
        $ext = strtolower(pathinfo(parse_url($abs, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $inPicture = $img->parentNode && strtolower($img->parentNode->nodeName) === 'picture';
        $modern = in_array($ext, ['webp', 'avif', 'svg'], true) || preg_match('/\.(webp|avif)\b|format=(webp|avif)|fm=(webp|avif)|\/f_auto|auto=format/i', $src);
        if ($inPicture) {
            foreach ($img->parentNode->childNodes as $c) {
                if ($c instanceof DOMElement && $c->nodeName === 'source' && preg_match('#image/(webp|avif)#', $c->getAttribute('type'))) {
                    $modern = true;
                }
            }
        }
        if (stripos($url, 'https://') === 0 && stripos($abs, 'http://') === 0) {
            $f['mixed'][] = $abs;
        }
        $f['images'][] = [
            'src' => mb_substr($abs, 0, 300),
            'alt' => $img->hasAttribute('alt') ? trim($img->getAttribute('alt')) : null,
            'w' => $img->hasAttribute('width') && $img->hasAttribute('height'),
            'lazy' => strtolower($img->getAttribute('loading')) === 'lazy' || $img->hasAttribute('data-src') || $img->hasAttribute('data-lazy-src') || stripos($img->getAttribute('class'), 'lazy') !== false,
            'modern' => (bool) $modern,
            'ext' => $ext,
            'i' => $idx++,
        ];
        if ($idx > 300) {
            break;
        }
    }

    // Links
    $host = strtolower(preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)));
    foreach ($xp->query('//a[@href]') as $a) {
        /** @var DOMElement $a */
        $href = trim($a->getAttribute('href'));
        if (preg_match('#^(https?://)?(api\.)?(wa\.me|whatsapp\.com|api\.whatsapp\.com)#i', $href) || stripos($href, 'whatsapp:') === 0) {
            $f['whatsapp'] = true;
        }
        if (stripos($href, 'tel:') === 0) {
            $f['tel'] = true;
        }
        if (stripos($href, 'mailto:') === 0) {
            $f['mailto'] = true;
        }
        $abs = s7x_seo_abs_url($href, $url);
        if ($abs === '' || count($f['links']) >= 600) {
            continue;
        }
        $lh = strtolower(preg_replace('/^www\./', '', (string) parse_url($abs, PHP_URL_HOST)));
        $text = s7x_seo_text($a);
        if ($text === '') {
            foreach ($a->getElementsByTagName('img') as $ii) {
                $text = trim($ii->getAttribute('alt'));
            }
            $text = $text ?: trim($a->getAttribute('aria-label') ?: $a->getAttribute('title'));
        }
        $f['links'][] = [
            'url' => mb_substr($abs, 0, 400),
            'text' => mb_substr($text, 0, 100),
            'internal' => $lh === $host,
            'nofollow' => stripos($a->getAttribute('rel'), 'nofollow') !== false,
        ];
    }

    // Ferramentas e tecnologia (pelo HTML)
    $f['tools'] = s7x_seo_detect_tools($html);
    $f['tech'] = s7x_seo_detect_tech($html, $headers, $f['generator']);

    // Texto visível
    foreach (['script', 'style', 'noscript', 'svg', 'template', 'iframe'] as $tag) {
        foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $n) {
            $n->parentNode->removeChild($n);
        }
    }
    // Texto visível: junta os nós com espaço (evita "Bem vindoNossa empresa")
    $parts = [];
    foreach ($xp->query('//body//text()') as $tn) {
        $t = trim($tn->nodeValue);
        if ($t !== '') {
            $parts[] = $t;
        }
    }
    $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));
    $f['text'] = mb_substr($text, 0, 20000);
    $f['words'] = $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    $f['first_words'] = implode(' ', array_slice(preg_split('/\s+/u', $text), 0, 120));
    $f['text_ratio'] = $f['html_bytes'] ? round(strlen($text) / $f['html_bytes'] * 100, 1) : 0;
    $f['address_hint'] = (bool) preg_match('/\b(rua|av\.|avenida|alameda|travessa|estrada|rodovia|praça)\s+[A-ZÀ-Ú]/u', $text) || (bool) preg_match('/\b\d{5}-?\d{3}\b/', $text);
    if (!$f['tel']) {
        $f['tel'] = (bool) preg_match('/\(?\b\d{2}\)?\s?9?\d{4}[-\s]?\d{4}\b/', $text);
    }
    // Site que só aparece com JavaScript (React/Vue sem pré-renderização)
    $f['spa'] = $f['words'] < 60 && preg_match('/<div[^>]+id=["\'](root|app|__next|__nuxt)["\'][^>]*>\s*<\/div>/i', $html);
    $f['mixed'] = array_slice(array_values(array_unique($f['mixed'])), 0, 10);
    $f['verification'] = array_values(array_unique($f['verification']));
    return $f;
}

/** Tipos de schema.org de um JSON-LD (inclui @graph e itens aninhados de 1º nível). */
function s7x_seo_schema_types($j): array
{
    $out = [];
    if (!is_array($j)) {
        return $out;
    }
    if (isset($j['@type'])) {
        foreach ((array) $j['@type'] as $t) {
            if (is_string($t)) {
                $out[] = $t;
            }
        }
    }
    foreach (['@graph', 'mainEntity', 'itemListElement'] as $k) {
        if (isset($j[$k]) && is_array($j[$k])) {
            $items = isset($j[$k]['@type']) ? [$j[$k]] : $j[$k];
            foreach ($items as $it) {
                if (is_array($it) && isset($it['@type'])) {
                    $out = array_merge($out, s7x_seo_schema_types($it));
                }
            }
        }
    }
    if (array_is_list($j)) {
        foreach ($j as $it) {
            $out = array_merge($out, s7x_seo_schema_types($it));
        }
    }
    return array_values(array_unique($out));
}

if (!function_exists('array_is_list')) {
    function array_is_list(array $a): bool
    {
        return $a === [] || array_keys($a) === range(0, count($a) - 1);
    }
}

/** Ferramentas de medição e marketing instaladas. */
function s7x_seo_detect_tools(string $html): array
{
    $t = [];
    if (preg_match_all('/\b(G-[A-Z0-9]{6,12})\b/', $html, $m)) {
        $t['ga4'] = array_values(array_unique($m[1]));
    }
    if (preg_match_all('/\b(GTM-[A-Z0-9]{4,10})\b/', $html, $m)) {
        $t['gtm'] = array_values(array_unique($m[1]));
    }
    if (preg_match('/\b(UA-\d{4,10}-\d{1,3})\b/', $html, $m)) {
        $t['ua'] = [$m[1]];
    }
    if (preg_match_all('/\b(AW-\d{6,12})\b/', $html, $m)) {
        $t['ads'] = array_values(array_unique($m[1]));
    }
    if (preg_match('/fbq\(\s*[\'"]init[\'"]\s*,\s*[\'"](\d{8,20})/', $html, $m) || preg_match('/connect\.facebook\.net\/[^"\']+fbevents\.js/', $html)) {
        $t['meta_pixel'] = isset($m[1]) ? [$m[1]] : [true];
    }
    if (preg_match('/ca-pub-(\d{10,20})/', $html, $m)) {
        $t['adsense'] = ['ca-pub-' . $m[1]];
    }
    if (stripos($html, 'clarity.ms') !== false) {
        $t['clarity'] = [true];
    }
    if (stripos($html, 'hotjar') !== false) {
        $t['hotjar'] = [true];
    }
    if (stripos($html, 'analytics.tiktok.com') !== false || stripos($html, 'ttq.load') !== false) {
        $t['tiktok'] = [true];
    }
    if (stripos($html, 'snap.licdn.com') !== false) {
        $t['linkedin'] = [true];
    }
    if (stripos($html, 'rdstation') !== false) {
        $t['rdstation'] = [true];
    }
    return $t;
}

/** Plataforma do site (para o texto das recomendações). */
function s7x_seo_detect_tech(string $html, array $headers, string $generator): array
{
    $t = [];
    $h = strtolower(json_encode($headers));
    $g = strtolower($generator);
    $map = [
        'WordPress' => '/wp-content\/|wp-includes\//i',
        'Elementor' => '/elementor/i',
        'WooCommerce' => '/woocommerce/i',
        'Wix' => '/static\.wixstatic\.com|wix\.com/i',
        'Shopify' => '/cdn\.shopify\.com/i',
        'Nuvemshop' => '/nuvemshop|tiendanube/i',
        'Loja Integrada' => '/lojaintegrada/i',
        'Tray' => '/tray\.com\.br|traycorp/i',
        'VTEX' => '/vtex(assets|commerce)/i',
        'Squarespace' => '/squarespace/i',
        'Webflow' => '/webflow/i',
        'Joomla' => '/\/media\/jui\/|joomla/i',
        'Drupal' => '/drupal/i',
        'Next.js' => '/__NEXT_DATA__|\/_next\//',
        'Nuxt' => '/__NUXT__|\/_nuxt\//',
        'React' => '/data-reactroot|react-dom|<div id="root"><\/div>/i',
        'Lovable' => '/lovable|gpt-engineer/i',
        'Sev7n Xpress' => '/Sev7n Xpress/i',
    ];
    foreach ($map as $name => $re) {
        if (preg_match($re, $html)) {
            $t[] = $name;
        }
    }
    if (strpos($g, 'wordpress') !== false && !in_array('WordPress', $t, true)) {
        $t[] = 'WordPress';
    }
    if (preg_match('/wordpress\s+([\d.]+)/', $g, $m)) {
        $t['wp_version'] = $m[1];
    }
    if (strpos($h, 'cloudflare') !== false) {
        $t[] = 'Cloudflare';
    }
    if (strpos($h, 'litespeed') !== false) {
        $t[] = 'LiteSpeed';
    }
    return $t;
}
