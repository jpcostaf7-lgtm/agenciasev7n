<?php
/**
 * Sev7n Xpress — configuração do projeto
 * Autor: João Costa
 *
 * Ajuste estes valores a cada novo projeto. O conteúdo editável
 * fica definido em cms/schema.php.
 */
return [
    // Nome exibido no topo do painel e na tela de login
    'cms_name'          => 'Sev7n Xpress',
    // Nome usado enquanto o "Nome do negócio" (SEO e Google) estiver vazio
    'project_name'      => '',

    // Fuso horário usado nas revisões e datas do painel
    'timezone'          => 'America/Sao_Paulo',

    // URL pública do site, sem barra no final (ex.: https://www.dominio.com.br).
    // Deixe vazio para detectar automaticamente.
    'site_url'          => '',

    // Tema usado quando nenhum foi ativado no painel (pasta dentro de themes/)
    'theme'             => 'sev7n',

    // Tamanho máximo do ZIP de tema enviado pelo painel
    'max_theme_mb'      => 40,

    // Uploads
    'max_upload_mb'     => 8,
    'image_max_width'   => 2000,   // imagens maiores são reduzidas
    'convert_webp'      => true,   // converte JPG/PNG para WebP (mais leve para SEO)
    'webp_quality'      => 82,

    // Segurança do login
    'login_max_attempts' => 5,     // tentativas erradas antes de bloquear
    'login_lock_minutes' => 15,
    'session_minutes'    => 240,   // tempo de sessão inativa

    // Quantas revisões do conteúdo manter
    'max_revisions'      => 30,
];
