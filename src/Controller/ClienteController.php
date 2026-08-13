<?php

namespace App\Controller;

use App\Entity\Cliente;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClienteController extends DefaultController
{
    #[Route('dashboard/cliente/', name: 'cliente_index', methods: "{GET}")]
    public function index(Request $request): Response
    {
        $this->switchDB();
        $search = $request->query->get('search');
        $clientes = $this->getRepositorio(Cliente::class)->search($this->getIdBase(), $search);

        return $this->render('cliente/index.html.twig', ['clientes' => $clientes]);
    }
    #[Route('dashboard/cliente/novo', name: 'cliente_novo')]
    public function novo(Request $request): Response
    {
        $this->switchDB();
        if ($request->isMethod('POST')) {
            $cliente = new Cliente();
            $cliente->setNome($request->request->get('nome'))
                ->setEmail($request->request->get('email'))
                ->setTelefone($request->request->get('telefone'))
                ->setRua($request->request->get('rua'))
                ->setNumero($request->request->get('numero'))
                ->setComplemento($request->request->get('complemento'))
                ->setBairro($request->request->get('bairro'))
                ->setCidade($request->request->get('cidade'));

            $this->getRepositorio(Cliente::class)->save($this->getIdBase(), [
                'nome' => $cliente->getNome(),
                'cpf' => $request->request->get('cpf'),
                'email' => $cliente->getEmail(),
                'telefone' => $cliente->getTelefone(),
                'rua' => $cliente->getRua(),
                'numero' => $cliente->getNumero(),
                'complemento' => $cliente->getComplemento(),
                'bairro' => $cliente->getBairro(),
                'cidade' => $cliente->getCidade(),
                'whatsapp' => $request->get('whatsapp'),
            ]);
            $clienteId = $this->getRepositorio(Cliente::class)->getLastInsertedId();
            return $this->redirectToRoute('pet_novo', ['cliente_id' => $clienteId]);
        }

        return $this->render('cliente/novo.html.twig');
    }
    #[Route('dashboard/cliente/editar/{id}', name: 'cliente_editar')]
    public function editar(
        Request $request,
        int $id
    ): Response {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $clienteRepository = $this->getRepositorio(Cliente::class);

        // Busca cliente pelo ID
        $cliente = $clienteRepository->localizaTodosClientePorID($baseId, $id);

        if (!$cliente) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $clienteAtualizado = [
                'id' => $cliente['id'],
                'nome' => $request->get('nome'),
                'cpf' => $request->get('cpf'),
                'email' => $request->get('email'),
                'telefone' => $request->get('telefone'),
                'rua' => $request->get('rua'),
                'numero' => $request->get('numero'),
                'complemento' => $request->get('complemento'),
                'bairro' => $request->get('bairro'),
                'cidade' => $request->get('cidade'),
                'whatsapp' => $request->get('whatsapp') ?? '',
                'cep' => $request->get('cep'),
            ];

            $clienteRepository->update($baseId, $clienteAtualizado);

            return $this->redirectToRoute('cliente_index');
        }

        return $this->render('cliente/editar.html.twig', [
            'cliente' => $cliente,
        ]);
    }
    #[Route('dashboard/cliente/deletar/{id}', name: 'cliente_deletar')]
    public function deletar(int $id): Response
    {
        $this->switchDB();
        $cliente = $this->getRepositorio(Cliente::class)->localizaTodosClientePorID($this->getIdBase(), $id);

        if (!$cliente) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        if ($this->getRepositorio(Cliente::class)->hasPets($id)) {
            $this->addFlash('error', 'Não é possível excluir este cliente, pois ele possui pets cadastrados.');
            return $this->redirectToRoute('cliente_index');
        }

        $this->getRepositorio(Cliente::class)->delete($this->getIdBase(), $id);
        return $this->redirectToRoute('cliente_index');
    }
    #[Route('dashboard/cliente/{id}/agendamentos', name: 'cliente_agendamentos', methods: "{GET}")]
    public function agendamentos(int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $clienteData = $this->getRepositorio(Cliente::class)->localizaTodosClientePorID($baseId, $id);

        if (!$clienteData) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        $agendamentos = $this->getRepositorio(Cliente::class)->findAgendamentosByCliente($baseId, $id);

        return $this->render('cliente/agendamentos.html.twig', [
            'cliente' => $clienteData,
            'agendamentos' => $agendamentos,
        ]);
    }
    #[Route('dashboard/cliente/cadastro', name: 'cadastro_cliente')]
    public function cadastro(): Response
    {
        $this->switchDB();
        return $this->render('cliente/cadastro_cliente.html.twig');
    }
    #[Route('dashboard/cliente/{id}/detalhes', name: 'cliente_detalhes', methods: "{GET}")]
    public function detalhes(int $id): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repo = $this->getRepositorio(Cliente::class);
        $cliente = $repo->localizaTodosClientePorID($baseId, $id);

        if (!$cliente) {
            return $this->json(['status' => 'erro', 'mensagem' => 'Cliente não encontrado.']);
        }

        $pets = $repo->findPetsByCliente($baseId, $id);
        $agendamentos = $repo->findAgendamentosByCliente($baseId, $id);

        $financeiroPendenteRepo = $this->getRepositorio(\App\Entity\FinanceiroPendente::class);
        $pendencias = $financeiroPendenteRepo->findByClienteId($baseId, $id);

        $temPendencia = !empty($pendencias);

        return $this->json([
            'status' => 'ok',
            'cliente' => array_merge($cliente, [
                'temFinanceiroPendente' => $temPendencia,
                'pets' => $pets,
                'agendamentos' => array_map(fn($ag) => [
                    'data' => (new \DateTime($ag['data']))->format('d/m/Y'),
                    'pet' => $ag['pet_nome'],
                    'servico' => $ag['servico_nome'],
                ], $agendamentos),
                'pendencias' => array_map(fn($p) => [
                    'descricao' => $p['descricao'],
                    'valor' => number_format($p['valor'], 2, ',', '.'),
                    'data' => (new \DateTime($p['data']))->format('d/m/Y'),
                ], $pendencias),
            ]),
        ]);
    }
}
