<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Orcamento;
use App\Entity\OrcamentoItem;
use App\Entity\Cliente;
use App\Entity\Produto;
use App\Entity\Servico;
use App\Entity\Pet;

/**
 * @Route("dashboard/")
 */
class OrcamentoController extends DefaultController
{
    /**
     * @Route("/orcamento", name="orcamento_index")
     */
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $status = $request->query->get('status', 'todos');
        $busca = $request->query->get('busca', '');

        $qb = $em->createQueryBuilder();
        $qb->select('o')
            ->from(Orcamento::class, 'o')
            ->where('o.estabelecimentoId = :estab')
            ->setParameter('estab', $baseId)
            ->orderBy('o.dataCriacao', 'DESC');

        if ($status !== 'todos') {
            $qb->andWhere('o.status = :status')
                ->setParameter('status', $status);
        }

        if ($busca) {
            $qb->andWhere('o.clienteNome LIKE :busca OR o.petNome LIKE :busca')
                ->setParameter('busca', '%' . $busca . '%');
        }

        $orcamentos = $qb->getQuery()->getResult();

        return $this->render('orcamento/index.html.twig', [
            'orcamentos' => $orcamentos,
            'status' => $status,
            'busca' => $busca
        ]);
    }

    /**
     * @Route("/orcamento/novo", name="orcamento_novo")
     */
    public function novo(Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Buscar clientes
        $clientesObj = $em->createQuery(
            'SELECT c FROM App\Entity\Cliente c WHERE c.estabelecimentoId = :estab ORDER BY c.nome'
        )->setParameter('estab', $baseId)->getResult();

        // Converter clientes para array
        $clientes = [];
        foreach ($clientesObj as $c) {
            $clientes[] = [
                'id' => $c->getId(),
                'nome' => $c->getNome(),
                'telefone' => $c->getTelefone(),
                'email' => $c->getEmail()
            ];
        }

        // Buscar produtos
        $produtosObj = $em->createQuery(
            'SELECT p FROM App\Entity\Produto p WHERE p.estabelecimentoId = :estab ORDER BY p.nome'
        )->setParameter('estab', $baseId)->getResult();

        // Converter produtos para array
        $produtos = [];
        foreach ($produtosObj as $p) {
            $produtos[] = [
                'id' => $p->getId(),
                'nome' => $p->getNome(),
                'preco' => $p->getPrecoVenda()
            ];
        }

        // Buscar serviços
        $servicosObj = $em->createQuery(
            'SELECT s FROM App\Entity\Servico s WHERE s.estabelecimentoId = :estab ORDER BY s.nome'
        )->setParameter('estab', $baseId)->getResult();

        // Converter serviços para array
        $servicos = [];
        foreach ($servicosObj as $s) {
            $servicos[] = [
                'id' => $s->getId(),
                'nome' => $s->getNome(),
                'preco' => $s->getValor()
            ];
        }

        return $this->render('orcamento/novo.html.twig', [
            'clientes' => $clientes,
            'produtos' => $produtos,
            'servicos' => $servicos
        ]);
    }

    /**
     * @Route("/orcamento/salvar", name="orcamento_salvar", methods={"POST"})
     */
    public function salvar(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $this->switchDB();
            $baseId = $this->getIdBase();
            $data = json_decode($request->getContent(), true);

            $orcamento = new Orcamento();
            $orcamento->setEstabelecimentoId($baseId);
            $orcamento->setClienteId($data['clienteId'] ?? null);
            $orcamento->setClienteNome($data['clienteNome']);
            $orcamento->setPetId($data['petId'] ?? null);
            $orcamento->setPetNome($data['petNome'] ?? null);
            $orcamento->setValorTotal($data['valorTotal']);
            $orcamento->setDesconto($data['desconto'] ?? 0);
            $orcamento->setValorFinal($data['valorFinal']);
            $orcamento->setStatus('pendente');
            $orcamento->setDataCriacao(new \DateTime());

            if (!empty($data['dataValidade'])) {
                $orcamento->setDataValidade(new \DateTime($data['dataValidade']));
            }

            $orcamento->setObservacoes($data['observacoes'] ?? null);

            $em->persist($orcamento);
            $em->flush();

            // Salvar itens
            foreach ($data['itens'] as $item) {
                $orcamentoItem = new OrcamentoItem();
                $orcamentoItem->setOrcamento($orcamento);
                $orcamentoItem->setDescricao($item['descricao']);
                $orcamentoItem->setTipo($item['tipo']);
                $orcamentoItem->setQuantidade($item['quantidade']);
                $orcamentoItem->setValorUnitario($item['valorUnitario']);
                $orcamentoItem->setSubtotal($item['subtotal']);

                $em->persist($orcamentoItem);
            }

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Orçamento criado com sucesso!',
                'id' => $orcamento->getId()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao criar orçamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/orcamento/{id}", name="orcamento_visualizar")
     */
    public function visualizar(int $id, EntityManagerInterface $em): Response
    {
        $this->switchDB();

        $orcamento = $em->getRepository(Orcamento::class)->find($id);

        if (!$orcamento) {
            throw $this->createNotFoundException('Orçamento não encontrado');
        }

        $itens = $em->createQuery(
            'SELECT i FROM App\Entity\OrcamentoItem i WHERE i.orcamento = :orc'
        )->setParameter('orc', $orcamento)->getResult();

        return $this->render('orcamento/visualizar.html.twig', [
            'orcamento' => $orcamento,
            'itens' => $itens
        ]);
    }

    /**
     * @Route("/orcamento/{id}/status", name="orcamento_status", methods={"POST"})
     */
    public function alterarStatus(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $this->switchDB();

            $orcamento = $em->getRepository(Orcamento::class)->find($id);

            if (!$orcamento) {
                return new JsonResponse(['success' => false, 'message' => 'Orçamento não encontrado'], 404);
            }

            $novoStatus = $request->request->get('status');
            $orcamento->setStatus($novoStatus);

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Status atualizado com sucesso!'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/orcamento/{id}/converter", name="orcamento_converter", methods={"POST"})
     */
    public function converter(int $id, EntityManagerInterface $em): JsonResponse
    {
        try {
            $this->switchDB();

            $orcamento = $em->getRepository(Orcamento::class)->find($id);

            if (!$orcamento) {
                return new JsonResponse(['success' => false, 'message' => 'Orçamento não encontrado'], 404);
            }

            // Aqui você pode implementar a lógica para converter em venda
            // Por enquanto, apenas muda o status
            $orcamento->setStatus('convertido');
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Orçamento convertido em venda!'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao converter orçamento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @Route("/orcamento/api/cliente/{id}/pets", name="api_cliente_pets")
     */
    public function getPetsCliente(int $id, EntityManagerInterface $em): JsonResponse
    {
        try {
            $this->switchDB();
            $baseId = $this->getIdBase();

            // Usar repositório para buscar pets
            // Nota: dono_id é VARCHAR na tabela, então convertemos o ID para string
            $pets = $em->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('p.dono_id = :donoId')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('donoId', (string)$id)
                ->setParameter('estab', $baseId)
                ->orderBy('p.nome', 'ASC')
                ->getQuery()
                ->getResult();

            $result = [];
            foreach ($pets as $pet) {
                $result[] = [
                    'id' => $pet->getId(),
                    'nome' => $pet->getNome(),
                    'especie' => $pet->getEspecie(),
                    'raca' => $pet->getRaca()
                ];
            }

            return new JsonResponse(['success' => true, 'pets' => $result]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao buscar pets: ' . $e->getMessage()
            ], 500);
        }
    }
}
