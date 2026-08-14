<?php

namespace App\Command;

use App\Entity\Venda;
use App\Entity\VendaItem;
use App\Service\DynamicConnectionManager;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Migração única: financeiro + financeiropendente → venda + venda_item
 *
 * Uso:
 *   php bin/console app:migrar-financeiro-vendas
 *
 * Opções:
 *   --dry-run   Simula sem gravar nada no banco
 *   --estab=X   Migra apenas o estabelecimento com ID X (padrão: todos)
 *
 * Segurança:
 *   - Verifica coluna `migrado` em financeiro/financeiropendente para idempotência.
 *   - Se a coluna não existir, cria automaticamente antes de migrar.
 *   - Registros já marcados como migrado=1 são ignorados.
 *   - Flush em lotes de 100 para não estourar memória.
 */
class MigrarFinanceiroVendasCommand extends Command
{
    protected static $defaultName        = 'app:migrar-financeiro-vendas';
    protected static $defaultDescription = 'Migração única: financeiro e financeiropendente → venda + venda_item';

    private ManagerRegistry $mr;

    public function __construct(ManagerRegistry $mr)
    {
        parent::__construct();
        $this->mr = $mr;
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE,   'Simula sem gravar nada')
            ->addOption('estab',   null, InputOption::VALUE_REQUIRED, 'ID do estabelecimento (padrão: todos)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try{
        $io     = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $estabFiltro = $input->getOption('estab') ? (int) $input->getOption('estab') : null;

        if ($dryRun) {
            $io->warning('MODO DRY-RUN — nenhuma alteração será gravada.');
        }

        // ── Conexão com o banco principal (lista de estabelecimentos) ─────────
        $connPrincipal = $this->mr->getConnection('default');

        $estabelecimentos = $connPrincipal->fetchAllAssociative(
            'SELECT id FROM estabelecimento' .
            ($estabFiltro ? ' WHERE id = :id' : ' ORDER BY id ASC'),
            $estabFiltro ? ['id' => $estabFiltro] : []
        );

        if (empty($estabelecimentos)) {
            $io->error('Nenhum estabelecimento encontrado.');
            return Command::FAILURE;
        }

        $totalVendas   = 0;
        $totalPendente = 0;
        $totalErros    = 0;

        foreach ($estabelecimentos as $estab) {
            $estabId = (int) $estab['id'];
            $dbName  = 'homepet_' . $estabId;

            $io->section("Estabelecimento #{$estabId} → banco: {$dbName}");

            // Troca para o banco do tenant
            (new DynamicConnectionManager($this->mr))->switchDatabase($dbName, $dbName);

            // EntityManager atualizado após o switchDatabase
            $em = $this->mr->getManager();
            $em->clear();

            $conn = $em->getConnection();

            $verificaBancoDeDados = $conn->fetchAssociative(
            "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbName}'"
            );

            if($verificaBancoDeDados){
                
            

            // Garante coluna de controle de idempotência
            $this->garantirColunasMigrado($conn, $dryRun, $io);

            // ── 1. MIGRAR financeiro (vendas pagas) ───────────────────────────
            $financeiros = $conn->fetchAllAssociative(
                "SELECT f.*, 
                        p.nome  AS pet_nome,
                        c.nome  AS cliente_nome
                 FROM financeiro f
                 LEFT JOIN pet     p ON p.id = f.pet_id
                 LEFT JOIN cliente c ON c.id = p.dono_id
                 -- WHERE f.inativar = 0
                 --   AND (f.migrado IS NULL OR f.migrado = 0)
                 ORDER BY f.data ASC"
            );

            $io->text(sprintf('  financeiro: %d registros a migrar', count($financeiros)));

            $lote = 0;
            foreach ($financeiros as $fin) {
                try {
                    $descricao = $fin['descricao'] ?? '';

                    // Detecta padrão PDV legado sem pet: "Venda PDV - NOME | N item(ns)"
                    $pdvInfo   = $this->detectarPdvSemPet($descricao);
                    $isPdv     = $pdvInfo !== null;

                    if ($isPdv) {
                        // Nome do cliente extraído da própria descrição
                        $nomeCliente = $pdvInfo['cliente'];
                        $petId       = null;
                    } else {
                        // Clínica: nome vem do JOIN com cliente/pet
                        $nomeCliente = $this->resolverNomeCliente(
                            $fin['cliente_nome'] ?? null,
                            $fin['pet_nome']     ?? null
                        );
                        $petId = $fin['pet_id'] ? (int) $fin['pet_id'] : null;
                    }

                    $venda = $this->criarVenda(
                        $estabId,
                        $nomeCliente,
                        (float) $fin['valor'],
                        $fin['metodo_pagamento'] ?? 'dinheiro',
                        $petId,
                        new \DateTime($fin['data']),
                        $fin['origem'] ?? 'clinica',
                        'Paga',
                        $descricao ?: null
                    );

                    if (!$dryRun) {
                        $em->persist($venda);
                        $em->flush(); // flush para obter o ID da venda
                    }

                    $itens = $this->explodirItens($descricao, (float) $fin['valor'], $isPdv);
                    foreach ($itens as $itemData) {
                        $item = $this->criarVendaItem($venda, $itemData);
                        if (!$dryRun) {
                            $em->persist($item);
                        }
                    }

                    if (!$dryRun) {
                        // Marca como migrado e vincula à venda criada
                        $conn->executeStatement(
                            'UPDATE financeiro SET migrado = 1, venda = :vendaId WHERE id = :id',
                            ['vendaId' => $venda->getId(), 'id' => $fin['id']]
                        );
                    }

                    $totalVendas++;
                    $lote++;

                    if (!$dryRun && $lote % 100 === 0) {
                        $em->flush();
                        $em->clear();
                        $io->text("    ... {$totalVendas} vendas processadas");
                    }

                } catch (\Throwable $e) {
                    $totalErros++;
                    $io->warning(sprintf(
                        '  [ERRO] financeiro #%d: %s',
                        $fin['id'],
                        $e->getMessage()
                    ));
                }
            }

            if (!$dryRun) {
                $em->flush();
                $em->clear();
            }

            // ── 2. MIGRAR financeiropendente (vendas pendentes) ───────────────
            $pendentes = $conn->fetchAllAssociative(
                "SELECT fp.*,
                        p.nome  AS pet_nome,
                        c.nome  AS cliente_nome
                 FROM financeiropendente fp
                 JOIN pet     p ON p.id = fp.pet_id
                 JOIN cliente c ON c.id = p.dono_id
                 -- WHERE fp.inativar = 0
                   -- AND (fp.migrado IS NULL OR fp.migrado = 0)
                 ORDER BY fp.data ASC"
            );

            $io->text(sprintf('  financeiropendente: %d registros a migrar', count($pendentes)));

            $lote = 0;
            foreach ($pendentes as $fp) {
                try {
                    $descricao = $fp['descricao'] ?? '';

                    // Detecta padrão PDV legado sem pet: "Venda PDV - NOME | N item(ns)"
                    $pdvInfo   = $this->detectarPdvSemPet($descricao);
                    $isPdv     = $pdvInfo !== null;

                    if ($isPdv) {
                        $nomeCliente = $pdvInfo['cliente'];
                        $petId       = null;
                    } else {
                        $nomeCliente = $this->resolverNomeCliente(
                            $fp['cliente_nome'] ?? null,
                            $fp['pet_nome']     ?? null
                        );
                        $petId = $fp['pet_id'] ? (int) $fp['pet_id'] : null;
                    }

                    $venda = $this->criarVenda(
                        $estabId,
                        $nomeCliente,
                        (float) $fp['valor'],
                        $fp['metodo_pagamento'] ?? 'pendente',
                        $petId,
                        new \DateTime($fp['data']),
                        $fp['origem'] ?? 'clinica',
                        'Pendente',
                        $descricao ?: null
                    );

                    if (!$dryRun) {
                        $em->persist($venda);
                        $em->flush();
                    }

                    $itens = $this->explodirItens($descricao, (float) $fp['valor'], $isPdv);
                    foreach ($itens as $itemData) {
                        $item = $this->criarVendaItem($venda, $itemData);
                        if (!$dryRun) {
                            $em->persist($item);
                        }
                    }

                    if (!$dryRun) {
                        $conn->executeStatement(
                            'UPDATE financeiropendente SET migrado = 1 WHERE id = :id',
                            ['id' => $fp['id']]
                        );
                    }

                    $totalPendente++;
                    $lote++;

                    if (!$dryRun && $lote % 100 === 0) {
                        $em->flush();
                        $em->clear();
                        $io->text("    ... {$totalPendente} pendentes processados");
                    }

                } catch (\Throwable $e) {
                    $totalErros++;
                    $io->warning(sprintf(
                        '  [ERRO] financeiropendente #%d: %s',
                        $fp['id'],
                        $e->getMessage()
                    ));
                }
            }

            if (!$dryRun) {
                $em->flush();
                $em->clear();
            }

            $io->text(sprintf(
                '  ✓ Pagas: %d | Pendentes: %d | Erros: %d',
                $totalVendas,
                $totalPendente,
                $totalErros
            ));
        }

        // ── Resumo final ──────────────────────────────────────────────────────
        $io->success(sprintf(
            "Migração %sconcluída!\n  Vendas pagas  : %d\n  Pendentes     : %d\n  Erros         : %d",
            $dryRun ? '[DRY-RUN] ' : '',
            $totalVendas,
            $totalPendente,
            $totalErros
        ));

        return $totalErros > 0 ? Command::FAILURE : Command::SUCCESS;
    }
    }catch(\Exception $e){
        dd($e);

    }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Adiciona coluna `migrado` nas tabelas de origem caso não exista.
     * Idempotente: roda sem erros se a coluna já existir.
     */
    private function garantirColunasMigrado(
        \Doctrine\DBAL\Connection $conn,
        bool $dryRun,
        SymfonyStyle $io
    ): void {
        if ($dryRun) {
            return;
        }

        $tabelas = ['financeiro', 'financeiropendente'];

        foreach ($tabelas as $tabela) {
            try {
                $conn->executeStatement(
                    "ALTER TABLE {$tabela} ADD COLUMN migrado TINYINT(1) NOT NULL DEFAULT 0"
                );
                $io->text("  + Coluna `migrado` criada em {$tabela}");
            } catch (\Throwable $ignored) {
                // Coluna já existe — ignorar
            }
        }
    }

