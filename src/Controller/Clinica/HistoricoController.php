<?php

namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\DocumentoModelo;
use App\Entity\Estabelecimento;
use App\Entity\Pet;
use App\Entity\Veterinario;
use App\Service\GeradorpdfService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

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
            // Verifica se é para salvar dados para PDF na sessão
            if ($request->get('salvar_pdf_sessao')) {
                $dados = json_decode($request->get('dados'), true);
                $request->getSession()->set('pdf_documento_dados', $dados);
                return new JsonResponse(['status' => 'success']);
            }

            // Salvar documento normal
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
            'pet' => $pet,
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
     * @Route("/pet/{petId}/documento/{id}/excluir", name="clinica_documento_excluir", methods={"POST"})
     */
    public function excluirDocumento(
        int $petId,
        int $id
    ): Response {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $repoDoc = $this->getRepositorio(DocumentoModelo::class);
        $petRepo = $this->getRepositorio(Pet::class);

        $pet = $petRepo->find($petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        $repoDoc->excluirDocumento($baseId, $id);

        $this->addFlash('success', 'Documento excluído com sucesso!');
        return $this->redirectToRoute('clinica_documentos', ['petId' => $petId]);
    }

    /**
     * @Route("/pet/{petId}/documento/pdf", name="clinica_documento_pdf", methods={"GET"})
     */
    public function gerarDocumentoPdf(
        int $petId,
        Request $request,
        GeradorpdfService $pdf
    ): Response {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Recupera dados da sessão
        $dadosPdf = $request->getSession()->get('pdf_documento_dados');
        if (!$dadosPdf) {
            throw $this->createNotFoundException('Dados do documento não encontrados na sessão.');
        }

        // Busca o pet com dados completos
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        // Busca dados do cliente/tutor
        $donoId = $pet['dono_id'] ?? null;
        $cliente = $donoId ? $this->getRepositorio(Cliente::class)->find($donoId) : null;
        $clienteNome = $cliente ? $cliente->getNome() : ($pet['dono_nome'] ?? 'Não informado');

        // Busca dados da clínica
        $this->restauraLoginDB();
        $clinica = $this->getRepositorio(Estabelecimento::class)->find($baseId);
        $this->switchDB();

        // Busca veterinário (usa mesma lógica da receita)
        $vetRepo = $this->getRepositorio(Veterinario::class);

        $vet = null;
        // Se nada foi escolhido, usa o veterinário vinculado ao usuário logado
        $user = $this->getUser();
        if ($user && method_exists($user, 'getVeterinarioId') && $user->getVeterinarioId()) {
            $vet = $vetRepo->find((int) $user->getVeterinarioId());
        }

        if (!$vet) {
            $vetIdConsulta = $this->getRepositorio(Consulta::class)->findVetIdUltimaConsulta($baseId, $petId);
            if ($vetIdConsulta) {
                $vet = $vetRepo->find($vetIdConsulta);
            }
        }

        if (!$vet) {
            $vet = $vetRepo->findOneBy(['estabelecimentoId' => $baseId]);
        }

        if (!$vet) {
            throw $this->createNotFoundException('Veterinário não encontrado.');
        }

        // Recupera dados do documento da sessão
        $cabecalho = $dadosPdf['cabecalho'] ?? '';
        $conteudo = $dadosPdf['conteudo'] ?? '';
        $rodape = $dadosPdf['rodape'] ?? '';
        $tipoDocumento = $dadosPdf['tipo_documento'] ?? 'DOCUMENTO';

        // --- Formatação dos dados (igual receita) ---
        $esc = function ($v) { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES); };
        $ou = function ($v) { $v = trim((string) ($v ?? '')); return $v !== '' ? $v : '—'; };

        $petNome = $esc($ou($pet['nome'] ?? ''));
        $petEspecie = $esc($ou($pet['especie'] ?? ''));
        $petRaca = $esc($ou($pet['raca'] ?? ''));
        $petSexo = $esc($ou($pet['sexo'] ?? ''));
        $petPorte = $esc($ou($pet['porte'] ?? ''));
        $idadeRaw = trim((string) ($pet['idade'] ?? ''));
        $petIdade = $esc($idadeRaw !== '' ? ($idadeRaw . (is_numeric($idadeRaw) ? ' ano(s)' : '')) : '—');
        $pesoRaw = trim((string) ($pet['peso'] ?? ''));
        $petPeso = $esc($pesoRaw !== '' ? ($pesoRaw . ' kg') : '—');
        $castradoRaw = $pet['castrado'] ?? null;
        $petCastrado = ($castradoRaw === null || $castradoRaw === '') ? '—' : (((int) $castradoRaw === 1) ? 'Sim' : 'Não');
        $petNasc = !empty($pet['dataNascimento']) ? date('d/m/Y', strtotime($pet['dataNascimento'])) : '—';

        $tutorNome = $esc($ou($clienteNome));
        $tutorCpf = $esc($ou($cliente ? $cliente->getCpf() : ''));
        $telefoneRaw = $cliente ? ($cliente->getTelefone() ?: '') : ($pet['dono_telefone'] ?? '');
        $whatsRaw = $cliente ? ($cliente->getWhatsapp() ?: '') : '';
        $tutorTel = $esc($ou($telefoneRaw));
        $tutorWhats = $esc($ou($whatsRaw));
        $tutorEmail = $esc($ou($cliente ? $cliente->getEmail() : ($pet['dono_email'] ?? '')));
        $tutorEndereco = $esc($ou(trim((string) ($pet['dono_endereco'] ?? ''), " ,-")));

        $lblStyle = "font-size:8px; text-transform:uppercase; letter-spacing:0.5px; color:#94A3B8;";
        $valStyle = "font-size:10.5px; color:#0F172A;";

        // Cabeçalho formatado igual receita
        $cabecalhoHtml = "
<table width='100%' style='border-collapse:collapse;'>
  <tr>
    <td style='padding:0 0 7px 0; border-bottom:2px solid #5d57f4;'>
      <table width='100%' style='border-collapse:collapse;'>
        <tr>
          <td style='width:48px; vertical-align:middle; padding-right:8px;'>
            <table style='border-collapse:collapse;'>
              <tr>
                <td style='width:38px; height:38px; border:2px solid #5d57f4; border-radius:50%; text-align:center; vertical-align:middle; font-size:16px; font-weight:bold; color:#5d57f4;'>+</td>
              </tr>
            </table>
          </td>
          <td style='vertical-align:middle; text-align:left;'>
            <span style='font-size:16px; font-weight:bold; color:#0F172A;'>" . ($clinica->getRazaoSocial() ?? 'Clínica Veterinária') . "</span><br>
            <span style='font-size:10px; color:#475569;'>CNPJ: " . ($clinica->getCnpj() ?? '') . "</span>
          </td>
          <td style='vertical-align:middle; text-align:right; font-size:10px; color:#475569; line-height:1.6;'>
            " . ($clinica->getRua() ? $clinica->getRua() . ', ' . $clinica->getNumero() . ' - ' . $clinica->getBairro() : 'Endereço não informado') . "<br>
            " . ($clinica->getCidade() ? $clinica->getCidade() . ' - CEP: ' . $clinica->getCep() : 'Cidade não informada') . "
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<table width='100%' style='border-collapse:collapse; margin-top:8px;'>
  <tr>
    <td style='text-align:center; padding-bottom:5px;'>
      <span style='font-size:11px; font-weight:bold; letter-spacing:2.5px; color:#5d57f4;'>" . strtoupper($tipoDocumento) . "</span>
    </td>
  </tr>
</table>

<table width='100%' style='border-collapse:collapse; background-color:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px;'>
  <tr>
    <td style='width:50%; vertical-align:top; padding:7px 11px; border-right:1px solid #E2E8F0;'>
      <div style='font-size:9px; font-weight:bold; letter-spacing:1px; color:#5d57f4; padding-bottom:3px;'>TUTOR</div>
      <div style='padding-bottom:2px;'><span style='{$lblStyle}'>Nome</span> <span style='{$valStyle}'>{$tutorNome}</span> &nbsp; <span style='{$lblStyle}'>CPF</span> <span style='{$valStyle}'>{$tutorCpf}</span></div>
      <div style='padding-bottom:2px;'><span style='{$lblStyle}'>Telefone</span> <span style='{$valStyle}'>{$tutorTel}</span> &nbsp; <span style='{$lblStyle}'>WhatsApp</span> <span style='{$valStyle}'>{$tutorWhats}</span></div>
      <div style='padding-bottom:2px;'><span style='{$lblStyle}'>E-mail</span> <span style='{$valStyle}'>{$tutorEmail}</span></div>
      <div><span style='{$lblStyle}'>Endereço</span> <span style='{$valStyle}'>{$tutorEndereco}</span></div>
    </td>
    <td style='width:50%; vertical-align:top; padding:7px 11px;'>
      <div style='font-size:9px; font-weight:bold; letter-spacing:1px; color:#5d57f4; padding-bottom:3px;'>PACIENTE</div>
      <div style='padding-bottom:2px;'><span style='{$lblStyle}'>Nome</span> <span style='{$valStyle}'>{$petNome}</span> &nbsp; <span style='{$lblStyle}'>Espécie</span> <span style='{$valStyle}'>{$petEspecie}</span></div>
      <div style='padding-bottom:2px;'><span style='{$lblStyle}'>Raça</span> <span style='{$valStyle}'>{$petRaca}</span> &nbsp; <span style='{$lblStyle}'>Sexo</span> <span style='{$valStyle}'>{$petSexo}</span> &nbsp; <span style='{$lblStyle}'>Porte</span> <span style='{$valStyle}'>{$petPorte}</span></div>
      <div><span style='{$lblStyle}'>Idade</span> <span style='{$valStyle}'>{$petIdade}</span> &nbsp; <span style='{$lblStyle}'>Peso</span> <span style='{$valStyle}'>{$petPeso}</span> &nbsp; <span style='{$lblStyle}'>Castrado</span> <span style='{$valStyle}'>{$petCastrado}</span> &nbsp; <span style='{$lblStyle}'>Nasc.</span> <span style='{$valStyle}'>{$petNasc}</span></div>
    </td>
  </tr>
</table>
";

        // Conteúdo do documento
        $conteudoHtml = '
        <div style="font-family: Arial, sans-serif; font-size:13px; line-height:1.6; color:#333; margin-top:15px;">
        ' . $cabecalho . '
        <div style="margin-top:15px;">' . $conteudo . '</div>
        <div style="margin-top:25px; font-size:12px; color:#555;">' . $rodape . '</div>
        </div>
        ';

        // Rodapé com assinatura do veterinário
        $rodapeHtml = "
<table width='100%' style='border-collapse:collapse; margin-top:2px;'>
  <tr>
    <td style='border-top:1px solid #E2E8F0; padding-top:8px; text-align:center;'>
      <table style='margin:0 auto; border-collapse:collapse;'>
        <tr>
          <td style='border-top:1px solid #0F172A; padding-top:3px; text-align:center; min-width:220px;'>
            <span style='font-size:8px; text-transform:uppercase; letter-spacing:1px; color:#94A3B8;'>Assinatura do Veterinário</span><br>
            <span style='font-size:11px; font-weight:bold; color:#0F172A;'>{$vet->getNome()}</span> &nbsp;
            <span style='font-size:9.5px; color:#475569;'>CRMV: {$vet->getCrmv()}</span>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style='text-align:center; padding-top:6px;'>
      <span style='font-size:8px; color:#94A3B8;'>Documento emitido em: " . date('d/m/Y H:i:s') . "</span>
      <span style='font-size:7.5px; color:#cbd5e1;'> &nbsp;·&nbsp; System Home Pet — Seu CRM para clínicas e pet shops</span>
    </td>
  </tr>
</table>
";

        // Configuração e geração do PDF (igual receita)
        $nomeArquivo = str_replace(' ', '_', $tipoDocumento) . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $petNome) . '_' . date('Ymd_His');
        
        $pdf->configuracaoPagina('A4', 10, 10, 64, 26, 8, 8);
        $pdf->setNomeArquivo($nomeArquivo);
        $pdf->setRodape($rodapeHtml);
        $pdf->montaCabecalhoPadrao($cabecalhoHtml);
        $pdf->addPagina('P');
        $pdf->conteudo($conteudoHtml);

        // Limpa os dados da sessão após usar
        $request->getSession()->remove('pdf_documento_dados');

        return $pdf->gerar();
    }
}
