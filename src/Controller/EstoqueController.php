<?php

namespace App\Controller;

use App\Entity\Produto;
use App\Entity\EstoqueMovimento;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clinica/estoque")
 */
class EstoqueController extends DefaultController
{
    /**
     * @Route("", name="clinica_estoque_index", methods={"GET"})
     */
    public function index(EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $produtos = $em->getRepository(Produto::class)->findBy(['estabelecimentoId' => $baseId], ['nome' => 'ASC']);

        return $this->render('clinica/estoque.html.twig', [
            'produtos' => $produtos,
        ]);
    }

    /**
     * @Route("/cadastrar", name="clinica_estoque_cadastrar", methods={"POST"})
     */
    public function cadastrar(Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $dados = $request->request->all();

        $produto = new Produto();
        $produto->setEstabelecimentoId($baseId);
        $produto->setNome($dados['nome']);
        $produto->setPrecoCusto($dados['preco_custo'] ?: 0);
        $produto->setPrecoVenda($dados['preco_venda'] ?: 0);
        $produto->setEstoqueAtual($dados['estoque_atual'] ?: 0);
        $produto->setUnidade($dados['unidade'] ?: 'un');
        $produto->setDataCadastro(new \DateTime());

        $em->persist($produto);
        $em->flush();

        $this->addFlash('success', 'Produto cadastrado com sucesso!');
        return $this->redirectToRoute('clinica_estoque_index');
    }

    /**
     * @Route("/entrada/{id}", name="clinica_estoque_entrada", methods={"POST"})
     */
    public function entrada(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $produto = $em->getRepository(Produto::class)->find($id);
        if (!$produto) {
            $this->addFlash('danger', 'Produto não encontrado.');
            return $this->redirectToRoute('clinica_estoque_index');
        }

        $quantidade = (int)$request->request->get('quantidade', 0);
        if ($quantidade <= 0) {
            $this->addFlash('danger', 'Quantidade inválida.');
            return $this->redirectToRoute('clinica_estoque_index');
        }

        // Atualiza estoque
        $produto->setEstoqueAtual($produto->getEstoqueAtual() + $quantidade);

        // Registra movimento
        $mov = new EstoqueMovimento();
        $mov->setProduto($produto);
        $mov->setEstabelecimentoId($baseId);
        $mov->setQuantidade($quantidade);
        $mov->setTipo('ENTRADA');
        $mov->setOrigem('Cadastro Manual');
        $mov->setData(new \DateTime());

        $em->persist($produto);
        $em->persist($mov);
        $em->flush();

        $this->addFlash('success', 'Entrada registrada com sucesso!');
        return $this->redirectToRoute('clinica_estoque_index');
    }

    /**
     * @Route("/movimentos/{id}", name="clinica_estoque_movimentos", methods={"GET"})
     */
    public function movimentos(int $id, EntityManagerInterface $em): Response
    {
        $this->switchDB();

        $produto = $em->getRepository(Produto::class)->find($id);
        if (!$produto) {
            throw $this->createNotFoundException('Produto não encontrado');
        }

        $movimentos = $em->getRepository(EstoqueMovimento::class)->findBy(['produto' => $produto], ['data' => 'DESC']);

        return $this->render('clinica/estoque_movimentos.html.twig', [
            'produto' => $produto,
            'movimentos' => $movimentos,
        ]);
    }
}
