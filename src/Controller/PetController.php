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
class PetController extends DefaultController
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
        $pets = $this->petRepository->findAllPets();
        return $this->render('pet/index.html.twig', ['pets' => $pets]);
    }

    /**
     * @Route("/novo", name="pet_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $donoId = $request->request->get('dono_id');

            $cliente = $this->clienteRepository->find($donoId);
            if (!$cliente) {
                throw $this->createNotFoundException('O cliente não foi encontrado');
            }

            $pet = new Pet();
            $pet->setNome($request->request->get('nome'))
                ->setEspecie($request->request->get('especie'))
                ->setSexo($request->request->get('sexo'))
                ->setRaca($request->request->get('raca'))
                ->setPorte($request->request->get('porte'))
                ->setIdade($request->request->get('idade'))
                ->setObservacoes($request->request->get('observacoes'))
                ->setDono_Id($donoId);

            $this->petRepository->save($pet);
            return $this->redirectToRoute('pet_index');
        }

        $clientes = $this->clienteRepository->findAll();
        $racas = ["Border Collie", "Poodle", "Pastor Alemão", "Golden Retriever", "Doberman Pinscher",
            "Pastor de Shetland", "Labrador Retriever", "Papillion", "Rottweiler", "Cão de gado australiano",
            "Welsh Corgi Pembroke", "Schnauzer Mini", "Springer Spaniel", "Pastor Belga Tervuren",
            "Pastor Belga Groenandel", "Schipperke", "Collie", "Keeshound", "Braço Alemão de Pelo Curto",
            "Cocker Spaniel Inglês", "Flat Coated Retriever", "Schnauzer Standard", "Spaniel Brittany",
            "Cocker Spaniel Americano", "Weimaraner", "Pastor Belga Malinois", "Bernese Montain Dog",
            "Spitz Alemão Anão", "Cão D'água Irlandês", "Vizsla", "Cardigan Welsh Corgi",
            "Yorkshire Terrier", "Chesapeake Bay Retriever", "Puli", "Schnauzer Gigante", "Airedale Terrier",
            "Bouvier de Flandres", "Border Terrier", "Briard", "Springer Spaniel Gaulês", "Manchester Terrier",
            "Samoieda", "Field Spaniel", "Terra Nova", "Australian Terrier", "American Stafford Terrier",
            "Gordon Setter", "Bearded Collie", "Setter Irlandês", "Cairn Terrier", "Kery Blue Terrier",
            "Elkhound Norueguês", "Pinscher Mini", "Affenpinscher", "Soft Coated Wheaten Terrier",
            "Silky Terrier", "Norwich Terrier", "Dálmata", "Bedlington Terrier", "Fox Terrier de Pelo Liso",
            "Curly Coated Retriever", "Wolfhound Irlandês", "Kuvasz", "Pastor Australiano", "Pointer",
            "Saluki", "Spitz da Finlândia", "Cavalier King Charles Spaniel", "Branco Alemão de Pelo Duro",
            "Coonhound", "Cão D'água Americano", "Husky Siberiano", "Bichon Frisè", "Spaniel Toy Inglês",
            "Spaniel do Tibet", "Foxhound Inglês", "Foxhound Americano", "Greyhound", "Grifo de Aponte de Pelo Duro",
            "West Highland White Terrier", "Deerhound Escocês", "Boxer", "Dogue Alemão", "Teckels",
            "Stafforshire Bull Terrier", "Malamute do Alaska", "Whippet", "Shar Pei", "Fox Terrier de Pelo Duro",
            "Rodesiano", "Ibiza Hound", "Welsh Terrier", "Irish Terrier", "Boston Terrier","Akita",
            "Skye Terrier", "Norfolk Terrier", "Sealyham Terrier", "Pug", "Bulldog Francês", "Grifo Belga",
            "Maltês", "Galgo Italiano", "Cão de Crista Chinês", "Dandie Dinmont Terrier",
            "Pequeno Grifo da Vendéia", "Terrier Tibetano", "Chin Japonês", "Lakeland Terrier",
            "Old Pastor Inglês", "Cão dos Pirineus", "São Bernardo", "Scottish Terrier", "Bull Terrier",
            "Chihuahua", "Lhasa Apso", "Bullmastiff", "Shih Tzu", "Basset Hound", "Mastiff", "Beagle",
            "Pequinês", "Bloodhound", "Borzoi", "Chow Chow", "Bulldog", "Basenji", "Afghan Hound"];

        return $this->render('pet/novo.html.twig', [
            'clientes' => $clientes,
            'racas' => $racas
        ]);
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

            $cliente = $this->clienteRepository->find($donoId);
            if (!$cliente) {
                throw $this->createNotFoundException('O cliente não foi encontrado');
            }

            $pet->setNome($request->request->get('nome') ?? '')
                ->setEspecie($request->request->get('especie') ?? '')
                ->setSexo($request->request->get('sexo') ?? '')
                ->setRaca($request->request->get('raca') ?? '')
                ->setPorte($request->request->get('porte') ?? '')
                ->setIdade($request->request->get('idade') ?? 0)
                ->setObservacoes($request->request->get('observacoes') ?? '')
                ->setDono_Id($donoId);

            $this->petRepository->update($pet);
            return $this->redirectToRoute('pet_index');
        }

        $clientes = $this->clienteRepository->findAll();

        // Definição da lista de raças para passar à view
        $racas = [
            "Border Collie", "Poodle", "Pastor Alemão", "Golden Retriever", "Doberman Pinscher",
            "Pastor de Shetland", "Labrador Retriever", "Papillion", "Rottweiler", "Australian Cattle Dog",
            "Welsh Corgi Pembroke", "Schnauzer Mini", "Springer Spaniel", "Pastor Belga Tervuren",
            "Pastor Belga Groenandel", "Schipperke", "Collie", "Keeshound", "Braco Alemão de Pelo Curto",
            "Cocker Spaniel Inglês", "Flat Coated Retriever", "Schnauzer Standard", "Spaniel Brittany",
            "Cocker Spaniel Americano", "Weimaraner", "Pastor Belga Malinois", "Bernese Montain Dog",
            "Spitz Alemão Anão", "Cão D'água Irlandês", "Vizsla", "Cardigan Welsh Corgi",
            "Yorkshire Terrier", "Chesapeake Bay Retriever", "Puli", "Schnauzer Gigante", "Airedale Terrier",
            "Bouvier de Flandres", "Border Terrier", "Briard", "Springer Spaniel Gaulês", "Manchester Terrier",
            "Samoieda", "Field Spaniel", "Terra Nova", "Australian Terrier", "American Stafford Terrier",
            "Gordon Setter", "Bearded Collie", "Setter Irlandês", "Cairn Terrier", "Kery Blue Terrier",
            "Elkhound Norueguês", "Pinscher Mini", "Affenpinscher", "Soft Coated Wheaten Terrier",
            "Silky Terrier", "Norwich Terrier", "Dálmata", "Bedlington Terrier", "Fox Terrier de Pelo Liso",
            "Curly Coated Retriever", "Wolfhound Irlandês", "Kuvasz", "Pastor Australiano", "Pointer",
            "Saluki", "Spitz da Finlândia", "Cavalier King Charles Spaniel", "Branco Alemão de Pelo Duro",
            "Coonhound", "Cão D'água Americano", "Husky Siberiano", "Bichon Frisè", "Spaniel Toy Inglês",
            "Spaniel do Tibet", "Foxhound Inglês", "Foxhound Americano", "Greyhound", "Grifo de Aponte de Pelo Duro",
            "West Highland White Terrier", "Deerhound Escocês", "Boxer", "Dogue Alemão", "Teckels",
            "Stafforshire Bull Terrier", "Malamute do Alaska", "Whippet", "Shar Pei", "Fox Terrier de Pelo Duro",
            "Rodesiano", "Ibizan Hound", "Welsh Terrier", "Irish Terrier", "Boston Terrier", "Akita",
            "Skye Terrier", "Norfolk Terrier", "Sealyham Terrier", "Pug", "Bulldog Francês", "Grifo Belga",
            "Maltês", "Galgo Italiano", "Cão de Crista Chinês", "Dandie Dinmont Terrier",
            "Pequeno Grifo da Vendéia", "Terrier Tibetano", "Chin Japonês", "Lakeland Terrier",
            "Old English Sheepdog", "Cão dos Pirineus", "São Bernardo", "Scottish Terrier", "Bull Terrier",
            "Chihuahua", "Lhasa Apso", "Bullmastiff", "Shih Tzu", "Basset Hound", "Mastiff", "Beagle",
            "Pequinês", "Bloodhound", "Borzoi", "Chow Chow", "Bulldog", "Basenji", "Afghan Hound"
        ];

        return $this->render('pet/editar.html.twig', [
            'pet' => $pet,
            'clientes' => $clientes,
            'racas' => $racas
        ]);
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
