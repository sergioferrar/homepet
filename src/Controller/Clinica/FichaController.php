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
 */
class FichaController extends DefaultController
{
    /** Extensões permitidas para o arquivo de encaminhamento */
    private const ANEXO_EXTENSOES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    /**
     */
    #[Route('dashboard/clinica/pet/{petId}/atendimento/novo', name: 'clinica_novo_atendimento')]
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

            // Data e hora do atendimento: se não vierem preenchidas, usa o momento atual.
            // Permite lançar atendimento retroativo (ex.: registro feito depois do fato).
            $dataInformada = trim((string) $request->get('data'));
            $horaInformada = trim((string) $request->get('hora'));
            $consulta->setData($dataInformada !== '' ? new \DateTime($dataInformada) : new \DateTime());
            $consulta->setHora($horaInformada !== '' ? new \DateTime($horaInformada) : new \DateTime());

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
                } catch (\Exception) {
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
                array_map(intval(...), (array) $request->get('pets_adicionais', [])),
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
     */
    #[Route('dashboard/clinica/consulta/{id}/anexo', name: 'clinica_consulta_anexo')]
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
     */
    #[Route('dashboard/clinica/pet/{petId}/receita', name: 'clinica_nova_receita')]
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
            $esc = (fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES));
            $ou = function ($v) {$v = trim((string) ($v ?? ''));return $v !== '' ? $v : '—';};

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
            $telefoneRaw = $cliente ? ($cliente->getTelefone() ?: ''): ($pet['dono_telefone'] ?? '');
            $whatsRaw = $cliente ? ($cliente->getWhatsapp() ?: ''): '';
            $tutorTel = $esc($ou($telefoneRaw));
            $tutorWhats = $esc($ou($whatsRaw));
            $tutorEmail = $esc($ou($cliente ? $cliente->getEmail() : ($pet['dono_email'] ?? '')));
            $tutorEndereco = $esc($ou(trim((string) ($pet['dono_endereco'] ?? ''), " ,-")));

            $lblStyle = "font-size:8px; text-transform:uppercase; letter-spacing:0.5px; color:#94A3B8;";
            $valStyle = "font-size:10.5px; color:#0F172A;";

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
            {$clinica->getRua()}, {$clinica->getNumero()} - {$clinica->getBairro()}<br>
            {$clinica->getCidade()} - CEP: {$clinica->getCep()}
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<table width='100%' style='border-collapse:collapse; margin-top:8px;'>
  <tr>
    <td style='text-align:center; padding-bottom:5px;'>
      <span style='font-size:11px; font-weight:bold; letter-spacing:2.5px; color:#5d57f4;'>RECEITUÁRIO VETERINÁRIO</span>
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
            // Margens: o cabeçalho da receita (logo + título + caixa TUTOR/PACIENTE)
            // ocupa ~45mm. Com tMargin 50 e margin_header 10 sobravam 40mm e o
            // conteúdo invadia o cabeçalho. tMargin 64 dá 56mm, com folga para o
            // endereço do tutor quebrar em duas linhas.
            $gerarPDF->configuracaoPagina('A4', 10, 10, 64, 26, 8, 8);
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
     */
    #[Route('dashboard/clinica/pet/{petId}/peso/novo', name: 'clinica_novo_peso')]
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
     */
    #[Route('dashboard/clinica/pet/{petId}/documento/novo', name: 'clinica_novo_documento_pet')]
    public function novoDocumentoPet(int $petId): Response
    {
        // Redireciona para a tela de documentos, ou implementa uma lógica específica aqui
        return $this->redirectToRoute('clinica_documentos', ['petId' => $petId]);

    }
    /**
     */
    #[Route('dashboard/clinica/pet/{petId}/exame/novo', name: 'clinica_novo_exame')]
    public function novoExame(int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        // Lógica para exames
        return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Exame']);
    }
    /**
     */
    #[Route('dashboard/clinica/consulta/nova', name: 'clinica_nova_consulta')]
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
                array_map(intval(...), (array) $request->get('pets_adicionais', [])),
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
     */
    #[Route('dashboard/clinica/consulta/{id}/status/{status}', name: 'clinica_consulta_status')]
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
     */
    #[Route('dashboard/clinica/consulta/{id}', name: 'clinica_ver_consulta')]
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
     * Tela de edição de um atendimento — permite corrigir data, hora, tipo,
     * status, veterinário, valor, observações, anamnese e o anexo.
     *
     */
    #[Route('dashboard/clinica/consulta/{id}/editar', name: 'clinica_editar_consulta')]
    public function editarConsulta(int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $consulta = $this->getRepositorio(Consulta::class)->findConsultaCompletaById($baseId, $id);
        if (!$consulta) {
            throw $this->createNotFoundException('Atendimento não encontrado.');
        }

        return $this->render('clinica/editar_consulta.html.twig', [
            'consulta' => $consulta,
            'veterinarios' => $this->getRepositorio(Veterinario::class)->findAll(),
        ]);
    }
    /**
     * Grava as alterações feitas na tela de edição do atendimento.
     *
     */
    #[Route('dashboard/clinica/consulta/{id}/editar', name: 'clinica_editar_consulta_salvar')]
    public function salvarEdicaoConsulta(Request $request, int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $consultaRepo = $this->getRepositorio(Consulta::class);
        $atual = $consultaRepo->findConsultaCompletaById($baseId, $id);
        if (!$atual) {
            throw $this->createNotFoundException('Atendimento não encontrado.');
        }

        $petId = (int) $atual['pet_id'];

        try {
            $consulta = new Consulta();
            $consulta->setEstabelecimentoId($baseId);

            // Data e hora: em branco mantém o valor que já estava gravado.
            $dataInformada = trim((string) $request->get('data'));
            $horaInformada = trim((string) $request->get('hora'));
            $consulta->setData(new \DateTime($dataInformada !== '' ? $dataInformada : $atual['data']));
            $consulta->setHora(new \DateTime($horaInformada !== '' ? $horaInformada : ($atual['hora'] ?? '00:00:00')));

            // Data de cadastro (criado_em): editável, pois em registros antigos
            // ela pode ter ficado incorreta.
            $criadoInformado = trim((string) $request->get('criado_em'));
            $consulta->setCriadoEm(new \DateTime(
                $criadoInformado !== '' ? str_replace('T', ' ', $criadoInformado) : ($atual['criado_em'] ?: 'now')
            ));

            $consulta->setTipo($request->get('tipo'));
            $consulta->setStatus($request->get('status') ?: 'atendido');

            $vetId = $request->get('veterinario');
            $consulta->setVeterinarioId($vetId !== null && $vetId !== '' ? (int) $vetId : null);

            $consulta->setObservacoes($request->get('observacoes'));
            $consulta->setAnamnese($request->get('anamnese_delta'));

            $valor = trim((string) $request->get('valor'));
            $consulta->setValor($valor !== '' ? (float) str_replace(',', '.', $valor) : null);

            // --- Anexo ---
            // Só mexe no anexo se o usuário enviar um arquivo novo ou marcar para remover.
            $alterarAnexo = false;
            $arquivo = $request->files->get('encaminhamento_arquivo');

            if ($request->get('remover_anexo')) {
                $consulta->setAttachment(null);
                $consulta->setAttachmentOriginal(null);
                $alterarAnexo = true;
            } elseif ($arquivo) {
                $extensao = strtolower($arquivo->getClientOriginalExtension() ?: $arquivo->guessExtension() ?: '');

                if (!in_array($extensao, self::ANEXO_EXTENSOES_PERMITIDAS, true)) {
                    $this->addFlash('error', 'Formato de arquivo não permitido. Use: ' . implode(', ', self::ANEXO_EXTENSOES_PERMITIDAS) . '.');
                    return $this->redirectToRoute('clinica_editar_consulta', ['id' => $id]);
                }

                $diretorio = $this->getParameter('encaminhamentos_directory');
                if (!is_dir($diretorio)) {
                    mkdir($diretorio, 0775, true);
                }

                do {
                    $nomeArquivo = (string) random_int(100000000000, 999999999999) . '.' . $extensao;
                } while (file_exists($diretorio . DIRECTORY_SEPARATOR . $nomeArquivo));

                $arquivo->move($diretorio, $nomeArquivo);

                $consulta->setAttachment($nomeArquivo);
                $consulta->setAttachmentOriginal($arquivo->getClientOriginalName());
                $alterarAnexo = true;
            }

            $consultaRepo->atualizarConsulta($baseId, $id, $consulta, $alterarAnexo);

            $this->addFlash('success', 'Atendimento atualizado com sucesso!');
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);

        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erro ao atualizar o atendimento: ' . $e->getMessage());
            return $this->redirectToRoute('clinica_editar_consulta', ['id' => $id]);
        }
    }
    /**
     * Gera o PDF de uma receita já emitida, a partir do cabeçalho, conteúdo e
     * rodapé gravados no momento da emissão.
     *
     * Substitui a impressão via window.print() sobre o HTML do modal: aquele
     * caminho não respeitava as margens do papel timbrado e sobrepunha o
     * conteúdo ao cabeçalho. Aqui o mPDF trata cabeçalho e rodapé como camadas
     * próprias, com as mesmas margens usadas na emissão.
     *
     * O PDF abre inline na aba; imprimir ou salvar fica a cargo do
     * visualizador do navegador.
     *
     */
    #[Route('dashboard/clinica/receita/{id}/pdf', name: 'clinica_receita_pdf')]
    public function gerarReceitaPdf(int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $receita = $this->getRepositorio(\App\Entity\Receita::class)->findById($baseId, $id);
        if (!$receita) {
            throw $this->createNotFoundException('Receita não encontrada.');
        }

        // O conteúdo é o Delta do Quill; cabeçalho e rodapé já foram gravados
        // como HTML pronto na emissão — não precisam ser remontados.
        $conteudoHtml = $this->quillDeltaToHtml($receita['conteudo'] ?? null);
        if (trim(strip_tags($conteudoHtml)) === '') {
            $conteudoHtml = '<p style="color:#666;"><em>Receita sem conteúdo registrado.</em></p>';
        }

        $dataReceita = !empty($receita['data']) ? strtotime($receita['data']) : false;
        $sufixo = $dataReceita ? date('Ymd', $dataReceita) : date('Ymd');
        $nomePet = preg_replace('/[^a-zA-Z0-9]/', '_', (string) ($receita['pet_nome'] ?? 'Pet'));

        $gerarPDF = new \App\Service\GeradorpdfService($this->tempDirManager, $this->requestStack);

        // Mesmas margens da emissão: 64-8 = 56mm para o cabeçalho timbrado.
        $gerarPDF->configuracaoPagina('A4', 10, 10, 64, 26, 8, 8);
        $gerarPDF->setNomeArquivo('Receita_' . $nomePet . '_' . $sufixo . '.pdf');

        if (!empty($receita['rodape'])) {
            $gerarPDF->setRodape($receita['rodape']);
        }
        if (!empty($receita['cabecalho'])) {
            $gerarPDF->montaCabecalhoPadrao($receita['cabecalho']);
        }

        $gerarPDF->addPagina('P');
        $gerarPDF->conteudo($conteudoHtml);

        return $gerarPDF->gerarInline();
    }
    /**
     * Gera PDF completo da ficha do pet com todas as consultas
     *
     */
    #[Route('dashboard/clinica/pet/{petId}/ficha/pdf', name: 'clinica_ficha_pdf')]
    public function gerarFichaPdf(\App\Service\FichaPdfService $fichaPdfService, int $petId): Response
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
