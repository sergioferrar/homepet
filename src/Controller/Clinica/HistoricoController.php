<?php

namespace App\Controller\Clinica;

use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\DocumentoModelo;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Internacao;
use App\Entity\InternacaoExecucao;
use App\Entity\InternacaoPrescricao;
use App\Entity\Medicamento;
use App\Entity\Pet;
use App\Entity\Servico;
use App\Entity\Vacina;
use App\Entity\Veterinario;
use App\Repository\ConsultaRepository;
use App\Repository\DocumentoModeloRepository;
use App\Repository\FinanceiroPendenteRepository;
use App\Repository\FinanceiroRepository;
use App\Repository\InternacaoRepository;
use App\Repository\VeterinarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\GeradorpdfService;
use App\Repository\PetRepository;
use App\Controller\DefaultController;


/**
 * @Route("dashboard/clinica")
 */
class HistoricoController extends DefaultController
{

    /**
     * @Route("/pet/{petId}/documentos", name="clinica_documentos", methods={"GET","POST"})
     */
    public function documentos(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repoDoc = $this->getRepositorio(DocumentoModelo::class);
        $petRepo = $this->getRepositorio(Pet::class);

        $pet = $petRepo->find($petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $doc = new DocumentoModelo();
            $doc->setTitulo($request->get('titulo'));
            $doc->setTipo($request->get('tipo'));
            $doc->setCabecalho($request->get('cabecalho'));
            $doc->setConteudo($request->get('conteudo'));
            $doc->setRodape($request->get('rodape'));
            $doc->setCriadoEm(new \DateTime());

            $repoDoc->salvarDocumentoCompleto($baseId, $doc);

            $this->addFlash('success', 'Documento salvo com sucesso!');
            return $this->redirectToRoute('clinica_documentos', ['petId' => $petId]);
        }

        $documentos = $repoDoc->listarDocumentos($baseId);

        return $this->render('clinica/documentos.html.twig', [
            'documentos' => $documentos,
            'pet' => $pet
        ]);
    }

    /**
     * @Route("/documento/{id}/editar", name="clinica_documento_editar", methods={"GET", "POST"})
     */
    public function editarDocumento(Request $request, int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repoDoc = $this->getRepositorio(DocumentoModelo::class);
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
            'documento' => $documento,
        ]);
    }

    /**
     * @Route("/clinica/pet/{petId}/documento/{id}/excluir", name="clinica_documento_excluir", methods={"POST"})
     */
    public function excluirDocumento(
        int                       $petId,
        int                       $id,
        DocumentoModeloRepository $repoDoc,
        PetRepository             $petRepo
    ): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $petRepo->find($petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        $repoDoc->excluirDocumento($baseId, $id);

        $this->addFlash('success', 'Documento excluído com sucesso!');
        return $this->redirectToRoute('clinica_documentos', ['petId' => $petId]);
    }

    /**
     * @Route("/clinica/pet/{petId}/documento/pdf", name="clinica_documento_pdf", methods={"POST"})
     */
    public function gerarDocumentoPdf(
        int               $petId,
        Request           $request,
        PetRepository     $petRepo,
        GeradorpdfService $pdf
    ): Response
    {
        // 🔹 Seleciona o banco de dados do tenant atual
        $this->switchDB();

        // 🔹 Busca o pet
        $pet = $petRepo->find($petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        // 🔹 Recupera dados do formulário (enviados pelo Quill)
        $cabecalho = $request->get('cabecalho');
        $conteudo = $request->get('conteudo');
        $rodape = $request->get('rodape');

        // 🔹 Cabeçalho fixo com dados da clínica, tutor e veterinário
        $htmlCabecalho = '
        <div style="text-align:center; font-size:14px; border-bottom:1px solid #ccc; padding-bottom:5px;">
        <strong>Doutora das Patas - Clínica Veterinária</strong><br>
        CRMV-SP 32803 | Tel: (11) 99999-9999<br>
        <small>Rua das Patinhas, 123 - Planalto, São Paulo - SP</small>
        </div>
        <br>
        <div style="font-size:12px;">
        <strong>Pet:</strong> ' . htmlspecialchars($pet->getNome()) . '<br>
        <strong>Tutor:</strong> (não vinculado)<br>
        <strong>Veterinário Responsável:</strong> Dra. Jéssica Sabrina - CRMV 32803
        </div>
        <hr>
        ';


        // 🔸 Monta o corpo completo com formatação leve
        $htmlCompleto = '
        <div style="font-family: Arial, sans-serif; font-size:13px; line-height:1.6; color:#333;">
        ' . $cabecalho . '
        <div style="margin-top:15px;">' . $conteudo . '</div>
        <div style="margin-top:25px; font-size:12px; color:#555;">' . $rodape . '</div>
        </div>
        ';

        // 🔹 Rodapé padrão automático (igual receitas)
        $pdfRodape = '
        <div style="text-align:center; font-size:10px; color:#777;">
        Gerado automaticamente pelo sistema <strong>HomePet</strong> em ' . date('d/m/Y H:i') . '
        </div>
        ';

        // 🔹 Configuração e geração do PDF
        $pdf->setNomeArquivo('Documento_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pet->getNome()) . '_' . date('Ymd_His'));
        $pdf->setCabecalho($htmlCabecalho);
        $pdf->setRodape($pdfRodape);
        $pdf->conteudo($htmlCompleto);
        $pdf->gerar(false); // false = exibir no navegador

        return new Response();
    }
}
