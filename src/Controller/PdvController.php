<?php

namespace App\Controller;

use App\Entity\Venda;
use App\Entity\VendaItem;
use App\Entity\Servico;
use App\Entity\Produto;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\EstoqueMovimento;
use App\Entity\Cliente;
use App\Entity\CaixaMovimento;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clinica/pdv")
 */
class PdvController extends DefaultController
{
    /**
     * @Route("", name="clinica_pdv_index", methods={"GET"})
     */
    public function index(EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // 🔹 Busca produtos, serviços e clientes do estabelecimento
        $repoProdutos = $this->getRepositorio(Produto::class);
        $repoServicos = $this->getRepositorio(Servico::class);
        $repoClientes = $this->getRepositorio(Cliente::class);

        $produtos = $repoProdutos->findBy(['estabelecimentoId' => $baseId]);
        $servicos = $repoServicos->findBy(['estabelecimentoId' => $baseId]);
        $clientes = $repoClientes->findBy(['estabelecimentoId' => $baseId]);

        $itens = [];

        foreach ($produtos as $p) {
            $itens[] = [
                'id'       => $p->getId(),
                'nome'     => $p->getNome(),
                'valor'    => $p->getPrecoVenda() ?? 0,
                'estoque'  => $p->getEstoqueAtual() ?? 0,
                'tipo'     => 'Produto'
            ];
        }

        foreach ($servicos as $s) {
            $itens[] = [
                'id'       => $s->getId(),
                'nome'     => $s->getNome(),
                'valor'    => $s->getValor() ?? 0,
                'estoque'  => null,
                'tipo'     => 'Serviço'
            ];
        }

        return $this->render('clinica/pdv.html.twig', [
            'produtos' => $itens,
            'clientes' => $clientes
        ]);
    }

