<?php

namespace App\Controller;

use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\Pet;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/clinica")
 */
class ClinicaController extends DefaultController
{
    /**
     * @Route("/dashboard", name="clinica_dashboard", methods={"GET"})
     */
    public function dashboard(): Response
    {
        $this->switchDB();

        $baseId = $this->session->get('userId');
        $repoCliente = $this->getRepositorio(Cliente::class);
        $repoPet = $this->getRepositorio(Pet::class);
        $repoConsulta = $this->getRepositorio(Consulta::class);

        $clientes = method_exists($repoCliente, 'findClientesComPet')
            ? $repoCliente->findClientesComPet($baseId)
            : [];

        $totalPets = method_exists($repoPet, 'countTotalPets')
            ? $repoPet->countTotalPets($baseId)
            : 0;

        $consultas = method_exists($repoConsulta, 'listarConsultasFuturas')
            ? $repoConsulta->listarConsultasFuturas($baseId)
            : [];

        $petsRecentes = method_exists($repoPet, 'listarPetsRecentes')
            ? $repoPet->listarPetsRecentes($baseId)
            : [];

        return $this->render('clinica/dashboard.html.twig', [
            'clientes' => $clientes,
            'total_pets' => $totalPets,
            'consultas' => $consultas,
            'pets_recentes' => $petsRecentes,
        ]);
    }

    /**
     * @Route("/consulta/nova", name="clinica_nova_consulta", methods={"GET", "POST"})
     */
    public function novaConsulta(Request $request): Response
    {
        $this->switchDB();
        $clientes = $this->getRepositorio(Cliente::class)->localizaTodosCliente($this->session->get('userId'));

        if ($request->isMethod('POST')) {
            $consulta = new Consulta();
            $consulta->setEstabelecimentoId($this->session->get('userId'));
            $consulta->setClienteId((int) $request->get('cliente_id'));
            $consulta->setPetId((int) $request->get('pet_id'));
            $consulta->setData(new \DateTime($request->get('data')));
            $consulta->setHora(new \DateTime($request->get('hora')));
            $consulta->setObservacoes($request->get('observacoes'));

            $this->getRepositorio(Consulta::class)->salvarConsulta($this->session->get('userId'), $consulta);

            $this->addFlash('success', 'Consulta marcada com sucesso!');
            return $this->redirectToRoute('clinica_dashboard');
        }

        return $this->render('clinica/nova_consulta.html.twig', [
            'clientes' => $clientes
        ]);
    }
}
