<?php

namespace App\Controller;

use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\Pet;
use App\Entity\DocumentoModelo; 
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

        $consultas = method_exists($repoConsulta, 'listarConsultasDoDiaEProximas')
            ? $repoConsulta->listarConsultasDoDiaEProximas($baseId)
            : [];

        $petsRecentes = method_exists($repoPet, 'listarPetsRecentes')
            ? $repoPet->listarPetsRecentes($baseId)
            : [];

        $consultasPorMes = method_exists($repoConsulta, 'contarConsultasPorMes')
            ? $repoConsulta->contarConsultasPorMes($baseId)
            : [];

        $petsPorEspecie = method_exists($repoPet, 'contarPetsPorEspecie')
            ? $repoPet->contarPetsPorEspecie($baseId)
            : [];

        return $this->render('clinica/dashboard.html.twig', [
            'clientes' => $clientes,
            'total_pets' => $totalPets,
            'consultas' => $consultas,
            'pets_recentes' => $petsRecentes,
            'consultas_por_mes_keys' => array_keys($consultasPorMes),
            'consultas_por_mes_vals' => array_values($consultasPorMes),
            'pets_por_especie_keys' => array_keys($petsPorEspecie),
            'pets_por_especie_vals' => array_values($petsPorEspecie),
        ]);
    }

    /**
     * @Route("/consulta/nova", name="clinica_nova_consulta", methods={"GET", "POST"})
     */
    public function novaConsulta(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $clientes = $this->getRepositorio(Cliente::class)->localizaTodosCliente($baseId);
        $consultas = $this->getRepositorio(Consulta::class)->listarConsultasDoDia($baseId, new \DateTime());

        if ($request->isMethod('POST')) {
            $consulta = new Consulta();
            $consulta->setEstabelecimentoId($baseId);
            $consulta->setClienteId((int) $request->get('cliente_id'));
            $consulta->setPetId((int) $request->get('pet_id'));
            $consulta->setData(new \DateTime($request->get('data')));
            $consulta->setHora(new \DateTime($request->get('hora')));
            $consulta->setObservacoes($request->get('observacoes'));
            $consulta->setStatus('aguardando');

            $this->getRepositorio(Consulta::class)->salvarConsulta($baseId, $consulta);

            $this->addFlash('success', 'Consulta marcada com sucesso!');
            return $this->redirectToRoute('clinica_nova_consulta');
        }

        return $this->render('clinica/nova_consulta.html.twig', [
            'clientes' => $clientes,
            'consultas' => $consultas,
        ]);
    }

    /**
     * @Route("/consulta/{id}/status/{status}", name="clinica_consulta_status", methods={"POST"})
     */
    public function atualizarStatusConsulta(int $id, string $status): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $statusPermitidos = ['aguardando', 'atendido', 'cancelado'];
        if (!in_array($status, $statusPermitidos)) {
            return $this->json(['erro' => 'Status inválido'], 400);
        }

        $this->getRepositorio(Consulta::class)->atualizarStatusConsulta($baseId, $id, $status);

        return $this->json(['success' => true]);
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
            $titulo    = $request->request->get('titulo');
            $cabecalho = $request->request->get('cabecalho');
            $conteudo  = $request->request->get('conteudo');
            $rodape    = $request->request->get('rodape');

            $doc = new DocumentoModelo();
            $doc->setTitulo($titulo);
            $doc->setCabecalho($cabecalho);
            $doc->setConteudo($conteudo);
            $doc->setRodape($rodape);
            $doc->setCriadoEm(new \DateTime());

            $repoDoc->salvarDocumentoCompleto($baseId, $doc);

            $this->addFlash('success', 'Novo documento salvo com sucesso!');
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
            $documento->setCabecalho($request->get('cabecalho'));
            $documento->setConteudo($request->get('conteudo'));
            $documento->setRodape($request->get('rodape'));

            $repoDoc->atualizarDocumentoCompleto($baseId, $documento);

            $this->addFlash('success', 'Documento atualizado com sucesso!');
            return $this->redirectToRoute('clinica_documento_editar', ['id' => $id]);
        }

        return $this->render('clinica/documento_editar.html.twig', [
            'documento' => $documento
        ]);
    }

    /**
     * @Route("/documento/{id}/excluir", name="clinica_documento_excluir", methods={"POST"})
     */
    public function excluirDocumento(int $id, Request $request, DocumentoModeloRepository $repoDoc): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $documento = $repoDoc->buscarPorId($baseId, $id);

        if (!$documento) {
            $this->addFlash('danger', 'Documento não encontrado.');
        } else {
            $repoDoc->excluirDocumento($baseId, $id);
            $this->addFlash('success', 'Documento excluído com sucesso!');
        }

        return $this->redirectToRoute('clinica_documentos');
    }


    /**
     * @Route("/api/pets/{clienteId}", name="clinica_api_pets", methods={"GET"})
     */
    public function apiPetsPorCliente(int $clienteId): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $pets = $this->getRepositorio(Pet::class)->buscarPetsPorCliente($baseId, $clienteId);

        $result = array_map(function ($pet) {
            return ['id' => $pet['id'], 'nome' => $pet['nome']];
        }, $pets);

        return $this->json($result);
    }


}
