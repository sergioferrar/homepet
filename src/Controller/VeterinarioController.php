<?php

namespace App\Controller;

use App\Entity\Veterinario;
use App\Repository\VeterinarioRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/veterinarios")
 */
class VeterinarioController extends DefaultController
{
    /**
     * Listagem de veterinários do estabelecimento logado.
     *
     * @Route("", name="veterinario_index", methods={"GET"})
     */
    public function index(VeterinarioRepository $repo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $veterinarios = $repo->findByEstabelecimento($baseId);

        return $this->render('veterinario/index.html.twig', [
            'veterinarios' => $veterinarios,
        ]);
    }

    /**
     * Formulário e processamento de cadastro de novo veterinário.
     *
     * @Route("/novo", name="veterinario_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request, VeterinarioRepository $repo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        if ($request->isMethod('POST')) {
            $veterinario = new Veterinario();
            $veterinario->setNome(trim((string) $request->get('nome')));
            $veterinario->setEmail(trim((string) $request->get('email')));
            $veterinario->setTelefone(trim((string) $request->get('telefone')));
            $veterinario->setEspecialidade(trim((string) $request->get('especialidade')) ?: null);
            $veterinario->setCrmv(trim((string) $request->get('crmv')) ?: null);
            $veterinario->setEstabelecimentoId($baseId);
            $veterinario->setStatus('ativo');

            try {
                $repo->salvar($baseId, $veterinario);
                $this->addFlash('success', 'Veterinário cadastrado com sucesso!');
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Erro ao cadastrar veterinário: ' . $e->getMessage());
            }

            return $this->redirectToRoute('veterinario_index');
        }

        return $this->render('veterinario/form.html.twig', [
            'veterinario' => null,
            'action'      => $this->generateUrl('veterinario_novo'),
            'titulo'      => 'Cadastrar Veterinário',
        ]);
    }

    /**
     * Formulário e processamento de edição de veterinário existente.
     *
     * @Route("/{id}/editar", name="veterinario_editar", methods={"GET", "POST"}, requirements={"id"="\d+"})
     */
    public function editar(int $id, Request $request, VeterinarioRepository $repo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Busca o veterinário garantindo que pertence ao estabelecimento do tenant
        $dados = $repo->findByIdCompleto($id, $baseId);
        if (!$dados) {
            throw $this->createNotFoundException('Veterinário não encontrado.');
        }

        if ($request->isMethod('POST')) {
            // Carrega a entidade gerenciada pelo ORM para atualização via repository raw SQL
            $veterinario = $this->getRepositorio(Veterinario::class)->find($id);
            if (!$veterinario) {
                throw $this->createNotFoundException('Veterinário não encontrado.');
            }

            $veterinario->setNome(trim((string) $request->get('nome')));
            $veterinario->setEmail(trim((string) $request->get('email')));
            $veterinario->setTelefone(trim((string) $request->get('telefone')));
            $veterinario->setEspecialidade(trim((string) $request->get('especialidade')) ?: null);
            $veterinario->setCrmv(trim((string) $request->get('crmv')) ?: null);
            // Mantém o status atual — alteração de status ocorre apenas via rota dedicada
            $veterinario->setStatus($dados['status'] ?? 'ativo');

            try {
                $repo->atualizar($baseId, $veterinario);
                $this->addFlash('success', 'Veterinário atualizado com sucesso!');
            } catch (\Throwable $e) {
                $this->addFlash('danger', 'Erro ao atualizar veterinário: ' . $e->getMessage());
            }

            return $this->redirectToRoute('veterinario_index');
        }

        return $this->render('veterinario/form.html.twig', [
            'veterinario' => $dados,
            'action'      => $this->generateUrl('veterinario_editar', ['id' => $id]),
            'titulo'      => 'Editar Veterinário',
        ]);
    }

    /**
     * Alterna o status (ativo ↔ inativo) de um veterinário.
     * Os dados NUNCA são excluídos do banco, apenas desabilitados,
     * para garantir a integridade dos relatórios e auditorias.
     *
     * @Route("/{id}/status", name="veterinario_status", methods={"POST"}, requirements={"id"="\d+"})
     */
    public function alterarStatus(int $id, Request $request, VeterinarioRepository $repo): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $dados = $repo->findByIdCompleto($id, $baseId);
        if (!$dados) {
            return new JsonResponse(['success' => false, 'message' => 'Veterinário não encontrado.'], 404);
        }

        $veterinario = $this->getRepositorio(Veterinario::class)->find($id);
        if (!$veterinario) {
            return new JsonResponse(['success' => false, 'message' => 'Veterinário não encontrado.'], 404);
        }

        $novoStatus = ($dados['status'] === 'ativo') ? 'inativo' : 'ativo';
        $veterinario->setStatus($novoStatus);

        try {
            $repo->atualizar($baseId, $veterinario);
            $label = $novoStatus === 'ativo' ? 'reativado' : 'desabilitado';
            return new JsonResponse([
                'success'    => true,
                'status'     => $novoStatus,
                'message'    => "Veterinário {$label} com sucesso.",
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erro ao alterar status: ' . $e->getMessage()], 500);
        }
    }
}
