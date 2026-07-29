<?php

namespace App\Controller;

use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Página pública onde o usuário recém-cadastrado define a própria senha
 * a partir do link enviado por e-mail. Não fica sob o prefixo /dashboard
 * e é liberada no security.yaml (acesso público).
 */
class DefinirSenhaController extends AbstractController
{
    /**
     * @Route("/usuario/definir-senha/{token}", name="usuario_definir_senha", methods={"GET"})
     */
    public function form(string $token, EntityManagerInterface $em): Response
    {
        $usuario = $em->getRepository(Usuario::class)->findOneBy(['tokenSenha' => $token]);

        $erro = $this->validarToken($usuario);

        return $this->render('usuario/definir_senha.html.twig', [
            'token' => $token,
            'erro' => $erro,
            'nome' => $usuario && !$erro ? $usuario->getNomeUsuario() : null,
        ]);
    }

    /**
     * @Route("/usuario/definir-senha/{token}", name="usuario_definir_senha_salvar", methods={"POST"})
     */
    public function salvar(string $token, Request $request, EntityManagerInterface $em): Response
    {
        $usuario = $em->getRepository(Usuario::class)->findOneBy(['tokenSenha' => $token]);

        $erro = $this->validarToken($usuario);
        if ($erro) {
            return $this->render('usuario/definir_senha.html.twig', [
                'token' => $token, 'erro' => $erro, 'nome' => null,
            ]);
        }

        $senha = (string) $request->get('senha');
        $confirmar = (string) $request->get('confirmar_senha');

        $problema = null;
        if (strlen($senha) < 6) {
            $problema = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif ($senha !== $confirmar) {
            $problema = 'As senhas não conferem.';
        }

        if ($problema) {
            return $this->render('usuario/definir_senha.html.twig', [
                'token' => $token,
                'erro' => null,
                'nome' => $usuario->getNomeUsuario(),
                'problema' => $problema,
            ]);
        }

        $usuario->setSenha(password_hash($senha, PASSWORD_DEFAULT, ['cost' => 10]));
        $usuario->setAtivo(true);
        $usuario->setTokenSenha(null);
        $usuario->setTokenExpira(null);
        $em->flush();

        return $this->render('usuario/definir_senha.html.twig', [
            'token' => $token,
            'erro' => null,
            'nome' => $usuario->getNomeUsuario(),
            'sucesso' => true,
        ]);
    }

    /**
     * Retorna uma mensagem de erro se o token for inválido/expirado; null se ok.
     */
    private function validarToken(?Usuario $usuario): ?string
    {
        if (!$usuario) {
            return 'Link inválido. Solicite um novo cadastro ao administrador.';
        }
        $expira = $usuario->getTokenExpira();
        if ($expira instanceof \DateTimeInterface && $expira < new \DateTime()) {
            return 'Este link expirou. Solicite um novo cadastro ao administrador.';
        }
        return null;
    }
}