    /**
     * @Route("/registrar", name="clinica_pdv_registrar", methods={"POST"})
     */
    public function registrar(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $dados = json_decode($request->getContent(), true);

        if (empty($dados['itens'])) {
            return new JsonResponse(['ok' => false, 'msg' => 'Nenhum item informado.'], 400);
        }

        // 🔹 Validação de valor total
        if (empty($dados['total']) || $dados['total'] <= 0) {
            return new JsonResponse(['ok' => false, 'msg' => 'Valor total inválido.'], 400);
        }

        // 🔹 Busca cliente
        $clienteRepo = $this->getRepositorio(Cliente::class);
        $cliente = !empty($dados['cliente_id'])
            ? $clienteRepo->findOneBy(['id' => (int)$dados['cliente_id'], 'estabelecimentoId' => $baseId])
            : null;

        // 🔹 Valida estoque ANTES de processar
        $produtosValidados = [];
        foreach ($dados['itens'] as $item) {
            if ($item['tipo'] === 'Produto') {
                $produto = $em->getRepository(Produto::class)
                              ->findOneBy(['id' => $item['id'], 'estabelecimentoId' => $baseId]);
                
                if (!$produto) {
                    return new JsonResponse([
                        'ok' => false,
                        'msg' => "❌ Produto '{$item['nome']}' não encontrado."
                    ], 404);
                }

                $estoqueAtual = $produto->getEstoqueAtual() ?? 0;
                if ($estoqueAtual < $item['quantidade']) {
                    return new JsonResponse([
                        'ok' => false,
                        'msg' => "❌ Estoque insuficiente para '{$produto->getNome()}'. Disponível: {$estoqueAtual}"
                    ], 400);
                }

                $produtosValidados[$item['id']] = $produto;
            }
        }

        // 🔹 Cria venda com informações adicionais
        $venda = new Venda();
        $venda->setEstabelecimentoId($baseId);
        $venda->setCliente($cliente ? $cliente->getNome() : 'Consumidor Final');
        $venda->setTotal($dados['total']);
        $venda->setMetodoPagamento($dados['metodo']);
        $venda->setData(new \DateTime());
        
        // 🔹 Campos adicionais (troco, bandeira, parcelas, observação)
        if (!empty($dados['troco'])) {
            $venda->setTroco($dados['troco']);
        }
        if (!empty($dados['bandeira'])) {
            $venda->setBandeiraCartao($dados['bandeira']);
        }
        if (!empty($dados['parcelas'])) {
            $venda->setParcelas((int)$dados['parcelas']);
        }
        if (!empty($dados['observacao'])) {
            $venda->setObservacao($dados['observacao']);
        }

        $em->persist($venda);

        // 🔹 Itens + baixa estoque com rastreamento detalhado
        $totalCalculado = 0;
        foreach ($dados['itens'] as $item) {
            $itemVenda = new VendaItem();
            $itemVenda->setVenda($venda);
            $itemVenda->setProduto($item['nome']);
            $itemVenda->setQuantidade($item['quantidade']);
            $itemVenda->setValorUnitario($item['valor']);
            $subtotal = $item['quantidade'] * $item['valor'];
            $itemVenda->setSubtotal($subtotal);
            $totalCalculado += $subtotal;
            $em->persist($itemVenda);

            // 🔹 Baixa estoque se for produto
            if ($item['tipo'] === 'Produto' && isset($produtosValidados[$item['id']])) {
                $produto = $produtosValidados[$item['id']];
                $estoqueAnterior = $produto->getEstoqueAtual() ?? 0;
                $novoEstoque = max(0, $estoqueAnterior - $item['quantidade']);
                $produto->setEstoqueAtual($novoEstoque);

                // 🔹 Registra movimento de estoque detalhado
                $mov = new EstoqueMovimento();
                $mov->setProduto($produto);
                $mov->setEstabelecimentoId($baseId);
                $mov->setTipo('SAIDA');
                $mov->setOrigem('Venda PDV #' . ($venda->getId() ?? 'novo'));
                $mov->setQuantidade($item['quantidade']);
                $mov->setData(new \DateTime());
                $mov->setObservacao("Venda para: " . ($cliente ? $cliente->getNome() : 'Consumidor Final') . 
                                   " | Estoque anterior: {$estoqueAnterior} | Novo estoque: {$novoEstoque}");
                
                $em->persist($produto);
                $em->persist($mov);
            }
        }

        // 🔹 Validação de integridade do total
        if (abs($totalCalculado - $dados['total']) > 0.01) {
            return new JsonResponse([
                'ok' => false,
                'msg' => "❌ Divergência no total. Calculado: R$ " . number_format($totalCalculado, 2, ',', '.') . 
                        " | Informado: R$ " . number_format($dados['total'], 2, ',', '.')
            ], 400);
        }

        // 🔹 Lançamento financeiro aprimorado
        $descricaoCliente = $cliente ? $cliente->getNome() : 'Consumidor Final';
        $descricaoCompleta = "Venda PDV - {$descricaoCliente} | " . count($dados['itens']) . " item(ns)";

        if ($dados['metodo'] === 'pendente') {
            $finPend = new FinanceiroPendente();
            $finPend->setDescricao($descricaoCompleta);
            $finPend->setValor($dados['total']);
            $finPend->setData(new \DateTime());
            $finPend->setMetodoPagamento($dados['metodo']);
            $finPend->setEstabelecimentoId($baseId);
            $finPend->setOrigem('PDV');
            $finPend->setStatus('Pendente');
            $em->persist($finPend);
        } else {
            $fin = new Financeiro();
            $fin->setDescricao($descricaoCompleta);
            $fin->setValor($dados['total']);
            $fin->setData(new \DateTime());
            $fin->setMetodoPagamento($dados['metodo']);
            $fin->setOrigem('PDV');
            $fin->setStatus('Pago');
            $fin->setTipo('ENTRADA');
            $fin->setEstabelecimentoId($baseId);
            $em->persist($fin);
            
            // ⚠️ NÃO registra no CaixaMovimento para evitar duplicação no fluxo de caixa
            // O Financeiro já é capturado no fluxo de caixa
        }

        $em->flush();

        return new JsonResponse([
            'ok' => true, 
            'msg' => '✅ Venda registrada com sucesso!',
            'venda_id' => $venda->getId(),
            'total' => number_format($dados['total'], 2, ',', '.')
        ]);
    }

