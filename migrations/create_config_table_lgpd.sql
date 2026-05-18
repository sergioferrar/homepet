-- =============================================================================
-- Migration: Tabela `config` no banco homepet_login
-- Armazena configurações globais do sistema: LGPD, tracking e outras.
-- Executar UMA VEZ no banco homepet_login (banco principal, não tenant).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `config` (
    `id`                 INT          NOT NULL AUTO_INCREMENT,
    `estabelecimento_id` INT          NOT NULL DEFAULT 0
                         COMMENT '0 = global do sistema (LGPD, tracking); >0 = configuração de tenant específico',
    `chave`              VARCHAR(255) NOT NULL,
    `valor`              LONGTEXT         NULL,
    `tipo`               VARCHAR(255) NOT NULL
                         COMMENT 'lgpd | tracking | gateway_payment | mailer | etc.',
    `observacao`         TEXT             NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_config_estab_tipo_chave` (`estabelecimento_id`, `tipo`(100), `chave`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configurações globais e por tenant: LGPD, tracking, pagamento, e-mail.';

-- -----------------------------------------------------------------------------
-- Dados iniciais — LGPD (conteúdo mínimo exigido pela Lei 13.709/2018)
-- O conteúdo pode ser sobrescrito pelo painel de configurações.
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `config` (`estabelecimento_id`, `tipo`, `chave`, `valor`, `observacao`) VALUES

-- Encarregado de Dados (DPO) — Art. 41 LGPD
(0, 'lgpd', 'dpo_nome',  '', 'Nome do Encarregado de Dados (DPO) — obrigatório pela LGPD Art. 41'),
(0, 'lgpd', 'dpo_email', '', 'E-mail do DPO para atendimento a titulares de dados'),

-- Banner de consentimento de cookies (0=desativado, 1=ativo)
(0, 'lgpd', 'cookie_banner_ativo', '1', 'Exibe banner de consentimento de cookies nas páginas públicas — recomendado pela ANPD'),

-- Política de Privacidade — texto HTML/Quill
(0, 'lgpd', 'politica_privacidade', '<h2>Política de Privacidade</h2>
<p>Esta Política de Privacidade descreve como o <strong>System Home Pet</strong> coleta, usa e protege as informações pessoais dos titulares de dados, em conformidade com a <strong>Lei Geral de Proteção de Dados (LGPD — Lei nº 13.709/2018)</strong>.</p>
<h3>1. Dados Coletados</h3>
<p>Coletamos apenas os dados necessários para a prestação dos serviços contratados, incluindo: nome, e-mail, telefone, CPF/CNPJ e informações dos animais de estimação cadastrados.</p>
<h3>2. Finalidade e Base Legal</h3>
<p>Os dados são tratados para execução do contrato de prestação de serviços (Art. 7º, V, LGPD), cumprimento de obrigações legais (Art. 7º, II) e legítimo interesse do controlador (Art. 7º, IX).</p>
<h3>3. Compartilhamento de Dados</h3>
<p>Os dados não são vendidos ou cedidos a terceiros, salvo quando necessário para a execução do serviço (ex.: processadoras de pagamento) ou por obrigação legal.</p>
<h3>4. Tempo de Retenção</h3>
<p>Os dados são mantidos pelo prazo necessário para a prestação do serviço e, após o encerramento, pelo período mínimo exigido pela legislação fiscal e trabalhista aplicável.</p>
<h3>5. Direitos do Titular</h3>
<p>Nos termos do Art. 18 da LGPD, você tem direito a: confirmação de tratamento, acesso, correção, anonimização, portabilidade, eliminação, informação sobre compartilhamento, revogação de consentimento e oposição ao tratamento.</p>
<h3>6. Contato</h3>
<p>Para exercer seus direitos ou esclarecer dúvidas, entre em contato com nosso Encarregado de Dados (DPO) pelo e-mail indicado abaixo.</p>',
'Texto da Política de Privacidade — LGPD Art. 9º e 18'),

-- Termos de Uso — texto HTML/Quill
(0, 'lgpd', 'termos_uso', '<h2>Termos de Uso</h2>
<p>Ao utilizar o <strong>System Home Pet</strong>, você concorda com os presentes Termos de Uso. Leia-os atentamente antes de prosseguir.</p>
<h3>1. Aceitação</h3>
<p>O uso do sistema implica na aceitação integral e irrestrita destes Termos. Caso não concorde, não utilize a plataforma.</p>
<h3>2. Descrição do Serviço</h3>
<p>O System Home Pet é uma plataforma de gestão para pet shops e clínicas veterinárias, oferecendo ferramentas de agendamento, prontuário, financeiro, internação e outros módulos.</p>
<h3>3. Obrigações do Usuário</h3>
<p>O usuário compromete-se a manter suas credenciais em sigilo, não compartilhar o acesso com terceiros não autorizados e utilizar o sistema apenas para fins lícitos.</p>
<h3>4. Propriedade Intelectual</h3>
<p>Todo o conteúdo, layout, código-fonte e marca do sistema são de propriedade exclusiva do desenvolvedor e estão protegidos pela legislação de direitos autorais.</p>
<h3>5. Limitação de Responsabilidade</h3>
<p>O fornecedor não se responsabiliza por danos indiretos decorrentes do uso inadequado da plataforma ou por indisponibilidade temporária por manutenção ou força maior.</p>
<h3>6. Alterações</h3>
<p>Estes termos podem ser atualizados a qualquer momento. O uso continuado após a publicação de alterações constitui aceite das novas condições.</p>
<h3>7. Foro</h3>
<p>Fica eleito o foro da comarca do estabelecimento contratante para dirimir quaisquer controvérsias decorrentes destes Termos.</p>',
'Texto dos Termos de Uso');

-- -----------------------------------------------------------------------------
-- Tracking — chaves criadas vazias para evitar erro na primeira leitura
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `config` (`estabelecimento_id`, `tipo`, `chave`, `valor`, `observacao`) VALUES
(0, 'tracking', 'google_analytics_id',   '', 'ID de medição do Google Analytics 4 (ex: G-XXXXXXXXXX)'),
(0, 'tracking', 'google_tag_manager_id', '', 'ID do Google Tag Manager (ex: GTM-XXXXXXX)'),
(0, 'tracking', 'facebook_pixel_id',     '', 'ID do Pixel do Facebook/Meta (ex: 1234567890)'),
(0, 'tracking', 'google_ads_id',         '', 'ID de conversão do Google Ads (ex: AW-XXXXXXXXX)');
