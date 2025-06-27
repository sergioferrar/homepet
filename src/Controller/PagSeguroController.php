<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Estabelecimento;

class PagSeguroController extends DefaultController
{

    /**
     * @Route("/pagseguro/retorno", name="pagseguro_retorno")
    */
    public function notificacao(Request $request, EntityManagerInterface $em): Response
    {
        $notificationCode = $request->request->get('notificationCode');
        if (!$notificationCode) {
            return new Response('Código de notificação ausente', 400);
        }

        $emEstabelecimento = $this->getRepositorio(\App\Entity\Estabelecimento::class);
        $emUsuario = $this->getRepositorio(\App\Entity\Usuario::class);

        $this->getRepositorio(\App\Entity\Estabelecimento::class)
        ->aprovacao(
            $request->getSession()->get('finaliza')['uid'], 
            $request->getSession()->get('finaliza')['eid']
        );
        // Consulta no PagSeguro o status da transação
        // Essa parte seria outro método no serviço: consultarTransacao($notificationCode)

        // Simula o processamento do pagamento
        // Aqui você deveria fazer: validar status = "aprovado", e ativar o plano
        // Exemplo:
        // $estabelecimento = $em->getRepository(Estabelecimento::class)->findOneBy([...]);
        // $estabelecimento->setStatus('ativo');
        // $em->flush();
        return $this->render('estabelecimento/confirmacao.html.twig', [
            'estabelecimento' => $request->getSession()->get('finaliza')['eid'],
        ]);
        // return new Response('Notificação recebida e processada', 200);
    }
}
