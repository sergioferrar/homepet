<?php

namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\ProntuarioPet;
use App\Repository\ProntuarioPetRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica")
 */
class ProntuarioController extends DefaultController
{
    /**
     * @Route("/prontuario/{petId}", name="clinica_prontuario", methods={"GET", "POST"})
     */
    public function prontuarioPet(int $petId, Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $repo = new ProntuarioPetRepository($this->getDoctrine()->getConnection());

        if ($request->isMethod('POST')) {
            $r = new ProntuarioPet();
            $r->setPetId($petId);
            $r->setData(new \DateTime());
            $r->setTipo($request->get('tipo'));
            $r->setDescricao($request->get('descricao'));

            if ($request->files->get('anexo')) {
                $file = $request->files->get('anexo');
                $nome = uniqid() . '.' . $file->guessExtension();
                $file->move($this->getParameter('uploads_directory'), $nome);
                $r->setAnexo($nome);
            }

            $repo->salvar($baseId, $r);
            $this->addFlash('success', 'Registro adicionado.');
            return $this->redirectToRoute('clinica_prontuario', ['petId' => $petId]);
        }

        $registros = $repo->listarPorPet($baseId, $petId);

        return $this->render('clinica/prontuario.html.twig', [
            'registros' => $registros,
            'petId' => $petId
        ]);
    }

    /**
     * @Route("/prontuario/ajax/{petId}", name="clinica_prontuario_ajax", methods={"GET"})
     */
    public function prontuarioAjax(int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $repo = new ProntuarioPetRepository($this->getDoctrine()->getConnection());

        $registros = $repo->listarPorPet($baseId, $petId);

        return $this->render('clinica/_prontuario_ajax.html.twig', [
            'registros' => $registros
        ]);
    }
}
