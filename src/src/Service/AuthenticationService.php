<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use App\Entity\User;

class AuthenticationService
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function getAuthenticatedUser(Request $request): ?User
    {
        // Obtener el token almacenado en TokenStorage
        $storedToken = $this->tokenStorage->getToken();
        if (!$storedToken) {
            throw new AuthenticationException('No existe token almacenado. ¿Has iniciado sesión?');
        }

        // Extraer el token del header Authorization en el request
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('No se ha proporcionado un token de autorización en el Request.');
        }

        $requestToken = substr($authHeader, 7); // Remover "Bearer "

        // Comparar ambos tokens
        if ($storedToken->getCredentials() === $requestToken) {
            $user = $storedToken->getUser();

            if (!$user instanceof User) {
                throw new AuthenticationException('Usuario no válido.');
            }

            return $user;
        }

    }
}
