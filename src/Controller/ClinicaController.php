<?php

namespace App\Controller;

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


/**
 * @Route("/clinica")
 */
class ClinicaController extends DefaultController
{
    /**
     * @Route("/dashboard", name="clinica_dashboard", methods={"GET"})
     */
    public function dashboard(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repoPet = $this->getRepositorio(\App\Entity\Pet::class);
        $repoCliente = $this->getRepositorio(\App\Entity\Cliente::class);
        $repoFinanceiro = $this->getRepositorio(\App\Entity\FinanceiroPendente::class);
        $repoConsulta = $this->getRepositorio(\App\Entity\Consulta::class);

        $repoInternacao = new InternacaoRepository($this->managerRegistry);
        $internacoes = $repoInternacao->listarInternacoesAtivas($baseId);
        $totalPets = $repoPet->countTotalPets($baseId);
        $totalDono = $repoCliente->countTotalDono($baseId);
        $debitosCliente = $repoFinanceiro->somarDebitosPendentes($baseId);
        $media = $repoConsulta->calcularMediaConsultas($baseId);
        $atendimentos = $repoConsulta->listarUltimosAtendimentos($baseId);
        $animaisCadastrados = method_exists($repoPet, 'listarPetsRecentes')
            ? $repoPet->listarPetsRecentes($baseId, 5)
            : [];

        $vacinasVencidas = method_exists($repoPet, 'listarVacinasPendentes')
            ? $repoPet->listarVacinasPendentes($baseId)
            : [];
        $vacinasProgramadas = method_exists($repoPet, 'listarVacinasProgramadas')
            ? $repoPet->listarVacinasProgramadas($baseId)
            : [];

        // 🔍 pesquisa
        $termo = $request->query->get('q');
        $pets = [];
        if ($termo) {
            $pets = $repoPet->pesquisarPetsOuTutor($baseId, $termo);
        }

        return $this->render('clinica/dashboard.html.twig', [
            'total_pets' => $totalPets,
            'debitos_cliente' => $debitosCliente,
            'media_atendimento' => $media,
            'atendimentos' => $atendimentos,
            'internados' => $internacoes,
            'vacinas_programadas' => $vacinasProgramadas,
            'vacinas_vencidas' => $vacinasVencidas,
            'totaldono' => $totalDono,
            'animais_cadastrados' => $animaisCadastrados,
            'pets' => $pets,
            'termo' => $termo,
        ]);
    }

    /**
     * @Route("/pet/{id}", name="clinica_detalhes_pet", methods={"GET"})
     */
    public function detalhesPet(
        int                              $id,
        ConsultaRepository               $consultaRepo,
        DocumentoModeloRepository        $documentoRepo,
        FinanceiroRepository             $financeiroRepo,
        FinanceiroPendenteRepository     $financeiroPendenteRepo,
        InternacaoRepository             $internacaoRepo,
        \App\Repository\VacinaRepository $vacinaRepo
    ): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(\App\Entity\Pet::class)->findPetById($baseId, $id);
        if (!$pet) {
            throw $this->createNotFoundException('O pet não foi encontrado.');
        }

        $consultas = $consultaRepo->findAllByPetId($baseId, $id);
        $documentos = $documentoRepo->listarDocumentos($baseId);

        // 🔹 só pega ativos agora
        $financeiro = $financeiroRepo->buscarAtivosPorPet($baseId, $pet['id']);
        $financeiroPendente = $financeiroPendenteRepo->findAtivosPorPet($baseId, $id);

        // 🔹 lista inativos separados (pra usar em outra aba se quiser)
        $financeiroInativos = $financeiroRepo->findInativos($baseId, $pet['id']);
        $financeiroPendenteInativos = $financeiroPendenteRepo->findInativosPorPet($baseId, $id);

        $receitas = $this->getRepositorio(\App\Entity\Receita::class)->listarPorPet($baseId, $pet['id']);
        $internacaoAtivaId = $internacaoRepo->findAtivaIdByPet($baseId, $pet['id']);
        $ultimaInternacaoId = $internacaoRepo->findUltimaIdByPet($baseId, $pet['id']);
        $internacoesPet = $internacaoRepo->listarInternacoesPorPet($baseId, $pet['id']);

        // --- Vacinas ---
        // $vacinas = $vacinaRepo->listarPorPet($baseId, $petId);
        $vacinas = $vacinaRepo->listarPorPet($baseId, $pet['id']);


        // --- BUSCA TODOS OS SERVIÇOS DA CLÍNICA ---
        $servicosClinica = $this->getRepositorio(\App\Entity\Servico::class)->findBy([
            'estabelecimentoId' => $baseId,
        ]);

        $timeline_items = [];

        foreach ($consultas as $item) {
            
            $anamnese = json_decode($item['anamnese'], true)['ops'];
            $resumo = '';
            foreach($anamnese as $row){
                $resumo .= "{$row['insert']} ";
            }

            $resumo = str_replace("\n", '', nl2br($resumo));

            $timeline_items[] = [
                'data' => new \DateTime($item['data'] . ' ' . $item['hora']),
                'tipo' => $item['tipo'] ?? 'Consulta',
                'observacoes' => $item['observacoes'],
                'resumo' => $resumo,
                'anamnese' => $item['anamnese'] ?? null,
            ];
        }

