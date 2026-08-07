<?php

namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\Pet;
use App\Entity\Veterinario;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica")
 */
class FichaController extends DefaultController
{
    /** Extensões permitidas para o arquivo de encaminhamento */
    private const ANEXO_EXTENSOES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    /**
     * @Route("/pet/{petId}/atendimento/novo", name="clinica_novo_atendimento", methods={"POST"})
     */
    public function novoAtendimento(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $isAjax = $request->isXmlHttpRequest();

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            if ($isAjax) {
                return $this->json(['status' => 'error', 'mensagem' => 'Pet não encontrado.'], 404);
            }
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        try {
        $consulta = new Consulta();
        $consulta->setEstabelecimentoId($baseId);
        $consulta->setClienteId((int) $request->get('cliente_id'));
        $consulta->setPetId($petId);
        $consulta->setData(new \DateTime($request->get('data')));
        $consulta->setHora(new \DateTime($request->get('hora')));
        $veterinarioId = $request->get('veterinario');
        $consulta->setVeterinarioId($veterinarioId !== null && $veterinarioId !== '' ? (int) $veterinarioId : null);
        $consulta->setObservacoes($request->get('observacoes'));

        $consulta->setAnamnese($request->get('anamnese_delta'));

        $consulta->setTipo($request->get('tipo'));
        $consulta->setStatus('atendido');
        $consulta->setCriadoEm(new \DateTime());

        // --- Upload do arquivo de encaminhamento (opcional) ---
        $arquivo = $request->files->get('encaminhamento_arquivo');
        if ($arquivo) {
            $extensao = strtolower($arquivo->getClientOriginalExtension() ?: $arquivo->guessExtension() ?: '');

            if (!in_array($extensao, self::ANEXO_EXTENSOES_PERMITIDAS, true)) {
                $msg = 'Formato de arquivo não permitido. Use: ' . implode(', ', self::ANEXO_EXTENSOES_PERMITIDAS) . '.';
                if ($isAjax) {
                    return $this->json(['status' => 'error', 'mensagem' => $msg], 400);
                }
                $this->addFlash('error', $msg);
                return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
            }

            $diretorio = $this->getParameter('encaminhamentos_directory');
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0775, true);
            }

            // Nome randômico de 12 dígitos, garantindo que não exista arquivo igual no diretório
            do {
                $nomeArquivo = (string) random_int(100000000000, 999999999999) . '.' . $extensao;
            } while (file_exists($diretorio . DIRECTORY_SEPARATOR . $nomeArquivo));

            try {
                $arquivo->move($diretorio, $nomeArquivo);
            } catch (\Exception $e) {
                $msg = 'Falha ao gravar o arquivo no servidor. Tente novamente.';
                if ($isAjax) {
                    return $this->json(['status' => 'error', 'mensagem' => $msg], 500);
                }
                $this->addFlash('error', $msg);
                return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
            }

            $consulta->setAttachment($nomeArquivo);
            $consulta->setAttachmentOriginal($arquivo->getClientOriginalName());
        }

        $consultaId = $this->getRepositorio(Consulta::class)->salvarConsulta($baseId, $consulta);

        // Atendimento para mais de um pet do mesmo tutor (pets adicionais).
        $petsAdicionais = array_filter(
            array_map('intval', (array) $request->get('pets_adicionais', [])),
            fn($id) => $id > 0 && $id !== $petId
        );
        if (!empty($petsAdicionais)) {
            $this->getRepositorio(Consulta::class)
                ->salvarPetsAdicionais($baseId, $consultaId, (int) $baseId, $petsAdicionais);
        }

        if ($isAjax) {
            return $this->json([
                'status' => 'success',
                'mensagem' => 'Atendimento salvo com sucesso!',
                'redirect' => $this->generateUrl('clinica_detalhes_pet', ['id' => $petId]),
            ]);
        }

