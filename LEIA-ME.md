# Sev7n Xpress 3.5.1 — painel base

CMS em PHP puro, sem banco de dados, com construtor de blocos, módulos e temas instaláveis pelo painel, no estilo do WordPress.
Este pacote vem com o tema **Inicial**. Para cada cliente, instale o painel e envie o tema dele em **Temas**.
Autor: João Costa

## Requisitos

- PHP 7.4 ou superior (testado em 8.3), com `gd` (WebP), `fileinfo`, `dom`, `mbstring` e `json`.
- Apache com `.htaccess` e `mod_rewrite` (padrão do cPanel).
- Recomendado: `max_input_vars` = 5000 no MultiPHP INI Editor (páginas com muitos blocos).
- Extensão `curl` e saída HTTPS liberada para `api.anthropic.com` e `*.googleapis.com` (assistente Claude e relatórios Google). Na maioria dos cPanel já vem assim.
- Não precisa de MySQL.

## Instalação no cPanel

1. Envie o conteúdo da pasta `sev7n-xpress/` para `public_html/`. Antes, ative "Mostrar arquivos ocultos" para enxergar os `.htaccess`.
2. Deixe as pastas `data/`, `uploads/` e `themes/` com permissão 755 (ou 775, se o painel não conseguir salvar).
3. Acesse `https://seudominio.com.br/admin/` e informe o nome do site, o usuário e a senha do administrador.
4. Em **Temas**, envie o `.zip` do tema do cliente e clique em **Ativar** (ou use o tema Inicial e ajuste cores e fonte em Configurações → Aparência).
5. Em **SEO e Google**, preencha a URL oficial do site (com https). Em **Dados de contato**, telefone, WhatsApp e endereço.
6. Em **Módulos → Formulário e leads → Configurar**, informe o e-mail que recebe os leads.
7. Envie `https://seudominio.com.br/sitemap.xml` no Google Search Console.

## Temas

- **Instalar:** Temas → Instalar tema → envie o `.zip`. O tema fica em `themes/<pasta>` e só vale depois de **Ativar**.
- **Atualizar:** envie o `.zip` de novo marcando “Atualizar se já existir”. A versão anterior fica em `data/theme-backups/` (as 3 últimas).
- **Ativar:** se as páginas ainda não foram editadas, o site passa a usar as páginas de demonstração do tema. Se já foram, você escolhe entre manter as páginas atuais ou trocar pelas de demonstração. A versão anterior fica em Revisões.
- **Segurança:** só administradores instalam temas. O ZIP é conferido (arquivos permitidos, caminhos, links simbólicos) e o `theme.php` é testado antes de instalar. Se um tema ativo der erro, o painel continua abrindo para trocar de tema.
- **Cores da marca:** o aviso de cookies e o painel usam as cores do site. O tema informa em `theme.php`:
  `'brand' => ['accent' => '#cor-dos-botões', 'dark' => '#cor-escura', 'ink' => '#cor-do-texto-no-botão']`.
  Sem `brand`, vale a "Cor do navegador no celular" (SEO e Google). Sem nenhuma das duas, ficam as cores padrão.
- **Criar temas:** veja `COMO-CRIAR-UM-TEMA.md`. É ali que eu (ou o Claude) transformo o layout de cada cliente em tema.

## Como o painel funciona

| Área | Função |
|---|---|
| Páginas | Lista de páginas (com o módulo Páginas extras). Criar, duplicar, excluir, rascunho e publicação |
| Editor de página | Blocos que você adiciona pela biblioteca, reordena arrastando ou com ↑ ↓, duplica, oculta e exclui. SEO próprio por página |
| Biblioteca de mídia | Upload com conversão para WebP, texto alternativo, aviso de uso antes de excluir |
| Leads | Mensagens do formulário de contato, com lido/não lido e exportação CSV (módulo Formulário e leads) |
| Configurações | Dados de contato, cabeçalho e rodapé, usados em todas as páginas |
| SEO e Google | Título, descrição, prévia do Google, Open Graph, schema.org, GA4, GTM, Meta Pixel |
| Módulos | Ativar, desativar e configurar módulos |
| Revisões e backup | Restaurar versões anteriores, exportar e importar tudo em .json |

## Perfis: Administrador e Editor

