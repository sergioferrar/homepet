<?php

namespace App\Controller;

use App\Entity\Modulo;
use App\Entity\Plano;
use App\Entity\Estabelecimento;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @
 */
class PlanoController extends DefaultController
{
    /** Módulos padrão incluídos em todos os planos (IDs 1–6) */
    private const MODULOS_PADRAO_IDS = [1, 2, 3, 4, 5, 6];
    /** Módulos adicionais cobrados à parte: Banho, Hospedagem, Clínica, PDV (IDs 7–10) */
    private const MODULOS_ADICIONAIS_IDS = [7, 8, 9, 10];
    // ---------------------------------------------------------------
    // LISTAGEM
    // ---------------------------------------------------------------
    /**
     * @
     */
    #[Route('dashboard/plano/lista', name: 'app_plano')]
    public function index(): Response
    {
        $planos  = $this->em->getRepository(Plano::class)->findBy([], ['id' => 'DESC']);
        $modulos = $this->em->getRepository(Modulo::class)->findBy([], ['id' => 'ASC']);

        // Enriquece cada plano com totalLojas via DQL
        $planosData = [];
        foreach ($planos as $plano) {
            $total = $this->em->createQuery(
                'SELECT COUNT(e.id) FROM App\\Entity\\Estabelecimento e WHERE e.planoId = :pid'
            )->setParameter('pid', $plano->getId())->getSingleScalarResult();

            $planosData[] = [
                'entidade'   => $plano,
                'totalLojas' => (int) $total,
                'modulosArr' => json_decode($plano->getModulos() ?? '{}', true) ?: [],
            ];
        }

        return $this->render('plano/index.html.twig', [
            'planosData' => $planosData,
            'modulos'    => $modulos,
        ]);
    }
    // ---------------------------------------------------------------
    // CADASTRO — GET
    // ---------------------------------------------------------------
    /**
     * @
     */
    #[Route('dashboard/plano/cadastrar', name: 'app_plano_create', methods: "{GET}")]
    public function cadastrar(): Response
    {
        $modulos = $this->em->getRepository(Modulo::class)->findBy(
            ['status' => 'Ativo'],
            ['id'     => 'ASC']
        );

        [$modulosPadrao, $modulosAdicionais] = $this->separarModulos($modulos);

        return $this->render('plano/cadastro.html.twig', [
            'modulosPadrao'     => $modulosPadrao,
            'modulosAdicionais' => $modulosAdicionais,
        ]);
    }
    // ---------------------------------------------------------------
    // CADASTRO — POST
    // ---------------------------------------------------------------
    /**
     * @
     */
    #[Route('dashboard/plano/cadastrar/novo', name: 'app_plano_create_new')]
    public function store(Request $request): Response
    {
        // Módulos adicionais selecionados no form
        $idsAdicionais = array_map(intval(...), (array) $request->get('modulos', []));
        $todosIds      = array_unique(array_merge(self::MODULOS_PADRAO_IDS, $idsAdicionais));

        $modulosEntidades = $this->em->getRepository(Modulo::class)->findBy(['id' => $todosIds]);
        $modulosJson      = $this->buildModulosJson($modulosEntidades);

        $plano = new Plano();
        $plano->setTitulo(trim((string) $request->get('nome', '')));
        $plano->setDescricao($modulosJson);
        $plano->setValor((float) str_replace(',', '.', (string) $request->get('valor', '0')));
        $plano->setStatus($request->get('status', 'Inativo'));
        $plano->setTrial($request->get('trial') ? 1 : 0);
        $plano->setDataPlano(new \DateTime('now'));
        $plano->setModulos($modulosJson);

        $this->em->persist($plano);
        $this->em->flush();

        $this->addFlash('success', 'Plano "' . $plano->getTitulo() . '" criado com sucesso!');

        return $this->redirectToRoute('app_plano');
    }
    // ---------------------------------------------------------------
    // EDIÇÃO — GET
    // ---------------------------------------------------------------
    /**
     * @
     */
    #[Route('dashboard/plano/editar/{id}', name: 'app_plano_editar', methods: "{GET}")]
    public function editar(int $id): Response
    {
        $plano = $this->em->getRepository(Plano::class)->find($id);

        if (!$plano) {
            $this->addFlash('error', 'Plano não encontrado.');
            return $this->redirectToRoute('app_plano');
        }

        $modulos = $this->em->getRepository(Modulo::class)->findBy(
            ['status' => 'Ativo'],
            ['id'     => 'ASC']
        );

        [$modulosPadrao, $modulosAdicionais] = $this->separarModulos($modulos);

        // IDs ativos no plano (chaves do JSON)
        $modulosAtivos = json_decode($plano->getModulos() ?? '{}', true);
        $idsAtivos = array_map(intval(...), array_keys(is_array($modulosAtivos) ? $modulosAtivos : []));

        return $this->render('plano/editar.html.twig', [
            'plano'             => $plano,
            'modulosPadrao'     => $modulosPadrao,
            'modulosAdicionais' => $modulosAdicionais,
            'idsAtivos'         => $idsAtivos,
        ]);
    }
    // ---------------------------------------------------------------
    // EDIÇÃO — POST
    // ---------------------------------------------------------------
    /**
     * @
     */
    #[Route('dashboard/plano/editar/update/{id}', name: 'app_plano_update')]
    public function update(Request $request, int $id): Response
    {
        $plano = $this->em->getRepository(Plano::class)->find($id);

        if (!$plano) {
            $this->addFlash('error', 'Plano não encontrado.');
            return $this->redirectToRoute('app_plano');
        }

        $idsAdicionais    = array_map(intval(...), (array) $request->get('modulos', []));
        $todosIds         = array_unique(array_merge(self::MODULOS_PADRAO_IDS, $idsAdicionais));
        $modulosEntidades = $this->em->getRepository(Modulo::class)->findBy(['id' => $todosIds]);
        $modulosJson      = $this->buildModulosJson($modulosEntidades);

        $plano->setTitulo(trim((string) $request->get('nome', '')));
        $plano->setDescricao($modulosJson);
        $plano->setValor((float) str_replace(',', '.', (string) $request->get('valor', '0')));
        $plano->setStatus($request->get('status', 'Inativo'));
        $plano->setTrial($request->get('trial') ? 1 : 0);
        $plano->setDataPlano(new \DateTime('now'));
        $plano->setModulos($modulosJson);

        $this->em->flush();

        $this->addFlash('success', 'Plano "' . $plano->getTitulo() . '" atualizado com sucesso!');

        return $this->redirectToRoute('app_plano');
    }
    // ---------------------------------------------------------------
    // TOGGLE ATIVO/INATIVO (Ajax)
    // ---------------------------------------------------------------
    /**
     * @
     */
    #[Route('dashboard/plano/toggle/{id}', name: 'app_plano_toggle')]
    public function toggle(int $id): JsonResponse
    {
        $plano = $this->em->getRepository(Plano::class)->find($id);

        if (!$plano) {
            return new JsonResponse(['success' => false, 'message' => 'Plano não encontrado.'], \Symfony\Component\HttpFoundation\Response::HTTP_NOT_FOUND);
        }

        $novo = $plano->getStatus() === 'Ativo' ? 'Inativo' : 'Ativo';
        $plano->setStatus($novo);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'status' => $novo]);
    }
    // ---------------------------------------------------------------
    // EXCLUIR
    // ---------------------------------------------------------------
    /**
     * @
     */
    #[Route('dashboard/plano/excluir/{id}', name: 'app_plano_excluir')]
    public function excluir(int $id): Response
    {
        $plano = $this->em->getRepository(Plano::class)->find($id);

        if (!$plano) {
            $this->addFlash('error', 'Plano não encontrado.');
            return $this->redirectToRoute('app_plano');
        }

        $total = $this->em->createQuery(
            'SELECT COUNT(e.id) FROM App\\Entity\\Estabelecimento e WHERE e.planoId = :pid'
        )->setParameter('pid', $id)->getSingleScalarResult();

        if ($total > 0) {
            $this->addFlash('warning', "Não é possível excluir: {$total} estabelecimento(s) vinculado(s).");
            return $this->redirectToRoute('app_plano');
        }

        $this->em->remove($plano);
        $this->em->flush();

        $this->addFlash('success', 'Plano excluído com sucesso.');
        return $this->redirectToRoute('app_plano');
    }
    // ---------------------------------------------------------------
    // HELPERS PRIVADOS
    // ---------------------------------------------------------------
    /** @param Modulo[] $modulos */
    private function separarModulos(array $modulos): array
    {
        $padrao     = [];
        $adicionais = [];
        foreach ($modulos as $m) {
            if (in_array($m->getId(), self::MODULOS_PADRAO_IDS, true)) {
                $padrao[] = $m;
            } else {
                $adicionais[] = $m;
            }
        }
        return [$padrao, $adicionais];
    }
    /** @param Modulo[] $modulos */
    private function buildModulosJson(array $modulos): string
    {
        $map = [];
        foreach ($modulos as $m) {
            $map[$m->getId()] = $m->getTitulo();
        }
        return json_encode($map, JSON_UNESCAPED_UNICODE);
    }
}