        $this->addFlash('success', 'Atendimento salvo com sucesso!');
        return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);

        } catch (\Throwable $e) {
            // Converte qualquer erro inesperado em resposta legível (JSON no AJAX),
            // evitando o genérico "Falha de comunicação com o servidor".
            $msg = $e->getMessage();
            if (stripos($msg, "doesn't exist") !== false
                || stripos($msg, 'base table or view not found') !== false) {
                $msg = 'A tabela consulta_pet não existe neste banco. Rode o SQL de criação (add_consulta_pet.sql) ou desmarque os pets adicionais.';
            }
            if ($isAjax) {
                return $this->json(['status' => 'error', 'mensagem' => 'Erro ao salvar o atendimento: ' . $msg], 500);
            }
            $this->addFlash('error', 'Erro ao salvar o atendimento: ' . $msg);
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
        }
    }

    /**
     * Download do arquivo de encaminhamento anexado a um atendimento (timeline).
     *
     * @Route("/consulta/{id}/anexo", name="clinica_consulta_anexo", methods={"GET"})
     */
    public function baixarAnexoConsulta(int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $anexo = $this->getRepositorio(Consulta::class)->findAnexoConsulta($baseId, $id);
        if (!$anexo) {
            throw $this->createNotFoundException('Nenhum anexo encontrado para este atendimento.');
        }

        // basename() impede path traversal caso o valor no banco seja adulterado
        $nomeServidor = basename($anexo['attachment']);
        $caminho = $this->getParameter('encaminhamentos_directory') . DIRECTORY_SEPARATOR . $nomeServidor;

        if (!is_file($caminho)) {
            throw $this->createNotFoundException('Arquivo não localizado no servidor.');
        }

        $response = new BinaryFileResponse($caminho);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $anexo['attachment_original'] ?: $nomeServidor
        );

        return $response;
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

        // Veterinário responsável pela receita:
        // 1) se o usuário escolher explicitamente no formulário (permite trocar o responsável);
        // 2) senão, usa o veterinário do último atendimento (consulta ativa) do pet;
        // 3) fallback: primeiro veterinário do estabelecimento.
        $vetRepo = $this->getRepositorio(Veterinario::class);
        $vetIdSelecionado = $request->get('veterinario_id');

        $vet = null;
        if ($vetIdSelecionado) {
            $vet = $vetRepo->find((int) $vetIdSelecionado);
        }

        // Se nada foi escolhido, usa o veterinário vinculado ao usuário logado (sessão)
        if (!$vet) {
            $user = $this->getUser();
            if ($user && method_exists($user, 'getVeterinarioId') && $user->getVeterinarioId()) {
                $vet = $vetRepo->find((int) $user->getVeterinarioId());
            }
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
            // Tratar caso em que o veterinário não é encontrado
            throw $this->createNotFoundException('Veterinário não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $conteudoDelta = $request->get('conteudo');
            $resumo = $request->get('resumo');
            $rodapeCustom = trim((string) $request->get('rodape_custom'));

            $conteudoHtml = $this->quillDeltaToHtml($conteudoDelta);

            // --- Dados do pet e do tutor (formatados e escapados) ---
            $esc = function ($v) { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES); };
            $ou = function ($v) { $v = trim((string) ($v ?? '')); return $v !== '' ? $v : '—'; };

            $petNome     = $esc($ou($pet['nome'] ?? ''));
            $petEspecie  = $esc($ou($pet['especie'] ?? ''));
            $petRaca     = $esc($ou($pet['raca'] ?? ''));
            $petSexo     = $esc($ou($pet['sexo'] ?? ''));
            $petPorte    = $esc($ou($pet['porte'] ?? ''));
            $idadeRaw    = trim((string) ($pet['idade'] ?? ''));
            $petIdade    = $esc($idadeRaw !== '' ? ($idadeRaw . (is_numeric($idadeRaw) ? ' ano(s)' : '')) : '—');
            $pesoRaw     = trim((string) ($pet['peso'] ?? ''));
            $petPeso     = $esc($pesoRaw !== '' ? ($pesoRaw . ' kg') : '—');
            $castradoRaw = $pet['castrado'] ?? null;
            $petCastrado = ($castradoRaw === null || $castradoRaw === '') ? '—' : (((int) $castradoRaw === 1) ? 'Sim' : 'Não');
            $petNasc     = !empty($pet['dataNascimento']) ? date('d/m/Y', strtotime($pet['dataNascimento'])) : '—';

            $tutorNome     = $esc($ou($clienteNome));
            $tutorCpf      = $esc($ou($cliente ? $cliente->getCpf() : ''));
            $telefoneRaw   = $cliente ? ($cliente->getTelefone() ?: '') : ($pet['dono_telefone'] ?? '');
            $whatsRaw      = $cliente ? ($cliente->getWhatsapp() ?: '') : '';
            $tutorTel      = $esc($ou($telefoneRaw));
            $tutorWhats    = $esc($ou($whatsRaw));
            $tutorEmail    = $esc($ou($cliente ? $cliente->getEmail() : ($pet['dono_email'] ?? '')));
            $tutorEndereco = $esc($ou(trim((string) ($pet['dono_endereco'] ?? ''), " ,-")));

            $lblStyle  = "font-size:8px; text-transform:uppercase; letter-spacing:0.5px; color:#94A3B8;";
            $valStyle  = "font-size:10.5px; color:#0F172A;";

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

<table width='100%' style='border-collapse:collapse; margin-top:12px;'>
  <tr>
    <td style='text-align:center; padding-bottom:8px;'>
      <span style='font-size:11px; font-weight:bold; letter-spacing:2.5px; color:#5d57f4;'>RECEITUÁRIO VETERINÁRIO</span>
    </td>
  </tr>
</table>

<table width='100%' style='border-collapse:collapse; background-color:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px;'>
  <tr>
    <td style='width:50%; vertical-align:top; padding:10px 14px; border-right:1px solid #E2E8F0;'>
      <div style='font-size:9px; font-weight:bold; letter-spacing:1px; color:#5d57f4; padding-bottom:5px;'>TUTOR</div>
      <div style='padding-bottom:3px;'><span style='{$lblStyle}'>Nome</span> <span style='{$valStyle}'>{$tutorNome}</span> &nbsp; <span style='{$lblStyle}'>CPF</span> <span style='{$valStyle}'>{$tutorCpf}</span></div>
      <div style='padding-bottom:3px;'><span style='{$lblStyle}'>Telefone</span> <span style='{$valStyle}'>{$tutorTel}</span> &nbsp; <span style='{$lblStyle}'>WhatsApp</span> <span style='{$valStyle}'>{$tutorWhats}</span></div>
      <div style='padding-bottom:3px;'><span style='{$lblStyle}'>E-mail</span> <span style='{$valStyle}'>{$tutorEmail}</span></div>
      <div><span style='{$lblStyle}'>Endereço</span> <span style='{$valStyle}'>{$tutorEndereco}</span></div>
    </td>
    <td style='width:50%; vertical-align:top; padding:10px 14px;'>
      <div style='font-size:9px; font-weight:bold; letter-spacing:1px; color:#5d57f4; padding-bottom:5px;'>PACIENTE</div>
      <div style='padding-bottom:4px;'><span style='{$lblStyle}'>Nome</span> <span style='{$valStyle}'>{$petNome}</span> &nbsp; <span style='{$lblStyle}'>Espécie</span> <span style='{$valStyle}'>{$petEspecie}</span></div>
      <div style='padding-bottom:4px;'><span style='{$lblStyle}'>Raça</span> <span style='{$valStyle}'>{$petRaca}</span> &nbsp; <span style='{$lblStyle}'>Sexo</span> <span style='{$valStyle}'>{$petSexo}</span> &nbsp; <span style='{$lblStyle}'>Porte</span> <span style='{$valStyle}'>{$petPorte}</span></div>
      <div><span style='{$lblStyle}'>Idade</span> <span style='{$valStyle}'>{$petIdade}</span> &nbsp; <span style='{$lblStyle}'>Peso</span> <span style='{$valStyle}'>{$petPeso}</span> &nbsp; <span style='{$lblStyle}'>Castrado</span> <span style='{$valStyle}'>{$petCastrado}</span> &nbsp; <span style='{$lblStyle}'>Nasc.</span> <span style='{$valStyle}'>{$petNasc}</span></div>
    </td>
  </tr>
</table>
";

// --- Rodapé HTML fixo (assinatura do veterinário + emissão) ---
            $rodapeCustomHtml = $rodapeCustom !== ''
                ? "<tr><td style='text-align:center; padding-top:6px; font-size:9px; color:#475569; line-height:1.4;'>" . nl2br(htmlspecialchars($rodapeCustom, ENT_QUOTES)) . "</td></tr>"
                : "";

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
  {$rodapeCustomHtml}
  <tr>
    <td style='text-align:center; padding-top:6px;'>
      <span style='font-size:8px; color:#94A3B8;'>Documento emitido em: " . date('d/m/Y H:i:s') . "</span>
      <span style='font-size:7.5px; color:#cbd5e1;'> &nbsp;·&nbsp; System Home Pet — Seu CRM para clínicas e pet shops</span>
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
        // Usa o ID do tenant conectado (mesmo do switchDB), e não o userId,
        // para as queries apontarem para o banco correto (homepet_<tenant>).
        $baseId = $this->getIdBase();

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

            $consultaId = $this->getRepositorio(Consulta::class)->salvarConsulta($baseId, $consulta);

            // Atendimento para mais de um pet do mesmo tutor: salva os pets
            // adicionais (o pet principal já está na própria consulta).
            $petsAdicionais = array_filter(
                array_map('intval', (array) $request->get('pets_adicionais', [])),
                fn($id) => $id > 0 && $id !== (int) $request->get('pet_id')
            );

            if (!empty($petsAdicionais)) {
                $this->getRepositorio(Consulta::class)
                    ->salvarPetsAdicionais($baseId, $consultaId, (int) $baseId, $petsAdicionais);
                $this->addFlash('success', 'Atendimento marcado para ' . (count($petsAdicionais) + 1) . ' pets do mesmo tutor!');
            } else {
                $this->addFlash('success', 'Consulta marcada com sucesso!');
            }

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
    public function verConsulta(int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $consultaRepo = $this->getRepositorio(Consulta::class);

        // Você precisará de um método no seu repositório para buscar uma única consulta com todos os detalhes
        $consulta = $consultaRepo->findConsultaCompletaById($baseId, $id);

        if (!$consulta) {
            throw $this->createNotFoundException('Atendimento não encontrado.');
        }

        return $this->render('clinica/ver_consulta.html.twig', [
            'consulta' => $consulta,
        ]);
    }

    /**
     * Gera PDF completo da ficha do pet com todas as consultas
     * 
     * @Route("/pet/{petId}/ficha/pdf", name="clinica_ficha_pdf", methods={"GET"})
     */
    public function gerarFichaPdf(Request $request, \App\Service\FichaPdfService $fichaPdfService, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        // A tabela `estabelecimento` fica no banco de login, não no do tenant.
        // Mesmo vai-e-vem usado na geração de receita.
        $this->restauraLoginDB();
        $clinica = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($baseId);
        $this->switchDB();

        try {
            /** @var \App\Service\FichaPdfService $fichaPdfService */
            // $fichaPdfService = $this->container->get('App\Service\FichaPdfService');
            return $fichaPdfService->gerarFichaPet($baseId, $petId, $this->getDoctrine()->getManager(), $clinica);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erro ao gerar PDF: ' . $e->getMessage());
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
        }
    }
}
