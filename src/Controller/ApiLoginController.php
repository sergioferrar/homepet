<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ApiLoginController extends DefaultController
{
    /**
     * @Route("/api/login", name="api_login")
     */
    public function login(Request $request, AuthenticationUtils $authenticationUtils): JsonResponse
    {
        // Capturar erros de autenticação, se houver
        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error) {
            return new JsonResponse(
                ['error' => $error->getMessage(), 'status' => 'danger', 'message' => $error->getMessage()],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        // Retornar informações personalizadas (opcional)
        $user = $this->security->getUser();
        // dd($user);
        if ($user) {
            return new JsonResponse([
                'message' => 'Login realizado com sucesso',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
                'direcionaHome' => $this->generateUrl('app_login_valida')
                // Você pode gerar e incluir um token JWT aqui
            ]);
        }

        return new JsonResponse(['error' => 'Credenciais inválidas'], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
