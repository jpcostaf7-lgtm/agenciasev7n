<?php
/** Página inicial — Autor: João Costa */
if (!defined('CMS_ROOT')) { exit; }
return ['id' => 'home', 'title' => 'Página inicial', 'home' => true, 'seo' => [
    'title' => 'Criação de Sites no Rio de Janeiro | Agência Sev7n',
    'description' => 'Criação de sites no Rio de Janeiro: sites, lojas virtuais e sistemas sob medida, feitos para aparecer no Google e trazer clientes. Analise seu site grátis.',
], 'blocks' => [
    ['type' => 'hero', 'anchor' => 'inicio', 'data' => [
        'text' => 'Criamos sites, lojas virtuais e sistemas sob medida para empresas e profissionais que querem ser encontrados no Google, no Maps e nas buscas por IA — e receber mais pedidos de orçamento.',
    ]],
    ['type' => 'servicos', 'anchor' => 'servicos', 'data' => [
        'eyebrow' => 'O que fazemos',
        'title' => 'Tudo o que sua empresa precisa para crescer na internet',
        'text' => 'Do primeiro site ao sistema sob medida, com SEO desde a primeira linha de código.',
        'items' => [
            ['icon' => 'site', 'title' => 'Criação de sites profissionais', 'text' => 'Sites institucionais e landing pages rápidos, com textos que vendem e estrutura pronta para o Google. Você atualiza tudo sozinho pelo painel.', 'link' => '/criacao-de-sites-rio-de-janeiro/'],
            ['icon' => 'carrinho', 'title' => 'Lojas virtuais', 'text' => 'Catálogo, carrinho, Pix, cartão, frete e cupons. Uma loja que vende todos os dias, inclusive fora do horário comercial.', 'link' => ''],
            ['icon' => 'sistema', 'title' => 'Sistemas web sob medida', 'text' => 'CRM, áreas de cliente, painéis e automações para tirar processos da planilha e ganhar tempo.', 'link' => ''],
            ['icon' => 'lupa', 'title' => 'SEO e busca por IA', 'text' => 'Otimização técnica e de conteúdo para subir no Google e ser citado pelo ChatGPT, Gemini e Perplexity.', 'link' => '/analisador-seo/'],
            ['icon' => 'pincel', 'title' => 'Identidade visual', 'text' => 'Logo, cores e layout que passam confiança antes do primeiro contato, no site e nas redes.', 'link' => ''],
            ['icon' => 'grafico', 'title' => 'Google Ads e medição', 'text' => 'Campanhas para quem já está procurando, com Analytics e conversões configurados para saber o que dá resultado.', 'link' => ''],
        ],
        'background' => 'noite',
    ]],
    ['type' => 'portfolio', 'anchor' => 'portfolio', 'data' => [
        'eyebrow' => 'Portfólio',
        'title' => 'Projetos no ar, trabalhando para os clientes',
        'text' => 'Passe o mouse sobre cada site para ver a página inteira.',
        'items' => $portfolio,
        'visible' => 6,
        'background' => 'grafite',
    ]],
    ['type' => 'processo', 'anchor' => 'processo', 'data' => [
        'eyebrow' => 'Como trabalhamos',
        'title' => 'Do briefing ao site no ar, sem surpresa',
        'text' => 'Você acompanha cada etapa e aprova antes de seguirmos para a próxima.',
        'items' => [
            ['title' => 'Briefing e estratégia', 'time' => '1ª semana', 'text' => 'Entendemos o negócio, o público e os concorrentes, e definimos as páginas e as palavras que seus clientes usam para procurar você.'],
            ['title' => 'Design', 'time' => '1 a 2 semanas', 'text' => 'Você vê o layout das telas no celular e no computador e aprova antes de começarmos a programar.'],
            ['title' => 'Desenvolvimento e SEO', 'time' => '1 a 3 semanas', 'text' => 'Código limpo e rápido, com SEO técnico, dados estruturados, sitemap e Analytics já configurados.'],
            ['title' => 'Lançamento e suporte', 'time' => 'contínuo', 'text' => 'Publicamos com domínio, SSL e testes completos, treinamos você no painel e seguimos dando suporte.'],
        ],
        'background' => 'noite',
    ]],
    ['type' => 'diferenciais', 'anchor' => 'sobre', 'data' => [
        'eyebrow' => 'Por que a Sev7n',
        'title' => 'Uma agência pequena de propósito: você fala com quem faz',
        'text' => '<p>A Sev7n nasceu em 2017 no Rio de Janeiro, fundada por João Costa, para tirar empresas do improviso digital. Desde então foram mais de 200 sites, lojas e sistemas para clínicas, escritórios, escolas, franquias e prestadores de serviço.</p><p>Cada projeto é acompanhado de perto, do briefing ao suporte depois do lançamento.</p>',
        'items' => [
            ['icon' => 'raio', 'title' => 'Sites rápidos de verdade', 'text' => 'Medimos a velocidade com o próprio Google antes de entregar.'],
            ['icon' => 'grafico', 'title' => 'Decisões com dados', 'text' => 'Analytics e conversões configurados para você ver o que traz cliente.'],
            ['icon' => 'relogio', 'title' => 'Prazo combinado', 'text' => 'Cronograma por etapas, com aprovação sua em cada uma.'],
            ['icon' => 'conversa', 'title' => 'Comunicação direta', 'text' => 'WhatsApp com quem cuida do seu projeto, sem intermediários.'],
            ['icon' => 'escudo', 'title' => 'O site é seu', 'text' => 'Sem aluguel de site: domínio, conteúdo e código ficam com você.'],
        ],
        'background' => 'grafite',
    ]],
    ['type' => 'depoimentos', 'anchor' => 'depoimentos', 'data' => [
        'eyebrow' => 'Depoimentos',
        'title' => 'O que dizem os clientes',
        'items' => $depoimentos,
        'background' => 'noite',
    ]],
    ['type' => 'material', 'anchor' => 'ebook', 'data' => [
        'eyebrow' => 'E-book gratuito',
        'title' => 'O digital não é mais opção',
        'text' => 'Os 7 pilares para sua empresa ser encontrada, passar confiança e vender pela internet — com teste de autoavaliação e plano de 30 dias.',
        'image' => ['src' => $a . 'ebook/capa.webp', 'alt' => 'Capa do e-book O digital não é mais opção'],
        'button_label' => 'Baixar grátis',
        'button_link' => '/o-digital-nao-e-mais-opcao/',
        'background' => 'grafite',
    ]],
    ['type' => 'faq', 'anchor' => 'perguntas', 'data' => [
        'title' => 'Perguntas frequentes',
        'items' => [
            ['question' => 'Quanto tempo leva para criar um site?', 'answer' => 'Entre 7 e 30 dias úteis, dependendo do tamanho. Landing pages ficam prontas em até uma semana; sites institucionais completos, em duas a quatro semanas.'],
            ['question' => 'Quanto custa um site profissional?', 'answer' => 'Depende do tipo de projeto: uma landing page parte de R$ 1.500 e um site institucional completo fica, em geral, entre R$ 3.500 e R$ 9.000. Veja as faixas detalhadas na página "Quanto custa criar um site profissional" ou peça um orçamento pelo WhatsApp.'],
            ['question' => 'O site já vem otimizado para o Google?', 'answer' => 'Sim. Todo site sai com SEO técnico: títulos e descrições, dados estruturados, sitemap, robots.txt, imagens otimizadas, velocidade medida no PageSpeed e Google Analytics configurado.'],
            ['question' => 'Vou conseguir atualizar o site sozinho?', 'answer' => 'Sim. Entregamos com um painel simples para trocar textos, fotos e páginas, e mostramos como usar. Se preferir, cuidamos das atualizações para você.'],
            ['question' => 'Vocês atendem fora do Rio de Janeiro?', 'answer' => 'Sim. Atendemos a capital, Niterói, São Gonçalo, Baixada e Região dos Lagos presencialmente, e o resto do Brasil de forma 100% remota.'],
            ['question' => 'O site fica no meu nome?', 'answer' => 'Sim. Domínio, conteúdo e código são seus. Você paga apenas a hospedagem e, se quiser, um plano de manutenção.'],
        ],
    ]],
    ['type' => 'contact-form', 'anchor' => 'contato', 'data' => [
        'title' => 'Vamos tirar o seu projeto do papel?',
        'text' => 'Conte rapidamente o que você precisa. Respondemos no mesmo dia útil, pelo WhatsApp ou por e-mail.',
        'phone_required' => true, 'show_email' => true, 'email_required' => false, 'show_message' => true,
        'button_label' => 'Pedir orçamento', 'success' => 'Recebemos! Em breve falamos com você pelo WhatsApp.',
        'background' => 'grafite',
    ]],
]];
