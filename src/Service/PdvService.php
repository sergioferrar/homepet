<?php

namespace App\Service;

use App\DTO\FinalizarCarrinhoDTO;
use App\DTO\RegistrarVendaDTO;
use App\Entity\Cliente;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Venda;
use App\Entity\VendaItem;
use App\Service\NotaFiscal\NotaFiscalServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Serviço principal do PDV
 * Centraliza toda lógica de negócio de vendas
 * Garante isolamento por estabelecimento
 */
class PdvService
{
    private EntityManagerInterface $em;
    private TenantContext $tenantContext;
    private EstoqueService $estoqueService;
    private NotaFiscalServiceInterface $notaFiscalService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        TenantContext $tenantContext,
        EstoqueService $estoqueService,
        NotaFiscalServiceInterface $notaFiscalService,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->tenantContext = $tenantContext;
        $this->estoqueService = $estoqueService;
        $this->notaFiscalService = $notaFiscalService;
        $this->logger = $logger;
    }

    /**
     * Registra nova venda no PDV
     * 
     * @param RegistrarVendaDTO $dto
     * @return array Resultado da operação com ID da venda
     * @throws \Exception
     */
    public function registrarVenda(RegistrarVendaDTO $dto): array
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        $this->em->beginTransaction();
        
        try {
            // 1. Busca cliente (se informado)
            $cliente = $this->buscarCliente($dto->clienteId);
            $nomeCliente = $cliente ? $cliente->getNome() : 'Consumidor Final';

            // 2. Valida estoque ANTES de processar
            $produtosValidados = $this->estoqueService->validarEstoque($dto->itens);

            // 3. Cria venda
            $venda = $this->criarVenda($dto, $estabelecimentoId, $nomeCliente);
            $this->em->persist($venda);
            $this->em->flush(); // Flush para obter ID da venda

            // 4. Adiciona itens e valida total
            $totalCalculado = $this->adicionarItensVenda($venda, $dto->itens);
            $this->validarTotalVenda($totalCalculado, $dto->total);

            // 5. Baixa estoque dos produtos
            $this->estoqueService->baixarEstoque(
                $dto->itens,
                $produtosValidados,
                $venda->getId(),
                $nomeCliente
            );

            // 6. Registra no financeiro
            $this->registrarFinanceiro($venda, $nomeCliente, count($dto->itens));

            $this->em->flush();
            $this->em->commit();

            $this->logger->info('Venda registrada com sucesso', [
                'venda_id' => $venda->getId(),
                'estabelecimento_id' => $estabelecimentoId,
                'total' => $dto->total
            ]);

            return [
                'ok' => true,
                'msg' => 'Venda registrada com sucesso!',
                'venda_id' => $venda->getId(),
                'total' => number_format($dto->total, 2, ',', '.')
            ];

        } catch (\Exception $e) {
            $this->em->rollback();
            
            $this->logger->error('Erro ao registrar venda', [
                'erro' => $e->getMessage(),
                'estabelecimento_id' => $estabelecimentoId
            ]);

            throw $e;
        }
    }

    /**
     * Finaliza venda em carrinho
     */
    public function finalizarCarrinho(FinalizarCarrinhoDTO $dto): array
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            // Busca venda em carrinho
            $venda = $this->em->getRepository(Venda::class)->findOneBy([
                'id' => $dto->vendaId,
                'estabelecimentoId' => $estabelecimentoId,
                'status' => 'Carrinho'
            ]);

            if (!$venda) {
                return [
                    'status' => 'error',
                    'mensagem' => 'Venda não encontrada ou já finalizada.'
                ];
            }

            // Atualiza venda
            $status = ($dto->metodoPagamento === 'pendente') ? 'Pendente' : 'Aberta';
            $venda->setStatus($status);
            $venda->setMetodoPagamento($dto->metodoPagamento);
            $venda->setBandeiraCartao($dto->bandeiraCartao);
            $venda->setParcelas($dto->parcelas);
            $venda->setData(new \DateTime());

            // Registra financeiro pendente se necessário
            if ($dto->metodoPagamento === 'pendente') {
                $this->registrarFinanceiroPendente($venda);
            }

            // Tenta emitir nota fiscal (se habilitado)
            if ($this->notaFiscalService->isDisponivel()) {
                try {
                    $resultadoNF = $this->notaFiscalService->emitir($venda);
                    $this->logger->info('Nota fiscal emitida', [
                        'venda_id' => $venda->getId(),
                        'resultado' => $resultadoNF
                    ]);
                } catch (\Exception $e) {
                    $this->logger->warning('Falha ao emitir nota fiscal', [
                        'venda_id' => $venda->getId(),
                        'erro' => $e->getMessage()
                    ]);
                }
            }

            $this->em->flush();

            return [
                'status' => 'success',
                'mensagem' => 'Venda finalizada com sucesso!'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao finalizar venda', [
                'venda_id' => $dto->vendaId,
                'erro' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'mensagem' => 'Erro ao finalizar venda: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca cliente por ID com validação de tenant
     */
    private function buscarCliente(?int $clienteId): ?Cliente
    {
        if (!$clienteId) {
            return null;
        }

        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        return $this->em->getRepository(Cliente::class)->findOneBy([
            'id' => $clienteId,
            'estabelecimentoId' => $estabelecimentoId
        ]);
    }

    /**
     * Cria entidade Venda
     */
    private function criarVenda(RegistrarVendaDTO $dto, int $estabelecimentoId, string $nomeCliente): Venda
    {
        $venda = new Venda();
        $venda->setEstabelecimentoId($estabelecimentoId);
        $venda->setCliente($nomeCliente);
        $venda->setTotal($dto->total);
        $venda->setMetodoPagamento($dto->metodo);
        $venda->setData(new \DateTime());
        $venda->setOrigem($dto->origem);
        $venda->setStatus('Carrinho');

        if ($dto->troco !== null) {
            $venda->setTroco($dto->troco);
        }
        if ($dto->bandeira !== null) {
            $venda->setBandeiraCartao($dto->bandeira);
        }
        if ($dto->parcelas !== null) {
            $venda->setParcelas($dto->parcelas);
        }
        if ($dto->observacao !== null) {
            $venda->setObservacao($dto->observacao);
        }
        if ($dto->petId !== null) {
            $venda->setPetId($dto->petId);
        }

        return $venda;
    }

    /**
     * Adiciona itens à venda e retorna total calculado
     */
    private function adicionarItensVenda(Venda $venda, array $itens): float
    {
        $totalCalculado = 0;

        foreach ($itens as $item) {
            $vendaItem = new VendaItem();
            $vendaItem->setVenda($venda);
            $vendaItem->setProduto($item['nome']);
            $vendaItem->setQuantidade($item['quantidade']);
            $vendaItem->setValorUnitario($item['valor']);
            $vendaItem->setTipo($item['tipo']);
            
            $subtotal = $item['quantidade'] * $item['valor'];
            $vendaItem->setSubtotal($subtotal);
            $totalCalculado += $subtotal;

            $this->em->persist($vendaItem);
        }

        return $totalCalculado;
    }

    /**
     * Valida se total calculado bate com o informado
     */
    private function validarTotalVenda(float $totalCalculado, float $totalInformado): void
    {
        if (abs($totalCalculado - $totalInformado) > 0.01) {
            throw new \RuntimeException(
                "Divergência no total. " .
                "Calculado: R$ " . number_format($totalCalculado, 2, ',', '.') .
                " | Informado: R$ " . number_format($totalInformado, 2, ',', '.')
            );
        }
    }

    /**
     * Registra venda no financeiro
     */
    private function registrarFinanceiro(Venda $venda, string $nomeCliente, int $qtdItens): void
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();
        $descricao = "Venda PDV - {$nomeCliente} | {$qtdItens} item(ns)";

        if ($venda->getMetodoPagamento() === 'pendente') {
            $finPendente = new FinanceiroPendente();
            $finPendente->setDescricao($descricao);
            $finPendente->setValor($venda->getTotal());
            $finPendente->setData(new \DateTime());
            $finPendente->setMetodoPagamento($venda->getMetodoPagamento());
            $finPendente->setEstabelecimentoId($estabelecimentoId);
            $finPendente->setOrigem('PDV');
            $finPendente->setStatus('Pendente');
            
            if ($venda->getPetId()) {
                $finPendente->setPetId($venda->getPetId());
            }

            $this->em->persist($finPendente);
        } else {
            $financeiro = new Financeiro();
            $financeiro->setDescricao($descricao);
            $financeiro->setValor($venda->getTotal());
            $financeiro->setData(new \DateTime());
            $financeiro->setMetodoPagamento($venda->getMetodoPagamento());
            $financeiro->setOrigem('PDV');
            $financeiro->setStatus('Pago');
            $financeiro->setTipo('ENTRADA');
            $financeiro->setEstabelecimentoId($estabelecimentoId);

            $this->em->persist($financeiro);
        }
    }

    /**
     * Registra financeiro pendente na finalização de carrinho
     */
    private function registrarFinanceiroPendente(Venda $venda): void
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        $finPendente = new FinanceiroPendente();
        $finPendente->setDescricao(
            'Venda ' . ucfirst($venda->getOrigem()) . ' - Pet ID: ' . $venda->getPetId()
        );
        $finPendente->setValor($venda->getTotal());
        $finPendente->setData(new \DateTime());
        $finPendente->setPetId($venda->getPetId());
        $finPendente->setEstabelecimentoId($estabelecimentoId);
        $finPendente->setMetodoPagamento('pendente');
        $finPendente->setStatus('Pendente');
        $finPendente->setOrigem($venda->getOrigem());

        $this->em->persist($finPendente);
    }
}