    /**
     * Detecta se a descrição é do formato PDV legado sem pet:
     *   "Venda PDV - NOME DO CLIENTE | N item(ns)"
     *
     * Retorna array ['cliente' => string, 'is_pdv' => bool] ou null se não for PDV.
     */
    private function detectarPdvSemPet(string $descricao): ?array
    {
        // Padrão: "Venda PDV - NOME | N item(ns)"
        if (preg_match('/^Venda PDV - (.+?) \| \d+ item/u', $descricao, $m)) {
            $nome = trim($m[1]);
            return [
                'cliente' => ($nome === '' || strtolower($nome) === 'consumidor final')
                    ? 'Consumidor Final'
                    : $nome,
                'is_pdv'  => true,
            ];
        }

        return null;
    }

    /**
     * Resolve o nome de exibição do cliente.
     *
     * Para registros PDV sem pet, o nome vem embutido na descrição.
     * Para registros de clínica com pet, vem do JOIN com cliente.
     * Prioridade: nome do cliente (JOIN) > nome do pet > fallback.
     */
    private function resolverNomeCliente(?string $nomeCliente, ?string $nomePet): string
    {
        if (!empty(trim((string) $nomeCliente))) {
            return trim($nomeCliente);
        }

        if (!empty(trim((string) $nomePet))) {
            return 'Tutor de ' . trim($nomePet);
        }

        return 'Consumidor Final';
    }

