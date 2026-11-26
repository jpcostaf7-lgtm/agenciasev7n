<?php
/**
 * Módulo: Claude (assistente + conector MCP) — Sev7n Xpress
 * Autor: João Costa
 */
if (!defined('CMS_ROOT')) {
    exit;
}

return [
    'name'        => 'Claude',
    'description' => 'Assistente Claude dentro do painel para editar páginas, SEO e leads conversando. Inclui um conector (MCP) para gerenciar o site direto pelo Claude.ai, Claude Desktop ou Claude Code, sempre com as permissões do usuário que conectou.',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'icon'        => 'neuronio',
    'settings'    => [
        'model'        => ['type' => 'select', 'label' => 'Modelo do assistente', 'default' => 'claude-sonnet-5-5', 'options' => [
            'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (recomendado: rápido e capaz)',
            'claude-opus-5-5'   => 'Claude Opus 5.5 (mais capaz, mais caro)',
            'claude-haiku-5-5'  => 'Claude Haiku 5.5 (mais barato, para tarefas simples)',
        ]],
        'max_tokens'   => ['type' => 'number', 'label' => 'Tamanho máximo de cada resposta (tokens)', 'default' => 4096],
        'chat_enabled' => ['type' => 'toggle', 'label' => 'Ativar o assistente no painel', 'default' => true],
        'mcp_enabled'  => ['type' => 'toggle', 'label' => 'Ativar o conector para Claude.ai / Claude Code (MCP)', 'default' => true],
    ],
    'boot'        => function (array $mod) {
        require_once __DIR__ . '/claude.php';
        require_once __DIR__ . '/oauth.php';
        require_once __DIR__ . '/mcp.php';
        s7x_claude_boot($mod);
    },
];
