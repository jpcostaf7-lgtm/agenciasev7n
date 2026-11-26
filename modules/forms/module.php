<?php
/**
 * Módulo: Formulário e leads — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Formulário e leads',
    'description' => 'Adiciona o bloco "Formulário de contato". As mensagens chegam por e-mail e ficam guardadas na tela Leads, com exportação para planilha.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'telefone',
    'settings'    => [
        'recipients' => ['type' => 'text', 'label' => 'E-mails que recebem os leads', 'default' => '', 'placeholder' => 'contato@dominio.com.br, outro@dominio.com.br', 'help' => 'Separe vários e-mails por vírgula. Vazio = os leads ficam só no painel.'],
        'subject'    => ['type' => 'text', 'label' => 'Assunto do e-mail', 'default' => 'Novo contato pelo site'],
        'from_name'  => ['type' => 'text', 'label' => 'Nome do remetente', 'default' => 'Site'],
        'from_email' => ['type' => 'email', 'label' => 'E-mail do remetente (opcional)', 'default' => '', 'help' => 'Use um e-mail do próprio domínio (ex.: site@seudominio.com.br) para não cair no spam. Vazio = no-reply@seudominio.'],
    ],
    'boot'        => function (array $mod) {
        require_once __DIR__ . '/forms.php';
        s7x_forms_boot($mod);
    },
];
