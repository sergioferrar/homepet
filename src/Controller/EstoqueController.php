<?php

namespace App\Controller;

use App\Entity\Produto;
use App\Entity\Estoque;
use App\Entity\EstoqueMovimento;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller de Estoque - Usa tabela estoque
 * 
 * @Route("dashboard/clinica/estoque")
 */
class EstoqueController extends DefaultController
{

    /**
     * @Route("", name="clinica_estoque_index", methods={"GET"})
     */
    public function index(): Response
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            // Parte da tabela produto para listar TODOS os produtos do estabelecimento,
            // mesmo os que ainda não possuem registro na tabela estoque.
            $produtosEntity = $this->em->getRepository(Produto::class)
                ->findBy(['estabelecimentoId' => $estabelecimentoId], ['nome' => 'ASC']);

            // Indexa os registros de estoque existentes por produtoId
            $estoquesPorProduto = [];
            $estoques = $this->em->getRepository(Estoque::class)
                ->findBy(['estabelecimentoId' => $estabelecimentoId]);
            foreach ($estoques as $estoque) {
                $estoquesPorProduto[$estoque->getProdutoId()] = $estoque;
            }

            // A quantidade real do produto é mantida em produto.estoqueAtual
            // (fonte de verdade usada pelo PDV/EstoqueService). O registro em
            // estoque, quando existir, fornece apenas metadados (mínimo, etc.).
            $produtos = array_map(function($produto) use ($estoquesPorProduto) {
                $estoque = $estoquesPorProduto[$produto->getId()] ?? null;
                $quantidade = $produto->getEstoqueAtual() ?? 0;

                return [
                    'id' => $produto->getId(),
                    'nome' => $produto->getNome(),
                    'codigo' => $produto->getCodigo(),
                    'precoVenda' => $produto->getPrecoVenda(),
                    'unidade' => $produto->getUnidade(),
                    'estoqueAtual' => $quantidade,
                    'estoque_disponivel' => $quantidade,
                    'estoque_minimo' => $estoque ? $estoque->getEstoqueMinimo() : 0,
                    'estoque_id' => $estoque ? $estoque->getId() : null,
                ];
            }, $produtosEntity);

            $estatisticas = $this->calcularEstatisticas($produtos);