- **Administrador** acessa tudo, inclusive Módulos, Usuários, Revisões e as conexões com Google e Claude.
- **Editor** vê só o que o Administrador liberar em **Usuários e senha → Permissões**: cada área (páginas, mídia, SEO, dados de contato, cabeçalho e rodapé, leads, assistente, relatórios do Google…) e quais páginas pode editar (todas ou só as escolhidas). “Criar e publicar páginas” é separado de “Editar páginas”: sem ela, o Editor altera o conteúdo mas não muda endereço nem status.
- As permissões valem também para o Claude: o assistente e o conector agem com o perfil de quem está usando.

## Claude (módulo `modules/claude`)

**Assistente no painel** (menu Conteúdo → Assistente Claude)
1. Crie uma chave em console.anthropic.com (cobrança na sua conta Anthropic).
2. Em **Conectar o Claude**, cole a chave (fica em `data/secrets.json`, fora dos backups).
3. Converse: “troque o título da seção de segurança”, “quais leads chegaram hoje?”, “crie a página Sobre com…”. Cada resposta mostra o que foi alterado e um botão **Desfazer estas alterações**.
Modelo (Sonnet 5.5 por padrão, Opus 5.5 ou Haiku 5.5) e liga/desliga em Módulos → Claude → Configurar.

**Conector para Claude.ai, Claude Desktop e Claude Code** (protocolo MCP)
- Endereço do conector: `https://seudominio.com.br/mcp`
- Claude.ai/Desktop: Personalizar → Conectores → Adicionar conector personalizado → cole o endereço. O Claude abre o login do painel; entre com o seu usuário e autorize (OAuth 2.1 com PKCE).
- Claude Code: em Conectar o Claude, crie um token pessoal e rode o comando mostrado (`claude mcp add --transport http … --header "Authorization: Bearer …"`).
- Acessos ativos aparecem na mesma tela e podem ser revogados na hora.
- O `.htaccess` já repassa o cabeçalho `Authorization` para o PHP. Se o conector responder sempre “401”, confirme com a hospedagem que esse cabeçalho chega ao PHP.

Ferramentas que o Claude usa (respeitando permissões): visão geral do site, ler/alterar/adicionar/mover/excluir blocos, criar e alterar páginas, configurações, SEO, mídia, leads e os relatórios do Google.

## Google (módulos `google*`)

| Módulo | Sem conexão Google | Com conexão Google |
|---|---|---|
| Conexão Google | Carrega a tag gtag uma vez para todos | Login OAuth com a conta da empresa |
| Search Console | Meta tag de verificação | Cliques, impressões, CTR, posição, buscas e páginas; envio do sitemap |
| Analytics 4 | Tag GA4 + evento `generate_lead` a cada formulário | Usuários, sessões, páginas e origens do tráfego |
| AdSense | Código de anúncios, `ads.txt` automático, bloco “Anúncio AdSense” | Ganhos estimados, cliques e RPM |
| Google Ads | Tag + conversões de lead e de clique em telefone/WhatsApp | Investimento, cliques e conversões por campanha (exige developer token) |

Todas as tags passam pelo aviso de cookies (só carregam após o aceite).

**Conectar a conta Google (uma vez):** siga o passo a passo da tela **Conexão Google** — criar projeto no Google Cloud, ativar as APIs, configurar a tela de consentimento, criar o “ID do cliente OAuth” do tipo Aplicativo da Web com o URI de redirecionamento `https://seudominio.com.br/admin/google-callback.php`, colar Client ID e Client Secret e clicar em Conectar. Ao ativar outro módulo Google depois, clique em Reconectar.

Os relatórios ficam guardados por 60 minutos (ajustável) para não gastar a cota do Google; o botão Atualizar busca de novo.

## Módulos incluídos

