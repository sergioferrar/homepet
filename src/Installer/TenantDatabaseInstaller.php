<?php

declare (strict_types = 1);

namespace App\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * TenantDatabaseInstaller
 *
 * Responsável por criar todas as tabelas necessárias para um novo tenant
 * no sistema SaaS multi-tenant de Pet Shop e Clínica Veterinária.
 *
 * Uso:
 *   $installer = new TenantDatabaseInstaller($logger);
 *   $result = $installer->install($connection);
 */
final class TenantDatabaseInstaller
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    // -------------------------------------------------------------------------
    // Ponto de entrada público
    // -------------------------------------------------------------------------

    /**
     * Executa a instalação completa do schema do tenant.
     *
     * @return array{
     *   success: bool,
     *   created_tables?: list<string>,
     *   errors?: list<string>,
     *   failed_table?: string,
     *   message?: string,
     * }
     */
    public function install($connection): array
    {
        $queries = $this->getQueries();
        $createdTables = [];

        $this->logger->info('[TenantDatabaseInstaller] Iniciando instalação do schema.', [
            'total_tables' => count($queries),
        ]);

        foreach ($queries as $tableName => $sql) {
            $this->logger->info("[TenantDatabaseInstaller] Criando tabela: {$tableName}");

            try {
                $connection->executeQuery($sql);
                $createdTables[] = $tableName;

                $this->logger->info("[TenantDatabaseInstaller] ✔ Tabela '{$tableName}' criada com sucesso.");
            } catch (DBALException $e) {
                $message = $e->getMessage();

                $this->logger->error(
                    "[TenantDatabaseInstaller] ✘ Falha ao criar a tabela '{$tableName}'.",
                    ['error' => $message, 'created_so_far' => $createdTables],
                );

                return [
                    'success' => false,
                    'failed_table' => $tableName,
                    'message' => $message,
                ];
            }
        }

        $this->logger->info('[TenantDatabaseInstaller] Instalação concluída com sucesso.', [
            'created_tables' => $createdTables,
        ]);

        return [
            'success' => true,
            'created_tables' => $createdTables,
            'errors' => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Definição das queries (ordem garante integridade referencial)
    // -------------------------------------------------------------------------

    /**
     * Retorna o mapa { nome_tabela => SQL } na ordem correta de criação.
     *
     * @return array<string, string>
     */
    private function getQueries(): array
    {
        return [

            // -----------------------------------------------------------------
            // 1. AGENDAMENTO
            // -----------------------------------------------------------------
            'agendamento' => <<<SQL
                CREATE TABLE agendamento (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    data datetime DEFAULT NULL,
                    concluido int(11) DEFAULT NULL,
                    pronto int(11) DEFAULT NULL,
                    horaChegada datetime DEFAULT NULL,
                    metodo_pagamento enum('dinheiro','pix','credito','debito','pendente','pacote_semanal_1','pacote_semanal_2','pacote_semanal_3','pacote_semanal_4','pacote_quinzenal') NOT NULL DEFAULT 'pendente',
                    horaSaida datetime DEFAULT NULL,
                    taxi_dog tinyint(4) DEFAULT 0,
                    taxa_taxi_dog decimal(10,2) DEFAULT NULL,
                    donoId int(11) DEFAULT NULL,
                    pacote_quinzenal tinyint(1) DEFAULT 0,
                    pacote_semanal tinyint(1) DEFAULT 0,
                    status varchar(20) DEFAULT 'aguardando',
                    pet_id int(11) DEFAULT 0,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. AGENDAMENTO_CLINICA
            // -----------------------------------------------------------------
            'agendamento_clinica'=><<<SQL
                CREATE TABLE agendamento_clinica (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    data datetime NOT NULL,
                    hora time DEFAULT NULL,
                    procedimento varchar(255) DEFAULT NULL,
                    status varchar(20) DEFAULT 'aguardando',
                    observacoes text DEFAULT NULL,
                    pet_id int(11) DEFAULT NULL,
                    dono_id int(11) DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. AGENDAMENTO_PET_SERVICO
            // -----------------------------------------------------------------
            'agendamento_pet_servico'=><<<SQL
                CREATE TABLE agendamento_pet_servico (
                    agendamentoId int(11) NOT NULL,
                    petId int(11) NOT NULL,
                    servicoId int(11) NOT NULL,
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    status varchar(20) DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. VETERINARIO
            // -----------------------------------------------------------------
            'veterinario'=><<<SQL
                CREATE TABLE veterinario (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    nome varchar(255) NOT NULL,
                    email varchar(255) DEFAULT NULL,
                    telefone varchar(20) DEFAULT NULL,
                    especialidade varchar(255) DEFAULT NULL,
                    estabelecimento_id int(11) NOT NULL DEFAULT 0,
                    crmv varchar(255) DEFAULT NULL,
                    status varchar(20) NOT NULL DEFAULT 'ativo' COMMENT 'ativo | inativo — Registro jamais excluído para fins de auditoria',
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. BANDEIRA_CARTAO_REF
            // -----------------------------------------------------------------
            'bandeira_cartao_ref'=><<<SQL
                CREATE TABLE bandeira_cartao_ref (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    nome varchar(100) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY nome (nome)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. BOX
            // -----------------------------------------------------------------
            'box'=><<<SQL
                CREATE TABLE box (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    numero varchar(20) NOT NULL,
                    tipo enum('internacao','emergencia','observacao','isolamento','cirurgia','recuperacao') NOT NULL,
                    porte enum('pequeno','medio','grande','gigante','todos') NOT NULL DEFAULT 'todos',
                    estrutura enum('maca','canil','gaiola','cercado','baia') NOT NULL DEFAULT 'canil',
                    localizacao varchar(100) DEFAULT NULL,
                    suporte_soro tinyint(1) NOT NULL DEFAULT 0,
                    suporte_oxigenio tinyint(1) NOT NULL DEFAULT 0,
                    tem_aquecimento tinyint(1) NOT NULL DEFAULT 0,
                    tem_camera tinyint(1) NOT NULL DEFAULT 0,
                    peso_maximo_kg decimal(5,1) DEFAULT NULL,
                    status enum('disponivel','ocupado','manutencao','reservado','higienizacao') NOT NULL DEFAULT 'disponivel',
                    capacidade int(11) DEFAULT 1,
                    valor_diaria decimal(10,2) DEFAULT NULL,
                    observacoes text DEFAULT NULL,
                    created_at timestamp NOT NULL DEFAULT current_timestamp(),
                    updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (id),
                    UNIQUE KEY unique_box (estabelecimento_id,numero),
                    KEY idx_estabelecimento (estabelecimento_id),
                    KEY idx_status (status),
                    KEY idx_box_status (estabelecimento_id,status)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. CAIXA_MOVIMENTO
            // -----------------------------------------------------------------
            'caixa_movimento'=><<<SQL
                CREATE TABLE caixa_movimento (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    descricao varchar(255) NOT NULL,
                    valor decimal(10,2) NOT NULL,
                    tipo enum('ENTRADA','SAIDA') DEFAULT 'SAIDA',
                    data datetime NOT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. CLIENTE
            // -----------------------------------------------------------------
            'cliente'=><<<SQL
                CREATE TABLE cliente (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    nome varchar(255) DEFAULT NULL,
                    email varchar(255) DEFAULT NULL,
                    telefone varchar(255) DEFAULT NULL,
                    cpf varchar(14) DEFAULT NULL,
                    whatsapp varchar(6) DEFAULT NULL,
                    rua varchar(255) DEFAULT NULL,
                    numero varchar(45) DEFAULT NULL,
                    complemento varchar(255) DEFAULT NULL,
                    bairro varchar(255) DEFAULT NULL,
                    cidade varchar(255) DEFAULT NULL,
                    cep int(11) DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. COLABORADOR
            // -----------------------------------------------------------------
            'colaborador'=><<<SQL
                CREATE TABLE colaborador (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    nome varchar(255) NOT NULL,
                    cargo varchar(100) DEFAULT NULL,
                    telefone varchar(20) DEFAULT NULL,
                    email varchar(150) DEFAULT NULL,
                    data_admissao date DEFAULT NULL,
                    ativo tinyint(1) DEFAULT 1,
                    criado_em datetime DEFAULT current_timestamp(),
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. CONSULTA
            // -----------------------------------------------------------------
            'consulta'=><<<SQL
                CREATE TABLE consulta (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    cliente_id int(11) NOT NULL,
                    pet_id int(11) NOT NULL,
                    data date NOT NULL,
                    hora time NOT NULL,
                    observacoes text DEFAULT NULL,
                    criado_em datetime DEFAULT current_timestamp(),
                    status enum('aguardando','atendido','cancelado') NOT NULL DEFAULT 'aguardando',
                    anamnese text DEFAULT NULL,
                    tipo varchar(50) DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_consulta_estabelecimento (estabelecimento_id),
                    KEY idx_consulta_data (data)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. DOCUMENTO_MODELO
            // -----------------------------------------------------------------
            'documento_modelo'=><<<SQL
                CREATE TABLE documento_modelo (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    titulo varchar(255) NOT NULL,
                    tipo varchar(100) DEFAULT 'Outros',
                    conteudo text NOT NULL,
                    criado_em datetime DEFAULT current_timestamp(),
                    cabecalho text DEFAULT NULL,
                    rodape text DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. ESTOQUE
            // -----------------------------------------------------------------
            'estoque'=><<<SQL
                CREATE TABLE estoque (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    produtoId int(11) DEFAULT NULL,
                    estabelecimento_id int(11) DEFAULT NULL,
                    local_estoque_id varchar(45) DEFAULT NULL,
                    quantidade_atual int(11) DEFAULT NULL,
                    quantidade_reserva int(11) DEFAULT NULL,
                    quantidade_disponivel int(11) DEFAULT NULL,
                    estoque_minimo int(11) DEFAULT NULL,
                    etoque_maximo int(11) DEFAULT NULL,
                    custo_medio double DEFAULT NULL,
                    custo_ultima_compra double DEFAULT NULL,
                    refrigerado int(11) DEFAULT NULL,
                    controla_lote int(11) DEFAULT NULL,
                    controla_validade int(11) DEFAULT NULL,
                    status enum('ativo','inativo','suspenso') DEFAULT NULL,
                    created_at datetime DEFAULT NULL,
                    updated_at datetime DEFAULT NULL,
                    updated_by datetime DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. PRODUTO
            // -----------------------------------------------------------------
            'produto'=><<<SQL
                CREATE TABLE produto (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    nome varchar(255) NOT NULL,
                    codigo bigint(20) DEFAULT NULL,
                    refrigerado varchar(255) NOT NULL DEFAULT 'nao',
                    preco_custo decimal(10,2) DEFAULT NULL,
                    preco_venda decimal(10,2) DEFAULT NULL,
                    estoque_atual int(11) DEFAULT 0,
                    unidade varchar(50) DEFAULT NULL,
                    data_cadastro datetime DEFAULT NULL,
                    data_validade datetime DEFAULT NULL,
                    codigo_fabrica varchar(255) DEFAULT NULL,
                    ncm int(11) DEFAULT NULL,
                    cfop int(11) DEFAULT NULL,
                    cest int(11) DEFAULT NULL,
                    aliquota_icms double DEFAULT NULL,
                    aliquota_pis double DEFAULT NULL,
                    aliquota_cofins double DEFAULT NULL,
                    aliquota_ipi double DEFAULT NULL,
                    aliquota_iss double DEFAULT NULL,
                    aliquota_ibs double DEFAULT NULL,
                    aliquota_cbs double DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. ESTOQUE_MOVIMENTO
            // -----------------------------------------------------------------
            'estoque_movimento'=><<<SQL
                CREATE TABLE estoque_movimento (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    produto_id int(11) NOT NULL,
                    estabelecimento_id int(11) NOT NULL,
                    quantidade int(11) NOT NULL,
                    tipo varchar(20) NOT NULL,
                    origem varchar(100) DEFAULT NULL,
                    data datetime NOT NULL,
                    observacao text DEFAULT NULL,
                    refrigerado enum('Sim','Não') DEFAULT 'Não',
                    PRIMARY KEY (id),
                    KEY fk_produto_estoque (produto_id),
                    CONSTRAINT fk_produto_estoque FOREIGN KEY (produto_id) REFERENCES produto (id) ON DELETE CASCADE
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. EXAME
            // -----------------------------------------------------------------
            'exame'=><<<SQL
                CREATE TABLE exame (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    pet_id int(11) DEFAULT NULL,
                    agendamento_id int(11) DEFAULT NULL,
                    descricao text DEFAULT NULL,
                    arquivo text DEFAULT NULL,
                    criado_em datetime DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. FINANCEIRO
            // -----------------------------------------------------------------
            'financeiro'=><<<SQL
                CREATE TABLE financeiro (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    valor decimal(10,0) DEFAULT NULL,
                    data datetime DEFAULT NULL,
                    descricao varchar(255) DEFAULT NULL,
                    pet_id int(11) DEFAULT NULL,
                    pet_nome varchar(255) DEFAULT NULL,
                    origem varchar(255) DEFAULT NULL,
                    status varchar(255) DEFAULT NULL,
                    tipo varchar(20) NOT NULL DEFAULT 'ENTRADA',
                    inativar tinyint(1) NOT NULL DEFAULT 0,
                    metodo_pagamento varchar(255) DEFAULT NULL,
                    bandeira_cartao varchar(100) DEFAULT NULL,
                    parcelas int(11) DEFAULT 1,
                    venda int(11) DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. FINANCEIROPENDENTE
            // -----------------------------------------------------------------
            'financeiropendente'=><<<SQL
                CREATE TABLE financeiropendente (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    descricao varchar(255) NOT NULL,
                    valor decimal(10,2) NOT NULL,
                    data datetime NOT NULL,
                    pet_id int(11) DEFAULT NULL,
                    metodo_pagamento enum('dinheiro','pix','credito','debito','pendente') DEFAULT 'pendente',
                    bandeira_cartao varchar(100) DEFAULT NULL,
                    parcelas int(11) DEFAULT 1,
                    agendamento_id int(11) DEFAULT NULL,
                    origem varchar(255) DEFAULT NULL,
                    status varchar(255) DEFAULT NULL,
                    inativar tinyint(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. MEDICAMENTOS
            // -----------------------------------------------------------------
            'medicamentos'=><<<SQL
                CREATE TABLE medicamentos (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    nome varchar(150) NOT NULL,
                    via varchar(50) DEFAULT NULL,
                    concentracao varchar(50) DEFAULT NULL,
                    descricao text DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. ORCAMENTO
            // -----------------------------------------------------------------
            'orcamento'=><<<SQL
                CREATE TABLE orcamento (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    cliente_id int(11) DEFAULT NULL,
                    cliente_nome varchar(255) NOT NULL,
                    pet_id int(11) DEFAULT NULL,
                    pet_nome varchar(255) DEFAULT NULL,
                    valor_total decimal(10,2) NOT NULL,
                    desconto decimal(10,2) DEFAULT 0.00,
                    valor_final decimal(10,2) NOT NULL,
                    status varchar(50) NOT NULL DEFAULT 'pendente',
                    data_criacao datetime NOT NULL,
                    data_validade datetime DEFAULT NULL,
                    observacoes text DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_estabelecimento (estabelecimento_id),
                    KEY idx_cliente (cliente_id),
                    KEY idx_status (status),
                    KEY idx_data_criacao (data_criacao)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. ORCAMENTO_ITEM
            // -----------------------------------------------------------------
            'orcamento_item'=><<<SQL
                CREATE TABLE orcamento_item (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    orcamento_id int(11) NOT NULL,
                    descricao varchar(255) NOT NULL,
                    tipo varchar(100) NOT NULL,
                    quantidade int(11) NOT NULL,
                    valor_unitario decimal(10,2) NOT NULL,
                    subtotal decimal(10,2) NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_orcamento (orcamento_id),
                    CONSTRAINT orcamento_item_ibfk_1 FOREIGN KEY (orcamento_id) REFERENCES orcamento (id) ON DELETE CASCADE
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. PET
            // -----------------------------------------------------------------
            'pet'=><<<SQL
                CREATE TABLE pet (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    nome varchar(255) DEFAULT NULL,
                    especie varchar(255) DEFAULT NULL,
                    idade varchar(255) DEFAULT NULL,
                    data_nascimento date DEFAULT NULL,
                    dono_id varchar(255) DEFAULT NULL,
                    sexo varchar(255) DEFAULT NULL,
                    raca varchar(255) DEFAULT NULL,
                    porte varchar(255) DEFAULT NULL,
                    observacoes varchar(255) DEFAULT NULL,
                    peso decimal(5,2) DEFAULT NULL,
                    castrado tinyint(1) NOT NULL DEFAULT 0,
                    tipo varchar(255) DEFAULT NULL,
                    data_cadastro datetime DEFAULT current_timestamp(),
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. PRONTUARIO
            // -----------------------------------------------------------------
            'prontuario'=><<<SQL
                CREATE TABLE prontuario (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    agendamento_id int(11) DEFAULT NULL,
                    observacoes text DEFAULT NULL,
                    arquivos text DEFAULT NULL,
                    criado_em datetime DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. PRONTUARIOPET
            // -----------------------------------------------------------------
            'prontuariopet'=><<<SQL
                CREATE TABLE prontuariopet (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    pet_id int(11) NOT NULL,
                    data datetime NOT NULL,
                    tipo enum('evolucao','procedimento','exame','vacina') NOT NULL,
                    descricao text NOT NULL,
                    anexo varchar(255) DEFAULT NULL,
                    criado_em datetime DEFAULT current_timestamp(),
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. RECEITA
            // -----------------------------------------------------------------
            'receita'=><<<SQL
                CREATE TABLE receita (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    pet_id int(11) NOT NULL,
                    data date NOT NULL,
                    resumo varchar(255) DEFAULT NULL,
                    cabecalho longtext DEFAULT NULL,
                    conteudo longtext DEFAULT NULL,
                    rodape longtext DEFAULT NULL,
                    criado_em datetime DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. SERVICO
            // -----------------------------------------------------------------
            'servico'=><<<SQL
                CREATE TABLE servico (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    nome varchar(255) DEFAULT NULL,
                    descricao varchar(255) DEFAULT NULL,
                    valor decimal(10,0) DEFAULT NULL,
                    tipo varchar(20) NOT NULL DEFAULT 'clinica',
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. VACINA
            // -----------------------------------------------------------------
            'vacina'=><<<SQL
                CREATE TABLE vacina (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    pet_id int(11) DEFAULT NULL,
                    tipo varchar(100) DEFAULT NULL,
                    data_aplicacao date DEFAULT NULL,
                    data_validade date DEFAULT NULL,
                    lote varchar(100) DEFAULT NULL,
                    fabricante varchar(150) DEFAULT NULL,
                    observacoes text DEFAULT NULL,
                    veterinario_id int(11) DEFAULT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
           SQL,

            // -----------------------------------------------------------------
            // 1. VENDA
            // -----------------------------------------------------------------
            'venda'=><<<SQL
                CREATE TABLE venda (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    cliente varchar(255) NOT NULL,
                    total decimal(10,2) NOT NULL,
                    troco decimal(10,2) DEFAULT 0.00,
                    metodo_pagamento varchar(50) NOT NULL,
                    bandeira_cartao varchar(50) DEFAULT NULL,
                    parcelas int(11) DEFAULT 1,
                    data datetime DEFAULT current_timestamp(),
                    observacao text DEFAULT NULL,
                    pet_id int(11) DEFAULT NULL,
                    origem varchar(255) DEFAULT NULL,
                    status enum('Aberta','Pendente','Paga','Inativa','Carrinho') DEFAULT 'Carrinho',
                    PRIMARY KEY (id),
                    KEY idx_venda_pet_id (pet_id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
           SQL,

            // -----------------------------------------------------------------
            // 1. VENDA_ITEM
            // -----------------------------------------------------------------
            'venda_item'=><<<SQL
                CREATE TABLE venda_item (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    venda_id int(11) NOT NULL,
                    produto varchar(255) NOT NULL,
                    quantidade int(11) NOT NULL,
                    valor_unitario decimal(10,2) NOT NULL,
                    subtotal decimal(10,2) NOT NULL,
                    tipo enum('servico','produto','medicamento') DEFAULT 'produto',
                    produto_id int(11) DEFAULT NULL,
                    preco_unitario decimal(10,2) DEFAULT 0.00,
                    PRIMARY KEY (id),
                    KEY fk_venda_item (venda_id),
                    CONSTRAINT fk_venda_item FOREIGN KEY (venda_id) REFERENCES venda (id) ON DELETE CASCADE
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. HOSPEDAGEM_CAES
            // -----------------------------------------------------------------
            'hospedagem_caes'=><<<SQL
                CREATE TABLE hospedagem_caes (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    cliente_id int(11) DEFAULT NULL,
                    pet_id int(11) DEFAULT NULL,
                    data_entrada date DEFAULT NULL,
                    data_saida date DEFAULT NULL,
                    valor decimal(10,2) DEFAULT NULL,
                    observacoes text DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY cliente_id (cliente_id),
                    KEY pet_id (pet_id),
                    CONSTRAINT hospedagem_caes_ibfk_1 FOREIGN KEY (cliente_id) REFERENCES cliente (id),
                    CONSTRAINT hospedagem_caes_ibfk_2 FOREIGN KEY (pet_id) REFERENCES pet (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. INTERNACAO
            // -----------------------------------------------------------------
            'internacao'=><<<SQL
                CREATE TABLE internacao (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    data_inicio datetime NOT NULL,
                    motivo text DEFAULT NULL,
                    status varchar(255) NOT NULL,
                    situacao varchar(255) DEFAULT NULL,
                    risco varchar(255) DEFAULT NULL,
                    box varchar(255) DEFAULT NULL,
                    alta_prevista date DEFAULT NULL,
                    diagnostico text DEFAULT NULL,
                    prognostico text DEFAULT NULL,
                    anotacoes text DEFAULT NULL,
                    pet_id int(11) NOT NULL,
                    dono_id int(11) DEFAULT NULL,
                    veterinario_id int(11) DEFAULT NULL,
                    estabelecimento_id int(11) NOT NULL,
                    PRIMARY KEY (id),
                    KEY pet_id (pet_id),
                    KEY dono_id (dono_id),
                    KEY veterinario_id (veterinario_id),
                    CONSTRAINT internacao_ibfk_1 FOREIGN KEY (pet_id) REFERENCES pet (id) ON DELETE CASCADE,
                    CONSTRAINT internacao_ibfk_2 FOREIGN KEY (dono_id) REFERENCES cliente (id) ON DELETE CASCADE,
                    CONSTRAINT internacao_ibfk_3 FOREIGN KEY (veterinario_id) REFERENCES veterinario (id) ON DELETE SET NULL
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. INTERNACAO_EVENTO
            // -----------------------------------------------------------------
            'internacao_evento'=><<<SQL
                CREATE TABLE internacao_evento (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    estabelecimento_id int(11) NOT NULL,
                    internacao_id int(11) NOT NULL,
                    pet_id int(11) NOT NULL,
                    tipo enum('internacao','alta','ocorrencia','peso','prescricao','medicacao_exec') NOT NULL,
                    titulo varchar(255) NOT NULL,
                    descricao text DEFAULT NULL,
                    data_hora datetime NOT NULL,
                    criado_em datetime NOT NULL DEFAULT current_timestamp(),
                    status varchar(150) NOT NULL DEFAULT 'Não aplicada',
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,

            // -----------------------------------------------------------------
            // 1. INTERNACAO_EXECUCAO
            // -----------------------------------------------------------------
            'internacao_execucao'=><<<SQL
                CREATE TABLE internacao_execucao (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    internacao_id int(11) NOT NULL,
                    prescricao_id int(11) NOT NULL,
                    veterinario_id int(11) NOT NULL,
                    data_execucao timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    status varchar(255) NOT NULL,
                    anotacoes varchar(255) DEFAULT NULL,
                    PRIMARY KEY (id) USING BTREE
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC
            SQL,

            // -----------------------------------------------------------------
            // 1. INTERNACAO_PRESCRICAO
            // -----------------------------------------------------------------
            'internacao_prescricao'=><<<SQL
                CREATE TABLE internacao_prescricao (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    internacao_id int(11) NOT NULL,
                    medicamento_id int(11) NOT NULL,
                    descricao text NOT NULL,
                    dose varchar(255) DEFAULT NULL,
                    frequencia varchar(255) DEFAULT NULL,
                    frequencia_horas int(11) NOT NULL DEFAULT 6,
                    duracao_dias int(11) NOT NULL DEFAULT 1,
                    data_hora datetime NOT NULL,
                    criado_em datetime NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (id),
                    KEY internacao_id (internacao_id),
                    KEY medicamento_id (medicamento_id),
                    CONSTRAINT internacao_prescricao_ibfk_1 FOREIGN KEY (internacao_id) REFERENCES internacao (id) ON DELETE CASCADE,
                    CONSTRAINT internacao_prescricao_ibfk_2 FOREIGN KEY (medicamento_id) REFERENCES medicamentos (id) ON DELETE CASCADE
                ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            SQL,
        ];
    }
}
