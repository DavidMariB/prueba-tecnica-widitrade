<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\ContentRepository;
use App\Repository\FavoriteRepository;
use App\Repository\RatingRepository;
use App\Entity\Content;
use App\Entity\User;
use App\Entity\Favorite;
use App\Entity\Rating;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Service\TokenComparatorService;

class ContentController extends AbstractController
{

    private $contentRepository;
    private $favoriteRepository;
    private $ratingRepository;
    private $validator;
    private $tokenStorage;
    private $tokenComparator;

    public function __construct(ContentRepository $contentRepository, FavoriteRepository $favoriteRepository, RatingRepository $ratingRepository, ValidatorInterface $validator, TokenStorageInterface $tokenStorage, TokenComparatorService $tokenComparator)
    {

        $this->contentRepository = $contentRepository;
        $this->favoriteRepository = $favoriteRepository;
        $this->ratingRepository = $ratingRepository;
        $this->validator = $validator;
        $this->tokenStorage = $tokenStorage;
        $this->tokenComparator = $tokenComparator;

    }

    #[Route('/api/content', name: 'create_content', methods: "POST")]
    public function add(Request $request): JsonResponse
    {

        // Comprobar si la decodificación fue exitosa
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Datos JSON no válidos'], Response::HTTP_BAD_REQUEST);
        }

        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        $requestData = $request->request->get('content');
        $data = json_decode($requestData, true);

        if (!$data) {
            return new JsonResponse(['error' => 'No se ha proporcionado el contenido JSON'], 400);
        }

        $requestMedia = $request->files->get('media');



        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;

        $content = new Content();
        $content->setTitle($title);
        $content->setDescription($description);
        $content->setUser($user);

        if (!empty($requestMedia)) {
            $mediaUrls = $this->uploadMedia($requestMedia);
            $content->setMediaUrls($mediaUrls);
        }

        // Validamos los campos del usuario
        $errors = $this->validator->validate($content);

        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            return new JsonResponse(['error' => $errorsString], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->contentRepository->createContent($content);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al registrar el usuario: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Contenido creado'], Response::HTTP_CREATED);
    }

    public function uploadMedia($requestMedia): array
    {

        $filePaths = [];

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/webm', 'video/ogg'];
        $uploadsDirectory = $this->getParameter('uploads_directory'); // Definimos este parámetro en config/services.yaml

        if ($requestMedia) {

            if (!is_array($requestMedia)) {
                $requestMedia = [$requestMedia]; // Convertimos en array si solo es un archivo, para evitar errores con el foreach.
            }

            foreach ($requestMedia as $file) {
                if ($file) {

                    $mimeType = $file->getMimeType();

                    if (!in_array($mimeType, $allowedMimeTypes)) {
                        throw new \Exception('Tipo de archivo no permitido');
                    }

                    if ($file->isValid()) {
                        $newFilename = uniqid() . '.' . $file->guessExtension();

                        try {
                            // Mover el archivo al directorio de destino
                            $file->move($uploadsDirectory, $newFilename);
                            // Guardar la ruta del archivo
                            $filePaths[] = $uploadsDirectory . "/" . $newFilename;
                        } catch (FileException $e) {
                            throw new \Exception('Error al subir los ficheros: ' . $e->getMessage());
                        }
                    }
                }
            }

        } else {
            throw new \Exception('No se ha proporcionado ningún archivo');
        }

        return $filePaths;

    }

