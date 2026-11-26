# Agência Sev7n — site (agenciasev7n.com.br)

Site da Agência Sev7n em **Sev7n Xpress** (PHP 8.2, sem banco de dados), com o tema `themes/sev7n`.
Autor: João Costa

## Como o site é publicado

O GitHub guarda o **código** (painel, módulos e tema). A hospedagem puxa o código do GitHub sozinha a cada atualização (implantação automática).

O que fica **só no servidor** e não vai para o GitHub (veja `.gitignore`):

- `data/`: textos editados no painel, leads, análises de SEO, chaves de API e sessões.
- `uploads/`: imagens enviadas pelo painel.

Por isso, atualizar o código nunca apaga o conteúdo nem os contatos do site.

## Onde mexer

- **Visual e blocos:** `themes/sev7n/` (`layout.php`, `blocks/`, `assets/css/site.css`, `assets/js/site.js`).
- **Campos, configurações e páginas iniciais:** `themes/sev7n/theme.php` e `themes/sev7n/paginas/`.
- **Núcleo** (`cms/`, `admin/`, `modules/`): compartilhado entre todos os sites. Atualize pelo pacote oficial do Sev7n Xpress.
