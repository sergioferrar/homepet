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
            // Busca produtos com seus estoques
            $estoques = $this->em->getRepository(Estoque::class)
                ->createQueryBuilder('e')
                ->leftJoin('App\Entity\Produto', 'p', 'WITH', 'p.id = e.produtoId')
                ->where('e.estabelecimentoId = :estab')
                ->setParameter('estab', $estabelecimentoId)
                ->orderBy('p.nome', 'ASC')
                ->getQuery()
                ->getResult();

            $produtos = array_map(function($estoque) {
                $produto = $this->em->getRepository(Produto::class)->find($estoque->getProdutoId());
                return [
                    'id' => $produto->getId(),
                    'nome' => $produto->getNome(),
                    'codigo' => $produto->getCodigo(),
                    'precoVenda' => $produto->getPrecoVenda(),
                    'estoqueAtual' => $estoque->getQuantidadeAtual(),
                    'estoque_disponivel' => $estoque->getQuantidadeDisponivel(),
                    'estoque_minimo' => $estoque->getEstoqueMinimo(),
                    'estoque_id' => $estoque->getId(),
                ];
            }, $estoques);

            $estatisticas = $this->calcularEstatisticas($estoques);

            return $this->render('clinica/estoque.html.twig', [
                'produtos' => $produtos,
                'estatisticas' => $estatisticas,
            ]);
        } catch (\Exception $e) {
            dd($e);
            $this->addFlash('danger', 'Erro ao carregar estoque.');
            // return $this->redirectToRoute('home');
        }
    }

    /**
     * @Route("/entrada", name="clinica_estoque_entrada", methods={"POST"})
     */
    public function entrada(Request $request): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            $produtoId = (int)$request->request->get('produto_id');
            $quantidade = (int)$request->request->get('quantidade');
            $observacao = $request->request->get('observacao', '');

            if ($quantidade <= 0) {
                return $this->json(['success' => false, 'message' => 'Quantidade inválida'], 400);
            }

            // Busca estoque
            $estoque = $this->em->getRepository(Estoque::class)
                ->findOneBy(['produtoId' => $produtoId, 'estabelecimentoId' => $estabelecimentoId]);

            if (!$estoque) {
                return $this->json(['success' => false, 'message' => 'Estoque não encontrado'], 404);
            }

            // Atualiza quantidade
            $estoqueAnterior = $estoque->getQuantidadeAtual();
            $novoEstoque = $estoqueAnterior + $quantidade;
            $estoque->setQuantidadeAtual($novoEstoque);

            // Registra movimento
            $produto = $this->em->getRepository(Produto::class)->find($produtoId);
            $this->registrarMovimento($produto, 'ENTRADA', $quantidade, $observacao);

            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => "Entrada de {$quantidade} unidade(s) registrada!",
                'estoque_anterior' => $estoqueAnterior,
                'estoque_atual' => $novoEstoque,
                'estoque_disponivel' => $estoque->getQuantidadeDisponivel()
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/saida", name="clinica_estoque_saida", methods={"POST"})
     */
    public function saida(Request $request): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            $produtoId = (int)$request->request->get('produto_id');
            $quantidade = (int)$request->request->get('quantidade');
            $observacao = $request->request->get('observacao', '');

            if ($quantidade <= 0) {
                return $this->json(['success' => false, 'message' => 'Quantidade inválida'], 400);
            }

            $estoque = $this->em->getRepository(Estoque::class)
                ->findOneBy(['produtoId' => $produtoId, 'estabelecimentoId' => $estabelecimentoId]);

            if (!$estoque) {
                return $this->json(['success' => false, 'message' => 'Estoque não encontrado'], 404);
            }

            $estoqueAtual = $estoque->getQuantidadeDisponivel();

            if ($estoqueAtual < $quantidade) {
                return $this->json([
                    'success' => false,
                    'message' => "Estoque insuficiente! Disponível: {$estoqueAtual}"
                ], 400);
            }

            // Atualiza quantidade
            $novoEstoque = $estoque->getQuantidadeAtual() - $quantidade;
            $estoque->setQuantidadeAtual($novoEstoque);

            // Registra movimento
            $produto = $this->em->getRepository(Produto::class)->find($produtoId);
            $this->registrarMovimento($produto, 'SAIDA', $quantidade, $observacao);

            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => "Saída de {$quantidade} unidade(s) registrada!",
                'estoque_anterior' => $estoqueAtual,
                'estoque_atual' => $novoEstoque,
                'estoque_disponivel' => $estoque->getQuantidadeDisponivel()
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/ajustar", name="clinica_estoque_ajustar", methods={"POST"})
     */
    public function ajustar(Request $request): JsonResponse
    {
        $this->switchDB();
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        try {
            $produtoId = (int)$request->request->get('produto_id');
            $novoEstoque = (int)$request->request->get('estoque');
            $observacao = $request->request->get('observacao', 'Ajuste manual');

            if ($novoEstoque < 0) {
                return $this->json(['success' => false, 'message' => 'Estoque não pode ser negativo'], 400);
            }

            $estoque = $this->em->getRepository(Estoque::class)
                ->findOneBy(['produtoId' => $produtoId, 'estabelecimentoId' => $estabelecimentoId]);

            if (!$estoque) {
                return $this->json(['success' => false, 'message' => 'Estoque não encontrado'], 404);
            }

            $estoqueAnterior = $estoque->getQuantidadeAtual();
            $diferenca = abs($novoEstoque - $estoqueAnterior);

            $estoque->setQuantidadeAtual($novoEstoque);

            $produto = $this->em->getRepository(Produto::class)->find($produtoId);
            $this->registrarMovimento(
                $produto,
                'AJUSTE',
                $diferenca,
                $observacao . " (de {$estoqueAnterior} para {$novoEstoque})"
            );

            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Estoque ajustado!',
                'estoque_anterior' => $estoqueAnterior,
                'estoque_atual' => $novoEstoque
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
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

    private function calcularEstatisticas(array $estoques): array
    {
        $totalProdutos = count($estoques);
        $estoqueBaixo = 0;
        $estoqueZerado = 0;
        $valorTotal = 0;

        foreach ($estoques as $estoque) {
            $disponivel = $estoque->getQuantidadeDisponivel();
            $minimo = $estoque->getEstoqueMinimo();

            if ($disponivel == 0) {
                $estoqueZerado++;
            } elseif ($disponivel <= $minimo) {
                $estoqueBaixo++;
            }

            $valorTotal += $estoque->getValorTotalEstoque();
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