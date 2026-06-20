<?php

namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\Pet;
use App\Entity\Veterinario;
use App\Repository\ConsultaRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica")
 */
class FichaController extends DefaultController
{
    /**
     * @Route("/pet/{petId}/atendimento/novo", name="clinica_novo_atendimento", methods={"POST"})
     */
    public function novoAtendimento(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        $consulta = new Consulta();
        $consulta->setEstabelecimentoId($baseId);
        $consulta->setClienteId((int) $request->get('cliente_id'));
        $consulta->setPetId($petId);
        $consulta->setData(new \DateTime($request->get('data')));
        $consulta->setHora(new \DateTime($request->get('hora')));
        $consulta->setObservacoes($request->get('observacoes'));

        $consulta->setAnamnese($request->get('anamnese_delta'));

        $consulta->setTipo($request->get('tipo'));
        $consulta->setStatus('atendido');
        $consulta->setCriadoEm(new \DateTime());

        $this->getRepositorio(Consulta::class)->salvarConsulta($baseId, $consulta);

        $this->addFlash('success', 'Atendimento salvo com sucesso!');
        return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
    }

    /**
     * @Route("/pet/{petId}/receita", name="clinica_nova_receita", methods={"GET","POST"})
     */
    public function receita(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        $donoId = $pet['dono_id'] ?? null;
        $cliente = $donoId ? $this->getRepositorio(Cliente::class)->find($donoId) : null;
        $clienteNome = $cliente ? $cliente->getNome() : ($pet['dono_nome'] ?? 'Não informado');

        $this->restauraLoginDB();
        $clinica = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($baseId);

        $this->switchDB();
        // Busca o veterinário
        $vet = $this->getRepositorio(Veterinario::class)->findOneBy(['estabelecimentoId' => $baseId]);
        if (!$vet) {
            // Tratar caso em que o veterinário não é encontrado
            throw $this->createNotFoundException('Veterinário não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $conteudoDelta = $request->get('conteudo');
            $resumo = $request->get('resumo');

            $conteudoHtml = $this->quillDeltaToHtml($conteudoDelta);

            $cabecalhoHtml = "
<table width='100%' style='border-collapse:collapse;'>
  <tr>
    <td style='padding:0 0 10px 0; border-bottom:2.5px solid #5d57f4;'>
      <table width='100%' style='border-collapse:collapse;'>
        <tr>
          <td style='width:55px; vertical-align:middle; padding-right:10px;'>
            <table style='border-collapse:collapse;'>
              <tr>
                <td style='width:44px; height:44px; border:2px solid #5d57f4; border-radius:50%; text-align:center; vertical-align:middle; font-size:18px; font-weight:bold; color:#5d57f4;'>+</td>
              </tr>
            </table>
          </td>
          <td style='vertical-align:middle; text-align:left;'>
            <span style='font-size:16px; font-weight:bold; color:#0F172A;'>" . ($clinica->getRazaoSocial() ?? 'Clínica Veterinária') . "</span><br>
            <span style='font-size:10px; color:#475569;'>CNPJ: " . ($clinica->getCnpj() ?? '') . "</span>
          </td>
          <td style='vertical-align:middle; text-align:right; font-size:10px; color:#475569; line-height:1.6;'>
            {$clinica->getRua()}, {$clinica->getNumero()} - {$clinica->getBairro()}<br>
            {$clinica->getCidade()} - CEP: {$clinica->getCep()}
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<table width='100%' style='border-collapse:collapse; margin-top:14px;'>
  <tr>
    <td style='text-align:center; padding-bottom:10px;'>
      <span style='font-size:11px; font-weight:bold; letter-spacing:2.5px; color:#5d57f4;'>RECEITUÁRIO VETERINÁRIO</span>
    </td>
  </tr>
</table>

<table width='100%' style='border-collapse:collapse; background-color:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px;'>
  <tr>
    <td style='padding:10px 14px; font-size:11px; color:#0F172A; line-height:1.8;'>
      <strong style='color:#475569;'>TUTOR:</strong> {$clienteNome} &nbsp;&nbsp;|&nbsp;&nbsp;
      <strong style='color:#475569;'>PET:</strong> {$pet['nome']} ({$pet['especie']} - {$pet['raca']}, {$pet['idade']} anos) &nbsp;&nbsp;|&nbsp;&nbsp;
      <strong style='color:#475569;'>SEXO:</strong> {$pet['sexo']}
    </td>
  </tr>
</table>
";

// --- Rodapé HTML fixo (assinatura do veterinário + emissão) ---
            $rodapeHtml = "
<table width='100%' style='border-collapse:collapse; margin-top:6px;'>
  <tr>
    <td style='border-top:1px solid #E2E8F0; padding-top:18px; text-align:center;'>
      <table style='margin:0 auto; border-collapse:collapse;'>
        <tr>
          <td style='border-top:1px solid #0F172A; padding-top:6px; text-align:center; min-width:240px;'>
            <span style='font-size:9px; text-transform:uppercase; letter-spacing:1px; color:#94A3B8;'>Assinatura do Veterinário</span><br>
            <span style='font-size:12px; font-weight:bold; color:#0F172A;'>{$vet->getNome()}</span><br>
            <span style='font-size:10.5px; color:#475569;'>CRMV: {$vet->getCrmv()}</span>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style='text-align:center; padding-top:14px;'>
      <span style='font-size:8.5px; color:#94A3B8;'>Documento emitido em: " . date('d/m/Y H:i:s') . "</span>
    </td>
  </tr>
  <tr>
    <td style='text-align:center; padding-top:4px;'>
      <span style='font-size:8px; color:#cbd5e1;'>System Home Pet — Seu CRM para clínicas e pet shops</span>
    </td>
  </tr>
</table>
";

            $receita = new \App\Entity\Receita();
            $receita->setEstabelecimentoId($baseId);
            $receita->setPetId($petId);
            $receita->setData(new \DateTime());
            $receita->setCabecalho($cabecalhoHtml);
            $receita->setConteudo($conteudoDelta);
            $receita->setRodape($rodapeHtml); // <-- Agora salva o HTML fixo, não o delta do formulário
            $receita->setResumo($resumo);

            $this->getRepositorio(\App\Entity\Receita::class)->salvar($receita);

            $gerarPDF = new \App\Service\GeradorpdfService($this->tempDirManager, $this->requestStack);
            $gerarPDF->configuracaoPagina('A4', 10, 10, 50, 6, 10, 3);
            $gerarPDF->setNomeArquivo('Receita_' . $pet['nome'] . '_' . date('YmdHis'));
            $gerarPDF->setRodape($rodapeHtml); // Usa o novo rodapé fixo
            $gerarPDF->montaCabecalhoPadrao($cabecalhoHtml);
            $gerarPDF->addPagina('P');
            $gerarPDF->conteudo($conteudoHtml);

            $this->addFlash('success', 'Receita registrada e PDF gerado com sucesso!');
            return $gerarPDF->gerar();
        }

        return $this->render('clinica/detalhes_pet.html.twig', [
            'pet' => $pet,
            'clinica' => $clinica,
            'veterinario' => $vet,
        ]);
    }

    /**
     * @Route("/pet/{petId}/peso/novo", name="clinica_novo_peso", methods={"GET", "POST"})
     */
    public function novoPeso(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);

        if ($request->isMethod('POST')) {
            // Lógica para salvar o novo registro de peso
            $this->addFlash('success', 'Peso registrado com sucesso!');
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
        }

        return $this->render('clinica/novo_peso.html.twig', ['pet' => $pet]);
    }

