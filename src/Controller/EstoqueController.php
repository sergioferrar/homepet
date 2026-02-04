<?php

namespace App\Controller;

use App\Entity\Produto;
use App\Entity\Estoque;
use App\Entity\EstoqueMovimento;
use App\Service\EstoqueService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica/estoque")
 */
class EstoqueController extends DefaultController
{
   

    /**
     * @Route("", name="clinica_estoque_index", methods={"GET"})
     */
    public function index(): Response
    {
        try {
            $this->switchDB();
            $estabelecimentoId = $this->getIdBase();

            $produtos = $this->findProdutosByEstabelecimento($estabelecimentoId);
            $estatisticas = $this->calcularEstatisticas($produtos);

            return $this->render('clinica/estoque.html.twig', [
                'produtos' => $produtos,
                'estatisticas' => $estatisticas,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao carregar dados do estoque.');
            dd($e);
            
            return $this->redirectToRoute('home');
        }
    }

    /**
     * Cadastrar novo produto
     * 
     * @Route("/cadastrar", name="clinica_estoque_cadastrar", methods={"POST"})
     */
    public function cadastrar(Request $request, ?EstoqueService $estoqueService = null): Response
    {
        try {
            $this->switchDB();
            $estabelecimentoId = $this->getIdBase();

            $dadosProduto = $this->extrairDadosProduto($request);
            
            if (!$this->validarDadosProduto($dadosProduto)) {
                $this->addFlash('danger', 'Dados do produto inválidos.');
                return $this->redirectToRoute('clinica_estoque_index');
            }

            $produto = $this->criarProduto($dadosProduto, $estabelecimentoId);
            
            $this->entityManager->persist($produto);
            $this->entityManager->flush();

            // Criar registro de estoque inicial
            if ($this->estoqueService) {
                $this->estoqueService->inicializarEstoque($produto);
            }

            // Registrar movimento inicial se houver quantidade
            if ($dadosProduto['estoque_atual'] > 0) {
                $this->registrarMovimentoInicial($produto, $dadosProduto['estoque_atual']);
            }


            $this->addFlash('success', 'Produto cadastrado com sucesso!');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao cadastrar produto. Tente novamente.');
        }

        return $this->redirectToRoute('clinica_estoque_index');
    }

    /**
     * Registrar entrada de estoque
     * 
     * @Route("/entrada/{id}", name="clinica_estoque_entrada", methods={"POST"})
     */
    public function entrada(int $id, Request $request): Response
    {
        try {
            $this->switchDB();
            $estabelecimentoId = $this->getIdBase();

            $produto = $this->findProdutoOr404($id);
            
            $quantidade = $this->validarQuantidade($request->request->get('quantidade'));
            
            if ($quantidade <= 0) {
                throw new \InvalidArgumentException('Quantidade deve ser maior que zero.');
            }

            $this->processarEntrada($produto, $quantidade, $estabelecimentoId);


            $this->addFlash('success', "Entrada de {$quantidade} unidade(s) registrada com sucesso!");
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('warning', $e->getMessage());
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao registrar entrada de estoque.');
        }

        return $this->redirectToRoute('clinica_estoque_index');
    }

    /**
     * Visualizar movimentos de um produto
     * 
     * @Route("/movimentos/{id}", name="clinica_estoque_movimentos", methods={"GET"})
     */
    public function movimentos(int $id): Response
    {
        try {
            $this->switchDB();

            $produto = $this->findProdutoOr404($id);
            $movimentos = $this->findMovimentosByProduto($produto);
            $estatisticasMovimento = $this->calcularEstatisticasMovimento($movimentos);

            return $this->render('clinica/estoque_movimentos.html.twig', [
                'produto' => $produto,
                'movimentos' => $movimentos,
                'estatisticas' => $estatisticasMovimento,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao carregar histórico de movimentos.');
            
            return $this->redirectToRoute('clinica_estoque_index');
        }
    }

    /**
     * Obter detalhes do estoque (AJAX)
     * 
     * @Route("/detalhes", name="clinica_estoque_detalhes", methods={"GET"})
     */
    public function detalhes(Request $request): JsonResponse
    {
        try {
            $this->switchDB();
            $produtoId = $request->query->get('produtoId');

            if (!$produtoId) {
                return $this->jsonError('ID do produto não informado', 400);
            }

            $produto = $this->findProdutoOr404($produtoId);
            $estoque = $this->findOrCreateEstoque($produto);

            return $this->json([
                'success' => true,
                'data' => $this->formatarDadosEstoque($estoque)
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Erro ao carregar dados do estoque');
        }
    }

    /**
     * Atualizar configurações do estoque (AJAX)
     * 
     * @Route("/atualizar", name="clinica_estoque_atualizar", methods={"POST"})
     */
    public function atualizar(Request $request): JsonResponse
    {
        try {
            $this->switchDB();
            
            $estoqueId = $request->request->get('id');
            $estoque = $this->entityManager->getRepository(Estoque::class)->find($estoqueId);

            if (!$estoque) {
                return $this->jsonError('Estoque não encontrado', 404);
            }

            $this->atualizarCamposEstoque($estoque, $request->request->all());
            
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Estoque atualizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Erro ao atualizar estoque');
        }
    }

    /**
     * Lançar movimento de estoque (AJAX)
     * 
     * @Route("/movimento", name="clinica_estoque_movimento", methods={"POST"})
     */
    public function movimento(Request $request): JsonResponse
    {
        try {
            $this->switchDB();
            $estabelecimentoId = $this->getIdBase();

            $dadosMovimento = [
                'produtoId' => $request->request->get('produtoId'),
                'tipo' => $request->request->get('tipo'),
                'quantidade' => $this->validarQuantidade($request->request->get('quantidade')),
                'custo' => $request->request->get('custo'),
                'data' => new \DateTime($request->request->get('data_movimento')),
                'observacao' => $request->request->get('observacao'),
            ];

            if (!$this->validarDadosMovimento($dadosMovimento)) {
                return $this->jsonError('Dados do movimento inválidos', 400);
            }

            $produto = $this->findProdutoOr404($dadosMovimento['produtoId']);
            
            $movimento = $this->criarMovimento($produto, $dadosMovimento, $estabelecimentoId);
            
            $this->aplicarMovimentoNoEstoque($produto, $dadosMovimento);
            
            $this->entityManager->persist($movimento);
            $this->entityManager->flush();

            

            return $this->json([
                'success' => true,
                'message' => 'Movimento lançado com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage());
        }
    }

    /**
     * Obter histórico de movimentos (AJAX)
     * 
     * @Route("/historico", name="clinica_estoque_historico", methods={"GET"})
     */
    public function historico(Request $request): JsonResponse
    {
        try {
            $this->switchDB();
            
            $produtoId = $request->query->get('produtoId');
            $produto = $this->findProdutoOr404($produtoId);
            
            $movimentos = $this->findMovimentosByProduto($produto, 50);
            
            $dados = array_map(function($movimento) {
                return $this->formatarDadosMovimento($movimento);
            }, $movimentos);

            return $this->json([
                'success' => true,
                'data' => $dados
            ]);
        } catch (\Exception $e) {
            return $this->jsonError('Erro ao carregar histórico');
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Buscar produtos por estabelecimento
     */
    private function findProdutosByEstabelecimento(int $estabelecimentoId): array
    {
        return $this->entityManager
            ->getRepository(Produto::class)
            ->findBy(
                ['estabelecimentoId' => $estabelecimentoId],
                ['nome' => 'ASC']
            );
    }

    /**
     * Buscar produto ou retornar 404
     */
    private function findProdutoOr404(int $id): Produto
    {
        $produto = $this->entityManager
            ->getRepository(Produto::class)
            ->find($id);

        if (!$produto) {
            throw $this->createNotFoundException('Produto não encontrado.');
        }

        return $produto;
    }

    /**
     * Buscar movimentos de um produto
     */
    private function findMovimentosByProduto(Produto $produto, ?int $limit = null): array
    {
        $qb = $this->entityManager
            ->getRepository(EstoqueMovimento::class)
            ->createQueryBuilder('m')
            ->where('m.produto = :produto')
            ->setParameter('produto', $produto)
            ->orderBy('m.data', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Buscar ou criar registro de estoque
     */
    private function findOrCreateEstoque(Produto $produto): Estoque
    {
        $estoque = $this->entityManager
            ->getRepository(Estoque::class)
            ->findOneBy(['produtoId' => $produto->getId()]);

        if (!$estoque) {
            $estoque = new Estoque();
            $estoque->setProdutoId($produto->getId());
            $estoque->setEstabelecimentoId($this->getIdBase());
            $estoque->setQuantidadeAtual($produto->getEstoqueAtual() ?? 0);
            $estoque->setQuantidadeDisponivel($produto->getEstoqueAtual() ?? 0);
            $estoque->setQuantidadeReserva(0);
            $estoque->setEstoqueMinimo(10);
            $estoque->setEtoqueMaximo(1000);
            $estoque->setStatus('ativo');
            $estoque->setCreatedAt(new \DateTime());
            
            $this->entityManager->persist($estoque);
            $this->entityManager->flush();
        }

        return $estoque;
    }

    /**
     * Extrair dados do produto do request
     */
    private function extrairDadosProduto(Request $request): array
    {
        return [
            'nome' => trim($request->request->get('nome', '')),
            'preco_custo' => (float) $request->request->get('preco_custo', 0),
            'preco_venda' => (float) $request->request->get('preco_venda', 0),
            'estoque_atual' => (int) $request->request->get('estoque_atual', 0),
            'unidade' => trim($request->request->get('unidade', 'un')),
        ];
    }

    /**
     * Validar dados do produto
     */
    private function validarDadosProduto(array $dados): bool
    {
        return !empty($dados['nome']) && 
               strlen($dados['nome']) >= 3 &&
               $dados['preco_venda'] >= 0 &&
               $dados['estoque_atual'] >= 0;
    }

    /**
     * Validar dados do movimento
     */
    private function validarDadosMovimento(array $dados): bool
    {
        return !empty($dados['produtoId']) &&
               !empty($dados['tipo']) &&
               $dados['quantidade'] > 0 &&
               in_array($dados['tipo'], ['entrada', 'saida', 'ajuste', 'devolucao']);
    }

    /**
     * Validar e converter quantidade
     */
    private function validarQuantidade($quantidade): int
    {
        $qtd = (int) $quantidade;
        
        if ($qtd < 0) {
            throw new \InvalidArgumentException('Quantidade não pode ser negativa.');
        }

        return $qtd;
    }

    /**
     * Criar instância de produto
     */
    private function criarProduto(array $dados, int $estabelecimentoId): Produto
    {
        $produto = new Produto();
        $produto->setEstabelecimentoId($estabelecimentoId);
        $produto->setNome($dados['nome']);
        $produto->setPrecoCusto($dados['preco_custo']);
        $produto->setPrecoVenda($dados['preco_venda']);
        $produto->setEstoqueAtual($dados['estoque_atual']);
        $produto->setUnidade($dados['unidade']);
        $produto->setDataCadastro(new \DateTime());
        $produto->setAtivo(true);

        return $produto;
    }

    /**
     * Criar movimento de estoque
     */
    private function criarMovimento(Produto $produto, array $dados, int $estabelecimentoId): EstoqueMovimento
    {
        $movimento = new EstoqueMovimento();
        $movimento->setProduto($produto);
        $movimento->setEstabelecimentoId($estabelecimentoId);
        $movimento->setQuantidade($dados['quantidade']);
        $movimento->setTipo(strtoupper($dados['tipo']));
        $movimento->setCustoUnitario($dados['custo'] ?? null);
        $movimento->setData($dados['data']);
        $movimento->setObservacao($dados['observacao'] ?? null);
        $movimento->setOrigem('MANUAL');
        $movimento->setUsuario($this->security->getUser()->getEmail() ?? 'Sistema');

        return $movimento;
    }

    /**
     * Registrar movimento inicial de estoque
     */
    private function registrarMovimentoInicial(Produto $produto, int $quantidade): void
    {
        $movimento = new EstoqueMovimento();
        $movimento->setProduto($produto);
        $movimento->setEstabelecimentoId($this->getIdBase());
        $movimento->setQuantidade($quantidade);
        $movimento->setTipo('ENTRADA');
        $movimento->setOrigem('ESTOQUE_INICIAL');
        $movimento->setData(new \DateTime());
        $movimento->setObservacao('Estoque inicial do produto');
        
        $this->entityManager->persist($movimento);
    }

    /**
     * Processar entrada de estoque
     */
    private function processarEntrada(Produto $produto, int $quantidade, int $estabelecimentoId): void
    {
        $estoqueAnterior = $produto->getEstoqueAtual();
        $produto->setEstoqueAtual($estoqueAnterior + $quantidade);

        $movimento = new EstoqueMovimento();
        $movimento->setProduto($produto);
        $movimento->setEstabelecimentoId($estabelecimentoId);
        $movimento->setQuantidade($quantidade);
        $movimento->setTipo('ENTRADA');
        $movimento->setOrigem('CADASTRO_MANUAL');
        $movimento->setData(new \DateTime());
        $movimento->setObservacao("Entrada manual - Estoque anterior: {$estoqueAnterior}");

        $this->entityManager->persist($produto);
        $this->entityManager->persist($movimento);
        $this->entityManager->flush();

        // Atualizar registro de estoque se existir
        $estoque = $this->entityManager
            ->getRepository(Estoque::class)
            ->findOneBy(['produtoId' => $produto->getId()]);

        if ($estoque) {
            $estoque->setQuantidadeAtual($produto->getEstoqueAtual());
            $estoque->setQuantidadeDisponivel($estoque->getQuantidadeAtual() - $estoque->getQuantidadeReserva());
            $estoque->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
        }
    }

    /**
     * Aplicar movimento no estoque do produto
     */
    private function aplicarMovimentoNoEstoque(Produto $produto, array $dados): void
    {
        $estoqueAtual = $produto->getEstoqueAtual();
        $quantidade = $dados['quantidade'];

        switch ($dados['tipo']) {
            case 'entrada':
            case 'devolucao':
                $novoEstoque = $estoqueAtual + $quantidade;
                break;
            
            case 'saida':
                if ($estoqueAtual < $quantidade) {
                    throw new \InvalidArgumentException('Estoque insuficiente para realizar a saída.');
                }
                $novoEstoque = $estoqueAtual - $quantidade;
                break;
            
            case 'ajuste':
                $novoEstoque = $quantidade; // Ajuste define valor absoluto
                break;
            
            default:
                throw new \InvalidArgumentException('Tipo de movimento inválido.');
        }

        $produto->setEstoqueAtual($novoEstoque);
        $this->entityManager->persist($produto);

        // Atualizar tabela de estoque
        $estoque = $this->findOrCreateEstoque($produto);
        $estoque->setQuantidadeAtual($novoEstoque);
        $estoque->setQuantidadeDisponivel($novoEstoque - $estoque->getQuantidadeReserva());
        $estoque->setUpdatedAt(new \DateTime());
        
        // Atualizar custo médio se informado
        if (!empty($dados['custo']) && in_array($dados['tipo'], ['entrada', 'devolucao'])) {
            $this->atualizarCustoMedio($estoque, $quantidade, (float) $dados['custo']);
        }
    }

    /**
     * Atualizar custo médio ponderado
     */
    private function atualizarCustoMedio(Estoque $estoque, int $quantidade, float $custoUnitario): void
    {
        $quantidadeAnterior = $estoque->getQuantidadeAtual() - $quantidade;
        $custoAnterior = $estoque->getCustoMedio() ?? 0;

        if ($quantidadeAnterior > 0) {
            $custoTotal = ($quantidadeAnterior * $custoAnterior) + ($quantidade * $custoUnitario);
            $novoCustoMedio = $custoTotal / $estoque->getQuantidadeAtual();
        } else {
            $novoCustoMedio = $custoUnitario;
        }

        $estoque->setCustoMedio($novoCustoMedio);
        $estoque->setCustoUltimaCompra($custoUnitario);
    }

    /**
     * Atualizar campos do estoque
     */
    private function atualizarCamposEstoque(Estoque $estoque, array $dados): void
    {
        if (isset($dados['estoque_minimo'])) {
            $estoque->setEstoqueMinimo((int) $dados['estoque_minimo']);
        }

        if (isset($dados['etoque_maximo'])) {
            $estoque->setEtoqueMaximo((int) $dados['etoque_maximo']);
        }

        if (isset($dados['custo_ultima_compra'])) {
            $estoque->setCustoUltimaCompra((float) $dados['custo_ultima_compra']);
        }

        if (isset($dados['status'])) {
            $estoque->setStatus($dados['status']);
        }

        $estoque->setRefrigerado(!empty($dados['refrigerado']) ? 1 : 0);
        $estoque->setControlaLote(!empty($dados['controla_lote']) ? 1 : 0);
        $estoque->setControlaValidade(!empty($dados['controla_validade']) ? 1 : 0);
        $estoque->setUpdatedAt(new \DateTime());
        $estoque->setUpdatedBy($this->security->getUser()->getId());
    }

    /**
     * Formatar dados do estoque para JSON
     */
    private function formatarDadosEstoque(Estoque $estoque): array
    {
        return [
            'id' => $estoque->getId(),
            'produtoId' => $estoque->getProdutoId(),
            'quantidade_atual' => $estoque->getQuantidadeAtual(),
            'quantidade_reserva' => $estoque->getQuantidadeReserva(),
            'quantidade_disponivel' => $estoque->getQuantidadeDisponivel(),
            'estoque_minimo' => $estoque->getEstoqueMinimo(),
            'etoque_maximo' => $estoque->getEtoqueMaximo(),
            'custo_medio' => $estoque->getCustoMedio(),
            'custo_ultima_compra' => $estoque->getCustoUltimaCompra(),
            'refrigerado' => $estoque->getRefrigerado(),
            'controla_lote' => $estoque->getControlaLote(),
            'controla_validade' => $estoque->getControlaValidade(),
            'status' => $estoque->getStatus(),
        ];
    }

    /**
     * Formatar dados do movimento para JSON
     */
    private function formatarDadosMovimento(EstoqueMovimento $movimento): array
    {
        return [
            'id' => $movimento->getId(),
            'data' => $movimento->getData()->format('d/m/Y H:i'),
            'tipo' => strtolower($movimento->getTipo()),
            'quantidade' => $movimento->getQuantidade(),
            'custo' => $movimento->getCustoUnitario(),
            'saldo' => $this->calcularSaldoAposMovimento($movimento),
            'observacao' => $movimento->getObservacao(),
            'origem' => $movimento->getOrigem(),
            'usuario' => $movimento->getUsuario(),
        ];
    }

    /**
     * Calcular saldo após movimento (simplificado)
     */
    private function calcularSaldoAposMovimento(EstoqueMovimento $movimento): int
    {
        // Este método pode ser melhorado para calcular o saldo real
        // baseado no histórico de movimentos
        return $movimento->getProduto()->getEstoqueAtual();
    }

    /**
     * Calcular estatísticas dos produtos
     */
    private function calcularEstatisticas(array $produtos): array
    {
        $totalProdutos = count($produtos);
        $estoqueBaixo = 0;
        $valorTotal = 0;

        foreach ($produtos as $produto) {
            $valorTotal += ($produto->getPrecoVenda() ?? 0) * ($produto->getEstoqueAtual() ?? 0);
            
            // Verificar estoque baixo (menos de 10 unidades como exemplo)
            if (($produto->getEstoqueAtual() ?? 0) < 10) {
                $estoqueBaixo++;
            }
        }

        return [
            'total_produtos' => $totalProdutos,
            'estoque_baixo' => $estoqueBaixo,
            'valor_total' => $valorTotal,
            'movimentos_hoje' => 0, // Implementar se necessário
        ];
    }

    /**
     * Calcular estatísticas dos movimentos
     */
    private function calcularEstatisticasMovimento(array $movimentos): array
    {
        $totalEntradas = 0;
        $totalSaidas = 0;
        $valorEntradas = 0;
        $valorSaidas = 0;

        foreach ($movimentos as $movimento) {
            $quantidade = $movimento->getQuantidade();
            $custo = $movimento->getCustoUnitario() ?? 0;

            if (in_array(strtoupper($movimento->getTipo()), ['ENTRADA', 'DEVOLUCAO'])) {
                $totalEntradas += $quantidade;
                $valorEntradas += $quantidade * $custo;
            } else {
                $totalSaidas += $quantidade;
                $valorSaidas += $quantidade * $custo;
            }
        }

        return [
            'total_entradas' => $totalEntradas,
            'total_saidas' => $totalSaidas,
            'valor_entradas' => $valorEntradas,
            'valor_saidas' => $valorSaidas,
            'total_movimentos' => count($movimentos),
        ];
    }

    /**
     * Retornar resposta JSON de erro
     */
    private function jsonError(string $message, int $status = 500): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message
        ], $status);
    }
}