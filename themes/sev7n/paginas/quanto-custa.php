<?php
/** Quanto custa criar um site profissional — Autor: João Costa */
if (!defined('CMS_ROOT')) { exit; }
return ['id' => 'quanto-custa', 'title' => 'Quanto custa criar um site profissional', 'slug' => 'quanto-custa-criar-um-site-profissional', 'seo' => [
    'title' => 'Quanto Custa Criar um Site Profissional em 2026? | Sev7n',
    'description' => 'Quanto custa criar um site profissional em 2026: faixas de preço por tipo de projeto, o que está incluso, o que encarece e como economizar sem perder qualidade.',
    'focus_keyword' => 'quanto custa criar um site',
], 'blocks' => [
    ['type' => 'cabecalho', 'data' => [
        'eyebrow' => 'Guia de preços 2026',
        'title' => 'Quanto custa criar um site profissional em 2026?',
        'text' => 'O preço de um site profissional no Brasil vai de R$ 1.500 a mais de R$ 50.000, dependendo do tipo, da complexidade e de quem desenvolve. Neste guia mostramos as faixas reais de mercado, o que entra em cada uma e como evitar armadilhas.',
    ]],
    ['type' => 'faixas', 'data' => [
        'title' => 'Faixas de preço por tipo de projeto',
        'text' => 'Valores de referência do mercado brasileiro para projetos feitos por agência.',
        'items' => [
            ['name' => 'Landing page', 'price' => 'R$ 1.500 a R$ 4.000', 'time' => 'até 1 semana', 'text' => 'Uma página focada em uma oferta ou campanha, com formulário e WhatsApp.', 'highlight' => false],
            ['name' => 'Site institucional', 'price' => 'R$ 3.500 a R$ 9.000', 'time' => '2 a 4 semanas', 'text' => 'Até 8 páginas, design exclusivo, SEO básico, painel de edição e Analytics.', 'highlight' => true],
            ['name' => 'Institucional premium + SEO', 'price' => 'R$ 8.000 a R$ 18.000', 'time' => '4 a 6 semanas', 'text' => 'Mais páginas, textos profissionais, blog, SEO avançado e animações.', 'highlight' => false],
            ['name' => 'Loja virtual', 'price' => 'R$ 6.000 a R$ 25.000', 'time' => '4 a 8 semanas', 'text' => 'Catálogo, carrinho, Pix, cartão, frete, cupons e integrações.', 'highlight' => false],
            ['name' => 'Sistema web / SaaS', 'price' => 'a partir de R$ 15.000', 'time' => 'sob consulta', 'text' => 'Área logada, painéis, regras de negócio e integrações com outros sistemas.', 'highlight' => false],
            ['name' => 'Manutenção mensal', 'price' => 'R$ 200 a R$ 1.500 / mês', 'time' => 'contínuo', 'text' => 'Atualizações, backups e suporte; nos planos maiores, SEO e conteúdo novo todo mês.', 'highlight' => false],
        ],
        'note' => 'Hospedagem (R$ 30 a R$ 200 por mês) e domínio .com.br (cerca de R$ 40 por ano no Registro.br) normalmente são pagos à parte.',
    ]],
    ['type' => 'artigo', 'data' => [
        'text' => '<h2>O que está incluso no preço de um site profissional?</h2>'
            . '<p>Em um projeto bem feito você paga por planejamento, design exclusivo, programação, textos ajustados para o Google, testes em vários aparelhos, configuração de domínio e SSL, Analytics e treinamento para usar o painel.</p>'
            . '<h2>O que faz o preço subir (ou descer)?</h2>'
            . '<p><strong>Sobe com:</strong> design 100% exclusivo, animações, integrações com outros sistemas, vários idiomas, área logada, blog, SEO avançado e textos escritos por redator.</p>'
            . '<p><strong>Desce com:</strong> modelos adaptados, escopo enxuto (1 a 3 páginas), conteúdo enviado pelo cliente e nenhuma integração.</p>'
            . '<p><strong>Cuidado:</strong> o "barato" pode sair caro. Site lento, sem SEO e sem suporte faz você perder vendas e, em um ou dois anos, ter de refazer tudo.</p>'
            . '<h2>Freelancer, agência ou plataforma faça-você-mesmo?</h2>'
            . '<ul><li><strong>Plataformas como Wix:</strong> de R$ 30 a R$ 100 por mês. Servem para testar uma ideia, mas limitam o crescimento e o SEO.</li>'
            . '<li><strong>Freelancer:</strong> de R$ 800 a R$ 5.000. Mais barato, com mais risco de prazo e de suporte. Bom para projetos simples.</li>'
            . '<li><strong>Agência:</strong> de R$ 3.500 a R$ 50.000+. Equipe completa (design, programação, SEO e gestão), processo estruturado e suporte garantido.</li></ul>'
            . '<h2>Como economizar sem perder qualidade</h2>'
            . '<ol><li>Comece pelo essencial: home, serviços, sobre e contato.</li><li>Prepare textos e fotos antes do projeto começar.</li><li>Prefira valor único + manutenção em vez de "aluguel de site".</li><li>Invista em SEO desde o início: refazer depois custa de 2 a 3 vezes mais.</li></ol>',
        'aside_title' => 'Quer um orçamento exato?',
        'aside_text' => 'Conte o que você precisa e receba uma proposta em até 1 dia útil.',
    ]],
    ['type' => 'faq', 'data' => [
        'title' => 'Perguntas frequentes sobre preço de site',
        'items' => [
            ['question' => 'Qual o preço médio de um site profissional para uma pequena empresa?', 'answer' => 'Entre R$ 4.000 e R$ 9.000 para um site institucional completo (5 a 8 páginas) com design exclusivo, SEO básico, painel de edição e integração com WhatsApp e Analytics.'],
            ['question' => 'É melhor pagar mensalidade ou valor único?', 'answer' => 'Valor único mais uma mensalidade de manutenção é o modelo mais saudável: você é dono do site e paga só hospedagem e suporte (R$ 200 a R$ 1.500 por mês). Cuidado com o "aluguel de site", que prende você ao fornecedor.'],
            ['question' => 'Posso parcelar o investimento?', 'answer' => 'Sim. A maioria das agências parcela no cartão, boleto ou Pix. Na Sev7n trabalhamos com entrada mais parcelas durante o projeto.'],
            ['question' => 'O preço inclui hospedagem e domínio?', 'answer' => 'Geralmente não. Hospedagem de qualidade custa de R$ 30 a R$ 200 por mês e o domínio .com.br, cerca de R$ 40 por ano no Registro.br.'],
            ['question' => 'Vale a pena pagar mais por SEO desde o início?', 'answer' => 'Sim. Refazer um site para corrigir SEO custa de 2 a 3 vezes mais do que fazer certo da primeira vez.'],
            ['question' => 'Quanto custa manter o site depois de pronto?', 'answer' => 'Manutenção básica (atualizações, backups e suporte) fica entre R$ 200 e R$ 500 por mês. Pacotes com SEO mensal e conteúdo novo, entre R$ 800 e R$ 1.500.'],
        ],
    ]],
    ['type' => 'chamada', 'data' => [
        'title' => 'Quer saber quanto fica o seu projeto?',
        'text' => 'Orçamento sem compromisso, direto com quem vai fazer o site.',
        'button_label' => 'Pedir orçamento no WhatsApp', 'button_link' => 'wa: Olá! Vi a página de preços e quero um orçamento para o meu site.',
        'button2_label' => 'Ver portfólio', 'button2_link' => '/#portfolio',
    ]],
]];
