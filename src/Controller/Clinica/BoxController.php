<?php

namespace App\Controller\Clinica;

use App\Entity\Box;
use App\Repository\BoxRepository;
use App\Repository\InternacaoRepository;
use App\Controller\DefaultController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Gestão de Boxes — área do administrador do estabelecimento.
 *
 * @Route("dashboard/clinica/boxes", name="clinica_box_")
 */
class BoxController extends DefaultController
{
    // ── Labels estáticos reutilizados nas views ──────────────────────

    private const TIPO_LABELS = [
        'internacao'  => 'Internação',
        'emergencia'  => 'Emergência',
        'observacao'  => 'Observação',
        'isolamento'  => 'Isolamento',
        'cirurgia'    => 'Pós-cirúrgico',
        'recuperacao' => 'Recuperação',
    ];

    private const ESTRUTURA_LABELS = [
        'maca'    => 'Maca',
        'canil'   => 'Canil',
        'gaiola'  => 'Gaiola',
        'cercado' => 'Cercado',
        'baia'    => 'Baía',
    ];

    private const PORTE_LABELS = [
        'pequeno' => 'Pequeno',
        'medio'   => 'Médio',
        'grande'  => 'Grande',
        'gigante' => 'Gigante',
        'todos'   => 'Todos os portes',
    ];

    private const STATUS_LABELS = [
        'disponivel'   => 'Disponível',
        'ocupado'      => 'Ocupado',
        'manutencao'   => 'Manutenção',
        'reservado'    => 'Reservado',
        'higienizacao' => 'Higienização',
    ];

    // ── Listagem / painel de controle ────────────────────────────────

    /**
     * @Route("", name="index", methods={"GET"})
     */
    public function index(BoxRepository $boxRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $boxes      = $boxRepo->findAllComOcupacao($baseId);
        $contadores = $boxRepo->contadoresPorStatus($baseId);

        return $this->render('clinica/boxes/index.html.twig', [
            'boxes'           => $boxes,
            'contadores'      => $contadores,
            'tipoLabels'      => self::TIPO_LABELS,
            'estruturaLabels' => self::ESTRUTURA_LABELS,
            'porteLabels'     => self::PORTE_LABELS,
            'statusLabels'    => self::STATUS_LABELS,
        ]);
    }

    // ── Criar ────────────────────────────────────────────────────────

    /**
     * @Route("/novo", name="novo", methods={"GET","POST"})
     */
    public function novo(Request $request, BoxRepository $boxRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        if ($request->isMethod('POST')) {
            $box = $this->hydrateFromRequest($request, new Box());
            $box->setEstabelecimentoId($baseId);

            try {
                $boxRepo->inserir($baseId, $box);
                $this->addFlash('success', "Box {$box->getNumero()} criado com sucesso!");
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erro ao salvar: ' . $e->getMessage());
            }

            return $this->redirectToRoute('clinica_box_index');
        }

        return $this->render('clinica/boxes/form.html.twig', [
            'box'             => null,
            'tipoLabels'      => self::TIPO_LABELS,
            'estruturaLabels' => self::ESTRUTURA_LABELS,
            'porteLabels'     => self::PORTE_LABELS,
            'statusLabels'    => self::STATUS_LABELS,
            'titulo'          => 'Novo Box',
        ]);
    }

    // ── Editar ───────────────────────────────────────────────────────

    /**
     * @Route("/{id}/editar", name="editar", methods={"GET","POST"})
     */
    public function editar(int $id, Request $request, BoxRepository $boxRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $row = $boxRepo->findByIdAndBase($baseId, $id);
        if (!$row) {
            throw $this->createNotFoundException('Box não encontrado.');
        }

        // Constrói um objeto Box a partir do array (para o formulário)
        $box = $this->arrayToBox($row);

        if ($request->isMethod('POST')) {
            $box = $this->hydrateFromRequest($request, $box);

            try {
                $boxRepo->atualizar($baseId, $box);
                $this->addFlash('success', "Box {$box->getNumero()} atualizado com sucesso!");
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erro ao atualizar: ' . $e->getMessage());
            }

            return $this->redirectToRoute('clinica_box_index');
        }

        return $this->render('clinica/boxes/form.html.twig', [
            'box'             => $row,
            'tipoLabels'      => self::TIPO_LABELS,
            'estruturaLabels' => self::ESTRUTURA_LABELS,
            'porteLabels'     => self::PORTE_LABELS,
            'statusLabels'    => self::STATUS_LABELS,
            'titulo'          => "Editar Box {$row['numero']}",
        ]);
    }

    // ── Alterar status (AJAX) ────────────────────────────────────────

