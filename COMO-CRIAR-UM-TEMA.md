# Como criar um tema para o Sev7n Xpress

Autor: João Costa

Um tema é uma pasta com o visual de um site. O painel (páginas, blocos, mídia, SEO, módulos, usuários) é sempre o mesmo; o tema define a aparência, os blocos próprios do site e o conteúdo de demonstração.

## Estrutura

```
meu-tema/
  theme.php          obrigatório: manifesto (nome, configurações, blocos, páginas de demonstração)
  layout.php         obrigatório: HTML do cabeçalho e do rodapé, chamando os blocos da página
  functions.php      opcional: funções auxiliares do tema
  blocks/            HTML dos blocos próprios (blocks/<tipo>.php)
  assets/css/        CSS
  assets/js/         JavaScript
  assets/img/        imagens padrão (aparecem na Biblioteca de mídia como "imagem do tema")
  assets/fonts/      fontes
  screenshot.webp    opcional: prévia na tela Temas (1200 × 750 px)
```

Para instalar: compacte a pasta (`meu-tema.zip`, com `meu-tema/theme.php` dentro) e envie em **Painel → Temas → Instalar tema**. O nome da pasta vira o identificador do tema. Para atualizar, envie de novo marcando “Atualizar se já existir” (a versão anterior fica em `data/theme-backups/`).

Arquivos aceitos no ZIP: php, css, js, json, map, png, jpg, webp, avif, gif, svg, ico, woff, woff2, ttf, otf, eot, txt, md. Arquivos ocultos (como `.htaccess`) são recusados.

## theme.php

Só retorna um array. Não declare funções aqui (use `functions.php`).

```php
<?php
if (!defined('CMS_ROOT')) { exit; }
$img = 'themes/' . basename(__DIR__) . '/assets/img/';   // caminho das imagens padrão

return [
    'name'        => 'Nome do tema',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'description' => 'Uma frase sobre o tema.',
    'default_modules' => ['forms', 'pages', 'cookies', 'claude'],   // sugeridos ao ativar

    'settings' => [        // telas em Configurações (dados globais)
        'contato' => ['label' => 'Dados de contato', 'icon' => 'telefone', 'fields' => [
            'phone'    => ['type' => 'tel', 'label' => 'Telefone'],
            'whatsapp' => ['type' => 'tel', 'label' => 'WhatsApp'],
            'whatsapp_message' => ['type' => 'text', 'label' => 'Mensagem inicial', 'default' => 'Olá!'],
        ]],
    ],

    'brand' => ['accent' => '#B01D90', 'dark' => '#24102A', 'ink' => '#FFFFFF'], // cores do aviso de cookies e do painel
    'seo_defaults' => [ 'favicon' => ['src' => $img . 'favicon.png', 'alt' => ''], 'og_image' => ['src' => $img . 'og.jpg', 'alt' => ''] ],

    'blocks' => [          // blocos próprios: HTML em blocks/<tipo>.php
        'hero' => ['label' => 'Topo', 'icon' => 'gota', 'description' => 'Primeira dobra.', 'fields' => [
            'title' => ['type' => 'textarea', 'label' => 'Título (H1)', 'h1' => true, 'default' => 'Título'],
            'image' => ['type' => 'image', 'label' => 'Imagem', 'default' => ['src' => $img . 'topo.webp', 'alt' => '']],
        ]],
    ],

    'default_pages' => [   // conteúdo de demonstração
        ['id' => 'home', 'title' => 'Página inicial', 'home' => true, 'blocks' => [
            ['type' => 'hero', 'anchor' => 'topo'],
            ['type' => 'faq', 'anchor' => 'duvidas', 'data' => ['title' => 'Dúvidas']],
        ]],
    ],
];
```

Convenção: use a seção `contato` com os campos `phone`, `whatsapp` e `whatsapp_message`. O bloco básico “Chamada para ação” usa esses campos quando o link do botão é `whatsapp`.

## layout.php

```php
<?php if (!defined('CMS_ROOT')) { exit; }
require_once __DIR__ . '/functions.php';
$page = $GLOBALS['cms_current_page'];
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php cms_head($page); ?>                       <!-- título, SEO, Open Graph, dados estruturados, tags -->
<link rel="stylesheet" href="<?= e(cms_theme_url('assets/css/site.css')) ?>">
</head>
<body>
<?php cms_body_open(); ?>
<header>…menu com c('geral.menu')…</header>
<main><?php cms_render_blocks($page); ?></main>
<footer>… <?php cms_do_action('footer_links'); ?> …</footer>  <!-- link "Preferências de cookies" -->
<?php cms_body_close(); ?>                      <!-- aviso de cookies, scripts de módulos -->
</body>
</html>
```

## Template de bloco (blocks/<tipo>.php)

Variáveis disponíveis: `$b` (dados do bloco), `$id` (atributo id da âncora, já pronto), `$block`, `$page`.

```php
<?php if (!defined('CMS_ROOT')) { exit; } ?>
<section class="blk"<?= $id ?>>
  <h1><?= nl($b['title']) ?></h1>
  <?php img($b['image'], ['loading' => 'eager']); ?>
</section>
```

## Funções úteis

| Função | Uso |
|---|---|
| `e($v)` / `nl($v)` | texto escapado / com quebras de linha |
| `cms_rich($html)` | HTML de campo richtext |
| `img($valor, $atributos)` | `<img>` com alt, largura e altura |
| `c('secao.campo')` / `t('secao.campo')` | valor de configuração global / impresso |
| `cms_link($url)` | links do painel (`/pagina`, `#ancora`, externos) |
| `cms_theme_url('assets/…')` | arquivo do tema com versão para cache |
| `cms_icon('nome')` | ícone SVG (dor, mente, neuronio, sono, inflamacao, escudo, folha, gota, coracao, pulso, estetoscopio, calendario, telefone, local, whatsapp, relogio, check) |
| `cms_site_name()` | nome do site |
| `cms_whatsapp_link($tel, $msg)` / `cms_tel_link($tel)` | links de contato |

## Blocos básicos e de módulos

Estes blocos funcionam em qualquer tema; o tema só precisa estilizar as classes:

| Bloco | Classes |
|---|---|
| Título da página | `.blk-page-header`, `.blk-page-header__lead` |
| Texto | `.blk-text`, `.blk-text__inner`, `.prose` |
| Texto + imagem | `.blk-textimg`, `__grid`, `__media`, `__text`, `.blk-textimg--img-direita`, `.pull` |
| Perguntas frequentes | `.blk-faq`, `__grid`, `__list`, `__answer` |
| Chamada para ação | `.blk-cta`, `__inner`, `.btn--cta` |
| Formulário de contato | `.blk-form`, `__grid`, `__intro`, `.s7x-form`, `.s7x-field`, `.s7x-consent`, `.s7x-form__msg` (`.is-ok`, `.is-error`) |
| Anúncio AdSense | `.blk-ad`, `.blk-ad__label` |
| Fundos | `.blk--branco`, `.blk--claro`, `.blk--escuro`, `.blk--destaque` |

Para trocar o HTML de um bloco básico, crie `blocks/<tipo>.php` no tema (ex.: `blocks/faq.php`).

O aviso de cookies aceita as variáveis CSS `--cookie-accent`, `--cookie-ink`, `--cookie-bg` e `--cookie-offset`.

## Exemplos prontos

- `themes/inicial`: tema genérico, com cores e fonte configuráveis em Configurações → Aparência.
- Tema Dr. Carlos Abdo Arbache (`tema-arbache.zip`): exemplo de tema sob medida com blocos próprios.
