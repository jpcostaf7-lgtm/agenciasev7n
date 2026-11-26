# Instruções para ajustes neste repositório

- Site principal da Agência Sev7n (agenciasev7n.com.br) no Sev7n Xpress (PHP 8.2, sem banco de dados). Autor nos cabeçalhos: João Costa.
- Publicação: cada push na branch `main` é implantado sozinho pela Hostinger em `public_html`. Só envie código testado.
- `data/` e `uploads/` ficam só no servidor (conteúdo do painel, leads, análises de SEO, chaves de API, imagens). Nunca coloque arquivos dessas pastas no Git, além do `.htaccess` e do `index.html`.
- Ajustes de visual e de blocos: `themes/sev7n/` (`layout.php`, `blocks/<tipo>.php`, `assets/css/site.css`, `assets/js/site.js`).
- Campos dos blocos, configurações, cores da marca (`brand`) e páginas iniciais: `themes/sev7n/theme.php` e `themes/sev7n/paginas/*.php`. As páginas iniciais só valem enquanto a página não foi salva no painel; depois o conteúdo vive em `data/content.json`, no servidor.
- `cms/`, `admin/` e `modules/` são o núcleo compartilhado entre os sites (inclui o analisador de SEO em `modules/seo-analyzer/`). Melhorias no núcleo valem para todos os sites: mantenha a versão em `cms/core.php` e o `LEIA-ME.md` atualizados.
- Cores: noite `#0B0D13`, laranja `#F2611D` → `#F7932F` (texto escuro `#160B03` sobre o laranja). Fontes: Bricolage Grotesque (títulos) e Figtree (texto).
- Imagens novas: WebP de até 1600 px em `themes/sev7n/assets/`.
- Antes de enviar: `php -l` nos arquivos alterados e conferir as páginas com `php -S` (celular e computador).
