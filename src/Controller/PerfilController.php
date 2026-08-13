<?php

namespace App\Controller;

use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Área de perfil: o usuário logado edita seus próprios dados e troca a senha.
 * A tabela usuario fica no banco de login (não usamos switchDB aqui).
 *
 * @
 */
class PerfilController extends DefaultController
{
    /**
     * @
     */
    #[Route('/dashboard/perfil', name: 'perfil_usuario', methods: "{GET}")]
    public function index(): Response
    {
        return $this->render('usuario/perfil.html.twig', [
            'usuario' => $this->getUser(),
        ]);
    }
    /**
     * @
     */
    #[Route('/dashboard/perfil/dados', name: 'perfil_salvar_dados')]
    public function salvarDados(Request $request, EntityManagerInterface $em): Response
    {
        $usuario = $em->getRepository(Usuario::class)->find($this->getUser()->getId());
        if (!$usuario) {
            $this->addFlash('danger', 'Usuário não encontrado.');
            return $this->redirectToRoute('perfil_usuario');
        }

        $nome = trim((string) $request->get('nome_usuario'));
        $email = trim((string) $request->get('email'));

        if ($nome === '' || $email === '') {
            $this->addFlash('warning', 'Nome e e-mail são obrigatórios.');
            return $this->redirectToRoute('perfil_usuario');
        }

        // Evita e-mail duplicado (é o identificador de login)
        $existe = $em->getRepository(Usuario::class)->findOneBy(['email' => $email]);
        if ($existe && $existe->getId() !== $usuario->getId()) {
            $this->addFlash('warning', 'Já existe um usuário com este e-mail.');
            return $this->redirectToRoute('perfil_usuario');
        }

        $usuario->setNomeUsuario($nome);
        $usuario->setEmail($email);
        $em->flush();

        $this->addFlash('success', 'Dados atualizados com sucesso!');
        return $this->redirectToRoute('perfil_usuario');
    }
    /**
     * @
     */
    #[Route('/dashboard/perfil/senha', name: 'perfil_alterar_senha')]
    public function alterarSenha(Request $request, EntityManagerInterface $em): Response
    {
        $usuario = $em->getRepository(Usuario::class)->find($this->getUser()->getId());
        if (!$usuario) {
            $this->addFlash('danger', 'Usuário não encontrado.');
            return $this->redirectToRoute('perfil_usuario');
        }

        $atual = (string) $request->get('senha_atual');
        $nova = (string) $request->get('nova_senha');
        $confirmar = (string) $request->get('confirmar_senha');

        if (!password_verify($atual, $usuario->getSenha())) {
            $this->addFlash('warning', 'A senha atual está incorreta.');
            return $this->redirectToRoute('perfil_usuario');
        }
        if (strlen($nova) < 6) {
            $this->addFlash('warning', 'A nova senha deve ter pelo menos 6 caracteres.');
            return $this->redirectToRoute('perfil_usuario');
        }
        if ($nova !== $confirmar) {
            $this->addFlash('warning', 'A confirmação da nova senha não confere.');
            return $this->redirectToRoute('perfil_usuario');
        }

        $usuario->setSenha(password_hash($nova, PASSWORD_DEFAULT, ['cost' => 10]));
        $em->flush();

        $this->addFlash('success', 'Senha alterada com sucesso!');
        return $this->redirectToRoute('perfil_usuario');
    }
}
