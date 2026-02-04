<?php

namespace App\Controller;

use App\Entity\CaixaMovimento;
use App\Entity\Cliente;
use App\Entity\EstoqueMovimento;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Produto;
use App\Entity\Servico;
use App\Entity\Venda;
use App\Entity\VendaItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica/pdv")
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
        $repoVendas = $this->getRepositorio(Venda::class);
        $repoVendaItem = $this->getRepositorio(VendaItem::class);

        $produtosEntities = $repoProdutos->findBy(["estabelecimentoId" => $baseId]);
        $servicosEntities = $repoServicos->findBy(["estabelecimentoId" => $baseId]);
        $clientes = $repoClientes->findBy(["estabelecimentoId" => $baseId]);

        // 🔹 Busca vendas em carrinho (aguardando finalização)
        $vendasCarrinho = $repoVendas->findCarrinho($baseId);
        
        // Busca itens de cada venda em carrinho
        foreach ($vendasCarrinho as &$venda) {
            $itens = $repoVendaItem->findBy(['venda' => $venda['id']]);
            $venda['itens'] = [];
            
            foreach ($itens as $item) {
                $servico = $repoServicos->find($item->getProduto());
                $venda['itens'][] = [
                    'descricao' => $servico ? $servico->getNome() : 'Serviço',
                    'quantidade' => $item->getQuantidade(),
                    'valor_unitario' => $item->getValorUnitario(),
                    'subtotal' => $item->getSubtotal(),
                ];
            }
        }

        $itensNormalizados = [];

        // Normaliza produtos
        foreach ($produtosEntities as $p) {
            $itensNormalizados[] = [
                "id" => "prod_" . $p->getId(), // Prefixo para diferenciar de serviços
                "nome" => $p->getNome(),
                "valor" => $p->getPrecoVenda() ?? 0.0, // Usa 'valor' como campo unificado
                "estoque" => $p->getEstoqueAtual() ?? 0,
                "tipo" => "Produto",
                "descricao" => $p->getNome() ?? "", // Adiciona descrição para o modal
            ];
        }

        // Normaliza serviços
        foreach ($servicosEntities as $s) {
            $itensNormalizados[] = [
                "id" => "serv_" . $s->getId(), // Prefixo para diferenciar de produtos
                "nome" => $s->getNome(),
                "valor" => $s->getValor() ?? 0.0, // Usa 'valor' como campo unificado
                "estoque" => null, // Serviços geralmente não têm estoque
                "tipo" => "Serviço",
                "descricao" => $s->getDescricao() ?? "", // Adiciona descrição para o modal
            ];
        }

        // Opcional: Ordenar os itens por nome para melhor UX no modal de seleção
        usort($itensNormalizados, function($a, $b) {
            return strcmp($a["nome"], $b["nome"]);
        });

        return $this->render("clinica/pdv.html.twig", [
            "produtos" => $itensNormalizados, // Passa a lista normalizada para a view
            "clientes" => $clientes,
            "vendas_carrinho" => $vendasCarrinho, // Passa vendas em carrinho
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
                // Extrai o ID numérico removendo o prefixo "prod_"
                $produtoId = str_replace('prod_', '', $item['id']);
                
                $produto = $em->getRepository(Produto::class)
                    ->findOneBy(['id' => (int)$produtoId, 'estabelecimentoId' => $baseId]);

                if (!$produto) {
                    return new JsonResponse([
                        'ok' => false,
                        'msg' => "❌ Produto '{$item['nome']}' não encontrado.",
                    ], 404);
                }

                $estoqueAtual = $produto->getEstoqueAtual() ?? 0;
                if ($estoqueAtual < $item['quantidade']) {
                    return new JsonResponse([
                        'ok' => false,
                        'msg' => "❌ Estoque insuficiente para '{$produto->getNome()}'. Disponível: {$estoqueAtual}",
                    ], 400);
                }

                $produtosValidados[$produtoId] = $produto;
            }
        }

        // 🔹 Cria venda com informações adicionais
        
        $venda = new Venda();
        $venda->setEstabelecimentoId($baseId);
        $venda->setCliente($cliente ? $cliente->getNome() : 'Consumidor Final');
        $venda->setTotal($dados['total']);
        $venda->setMetodoPagamento($dados['metodo']);
        $venda->setData(new \DateTime());
        $venda->setOrigem($dados['origem']);
        $venda->setStatus('Carrinho'); // Define status como Carrinho para finalizar depois


        // 🔹 Campos adicionais (troco, bandeira, parcelas, observação, pet)
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
        if (!empty($dados['pet_id'])) {
            $venda->setPetId((int)$dados['pet_id']);
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
            if ($item['tipo'] === 'Produto') {
                // Extrai o ID numérico removendo o prefixo "prod_"
                $produtoId = str_replace('prod_', '', $item['id']);
                
                if (isset($produtosValidados[$produtoId])) {
                    $produto = $produtosValidados[$produtoId];
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
        }

        // 🔹 Validação de integridade do total
        if (abs($totalCalculado - $dados['total']) > 0.01) {
            return new JsonResponse([
                'ok' => false,
                'msg' => "❌ Divergência no total. Calculado: R$ " . number_format($totalCalculado, 2, ',', '.') .
                    " | Informado: R$ " . number_format($dados['total'], 2, ',', '.'),
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
            'total' => number_format($dados['total'], 2, ',', '.'),
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
                    'msg' => '❌ Saldo insuficiente no caixa. Disponível: R$ ' . number_format($saldoAtual, 2, ',', '.'),
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
            'saldo_atual' => number_format($novoSaldo, 2, ',', '.'),
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
                'tipo' => 'ENTRADA',
            ];
        }

        foreach ($saidasRepo as $s) {
            $registros[] = [
                'data' => $s->getData(),
                'descricao' => $s->getDescricao(),
                'metodoPagamento' => 'Caixa Manual',
                'valor' => $s->getValor(),
                'tipo' => 'SAIDA',
            ];
        }

        // 🔹 Ordena por data
        usort($registros, fn($a, $b) => $a['data'] <=> $b['data']);

        return $this->render('clinica/pdv_caixa.html.twig', [
            'data' => new \DateTime(),
            'registros' => $registros,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $saldo,
            'totais' => $totais,
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
            'estoque_atual' => $novoEstoque,
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
            'diferenca' => $diferenca,
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

        $dados = array_map(function ($m) {
            return [
                'id' => $m->getId(),
                'data' => $m->getData()->format('d/m/Y H:i'),
                'tipo' => $m->getTipo(),
                'quantidade' => $m->getQuantidade(),
                'origem' => $m->getOrigem(),
                'observacao' => $m->getObservacao(),
            ];
        }, $movimentos);

        return new JsonResponse([
            'ok' => true,
            'produto' => [
                'id' => $produto->getId(),
                'nome' => $produto->getNome(),
                'estoque_atual' => $produto->getEstoqueAtual() ?? 0,
            ],
            'movimentos' => $dados,
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

        $dados = array_map(function ($p) {
            return [
                'id' => $p->getId(),
                'nome' => $p->getNome(),
                'estoque_atual' => $p->getEstoqueAtual() ?? 0,
                'preco_venda' => $p->getPrecoVenda() ?? 0,
                'status' => ($p->getEstoqueAtual() ?? 0) == 0 ? 'ESGOTADO' : 'BAIXO',
            ];
        }, $produtos);

        return new JsonResponse([
            'ok' => true,
            'total_alertas' => count($dados),
            'produtos' => $dados,
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

        // 🔹 Busca vendas do dia (exclui Pendente, Inativa e Carrinho)
        $vendas = $em->getRepository(Venda::class)
            ->createQueryBuilder('v')
            ->where('v.estabelecimentoId = :estab')
            ->andWhere('v.data BETWEEN :inicio AND :fim')
            ->andWhere('v.status NOT IN (:statusExcluidos)')
            ->setParameter('estab', $baseId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->setParameter('statusExcluidos', ['Pendente', 'Inativa', 'Carrinho'])
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
                'por_metodo' => $porMetodo,
            ],
        ]);
    }

    /**
     * @Route("/pet/{petId}/vendas", name="clinica_pdv_pet_vendas", methods={"GET"})
     */
    public function vendasPorPet(int $petId, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        try {
            // Buscar pet
            $pet = $em->getRepository(Pet::class)
                ->findOneBy(['id' => $petId, 'estabelecimentoId' => $baseId]);

            if (!$pet) {
                return new JsonResponse(['ok' => false, 'msg' => 'Pet não encontrado'], 404);
            }

            // Buscar vendas do pet
            $vendas = $em->getRepository(Venda::class)
                ->createQueryBuilder('v')
                ->where('v.petId = :petId')
                ->andWhere('v.estabelecimentoId = :estab')
                ->setParameter('petId', $petId)
                ->setParameter('estab', $baseId)
                ->orderBy('v.data', 'DESC')
                ->getQuery()
                ->getResult();

            $vendasFormatadas = [];
            $totalGasto = 0;

            foreach ($vendas as $venda) {
                $totalGasto += $venda->getTotal();

                // Buscar itens da venda
                $itens = $em->getRepository(VendaItem::class)
                    ->findBy(['venda' => $venda]);

                $itensFormatados = [];
                foreach ($itens as $item) {
                    $itensFormatados[] = [
                        'produto' => $item->getProduto(),
                        'quantidade' => $item->getQuantidade(),
                        'valor_unitario' => number_format($item->getValorUnitario(), 2, ',', '.'),
                        'subtotal' => number_format($item->getSubtotal(), 2, ',', '.'),
                    ];
                }

                $vendasFormatadas[] = [
                    'id' => $venda->getId(),
                    'data' => $venda->getData()->format('d/m/Y H:i'),
                    'total' => number_format($venda->getTotal(), 2, ',', '.'),
                    'metodo_pagamento' => $venda->getMetodoPagamento(),
                    'observacao' => $venda->getObservacao(),
                    'itens' => $itensFormatados,
                ];
            }

            return new JsonResponse([
                'ok' => true,
                'pet' => [
                    'id' => $pet->getId(),
                    'nome' => $pet->getNome(),
                    'especie' => $pet->getEspecie(),
                    'raca' => $pet->getRaca(),
                ],
                'vendas' => $vendasFormatadas,
                'resumo' => [
                    'total_vendas' => count($vendas),
                    'total_gasto' => number_format($totalGasto, 2, ',', '.'),
                    'ticket_medio' => count($vendas) > 0 ? number_format($totalGasto / count($vendas), 2, ',', '.') : '0,00',
                ],
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/autocomplete/tutor", name="clinica_autocomplete_tutor", methods={"GET"})
     */
    public function autocompleteTutor(Request $request): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $query = $request->query->get('query');

        if (empty($query) || strlen($query) < 3) {
            return new JsonResponse([]);
        }

        // Busca por clientes cujo nome contenha a query
        $clienteRepo = $this->getRepositorio(Cliente::class);
        $clientes = $clienteRepo->findByNomeLike($baseId, $query);

        $results = [];
        foreach ($clientes as $cliente) {
            $results[] = [
                'id' => $cliente['id'],
                'nome' => $cliente['nome'],
            ];
        }

        return new JsonResponse($results);
    }

    /**
     * @Route("/pets-by-tutor/{id}", name="clinica_pets_by_tutor", methods={"GET"})
     */
    public function getPetsByTutor(int $id): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $clienteRepo = $this->getRepositorio(Cliente::class);
        $pets = $clienteRepo->listarPetsDoCliente($baseId, $id);

        $results = [];
        foreach ($pets as $pet) {
            $results[] = [
                'id' => $pet['id'],
                'nome' => $pet['nome'],
            ];
        }

        return new JsonResponse($results);
    }

    /**
     * @Route("/carrinho/finalizar/{id}", name="pdv_finalizar_carrinho", methods={"POST"})
     */
    public function finalizarCarrinho(Request $request, int $id, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        try {
            $repoVendas = $this->getRepositorio(Venda::class);
            
            // Busca a venda em carrinho
            $venda = $repoVendas->findOneBy([
                'id' => $id,
                'estabelecimentoId' => $baseId,
                'status' => 'Carrinho'
            ]);

            if (!$venda) {
                return new JsonResponse([
                    'status' => 'error',
                    'mensagem' => 'Venda não encontrada ou já finalizada.'
                ], 404);
            }

            // Pega dados do formulário
            $metodoPagamento = $request->request->get('metodo_pagamento');
            $bandeiraCartao = $request->request->get('bandeira_cartao');
            $parcelas = $request->request->get('parcelas');

            // Finaliza a venda
            $repoVendas->finalizarVenda($baseId, $id, $metodoPagamento, $bandeiraCartao, $parcelas);

            // Se for pendente, cria no financeiro_pendente
            if ($metodoPagamento === 'pendente') {
                $financeiroPendente = new FinanceiroPendente();
                $financeiroPendente->setDescricao('Venda ' . ucfirst($venda->getOrigem()) . ' - Pet ID: ' . $venda->getPetId());
                $financeiroPendente->setValor($venda->getTotal());
                $financeiroPendente->setData(new \DateTime());
                $financeiroPendente->setPetId($venda->getPetId());
                $financeiroPendente->setEstabelecimentoId($baseId);
                $financeiroPendente->setMetodoPagamento('pendente');
                $financeiroPendente->setStatus('Pendente');
                $financeiroPendente->setOrigem($venda->getOrigem());
                
                $em->persist($financeiroPendente);
                $em->flush();
            }

            return new JsonResponse([
                'status' => 'success',
                'mensagem' => 'Venda finalizada com sucesso!'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'mensagem' => 'Erro ao finalizar venda: ' . $e->getMessage()
            ], 500);
        }
    }
}
