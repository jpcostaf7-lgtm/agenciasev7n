<?php
/** Acessar meu projeto — Autor: João Costa */
if (!defined('CMS_ROOT')) { exit; }
return ['id' => 'meu-projeto', 'title' => 'Acessar meu projeto', 'slug' => 'meu-projeto', 'seo' => [
    'title' => 'Acessar Meu Projeto | Agência Sev7n',
    'description' => 'Área dos clientes da Agência Sev7n: informe o nome do projeto e acompanhe o andamento do seu site, sistema ou loja virtual.',
    'noindex' => false,
], 'blocks' => [
    ['type' => 'projeto', 'data' => []],
]];
