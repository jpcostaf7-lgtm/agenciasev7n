<?php
/**
 * Analisador de SEO — avaliação: itens, notas e plano de ação
 * Autor: João Costa
 *
 * Cada item: cat, id, status (ok | aviso | erro | info), titulo, detalhe, como (como resolver),
 * peso (quanto pesa na nota da categoria), impacto (alto | medio | baixo), esforco (facil | medio | dificil).
 */
if (!defined('CMS_ROOT')) {
    exit;
}

function s7x_seo_categories(): array
{
    return [
        'indexacao'  => ['label' => 'Google encontra o site', 'short' => 'Indexação', 'peso' => 20, 'icon' => 'lupa'],
        'conteudo'   => ['label' => 'Conteúdo e palavras-chave', 'short' => 'Conteúdo', 'peso' => 18, 'icon' => 'texto'],
        'velocidade' => ['label' => 'Velocidade e Core Web Vitals', 'short' => 'Velocidade', 'peso' => 18, 'icon' => 'raio'],
        'mobile'     => ['label' => 'Celular e experiência', 'short' => 'Celular', 'peso' => 10, 'icon' => 'celular'],
        'imagens'    => ['label' => 'Imagens', 'short' => 'Imagens', 'peso' => 7, 'icon' => 'imagem'],
        'links'      => ['label' => 'Links', 'short' => 'Links', 'peso' => 6, 'icon' => 'link'],
        'seguranca'  => ['label' => 'Segurança', 'short' => 'Segurança', 'peso' => 8, 'icon' => 'escudo'],
        'aparencia'  => ['label' => 'Aparência no Google e nas redes', 'short' => 'Aparência', 'peso' => 6, 'icon' => 'olho'],
        'ia'         => ['label' => 'Busca por IA (ChatGPT, Gemini)', 'short' => 'Busca por IA', 'peso' => 5, 'icon' => 'faisca'],
        'conversao'  => ['label' => 'Medição e contato', 'short' => 'Medição', 'peso' => 4, 'icon' => 'alvo'],
    ];
}

function s7x_seo_item(string $cat, string $id, string $status, string $titulo, string $detalhe = '', string $como = '', int $peso = 2, string $impacto = 'medio', string $esforco = 'facil'): array
{
    return compact('cat', 'id', 'status', 'titulo', 'detalhe', 'como', 'peso', 'impacto', 'esforco');
}

function s7x_seo_ms(?float $ms): string
{
    if ($ms === null) {
        return '—';
    }
    return $ms >= 1000 ? number_format($ms / 1000, 1, ',', '.') . ' s' : (int) round($ms) . ' ms';
}

function s7x_seo_kb(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : number_format(max(0, $bytes) / 1024, 0, ',', '.') . ' KB';
}

function s7x_seo_plain(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);
    return preg_replace('/\s+/', ' ', $s);
}

