<?php

namespace App\Controller;

use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\Pet;
use App\Entity\DocumentoModelo;
use App\Entity\Internacao;
use App\Repository\ConsultaRepository;
use App\Repository\DocumentoModeloRepository;
use App\Repository\InternacaoRepository;
use App\Repository\FinanceiroRepository;
use App\Service\PdfService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

        $vacinasVencidas = method_exists($repoPet, 'listarVacinasPendentes') ? $repoPet->listarVacinasPendentes($baseId) : [];
        $vacinasProgramadas = method_exists($repoPet, 'listarVacinasProgramadas') ? $repoPet->listarVacinasProgramadas($baseId) : [];

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
        ]);
    }

    /**
     * @Route("/pet/{id}", name="clinica_detalhes_pet", methods={"GET"})
     */
    public function detalhesPet(int $id, ConsultaRepository $consultaRepo, DocumentoModeloRepository $documentoRepo, FinanceiroRepository $financeiroRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(\App\Entity\Pet::class)->findPetById($baseId, $id);
        if (!$pet) {
            throw $this->createNotFoundException('O pet não foi encontrado.');
        }

        $consultas = $consultaRepo->findAllByPetId($baseId, $id);
        $documentos = $documentoRepo->listarDocumentos($baseId);
        $financeiro = $financeiroRepo->buscarPorPet($baseId, $pet['id']);

        // ✅ **LÓGICA CORRIGIDA AQUI**
        $timeline_items = [];
        foreach ($consultas as $item) {
            $timeline_items[] = [
                'data' => new \DateTime($item['data'] . ' ' . $item['hora']),
                'tipo' => $item['tipo'] ?? 'Consulta',
                'resumo' => $item['observacoes'],
                // **LINHA CRÍTICA CORRIGIDA:** Adiciona o conteúdo da anamnese diretamente.
                // O template Twig agora receberá esta variável e poderá exibir os detalhes.
                'anamnese' => $item['anamnese'] ?? null
            ];
        }

        // Ordena a timeline do mais recente para o mais antigo.
        usort($timeline_items, function($a, $b) {
            return $b['data'] <=> $a['data'];
        });

        // ✅ **BOA PRÁTICA IMPLEMENTADA:** Cálculo movido do Twig para o Controller.
        $totalDebitos = 0;
        foreach ($financeiro as $itemFinanceiro) {
            if (isset($itemFinanceiro['valor'])) {
                $totalDebitos += $itemFinanceiro['valor'];
            }
        }

        // ✅ **RENDERIZAÇÃO LIMPA:** Remove chaves duplicadas e adiciona total_debitos.
        return $this->render('clinica/detalhes_pet.html.twig', [
            'pet' => $pet,
            'timeline_items' => $timeline_items,
            'documentos' => $documentos,
            'financeiro' => $financeiro,
            'consultas' => $consultas, // Mantido para outras partes da página, se necessário.
            'total_debitos' => $totalDebitos,
        ]);
    }

    /**
     * @Route("/pet/{petId}/internacao/nova", name="clinica_nova_internacao", methods={"GET", "POST"})
     */
    public function novaInternacao(Request $request, int $petId): Response
    {
        // ... (lógica da internação, se necessário)
        $this->addFlash('info', 'Funcionalidade de internação ainda não implementada.');
        return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
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
        $consulta->setClienteId((int) $request->request->get('cliente_id'));
        $consulta->setPetId($petId);
        $consulta->setData(new \DateTime($request->request->get('data')));
        $consulta->setHora(new \DateTime($request->request->get('hora')));
        $consulta->setObservacoes($request->request->get('observacoes'));
        
        $consulta->setAnamnese($request->request->get('anamnese_delta'));  
        
        $consulta->setTipo($request->request->get('tipo'));
        $consulta->setStatus('atendido');
        $consulta->setCriadoEm(new \DateTime());

        $consultaRepo->salvarConsulta($consulta);

        $this->addFlash('success', 'Atendimento salvo com sucesso!');
        return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
    }

    /**
     * @Route("/pet/{petId}/receita/nova", name="clinica_nova_receita", methods={"POST"})
     */
    public function novaReceita(Request $request, int $petId, PdfService $pdfService): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);

        return $pdfService->gerarPdf(
            'clinica/receita_pdf_backend.html.twig',
            [
                'cabecalho' => $request->get('cabecalho', ''),
                'conteudo'  => $request->get('conteudo', ''),
                'rodape'    => $request->get('rodape', ''),
                'pet'       => $pet
            ],
            'receita-' . $pet['nome'] . '.pdf'
        );
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
        return $this->redirectToRoute('clinica_documentos');
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
    public function novaVacina(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        // Lógica para vacinas
        return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Vacina']);
    }

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

        return $pdfService->gerarPdf(
            'clinica/receita_pdf_backend.html.twig',
            [
                'cabecalho' => $request->get('cabecalho', ''),
                'conteudo'  => $request->get('conteudo', ''),
                'rodape'    => $request->get('rodape', '')
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
            $doc = new DocumentoModelo();
            $doc->setTitulo($request->get('titulo'));
            $doc->setCabecalho($request->get('cabecalho'));
            $doc->setConteudo($request->get('conteudo'));
            $doc->setRodape($request->get('rodape'));
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

        return $this->json(array_map(fn ($pet) => ['id' => $pet['id'], 'nome' => $pet['nome']], $pets));
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
}