        foreach ($receitas as $r) {
            $timeline_items[] = [
                'data' => new \DateTime($r['data']),
                'tipo' => 'Receita',
                'resumo' => $r['resumo'],
                'observacoes' => $item['observacoes']??null,
                'receita_cabecalho' => $r['cabecalho'],
                'receita_conteudo' => $r['conteudo'],
                'receita_rodape' => $r['rodape'],
            ];
        }

        foreach ($vacinas as $v) {
            $timeline_items[] = [
                'data' => isset($v['data_aplicacao']) ? new \DateTime($v['data_aplicacao']) : new \DateTime(),
                'tipo' => 'Vacina',
                'resumo' => sprintf(
                    '%s — Lote: %s | Validade: %s',
                    strtoupper($v['tipo'] ?? 'VACINA'),
                    $v['lote'] ?? '—',
                    isset($v['data_validade']) ? (new \DateTime($v['data_validade']))->format('d/m/Y') : '—'
                ),
                'cor' => '#FFD700',
            ];
        }

        // Agrupa por tipo
        // dd($timeline_items);
        $agrupado = [];
        foreach ($timeline_items as $item) {
            $tipo = $item['tipo'];
            if (!isset($agrupado[$tipo])) {
                $agrupado[$tipo] = [];
            }
            $agrupado[$tipo][] = $item;
        }

        //  Total de débitos PENDENTES (só ativos contam aqui)
        $totalDebitos = 0;
        foreach ($financeiroPendente as $itemFinanceiro) {
            $totalDebitos += $itemFinanceiro['valor'];
        }

        $data = [];

        $data['pet'] = $pet;
        $data['timeline_items'] = $timeline_items;
        $data['timeline_agrupado'] = $agrupado;
        $data['documentos'] = $documentos;
        $data['financeiro'] = $financeiro;
        $data['financeiroPendente'] = $financeiroPendente;
        $data['financeiroInativos'] = $financeiroInativos;
        $data['financeiroPendenteInativos'] = $financeiroPendenteInativos;
        $data['consultas'] = $consultas;
        $data['total_debitos'] = $totalDebitos;
        $data['servicos_clinica'] = $servicosClinica;
        $data['internacao_ativa_id'] = $internacaoAtivaId;
        $data['ultima_internacao_id'] = $ultimaInternacaoId;
        $data['internacoes_pet'] = $internacoesPet;
        $data['vacinas'] = $vacinas;
        //dd($data['timeline_items']);

