<?php

namespace App\Controller;

use App\Entity\Servico;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Esta versão da controller inclui suporte ao novo campo `tipo` (clinica ou pet_shop)
 * e permite filtrar a listagem de serviços de acordo com esse valor. Note que
 * você precisa atualizar o repositório para aceitar o parâmetro de tipo na
 * busca, bem como adaptar as entidades e formulários conforme descrito no
 * arquivo de orientações.
 */
class ServicoController extends DefaultController
{
    /**
     * @Route("/servico", name="servico_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $this->switchDB();
        $userId = $this->session->get('userId');
        $repo = $this->getRepositorio(Servico::class);

        // Captura o parâmetro 'tipo' da query string para filtrar a listagem
        $tipoFiltro = $request->query->get('tipo');
        if ($tipoFiltro) {
            // Ajuste findAllService para aceitar o parâmetro de filtro ou use findBy
            $servicos = $repo->findBy([
                'estabelecimento_id' => $userId,
                'tipo' => $tipoFiltro
            ]);
        } else {
            $servicos = $repo->findAllService($userId);
        }

        return $this->render('servico/index.html.twig', [
            'servicos' => $servicos,
            'tipo'     => $tipoFiltro,
        ]);
    }

    /**
     * @Route("/servico/novo", name="servico_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        $this->switchDB();
        if ($request->isMethod('POST')) {
            $servico = new Servico();
            $servico->setNome($request->request->get('nome'));
            $servico->setDescricao($request->request->get('descricao'));
            $servico->setValor((float)$request->request->get('valor'));

            // Lê o tipo enviado pelo formulário; padrão 'clinica' se não enviado
            $tipo = $request->request->get('tipo', 'clinica');
            $servico->setTipo($tipo);

            $this->getRepositorio(Servico::class)->save($this->session->get('userId'), $servico);
            return $this->redirectToRoute('servico_index');
        }

        return $this->render('servico/novo.html.twig');
    }

    /**
     * @Route("/servico/editar/{id}", name="servico_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $this->switchDB();
        $servico = $this->getRepositorio(Servico::class)->findService($this->session->get('userId'), $id);

        if (!$servico) {
            throw $this->createNotFoundException('O serviço não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $servico->setNome($request->request->get('nome'));
            $servico->setDescricao($request->request->get('descricao'));
            $servico->setValor((float)$request->request->get('valor'));
            // Atualiza o tipo a partir do formulário
            $tipo = $request->request->get('tipo', 'clinica');
            $servico->setTipo($tipo);

            $this->getRepositorio(Servico::class)->update($this->session->get('userId'), $servico);
            return $this->redirectToRoute('servico_index');
        }

        return $this->render('servico/editar.html.twig', [
            'servico' => $servico
        ]);
    }

    /**
     * @Route("/servico/deletar/{id}", name="servico_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $this->switchDB();
        $servico = $this->getRepositorio(Servico::class)->findService($this->session->get('userId'), $id);

        if (!$servico) {
            throw $this->createNotFoundException('O serviço não foi encontrado');
        }

        $this->getRepositorio(Servico::class)->delete($this->session->get('userId'), $id);
        return $this->redirectToRoute('servico_index');
    }
}