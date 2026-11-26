# Agência Sev7n — como colocar o site novo no ar

Site em Sev7n Xpress 3.4 com o tema **Agência Sev7n** e os módulos Analisador de SEO, Formulário e leads (com o e-book), Páginas, Cookies LGPD, Google (Analytics e Search Console) e Claude.

Autor: João Costa

## O que já vem pronto

- **Páginas:** home, Criação de sites no Rio de Janeiro, Quanto custa, Analisador de SEO, E-book, Meu projeto e Política de privacidade, nos mesmos endereços do site atual.
- **Endereços que saíram:** `/bio` e `/painel-seo` levam para a home, e as URLs antigas do WordPress também redirecionam. A lista fica em Configurações → Cabeçalho e rodapé → Redirecionamentos.
- **Google:** Google Analytics `G-MNP1BHLJZS` e a verificação do Search Console, os mesmos do site atual.
- **E-book novo:** está em `themes/sev7n/assets/ebook/o-digital-nao-e-mais-opcao.pdf`. Cada download vira um lead.
- **Para as IAs:** `/llms.txt` com o resumo da agência. Pode editar em Cabeçalho e rodapé.

## 1. Hospedagem

O site atual está no Lovable, que não roda PHP. O site novo precisa de uma hospedagem com PHP 8.2, como a Hostinger.

1. Na hospedagem, crie o site `agenciasev7n.com.br` com PHP 8.2.
2. Envie o conteúdo da pasta `agenciasev7n/` para `public_html`. Inclua o `.htaccess` e as pastas `data/` e `uploads/`.
3. Abra `https://agenciasev7n.com.br/admin/` (ou o endereço provisório da hospedagem) e crie o administrador.

## 2. DNS: cuidado com os subdomínios dos clientes

A página **Meu projeto** abre `nome-do-projeto.agenciasev7n.com.br`. Antes de mexer no DNS, anote todos os registros que existem hoje, principalmente os subdomínios dos projetos.

1. Troque só os registros do domínio principal (`@` e `www`) para o IP da nova hospedagem.
2. Deixe os subdomínios de projeto como estão.
3. Depois que o site novo estiver no ar, remova o domínio personalizado do projeto no Lovable.

## 3. Antes de desligar o painel antigo

O painel antigo (Supabase) guarda os **leads do e-book** e as estatísticas de visitas. Esses dados não vêm para o site novo. Entre em `/painel-seo/ebook-leads` no site antigo e exporte a lista **antes** de trocar o DNS.

## 4. Configurar no painel novo

- **Conectar o Claude:** cole a chave da API. Ela é usada no resumo em linguagem simples do analisador e custa centavos por análise.
- **Analisador de SEO → Google PageSpeed:** cole uma chave gratuita da *PageSpeed Insights API*, criada em console.cloud.google.com. **Sem essa chave o Google quase sempre recusa a medição de velocidade**, porque a cota sem chave é dividida entre todo mundo.
- **Módulos → Analisador de SEO:** escolha quando pedir o contato.
  - **Depois** (padrão): a nota e os 3 principais problemas são grátis; o relatório completo sai com o contato.
  - **Antes:** só analisa depois do contato.
  - **Nunca:** o relatório completo fica liberado.
- **Módulos → Formulário e leads:** e-mail que recebe os contatos (já vem `contato@agenciasev7n.com.br`).
- **Search Console:** envie `https://agenciasev7n.com.br/sitemap.xml`.

## PDF para o cliente

Em **Analisador de SEO**, cada análise tem o botão **PDF**. Ele abre o relatório em formato A4, com capa da agência, resumo, plano de ação, lista de correções, velocidade, todos os itens e página de contato. A janela de impressão abre sozinha:

1. Em "Destino", escolha **Salvar como PDF**.
2. Em "Mais definições", ative **Gráficos de plano de fundo**.
3. Clique em **Salvar**. O arquivo já sai com o nome do site e a data.

## Onde ficam os contatos

- **Leads:** todos os contatos em um lugar só, com origem e link do relatório. Entram aqui:
  - o formulário de orçamento;
  - os downloads do e-book;
  - quem pediu o relatório completo do analisador.
- **Analisador de SEO:** todas as análises feitas, com nota, contato, origem (UTM) e quantas vezes o link do relatório foi aberto. Botão **Refazer** e exportação em planilha.
