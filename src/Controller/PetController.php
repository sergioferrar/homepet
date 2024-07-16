<?php
namespace App\Controller;

use App\Entity\Pet;
use App\Repository\PetRepository;
use App\Repository\ClienteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/pet")
 */
class PetController extends AbstractController
{
    private $petRepository;
    private $clienteRepository;

    public function __construct(PetRepository $petRepository, ClienteRepository $clienteRepository)
    {
        $this->petRepository = $petRepository;
        $this->clienteRepository = $clienteRepository;
    }

    /**
     * @Route("/", name="pet_index", methods={"GET"})
     */
    public function index(): Response
    {
        $pets = $this->petRepository->findAll();
        return $this->render('pet/index.html.twig', ['pets' => $pets]);
    }

    /**
     * @Route("/novo", name="pet_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $donoId = $request->request->get('dono_id');

            // Verifica se o cliente existe
            $cliente = $this->clienteRepository->find($donoId);
            if (!$cliente) {
                throw $this->createNotFoundException('O cliente não foi encontrado');
            }

            $pet = new Pet();
            $pet->setNome($request->request->get('nome'))
                ->setTipo($request->request->get('tipo'))
                ->setIdade($request->request->get('idade'))
                ->setDono_Id($donoId);

            $this->petRepository->save($pet);
            return $this->redirectToRoute('pet_index');
        }

        $clientes = $this->clienteRepository->findAll();
        return $this->render('pet/novo.html.twig', ['clientes' => $clientes]);
    }

    /**
     * @Route("/editar/{id}", name="pet_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $pet = $this->petRepository->find($id);

        if (!$pet) {
            throw $this->createNotFoundException('O pet não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $donoId = $request->request->get('dono_id');

            // Verifica se o cliente existe
            $cliente = $this->clienteRepository->find($donoId);
            if (!$cliente) {
                throw $this->createNotFoundException('O cliente não foi encontrado');
            }

            $pet->setNome($request->request->get('nome'))
                ->setTipo($request->request->get('tipo'))
                ->setIdade($request->request->get('idade'))
                ->setDono_Id($donoId);

            $this->petRepository->update($pet);
            return $this->redirectToRoute('pet_index');
        }

        $clientes = $this->clienteRepository->findAll();
        return $this->render('pet/editar.html.twig', ['pet' => $pet, 'clientes' => $clientes]);
    }

    /**
     * @Route("/deletar/{id}", name="pet_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $pet = $this->petRepository->find($id);

        if (!$pet) {
            throw $this->createNotFoundException('O pet não foi encontrado');
        }

        $this->petRepository->delete($id);
        return $this->redirectToRoute('pet_index');
    }

    /**
     * @Route("/pet/cadastro", name="cadastro_pet")
     */
    public function cadastro(): Response
    {
        return $this->render('pet/cadastro_pet.html.twig');
    }
}
