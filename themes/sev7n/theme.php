<?php
/**
 * Tema: Agência Sev7n (agenciasev7n.com.br)
 * Autor: João Costa
 *
 * Agência digital do Rio de Janeiro: sites, lojas virtuais, sistemas e SEO.
 * Fundo noite (#0B0D13) com o laranja da marca (#F7932F → #F2611D), Bricolage Grotesque
 * nos títulos e Figtree no texto. O topo da home já é o Analisador de SEO: a pessoa digita o
 * site e vê a nota — é a principal porta de entrada de clientes.
 * Páginas iniciais em paginas/*.php.
 */
if (!defined('CMS_ROOT')) {
    exit;
}
require_once __DIR__ . '/functions.php';

$a    = 'themes/' . basename(__DIR__) . '/assets/';
$img  = $a . 'img/';
$port = $a . 'fotos/portfolio/';

$linkHelp = 'Use /pagina, #secao ou https://… Para o WhatsApp: "wa" (mensagem padrão) ou "wa: sua mensagem".';
$bg   = ['type' => 'select', 'label' => 'Fundo', 'options' => ['noite' => 'Escuro', 'grafite' => 'Escuro (mais claro)', 'papel' => 'Claro'], 'default' => 'noite'];
$icon = ['type' => 'select', 'label' => 'Ícone', 'options' => ['' => 'Sem ícone'] + sv_icons(), 'default' => ''];