    /**
     * @Route("/pet/{petId}/documento/novo", name="clinica_novo_documento_pet", methods={"GET"})
     */
    public function novoDocumentoPet(int $petId): Response
    {
        // Redireciona para a tela de documentos, ou implementa uma lógica específica aqui
        return $this->redirectToRoute('clinica_documentos', ['petId' => $petId]);

    }

    /**
     * @Route("/pet/{petId}/exame/novo", name="clinica_novo_exame", methods={"GET", "POST"})
     */
    public function novoExame(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        // Lógica para exames
        return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Exame']);
    }
    /**
     * @Route("/consulta/nova", name="clinica_nova_consulta", methods={"GET", "POST"})
     */
    public function novaConsulta(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $clientes = $this->getRepositorio(Cliente::class)->localizaTodosCliente($baseId);

        $petNome = $request->query->get('pet_nome');
        $dataFiltro = $request->query->get('data') ?: (new \DateTime())->format('Y-m-d');

        $consultas = $this->getRepositorio(Consulta::class)->listarConsultasDoDia($baseId, new \DateTime($dataFiltro), $petNome);

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
     * @Route("/consulta/{id}", name="clinica_ver_consulta", methods={"GET"})
     */
    public function verConsulta(int $id, ConsultaRepository $consultaRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Você precisará de um método no seu repositório para buscar uma única consulta com todos os detalhes
        $consulta = $consultaRepo->findConsultaCompletaById($baseId, $id);

        if (!$consulta) {
            throw $this->createNotFoundException('Atendimento não encontrado.');
        }

        return $this->render('clinica/ver_consulta.html.twig', [
            'consulta' => $consulta,
        ]);
    }
}