            return $this->render('clinica/estoque.html.twig', [
                'produtos' => $produtos,
                'estatisticas' => $estatisticas,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao carregar estoque.');
            return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("/entrada", name="clinica_estoque_entrada", methods={"POST"})
     */
    public function entrada(Request $request): Response
    {
        $this->switchDB();

        try {
            $produto = $this->resolverProduto($request);
            $quantidade = (int)$request->request->get('quantidade');
            $observacao = $request->request->get('observacao', '');

            if (!$produto) {
                return $this->responder($request, false, 'Produto não encontrado');
            }
            if ($quantidade <= 0) {
                return $this->responder($request, false, 'Quantidade inválida');
            }

            $estoqueAnterior = $produto->getEstoqueAtual() ?? 0;
            $novoEstoque = $estoqueAnterior + $quantidade;
            $produto->setEstoqueAtual($novoEstoque);

            $this->registrarMovimento($produto, 'ENTRADA', $quantidade, $observacao);
            $this->em->flush();

            return $this->responder($request, true, "Entrada de {$quantidade} unidade(s) registrada!", [
                'estoque_anterior' => $estoqueAnterior,
                'estoque_atual' => $novoEstoque,
            ]);
        } catch (\Exception $e) {
            return $this->responder($request, false, $e->getMessage());
        }
    }

    /**
     * @Route("/saida", name="clinica_estoque_saida", methods={"POST"})
     */
    public function saida(Request $request): Response
    {
        $this->switchDB();

        try {
            $produto = $this->resolverProduto($request);
            $quantidade = (int)$request->request->get('quantidade');
            $observacao = $request->request->get('observacao', '');

            if (!$produto) {
                return $this->responder($request, false, 'Produto não encontrado');
            }
            if ($quantidade <= 0) {
                return $this->responder($request, false, 'Quantidade inválida');
            }

            $estoqueAnterior = $produto->getEstoqueAtual() ?? 0;
            if ($estoqueAnterior < $quantidade) {
                return $this->responder($request, false, "Estoque insuficiente! Disponível: {$estoqueAnterior}");
            }

            $novoEstoque = $estoqueAnterior - $quantidade;
            $produto->setEstoqueAtual($novoEstoque);

            $this->registrarMovimento($produto, 'SAIDA', $quantidade, $observacao);
            $this->em->flush();

            return $this->responder($request, true, "Saída de {$quantidade} unidade(s) registrada!", [
                'estoque_anterior' => $estoqueAnterior,
                'estoque_atual' => $novoEstoque,
            ]);
        } catch (\Exception $e) {
            return $this->responder($request, false, $e->getMessage());
        }
    }

    /**
     * @Route("/ajustar", name="clinica_estoque_ajustar", methods={"POST"})
     */
    public function ajustar(Request $request): Response
    {
        $this->switchDB();

        try {
            $produto = $this->resolverProduto($request);
            $novoEstoque = (int)$request->request->get('estoque');
            $observacao = $request->request->get('observacao', 'Ajuste manual');

            if (!$produto) {
                return $this->responder($request, false, 'Produto não encontrado');
            }
            if ($novoEstoque < 0) {
                return $this->responder($request, false, 'Estoque não pode ser negativo');
            }

            $estoqueAnterior = $produto->getEstoqueAtual() ?? 0;
            $diferenca = abs($novoEstoque - $estoqueAnterior);
            $produto->setEstoqueAtual($novoEstoque);

            $this->registrarMovimento(
                $produto,
                'AJUSTE',
                $diferenca,
                $observacao . " (de {$estoqueAnterior} para {$novoEstoque})"
            );
            $this->em->flush();

            return $this->responder($request, true, 'Estoque ajustado!', [
                'estoque_anterior' => $estoqueAnterior,
                'estoque_atual' => $novoEstoque,
            ]);
        } catch (\Exception $e) {
            return $this->responder($request, false, $e->getMessage());
        }
    }

    /**
     * @Route("/movimentos/{id}", name="clinica_estoque_movimentos", methods={"GET"})
     */
    public function movimentos(int $id): Response
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            $produto = $this->em->getRepository(Produto::class)
                ->findOneBy(['id' => $id, 'estabelecimentoId' => $estabelecimentoId]);

            if (!$produto) {
                $this->addFlash('danger', 'Produto não encontrado');
                return $this->redirectToRoute('clinica_estoque_index');
            }

            $movimentos = $this->em->getRepository(EstoqueMovimento::class)
                ->createQueryBuilder('m')
                ->where('m.produto = :produto')
                ->andWhere('m.estabelecimentoId = :estab')
                ->setParameter('produto', $produto)
                ->setParameter('estab', $estabelecimentoId)
                ->orderBy('m.data', 'DESC')
                ->getQuery()
                ->getResult();

            $estatisticas = $this->calcularEstatisticasMovimento($movimentos);

            return $this->render('clinica/estoque_movimentos.html.twig', [
                'produto' => $produto,
                'movimentos' => $movimentos,
                'estatisticas' => $estatisticas,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao carregar movimentações');
            return $this->redirectToRoute('clinica_estoque_index');
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Resolve o Produto a partir do request (aceita produto_id no corpo ou id na query),
     * garantindo o isolamento por estabelecimento.
     */
    private function resolverProduto(Request $request): ?Produto
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();
        $produtoId = (int)($request->request->get('produto_id') ?: $request->query->get('id'));

        if (!$produtoId) {
            return null;
        }

        return $this->em->getRepository(Produto::class)
            ->findOneBy(['id' => $produtoId, 'estabelecimentoId' => $estabelecimentoId]);
    }

    /**
     * Responde em JSON para requisições AJAX ou com redirect + flash para POST de formulário.
     */
    private function responder(Request $request, bool $sucesso, string $mensagem, array $extra = []): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->json(array_merge([
                'success' => $sucesso,
                'message' => $mensagem,
            ], $extra), $sucesso ? 200 : 400);
        }

        $this->addFlash($sucesso ? 'success' : 'danger', $mensagem);

        // Volta para a página de origem (ex.: histórico do produto) quando disponível
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('clinica_estoque_index');
    }

    private function registrarMovimento(
        Produto $produto,
        string $tipo,
        int $quantidade,
        string $observacao
    ): void {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        $movimento = new EstoqueMovimento();
        $movimento->setProduto($produto);
        $movimento->setEstabelecimentoId($estabelecimentoId);
        $movimento->setTipo(strtoupper($tipo));
        $movimento->setQuantidade($quantidade);
        $movimento->setData(new \DateTime());
        $movimento->setObservacao($observacao);
        $movimento->setOrigem('Sistema');

        $this->em->persist($movimento);
    }

    private function calcularEstatisticas(array $produtos): array
    {
        $totalProdutos = count($produtos);
        $estoqueBaixo = 0;
        $estoqueZerado = 0;
        $valorTotal = 0;

        foreach ($produtos as $produto) {
            $disponivel = (int) ($produto['estoque_disponivel'] ?? 0);
            $minimo = (int) ($produto['estoque_minimo'] ?? 0);

            if ($disponivel == 0) {
                $estoqueZerado++;
            } elseif ($disponivel <= $minimo) {
                $estoqueBaixo++;
            }

            $valorTotal += (float) ($produto['estoqueAtual'] ?? 0) * (float) ($produto['precoVenda'] ?? 0);
        }

        return [
            'total_produtos' => $totalProdutos,
            'estoque_baixo' => $estoqueBaixo,
            'estoque_zerado' => $estoqueZerado,
            'valor_total_estoque' => $valorTotal,
        ];
    }

    private function calcularEstatisticasMovimento(array $movimentos): array
    {
        $totalEntradas = 0;
        $totalSaidas = 0;
        $totalAjustes = 0;

        foreach ($movimentos as $movimento) {
            $tipo = strtoupper($movimento->getTipo());
            $quantidade = $movimento->getQuantidade();

            switch ($tipo) {
                case 'ENTRADA':
                    $totalEntradas += $quantidade;
                    break;
                case 'SAIDA':
                    $totalSaidas += $quantidade;
                    break;
                case 'AJUSTE':
                    $totalAjustes += $quantidade;
                    break;
            }
        }

        return [
            'total_movimentos' => count($movimentos),
            'total_entradas' => $totalEntradas,
            'total_saidas' => $totalSaidas,
            'total_ajustes' => $totalAjustes,
            'saldo' => $totalEntradas - $totalSaidas,
        ];
    }
}