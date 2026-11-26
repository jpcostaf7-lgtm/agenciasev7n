<?php
/** Política de privacidade — Autor: João Costa */
if (!defined('CMS_ROOT')) { exit; }
return ['id' => 'privacidade', 'title' => 'Política de privacidade', 'slug' => 'politica-de-privacidade', 'seo' => [
    'description' => 'Como a Agência Sev7n coleta, usa e protege os seus dados pessoais, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018).',
], 'blocks' => [
    ['type' => 'cabecalho', 'data' => ['eyebrow' => 'LGPD', 'title' => 'Política de privacidade', 'text' => 'Como coletamos, usamos e protegemos os seus dados, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018).']],
    ['type' => 'artigo', 'data' => ['aside' => false, 'text' =>
        '<h2>Quem é o responsável pelos dados</h2><p>Agência Sev7n, Rio de Janeiro – RJ. Para qualquer assunto sobre os seus dados, escreva para <a href="mailto:contato@agenciasev7n.com.br">contato@agenciasev7n.com.br</a>.</p>'
        . '<h2>Quais dados coletamos</h2><ul><li><strong>Formulários e e-book:</strong> nome, e-mail, WhatsApp e a mensagem que você escrever.</li><li><strong>Analisador de SEO:</strong> o endereço do site analisado, a palavra-chave (se informada) e, quando você pede o relatório completo, nome, WhatsApp e e-mail.</li><li><strong>Navegação:</strong> páginas visitadas e origem da visita, por meio do Google Analytics, somente com o seu consentimento.</li></ul>'
        . '<h2>Para que usamos</h2><ul><li>Responder ao seu contato e enviar orçamentos.</li><li>Entregar o e-book e o relatório do analisador.</li><li>Enviar conteúdos sobre presença digital, se você autorizar (dá para cancelar a qualquer momento).</li><li>Entender como o site é usado e melhorá-lo.</li></ul><p>Não vendemos nem alugamos os seus dados.</p>'
        . '<h2>Com quem compartilhamos</h2><p>Apenas com fornecedores necessários para o site funcionar: a empresa de hospedagem, o Google (Analytics e PageSpeed Insights, usado pelo analisador) e a Anthropic (Claude), que gera o resumo do relatório do analisador a partir dos dados públicos do site analisado.</p>'
        . '<h2>Por quanto tempo guardamos</h2><p>Pelo tempo necessário para o atendimento e para cumprir obrigações legais. Você pode pedir a exclusão a qualquer momento.</p>'
        . '<h2>Cookies</h2><p>Usamos cookies essenciais para o site funcionar. Cookies de medição só são ativados depois do seu consentimento, e você pode mudar a sua escolha em "Preferências de cookies", no rodapé.</p>'
        . '<h2>Seus direitos</h2><p>Você pode pedir acesso, correção, exclusão ou portabilidade dos seus dados e revogar consentimentos pelo e-mail acima.</p>'
        . '<p><em>Última atualização: novembro de 2026.</em></p>']],
]];