- **Formulário e leads** (`modules/forms`): bloco "Formulário de contato" com aceite LGPD, máscara de telefone, proteção contra spam (campo oculto, tempo mínimo e limite por IP), e-mail de aviso e tela Leads. Bloco **Material para download (e-book)**: capa, benefícios e formulário curto; a pessoa deixa nome e e-mail, o download começa na hora e o contato entra em Leads com a origem (UTM).
- **Páginas extras** (`modules/pages`): várias páginas com endereço próprio (`/sobre`, `/servicos`…). Sem ele, o site tem só a página inicial.
- **Cookies LGPD** (`modules/cookies`): aviso com Aceitar/Recusar. GA4, GTM, Meta Pixel, AdSense, Google Ads e códigos extras só carregam depois do aceite. Link "Preferências de cookies" no rodapé.
- **Blog** (`modules/blog`): posts com editor (subtítulos, imagens, vídeo do YouTube, HTML), categorias, tags, agendamento, busca, RSS (`/feed/`), sitemap, dados estruturados de artigo e FAQ, "Mais lidas", posts relacionados e o bloco "Últimos posts do blog". Os endereços podem ficar iguais aos do WordPress (`/nome-do-post/`, `/category/x/`, `/tag/x/`). Tela **Importar do WordPress**: traz categorias, tags, posts e imagens pela API pública (`wp-json`), converte as imagens para WebP e redireciona os endereços antigos das imagens.
- **Artigos com IA** (`modules/blog-ia`, precisa do Blog): gera artigos com o Claude (um por vez, em lote ou todos os dias nos horários definidos, revezando categorias), com fotos do Pixabay, SEO (palavra-chave, título, meta descrição, sumário, links internos, fonte oficial e FAQ), quadro "Leia também na nossa rede" e importação automática de artigos de outro site da rede.
- **Analisador de SEO** (`modules/seo-analyzer`): ferramenta pública para o visitante analisar qualquer site (bloco "Analisador de SEO"). Confere mais de 60 itens em 10 áreas — indexação, conteúdo e palavra-chave, velocidade e Core Web Vitals pelo Google PageSpeed (celular e computador, com dados reais do Chrome), celular e acessibilidade, imagens, links quebrados, segurança, aparência no Google e no WhatsApp, busca por IA (robôs de IA, llms.txt, dados estruturados) e medição. Gera nota de 0 a 100, plano de ação, resumo em linguagem simples com o Claude, link compartilhável (`/analisador-seo/relatorio/código/`) e PDF. No painel, o botão **PDF** de cada análise abre o relatório para o cliente em A4: capa com a marca da agência, resumo, plano de ação, lista de correções, velocidade, todos os itens e página de contato, com número de página. Salve com Ctrl+P → Salvar como PDF (o endereço `/relatorio/código/pdf/` só abre para quem está logado). **Nota (régua 2, versão 3.5.1):** "atenção" vale 40% do item; a nota de cada área é curvada para que áreas meio certas não pareçam boas; erros graves tiram pontos; e problemas como falta de cadeado, de versão para celular ou velocidade ruim limitam a nota máxima. Sem a chave do PageSpeed, a nota vai no máximo até 84 e o relatório avisa. Análises antigas são recalculadas sozinhas. Pode pedir o contato antes, depois (nota e 3 problemas grátis, relatório completo com o contato) ou nunca; cada contato vira lead. Tela **Analisador de SEO** com as análises, contatos, exportação e a chave da API PageSpeed. Protegido contra uso abusivo (limite por visitante e bloqueio de endereços internos do servidor).
- **Anúncios MGID** (`modules/mgid`): anúncios no topo, depois do parágrafo N, no meio e no fim dos posts, bloco "Anúncio MGID" e linhas no `ads.txt`.
- **Claude** (`modules/claude`) e **Google** (`modules/google`, `google-search-console`, `google-analytics`, `google-adsense`, `google-ads`): veja as seções acima. Os módulos Google dependem da Conexão Google; ativar um deles ativa a conexão junto.

Ao desativar um módulo, os dados e os blocos dele ficam guardados e voltam ao reativar.

**Chave da API PageSpeed (Analisador de SEO):** sem chave, o Google divide uma cota pequena entre todo mundo e costuma recusar a medição. Crie uma chave gratuita em console.cloud.google.com (ative a *PageSpeed Insights API* → Credenciais → Chave de API) e cole em Analisador de SEO → Google PageSpeed. Uma chave serve para todos os sites.

## Tarefas agendadas (cron)

Artigos automáticos, importações e publicação de posts agendados rodam pelo `cron.php`. Sem configurar nada, eles rodam logo depois das visitas ao site (sem atrasar o visitante). Para ficar pontual mesmo sem visitas, crie no cPanel → **Cron Jobs** uma tarefa a cada 5 minutos:

```
php /home/USUARIO/public_html/cron.php
```

O comando exato (e a opção com `curl` e chave) aparece em Artigos com IA → Automação.

## Estrutura

```
index.php          roteador do site (páginas, 404, envio de formulários)
sitemap.php        /sitemap.xml com todas as páginas publicadas
robots.php         /robots.txt
admin/             painel
cms/               núcleo (config.php, core.php, lib/, blocks/ básicos, admin_lib.php)
modules/           um módulo por pasta
themes/            um tema por pasta (inicial vem junto)
data/              conteúdo (JSON), usuários, revisões, leads, segredos — bloqueada para a web
uploads/           imagens enviadas
```