/** Gera todos os itens a partir do relatório coletado. */
function s7x_seo_evaluate(array $R): array
{
    $I = [];
    $f = $R['page'] ?? [];
    $fetch = $R['fetch'] ?? [];
    $site = $R['site'] ?? [];
    $psiM = $R['psi']['mobile'] ?? null;
    $psiD = $R['psi']['desktop'] ?? null;
    $kw = trim((string) ($R['keyword'] ?? ''));
    $url = (string) ($R['final_url'] ?? $R['url']);
    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
    $tech = $f['tech'] ?? [];
    $isWp = in_array('WordPress', $tech, true);

    /* ---------------- Indexação ---------------- */
    $st = (int) ($fetch['status'] ?? 0);
    if ($st >= 200 && $st < 300) {
        $I[] = s7x_seo_item('indexacao', 'status', 'ok', 'A página abre normalmente', 'O servidor respondeu com o código ' . $st . '.', '', 3, 'alto');
    } else {
        $I[] = s7x_seo_item('indexacao', 'status', 'erro', 'A página respondeu com erro ' . $st, 'O Google não indexa páginas que respondem com erro.', 'Verifique com quem cuida do servidor por que a página não abre normalmente.', 5, 'alto', 'medio');
    }
    $noindex = stripos((string) ($f['robots'] ?? ''), 'noindex') !== false || stripos((string) ($fetch['headers']['x-robots-tag'] ?? ''), 'noindex') !== false;
    if ($noindex) {
        $I[] = s7x_seo_item('indexacao', 'noindex', 'erro', 'A página pede para NÃO aparecer no Google', 'Há uma instrução "noindex" na página' . (isset($fetch['headers']['x-robots-tag']) ? ' (no cabeçalho X-Robots-Tag)' : '') . '. Com ela, o Google tira a página dos resultados.', $isWp ? 'No WordPress, vá em Configurações → Leitura e desmarque "Evitar que mecanismos de busca indexem este site". Confira também o plugin de SEO.' : 'Remova a meta tag robots com "noindex" do código da página.', 8, 'alto');
    } else {
        $I[] = s7x_seo_item('indexacao', 'noindex', 'ok', 'A página está liberada para o Google', 'Nenhuma instrução "noindex" encontrada.', '', 4, 'alto');
    }
    $rob = $site['robots'] ?? [];
    if (!empty($rob['blocks_all'])) {
        $I[] = s7x_seo_item('indexacao', 'robots', 'erro', 'O robots.txt bloqueia o Google no site inteiro', 'O arquivo robots.txt tem "Disallow: /" para os robôs de busca. Nada do site é rastreado.', 'Edite o robots.txt e remova a linha "Disallow: /" do grupo "User-agent: *".', 8, 'alto');
    } elseif (!empty($rob['blocks_page'])) {
        $I[] = s7x_seo_item('indexacao', 'robots', 'erro', 'O robots.txt bloqueia esta página', 'O Google não pode rastrear o endereço analisado.', 'Revise as regras "Disallow" do robots.txt.', 6, 'alto');
    } elseif (!empty($rob['exists'])) {
        $I[] = s7x_seo_item('indexacao', 'robots', 'ok', 'robots.txt configurado', 'O arquivo existe e não bloqueia as páginas importantes.', '', 2, 'medio');
    } else {
        $I[] = s7x_seo_item('indexacao', 'robots', 'aviso', 'Não encontramos o robots.txt', 'Esse arquivo orienta os robôs de busca e indica onde está o sitemap. Sem ele, o Google rastreia tudo, mas com menos direção.', 'Crie um arquivo robots.txt na raiz do site com "User-agent: *", "Allow: /" e a linha "Sitemap:" apontando para o sitemap.', 2, 'baixo');
    }
    $sm = $site['sitemap'] ?? [];
    if (!empty($sm['found'])) {
        $n = (int) $sm['urls'];
        $txt = 'Encontrado em ' . $sm['url'] . ($n ? ' com ' . number_format($n, 0, ',', '.') . (!empty($sm['urls_partial']) ? '+' : '') . ' endereço(s)' : '') . '.';
        if (empty($sm['declared'])) {
            $I[] = s7x_seo_item('indexacao', 'sitemap', 'aviso', 'Sitemap existe, mas não está no robots.txt', $txt, 'Acrescente no robots.txt a linha "Sitemap: ' . $sm['url'] . '" e envie o sitemap no Google Search Console.', 3, 'medio');
        } elseif ($n === 0 && empty($sm['index'])) {
            $I[] = s7x_seo_item('indexacao', 'sitemap', 'aviso', 'O sitemap está vazio', $txt, 'Gere o sitemap de novo com todas as páginas públicas.', 3, 'medio');
        } else {
            $I[] = s7x_seo_item('indexacao', 'sitemap', 'ok', 'Sitemap XML encontrado', $txt, '', 3, 'medio');
        }
    } else {
        $I[] = s7x_seo_item('indexacao', 'sitemap', 'erro', 'Sem sitemap XML', 'O sitemap é a lista de páginas que você entrega ao Google. Sem ele, páginas novas demoram mais para aparecer.', $isWp ? 'Ative o sitemap no plugin de SEO (Yoast, Rank Math) ou use o nativo do WordPress em /wp-sitemap.xml. Depois envie no Google Search Console.' : 'Gere um sitemap.xml com todas as páginas e envie no Google Search Console.', 4, 'medio');
    }
    $can = (string) ($f['canonical'] ?? '');
    if ($can === '') {
        $I[] = s7x_seo_item('indexacao', 'canonical', 'aviso', 'Sem tag canonical', 'A canonical diz ao Google qual é o endereço oficial da página. Sem ela, versões com e sem "www" ou com parâmetros podem dividir a força da página.', 'Adicione <link rel="canonical" href="' . $url . '"> no <head>.', 3, 'medio');
    } elseif (rtrim(strtolower($can), '/') !== rtrim(strtolower(preg_replace('/[?#].*$/', '', $url)), '/')) {
        $I[] = s7x_seo_item('indexacao', 'canonical', 'aviso', 'A canonical aponta para outro endereço', 'Esta página indica como oficial: ' . $can . '. Se não for de propósito, o Google pode ignorar esta página.', 'Confira se a canonical deveria apontar para o próprio endereço da página.', 3, 'medio');
    } else {
        $I[] = s7x_seo_item('indexacao', 'canonical', 'ok', 'Tag canonical correta', $can, '', 3, 'medio');
    }
    $var = $site['variants'] ?? [];
    if (($var['http_to_https'] ?? null) === false) {
        $I[] = s7x_seo_item('indexacao', 'http', 'erro', 'A versão sem cadeado (http://) não leva para a com cadeado', 'Quem digita o endereço sem "https" fica na versão insegura, e o Google enxerga dois sites iguais.', 'Configure o redirecionamento 301 de http:// para https:// no servidor ou no painel da hospedagem.', 4, 'alto');
    }
    if (($var['alt_ok'] ?? null) === false) {
        $I[] = s7x_seo_item('indexacao', 'www', 'aviso', 'Com e sem "www" abrem como sites separados', $var['alt_host'] . ' não redireciona para ' . parse_url($url, PHP_URL_HOST) . '.', 'Escolha uma versão (com ou sem www) e redirecione a outra com 301.', 3, 'medio');
    } elseif (($var['alt_ok'] ?? null) === true) {
        $I[] = s7x_seo_item('indexacao', 'www', 'ok', 'Endereço único (com e sem "www")', 'As duas versões levam para o mesmo endereço.', '', 1, 'baixo');
    }
    $chain = $fetch['chain'] ?? [];
    if (count($chain) > 2) {
        $I[] = s7x_seo_item('indexacao', 'redirects', 'aviso', 'Muitos redirecionamentos até a página', (count($chain) - 1) . ' saltos: ' . implode(' → ', array_map(fn ($c) => $c['status'], $chain)) . '. Cada salto atrasa a abertura.', 'Faça o endereço antigo apontar direto para o final.', 2, 'baixo');
    }
    if (!empty($f['spa'])) {
        $I[] = s7x_seo_item('indexacao', 'spa', 'erro', 'O conteúdo só aparece depois do JavaScript', 'O HTML que chega ao Google está praticamente vazio: o site monta tudo no navegador (React, Vue ou criadores com IA). O Google demora mais para ler e muitas IAs nem leem.', 'Use pré-renderização ou renderização no servidor (SSR), ou migre para uma plataforma que entregue o HTML pronto.', 6, 'alto', 'dificil');
    }
    if (!empty($R['subpages'])) {
        $ni = array_filter($R['subpages'], fn ($s) => !empty($s['noindex']));
        if ($ni) {
            $I[] = s7x_seo_item('indexacao', 'sub_noindex', 'aviso', count($ni) . ' página(s) internas com "noindex"', implode(' · ', array_map(fn ($s) => s7x_seo_short_url($s['url']), $ni)), 'Confira se essas páginas deveriam mesmo ficar fora do Google.', 2, 'medio');
        }
    }

    /* ---------------- Conteúdo ---------------- */
    $title = (string) ($f['title'] ?? '');
    $tl = mb_strlen($title);
    if ($title === '') {
        $I[] = s7x_seo_item('conteudo', 'title', 'erro', 'A página não tem título (title)', 'O título é a linha azul que aparece no Google. É um dos fatores que mais pesam.', 'Escreva um título de 50 a 60 caracteres com o serviço principal e a cidade/marca.', 6, 'alto');
    } elseif ($tl > 65) {
        $I[] = s7x_seo_item('conteudo', 'title', 'aviso', 'Título longo demais (' . $tl . ' caracteres)', '"' . $title . '" — o Google corta depois de uns 60 caracteres.', 'Resuma para até 60 caracteres, com o mais importante no começo.', 4, 'medio');
    } elseif ($tl < 25) {
        $I[] = s7x_seo_item('conteudo', 'title', 'aviso', 'Título curto demais (' . $tl . ' caracteres)', '"' . $title . '" — sobra espaço para dizer o que você faz.', 'Use de 50 a 60 caracteres: serviço + diferencial + cidade ou marca.', 4, 'medio');
    } else {
        $I[] = s7x_seo_item('conteudo', 'title', 'ok', 'Título com bom tamanho (' . $tl . ' caracteres)', '"' . $title . '"', '', 4, 'alto');
    }
    $desc = (string) ($f['description'] ?? '');
    $dl = mb_strlen($desc);
    if ($desc === '') {
        $I[] = s7x_seo_item('conteudo', 'description', 'erro', 'Sem meta description', 'É o texto que aparece embaixo do título no Google. Sem ela, o Google escolhe um trecho qualquer da página.', 'Escreva de 140 a 160 caracteres que convençam a pessoa a clicar, com uma chamada para ação.', 4, 'alto');
    } elseif ($dl > 165) {
        $I[] = s7x_seo_item('conteudo', 'description', 'aviso', 'Meta description longa (' . $dl . ' caracteres)', 'O Google corta perto dos 160 caracteres.', 'Resuma para até 160 caracteres.', 2, 'baixo');
    } elseif ($dl < 90) {
        $I[] = s7x_seo_item('conteudo', 'description', 'aviso', 'Meta description curta (' . $dl . ' caracteres)', '"' . $desc . '"', 'Aproveite de 140 a 160 caracteres para convencer a pessoa a clicar.', 2, 'medio');
    } else {
        $I[] = s7x_seo_item('conteudo', 'description', 'ok', 'Meta description com bom tamanho', '"' . $desc . '"', '', 3, 'medio');
    }
    $h1 = $f['h1'] ?? [];
    if (!$h1) {
        $I[] = s7x_seo_item('conteudo', 'h1', 'erro', 'Sem título principal (H1)', 'O H1 diz ao Google qual é o assunto da página.', 'Transforme o título principal da página em H1 (um só por página).', 4, 'alto');
    } elseif (count($h1) > 1) {
        $I[] = s7x_seo_item('conteudo', 'h1', 'aviso', count($h1) . ' títulos H1 na mesma página', implode(' · ', array_map(fn ($h) => '"' . mb_strimwidth($h, 0, 60, '…') . '"', array_slice($h1, 0, 4))), 'Deixe apenas um H1 e transforme os outros em H2.', 2, 'medio');
    } elseif (trim($h1[0]) === '') {
        $I[] = s7x_seo_item('conteudo', 'h1', 'aviso', 'O H1 está vazio (ou é só uma imagem sem texto)', '', 'Coloque texto no H1, ou um alt descritivo na imagem.', 3, 'medio');
    } else {
        $I[] = s7x_seo_item('conteudo', 'h1', 'ok', 'Um H1 bem definido', '"' . mb_strimwidth($h1[0], 0, 90, '…') . '"', '', 3, 'alto');
    }
    if (!empty($f['heading_skips'])) {
        $I[] = s7x_seo_item('conteudo', 'headings', 'aviso', 'Títulos fora de ordem', 'Saltos encontrados: ' . implode(', ', $f['heading_skips']) . '. Isso confunde leitores de tela e a leitura da estrutura.', 'Use os títulos em sequência: H1, depois H2, depois H3.', 1, 'baixo');
    } elseif (count($f['headings'] ?? []) >= 3) {
        $I[] = s7x_seo_item('conteudo', 'headings', 'ok', 'Estrutura de títulos organizada', count($f['headings']) . ' títulos (H1–H6) em ordem.', '', 1, 'baixo');
    }
    $w = (int) ($f['words'] ?? 0);
    if ($w < 250) {
        $I[] = s7x_seo_item('conteudo', 'words', $w < 100 ? 'erro' : 'aviso', 'Pouco texto na página (' . $w . ' palavras)', 'O Google precisa de texto para entender e ranquear a página. Páginas com pouco conteúdo raramente aparecem bem.', 'Explique seus serviços, para quem, onde e por que escolher você. Mire em 500+ palavras nas páginas principais.', 4, 'alto', 'medio');
    } else {
        $I[] = s7x_seo_item('conteudo', 'words', 'ok', 'Quantidade de texto boa (' . number_format($w, 0, ',', '.') . ' palavras)', '', '', 3, 'medio');
    }
    if (empty($f['lang'])) {
        $I[] = s7x_seo_item('conteudo', 'lang', 'aviso', 'Idioma da página não informado', 'Falta lang="pt-BR" na tag <html>.', 'Adicione <html lang="pt-BR">.', 1, 'baixo');
    }
    if ($kw !== '') {
        $k = s7x_seo_plain($kw);
        // Conta como presente a frase exata ou todas as palavras importantes (em qualquer ordem)
        $stop = ['de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas', 'para', 'por', 'com', 'e', 'o', 'a', 'os', 'as', 'um', 'uma'];
        $words = array_values(array_filter(explode(' ', $k), fn ($w) => mb_strlen($w) > 1 && !in_array($w, $stop, true)));
        $has = function (string $s) use ($k, $words) {
            if ($s === '') {
                return false;
            }
            $p = s7x_seo_plain($s);
            if (strpos($p, $k) !== false) {
                return true;
            }
            foreach ($words as $w) {
                if (!preg_match('/\b' . preg_quote($w, '/') . '/u', $p)) {
                    return false;
                }
            }
            return (bool) $words;
        };
        $text = s7x_seo_plain((string) ($f['text'] ?? ''));
        $occ = $k !== '' ? substr_count($text, $k) : 0;
        $density = $w ? round($occ * max(1, count(explode(' ', $k))) / $w * 100, 1) : 0;
        $places = [
            'no título' => $has($title), 'no H1' => $has(implode(' ', $h1)), 'na meta description' => $has($desc),
            'no começo do texto' => $has((string) ($f['first_words'] ?? '')), 'em um H2/H3' => $has(implode(' ', array_column(array_filter($f['headings'] ?? [], fn ($h) => $h['l'] > 1), 't'))),
            'no endereço (URL)' => $has(str_replace(['-', '_', '/'], ' ', urldecode($path))),
        ];
        $miss = array_keys(array_filter($places, fn ($v) => !$v));
        $okp = array_keys(array_filter($places));
        if (!$places['no título'] || !$places['no H1']) {
            $I[] = s7x_seo_item('conteudo', 'kw_main', 'erro', '"' . $kw . '" não está ' . (!$places['no título'] ? 'no título' : 'no H1'), 'Para ranquear por um termo, ele precisa aparecer no título e no H1.', 'Inclua "' . $kw . '" de forma natural no título (title) e no H1.', 5, 'alto');
        } else {
            $I[] = s7x_seo_item('conteudo', 'kw_main', 'ok', '"' . $kw . '" aparece no título e no H1', '', '', 5, 'alto');
        }
        $I[] = s7x_seo_item('conteudo', 'kw_places', $miss ? 'aviso' : 'ok', $miss ? 'Faltam lugares estratégicos para "' . $kw . '"' : 'Palavra-chave bem distribuída', ($okp ? 'Aparece ' . implode(', ', $okp) . '. ' : '') . ($miss ? 'Falta ' . implode(', ', $miss) . '.' : ''), $miss ? 'Use o termo (ou variações) ' . implode(', ', $miss) . '.' : '', 3, 'medio');
        if ($occ === 0 && $has((string) ($f['text'] ?? ''))) {
            $I[] = s7x_seo_item('conteudo', 'kw_density', 'aviso', 'A frase exata "' . $kw . '" não aparece no texto', 'As palavras estão na página, mas não juntas. Usar a frase completa ao menos uma vez ajuda o Google a ligar a página a essa busca.', 'Inclua "' . $kw . '" de forma natural em um parágrafo ou subtítulo.', 2, 'medio');
        } elseif ($occ === 0) {
            $I[] = s7x_seo_item('conteudo', 'kw_density', 'erro', '"' . $kw . '" não aparece no texto', '', 'Escreva sobre o termo no corpo da página.', 3, 'alto', 'medio');
        } elseif ($density > 3.5) {
            $I[] = s7x_seo_item('conteudo', 'kw_density', 'aviso', 'Palavra-chave repetida demais (' . $density . '%)', 'Aparece ' . $occ . ' vezes. Exagero pode parecer manipulação para o Google.', 'Troque algumas repetições por sinônimos.', 2, 'medio');
        } else {
            $I[] = s7x_seo_item('conteudo', 'kw_density', 'ok', 'Frequência natural da palavra-chave', 'Aparece ' . $occ . ' vez(es) (' . $density . '% do texto).', '', 2, 'baixo');
        }
    }
    if (!empty($R['subpages'])) {
        $titles = [];
        $descs = [];
        foreach (array_merge([['url' => $url, 'title' => $title, 'description' => $desc]], $R['subpages']) as $s) {
            if (($s['title'] ?? '') !== '') {
                $titles[mb_strtolower($s['title'])][] = $s['url'];
            }
            if (($s['description'] ?? '') !== '') {
                $descs[mb_strtolower($s['description'])][] = $s['url'];
            }
        }
        $dupT = array_filter($titles, fn ($v) => count($v) > 1);
        $dupD = array_filter($descs, fn ($v) => count($v) > 1);
        $noDesc = array_filter($R['subpages'], fn ($s) => ($s['status'] ?? 0) === 200 && ($s['description'] ?? '') === '');
        $thin = array_filter($R['subpages'], fn ($s) => ($s['status'] ?? 0) === 200 && ($s['words'] ?? 0) < 200);
        if ($dupT) {
            $I[] = s7x_seo_item('conteudo', 'dup_title', 'aviso', 'Páginas diferentes com o mesmo título', count($dupT) . ' título(s) repetido(s), por exemplo "' . mb_strimwidth((string) array_key_first($dupT), 0, 70, '…') . '".', 'Dê um título único para cada página, dizendo o assunto dela.', 3, 'medio');
        } else {
            $I[] = s7x_seo_item('conteudo', 'dup_title', 'ok', 'Cada página tem um título próprio', count($R['subpages']) + 1 . ' páginas conferidas.', '', 2, 'medio');
        }
        if ($dupD || $noDesc) {
            $I[] = s7x_seo_item('conteudo', 'sub_desc', 'aviso', 'Descrições ausentes ou repetidas nas páginas internas', ($noDesc ? count($noDesc) . ' sem description. ' : '') . ($dupD ? count($dupD) . ' descrição(ões) repetida(s).' : ''), 'Escreva uma meta description única para cada página.', 2, 'medio');
        }
        if (count($thin) >= 2) {
            $I[] = s7x_seo_item('conteudo', 'sub_thin', 'aviso', count($thin) . ' páginas internas com pouco texto', implode(' · ', array_map(fn ($s) => s7x_seo_short_url($s['url']) . ' (' . (int) $s['words'] . ')', array_slice($thin, 0, 5))), 'Enriqueça essas páginas com texto útil sobre o assunto de cada uma.', 2, 'medio', 'medio');
        }
    }

    /* ---------------- Velocidade ---------------- */
    $ttfb = (int) ($fetch['ttfb'] ?? 0);
    if ($psiM && !empty($psiM['ok'])) {
        $sc = (int) ($psiM['scores']['performance'] ?? 0);
        $I[] = s7x_seo_item('velocidade', 'psi_mobile', $sc >= 90 ? 'ok' : ($sc >= 50 ? 'aviso' : 'erro'), 'Nota de velocidade no celular: ' . $sc . '/100', 'Medida pelo Google PageSpeed Insights (Lighthouse) num celular com internet 4G.', $sc < 90 ? 'Veja abaixo as oportunidades que o Google apontou.' : '', 6, 'alto', 'medio');
        if ($psiD && !empty($psiD['ok'])) {
            $sd = (int) ($psiD['scores']['performance'] ?? 0);
            $I[] = s7x_seo_item('velocidade', 'psi_desktop', $sd >= 90 ? 'ok' : ($sd >= 50 ? 'aviso' : 'erro'), 'Nota de velocidade no computador: ' . $sd . '/100', '', '', 2, 'medio', 'medio');
        }
        $fd = $psiM['field'] ?? null;
        $lab = $psiM['lab'] ?? [];
        // LCP
        $lcp = $fd['lcp']['v'] ?? $lab['lcp']['v'] ?? null;
        $src = isset($fd['lcp']) ? 'dados reais dos visitantes' : 'teste do Google';
        if ($lcp !== null) {
            $I[] = s7x_seo_item('velocidade', 'lcp', $lcp <= 2500 ? 'ok' : ($lcp <= 4000 ? 'aviso' : 'erro'), 'LCP (conteúdo principal aparece em): ' . s7x_seo_ms($lcp), 'Tempo até o maior elemento da tela (geralmente a foto ou o título principal) aparecer. Meta do Google: até 2,5 s. Fonte: ' . $src . '.', $lcp > 2500 ? 'Comprima e converta a imagem principal para WebP, evite slides pesados no topo e use cache.' : '', 4, 'alto', 'medio');
        }
        $inp = $fd['inp']['v'] ?? null;
        if ($inp !== null) {
            $I[] = s7x_seo_item('velocidade', 'inp', $inp <= 200 ? 'ok' : ($inp <= 500 ? 'aviso' : 'erro'), 'INP (resposta ao toque): ' . s7x_seo_ms($inp), 'Quanto o site demora para reagir quando a pessoa toca ou clica. Meta: até 200 ms. Fonte: dados reais dos visitantes.', $inp > 200 ? 'Reduza scripts pesados (chats, pixels, sliders) e adie o que não é essencial.' : '', 3, 'alto', 'dificil');
        } elseif (isset($lab['tbt']['v'])) {
            $tbt = $lab['tbt']['v'];
            $I[] = s7x_seo_item('velocidade', 'tbt', $tbt <= 200 ? 'ok' : ($tbt <= 600 ? 'aviso' : 'erro'), 'Tempo de bloqueio: ' . s7x_seo_ms($tbt), 'Tempo em que a página fica "travada" carregando scripts e não responde ao toque. Meta: até 200 ms.', $tbt > 200 ? 'Remova plugins e scripts que não usa e adie os demais (defer/async).' : '', 3, 'alto', 'dificil');
        }
        $cls = $fd['cls']['v'] ?? $lab['cls']['v'] ?? null;
        if ($cls !== null) {
            $I[] = s7x_seo_item('velocidade', 'cls', $cls <= 0.1 ? 'ok' : ($cls <= 0.25 ? 'aviso' : 'erro'), 'CLS (estabilidade visual): ' . number_format((float) $cls, 2, ',', ''), 'Mede o quanto a página "pula" enquanto carrega. Meta: até 0,1.', $cls > 0.1 ? 'Defina largura e altura nas imagens e reserve espaço para banners e anúncios.' : '', 3, 'medio', 'medio');
        }
        if (!empty($psiM['bytes'])) {
            $b = (int) $psiM['bytes'];
            $I[] = s7x_seo_item('velocidade', 'weight', $b <= 2 * 1048576 ? 'ok' : ($b <= 5 * 1048576 ? 'aviso' : 'erro'), 'Peso total da página: ' . s7x_seo_kb($b), ($psiM['requests'] ? $psiM['requests'] . ' arquivos carregados. ' : '') . 'Quanto mais pesado, mais demora no 4G e mais consome o plano de dados.', $b > 2 * 1048576 ? 'Comprima imagens e vídeos e retire o que não é usado.' : '', 2, 'medio', 'medio');
        }
    } else {
        // Sem PageSpeed: avalia pelo que medimos
        $total = (int) ($fetch['total'] ?? 0);
        $I[] = s7x_seo_item('velocidade', 'psi_mobile', 'info', 'Teste completo de velocidade indisponível agora', (string) ($psiM['error'] ?? 'O Google PageSpeed não respondeu.') . ' A avaliação abaixo usa o tempo medido pelo nosso servidor.', '', 0, 'medio');
        $I[] = s7x_seo_item('velocidade', 'total', $total <= 1500 ? 'ok' : ($total <= 3500 ? 'aviso' : 'erro'), 'HTML entregue em ' . s7x_seo_ms($total), 'Tempo para baixar o código da página (sem imagens e scripts).', $total > 1500 ? 'Ative cache de página e verifique a hospedagem.' : '', 4, 'alto', 'medio');
    }
    if ($ttfb) {
        $I[] = s7x_seo_item('velocidade', 'ttfb', $ttfb <= 800 ? 'ok' : ($ttfb <= 1800 ? 'aviso' : 'erro'), 'Resposta do servidor: ' . s7x_seo_ms($ttfb), 'Tempo até o servidor começar a enviar a página (TTFB). Meta: até 0,8 s.', $ttfb > 800 ? 'Use cache de página (LiteSpeed Cache, WP Rocket), atualize o PHP ou troque por uma hospedagem mais rápida.' : '', 3, 'alto', 'medio');
    }
    $enc = (string) ($fetch['encoding'] ?? '');
    if ($enc === '' && (int) ($f['html_bytes'] ?? 0) > 5000) {
        $I[] = s7x_seo_item('velocidade', 'gzip', 'erro', 'Compressão desativada', 'O servidor envia o HTML sem compressão (gzip/brotli). Ativar reduz o tamanho em até 70%.', 'Ative gzip ou brotli no servidor (no cPanel: Otimizar site → Compactar todo o conteúdo).', 3, 'medio');
    } elseif ($enc !== '') {
        $I[] = s7x_seo_item('velocidade', 'gzip', 'ok', 'Compressão ativa (' . $enc . ')', '', '', 2, 'medio');
    }
    if ((int) ($f['scripts_blocking'] ?? 0) >= 3) {
        $I[] = s7x_seo_item('velocidade', 'blocking', 'aviso', $f['scripts_blocking'] . ' scripts travando o carregamento', 'Scripts no <head> sem async/defer seguram a exibição da página.', 'Adicione defer nos scripts que não são essenciais para o primeiro carregamento.', 2, 'medio', 'medio');
    }

    /* ---------------- Celular ---------------- */
    $vp = (string) ($f['viewport'] ?? '');
    if ($vp === '') {
        $I[] = s7x_seo_item('mobile', 'viewport', 'erro', 'O site não se adapta ao celular', 'Falta a meta tag viewport. No celular, a página aparece miniatura. O Google usa a versão de celular para ranquear.', 'Adicione <meta name="viewport" content="width=device-width, initial-scale=1"> e ajuste o layout.', 6, 'alto', 'medio');
    } else {
        $I[] = s7x_seo_item('mobile', 'viewport', 'ok', 'Preparado para celular (viewport)', '', '', 4, 'alto');
        if (preg_match('/user-scalable\s*=\s*(no|0)|maximum-scale\s*=\s*1(\.0)?\b/i', $vp)) {
            $I[] = s7x_seo_item('mobile', 'zoom', 'aviso', 'O zoom está bloqueado no celular', 'Pessoas com dificuldade de visão não conseguem ampliar a página.', 'Retire "user-scalable=no" e "maximum-scale=1" da meta viewport.', 1, 'baixo');
        }
    }
    if ($psiM && !empty($psiM['ok']) && isset($psiM['scores']['accessibility'])) {
        $a11 = (int) $psiM['scores']['accessibility'];
        $fails = array_column($psiM['failed_a11y'] ?? [], 'title');
        $I[] = s7x_seo_item('mobile', 'a11y', $a11 >= 90 ? 'ok' : ($a11 >= 70 ? 'aviso' : 'erro'), 'Acessibilidade: ' . $a11 . '/100', $fails ? 'Pontos a corrigir: ' . implode('; ', array_slice($fails, 0, 4)) . '.' : 'Avaliação do Google Lighthouse.', $a11 < 90 ? 'Corrija contraste de cores, textos alternativos e rótulos de botões.' : '', 3, 'medio', 'medio');
    }
    if (empty($f['favicon'])) {
        $I[] = s7x_seo_item('mobile', 'favicon', 'aviso', 'Sem ícone do site (favicon)', 'O favicon aparece na aba do navegador e ao lado do site nos resultados do Google no celular.', 'Adicione um ícone quadrado (512×512) com a sua marca.', 2, 'baixo');
    } else {
        $I[] = s7x_seo_item('mobile', 'favicon', 'ok', 'Ícone do site (favicon) configurado', '', '', 1, 'baixo');
    }
    if (!empty($f['amp'])) {
        $I[] = s7x_seo_item('mobile', 'amp', 'info', 'Página em AMP', 'O Google não dá mais vantagem para AMP. Pode ser simplificado.', '', 0, 'baixo');
    }

    /* ---------------- Imagens ---------------- */
    $imgs = $f['images'] ?? [];
    $nImg = count($imgs);
    if ($nImg) {
        $noAlt = array_filter($imgs, fn ($i) => $i['alt'] === null);
        $emptyAlt = array_filter($imgs, fn ($i) => $i['alt'] === '');
        if ($noAlt) {
            $I[] = s7x_seo_item('imagens', 'alt', count($noAlt) > 5 ? 'erro' : 'aviso', count($noAlt) . ' de ' . $nImg . ' imagens sem texto alternativo (alt)', 'O alt descreve a imagem para o Google Imagens e para leitores de tela.', 'Descreva cada imagem em poucas palavras (ex.: "fachada da clínica em Copacabana"). Imagens só decorativas podem ter alt vazio.', 4, 'medio');
        } else {
            $I[] = s7x_seo_item('imagens', 'alt', 'ok', 'Todas as imagens têm texto alternativo', $emptyAlt ? count($emptyAlt) . ' marcadas como decorativas (alt vazio).' : '', '', 4, 'medio');
        }
        $old = array_filter($imgs, fn ($i) => !$i['modern'] && in_array($i['ext'], ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true));
        if (count($old) >= 3 && count($old) >= $nImg * 0.4) {
            $I[] = s7x_seo_item('imagens', 'format', 'aviso', count($old) . ' imagens em formato antigo (JPG/PNG)', 'WebP e AVIF deixam as fotos de 25% a 50% mais leves, sem perder qualidade.', $isWp ? 'Use um plugin de otimização (Converter for Media, ShortPixel, LiteSpeed) para servir WebP.' : 'Converta as imagens para WebP.', 3, 'medio');
        } else {
            $I[] = s7x_seo_item('imagens', 'format', 'ok', 'Imagens em formatos modernos', '', '', 2, 'medio');
        }
        $noDim = array_filter($imgs, fn ($i) => !$i['w']);
        if (count($noDim) >= 3) {
            $I[] = s7x_seo_item('imagens', 'dims', 'aviso', count($noDim) . ' imagens sem largura e altura definidas', 'Sem essas medidas, a página "pula" enquanto as imagens carregam (afeta o CLS).', 'Informe width e height nas tags <img>.', 2, 'medio');
        }
        if ($nImg >= 6) {
            $below = array_filter($imgs, fn ($i) => $i['i'] >= 3);
            $lazy = array_filter($below, fn ($i) => $i['lazy']);
            if ($below && count($lazy) < count($below) * 0.5) {
                $I[] = s7x_seo_item('imagens', 'lazy', 'aviso', 'Imagens fora da tela carregam todas de uma vez', 'Só ' . count($lazy) . ' de ' . count($below) . ' imagens abaixo do topo usam carregamento tardio (lazy).', 'Use loading="lazy" nas imagens que não aparecem na primeira tela.', 2, 'medio');
            } else {
                $I[] = s7x_seo_item('imagens', 'lazy', 'ok', 'Imagens com carregamento tardio (lazy)', '', '', 1, 'baixo');
            }
        }
        $big = array_filter($R['image_sizes'] ?? [], fn ($b) => $b > 350 * 1024);
        if ($big) {
            arsort($big);
            $I[] = s7x_seo_item('imagens', 'heavy', count($big) >= 3 ? 'erro' : 'aviso', count($big) . ' imagem(ns) pesada(s) demais', implode(' · ', array_map(fn ($u, $b) => basename(parse_url($u, PHP_URL_PATH) ?: $u) . ' (' . s7x_seo_kb($b) . ')', array_slice(array_keys($big), 0, 4), array_slice($big, 0, 4))), 'Redimensione para no máximo 1600 px de largura e comprima (fotos devem ter menos de 200 KB).', 4, 'alto');
        } elseif (!empty($R['image_sizes'])) {
            $I[] = s7x_seo_item('imagens', 'heavy', 'ok', 'Imagens com peso adequado', count($R['image_sizes']) . ' imagens conferidas.', '', 2, 'medio');
        }
    } else {
        $I[] = s7x_seo_item('imagens', 'none', 'info', 'Nenhuma imagem encontrada no HTML', 'As imagens podem estar no CSS ou ser carregadas por JavaScript.', '', 0, 'baixo');
    }

    /* ---------------- Links ---------------- */
    $links = $f['links'] ?? [];
    $int = array_filter($links, fn ($l) => $l['internal']);
    $intU = array_unique(array_map(fn ($l) => rtrim(preg_replace('/[?#].*$/', '', $l['url']), '/'), $int));
    if (count($intU) < 5) {
        $I[] = s7x_seo_item('links', 'internal', 'aviso', 'Poucos links internos (' . count($intU) . ')', 'Links entre as suas páginas ajudam o Google a encontrar e valorizar o conteúdo.', 'Ligue as páginas entre si: serviços, sobre, contato, artigos relacionados.', 3, 'medio');
    } else {
        $I[] = s7x_seo_item('links', 'internal', 'ok', count($intU) . ' links internos', '', '', 2, 'medio');
    }
    $generic = array_filter($links, fn ($l) => preg_match('/^(clique aqui|saiba mais|leia mais|aqui|veja mais|mais|click here|read more|ver mais|acesse)$/iu', trim($l['text'])) || trim($l['text']) === '');
    if (count($generic) >= 4) {
        $empty = count(array_filter($generic, fn ($l) => trim($l['text']) === ''));
        $I[] = s7x_seo_item('links', 'anchors', 'aviso', count($generic) . ' links sem descrição clara', ($empty ? $empty . ' sem texto nenhum (ícones sem rótulo). ' : '') . 'Textos como "clique aqui" ou "saiba mais" não dizem ao Google para onde o link leva.', 'Use textos que descrevam o destino ("ver preços de manutenção") e aria-label nos ícones.', 2, 'baixo');
    }
    $broken = $R['broken'] ?? null;
    if (is_array($broken)) {
        if ($broken) {
            $I[] = s7x_seo_item('links', 'broken', 'erro', count($broken) . ' link(s) quebrado(s)', implode(' · ', array_map(fn ($b) => s7x_seo_short_url($b['url']) . ' (' . $b['status'] . ')', array_slice($broken, 0, 6))), 'Corrija ou remova esses links. Links quebrados frustram o visitante e desperdiçam o rastreio do Google.', 4, 'alto');
        } else {
            $I[] = s7x_seo_item('links', 'broken', 'ok', 'Nenhum link quebrado', (int) ($R['links_checked'] ?? 0) . ' links conferidos.', '', 4, 'alto');
        }
    }
    $nofInt = array_filter($int, fn ($l) => $l['nofollow']);
    if (count($nofInt) >= 3) {
        $I[] = s7x_seo_item('links', 'nofollow', 'aviso', count($nofInt) . ' links internos com "nofollow"', 'O nofollow em links do próprio site impede que o Google siga para essas páginas.', 'Retire rel="nofollow" dos links internos.', 2, 'medio');
    }

    /* ---------------- Segurança ---------------- */
    $https = stripos($url, 'https://') === 0;
    if (!$https) {
        $I[] = s7x_seo_item('seguranca', 'https', 'erro', 'Site sem cadeado (sem HTTPS)', 'O Chrome mostra "Não seguro" e o Google dá preferência a sites com HTTPS.', 'Ative o certificado SSL gratuito (Let\'s Encrypt / AutoSSL) na hospedagem.', 8, 'alto');
    } elseif (($fetch['ssl_ok'] ?? null) === false) {
        $I[] = s7x_seo_item('seguranca', 'https', 'erro', 'Certificado de segurança com problema', (string) ($fetch['ssl_error'] ?? 'O certificado é inválido, expirou ou não cobre este domínio.') . ' Visitantes veem um alerta de site perigoso.', 'Renove ou reinstale o certificado SSL na hospedagem.', 8, 'alto');
    } else {
        $I[] = s7x_seo_item('seguranca', 'https', 'ok', 'Cadeado ativo (HTTPS válido)', '', '', 6, 'alto');
    }
    if (!empty($f['mixed'])) {
        $I[] = s7x_seo_item('seguranca', 'mixed', 'aviso', 'Arquivos sem cadeado dentro da página', count($f['mixed']) . ' arquivo(s) carregados por http://, ex.: ' . s7x_seo_short_url($f['mixed'][0]) . '. O navegador pode bloquear ou tirar o cadeado.', 'Troque esses endereços por https://.', 3, 'medio');
    }
    $sec = $site['security'] ?? [];
    if ($sec) {
        $miss = array_filter($sec, fn ($s) => !$s['ok']);
        $okN = count($sec) - count($miss);
        $I[] = s7x_seo_item('seguranca', 'headers', $okN >= 4 ? 'ok' : ($okN >= 2 ? 'aviso' : 'erro'), 'Proteções do servidor: ' . $okN . ' de ' . count($sec), $miss ? 'Faltando: ' . implode(', ', array_column($miss, 'label')) . '.' : 'Todos os cabeçalhos de segurança principais estão ativos.', $miss ? 'Configure os cabeçalhos de segurança no servidor (.htaccess ou Cloudflare).' : '', 2, 'baixo', 'medio');
    }
    $xp = (string) ($fetch['headers']['x-powered-by'] ?? '');
    $wpv = $tech['wp_version'] ?? '';
    if ($wpv !== '' || preg_match('/php\/?\s*[\d.]+/i', $xp)) {
        $I[] = s7x_seo_item('seguranca', 'exposed', 'aviso', 'O site mostra a versão do sistema', ($wpv ? 'WordPress ' . $wpv . ' aparece no código. ' : '') . ($xp ? 'O servidor informa "' . $xp . '". ' : '') . 'Isso facilita a vida de quem procura brechas conhecidas.', 'Esconda a versão (plugins de segurança fazem isso) e mantenha tudo atualizado.', 1, 'baixo');
    }

    /* ---------------- Aparência no Google e redes ---------------- */
    $og = $f['og'] ?? [];
    $ogMiss = array_diff(['title', 'description', 'image'], array_keys(array_filter($og)));
    if (empty($og['image'])) {
        $I[] = s7x_seo_item('aparencia', 'og_image', 'erro', 'Sem imagem de compartilhamento', 'Quando alguém manda o link no WhatsApp, Instagram ou LinkedIn, aparece sem foto (ou com uma imagem aleatória).', 'Crie uma imagem 1200×630 com a sua marca e configure a tag og:image.', 3, 'medio');
    } elseif ($ogMiss) {
        $I[] = s7x_seo_item('aparencia', 'og_image', 'aviso', 'Prévia de compartilhamento incompleta', 'Faltam: og:' . implode(', og:', $ogMiss) . '.', 'Complete as tags Open Graph.', 2, 'baixo');
    } else {
        $I[] = s7x_seo_item('aparencia', 'og_image', 'ok', 'Prévia bonita no WhatsApp e redes sociais', 'Título, descrição e imagem de compartilhamento configurados.', '', 3, 'medio');
    }
    $types = $f['jsonld_types'] ?? [];
    $biz = array_intersect($types, ['Organization', 'LocalBusiness', 'ProfessionalService', 'Corporation', 'Store', 'Restaurant', 'MedicalBusiness', 'MedicalClinic', 'Dentist', 'Physician', 'LegalService', 'Attorney', 'AccountingService', 'RealEstateAgent', 'HomeAndConstructionBusiness', 'AutoRepair', 'BeautySalon', 'HealthAndBeautyBusiness', 'EducationalOrganization', 'SportsActivityLocation', 'ExerciseGym', 'Hotel', 'LodgingBusiness', 'FoodEstablishment', 'Person']);
    if (!$types && empty($f['microdata'])) {
        $I[] = s7x_seo_item('aparencia', 'schema', 'erro', 'Sem dados estruturados (Schema.org)', 'São informações "escondidas" que ajudam o Google a mostrar endereço, telefone, avaliações, perguntas frequentes e preços direto nos resultados.', 'Adicione JSON-LD do tipo ' . (!empty($f['address_hint']) ? 'LocalBusiness' : 'Organization') . ' com nome, logo, telefone e endereço.', 4, 'medio', 'medio');
    } else {
        $I[] = s7x_seo_item('aparencia', 'schema', ($f['jsonld_errors'] ?? 0) ? 'aviso' : 'ok', 'Dados estruturados encontrados', 'Tipos: ' . ($types ? implode(', ', array_slice($types, 0, 8)) : 'microdados') . '.' . (($f['jsonld_errors'] ?? 0) ? ' ' . $f['jsonld_errors'] . ' bloco(s) com erro de sintaxe.' : ''), ($f['jsonld_errors'] ?? 0) ? 'Corrija o JSON-LD com erro (teste em search.google.com/test/rich-results).' : '', 4, 'medio');
        if (!$biz) {
            $I[] = s7x_seo_item('aparencia', 'schema_biz', 'aviso', 'Falta identificar a empresa nos dados estruturados', 'Nenhum schema de empresa (Organization/LocalBusiness).', 'Adicione um schema Organization ou LocalBusiness com nome, logo, telefone e endereço.', 2, 'medio');
        }
    }
    if (!empty($R['subpages']) || $tl) {
        // Prévia no Google (sempre informativa; a nota vem do título/description)
    }

    /* ---------------- Busca por IA ---------------- */
    $aiBlocked = $rob['ai_blocked'] ?? [];
    if (!empty($rob['blocks_all'])) {
        // já contado na indexação
    } elseif ($aiBlocked) {
        $search = array_intersect_key($aiBlocked, array_flip(['OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'PerplexityBot', 'Bingbot']));
        $I[] = s7x_seo_item('ia', 'ai_bots', $search ? 'erro' : 'aviso', 'Robôs de IA bloqueados: ' . implode(', ', array_values($aiBlocked)), $search ? 'Com esse bloqueio, o site não aparece quando alguém pergunta ao ChatGPT ou ao Perplexity.' : 'Esses robôs coletam dados para treinar IA. Bloquear é uma escolha, mas reduz a chance de a marca ser citada.', 'Se quiser ser recomendado por IAs, libere esses robôs no robots.txt.', 3, 'medio');
    } else {
        $I[] = s7x_seo_item('ia', 'ai_bots', 'ok', 'Robôs de IA liberados', 'ChatGPT, Claude, Gemini e Perplexity podem ler o site.', '', 3, 'medio');
    }
    $I[] = s7x_seo_item('ia', 'llms', !empty($site['llms']) ? 'ok' : 'aviso', !empty($site['llms']) ? 'Arquivo llms.txt encontrado' : 'Sem arquivo llms.txt', 'O llms.txt é um resumo do site feito para as IAs (o que a empresa faz, páginas principais, contato). É novo e ainda pouco usado — sair na frente ajuda.', empty($site['llms']) ? 'Crie /llms.txt com a descrição da empresa, serviços, região atendida e links das páginas principais.' : '', 1, 'baixo');
    $faq = in_array('FAQPage', $types, true) || preg_match('/perguntas frequentes|d[úu]vidas frequentes|\bFAQ\b/iu', (string) ($f['text'] ?? ''));
    $I[] = s7x_seo_item('ia', 'faq', $faq ? 'ok' : 'aviso', $faq ? 'Tem perguntas e respostas' : 'Sem seção de perguntas frequentes', 'IAs e o Google gostam de respostas diretas às dúvidas reais dos clientes.', $faq ? '' : 'Crie uma seção de perguntas frequentes com respostas objetivas (e o schema FAQPage).', 2, 'medio', 'medio');
    $I[] = s7x_seo_item('ia', 'entity', ($biz && !empty($f['address_hint'])) || $biz ? 'ok' : 'aviso', $biz ? 'A IA consegue identificar a empresa' : 'A IA tem dificuldade para identificar a empresa', $biz ? 'Nome e dados da empresa estão marcados nos dados estruturados.' : 'Sem schema de empresa, as IAs não têm certeza de quem é o site, onde atende e como contatar.', $biz ? '' : 'Marque nome, endereço, telefone, redes sociais (sameAs) e área de atendimento no schema.', 2, 'medio');
    if (!empty($f['spa'])) {
        $I[] = s7x_seo_item('ia', 'spa_ai', 'erro', 'As IAs não conseguem ler o conteúdo', 'A maioria dos robôs de IA não executa JavaScript: para eles, a página está vazia.', 'Entregue o HTML já pronto (renderização no servidor).', 3, 'alto', 'dificil');
    }

    /* ---------------- Medição e contato ---------------- */
    $tools = $f['tools'] ?? [];
    $hasGa = !empty($tools['ga4']) || !empty($tools['gtm']);
    $I[] = s7x_seo_item('conversao', 'analytics', $hasGa ? 'ok' : 'erro', $hasGa ? 'Google Analytics instalado' : 'Sem Google Analytics', $hasGa ? implode(', ', array_merge($tools['ga4'] ?? [], $tools['gtm'] ?? [])) : 'Sem medição, não dá para saber quantas pessoas visitam o site, de onde vêm e o que fazem.', $hasGa ? '' : 'Instale o Google Analytics 4 (é gratuito) e marque os cliques no WhatsApp como conversão.', 3, 'alto');
    if (!empty($tools['ua']) && empty($tools['ga4'])) {
        $I[] = s7x_seo_item('conversao', 'ua', 'erro', 'Analytics antigo (Universal Analytics)', 'O código ' . $tools['ua'][0] . ' parou de coletar dados em 2023.', 'Migre para o Google Analytics 4.', 2, 'alto');
    }
    $contact = !empty($f['whatsapp']) || !empty($f['tel']) || ($f['forms'] ?? 0) > 0;
    $I[] = s7x_seo_item('conversao', 'contact', $contact ? 'ok' : 'erro', $contact ? 'Contato fácil de achar' : 'Sem forma rápida de contato', $contact ? implode(', ', array_filter([!empty($f['whatsapp']) ? 'WhatsApp' : '', !empty($f['tel']) ? 'telefone' : '', ($f['forms'] ?? 0) ? 'formulário' : '', !empty($f['mailto']) ? 'e-mail' : ''])) . '.' : 'Não achamos botão de WhatsApp, telefone ou formulário na página.', $contact ? '' : 'Coloque um botão de WhatsApp visível e um formulário curto.', 3, 'alto');
    $mk = array_intersect_key($tools, array_flip(['meta_pixel', 'ads', 'tiktok', 'linkedin']));
    $names = ['meta_pixel' => 'Pixel da Meta', 'ads' => 'Google Ads', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn'];
    $I[] = s7x_seo_item('conversao', 'pixels', $mk ? 'ok' : 'info', $mk ? 'Pronto para anúncios: ' . implode(', ', array_map(fn ($k) => $names[$k], array_keys($mk))) : 'Sem pixels de anúncio', $mk ? '' : 'Se for anunciar no Instagram ou no Google, instale o Pixel da Meta e a tag do Google Ads antes, para medir resultado.', '', $mk ? 1 : 0, 'baixo');

    return $I;
}

function s7x_seo_short_url(string $u): string
{
    $p = parse_url($u);
    $s = ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
    return mb_strimwidth($s === '' ? '/' : $s, 0, 60, '…');
}

/** Notas por categoria e geral (0–100). */
/** Versão da régua de notas (mudou → análises antigas são recalculadas ao abrir). */
const S7X_SEO_SCORE_V = 2;

/**
 * Nota de 0 a 100.
 * - Cada item vale o seu peso: certo = 100%, atenção = 40%, corrigir = 0.
 * - A nota da área é curvada (expoente 1,3): áreas "meio certas" não passam por boas.
 * - Cada erro de impacto alto tira 1,5 ponto da nota geral (até 8).
 * - Tetos para problemas que, sozinhos, seguram o site no Google:
 *     fora do Google (noindex, robots, erro na página) → 35 · sem versão para celular → 49
 *     sem cadeado (HTTPS) ou sem título → 59 · velocidade no celular abaixo de 50 ou quase sem texto → 69
 *     conteúdo principal demorando mais de 4 s (LCP) → 74 · qualquer erro de impacto alto → 84
 * - Sem a medição do Google PageSpeed, a velocidade não é confirmada: a área vale no máximo 50
 *   e a nota geral no máximo 84 (o relatório avisa).
 */
function s7x_seo_score(array $items): array
{
    $cats = s7x_seo_categories();
    $out = ['cats' => [], 'total' => 0, 'v' => S7X_SEO_SCORE_V, 'caps' => []];
    $status = [];
    $altos = 0;
    $psi = true;
    foreach ($items as $it) {
        $status[$it['id']] = $it['status'];
        if ($it['status'] === 'erro' && $it['impacto'] === 'alto' && $it['peso'] > 0) {
            $altos++;
        }
        if ($it['id'] === 'psi_mobile' && $it['status'] === 'info') {
            $psi = false;
        }
    }
    $sum = 0;
    $wsum = 0;
    foreach ($cats as $k => $c) {
        $pts = 0;
        $max = 0;
        $count = ['ok' => 0, 'aviso' => 0, 'erro' => 0];
        foreach ($items as $it) {
            if ($it['cat'] !== $k || $it['status'] === 'info' || $it['peso'] <= 0) {
                continue;
            }
            $max += $it['peso'];
            $pts += $it['peso'] * ['ok' => 1, 'aviso' => 0.4, 'erro' => 0][$it['status']];
            $count[$it['status']]++;
        }
        if ($max <= 0) {
            continue;
        }
        $score = (int) round(100 * ($pts / $max) ** 1.3);
        if ($k === 'velocidade' && !$psi) {
            $score = min($score, 50);
        }
        $out['cats'][$k] = ['score' => $score, 'count' => $count] + $c;
        $sum += $score * $c['peso'];
        $wsum += $c['peso'];
    }
    $total = $wsum ? $sum / $wsum : 0;
    $total -= min(8, 1.5 * $altos);

    $caps = [
        [['noindex', 'robots', 'status'], 35, 'O site está fora do Google'],
        [['viewport'], 49, 'O site não se adapta ao celular'],
        [['https'], 59, 'O site não tem cadeado (HTTPS)'],
        [['title'], 59, 'A página não tem título'],
        [['psi_mobile'], 69, 'Velocidade no celular abaixo de 50'],
        [['words'], 69, 'Quase não há texto na página'],
        [['lcp'], 74, 'O conteúdo principal demora mais de 4 s para aparecer'],
    ];
    foreach ($caps as [$ids, $cap, $why]) {
        foreach ($ids as $id) {
            if (($status[$id] ?? '') === 'erro' && $total > $cap) {
                $total = $cap;
                $out['caps'][] = $why;
                break;
            }
        }
    }
    if ($altos && $total > 84) {
        $total = 84;
        $out['caps'][] = 'Há problemas de impacto alto para corrigir';
    }
    if (!$psi) {
        $out['partial'] = true;
        if ($total > 84) {
            $total = 84;
        }
    }
    $out['total'] = (int) max(0, round($total));
    $out['grade'] = s7x_seo_grade($out['total']);
    return $out;
}

function s7x_seo_grade(int $s): array
{
    if ($s >= 90) {
        return ['key' => 'otimo', 'label' => 'Excelente', 'text' => 'O site está muito bem preparado. Os ajustes abaixo são o acabamento.'];
    }
    if ($s >= 75) {
        return ['key' => 'bom', 'label' => 'Bom', 'text' => 'A base está boa, mas há pontos que seguram o site antes do topo do Google.'];
    }
    if ($s >= 50) {
        return ['key' => 'medio', 'label' => 'Precisa melhorar', 'text' => 'O site perde visitas e clientes por problemas que têm solução.'];
    }
    return ['key' => 'ruim', 'label' => 'Crítico', 'text' => 'Problemas sérios impedem o site de aparecer bem no Google.'];
}

/** Plano de ação: problemas ordenados por impacto e facilidade. */
function s7x_seo_plan(array $items): array
{
    $imp = ['alto' => 3, 'medio' => 2, 'baixo' => 1];
    $eff = ['facil' => 3, 'medio' => 2, 'dificil' => 1];
    $todo = array_values(array_filter($items, fn ($i) => in_array($i['status'], ['erro', 'aviso'], true) && $i['como'] !== ''));
    usort($todo, function ($a, $b) use ($imp, $eff) {
        $sa = $imp[$a['impacto']] * 10 + ($a['status'] === 'erro' ? 5 : 0) + $eff[$a['esforco']] * 2 + $a['peso'];
        $sb = $imp[$b['impacto']] * 10 + ($b['status'] === 'erro' ? 5 : 0) + $eff[$b['esforco']] * 2 + $b['peso'];
        return $sb <=> $sa;
    });
    return $todo;
}