$portfolio = [
    ['name' => 'Bestane Urologia', 'category' => 'Site institucional', 'url' => 'https://bestane-urologia.com.br', 'image' => ['src' => $port . 'bestane.webp', 'alt' => 'Site da Bestane Urologia'], 'text' => 'Clínica de urologia com equipe, exames, cirurgias e resultado de exames on-line.', 'tags' => 'Saúde, WordPress'],
    ['name' => 'BVI Leilões', 'category' => 'Site + sistema', 'url' => 'https://bvileiloes.com.br', 'image' => ['src' => $port . 'bvileiloes.webp', 'alt' => 'Site da BVI Leilões'], 'text' => 'Imobiliária de leilões da Caixa com catálogo de imóveis, área administrativa e assessoria on-line.', 'tags' => 'Imobiliária, Sistema'],
    ['name' => 'Jequitibá Investimentos', 'category' => 'Site institucional', 'url' => 'https://jequitibainvestimentos.com.br', 'image' => ['src' => $port . 'jequitiba.webp', 'alt' => 'Site da Jequitibá Investimentos'], 'text' => 'Investimentos em precatórios com design premium, vídeo e foco em captação de leads.', 'tags' => 'Finanças, Conversão'],
    ['name' => 'Sheltermar', 'category' => 'Site + EAD + loja', 'url' => 'https://sheltermar.com.br', 'image' => ['src' => $port . 'sheltermar.webp', 'alt' => 'Site da Sheltermar'], 'text' => 'Escola de cursos marítimos com loja, área do aluno e plataforma de ensino a distância.', 'tags' => 'Educação, E-commerce'],
    ['name' => 'Ladislau Assessoria Contábil', 'category' => 'Site institucional', 'url' => 'https://ladislauassessoriacontabil.com.br', 'image' => ['src' => $port . 'ladislau.webp', 'alt' => 'Site da Ladislau Assessoria Contábil'], 'text' => 'Escritório de contabilidade no Rio com design sóbrio, foco em autoridade e captação de clientes.', 'tags' => 'Contabilidade, WordPress'],
    ['name' => 'Escovei', 'category' => 'Site + plugin de unidades', 'url' => 'https://escovei.com.br', 'image' => ['src' => $port . 'escovei.webp', 'alt' => 'Site da Escovei'], 'text' => 'Rede de salões com franquias, mapa de unidades com filtro por região e agendamento.', 'tags' => 'Franquia, Beleza'],
    ['name' => 'Reboque Ferreira 24h', 'category' => 'Site com SEO local', 'url' => 'https://reboqueferreira24horas.com.br', 'image' => ['src' => $port . 'reboque.webp', 'alt' => 'Site do Reboque Ferreira 24h'], 'text' => 'Reboque 24 horas em São Gonçalo e Niterói, feito para aparecer no Google e gerar ligação.', 'tags' => 'Serviços, SEO local'],
    ['name' => 'WVS Arquitetura', 'category' => 'Site institucional', 'url' => 'https://www.wvsarquitetura.com.br', 'image' => ['src' => $port . 'wvs.webp', 'alt' => 'Site da WVS Arquitetura'], 'text' => 'Escritório de arquitetura com portfólio visual e design sofisticado.', 'tags' => 'Arquitetura, Portfólio'],
    ['name' => 'Fundação Fujitega', 'category' => 'Site institucional', 'url' => 'https://fujitega.com', 'image' => ['src' => $port . 'fujitega.webp', 'alt' => 'Site da Fundação Fujitega'], 'text' => 'Fundação e clínica de quiropraxia com agendamento on-line e várias áreas de atuação.', 'tags' => 'Saúde, Fundação'],
    ['name' => 'VendPerto', 'category' => 'Site institucional', 'url' => 'https://vendperto.com.br', 'image' => ['src' => $port . 'vendperto.webp', 'alt' => 'Site da VendPerto'], 'text' => 'Minimercados autônomos em condomínios, com apresentação da franquia e contato direto.', 'tags' => 'Varejo, Franquia'],
    ['name' => 'Jequitibá App', 'category' => 'Sistema web', 'url' => 'https://app.jequitibainvestimentos.com.br', 'image' => ['src' => $port . 'jequitiba-app.webp', 'alt' => 'Sistema Jequitibá App'], 'text' => 'Plataforma de investimentos com login seguro, painel do cliente e controle de precatórios.', 'tags' => 'Sistema, Fintech'],
    ['name' => 'Credtools', 'category' => 'Sistema web', 'url' => 'https://credtools.com.br', 'image' => ['src' => $port . 'credtools.webp', 'alt' => 'Sistema Credtools'], 'text' => 'Gestão de crédito com autenticação, painel administrativo e ferramentas de análise.', 'tags' => 'Sistema, Fintech'],
    ['name' => 'Shelter CRM', 'category' => 'Sistema web', 'url' => 'https://sapsheltermar.com.br', 'image' => ['src' => $port . 'shelter.webp', 'alt' => 'Sistema Shelter CRM'], 'text' => 'CRM com leads, financeiro, automação de marketing e plataforma de cursos integrada.', 'tags' => 'Sistema, CRM'],
];

$depoimentos = [
    ['name' => 'Italo Santos', 'company' => 'Cross Training Vila Madá', 'text' => 'O João junto com sua equipe conseguiu captar exatamente a ideia que tínhamos. Uma pegada mais leve e colorida. Estamos muito satisfeitos nessa parceria que já dura mais de 4 anos.'],
    ['name' => 'Letícia', 'company' => 'Alumiar Casa de Cultura', 'text' => 'Criamos nosso e-commerce e de forma muito rápida já começamos a vender. Adicionamos a venda de cursos e seminários e também tivemos ótimos resultados.'],
    ['name' => 'Wellington', 'company' => 'Simulado de Vestibular', 'text' => 'Após muita dificuldade para transformar minha ideia em realidade, conheci a Agência Sev7n e os resultados não poderiam ser melhores! Além de um site sempre estável, as vendas têm crescimento constante.'],
    ['name' => 'Ana Cláudia', 'company' => '', 'text' => 'Toda nossa identidade visual física e digital foi desenvolvida pela Agência Sev7n. Depois de uma longa pesquisa e muitas conversas fechamos a parceria e estamos extremamente satisfeitos.'],
    ['name' => 'Vanessa', 'company' => 'Dellas Depilação e Estética', 'text' => 'Conheci o João e a Agência Sev7n por indicação de um amigo. Todos sempre foram muito solícitos e prontamente atendem todas as necessidades que tenho.'],
];

