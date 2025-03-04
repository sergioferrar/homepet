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


    /**
     * @Route("/", name="cliente_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');

        $clientes =  $this->getRepositorio(Cliente::class)->search($this->session->get('userId'), $search);

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
                ->setCidade($request->request->get('cidade'));

            $this->getRepositorio(Cliente::class)->save($this->session->get('userId'), [
                'nome' => $cliente->getNome(),
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

    /**
     * @Route("/editar/{id}", name="cliente_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $cliente = $this->getRepositorio(Cliente::class)->localizaTodosClientePorID($this->session->get('userId'), $id);

        if (!$cliente) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $clientes = new Cliente();
            $clientes->setNome($request->get('nome'))
                ->setId($cliente['id'])
                ->setEmail($request->get('email'))
                ->setTelefone($request->get('telefone'))
                ->setRua($request->get('rua'))
                ->setNumero($request->get('numero'))
                ->setComplemento($request->get('complemento'))
                ->setBairro($request->get('bairro'))
                ->setCidade($request->get('cidade'))
                ->setWhatsapp($request->get('whatsapp'))
                ;
            $cliente['whatsapp'] = $request->get('whatsapp');
            $cliente['nome'] = $request->get('nome');

            $this->getRepositorio(Cliente::class)->update($this->session->get('userId'), $cliente);
//            dd($cliente, $clientes, $request);

            // $clienteId = $this->getRepositorio(Cliente::class)->getLastInsertedId();
            //return $this->redirectToRoute('pet_novo', ['cliente_id' => $id]);

        }
//        dd($cliente);
        return $this->render('cliente/editar.html.twig', [
            'cliente' => $cliente
        ]);
    }


    /**
     * @Route("/deletar/{id}", name="cliente_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $cliente = $this->getRepositorio(Cliente::class)->localizaTodosClientePorID($this->session->get('userId'), $id);

        if (!$cliente) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        // Verifica se o cliente tem pets antes de excluir
        if ($this->getRepositorio(Cliente::class)->hasPets($id)) {
            $this->addFlash('error', 'Não é possível excluir este cliente, pois ele possui pets cadastrados.');
            $clienteId = $this->clienteRepository->getLastInsertedId();
            return $this->redirectToRoute('pet_novo', ['cliente_id' => $clienteId]);

        }

        $this->getRepositorio(Cliente::class)->delete($this->session->get('userId'), $id);
        $clienteId = $this->getRepositorio(Cliente::class)->getLastInsertedId();
        return $this->redirectToRoute('pet_novo', ['cliente_id' => $clienteId]);

    }


    /**
     * @Route("/{id}/agendamentos", name="cliente_agendamentos", methods={"GET"})
     */
    public function agendamentos(Request $request, int $id): Response
    {
        $clienteData = $this->getRepositorio(Cliente::class)->findAgendamentosByCliente($this->session->get('userId'), $id);

        if (!$clienteData) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        $agendamentos = $this->getRepositorio(Cliente::class)->findAgendamentosByCliente($id);
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
