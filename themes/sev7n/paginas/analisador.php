<?php
/** Analisador de SEO — Autor: João Costa */
if (!defined('CMS_ROOT')) { exit; }
return ['id' => 'analisador', 'title' => 'Analisador de SEO grátis', 'slug' => 'analisador-seo', 'seo' => [
    'title' => 'Analisador de SEO Grátis: nota do seu site em 1 minuto | Agência Sev7n',
    'description' => 'Analise seu site grátis: velocidade medida pelo Google, celular, indexação, conteúdo, segurança e busca por IA. Nota de 0 a 100, plano de ação e relatório em PDF.',
    'focus_keyword' => 'analisador de seo',
], 'blocks' => [
    ['type' => 'seo-analyzer', 'anchor' => 'analisar', 'data' => [
        'eyebrow' => 'Analisador de SEO gratuito',
        'title' => 'Descubra o que impede seu site de aparecer no Google',
        'text' => 'Mais de 60 verificações em cerca de 1 minuto: velocidade real medida pelo Google, celular, indexação, conteúdo, segurança, aparência no WhatsApp e se o ChatGPT consegue ler o seu site. Você recebe uma nota de 0 a 100, o plano de ação e o relatório em PDF.',
        'placeholder' => 'www.seusite.com.br', 'keyword' => true, 'button' => 'Analisar meu site',
        'note' => 'Grátis. A nota aparece na hora; o relatório completo chega com o seu contato.', 'heading' => 'h1',
    ]],
    ['type' => 'servicos', 'anchor' => 'o-que-analisamos', 'data' => [
        'eyebrow' => 'O que o analisador confere',
        'title' => '10 áreas que decidem se o cliente encontra você',
        'items' => [
            ['icon' => 'lupa', 'title' => 'Google encontra o site', 'text' => 'Bloqueios no robots.txt, "noindex", sitemap, canonical, http→https, www e redirecionamentos.'],
            ['icon' => 'raio', 'title' => 'Velocidade e Core Web Vitals', 'text' => 'Nota do Google PageSpeed no celular e no computador, LCP, INP e CLS com dados reais dos visitantes.'],
            ['icon' => 'site', 'title' => 'Conteúdo e palavra-chave', 'text' => 'Título, descrição, H1, quantidade de texto e onde a sua palavra-chave aparece.'],
            ['icon' => 'celular', 'title' => 'Celular e acessibilidade', 'text' => 'Layout adaptado, zoom, contraste e ícone do site.'],
            ['icon' => 'pincel', 'title' => 'Imagens', 'text' => 'Texto alternativo, formatos modernos, peso e carregamento tardio.'],
            ['icon' => 'escudo', 'title' => 'Segurança', 'text' => 'Certificado SSL, arquivos sem cadeado e proteções do servidor.'],
            ['icon' => 'conversa', 'title' => 'Aparência no Google e no WhatsApp', 'text' => 'Prévia do resultado no Google e do link compartilhado, e dados estruturados.'],
            ['icon' => 'faisca', 'title' => 'Busca por IA', 'text' => 'Se ChatGPT, Claude, Gemini e Perplexity conseguem ler e entender a sua empresa.'],
            ['icon' => 'alvo', 'title' => 'Links e páginas internas', 'text' => 'Links quebrados, títulos repetidos e páginas com pouco conteúdo.'],
            ['icon' => 'grafico', 'title' => 'Medição e contato', 'text' => 'Google Analytics, pixels de anúncio e se o visitante acha o seu WhatsApp.'],
        ],
        'background' => 'grafite',
    ]],
    ['type' => 'faq', 'anchor' => 'perguntas', 'data' => [
        'title' => 'Perguntas sobre o analisador',
        'items' => [
            ['question' => 'O analisador é gratuito mesmo?', 'answer' => 'Sim. A nota e os principais problemas aparecem na hora. Para ver o relatório completo e baixar o PDF, basta deixar nome e contato.'],
            ['question' => 'De onde vêm os dados de velocidade?', 'answer' => 'Do Google PageSpeed Insights, a ferramenta oficial do Google. Quando o site tem visitas suficientes, mostramos também os dados reais dos visitantes do Chrome (Core Web Vitals) dos últimos 28 dias.'],
            ['question' => 'Posso analisar o site de um concorrente?', 'answer' => 'Pode. A análise usa só informações públicas, as mesmas que o Google vê.'],
            ['question' => 'A nota 100 garante a primeira posição no Google?', 'answer' => 'Não. A nota mostra se o site está tecnicamente preparado. A posição também depende do conteúdo, da concorrência e da autoridade do site. Ainda assim, corrigir os problemas da análise costuma ser o primeiro passo para subir.'],
            ['question' => 'O que é a busca por IA?', 'answer' => 'Cada vez mais pessoas perguntam ao ChatGPT, ao Gemini ou ao Perplexity qual empresa contratar. O analisador confere se esses robôs conseguem ler o seu site e entender quem é a sua empresa.'],
        ],
    ]],
]];