$pagina = static function (string $file) use ($a, $img, $port, $portfolio, $depoimentos) {
    return include __DIR__ . '/paginas/' . $file . '.php';
};

return [

    'name'        => 'Agência Sev7n',
    'version'     => '1.0.0',
    'author'      => 'João Costa',
    'description' => 'Site da Agência Sev7n: criação de sites, lojas virtuais, sistemas e SEO no Rio de Janeiro, com o Analisador de SEO gratuito como porta de entrada de clientes.',

    'default_modules' => ['forms', 'pages', 'seo-analyzer', 'cookies', 'google', 'google-analytics', 'google-search-console', 'claude'],

    /* ------------------------------------------------------------------
     * Configurações globais
     * --------------------------------------------------------------- */
    'settings' => [
        'contato' => [
            'label' => 'Dados de contato', 'icon' => 'telefone',
            'description' => 'WhatsApp, e-mail e redes usados no topo, no rodapé, nos botões e no relatório do analisador.',
            'fields' => [
                'whatsapp' => ['type' => 'tel', 'label' => 'WhatsApp', 'default' => '(21) 97271-4155'],
                'whatsapp_message' => ['type' => 'text', 'label' => 'Mensagem padrão do WhatsApp', 'default' => 'Olá! Vim pelo site da Agência Sev7n ({pagina}) e gostaria de um orçamento.', 'help' => '{pagina} vira o nome da página onde o botão foi clicado.'],
                'whatsapp_float' => ['type' => 'toggle', 'label' => 'Botão flutuante do WhatsApp', 'default' => true],
                'phone'    => ['type' => 'tel', 'label' => 'Telefone', 'default' => '(21) 97271-4155'],
                'email'    => ['type' => 'email', 'label' => 'E-mail', 'default' => 'contato@agenciasev7n.com.br'],
                'city'     => ['type' => 'text', 'label' => 'Cidade', 'default' => 'Rio de Janeiro – RJ'],
                'hours'    => ['type' => 'text', 'label' => 'Horário de atendimento', 'default' => 'Segunda a sexta, das 9h às 18h'],
                'instagram' => ['type' => 'url', 'label' => 'Instagram', 'default' => 'https://instagram.com/agenciasev7n'],
                'tiktok'    => ['type' => 'url', 'label' => 'TikTok', 'default' => 'https://www.tiktok.com/@agencia.sev7n'],
                'linkedin'  => ['type' => 'url', 'label' => 'LinkedIn', 'default' => ''],
            ],
        ],
        'geral' => [
            'label' => 'Cabeçalho e rodapé', 'icon' => 'folha',
            'description' => 'Marca, menu, botões do topo, rodapé, redirecionamentos e o arquivo para as IAs (llms.txt).',
            'fields' => [
                'logo'        => ['type' => 'image', 'label' => 'Símbolo da marca (V7)', 'default' => ['src' => $img . 'marca.webp', 'alt' => 'Agência Sev7n']],
                'menu'        => ['type' => 'textarea', 'label' => 'Menu', 'rows' => 8, 'default' => "Serviços | /#servicos\nPortfólio | /#portfolio\nComo trabalhamos | /#processo\nQuanto custa | /quanto-custa-criar-um-site-profissional/\nE-book grátis | /o-digital-nao-e-mais-opcao/", 'help' => 'Uma linha por item: Texto | link.'],
                'header_tool' => ['type' => 'text', 'label' => 'Botão do analisador no topo', 'default' => 'Analisar meu site', 'help' => 'Vazio = sem botão.'],
                'header_cta'  => ['type' => 'text', 'label' => 'Botão principal do topo', 'default' => 'Pedir orçamento'],
                'header_link' => ['type' => 'text', 'label' => 'Link do botão principal', 'default' => 'wa', 'help' => $linkHelp],
                'footer_text' => ['type' => 'textarea', 'label' => 'Texto do rodapé', 'rows' => 3, 'default' => 'Agência digital no Rio de Janeiro. Sites, lojas virtuais e sistemas feitos para aparecer no Google, nas buscas por IA e converter visitas em clientes. Desde 2017.'],
                'footer_links' => ['type' => 'textarea', 'label' => 'Links do rodapé', 'rows' => 7, 'default' => "Criação de sites no Rio de Janeiro | /criacao-de-sites-rio-de-janeiro/\nQuanto custa um site profissional | /quanto-custa-criar-um-site-profissional/\nAnalisador de SEO grátis | /analisador-seo/\nE-book: O digital não é mais opção | /o-digital-nao-e-mais-opcao/\nAcessar meu projeto | /meu-projeto/\nPolítica de privacidade | /politica-de-privacidade/"],
                'redirects'   => ['type' => 'textarea', 'label' => 'Redirecionamentos (endereços antigos)', 'rows' => 6, 'default' => "/bio | /\n/painel-seo | /\n/fale-conosco | /#contato\n/o-que-fazemos | /#servicos\n/feed-teste | /\n/testesssssss | /", 'help' => 'Uma linha por endereço: /antigo | /novo. Também vale para tudo que vem depois (ex.: /painel-seo/login).'],
                'llms'        => ['type' => 'textarea', 'label' => 'Resumo para as IAs (llms.txt)', 'rows' => 10, 'default' => "# Agência Sev7n\n\n> Agência digital no Rio de Janeiro (desde 2017) que cria sites profissionais, lojas virtuais, landing pages e sistemas web sob medida, com SEO e foco em conversão. Atende todo o Brasil.\n\nContato: WhatsApp (21) 97271-4155 · contato@agenciasev7n.com.br\n\n## Páginas\n\n- [Início](/): serviços, portfólio, processo e depoimentos.\n- [Criação de sites no Rio de Janeiro](/criacao-de-sites-rio-de-janeiro/): como trabalhamos, prazos e tipos de site.\n- [Quanto custa um site profissional](/quanto-custa-criar-um-site-profissional/): faixas de preço por tipo de projeto.\n- [Analisador de SEO grátis](/analisador-seo/): auditoria de qualquer site com nota, velocidade do Google e plano de ação.\n- [E-book O digital não é mais opção](/o-digital-nao-e-mais-opcao/): guia gratuito de presença digital para pequenas empresas.", 'help' => 'Aparece em /llms.txt. É um resumo do site para ChatGPT, Claude, Gemini e Perplexity.'],
            ],
        ],
    ],

    // Cores da marca no aviso de cookies e no painel (laranja da marca, texto escuro no botão (como no site))
    'brand' => ['accent' => '#F2611D', 'dark' => '#0B0D13', 'ink' => '#160B03'],

    'seo_defaults' => [
        'title'       => 'Criação de Sites Profissionais no Rio de Janeiro | Agência Sev7n',
        'description' => 'Sites, lojas virtuais e sistemas sob medida no Rio de Janeiro, feitos para aparecer no Google e transformar visitas em clientes. Analise seu site grátis.',
        'focus_keyword' => 'criação de sites rio de janeiro',
        'og_image'    => ['src' => $img . 'og-image.jpg', 'alt' => 'Agência Sev7n — criação de sites no Rio de Janeiro'],
        'favicon'     => ['src' => $img . 'favicon.png', 'alt' => ''],
        'theme_color' => '#0B0D13',
        'schema_type' => 'ProfessionalService',
        'org_name'    => 'Agência Sev7n',
        'org_phone'   => '+55 21 97271-4155',
        'org_email'   => 'contato@agenciasev7n.com.br',
        'org_city'    => 'Rio de Janeiro',
        'org_state'   => 'RJ',
    ],

    /* Valores iniciais dos módulos (dá para mudar tudo no painel) */
    'module_defaults' => [
        'google-analytics' => ['measurement_id' => 'G-MNP1BHLJZS'],
        'google-search-console' => ['verification' => '9_RK29OEhIhST-c0zKRoqIv6Yg0IvUJiEJBdSCXfGzM', 'property' => 'sc-domain:agenciasev7n.com.br'],
        'forms' => ['subject' => 'Novo contato pelo site da Agência Sev7n', 'from_name' => 'Site Agência Sev7n', 'recipients' => 'contato@agenciasev7n.com.br'],
        'seo-analyzer' => [
            'agency_name' => 'Agência Sev7n',
            'wa_message' => 'Olá! Analisei o site {site} no analisador da Sev7n e tirei nota {nota}. Quero ajuda para melhorar.',
            'cta_title' => 'Quer que a Sev7n resolva isso para você?',
            'cta_text' => 'A gente corrige os problemas deste relatório, deixa o site rápido e preparado para o Google e para as buscas por IA. A conversa com um especialista é gratuita e sem compromisso.',
            'cta_button' => 'Falar com um especialista',
        ],
    ],

    /* ------------------------------------------------------------------
     * Blocos do tema
     * --------------------------------------------------------------- */
    'blocks' => [
        'hero' => [
            'label' => 'Topo da home com analisador', 'icon' => 'pulso',
            'description' => 'Título principal (H1), texto, botões e o campo "Analise seu site" que leva ao Analisador de SEO. Vídeo de fundo opcional.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Rótulo acima do título', 'default' => 'Agência digital no Rio de Janeiro desde 2017'],
                'title' => ['type' => 'text', 'label' => 'Título (H1)', 'h1' => true, 'default' => 'Sites que aparecem no Google e viram clientes'],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 3, 'default' => ''],
                'button_label' => ['type' => 'text', 'label' => 'Botão principal', 'default' => 'Pedir orçamento'],
                'button_link' => ['type' => 'text', 'label' => 'Link do botão principal', 'default' => 'wa', 'help' => $linkHelp],
                'button2_label' => ['type' => 'text', 'label' => 'Botão secundário', 'default' => 'Ver projetos'],
                'button2_link' => ['type' => 'text', 'label' => 'Link do botão secundário', 'default' => '#portfolio'],
                'tool_title' => ['type' => 'text', 'label' => 'Título do quadro do analisador', 'default' => 'Seu site está pronto para ser encontrado?'],
                'tool_text' => ['type' => 'text', 'label' => 'Texto do quadro do analisador', 'default' => 'Digite o endereço e receba a nota em 1 minuto. Grátis.'],
                'tool_points' => ['type' => 'textarea', 'label' => 'O que o analisador confere (um por linha)', 'rows' => 4, 'default' => "Velocidade real medida pelo Google\nSe o ChatGPT consegue ler o site\nErros que tiram você do Google"],
                'facts' => ['type' => 'textarea', 'label' => 'Faixa de números', 'rows' => 3, 'default' => "7+ | anos criando sites\n200+ | projetos entregues\nRJ + BR | presencial no Rio, remoto no Brasil", 'help' => 'Uma linha por item: número | texto.'],
                'video' => ['type' => 'toggle', 'label' => 'Vídeo de fundo (só no computador)', 'default' => true],
                'image' => ['type' => 'image', 'label' => 'Imagem de fundo', 'default' => ['src' => $img . 'hero-poster.webp', 'alt' => '']],
            ],
        ],
        'servicos' => [
            'label' => 'Serviços', 'icon' => 'escudo',
            'description' => 'Lista de serviços com ícone, título, texto e link.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Rótulo acima do título'],
                'title' => ['type' => 'text', 'label' => 'Título (H2)'],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2],
                'items' => ['type' => 'repeater', 'label' => 'Serviços', 'item_label' => 'title', 'add_label' => 'serviço', 'fields' => [
                    'icon' => $icon, 'title' => ['type' => 'text', 'label' => 'Título'], 'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 3], 'link' => ['type' => 'text', 'label' => 'Link (opcional)', 'help' => $linkHelp],
                ]],
                'background' => $bg,
            ],
        ],
        'portfolio' => [
            'label' => 'Portfólio', 'icon' => 'check',
            'description' => 'Projetos com print do site (a imagem rola ao passar o mouse), categoria, texto e link.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Rótulo acima do título'],
                'title' => ['type' => 'text', 'label' => 'Título (H2)'],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2],
                'items' => ['type' => 'repeater', 'label' => 'Projetos', 'item_label' => 'name', 'add_label' => 'projeto', 'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Nome'], 'category' => ['type' => 'text', 'label' => 'Tipo de projeto'],
                    'image' => ['type' => 'image', 'label' => 'Print da página inteira', 'help' => 'Pode ser bem comprido: ele rola dentro da moldura.'],
                    'url' => ['type' => 'url', 'label' => 'Endereço do site'], 'text' => ['type' => 'textarea', 'label' => 'Descrição', 'rows' => 2],
                    'tags' => ['type' => 'text', 'label' => 'Etiquetas (separadas por vírgula)'],
                ]],
                'visible' => ['type' => 'number', 'label' => 'Quantos aparecem antes do "ver mais"', 'default' => 6],
                'background' => $bg,
            ],
        ],
        'processo' => [
            'label' => 'Etapas do trabalho', 'icon' => 'calendario',
            'description' => 'Passo a passo numerado de como o projeto acontece.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Rótulo acima do título'],
                'title' => ['type' => 'text', 'label' => 'Título (H2)'],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2],
                'items' => ['type' => 'repeater', 'label' => 'Etapas', 'item_label' => 'title', 'add_label' => 'etapa', 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Etapa'], 'time' => ['type' => 'text', 'label' => 'Duração (opcional)'], 'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 3],
                ]],
                'background' => $bg,
            ],
        ],
        'diferenciais' => [
            'label' => 'Sobre e diferenciais', 'icon' => 'pulso',
            'description' => 'Texto sobre a agência, foto opcional e lista de diferenciais.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Rótulo acima do título'],
                'title' => ['type' => 'text', 'label' => 'Título (H2)'],
                'text' => ['type' => 'richtext', 'label' => 'Texto'],
                'image' => ['type' => 'image', 'label' => 'Foto (opcional)'],
                'items' => ['type' => 'repeater', 'label' => 'Diferenciais', 'item_label' => 'title', 'add_label' => 'diferencial', 'fields' => [
                    'icon' => $icon, 'title' => ['type' => 'text', 'label' => 'Título'], 'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2],
                ]],
                'background' => $bg,
            ],
        ],
        'depoimentos' => [
            'label' => 'Depoimentos', 'icon' => 'coracao',
            'description' => 'O que os clientes dizem.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Rótulo acima do título'],
                'title' => ['type' => 'text', 'label' => 'Título (H2)'],
                'items' => ['type' => 'repeater', 'label' => 'Depoimentos', 'item_label' => 'name', 'add_label' => 'depoimento', 'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Nome'], 'company' => ['type' => 'text', 'label' => 'Empresa'], 'text' => ['type' => 'textarea', 'label' => 'Depoimento', 'rows' => 4],
                ]],
                'background' => $bg,
            ],
        ],
        'material' => [
            'label' => 'Chamada para o e-book', 'icon' => 'folha',
            'description' => 'Capa do material gratuito, texto e botão para a página de download.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Rótulo acima do título'],
                'title' => ['type' => 'text', 'label' => 'Título (H2)'],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 3],
                'image' => ['type' => 'image', 'label' => 'Capa'],
                'button_label' => ['type' => 'text', 'label' => 'Botão'],
                'button_link' => ['type' => 'text', 'label' => 'Link do botão', 'help' => $linkHelp],
                'background' => $bg,
            ],
        ],
        'chamada' => [
            'label' => 'Chamada final', 'icon' => 'whatsapp',
            'description' => 'Título grande, texto, botões e contatos.',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Título (H2)'],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2],
                'button_label' => ['type' => 'text', 'label' => 'Botão principal'],
                'button_link' => ['type' => 'text', 'label' => 'Link do botão principal', 'help' => $linkHelp],
                'button2_label' => ['type' => 'text', 'label' => 'Botão secundário'],
                'button2_link' => ['type' => 'text', 'label' => 'Link do botão secundário', 'help' => $linkHelp],
            ],
        ],
        'cabecalho' => [
            'label' => 'Topo das páginas internas', 'icon' => 'calendario',
            'description' => 'Título (H1), texto de abertura e botões.',
            'fields' => [
                'eyebrow' => ['type' => 'text', 'label' => 'Rótulo acima do título'],
                'title' => ['type' => 'text', 'label' => 'Título (H1)', 'h1' => true],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 3],
                'button_label' => ['type' => 'text', 'label' => 'Botão (opcional)'],
                'button_link' => ['type' => 'text', 'label' => 'Link do botão', 'help' => $linkHelp],
                'points' => ['type' => 'textarea', 'label' => 'Destaques (um por linha, opcional)', 'rows' => 4],
            ],
        ],
        'artigo' => [
            'label' => 'Texto longo (artigo)', 'icon' => 'folha',
            'description' => 'Texto com subtítulos, listas e links, em fundo claro para leitura, com chamada lateral.',
            'fields' => [
                'text' => ['type' => 'richtext', 'label' => 'Texto'],
                'aside_title' => ['type' => 'text', 'label' => 'Título da caixa lateral', 'default' => 'Vamos conversar sobre o seu site?'],
                'aside_text' => ['type' => 'textarea', 'label' => 'Texto da caixa lateral', 'rows' => 2, 'default' => 'Orçamento sem compromisso, direto com quem vai fazer o projeto.'],
                'aside' => ['type' => 'toggle', 'label' => 'Mostrar a caixa lateral', 'default' => true],
            ],
        ],
        'faixas' => [
            'label' => 'Tabela de faixas de preço', 'icon' => 'check',
            'description' => 'Tipos de projeto com faixa de preço, prazo e o que inclui.',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Título (H2)'],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2],
                'items' => ['type' => 'repeater', 'label' => 'Faixas', 'item_label' => 'name', 'add_label' => 'faixa', 'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Tipo de projeto'], 'price' => ['type' => 'text', 'label' => 'Faixa de preço'], 'time' => ['type' => 'text', 'label' => 'Prazo'],
                    'text' => ['type' => 'textarea', 'label' => 'O que inclui', 'rows' => 3], 'highlight' => ['type' => 'toggle', 'label' => 'Destacar', 'default' => false],
                ]],
                'note' => ['type' => 'text', 'label' => 'Observação abaixo da tabela'],
            ],
        ],
        'projeto' => [
            'label' => 'Acesso ao projeto do cliente', 'icon' => 'escudo',
            'description' => 'Campo para o cliente digitar o nome do projeto e abrir o endereço nome.agenciasev7n.com.br.',
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Título (H1)', 'h1' => true, 'default' => 'Acessar meu projeto'],
                'text' => ['type' => 'textarea', 'label' => 'Texto', 'rows' => 2, 'default' => 'Digite o nome do seu projeto para abrir a área de acompanhamento.'],
                'domain' => ['type' => 'text', 'label' => 'Domínio dos projetos', 'default' => 'agenciasev7n.com.br'],
                'button' => ['type' => 'text', 'label' => 'Botão', 'default' => 'Abrir meu projeto'],
                'help' => ['type' => 'text', 'label' => 'Ajuda abaixo do campo', 'default' => 'Não sabe o nome do projeto? Chame a gente no WhatsApp.'],
            ],
        ],
    ],

    /* ------------------------------------------------------------------
     * Páginas iniciais
     * --------------------------------------------------------------- */
    'default_pages' => [
        $pagina('home'),
        $pagina('criacao-sites-rj'),
        $pagina('quanto-custa'),
        $pagina('analisador'),
        $pagina('ebook'),
        $pagina('meu-projeto'),
        $pagina('privacidade'),
    ],
];
