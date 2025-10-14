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

        // 🔹 Busca cliente
        $clienteRepo = $this->getRepositorio(Cliente::class);
        $cliente = !empty($dados['cliente_id'])
            ? $clienteRepo->findOneBy(['id' => (int)$dados['cliente_id'], 'estabelecimentoId' => $baseId])
            : null;

        // 🔹 Valida estoque
        foreach ($dados['itens'] as $item) {
            $produto = $em->getRepository(Produto::class)
                          ->findOneBy(['id' => $item['id'], 'estabelecimentoId' => $baseId]);
            if ($produto && ($produto->getEstoqueAtual() ?? 0) < $item['quantidade']) {
                return new JsonResponse([
                    'ok' => false,
                    'msg' => "❌ Estoque insuficiente para '{$produto->getNome()}'."
                ]);
            }
        }

        // 🔹 Cria venda
        $venda = new Venda();
        $venda->setEstabelecimentoId($baseId);
        $venda->setCliente($cliente ? $cliente->getNome() : 'Consumidor Final');
        $venda->setTotal($dados['total']);
        $venda->setMetodoPagamento($dados['metodo']);
        $venda->setData(new \DateTime());
        $em->persist($venda);

        // 🔹 Itens + baixa estoque
        foreach ($dados['itens'] as $item) {
            $itemVenda = new VendaItem();
            $itemVenda->setVenda($venda);
            $itemVenda->setProduto($item['nome']);
            $itemVenda->setQuantidade($item['quantidade']);
            $itemVenda->setValorUnitario($item['valor']);
            $itemVenda->setSubtotal($item['quantidade'] * $item['valor']);
            $em->persist($itemVenda);

            // baixa estoque se for produto
            $produto = $em->getRepository(Produto::class)
                          ->findOneBy(['id' => $item['id'], 'estabelecimentoId' => $baseId]);
            if ($produto) {
                $produto->setEstoqueAtual(max(0, ($produto->getEstoqueAtual() ?? 0) - $item['quantidade']));
                $mov = new EstoqueMovimento();
                $mov->setProduto($produto);
                $mov->setEstabelecimentoId($baseId);
                $mov->setTipo('SAIDA');
                $mov->setOrigem('Venda PDV');
                $mov->setQuantidade($item['quantidade']);
                $mov->setData(new \DateTime());
                $em->persist($produto);
                $em->persist($mov);
            }
        }

        // 🔹 Lançamento financeiro
        $descricaoCliente = $cliente ? $cliente->getNome() : 'Consumidor Final';

        if ($dados['metodo'] === 'pendente') {
            $finPend = new FinanceiroPendente();
            $finPend->setDescricao('Venda PDV - ' . $descricaoCliente);
            $finPend->setValor($dados['total']);
            $finPend->setData(new \DateTime());
            $finPend->setMetodoPagamento($dados['metodo']);
            $finPend->setEstabelecimentoId($baseId);
            $finPend->setOrigem('PDV');
            $finPend->setStatus('Pendente');
            $em->persist($finPend);
        } else {
            $fin = new Financeiro();
            $fin->setDescricao('Venda PDV - ' . $descricaoCliente);
            $fin->setValor($dados['total']);
            $fin->setData(new \DateTime());
            $fin->setMetodoPagamento($dados['metodo']);
            $fin->setOrigem('PDV');
            $fin->setStatus('Pago');
            $fin->setTipo('ENTRADA');
            $fin->setEstabelecimentoId($baseId);
            $em->persist($fin);
        }

        $em->flush();

        return new JsonResponse(['ok' => true, 'msg' => '✅ Venda registrada e lançada no financeiro!']);
    }

    /**
     * @Route("/saida", name="clinica_pdv_saida", methods={"POST"})
     */
    public function registrarSaida(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $dados = json_decode($request->getContent(), true);

        if (empty($dados['descricao']) || empty($dados['valor'])) {
            return new JsonResponse(['ok' => false, 'msg' => 'Informe a descrição e o valor.']);
        }

        $mov = new CaixaMovimento();
        $mov->setDescricao($dados['descricao']);
        $mov->setValor($dados['valor']);
        $mov->setTipo('SAIDA');
        $mov->setData(new \DateTime());
        $mov->setEstabelecimentoId($baseId);

        $em->persist($mov);
        $em->flush();

        return new JsonResponse(['ok' => true, 'msg' => '💸 Saída registrada no caixa (sem afetar o financeiro).']);
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
}
