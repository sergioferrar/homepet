<?php

namespace App\Controller;

use App\Entity\Vacina;
use App\Entity\Pet;
use App\Repository\VacinaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/clinica")
 */
class VacinaController extends DefaultController
{
    /**
     * Lista todas as vacinas do pet
     * 
     * @Route("/pet/{petId}/vacinas", name="clinica_vacinas", methods={"GET"})
     */
    public function listar(int $petId, VacinaRepository $repo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Usa o método SQL puro do novo repository
        $vacinas = $repo->listarPorPet($baseId, $petId);

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);

        return $this->render('clinica/vacina/index.html.twig', [
            'vacinas' => $vacinas,
            'pet'     => $pet,
        ]);
    }

    /**
     * Cria uma nova vacina (POST)
     * 
     * @Route("/pet/{petId}/vacina/nova", name="clinica_vacina_nova", methods={"POST"})
     */
    public function novaVacina(int $petId, Request $request, VacinaRepository $repo): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $vacina = new Vacina();
        $vacina->setPetId($petId);
        $vacina->setTipo($request->request->get('tipo'));
        $vacina->setDataAplicacao(new \DateTime($request->request->get('data_aplicacao')));
        $vacina->setDataValidade(
            $request->request->get('data_validade') ? new \DateTime($request->request->get('data_validade')) : null
        );
        $vacina->setLote($request->request->get('lote'));
        $vacina->setFabricante($request->request->get('fabricante'));
        $vacina->setObservacoes($request->request->get('observacoes'));
        $vacina->setVeterinarioId($request->request->get('veterinario_id'));

        // Usa o método SQL direto (multi-tenant)
        $repo->inserir($baseId, $vacina);

        return new JsonResponse(['ok' => true, 'msg' => 'Vacina registrada com sucesso!']);
    }

    /**
     * Remove uma vacina
     * 
     * @Route("/pet/{petId}/vacina/{id}/remover", name="clinica_vacina_remover", methods={"POST"})
     */
    public function remover(int $petId, int $id, VacinaRepository $repo, Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $vacina = $repo->buscarPorId($baseId, $id);
        if (! $vacina) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'msg' => 'Vacina não encontrada']);
            }
            $this->addFlash('danger', 'Vacina não encontrada.');
            return $this->redirectToRoute('clinica_vacinas', ['petId' => $petId]);
        }

        $repo->deletar($baseId, $id);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'msg' => 'Vacina removida com sucesso!']);
        }

        $this->addFlash('success', 'Vacina removida com sucesso!');
        return $this->redirectToRoute('clinica_vacinas', ['petId' => $petId]);
    }
}