    /**
     * @Route("/saida", name="clinica_pdv_saida", methods={"POST"})
     */
    public function registrarSaida(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $dados = json_decode($request->getContent(), true);

        // 🔹 Validações aprimoradas
        if (empty($dados['descricao'])) {
            return new JsonResponse(['ok' => false, 'msg' => 'Informe a descrição da saída.'], 400);
        }

        if (empty($dados['valor']) || $dados['valor'] <= 0) {
            return new JsonResponse(['ok' => false, 'msg' => 'Informe um valor válido.'], 400);
        }

        // 🔹 Verifica saldo disponível no caixa
        $repoCaixa = $em->getRepository(CaixaMovimento::class);
        $inicioDia = (new \DateTime('today'))->setTime(0, 0, 0);
        $fimDia = (new \DateTime('today'))->setTime(23, 59, 59);

        $movimentos = $repoCaixa->createQueryBuilder('c')
            ->where('c.estabelecimentoId = :estab')
            ->andWhere('c.data BETWEEN :inicio AND :fim')
            ->setParameter('estab', $baseId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->getQuery()
            ->getResult();

        $saldoAtual = 0;
        foreach ($movimentos as $m) {
            if ($m->getTipo() === 'ENTRADA') {
                $saldoAtual += $m->getValor();
            } else {
                $saldoAtual -= $m->getValor();
            }
        }

        // 🔹 Adiciona entradas do financeiro do dia
        $repoFinanceiro = $em->getRepository(Financeiro::class);
        $financeiros = $repoFinanceiro->createQueryBuilder('f')
            ->where('f.estabelecimentoId = :estab')
            ->andWhere('f.data BETWEEN :inicio AND :fim')
            ->andWhere('f.tipo = :tipo')
            ->setParameter('estab', $baseId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->setParameter('tipo', 'ENTRADA')
            ->getQuery()
            ->getResult();

        foreach ($financeiros as $f) {
            $saldoAtual += $f->getValor();
        }

        // 🔹 Verifica se há saldo suficiente (opcional - pode comentar se não quiser essa validação)
        if (!empty($dados['verificar_saldo']) && $dados['verificar_saldo'] === true) {
            if ($saldoAtual < $dados['valor']) {
                return new JsonResponse([
                    'ok' => false,
                    'msg' => '❌ Saldo insuficiente no caixa. Disponível: R$ ' . number_format($saldoAtual, 2, ',', '.')
                ], 400);
            }
        }

        // 🔹 Registra saída no caixa
        $mov = new CaixaMovimento();
        $mov->setDescricao($dados['descricao']);
        $mov->setValor($dados['valor']);
        $mov->setTipo('SAIDA');
        $mov->setData(new \DateTime());
        $mov->setEstabelecimentoId($baseId);
        $em->persist($mov);

        // 🔹 Opcionalmente registra no financeiro como despesa
        if (!empty($dados['registrar_financeiro']) && $dados['registrar_financeiro'] === true) {
            $fin = new Financeiro();
            $fin->setDescricao('Saída Caixa PDV - ' . $dados['descricao']);
            $fin->setValor($dados['valor']);
            $fin->setData(new \DateTime());
            $fin->setMetodoPagamento($dados['metodo_pagamento'] ?? 'Dinheiro');
            $fin->setOrigem('PDV - Saída');
            $fin->setStatus('Pago');
            $fin->setTipo('SAIDA');
            $fin->setEstabelecimentoId($baseId);
            $em->persist($fin);
        }

        $em->flush();

        $novoSaldo = $saldoAtual - $dados['valor'];
        return new JsonResponse([
            'ok' => true, 
            'msg' => '💸 Saída registrada com sucesso!',
            'valor' => number_format($dados['valor'], 2, ',', '.'),
            'saldo_anterior' => number_format($saldoAtual, 2, ',', '.'),
            'saldo_atual' => number_format($novoSaldo, 2, ',', '.')
        ]);
    }

    
/**
 * @Route("/caixa", name="clinica_pdv_caixa", methods={"GET"})
 */
public function caixa(EntityManagerInterface $em): Response
{
    $this->switchDB();
    $baseId = $this->getIdBase();

    // 🔹 Define o intervalo do dia atual (00:00 até 23:59)
    $inicioDia = (new \DateTime('today'))->setTime(0, 0, 0);
    $fimDia = (new \DateTime('today'))->setTime(23, 59, 59);

    $repoVenda = $em->getRepository(\App\Entity\Venda::class);
    $repoFinanceiro = $em->getRepository(\App\Entity\Financeiro::class);
    $repoCaixa = $em->getRepository(\App\Entity\CaixaMovimento::class);

    // 🔹 1. Busca todos os lançamentos do financeiro de hoje (entradas)
    $financeiros = $repoFinanceiro->createQueryBuilder('f')
        ->where('f.estabelecimentoId = :estab')
        ->andWhere('f.data BETWEEN :inicio AND :fim')
        ->setParameter('estab', $baseId)
        ->setParameter('inicio', $inicioDia)
        ->setParameter('fim', $fimDia)
        ->orderBy('f.data', 'ASC')
        ->getQuery()
        ->getResult();

    $entradas = 0;
    foreach ($financeiros as $f) {
        $entradas += floatval($f->getValor());
    }

    // 🔹 2. Busca as saídas do caixa manual
    $saidasRepo = $repoCaixa->createQueryBuilder('c')
        ->where('c.estabelecimentoId = :estab')
        ->andWhere('c.data BETWEEN :inicio AND :fim')
        ->setParameter('estab', $baseId)
        ->setParameter('inicio', $inicioDia)
        ->setParameter('fim', $fimDia)
        ->orderBy('c.data', 'ASC')
        ->getQuery()
        ->getResult();

    $saidas = 0;
    foreach ($saidasRepo as $s) {
        $saidas += floatval($s->getValor());
    }

    // 🔹 3. Calcula saldo
    $saldo = $entradas - $saidas;

    // 🔹 4. Totais de vendas
    $totalGeral = 0;
    $totais = [];
    if (method_exists($repoVenda, 'totalPorFormaPagamento')) {
        $totais = $repoVenda->totalPorFormaPagamento($baseId, new \DateTime());
    }
    if (method_exists($repoVenda, 'totalGeralDoDia')) {
        $totalGeral = $repoVenda->totalGeralDoDia($baseId, new \DateTime());
    }

    // 🔹 5. Junta todos os registros pro Twig
    $registros = [];

    foreach ($financeiros as $f) {
        $registros[] = [
            'data' => $f->getData(),
            'descricao' => $f->getDescricao(),
            'metodoPagamento' => $f->getMetodoPagamento(),
            'valor' => $f->getValor(),
            'tipo' => 'ENTRADA'
        ];
    }

    foreach ($saidasRepo as $s) {
        $registros[] = [
            'data' => $s->getData(),
            'descricao' => $s->getDescricao(),
            'metodoPagamento' => 'Caixa Manual',
            'valor' => $s->getValor(),
            'tipo' => 'SAIDA'
        ];
    }

    // 🔹 Ordena por data
    usort($registros, fn($a, $b) => $a['data'] <=> $b['data']);

    return $this->render('clinica/pdv_caixa.html.twig', [
        'data'       => new \DateTime(),
        'registros'  => $registros,
        'entradas'   => $entradas,
        'saidas'     => $saidas,
        'saldo'      => $saldo,
        'totais'     => $totais,
        'totalGeral' => $totalGeral,
    ]);
}


    /**
     * @Route("/listar", name="clinica_pdv_listar", methods={"GET"})
     */
    public function listar(EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repo = $em->getRepository(Venda::class);
        $vendas = $repo->findBy(['estabelecimentoId' => $baseId], ['data' => 'DESC']);

        return $this->render('clinica/pdv_listar.html.twig', [
            'vendas' => $vendas,
        ]);
    }

    /**
     * 🔹 Rota AJAX — Listagem de clientes para o Select2 do modal
     * @Route("/clientes/listar", name="clinica_pdv_clientes_listar", methods={"GET"})
     */
    public function listarClientes(EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repo = $this->getRepositorio(Cliente::class);
        $clientes = $repo->findBy(['estabelecimentoId' => $baseId]);

        $dados = array_map(fn($c) => [
            'id' => $c->getId(),
            'text' => $c->getNome(),
        ], $clientes);

        return new JsonResponse(['results' => $dados]);
    }

    /**
     * 🔹 Entrada de estoque manual
     * @Route("/estoque/entrada", name="clinica_pdv_estoque_entrada", methods={"POST"})
     */
    public function entradaEstoque(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $dados = json_decode($request->getContent(), true);

        // 🔹 Validações
        if (empty($dados['produto_id']) || empty($dados['quantidade'])) {
            return new JsonResponse(['ok' => false, 'msg' => 'Informe o produto e a quantidade.'], 400);
        }

        if ($dados['quantidade'] <= 0) {
            return new JsonResponse(['ok' => false, 'msg' => 'Quantidade deve ser maior que zero.'], 400);
        }

        // 🔹 Busca produto
        $produto = $em->getRepository(Produto::class)
                     ->findOneBy(['id' => $dados['produto_id'], 'estabelecimentoId' => $baseId]);

        if (!$produto) {
            return new JsonResponse(['ok' => false, 'msg' => 'Produto não encontrado.'], 404);
        }

        // 🔹 Atualiza estoque
        $estoqueAnterior = $produto->getEstoqueAtual() ?? 0;
        $novoEstoque = $estoqueAnterior + $dados['quantidade'];
        $produto->setEstoqueAtual($novoEstoque);

        // 🔹 Registra movimento
        $mov = new EstoqueMovimento();
        $mov->setProduto($produto);
        $mov->setEstabelecimentoId($baseId);
        $mov->setTipo('ENTRADA');
        $mov->setOrigem($dados['origem'] ?? 'Entrada Manual PDV');
        $mov->setQuantidade($dados['quantidade']);
        $mov->setData(new \DateTime());
        $mov->setObservacao($dados['observacao'] ?? "Estoque anterior: {$estoqueAnterior} | Novo estoque: {$novoEstoque}");

        $em->persist($produto);
        $em->persist($mov);
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'msg' => '✅ Entrada de estoque registrada!',
            'produto' => $produto->getNome(),
            'estoque_anterior' => $estoqueAnterior,
            'quantidade_entrada' => $dados['quantidade'],
            'estoque_atual' => $novoEstoque
        ]);
    }

