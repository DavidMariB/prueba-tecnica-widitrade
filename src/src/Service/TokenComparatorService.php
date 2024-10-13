<?php 

namespace App\Service;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Request;

class TokenComparatorService
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function areTokensEqual(Request $request): bool
    {
        // Obtener el token almacenado en TokenStorage
        $storedToken = $this->tokenStorage->getToken();
        if (!$storedToken) {
            return false;
        }

        // Extraer el token del header Authorization en el request
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }
        $requestToken = substr($authHeader, 7); // Remover "Bearer "

        // Comparar ambos tokens
        return $storedToken->getCredentials() === $requestToken;
    }
}
