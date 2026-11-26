<?php
/**
 * Módulo: Analisador de SEO — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Analisador de SEO',
    'description' => 'Ferramenta gratuita para os visitantes analisarem qualquer site: indexação, conteúdo, velocidade real do Google (Core Web Vitals), celular, imagens, links, segurança, aparência no Google e nas redes e busca por IA. Gera nota, plano de ação, resumo em linguagem simples com o Claude e relatório em PDF — e cada análise pode virar um lead.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'lupa',
    'settings'    => [
        'base'          => ['type' => 'text', 'label' => 'Endereço da página do analisador', 'default' => 'analisador-seo', 'help' => 'Os relatórios ficam em /ENDEREÇO/relatorio/código/. Crie uma página com esse endereço e coloque nela o bloco "Analisador de SEO".'],
        'lead_mode'     => ['type' => 'select', 'label' => 'Quando pedir o contato', 'default' => 'depois', 'options' => [
            'depois' => 'Depois da análise: mostra a nota e os 3 principais problemas, e libera o relatório completo com o contato',
            'antes'  => 'Antes da análise: só analisa depois do contato',
            'nunca'  => 'Não pedir: relatório completo liberado (o contato fica opcional)',
        ]],
        'lead_fields'   => ['type' => 'select', 'label' => 'Campos do contato', 'default' => 'nome_whatsapp_email', 'options' => [
            'nome_whatsapp_email' => 'Nome, WhatsApp e e-mail', 'nome_whatsapp' => 'Nome e WhatsApp', 'nome_email' => 'Nome e e-mail',
        ]],
        'consent_text'  => ['type' => 'text', 'label' => 'Texto do aceite', 'default' => 'Aceito receber o relatório e o contato de um especialista.'],
        'pagespeed'     => ['type' => 'toggle', 'label' => 'Medir a velocidade com o Google PageSpeed (celular e computador)', 'default' => true, 'help' => 'Leva de 20 a 60 segundos a mais. A chave da API fica em Analisador de SEO → Configurações.'],
        'subpages'      => ['type' => 'number', 'label' => 'Páginas internas conferidas', 'default' => 6, 'help' => 'De 0 a 12. Mais páginas = análise mais completa e mais demorada.'],
        'ai'            => ['type' => 'toggle', 'label' => 'Resumo e prioridades em linguagem simples com o Claude', 'default' => true, 'help' => 'Usa a chave do Claude conectada ao site (custa centavos por análise).'],
        'ai_model'      => ['type' => 'select', 'label' => 'Modelo do Claude para o resumo', 'default' => 'claude-haiku-5-5', 'options' => [
            'claude-haiku-5-5' => 'Claude Haiku 5.5 (rápido e barato — recomendado)', 'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (texto mais elaborado)',
        ]],
        'rate_limit'    => ['type' => 'number', 'label' => 'Máximo de análises por visitante por hora', 'default' => 8],
        'agency_name'   => ['type' => 'text', 'label' => 'Nome que assina os relatórios', 'default' => '', 'help' => 'Vazio = nome do site.'],
        'whatsapp'      => ['type' => 'tel', 'label' => 'WhatsApp para o botão do relatório', 'default' => '', 'help' => 'Vazio = WhatsApp das configurações do site.'],
        'wa_message'    => ['type' => 'text', 'label' => 'Mensagem do WhatsApp', 'default' => 'Olá! Fiz a análise do meu site ({site}) e tirei nota {nota}. Quero ajuda para melhorar.', 'help' => '{site} e {nota} são trocados pelos dados da análise.'],
        'cta_title'     => ['type' => 'text', 'label' => 'Título da chamada no fim do relatório', 'default' => 'Quer que a gente resolva isso para você?'],
        'cta_text'      => ['type' => 'textarea', 'label' => 'Texto da chamada', 'rows' => 3, 'default' => 'Nossa equipe corrige os problemas deste relatório, deixa o site rápido e preparado para o Google e para as buscas por IA. O diagnóstico com um especialista é gratuito.'],
        'cta_button'    => ['type' => 'text', 'label' => 'Botão da chamada', 'default' => 'Falar com um especialista'],
    ],
    'boot'        => function (array $mod) {
        require_once __DIR__ . '/fetch.php';
        require_once __DIR__ . '/page.php';
        require_once __DIR__ . '/site.php';
        require_once __DIR__ . '/checks.php';
        require_once __DIR__ . '/engine.php';
        require_once __DIR__ . '/report.php';
        require_once __DIR__ . '/pdf.php';
        require_once __DIR__ . '/front.php';
        require_once __DIR__ . '/admin.php';
        s7x_seo_boot();
        s7x_seo_admin_boot();
    },
];
