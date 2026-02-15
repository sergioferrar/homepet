<?php

namespace App\Service;

use App\DTO\RegistrarSaidaDTO;
use App\Entity\CaixaMovimento;
use App\Entity\Financeiro;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Serviço para gerenciamento de caixa do PDV
 * Controla entradas, saídas e saldo
 */
class CaixaService
{
    private EntityManagerInterface $em;
    private TenantContext $tenantContext;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em,
        TenantContext $tenantContext,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->tenantContext = $tenantContext;
        $this->logger = $logger;
    }

    /**
     * Registra saída de caixa
     */
    public function registrarSaida(RegistrarSaidaDTO $dto): array
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            $saldoAtual = $this->calcularSaldoDoDia();

            // Verifica saldo se solicitado
            if ($dto->verificarSaldo && $saldoAtual < $dto->valor) {
                return [
                    'ok' => false,
                    'msg' => 'Saldo insuficiente no caixa. Disponível: R$ ' . 
                             number_format($saldoAtual, 2, ',', '.')
                ];
            }

            // Registra movimento de caixa
            $movimento = new CaixaMovimento();
            $movimento->setDescricao($dto->descricao);
            $movimento->setValor($dto->valor);
            $movimento->setTipo('SAIDA');
            $movimento->setData(new \DateTime());
            $movimento->setEstabelecimentoId($estabelecimentoId);
            $this->em->persist($movimento);

            // Registra no financeiro se solicitado
            if ($dto->registrarFinanceiro) {
                $this->registrarSaidaNoFinanceiro($dto, $estabelecimentoId);
            }

            $this->em->flush();

            $novoSaldo = $saldoAtual - $dto->valor;

            $this->logger->info('Saída de caixa registrada', [
                'estabelecimento_id' => $estabelecimentoId,
                'valor' => $dto->valor,
                'descricao' => $dto->descricao
            ]);

            return [
                'ok' => true,
                'msg' => 'Saída registrada com sucesso!',
                'valor' => number_format($dto->valor, 2, ',', '.'),
                'saldo_anterior' => number_format($saldoAtual, 2, ',', '.'),
                'saldo_atual' => number_format($novoSaldo, 2, ',', '.')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao registrar saída de caixa', [
                'erro' => $e->getMessage(),
                'estabelecimento_id' => $estabelecimentoId
            ]);

            return [
                'ok' => false,
                'msg' => 'Erro ao registrar saída: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calcula saldo do caixa do dia atual
     */
    public function calcularSaldoDoDia(): float
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();
        list($inicioDia, $fimDia) = $this->getIntervaloHoje();

        $saldo = 0;

        // Entradas do financeiro
        $financeiros = $this->em->getRepository(Financeiro::class)
            ->createQueryBuilder('f')
            ->where('f.estabelecimentoId = :estab')
            ->andWhere('f.data BETWEEN :inicio AND :fim')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->getQuery()
            ->getResult();

        foreach ($financeiros as $f) {
            $saldo += $f->getValor();
        }

        // Saídas do caixa
        $movimentos = $this->em->getRepository(CaixaMovimento::class)
            ->createQueryBuilder('c')
            ->where('c.estabelecimentoId = :estab')
            ->andWhere('c.data BETWEEN :inicio AND :fim')
            ->andWhere('c.tipo = :tipo')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->setParameter('tipo', 'SAIDA')
            ->getQuery()
            ->getResult();

        foreach ($movimentos as $m) {
            $saldo -= $m->getValor();
        }

        return $saldo;
    }

    /**
     * Retorna resumo do caixa do dia
     */
    public function getResumoDoDia(): array
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();
        list($inicioDia, $fimDia) = $this->getIntervaloHoje();

        $entradas = 0;
        $saidas = 0;

        // Busca entradas (financeiro)
        $financeiros = $this->em->getRepository(Financeiro::class)
            ->createQueryBuilder('f')
            ->where('f.estabelecimentoId = :estab')
            ->andWhere('f.data BETWEEN :inicio AND :fim')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->orderBy('f.data', 'ASC')
            ->getQuery()
            ->getResult();

        $registrosEntradas = [];
        foreach ($financeiros as $f) {
            $entradas += $f->getValor();
            $registrosEntradas[] = [
                'data' => $f->getData(),
                'descricao' => $f->getDescricao(),
                'metodoPagamento' => $f->getMetodoPagamento(),
                'valor' => $f->getValor(),
                'tipo' => 'ENTRADA'
            ];
        }

        // Busca saídas (caixa movimento)
        $movimentos = $this->em->getRepository(CaixaMovimento::class)
            ->createQueryBuilder('c')
            ->where('c.estabelecimentoId = :estab')
            ->andWhere('c.data BETWEEN :inicio AND :fim')
            ->setParameter('estab', $estabelecimentoId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->orderBy('c.data', 'ASC')
            ->getQuery()
            ->getResult();

        $registrosSaidas = [];
        foreach ($movimentos as $m) {
            $saidas += $m->getValor();
            $registrosSaidas[] = [
                'data' => $m->getData(),
                'descricao' => $m->getDescricao(),
                'metodoPagamento' => 'Caixa Manual',
                'valor' => $m->getValor(),
                'tipo' => 'SAIDA'
            ];
        }

        // Combina e ordena todos os registros
        $todosRegistros = array_merge($registrosEntradas, $registrosSaidas);
        usort($todosRegistros, fn($a, $b) => $a['data'] <=> $b['data']);

        return [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $entradas - $saidas,
            'registros' => $todosRegistros
        ];
    }

    /**
     * Registra saída no financeiro
     */
    private function registrarSaidaNoFinanceiro(RegistrarSaidaDTO $dto, int $estabelecimentoId): void
    {
        $financeiro = new Financeiro();
        $financeiro->setDescricao('Saída Caixa PDV - ' . $dto->descricao);
        $financeiro->setValor($dto->valor);
        $financeiro->setData(new \DateTime());
        $financeiro->setMetodoPagamento($dto->metodoPagamento);
        $financeiro->setOrigem('PDV - Saída');
        $financeiro->setStatus('Pago');
        $financeiro->setTipo('SAIDA');
        $financeiro->setEstabelecimentoId($estabelecimentoId);

        $this->em->persist($financeiro);
    }

    /**
     * Retorna intervalo do dia atual (00:00 até 23:59)
     */
    private function getIntervaloHoje(): array
    {
        $inicioDia = (new \DateTime('today'))->setTime(0, 0, 0);
        $fimDia = (new \DateTime('today'))->setTime(23, 59, 59);

        return [$inicioDia, $fimDia];
    }
}
