<?php

namespace App\Controller;

use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\Pet;
use App\Repository\DocumentoModeloRepository;
use App\Service\PdfService;
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

    /**
     * @Route("/receita", name="clinica_receita", methods={"GET"})
     */
    public function receita(): Response
    {
        $this->switchDB();
        return $this->render('clinica/receita.html.twig');
    }

    /**
     * @Route("/receita/pdf", name="clinica_receita_pdf", methods={"POST"})
     */
    public function gerarReceitaPdf(Request $request, PdfService $pdfService): Response
    {
        $this->switchDB();

        $cabecalho = $request->request->get('cabecalho', '');
        $conteudo  = $request->request->get('conteudo', '');
        $rodape    = $request->request->get('rodape', '');

        return $pdfService->gerarPdf(
            'clinica/receita_pdf_backend.html.twig',
            [
                'cabecalho' => $cabecalho,
                'conteudo'  => $conteudo,
                'rodape'    => $rodape
            ],
            'receita-medica.pdf'
        );
    }

    /**
     * @Route("/documentos", name="clinica_documentos", methods={"GET", "POST"})
     */
    public function documentos(Request $request, DocumentoModeloRepository $repoDoc): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        if ($request->isMethod('POST')) {
            $titulo = $request->request->get('titulo');
            $conteudo = $request->request->get('conteudo');

            $repoDoc->salvarDocumento($baseId, $titulo, $conteudo);
            $this->addFlash('success', 'Documento criado com sucesso!');
            return $this->redirectToRoute('clinica_documentos');
        }

        $documentos = $repoDoc->listarDocumentos($baseId);

        return $this->render('clinica/documentos.html.twig', [
            'documentos' => $documentos
        ]);
    }

    /**
     * @Route("/documento/{id}/editar", name="clinica_documento_editar", methods={"GET", "POST"})
     */
    public function editarDocumento(int $id, Request $request, DocumentoModeloRepository $repoDoc): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $documento = $repoDoc->buscarPorId($baseId, $id);

        if (!$documento) {
            throw $this->createNotFoundException('Documento não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $documento->setTitulo($request->get('titulo'));
            $documento->setConteudo($request->get('conteudo'));

            $repoDoc->atualizarDocumento($baseId, $documento);

            $this->addFlash('success', 'Documento atualizado com sucesso!');
            return $this->redirectToRoute('clinica_documento_editar', ['id' => $id]);
        }

        return $this->render('clinica/documento_editar.html.twig', [
            'documento' => $documento
        ]);
    }

    /**
     * @Route("/receita/pdf", name="clinica_receita_pdf", methods={"POST"})
     */
    public function gerarPdfReceitaBackend(Request $request, PdfService $pdfService): Response
    {
        $this->switchDB(); // se necessário

        $cabecalho = $request->request->get('cabecalho', '');
        $conteudo  = $request->request->get('conteudo', '');
        $rodape    = $request->request->get('rodape', '');

        return $pdfService->gerarPdfReceita($cabecalho, $conteudo, $rodape);
    }

}