    /**
     * 🔹 Ajuste de estoque (correção)
     * @Route("/estoque/ajuste", name="clinica_pdv_estoque_ajuste", methods={"POST"})
     */
    public function ajusteEstoque(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $dados = json_decode($request->getContent(), true);

        // 🔹 Validações
        if (empty($dados['produto_id']) || !isset($dados['novo_estoque'])) {
            return new JsonResponse(['ok' => false, 'msg' => 'Informe o produto e o novo estoque.'], 400);
        }

        if ($dados['novo_estoque'] < 0) {
            return new JsonResponse(['ok' => false, 'msg' => 'Estoque não pode ser negativo.'], 400);
        }

        // 🔹 Busca produto
        $produto = $em->getRepository(Produto::class)
                     ->findOneBy(['id' => $dados['produto_id'], 'estabelecimentoId' => $baseId]);

        if (!$produto) {
            return new JsonResponse(['ok' => false, 'msg' => 'Produto não encontrado.'], 404);
        }

        // 🔹 Calcula diferença
        $estoqueAnterior = $produto->getEstoqueAtual() ?? 0;
        $diferenca = $dados['novo_estoque'] - $estoqueAnterior;
        
        if ($diferenca == 0) {
            return new JsonResponse(['ok' => false, 'msg' => 'O estoque já está no valor informado.'], 400);
        }

        // 🔹 Atualiza estoque
        $produto->setEstoqueAtual($dados['novo_estoque']);

        // 🔹 Registra movimento de ajuste
        $mov = new EstoqueMovimento();
        $mov->setProduto($produto);
        $mov->setEstabelecimentoId($baseId);
        $mov->setTipo('AJUSTE');
        $mov->setOrigem('Ajuste Manual PDV');
        $mov->setQuantidade(abs($diferenca));
        $mov->setData(new \DateTime());
        $mov->setObservacao(
            ($dados['motivo'] ?? 'Ajuste de estoque') . 
            " | Estoque anterior: {$estoqueAnterior} | Novo estoque: {$dados['novo_estoque']} | " .
            ($diferenca > 0 ? "Acréscimo: +{$diferenca}" : "Redução: {$diferenca}")
        );

        $em->persist($produto);
        $em->persist($mov);
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'msg' => '✅ Estoque ajustado com sucesso!',
            'produto' => $produto->getNome(),
            'estoque_anterior' => $estoqueAnterior,
            'estoque_atual' => $dados['novo_estoque'],
            'diferenca' => $diferenca
        ]);
    }

    /**
     * 🔹 Consulta movimentação de estoque
     * @Route("/estoque/movimentos/{produtoId}", name="clinica_pdv_estoque_movimentos", methods={"GET"})
     */
    public function movimentosEstoque(int $produtoId, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // 🔹 Busca produto
        $produto = $em->getRepository(Produto::class)
                     ->findOneBy(['id' => $produtoId, 'estabelecimentoId' => $baseId]);

        if (!$produto) {
            return new JsonResponse(['ok' => false, 'msg' => 'Produto não encontrado.'], 404);
        }

        // 🔹 Busca movimentos
        $movimentos = $em->getRepository(EstoqueMovimento::class)
                        ->createQueryBuilder('m')
                        ->where('m.produto = :produto')
                        ->andWhere('m.estabelecimentoId = :estab')
                        ->setParameter('produto', $produto)
                        ->setParameter('estab', $baseId)
                        ->orderBy('m.data', 'DESC')
                        ->setMaxResults(50)
                        ->getQuery()
                        ->getResult();

        $dados = array_map(function($m) {
            return [
                'id' => $m->getId(),
                'data' => $m->getData()->format('d/m/Y H:i'),
                'tipo' => $m->getTipo(),
                'quantidade' => $m->getQuantidade(),
                'origem' => $m->getOrigem(),
                'observacao' => $m->getObservacao()
            ];
        }, $movimentos);

        return new JsonResponse([
            'ok' => true,
            'produto' => [
                'id' => $produto->getId(),
                'nome' => $produto->getNome(),
                'estoque_atual' => $produto->getEstoqueAtual() ?? 0
            ],
            'movimentos' => $dados
        ]);
    }

    /**
     * 🔹 Relatório de estoque baixo
     * @Route("/estoque/alerta", name="clinica_pdv_estoque_alerta", methods={"GET"})
     */
    public function alertaEstoque(EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // 🔹 Busca produtos com estoque baixo (menos de 10 unidades)
        $produtos = $em->getRepository(Produto::class)
                      ->createQueryBuilder('p')
                      ->where('p.estabelecimentoId = :estab')
                      ->andWhere('p.estoqueAtual < :minimo')
                      ->setParameter('estab', $baseId)
                      ->setParameter('minimo', 10)
                      ->orderBy('p.estoqueAtual', 'ASC')
                      ->getQuery()
                      ->getResult();

        $dados = array_map(function($p) {
            return [
                'id' => $p->getId(),
                'nome' => $p->getNome(),
                'estoque_atual' => $p->getEstoqueAtual() ?? 0,
                'preco_venda' => $p->getPrecoVenda() ?? 0,
                'status' => ($p->getEstoqueAtual() ?? 0) == 0 ? 'ESGOTADO' : 'BAIXO'
            ];
        }, $produtos);

        return new JsonResponse([
            'ok' => true,
            'total_alertas' => count($dados),
            'produtos' => $dados
        ]);
    }

    /**
     * 🔹 Resumo de vendas do dia
     * @Route("/vendas/resumo", name="clinica_pdv_vendas_resumo", methods={"GET"})
     */
    public function resumoVendas(EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $inicioDia = (new \DateTime('today'))->setTime(0, 0, 0);
        $fimDia = (new \DateTime('today'))->setTime(23, 59, 59);

        // 🔹 Busca vendas do dia
        $vendas = $em->getRepository(Venda::class)
                    ->createQueryBuilder('v')
                    ->where('v.estabelecimentoId = :estab')
                    ->andWhere('v.data BETWEEN :inicio AND :fim')
                    ->setParameter('estab', $baseId)
                    ->setParameter('inicio', $inicioDia)
                    ->setParameter('fim', $fimDia)
                    ->getQuery()
                    ->getResult();

        $totalVendas = 0;
        $quantidadeVendas = count($vendas);
        $porMetodo = [];

        foreach ($vendas as $v) {
            $totalVendas += $v->getTotal();
            $metodo = $v->getMetodoPagamento();
            
            if (!isset($porMetodo[$metodo])) {
                $porMetodo[$metodo] = ['quantidade' => 0, 'total' => 0];
            }
            
            $porMetodo[$metodo]['quantidade']++;
            $porMetodo[$metodo]['total'] += $v->getTotal();
        }

        return new JsonResponse([
            'ok' => true,
            'data' => (new \DateTime())->format('d/m/Y'),
            'resumo' => [
                'quantidade_vendas' => $quantidadeVendas,
                'total_vendas' => number_format($totalVendas, 2, ',', '.'),
                'ticket_medio' => $quantidadeVendas > 0 ? number_format($totalVendas / $quantidadeVendas, 2, ',', '.') : '0,00',
                'por_metodo' => $porMetodo
            ]
        ]);
    }
}
