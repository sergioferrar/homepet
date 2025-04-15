<?php

namespace App\Controller;

use App\Entity\Cliente;
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


    /**
     * @Route("/", name="pet_index", methods={"GET"})
     */
    public function index(): Response
    {
        $this->switchDB();
        $pets = $this->getRepositorio(Pet::class)->findAllPets($this->session->get('userId'));
        return $this->render('pet/index.html.twig', ['pets' => $pets]);
    }

    /**
     * @Route("/novo", name="pet_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        $this->switchDB();
        if ($request->isMethod('POST')) {
            $donoId = $request->get('dono_id');

//            $cliente = $this->getRepositorio(Cliente::class)->findAgendamentosByCliente($this->session->get('userId'), $donoId);
//            if (!$cliente) {
//                throw $this->createNotFoundException('O cliente não foi encontrado');
//            }

            $pet = new Pet();
            $pet->setNome($request->get('nome'))
                ->setEspecie($request->get('especie'))
                ->setSexo($request->get('sexo'))
                ->setRaca($request->get('raca'))
                ->setPorte($request->get('porte'))
                ->setIdade($request->get('idade'))
                ->setObservacoes($request->get('observacoes'))
                ->setDono_Id($donoId);
            $this->getRepositorio(Pet::class)->save($this->session->get('userId'), $pet);
            return $this->redirectToRoute('pet_index');
        }

        $clientes = $this->getRepositorio(Cliente::class)->localizaTodosCliente($this->session->get('userId'));
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

        sort($racas, SORT_LOCALE_STRING);

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
    $this->switchDB();
    $pet = $this->getRepositorio(Pet::class)->findPetById($this->session->get('userId'), $id);
    if (!$pet) {
        throw $this->createNotFoundException('O pet não foi encontrado');
    }

    $clientes = $this->getRepositorio(Cliente::class)->localizaTodosCliente($this->session->get('userId'));

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
    sort($racas, SORT_LOCALE_STRING);

    if ($request->isMethod('POST')) {
        $donoId = $request->get('dono_id');

        $cliente = $this->getRepositorio(Cliente::class)->localizaTodosClientePorID($this->session->get('userId'), $donoId);
        if (!$cliente) {
            throw $this->createNotFoundException('O cliente não foi encontrado');
        }

        $pets = new Pet();
        $pets->setId($pet['id'])
            ->setNome($request->get('nome') ?? '')
            ->setEspecie($request->get('especie') ?? '')
            ->setSexo($request->get('sexo') ?? '')
            ->setRaca($request->get('raca') ?? '')
            ->setPorte($request->get('porte') ?? '')
            ->setIdade($request->get('idade') ?? 0)
            ->setObservacoes($request->get('observacoes') ?? '')
            ->setDono_Id($donoId);

        $this->getRepositorio(Pet::class)->update($this->session->get('userId'), $pets);
        return $this->redirectToRoute('pet_index');
    }

    // Se for uma requisição AJAX, renderiza apenas o formulário para o modal
    if ($request->isXmlHttpRequest()) {
        return $this->render('pet/_form_modal.html.twig', [
            'pet' => $pet,
            'clientes' => $clientes,
            'racas' => $racas
        ]);
    }

    // Caso contrário, renderiza a página inteira normalmente
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
        $this->switchDB();
        $pet = $this->getRepositorio(Pet::class)->find($id);

        if (!$pet) {
            throw $this->createNotFoundException('O pet não foi encontrado');
        }

        $this->getRepositorio(Pet::class)->delete($id);
        return $this->redirectToRoute('pet_index');
    }

    /**
     * @Route("/pet/cadastro", name="cadastro_pet")
     */
    public function cadastro(): Response
    {
        $this->switchDB();
        return $this->render('pet/cadastro_pet.html.twig');
    }
}
