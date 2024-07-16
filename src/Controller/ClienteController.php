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
class ClienteController extends AbstractController
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
                    ->setEndereco($request->request->get('Endereco'));

            $this->clienteRepository->save([
                'nome' => $cliente->getNome(),
                'email' => $cliente->getEmail(),
                'telefone' => $cliente->getTelefone(),
                'Endereco' => $cliente->getEndereco()
            ]);
            return $this->redirectToRoute('cliente_index');
        }

        return $this->render('cliente/novo.html.twig');
    }

    /**
     * @Route("/editar/{id}", name="cliente_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $clienteData = $this->clienteRepository->find($id);

        if (!$clienteData) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        $cliente = new Cliente();
        $cliente->setId($clienteData['id'])
                ->setNome($clienteData['nome'])
                ->setEmail($clienteData['email'])
                ->setTelefone($clienteData['telefone'])
                ->setEndereco($clienteData['Endereco']);

        if ($request->isMethod('POST')) {
            $cliente->setNome($request->request->get('nome'))
                    ->setEmail($request->request->get('email'))
                    ->setTelefone($request->request->get('telefone'))
                    ->setEndereco($request->request->get('Endereco'));

            $this->clienteRepository->update([
                'id' => $cliente->getId(),
                'nome' => $cliente->getNome(),
                'email' => $cliente->getEmail(),
                'telefone' => $cliente->getTelefone(),
                'Endereco' => $cliente->getEndereco()
            ]);
            return $this->redirectToRoute('cliente_index');
        }

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

        $this->clienteRepository->delete($id);
        return $this->redirectToRoute('cliente_index');
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
