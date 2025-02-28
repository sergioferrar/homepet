<?php

namespace App\Controller;

use App\Entity\Cliente;
use App\Repository\ClienteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/cliente")
 */
class ClienteController extends DefaultController
{
    private $clienteRepository;

    public function __construct(ClienteRepository $clienteRepository)
    {
        $this->clienteRepository = $clienteRepository;
    }

    /**
     * @Route("/", name="cliente_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $clientes = $search ? $this->clienteRepository->search($search) : $this->clienteRepository->findAll();
        return $this->render('cliente/index.html.twig', ['clientes' => $clientes]);
    }

    /**
     * @Route("/novo", name="cliente_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $cliente = new Cliente();
            $cliente->setNome($request->request->get('nome'))
                ->setEmail($request->request->get('email'))
                ->setTelefone($request->request->get('telefone'))
                ->setRua($request->request->get('rua'))
                ->setNumero($request->request->get('numero'))
                ->setComplemento($request->request->get('complemento'))
                ->setBairro($request->request->get('bairro'))
                ->setCidade($request->request->get('cidade'))
            ;

            $this->clienteRepository->save([
                'nome' => $cliente->getNome(),
                'email' => $cliente->getEmail(),
                'telefone' => $cliente->getTelefone(),
                'rua' => $cliente->getRua(),
                'numero' => $cliente->getNumero(),
                'complemento' => $cliente->getComplemento(),
                'bairro' => $cliente->getBairro(),
                'cidade' => $cliente->getCidade(),
            ]);
            $clienteId = $this->clienteRepository->getLastInsertedId();
            return $this->redirectToRoute('pet_novo', ['cliente_id' => $clienteId]);
 
        }

        return $this->render('cliente/novo.html.twig');
    }

    /**
     * @Route("/editar/{id}", name="cliente_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $cliente = $this->clienteRepository->find($id);

        if (!$cliente) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $cliente->setNome($request->get('nome'))
                ->setEmail($request->get('email'))
                ->setTelefone($request->get('telefone'))
                ->setRua($request->get('rua'))
                ->setNumero($request->get('numero'))
                ->setComplemento($request->get('complemento'))
                ->setBairro($request->get('bairro'))
                ->setCidade($request->get('cidade'));

            $this->clienteRepository->update([
                'id' => $cliente->getId(), // Agora podemos pegar o ID corretamente
                'nome' => $cliente->getNome(),
                'email' => $cliente->getEmail(),
                'telefone' => $cliente->getTelefone(),
                'rua' => $cliente->getRua(),
                'numero' => $cliente->getNumero(),
                'complemento' => $cliente->getComplemento(),
                'bairro' => $cliente->getBairro(),
                'cidade' => $cliente->getCidade(),
            ]);

            $clienteId = $this->clienteRepository->getLastInsertedId();
            return $this->redirectToRoute('pet_novo', ['cliente_id' => $clienteId]);
 
        }
//        dd($cliente);
        return $this->render('cliente/editar.html.twig', [
            'cliente' => $cliente
        ]);
    }


    /**
     * @Route("/deletar/{id}", name="cliente_deletar", methods={"POST"})
     */
    public function deletar(int $id): Response
    {
        $cliente = $this->clienteRepository->find($id);

        if (!$cliente) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        // Verifica se o cliente tem pets antes de excluir
        if ($this->clienteRepository->hasPets($id)) {
            $this->addFlash('error', 'Não é possível excluir este cliente, pois ele possui pets cadastrados.');
            $clienteId = $this->clienteRepository->getLastInsertedId();
            return $this->redirectToRoute('pet_novo', ['cliente_id' => $clienteId]);
 
        }

        $this->clienteRepository->delete($id);
        $clienteId = $this->clienteRepository->getLastInsertedId();
        return $this->redirectToRoute('pet_novo', ['cliente_id' => $clienteId]);
 
    }



    /**
     * @Route("/{id}/agendamentos", name="cliente_agendamentos", methods={"GET"})
     */
    public function agendamentos(int $id): Response
    {
        $clienteData = $this->clienteRepository->find($id);

        if (!$clienteData) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        $agendamentos = $this->clienteRepository->findAgendamentosByCliente($id);
        return $this->render('cliente/agendamentos.html.twig', [
            'cliente' => $clienteData,
            'agendamentos' => $agendamentos
        ]);
    }

    /**
     * @Route("/cliente/cadastro", name="cadastro_cliente")
     */
    public function cadastro(): Response
    {
        return $this->render('cliente/cadastro_cliente.html.twig');
    }
}