    // He puesto el {id<\d+>} porque si no al ejecutar la llamada de recuperar favoritos ejecutaba esta, parece alguna confusión de rutas
    #[Route('/api/content/{id<\d+>}', name: 'get_content', methods: "GET")]
    public function get($id, Request $request, SerializerInterface $serializer): JsonResponse
    {

        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $content = $this->contentRepository->find($id);

        if (!$content) {
            return new JsonResponse(['error' => 'Contenido no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }

        $data = $serializer->serialize($content, 'json', ['groups' => 'content']);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    // Al ser un get, metémos los parámetros en la ruta
    // Ej: localhost/api/content?title=example$description=example
    #[Route('/api/content/', name: 'get_all_content', methods: "GET")]
    public function getFilteredContent(Request $request, SerializerInterface $serializer): JsonResponse
    {

        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        // Obtener los parámetros de filtro de la query (si están presentes)
        $title = $request->query->get('title', '');
        $description = $request->query->get('description', '');

        $contents = "";

        try {
            $contents = $this->contentRepository->findByFilters($title, $description);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al filtrar el contenido: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (!$contents) {
            return new JsonResponse(['error' => 'No se encontraron contenidos con los filtros proporcionados'], Response::HTTP_NOT_FOUND);
        }

        // Serializar los resultados
        $data = $serializer->serialize($contents, 'json');

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/api/content/{id}', name: 'update_content', methods: "POST")]
    public function update($id, Request $request): JsonResponse
    {
        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token->getUser();

        $content = $this->contentRepository->find($id);

        if (!$content) {
            return new JsonResponse(['error' => 'Contenido no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }


        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->getId() != $content->getUserId()) {
            return new JsonResponse(['error' => 'No puedes editar el contenido de otro usuario'], Response::HTTP_UNAUTHORIZED);
        }

        $requestData = $request->request->get('content');
        $data = json_decode($requestData, true);

        if (!$data) {
            return new JsonResponse(['error' => 'No se ha proporcionado el contenido JSON'], 400);
        }

        $requestMedia = $request->files->get('media');

        // Comprobar si la decodificación fue exitosa
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Datos JSON no válidos'], Response::HTTP_BAD_REQUEST);
        }

        empty($data['title']) ? true : $content->setTitle($data['title']);
        empty($data['description']) ? true : $content->setDescription($data['description']);
        $content->setUser($user);


        if (!empty($requestMedia)) {
            $mediaUrls = $this->uploadMedia($requestMedia);
            $content->setMediaUrls($mediaUrls);
        }

        // Validamos los campos del usuario
        $errors = $this->validator->validate($content);

        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            return new JsonResponse(['error' => $errorsString], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->contentRepository->updateContent($content);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al eliminar el contenido: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Contenido actualizado'], Response::HTTP_OK);
    }

    #[Route(path: '/api/content/{id}', name: 'delete_content', methods: "DELETE")]
    public function delete($id, Request $request): JsonResponse
    {
        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token->getUser();

        $content = $this->contentRepository->find($id);

        if (!$content) {
            return new JsonResponse(['error' => 'Contenido no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }


        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->getId() != $content->getUserId()) {
            return new JsonResponse(['error' => 'No puedes eliminar el contenido de otro usuario'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->contentRepository->deleteContent($content);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al eliminar el contenido: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Contenido eliminado'], Response::HTTP_OK);
    }

    #[Route('/api/content/{id}/favorite', name: 'content_favorite', methods: 'POST')]
    public function favorite(int $id, Request $request): JsonResponse
    {
        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        $content = $this->contentRepository->find($id);

        if (!$content) {
            return new JsonResponse(['error' => 'Contenido no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }

        $existingFavorite = $this->favoriteRepository->findOneBy(['user' => $user, 'content' => $content]);

        if ($existingFavorite) {
            return new JsonResponse(['status' => 'Este contenido ya está marcado como favorito.'], Response::HTTP_OK);
        }

        $favorite = new Favorite();
        $favorite->setUser($user);
        $favorite->setContent($content);

        try {
            $this->favoriteRepository->addFavorite($favorite);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al marcar el contenido como favorito: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Contenido marcado como favorito.'], Response::HTTP_CREATED);
    }

    #[Route('/api/content/{id}/favorite', name: 'content_delete_favorite', methods: 'DELETE')]
    public function deleteFavorite(int $id, Request $request): JsonResponse
    {
        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        $content = $this->contentRepository->find($id);

        if (!$content) {
            return new JsonResponse(['error' => 'Contenido no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }

        $existingFavorite = $this->favoriteRepository->findOneBy(['user' => $user, 'content' => $content]);

        if (!$existingFavorite) {
            return new JsonResponse(['status' => 'No tienes este contenido marcado como favorito.'], Response::HTTP_OK);
        }

        try {
            $this->favoriteRepository->deleteFavorite($existingFavorite);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al elminar el contenido como favorito: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Contenido eliminado como favorito.'], Response::HTTP_CREATED);
    }

    #[Route('/api/content/favorites', name: 'get_favorites', methods: 'GET')]
    public function getFavorites(Request $request): JsonResponse
    {
        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        $favorites = $this->favoriteRepository->findBy(['user' => $user]);

        if (!$favorites) {
            return new JsonResponse(['status' => 'El usuario no tiene contenidos favoritos.'], Response::HTTP_CREATED);
        }

        // Extraer los contenidos favoritos para devolver en la respuesta
        $contents = array_map(function ($favorite) {
            return [
                'id' => $favorite->getContent()->getId(),
                'title' => $favorite->getContent()->getTitle(),
                'description' => $favorite->getContent()->getDescription(),
                'mediaUrls' => $favorite->getContent()->getMediaUrls(),
            ];
        }, $favorites);

        return new JsonResponse($contents, Response::HTTP_OK);
    }

    #[Route('/api/content/{id}/rate', name: 'rate_content', methods: 'POST')]
    public function rateContent(int $id, Request $request): Response
    {

        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        // Buscar el contenido
        $content = $this->contentRepository->find($id);
        if (!$content) {
            return $this->json(['error' => 'No se ha encotnrado contenido con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }

        // Obtener y validar los datos de la solicitud
        $data = json_decode($request->getContent(), true);
        $ratingValue = $data['rating'] ?? null;
        $review = $data['review'] ?? null;

        if ($ratingValue === null || $ratingValue < 1 || $ratingValue > 5) {
            return $this->json(['error' => 'El rating debe estar entre 1 y 5'], Response::HTTP_BAD_REQUEST);
        }

        // Verificar si el usuario ya ha valorado este contenido
        $existingRating = $this->ratingRepository->findOneBy(['user' => $user, 'content' => $content]);

        if ($existingRating) {
            $existingRating->setRating($ratingValue);
            $existingRating->setReview($review);
            $rating = $existingRating;
        } else {
            $rating = new Rating();
            $rating->setUser($user);
            $rating->setContent($content);
            $rating->setRating($ratingValue);
            $rating->setReview($review);
        }

        $user = $this->getUser();

        // Llamar al repositorio para insertar o actualizar la calificación

        try {
            $this->ratingRepository->saveOrUpdateRating($rating);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al eliminar el contenido como favorito: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['status' => 'Valoración añadida'], Response::HTTP_CREATED);
    }

    #[Route('/api/content/{id}/rate', name: 'content_delete_rating', methods: 'DELETE')]
    public function deleteRating(int $id, Request $request): JsonResponse
    {
        // Verificar si el token en el request es válido
        if (!$this->tokenComparator->areTokensEqual($request)) {
            return new JsonResponse(['error' => 'No existe token de autenticación o es inválido. ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenStorage->getToken();
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        $content = $this->contentRepository->find($id);

        if (!$content) {
            return new JsonResponse(['error' => 'Contenido no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }

        $existingRating = $this->ratingRepository->findOneBy(['user' => $user, 'content' => $content]);

        if (!$existingRating) {
            return new JsonResponse(['status' => 'No has valorado este contenido.'], Response::HTTP_OK);
        }

        try {
            $this->ratingRepository->deleteRating($existingRating);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al eliminar la valoración: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Valoración eliminada.'], Response::HTTP_CREATED);
    }
}