<?php
/** E-book O digital não é mais opção — Autor: João Costa */
if (!defined('CMS_ROOT')) { exit; }
return ['id' => 'ebook', 'title' => 'E-book: O digital não é mais opção', 'slug' => 'o-digital-nao-e-mais-opcao', 'seo' => [
    'title' => 'E-book Grátis: O Digital Não É Mais Opção | Agência Sev7n',
    'description' => 'Baixe grátis o e-book "O digital não é mais opção": os 7 pilares para sua empresa ser encontrada no Google e nas IAs, teste de autoavaliação e plano de 30 dias.',
    'focus_keyword' => 'e-book presença digital',
], 'blocks' => [
    ['type' => 'lead-download', 'anchor' => 'baixar', 'data' => [
        'eyebrow' => 'E-book gratuito · edição 2026',
        'title' => 'O digital não é mais opção',
        'heading' => 'h1',
        'text' => 'Um guia direto ao ponto para donos de pequenas e médias empresas que querem ser encontrados no Google, nas buscas por IA e no WhatsApp — sem desperdiçar dinheiro.',
        'bullets' => "Os 7 pilares do crescimento digital, explicados sem jargão\nComo aparecer no ChatGPT e no Gemini quando alguém procura o seu serviço\nTeste de autoavaliação com nota para a sua empresa\nOs erros que mais fazem empresas perderem clientes\nPlano de 30 dias, semana a semana",
        'cover' => ['src' => $a . 'ebook/capa.webp', 'alt' => 'Capa do e-book O digital não é mais opção'],
        'file' => $a . 'ebook/o-digital-nao-e-mais-opcao.pdf',
        'file_name' => 'O-Digital-Nao-E-Mais-Opcao-Agencia-Sev7n.pdf',
        'details' => 'PDF · 23 páginas · leitura de 20 minutos',
        'ask_phone' => 'opcional',
        'button_label' => 'Quero o e-book grátis',
        'success' => 'Pronto! O download começou. Se não começar, use o botão abaixo.',
        'consent_text' => 'Aceito receber conteúdos da Agência Sev7n por e-mail e WhatsApp. Posso cancelar quando quiser.',
    ]],
    ['type' => 'chamada', 'data' => [
        'title' => 'Enquanto lê, que tal ver a nota do seu site?',
        'text' => 'O analisador gratuito mostra em 1 minuto o que está tirando o seu site do Google.',
        'button_label' => 'Analisar meu site', 'button_link' => '/analisador-seo/',
        'button2_label' => 'Falar com a Sev7n', 'button2_link' => 'wa: Olá! Baixei o e-book e quero conversar sobre o meu site.',
    ]],
]];