    /**
     * Altera o status manualmente (manutenção, higienização etc.)
     *
     * @Route("/{id}/status", name="status", methods={"POST"})
     */
    public function alterarStatus(int $id, Request $request, BoxRepository $boxRepo): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $novoStatus = $request->get('status');
        $statusValidos = array_keys(self::STATUS_LABELS);

        if (!in_array($novoStatus, $statusValidos)) {
            return $this->json(['ok' => false, 'msg' => 'Status inválido.'], 400);
        }

        $row = $boxRepo->findByIdAndBase($baseId, $id);
        if (!$row) {
            return $this->json(['ok' => false, 'msg' => 'Box não encontrado.'], 404);
        }

        // Não permite mudar status de box com internação ativa para "disponível" manualmente
        // (isso é feito automaticamente pela InternacaoController::acaoInternacao)
        if ($row['status'] === 'ocupado' && $novoStatus === 'disponivel') {
            return $this->json([
                'ok'  => false,
                'msg' => 'Box com internação ativa. Para liberar, dê alta ou cancele a internação.',
            ], 422);
        }

        try {
            $boxRepo->atualizarStatus($baseId, $id, $novoStatus);
            return $this->json([
                'ok'     => true,
                'msg'    => 'Status atualizado.',
                'status' => $novoStatus,
                'label'  => self::STATUS_LABELS[$novoStatus],
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    // ── Excluir ──────────────────────────────────────────────────────

    /**
     * @Route("/{id}/excluir", name="excluir", methods={"POST"})
     */
    public function excluir(int $id, Request $request, BoxRepository $boxRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $row = $boxRepo->findByIdAndBase($baseId, $id);
        if (!$row) {
            $this->addFlash('danger', 'Box não encontrado.');
            return $this->redirectToRoute('clinica_box_index');
        }

        if ($row['status'] === 'ocupado') {
            $this->addFlash('danger', "Box {$row['numero']} está ocupado e não pode ser excluído.");
            return $this->redirectToRoute('clinica_box_index');
        }

        try {
            $boxRepo->excluir($baseId, $id);
            $this->addFlash('success', "Box {$row['numero']} excluído.");
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erro ao excluir: ' . $e->getMessage());
        }

        return $this->redirectToRoute('clinica_box_index');
    }

    // ── Helpers privados ─────────────────────────────────────────────

    private function hydrateFromRequest(Request $r, Box $box): Box
    {
        $box->setNumero(trim((string) $r->get('numero')));
        $box->setTipo((string) $r->get('tipo'));
        $box->setPorte((string) $r->get('porte'));
        $box->setEstrutura((string) $r->get('estrutura'));
        $box->setLocalizacao(trim((string) $r->get('localizacao')) ?: null);
        $box->setStatus((string) $r->get('status', 'disponivel'));
        $box->setSuporteSoro((bool) $r->get('suporte_soro', false));
        $box->setSuporteOxigenio((bool) $r->get('suporte_oxigenio', false));
        $box->setTemAquecimento((bool) $r->get('tem_aquecimento', false));
        $box->setTemCamera((bool) $r->get('tem_camera', false));

        $pesoMax = $r->get('peso_maximo_kg');
        $box->setPesoMaximoKg($pesoMax !== '' && $pesoMax !== null ? (float) $pesoMax : null);

        $diaria = $r->get('valor_diaria');
        $box->setValorDiaria($diaria !== '' && $diaria !== null ? (float) $diaria : null);

        $box->setObservacoes(trim((string) $r->get('observacoes')) ?: null);
        $box->setUpdatedAt(new \DateTime());

        return $box;
    }

    private function arrayToBox(array $row): Box
    {
        $box = new Box();
        // Inject id via reflection (no setter by design)
        $ref = new \ReflectionProperty(Box::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($box, (int) $row['id']);

        $box->setEstabelecimentoId((int) $row['estabelecimento_id']);
        $box->setNumero($row['numero']);
        $box->setTipo($row['tipo']);
        $box->setPorte($row['porte'] ?? 'todos');
        $box->setEstrutura($row['estrutura'] ?? 'canil');
        $box->setLocalizacao($row['localizacao'] ?? null);
        $box->setStatus($row['status']);
        $box->setSuporteSoro((bool) ($row['suporte_soro'] ?? false));
        $box->setSuporteOxigenio((bool) ($row['suporte_oxigenio'] ?? false));
        $box->setTemAquecimento((bool) ($row['tem_aquecimento'] ?? false));
        $box->setTemCamera((bool) ($row['tem_camera'] ?? false));
        $box->setPesoMaximoKg(isset($row['peso_maximo_kg']) && $row['peso_maximo_kg'] !== null ? (float) $row['peso_maximo_kg'] : null);
        $box->setValorDiaria(isset($row['valor_diaria']) && $row['valor_diaria'] !== null ? (float) $row['valor_diaria'] : null);
        $box->setObservacoes($row['observacoes'] ?? null);

        return $box;
    }
}