## Reaproveitar em outro projeto

Instale este mesmo pacote no servidor do novo cliente e envie o tema dele pela tela Temas. O núcleo (`cms/`, `admin/`, `modules/`) é idêntico em todos os sites, então dá para atualizar o painel de todos substituindo essas três pastas.

### Criar um bloco no tema

Em `themes/<tema>/theme.php`, dentro de `'blocks'`:

```php
'depoimentos' => [
    'label' => 'Depoimentos', 'icon' => 'coracao',
    'description' => 'Grade de depoimentos de clientes.',
    'fields' => [
        'title' => ['type' => 'text', 'label' => 'Título', 'default' => 'O que dizem'],
        'items' => ['type' => 'repeater', 'label' => 'Depoimentos', 'item_label' => 'name', 'fields' => [
            'name' => ['type' => 'text', 'label' => 'Nome'],
            'text' => ['type' => 'textarea', 'label' => 'Depoimento'],
        ]],
    ],
],
```

E crie `themes/<tema>/blocks/depoimentos.php`. No template ficam disponíveis `$b` (dados do bloco), `$id` (atributo id da âncora), `$block` e `$page`:

```php
<section class="blk"<?= $id ?>>
  <h2><?= e($b['title']) ?></h2>
  <?php foreach ($b['items'] as $d): ?><blockquote><?= nl($d['text']) ?><cite><?= e($d['name']) ?></cite></blockquote><?php endforeach; ?>
</section>
```

Tipos de campo: `text`, `textarea`, `richtext`, `image`, `url`, `email`, `tel`, `color`, `toggle`, `select` (com `options`), `icon`, `number`, `code`, `repeater` (com `fields`). Opções úteis: `default`, `help`, `max` (contador), `h1` (marca o título principal para a análise de SEO) e `faq` (no repetidor com `question`/`answer`, gera FAQ para o Google).

Para mudar o HTML de um bloco básico, crie `blocks/<tipo>.php` dentro do tema (ex.: `themes/inicial/blocks/faq.php`).

### Criar um módulo

Crie `modules/meu-modulo/module.php`:

```php
<?php
if (!defined('CMS_ROOT')) { exit; }
return [
    'name' => 'Meu módulo', 'description' => 'O que ele faz.', 'version' => '1.0.0', 'author' => 'João Costa', 'icon' => 'folha',
    'settings' => [   // aparecem em Módulos → Configurar
        'texto' => ['type' => 'text', 'label' => 'Texto', 'default' => 'Olá'],
    ],
    'boot' => function (array $mod) {
        cms_register_block('meu-bloco', [ 'label' => 'Meu bloco', 'template' => __DIR__ . '/block.php', 'fields' => [ /* ... */ ] ]);
        cms_add_action('body_close', function ($page) { echo '<!-- ' . e(cms_module_get('meu-modulo', 'texto')) . ' -->'; });
        cms_register_admin_page('minha-tela', ['label' => 'Minha tela', 'icon' => 'folha', 'render' => function () { echo '<section class="panel">Olá</section>'; }]);
    },
];
```

Ganchos disponíveis: `init`, `head`, `body_open`, `body_close`, `footer_links`, `request`, `request:<acao>` (POST com `_s7x_action`), `ai_tools`, `admin_head`, `admin_footer`, `admin_dashboard`, `admin_api:<acao>`. Filtros: `tracking_html`, `gtag_ids`, `multi_pages`, `settings_sections`, `structured_data_business`, `permissions`, `http_request`.

Outras peças para módulos:
- `cms_register_admin_page($slug, [... 'perm' => 'page.slug', 'group' => 'Google', 'admin_only' => false, 'order' => 50])` — a tela entra automaticamente na lista de permissões do Editor.
- `cms_register_ai_tool($nome, ['description', 'input_schema', 'perm', 'write', 'run'])` — a ferramenta aparece no assistente e no conector MCP.
- `cms_can('permissao', $idDaPagina)`, `cms_secret_get/set()` (segredos fora do backup), `cms_http()` (requisições HTTP).
- No manifesto do módulo: `'requires' => ['outro-modulo']` e, para módulos Google, `'google_scopes' => [...]`.

## Conferir antes de publicar

- **Política de Privacidade**: o tema traz um modelo geral. Revise com o cliente (e, se possível, com um advogado).
- **E-mail dos leads**: sem e-mail configurado, os leads ficam só no painel. Use um remetente do próprio domínio.
- **Conector do Claude e login Google** só funcionam com o site no endereço final com HTTPS.