        return $this->render('clinica/detalhes_pet.html.twig', $data);
    }


    /**
     * @Route("/pet/{petId}/internacao/nova", name="clinica_nova_internacao", methods={"GET", "POST"})
     */
    public function novaInternacao(
        Request                $request,
        int                    $petId,
        InternacaoRepository   $internacaoRepo,
        VeterinarioRepository  $veterinarioRepo,
        EntityManagerInterface $entityManager
    ): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        // lista para o formulário
        $veterinarios = $veterinarioRepo->findBy(['estabelecimentoId' => $baseId]);
        $boxes = [
            ['id' => 1, 'nome' => 'Box 1'],
            ['id' => 2, 'nome' => 'Box 2'],
            ['id' => 3, 'nome' => 'Box 3'],
        ];

        // POST: salva e redireciona para a ficha criada
        if ($request->isMethod('POST')) {
            $internacao = new Internacao();

            $donoId = $pet['dono_id'] ?? null;

            $internacao->setPetId($petId);
            $internacao->setDonoId($donoId);
            $internacao->setEstabelecimentoId($baseId);
            $internacao->setDataInicio(new \DateTime());
            $internacao->setStatus('ativa');
            $internacao->setMotivo((string)$request->get('queixa'));

            // Campos adicionais
            $internacao->setSituacao((string)$request->get('situacao'));
            $internacao->setRisco((string)$request->get('risco'));
            $internacao->setVeterinarioId((int)$request->get('veterinario_id'));
            $internacao->setBox((string)$request->get('box'));

            // Alta prevista (opcional)
            $altaPrevistaStr = trim((string)$request->get('alta_prevista'));
            if ($altaPrevistaStr !== '') {
                try {
                    $internacao->setAltaPrevista(new \DateTime($altaPrevistaStr));
                } catch (\Exception $e) {
                    $internacao->setAltaPrevista(null);
                }
            }

            $internacao->setDiagnostico((string)$request->get('diagnostico'));
            $internacao->setPrognostico((string)$request->get('prognostico'));
            $internacao->setAnotacoes((string)$request->get('alergias_marcacoes'));

            // Salva e obtém o ID gerado
            $novoId = $internacaoRepo->inserirInternacao($baseId, $internacao);

            // Cria um evento inicial na timeline da internação
            $internacaoRepo->inserirEvento(
                $baseId,
                $novoId,
                $petId,
                'Internação',
                'Internação iniciada',
                sprintf(
                    'Motivo: %s | Situação: %s | Risco: %s | Box: %s',
                    (string)$internacao->getMotivo(),
                    (string)$internacao->getSituacao(),
                    (string)$internacao->getRisco(),
                    (string)$internacao->getBox()
                ),
                new \DateTime()
            );

            $this->addFlash('success', 'Internação registrada com sucesso!');
            return $this->redirectToRoute('clinica_ficha_internacao', ['id' => $novoId]);
        }

        // GET: exibe o formulário
        return $this->render('clinica/nova_internacao.html.twig', [
            'pet' => $pet,
            'veterinarios' => $veterinarios,
            'boxes' => $boxes,
        ]);
    }

    /**
     * @Route("/pet/{petId}/atendimento/novo", name="clinica_novo_atendimento", methods={"POST"})
     */
    public function novoAtendimento(Request $request, int $petId, ConsultaRepository $consultaRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        $consulta = new Consulta();
        $consulta->setEstabelecimentoId($baseId);
        $consulta->setClienteId((int)$request->get('cliente_id'));
        $consulta->setPetId($petId);
        $consulta->setData(new \DateTime($request->get('data')));
        $consulta->setHora(new \DateTime($request->get('hora')));
        $consulta->setObservacoes($request->get('observacoes'));

        $consulta->setAnamnese($request->get('anamnese_delta'));

        $consulta->setTipo($request->get('tipo'));
        $consulta->setStatus('atendido');
        $consulta->setCriadoEm(new \DateTime());

        $consultaRepo->salvarConsulta($consulta);

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

        $cliente = $this->getRepositorio(Cliente::class)->find($pet['dono_id']);

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
            <div style='text-align:center; font-size:14px; font-weight:bold;'>
            " . ($clinica->getRazaoSocial() ?? 'Clínica Veterinária') . " <br>
            CNPJ: " . ($clinica->getCnpj() ?? '') . " <br>
            {$clinica->getRua()}, {$clinica->getNumero()} - {$clinica->getBairro()}, {$clinica->getCidade()} - CEP: {$clinica->getCep()}
            </div>
            <hr>
            <div style='font-size:12px;'>
            <strong>Tutor:</strong> {$cliente->getNome()} <br>
            <strong>Pet:</strong> {$pet['nome']} ({$pet['especie']} - {$pet['raca']}, {$pet['idade']} anos) <br>
            <strong>Sexo:</strong> {$pet['sexo']}
            </div>
            <hr>
            ";

            // --- NOVO: Cria o rodapé HTML fixo diretamente no PHP ---
            $rodapeHtml = "
            <div style='text-align:center; font-size:12px; margin-top: 20px;'>
            <hr style='border: 1px dashed black; width: 50%; margin: 10px auto;'>
            <p>Assinatura do Veterinário</p>
            <p>
            <strong>{$vet->getNome()}</strong> <br>
            <strong>CRMV:</strong> {$vet->getCrmv()}
            </p>
            </div>
            <div style='text-align:center; font-size:10px; margin-top: 10px;'>
            <span>Documento emitido em: " . date('d/m/Y H:i:s') . "</span>
            </div>
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

    private function quillDeltaToHtml(?string $deltaJson): string
    {
        if (!$deltaJson) {
            return '';
        }

        $delta = json_decode($deltaJson, true);
        if (!$delta || !isset($delta['ops'])) {
            return '';
        }

        $html = '';
        foreach ($delta['ops'] as $op) {
            $insert = $op['insert'] ?? '';
            $attributes = $op['attributes'] ?? [];

            if (isset($attributes['bold'])) {
                $insert = "<strong>{$insert}</strong>";
            }
            if (isset($attributes['italic'])) {
                $insert = "<em>{$insert}</em>";
            }
            if (isset($attributes['underline'])) {
                $insert = "<u>{$insert}</u>";
            }
            if (isset($attributes['header'])) {
                $level = $attributes['header'];
                $insert = "<h{$level}>{$insert}</h{$level}>";
            }
            if (isset($attributes['list'])) {
                $listType = $attributes['list'] === 'ordered' ? 'ol' : 'ul';
                $insert = "<li>{$insert}</li>";
                $html .= "<{$listType}>{$insert}</{$listType}>";
                continue;
            }
            $html .= nl2br($insert);
        }

        return $html;
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
     * @Route("/pet/{petId}/fotos/nova", name="clinica_novas_fotos", methods={"GET", "POST"})
     */
    public function novasFotos(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        // Lógica para fotos
        return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Fotos']);
    }

    /**
     * @Route("/pet/{petId}/vacina/nova", name="clinica_nova_vacina", methods={"GET", "POST"})
     */
    // public function novaVacina(Request $request, int $petId): Response
    // {
    //     $this->switchDB();
    //     $baseId = $this->getIdBase();
    //     $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
    //     // Lógica para vacinas
    //     return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Vacina']);
    // }

    // --- ROTAS ORIGINAIS (MANTIDAS E FUNCIONAIS) ---

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
            $consulta->setClienteId((int)$request->get('cliente_id'));
            $consulta->setPetId((int)$request->get('pet_id'));
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

    // /**
    //  * @Route("/receita", name="clinica_receita", methods={"GET"})
    //  */
    // public function receita(): Response
    // {
    //     $this->switchDB();
    //     return $this->render('clinica/receita.html.twig');
    // }

    // /**
    //  * @Route("/receita/pdf", name="clinica_receita_pdf", methods={"POST"})
    //  */
    // public function gerarReceitaPdf(Request $request, PdfService $pdfService): Response
    // {
    //     $this->switchDB();

    //     return $pdfService->gerarPdf(
    //         'clinica/receita_pdf_backend.html.twig',
    //         [
    //             'cabecalho' => $request->get('cabecalho', ''),
    //             'conteudo'  => $request->get('conteudo', ''),
    //             'rodape'    => $request->get('rodape', '')
    //         ],
    //         'receita-medica.pdf'
    //     );
    // }

    /**
     * @Route("/clinica/pet/{petId}/documentos", name="clinica_documentos", methods={"GET","POST"})
     */
    public function documentos(
        int                       $petId,
        Request                   $request,
        DocumentoModeloRepository $repoDoc,
        PetRepository             $petRepo
    ): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

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
        $baseId = $this->session->get('userId');

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


    /**
     * @Route("/api/pets/{clienteId}", name="clinica_api_pets", methods={"GET"})
     */
    public function apiPetsPorCliente(int $clienteId): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $pets = $this->getRepositorio(Pet::class)->buscarPetsPorCliente($baseId, $clienteId);

        return $this->json(array_map(fn($pet) => ['id' => $pet['id'], 'nome' => $pet['nome']], $pets));
    }

    /**
     * @Route("/financeiro", name="financeiro_dashboard", methods={"GET"})
     */
    public function financeirodash(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repoFinanceiro = new FinanceiroRepository($this->managerRegistry);

        $hoje = new \DateTime();
        $inicioMes = (clone $hoje)->modify('first day of this month')->setTime(0, 0);
        $semanaPassada = (clone $hoje)->modify('-7 days');

        $financeiroHoje = $repoFinanceiro->findByDate($baseId, $hoje);
        $financeiroSemana = $repoFinanceiro->getRelatorioPorPeriodo($baseId, $semanaPassada, $hoje);
        $financeiroMes = $repoFinanceiro->getRelatorioPorPeriodo($baseId, $inicioMes, $hoje);

        $inicioMes = (clone $hoje)->modify('first day of this month');
        $totalReceita = $repoFinanceiro->somarPorDescricao($baseId, 'Receita', $inicioMes, $hoje);
        $totalDespesa = $repoFinanceiro->somarPorDescricao($baseId, 'Pagamento', $inicioMes, $hoje);

        $totalGeral = $totalReceita - $totalDespesa;
        $dataAtual = $request->query->get('data')
            ? new \DateTime($request->query->get('data'))
            : new \DateTime();

        return $this->render('clinica/financeirodash.html.twig', [
            'financeiro_hoje' => $financeiroHoje,
            'financeiro_semana' => $financeiroSemana,
            'financeiro_mes' => $financeiroMes,
            'total_receita' => $totalReceita,
            'total_despesa' => $totalDespesa,
            'saldo_geral' => $totalGeral,
            'dataAtual' => $dataAtual,
        ]);
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

    /**
     * @Route("/pet/{petId}/venda/concluir", name="clinica_concluir_venda", methods={"POST"})
     */
    public function concluirVenda(Request $request, int $petId, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // --- Pegando todos os campos do POST
        $servicoId = $request->get('servico_id');
        $descricao = $request->get('descricao');
        $data = $request->get('data') ? new \DateTime($request->get('data')) : new \DateTime();
        $observacao = $request->get('observacao');
        $metodoPagamento = $request->get('metodo_pagamento');

        // --- Se vier ID do serviço, busca o valor oficial no banco (anti-gambiarra)
        if ($servicoId) {
            $servico = $entityManager->getRepository(Servico::class)->find($servicoId);
            if (!$servico) {
                return $this->json(['status' => 'error', 'mensagem' => 'Serviço não encontrado!'], 404);
            }
            $descricao = $servico->getNome();
            $valor = (float)$servico->getValor();
        }


        $valorFinal = 0;
        $descontoFinal = 0;
        $descricaoFinal = '';


        $descricoes = (array)$request->get('descricao', []);
        $descontos = (array)$request->get('desconto', []);
        $valoresCalculados = (array)$request->get('valor_calculado', []); // vindo do JS (internações)
        $valoresSimples = (array)$request->get('valor', []); // outros serviços
        $quantidades = (array)$request->get('quantidade_diarias', []);

        $valorFinal = 0;
        $descontoFinal = 0;
        $descricaoFinal = '';

        foreach ($descricoes as $i => $servicoId) {
            $servico = $this->getRepositorio(\App\Entity\Servico::class)->listaServicoPorId($baseId, $servicoId);
            if (!$servico) continue;

            $descricaoServico = $servico['descricao'] ?? 'Serviço';
            $valorBase = (float)($servico['valor'] ?? 0);
            $desconto = isset($descontos[$i]) ? (float)$descontos[$i] : 0;

            // 🔍 Tenta achar uma quantidade ou valor calculado mesmo que o índice não coincida
            $quantidade = 1;
            $valorCalculado = null;

            if (stripos($descricaoServico, 'internação') !== false) {
                // Procura o primeiro valor em quantidade_diarias
                if (!empty($quantidades)) {
                    $quantidade = (int)reset($quantidades); // pega o primeiro valor do array
                }
                if (!empty($valoresCalculados)) {
                    $valorCalculado = (float)reset($valoresCalculados);
                }

                // Calcula corretamente
                if ($valorCalculado > 0) {
                    $valorBase = $valorCalculado;
                } elseif ($quantidade > 1) {
                    $valorBase *= $quantidade;
                }

                $descricaoServico .= " ({$quantidade} diárias)";
            }

            $valorFinal += $valorBase;
            $descontoFinal += $desconto;
            $descricaoFinal .= $descricaoServico . ' + ';
        }


        $valorFinal = max(0, $valorFinal - $descontoFinal);

        if ($metodoPagamento === 'pendente') {
            $financeiroPendente = new FinanceiroPendente();
            $financeiroPendente->setEstabelecimentoId($baseId);
            $financeiroPendente->setPetId($petId);
            $financeiroPendente->setValor($valorFinal);
            $financeiroPendente->setDescricao(trim($descricaoFinal, ' +'));
            $financeiroPendente->setData($data);
            $financeiroPendente->setStatus('pendente');
            $financeiroPendente->setOrigem('clinica');
            $financeiroPendente->setMetodoPagamento($metodoPagamento);

            $entityManager->persist($financeiroPendente);
            $entityManager->flush();

            return $this->json([
                'status' => 'success',
                'mensagem' => 'Lançado como pendente.',
            ]);
        } else {
            $financeiro = new Financeiro();
            $financeiro->setEstabelecimentoId($baseId);
            $financeiro->setPetId($petId);
            $financeiro->setValor($valorFinal);
            $financeiro->setDescricao(trim($descricaoFinal, ' +'));
            $financeiro->setData($data);
            $financeiro->setOrigem('clinica');
            $financeiro->setStatus('concluido');

            $entityManager->persist($financeiro);
            $entityManager->flush();

            return $this->json([
                'status' => 'success',
                'mensagem' => 'Pagamento registrado no financeiro!',
            ]);
        }

    }


    /**
     * @Route("/internacao/{id}/ficha", name="clinica_ficha_internacao", methods={"GET"})
     */
    public function fichaInternacao(
        int                    $id,
        InternacaoRepository   $internacaoRepo,
        VeterinarioRepository  $veterinarioRepo,
        EntityManagerInterface $em
    ): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $internacao = $internacaoRepo->findInternacaoCompleta($baseId, $id);
        if (!$internacao) {
            throw $this->createNotFoundException('A ficha de internação não foi encontrada.');
        }

        // --- TIMELINE ---
        $rows = $internacaoRepo->listarEventosPorInternacao($baseId, $id);
        $timeline = array_map(function (array $r) {
            try {
                $r['data_hora'] = !empty($r['data_hora']) ? new \DateTime($r['data_hora']) : new \DateTime();
            } catch (\Exception $e) {
                $r['data_hora'] = new \DateTime();
            }
            $r['titulo'] = $r['titulo'] ?? '—';
            $r['descricao'] = $r['descricao'] ?? '';
            $r['tipo'] = (string)($r['tipo'] ?? 'internacao');
            return $r;
        }, $rows);
        usort($timeline, fn($a, $b) => $b['data_hora'] <=> $a['data_hora']);

        // --- PRESCRIÇÕES ---
        $medicamentos = $em->getRepository(Medicamento::class)->findAll();
        $prescricoes = $em->getRepository(InternacaoPrescricao::class)->findBy(['internacaoId' => $id]);

        // --- CALENDÁRIO ---
        $events = [];
        foreach ($prescricoes as $p) {
            // if (!$p->getDataHora()) {
            //     continue;
            // }
            $eventos = $em->getRepository(\App\Entity\InternacaoEvento::class)->findBy(['internacaoId' => $p->getId()]);
            $primeira = $p->getDataHora();
            $freqHoras = (int)$p->getFrequenciaHoras();
            $duracaoDias = (int)$p->getDuracaoDias();

            if ($freqHoras <= 0 || $duracaoDias <= 0) {
                continue;
            }

            $numDoses = ($duracaoDias * 24) / $freqHoras;
            foreach ($eventos as $i => $evento) {
                $doseTime = (clone $primeira)->modify('+' . ($i * $freqHoras) . ' hours');
                // consultar na porecricao execucao
                $execucao = $em->getRepository(\App\Entity\InternacaoExecucao::class)->findOneBy(['prescricaoId' => $evento->getId()]);

                $cor = '#0d6efd';
                $habilitaModal = true;
                if ($execucao) {
                    if ($execucao->getStatus() == 'confirmado') {
                        $habilitaModal = false;
                        $cor = 'green';
                    }
                }

                $events[] = [
                    'title' => $p->getMedicamento()->getNome() . " - " . $p->getDose(),
                    'start' => $doseTime->format(\DateTime::ATOM),
                    'end' => (clone $doseTime)->modify('+30 minutes')->format(\DateTime::ATOM),
                    'color' => $cor,
                    'prescricao_id' => $evento->getId(),
                    'habilita_modal' => $habilitaModal,
                ];
            }
            //dd($events);
            /*for ($i = 0; $i < $numDoses; $i++) {
                $doseTime = (clone $primeira)->modify('+' . ($i * $freqHoras) . ' hours');
                // consultar na porecricao execucao
                $execucao = $em->getRepository(\App\Entity\InternacaoExecucao::class)->findOneBy(['prescricaoId' => $p->getId()]);
                $cor = ($execucao->getStatus() == 'confirmado' ?'green':'#0d6efd');
                $events[] = [
                    'title' => $p->getMedicamento()->getNome() . " - " . $p->getDose(),
                    'start' => $doseTime->format(\DateTime::ATOM),
                    'end' => (clone $doseTime)->modify('+30 minutes')->format(\DateTime::ATOM),
                    'color' => $cor,
                    'prescricao_id' => $p->getId(),
                ];
            }*/
        }
        // dd($events);

        // --- VETERINÁRIOS ---
        $veterinarios = $veterinarioRepo->findBy(['estabelecimentoId' => $baseId]);

        $data = [];
        $data['internacao'] = $internacao;
        $data['timeline'] = $timeline;
        $data['medicamentos'] = $medicamentos;
        $data['prescricoes'] = $prescricoes;
        $data['calendario_prescricoes'] = $events;
        $data['veterinarios'] = $veterinarios;
        // dd($data);
        return $this->render('clinica/ficha_internacao.html.twig', $data);
    }

    /**
     * @Route("/internacao/{id}/acao/{acao}", name="clinica_internacao_acao", methods={"POST"})
     */
    public function acaoInternacao(int $id, string $acao, Request $request, InternacaoRepository $internacaoRepo): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Validar CSRF token (um único nome genérico)
        if (!$this->isCsrfTokenValid('internacao_acao_' . $id, $request->get('_token'))) {
            return $this->json(['ok' => false, 'msg' => 'Token inválido.'], 400);
        }

        // Busca internação
        $internacao = $internacaoRepo->buscarPorId($baseId, $id);
        if (!$internacao) {
            return $this->json(['ok' => false, 'msg' => 'Internação não encontrada.'], 404);
        }

        // Normaliza a ação
        $acoesValidas = [
            'alta' => ['status' => 'finalizada', 'titulo' => 'Alta concedida', 'descricao' => 'Internação finalizada pelo sistema'],
            'obito' => ['status' => 'obito', 'titulo' => 'Óbito registrado', 'descricao' => 'Internação encerrada por óbito'],
            'cancelar' => ['status' => 'cancelada', 'titulo' => 'Internação cancelada', 'descricao' => 'Internação cancelada pelo sistema'],
            'box' => ['status' => 'ativa', 'titulo' => 'Box alterado', 'descricao' => 'Box de internação atualizado'],
            'editar' => ['status' => 'ativa', 'titulo' => 'Internação editada', 'descricao' => 'Dados da internação foram atualizados'],
        ];

        if (!isset($acoesValidas[$acao])) {
            return $this->json(['ok' => false, 'msg' => 'Ação inválida.'], 400);
        }

        if (in_array($internacao['status'], ['alta', 'obito'])) {
            return $this->json([
                'ok' => false,
                'msg' => 'Esta internação já foi encerrada por ' . $internacao['status'] . '.'
            ]);
        }

        $acaoInfo = $acoesValidas[$acao];

        $internacaoRepo->atualizarStatus($baseId, $id, $acaoInfo['status']);

        // Cria evento na timeline
        $internacaoRepo->inserirEvento(
            $baseId,
            $id,
            (int)$internacao['pet_id'],
            ucfirst($acao),
            $acaoInfo['titulo'],
            $acaoInfo['descricao'],
            new \DateTime()
        );

        return $this->json(['ok' => true]);
    }


    /**
     * @Route("/internacao/{id}/prescricao/nova", name="clinica_internacao_prescricao_nova", methods={"GET","POST"})
     */
    public function novaPrescricao(
        int                    $id,
        Request                $request,
        EntityManagerInterface $em
    ): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $internacaoRepo = $this->getRepositorio(\App\Entity\Internacao::class);

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('clinica_ficha_internacao', ['id' => $id]);
        }

        try {
            $internacao = $em->getRepository(Internacao::class)->find($id);
            if (!$internacao) {
                return $this->json(['ok' => false, 'msg' => 'Internação não encontrada.'], 404);
            }

            $medicamentoId = (int)$request->get('medicamentoId');
            $dose = trim((string)$request->get('dose'));
            $frequenciaHoras = (int)$request->get('frequencia_horas');
            $duracaoDias = (int)$request->get('duracao_dias');
            $dataHoraPrimeiraDose = $request->get('data_hora_primeira_dose');

            if (!$medicamentoId || empty($dose) || $frequenciaHoras <= 0 || $duracaoDias <= 0 || empty($dataHoraPrimeiraDose)) {
                return $this->json(['ok' => false, 'msg' => 'Campos obrigatórios faltando.'], 400);
            }

            $medicamento = $em->getRepository(Medicamento::class)->find($medicamentoId);
            if (!$medicamento) {
                return $this->json(['ok' => false, 'msg' => 'Medicamento não encontrado.'], 404);
            }

            // --- Cria a prescrição ---
            $prescricao = new InternacaoPrescricao();
            $prescricao->setInternacaoId($internacao->getId());
            $prescricao->setMedicamento($medicamento);
            $prescricao->setDescricao($medicamento->getNome());
            $prescricao->setDose($dose);

            // Salva tanto a string "bonita" quanto os valores numéricos
            $prescricao->setFrequencia(sprintf('a cada %d horas por %d dias', $frequenciaHoras, $duracaoDias));
            $prescricao->setFrequenciaHoras($frequenciaHoras);
            $prescricao->setDuracaoDias($duracaoDias);

            $prescricao->setDataHora(new \DateTime($dataHoraPrimeiraDose));
            $prescricao->setCriadoEm(new \DateTime());

            $em->persist($prescricao);
            $em->flush();

            // --- Cria eventos da timeline ---
            $petId = method_exists($internacao, 'getPetId')
                ? $internacao->getPetId()
                : ($internacao->getPet() ? $internacao->getPet()->getId() : null);

            if ($petId) {
                $numDoses = ($duracaoDias * 24) / $frequenciaHoras;

                for ($i = 0; $i < $numDoses; $i++) {
                    $dataDose = (new \DateTime($dataHoraPrimeiraDose))->modify('+' . ($i * $frequenciaHoras) . ' hours');

                    $descricaoEvento = sprintf(
                        "Medicamento: %s | Dose: %s | Frequência: %s",
                        $medicamento->getNome(),
                        $dose,
                        $prescricao->getFrequencia()
                    );

                    $internacaoRepo->inserirEvento(
                        $baseId,
                        $prescricao->getId(),
                        $petId,
                        'medicacao',
                        'Dose de medicação agendada',
                        $descricaoEvento,
                        $dataDose
                    );
                }
            }

            return $this->json(['ok' => true, 'msg' => 'Prescrição salva com sucesso!']);

        } catch (\Exception $e) {
            return $this->json([
                'ok' => false,
                'msg' => 'Erro interno: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @Route("/internacao/{id}/prescricao/{eventoId}/executar", name="clinica_internacao_prescricao_executar", methods={"POST"})
     */
    public function executarPrescricao(
        int                    $id,
        int                    $eventoId,
        Request                $request,
        EntityManagerInterface $em,
        InternacaoRepository   $internacaoRepo
    ): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $horaAplicacao = $request->get('hora_aplicacao');
        $veterinarioId = (int)$request->get('veterinario_id');
        $anotacoes = $request->get('anotacoes');

        if (!$horaAplicacao || !$veterinarioId) {
            return $this->json(['ok' => false, 'msg' => 'Preencha hora e veterinário.'], 400);
        }

        try {

            // Busca o veterinário
            $veterinario = $em->getRepository(\App\Entity\Veterinario::class)->find($veterinarioId);
            if (!$veterinario) {
                return $this->json(['ok' => false, 'msg' => 'Veterinário não encontrado.'], 404);
            }

            // Cria entidade de execução
            $execucao = new InternacaoExecucao();
            $execucao->setInternacaoId($id);
            $execucao->setPrescricaoId($eventoId);
            $execucao->setVeterinario($veterinario);
            $execucao->setDataExecucao(new \DateTime($horaAplicacao));
            $execucao->setStatus('confirmado'); // marca como confirmado
            $execucao->setAnotacoes($anotacoes);

            $em->persist($execucao);
            $em->flush();

            // Opcional: marcar evento da prescrição como executado
            $internacaoRepo->marcarMedicacaoComoExecutada($baseId, $eventoId);

            return $this->json(['ok' => true, 'msg' => 'Medicação confirmada com sucesso!']);

        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @Route("/medicamentos", name="clinica_medicamentos", methods={"GET","POST"})
     */
    public function medicamentos(Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Cadastro de novo medicamento
        if ($request->isMethod('POST')) {
            $medicamento = new Medicamento();
            $medicamento->setNome($request->get('nome'));
            $medicamento->setVia($request->get('via'));
            $medicamento->setConcentracao($request->get('concentracao'));

            $em->persist($medicamento);
            $em->flush();

            $this->addFlash('success', 'Medicamento cadastrado com sucesso!');
            return $this->redirectToRoute('clinica_medicamentos');
        }

        // Listagem de medicamentos
        $medicamentos = $em->getRepository(Medicamento::class)->findAll();

        return $this->render('clinica/medicamentos.html.twig', [
            'medicamentos' => $medicamentos,
        ]);
    }

    /**
     * @Route("/medicamentos/{id}/editar", name="clinica_medicamento_editar", methods={"GET","POST"})
     */
    public function editarMedicamento(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();

        $medicamento = $em->getRepository(Medicamento::class)->find($id);
        if (!$medicamento) {
            throw $this->createNotFoundException('Medicamento não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $medicamento->setNome($request->get('nome'));
            $medicamento->setVia($request->get('via'));
            $medicamento->setConcentracao($request->get('concentracao'));

            $em->flush();

            $this->addFlash('success', 'Medicamento atualizado!');
            return $this->redirectToRoute('clinica_medicamentos');
        }

        return $this->render('clinica/medicamento_editar.html.twig', [
            'medicamento' => $medicamento,
        ]);
    }

    /**
     * @Route("/medicamentos/{id}/excluir", name="clinica_medicamento_excluir", methods={"POST"})
     */
    public function excluirMedicamento(int $id, EntityManagerInterface $em): Response
    {
        $this->switchDB();

        $medicamento = $em->getRepository(Medicamento::class)->find($id);
        if ($medicamento) {
            $em->remove($medicamento);
            $em->flush();
            $this->addFlash('success', 'Medicamento excluído.');
        }

        return $this->redirectToRoute('clinica_medicamentos');
    }

    /**
     * @Route("/internacao/medicamento/novo", name="clinica_internacao_medicamento_novo", methods={"POST"})
     */
    public function novoMedicamentoViaInternacao(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $nome = trim((string)$request->get('nome'));
        $via = trim((string)$request->get('via'));
        $concentracao = trim((string)$request->get('concentracao'));

        if (empty($nome)) {
            return $this->json(['ok' => false, 'msg' => 'O nome do medicamento é obrigatório.']);
        }

        try {
            $medicamento = new Medicamento();
            $medicamento->setNome($nome);
            $medicamento->setVia($via);
            $medicamento->setConcentracao($concentracao);

            $em->persist($medicamento);
            $em->flush();
            return $this->json(['ok' => true, 'msg' => 'Medicamento cadastrado com sucesso!']);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'msg' => 'Erro ao salvar o medicamento: ' . $e->getMessage()]);
        }

    }

    /**
     * @Route("/clinica/pet/{petId}/venda/{id}/editar", name="clinica_editar_venda", methods={"POST"})
     */
    public function editarVenda(Request $request, int $petId, int $id, FinanceiroRepository $financeiroRepository): JsonResponse
    {
        try {
            // 🔧 Troca o banco para o da clínica atual
            $this->switchDB();
            $baseId = $this->getIdBase();

            $financeiro = $financeiroRepository->findFinanceiro($baseId, $id);
            if (!$financeiro) {
                return new JsonResponse(['status' => 'error', 'mensagem' => 'Venda não encontrada.'], 404);
            }

            $financeiro->setDescricao($request->get('descricao'));
            $financeiro->setValor((float)$request->get('valor'));

            $data = $request->get('data');
            if ($data) {
                $financeiro->setData(new \DateTime($data));
            }

            $metodo = $request->get('metodo_pagamento') ?: 'pendente';
            $financeiro->setMetodoPagamento($metodo);
            $financeiro->setObservacoes($request->get('observacao'));

            $financeiroRepository->update($baseId, $financeiro);

            return new JsonResponse(['status' => 'success', 'mensagem' => 'Venda atualizada com sucesso.']);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'mensagem' => 'Erro ao editar venda: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @Route("/pet/{petId}/venda/{id}/inativar", name="clinica_inativar_venda", methods={"POST"})
     */
    public function inativarVenda(Request $request, FinanceiroRepository $financeiroRepository, FinanceiroPendenteRepository $pendenteRepository, int $petId, int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();


        try {
            $financeiroRepository->inativar($baseId, $id);

            $pendenteRepository->inativar($baseId, $id);

            $this->addFlash('success', 'Venda inativada com sucesso.');
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao inativar venda: ' . $e->getMessage());
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
        }
    }

    /**
     * @Route("/calendario/geral", name="clinica_calendario_geral", methods={"GET"})
     */
    public function calendarioGeral(EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $prescricoes = $em->getRepository(InternacaoPrescricao::class)->findAll();

        $eventos = [];
        foreach ($prescricoes as $p) {
            if (!$p->getDataHora()) {
                continue;
            }

            $primeira = $p->getDataHora();
            $freq = (int)$p->getFrequenciaHoras();
            $dias = (int)$p->getDuracaoDias();
            if ($freq <= 0 || $dias <= 0) {
                continue;
            }

            $numDoses = ($dias * 24) / $freq;

            for ($i = 0; $i < $numDoses; $i++) {
                $horas = $i * $freq;
                $doseTime = (clone $primeira)->modify("+{$horas} hours");

                $internacao = $em->getRepository(Internacao::class)->find($p->getInternacaoId());
                $petNome = 'Pet';
                if ($internacao) {
                    $pet = $em->getRepository(Pet::class)->find($internacao->getPetId());
                    $petNome = $pet ? $pet->getNome() : 'Pet';
                }

                $eventos[] = [
                    'title' => $p->getMedicamento()->getNome() . " - " . $p->getDose(),
                    'start' => $doseTime->format(\DateTime::ATOM),
                    'end' => (clone $doseTime)->modify('+30 minutes')->format(\DateTime::ATOM),
                    'color' => '#0d6efd',
                    'prescricao_id' => $p->getId(),
                ];

            }

        }

        return $this->render('clinica/calendario_geral.html.twig', [
            'eventos' => $eventos,
        ]);
    }

}