    /**
     * Explode a descrição concatenada em itens individuais.
     *
     * Clínica: "Banho e Tosa + Coleira anti pulgas"
     *          → [{nome: 'Banho e Tosa', valor: X}, {nome: 'Coleira anti pulgas', valor: X}]
     *
     * PDV sem pet: "Venda PDV - Lucas Santos | 1 item(ns)"
     *          → [{nome: 'Venda PDV', valor: total}]
     *          (a descrição original do PDV não preserva os nomes dos produtos)
     *
     * O total é dividido igualmente entre os itens.
     * Correção de arredondamento aplicada no último item.
     */
    private function explodirItens(string $descricao, float $total, bool $isPdvSemPet = false): array
    {
        // PDV sem pet: descrição não contém os itens reais, apenas metadado
        if ($isPdvSemPet) {
            return [['nome' => 'Venda PDV', 'valor' => $total]];
        }

        // Clínica: itens separados por '+'
        // Remove '+' solto no início/fim e espaços extras
        $descricaoLimpa = trim(trim($descricao), '+');

        $partes = array_values(array_filter(
            array_map('trim', explode('+', $descricaoLimpa)),
            function ($s) { return $s !== ''; }
        ));

        if (empty($partes)) {
            $partes = ['Serviço'];
        }

        $qtd           = count($partes);
        $valorUnitario = round($total / $qtd, 2);

        // Corrige diferença de arredondamento no último item
        $somaParciais = round($valorUnitario * ($qtd - 1), 2);
        $ultimoValor  = round($total - $somaParciais, 2);

        $itens = [];
        foreach ($partes as $index => $nome) {
            $itens[] = [
                'nome'  => $nome,
                'valor' => ($index === ($qtd - 1)) ? $ultimoValor : $valorUnitario,
            ];
        }

        return $itens;
    }

    /**
     * Cria e hidrata uma entidade Venda — sem persistir.
     */
    private function criarVenda(
        int       $estabId,
        string    $cliente,
        float     $total,
        string    $metodo,
        ?int      $petId,
        \DateTime $data,
        string    $origem,
        string    $status,
        ?string   $observacao
    ): Venda {
        $venda = new Venda();
        $venda->setEstabelecimentoId($estabId);
        $venda->setCliente($cliente);
        $venda->setTotal($total);
        $venda->setMetodoPagamento($metodo ?: 'dinheiro');
        $venda->setPetId($petId);
        $venda->setData($data);
        $venda->setOrigem($origem ?: 'clinica');
        $venda->setStatus($status);

        if ($observacao) {
            $venda->setObservacao($observacao);
        }

        return $venda;
    }

    /**
     * Cria e hidrata uma entidade VendaItem — sem persistir.
     * Tipo sempre 'servico' pois dados legados são serviços/atendimentos.
     */
    private function criarVendaItem(Venda $venda, array $itemData): VendaItem
    {
        $item = new VendaItem();
        $item->setVenda($venda);             // usa o atalho setVenda() da entity
        $item->setProdutoId(null);           // legado: sem ID de produto cadastrado
        $item->setProdutoNome($itemData['nome']);
        $item->setQuantidade(1);
        $item->setPrecoUnitario($itemData['valor']);
        $item->setSubtotal($itemData['valor']);
        $item->setTipo('servico');           // dado legado = serviço/atendimento

        return $item;
    }
}