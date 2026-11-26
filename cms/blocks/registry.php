<?php
/**
 * Sev7n Xpress — blocos básicos (disponíveis em qualquer projeto)
 * Autor: João Costa
 *
 * O tema pode trocar o HTML de qualquer bloco criando theme/blocks/<tipo>.php
 */
declare(strict_types=1);

$bg = ['branco' => 'Branco', 'claro' => 'Claro', 'escuro' => 'Escuro (cor principal)', 'destaque' => 'Cor de destaque'];

cms_register_block('page-header', [
    'label' => 'Título da página', 'icon' => 'calendario', 'group' => 'Básicos',
    'description' => 'Título principal (H1) e texto de abertura. Use no topo das páginas internas.',
    'template' => __DIR__ . '/page-header.php',
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Título (H1)', 'h1' => true, 'default' => 'Título da página'],
        'text'  => ['type' => 'textarea', 'label' => 'Texto de abertura', 'rows' => 2],
    ],
]);

cms_register_block('text', [
    'label' => 'Texto', 'icon' => 'folha', 'group' => 'Básicos',
    'description' => 'Título e texto corrido com negrito, listas e links. Ideal para políticas e páginas institucionais.',
    'template' => __DIR__ . '/text.php',
    'fields' => [
        'title'      => ['type' => 'text', 'label' => 'Título (H2) — opcional'],
        'text'       => ['type' => 'richtext', 'label' => 'Texto', 'default' => '<p>Escreva o texto aqui.</p>'],
        'background' => ['type' => 'select', 'label' => 'Fundo', 'options' => $bg, 'default' => 'branco'],
    ],
]);

cms_register_block('text-image', [
    'label' => 'Texto + imagem', 'icon' => 'neuronio', 'group' => 'Básicos',
    'description' => 'Duas colunas: texto de um lado e imagem do outro, com frase de destaque opcional.',
    'template' => __DIR__ . '/text-image.php',
    'fields' => [
        'title'      => ['type' => 'text', 'label' => 'Título (H2)', 'default' => 'Título da seção'],
        'text'       => ['type' => 'richtext', 'label' => 'Texto', 'default' => '<p>Escreva o texto aqui.</p>'],
        'highlight'  => ['type' => 'textarea', 'label' => 'Frase em destaque (opcional)', 'rows' => 2],
        'image'      => ['type' => 'image', 'label' => 'Imagem'],
        'image_side' => ['type' => 'select', 'label' => 'Lado da imagem', 'options' => ['esquerda' => 'Esquerda', 'direita' => 'Direita'], 'default' => 'esquerda'],
        'background' => ['type' => 'select', 'label' => 'Fundo', 'options' => $bg, 'default' => 'branco'],
    ],
]);

cms_register_block('faq', [
    'label' => 'Perguntas frequentes', 'icon' => 'check', 'group' => 'Básicos',
    'description' => 'Perguntas que abrem e fecham. Também viram FAQ estruturado para o Google.',
    'template' => __DIR__ . '/faq.php',
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Título (H2)', 'default' => 'Perguntas frequentes'],
        'items' => [
            'type' => 'repeater', 'label' => 'Perguntas', 'item_label' => 'question', 'add_label' => 'pergunta', 'faq' => true,
            'fields' => [
                'question' => ['type' => 'text', 'label' => 'Pergunta'],
                'answer'   => ['type' => 'textarea', 'label' => 'Resposta', 'rows' => 4],
            ],
            'default' => [['question' => 'Pergunta de exemplo?', 'answer' => 'Resposta de exemplo.']],
        ],
    ],
]);

cms_register_block('cta', [
    'label' => 'Chamada para ação', 'icon' => 'calendario', 'group' => 'Básicos',
    'description' => 'Faixa com título, texto curto e um botão.',
    'template' => __DIR__ . '/cta.php',
    'fields' => [
        'title'        => ['type' => 'text', 'label' => 'Título', 'default' => 'Agende sua consulta'],
        'text'         => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2],
        'button_label' => ['type' => 'text', 'label' => 'Texto do botão', 'default' => 'Fale conosco'],
        'button_link'  => ['type' => 'url', 'label' => 'Link do botão', 'default' => '#', 'help' => 'Use /pagina, #secao, https://..., tel:... ou deixe "whatsapp" para abrir o WhatsApp do site.'],
        'background'   => ['type' => 'select', 'label' => 'Fundo', 'options' => $bg, 'default' => 'escuro'],
    ],
]);
