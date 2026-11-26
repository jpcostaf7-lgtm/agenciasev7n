<?php
/** Criação de sites no Rio de Janeiro (página de SEO local) — Autor: João Costa */
if (!defined('CMS_ROOT')) { exit; }
return ['id' => 'criacao-sites-rj', 'title' => 'Criação de sites no Rio de Janeiro', 'slug' => 'criacao-de-sites-rio-de-janeiro', 'seo' => [
    'title' => 'Criação de Sites no Rio de Janeiro | Agência Sev7n',
    'description' => 'Criação de sites profissionais no Rio de Janeiro com design exclusivo, SEO e foco em conversão. Agência carioca com mais de 7 anos e 200+ projetos entregues.',
    'focus_keyword' => 'criação de sites rio de janeiro',
], 'blocks' => [
    ['type' => 'cabecalho', 'data' => [
        'eyebrow' => 'Criação de sites no Rio de Janeiro',
        'title' => 'Criação de sites profissionais no Rio de Janeiro',
        'text' => 'Somos uma agência carioca especializada em sites que vendem. Atendemos empresas de todos os cantos do Rio — Barra, Zona Sul, Centro, Zona Norte, Niterói e Baixada — com projetos rápidos, bonitos no celular e preparados para o Google.',
        'button_label' => 'Pedir orçamento no WhatsApp', 'button_link' => 'wa: Olá! Quero um orçamento de site para minha empresa no Rio.',
        'points' => "Design exclusivo para a sua marca\nPerfeito no celular, tablet e computador\nSEO de fábrica para aparecer no Google\nCore Web Vitals no verde\nWhatsApp, Instagram e Google Analytics integrados\nPainel simples para você atualizar",
    ]],
    ['type' => 'artigo', 'data' => [
        'text' => '<h2>Por que contratar uma agência de sites no Rio de Janeiro?</h2>'
            . '<p>Um site profissional deixou de ser cartão de visitas: hoje ele é a principal <strong>ferramenta de venda</strong> de quem presta serviço ou vende produtos. Quando alguém procura "dentista em Botafogo" ou "contador na Barra", escolhe entre os primeiros resultados — e decide em segundos se confia ou não na empresa.</p>'
            . '<p>Trabalhar com uma agência local ajuda porque a gente conhece os bairros, os termos que os cariocas usam para procurar e a concorrência da sua região. E, se você preferir, dá para fazer reuniões presenciais.</p>'
            . '<h2>Tipos de site que desenvolvemos</h2>'
            . '<ul><li><strong>Sites institucionais</strong> — para empresas que precisam de presença digital sólida e profissional.</li>'
            . '<li><strong>Landing pages de alta conversão</strong> — para campanhas no Google Ads e no Instagram.</li>'
            . '<li><strong>Lojas virtuais</strong> — em WooCommerce, Shopify ou sob medida, com Pix, cartão e frete.</li>'
            . '<li><strong>Sistemas web</strong> — áreas de cliente, CRMs e painéis com acesso por login.</li>'
            . '<li><strong>Sites para profissionais liberais</strong> — advogados, médicos, dentistas, arquitetos e contadores.</li></ul>'
            . '<h2>Como funciona o projeto</h2>'
            . '<ol><li><strong>Briefing e estratégia</strong> — entendemos seu negócio, público e objetivos.</li>'
            . '<li><strong>Design</strong> — você aprova as telas antes de programarmos.</li>'
            . '<li><strong>Desenvolvimento</strong> — código limpo, rápido e otimizado para o Google.</li>'
            . '<li><strong>Testes e SEO técnico</strong> — PageSpeed, dados estruturados, sitemap e robots.txt.</li>'
            . '<li><strong>Publicação e suporte</strong> — colocamos no ar, treinamos você e seguimos dando suporte.</li></ol>'
            . '<h2>Quer saber como está o seu site atual?</h2>'
            . '<p>Use o nosso <a href="/analisador-seo/">analisador de SEO gratuito</a>: em cerca de um minuto você recebe uma nota de 0 a 100 e a lista do que corrigir.</p>',
    ]],
    ['type' => 'portfolio', 'anchor' => 'portfolio', 'data' => [
        'eyebrow' => 'Alguns projetos no Rio',
        'title' => 'Sites que fizemos para empresas cariocas',
        'text' => '',
        'items' => array_values(array_filter($portfolio, fn ($p) => in_array($p['name'], ['Ladislau Assessoria Contábil', 'Reboque Ferreira 24h', 'Bestane Urologia', 'BVI Leilões', 'Jequitibá Investimentos', 'Sheltermar'], true))),
        'visible' => 6,
        'background' => 'grafite',
    ]],
    ['type' => 'faq', 'data' => [
        'title' => 'Perguntas frequentes',
        'items' => [
            ['question' => 'Quanto tempo leva para criar um site no Rio de Janeiro?', 'answer' => 'Entre 7 e 30 dias úteis, dependendo da complexidade. Landing pages ficam prontas em até 1 semana; sites institucionais completos, em 2 a 4 semanas.'],
            ['question' => 'Vocês atendem empresas de toda a Grande Rio?', 'answer' => 'Sim. Atendemos a capital (Zona Sul, Norte, Oeste e Centro), Niterói, São Gonçalo, Baixada Fluminense e Região dos Lagos — presencialmente ou 100% a distância.'],
            ['question' => 'O site já vem otimizado para o Google?', 'answer' => 'Sim. Todo site sai com SEO técnico: títulos e descrições, dados estruturados, sitemap, robots.txt, boa velocidade e estrutura semântica. Você também recebe orientações de conteúdo.'],
            ['question' => 'Posso atualizar o site sozinho depois?', 'answer' => 'Sim. Entregamos com um painel de edição simples e um treinamento rápido.'],
        ],
    ]],
    ['type' => 'chamada', 'data' => [
        'title' => 'Seu próximo cliente está procurando você no Google agora.',
        'text' => 'Vamos fazer ele encontrar o seu site primeiro?',
        'button_label' => 'Pedir orçamento', 'button_link' => 'wa',
        'button2_label' => 'Analisar meu site atual', 'button2_link' => '/analisador-seo/',
    ]],
]];
