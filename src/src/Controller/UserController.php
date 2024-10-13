<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\UserRepository;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\Exception\ORMException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Service\TokenComparatorService;

class UserController extends AbstractController
{

    private $userRepository;
    private $passwordHasher;
    private $validator;
    private $tokenStorage;
    private $tokenComparator;

    public function __construct(UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator, TokenStorageInterface $tokenStorage, TokenComparatorService $tokenComparator)
    {

        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->validator = $validator;
        $this->tokenStorage = $tokenStorage;
        $this->tokenComparator = $tokenComparator;

    }

    #[Route('/api/register', name: 'register_user', methods: "POST")]
    public function add(Request $request): JsonResponse
    {

        $data = json_decode($request->getContent(), true);

        // Comprobar si la decodificación fue exitosa
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Datos JSON no válidos'], Response::HTTP_BAD_REQUEST);
        }

        // Obtener los campos del usuario del array decodificado
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        $name = $data['name'] ?? null;
        $surname = $data['surname'] ?? null;
        $email = $data['email'] ?? null;

        // Comprobar si el usuario ya existe
        if ($this->userRepository->userExists($username, $email)) {
            return new JsonResponse(['error' => 'Ya existe un usuario con este nombre de usuario o email registrado'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setName($name);
        $user->setSurname($surname);
        $user->setEmail($email);

        // Hashear la contraseña
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Validamos los campos del usuario
        $errors = $this->validator->validate($user);

        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            return new JsonResponse(['error' => $errorsString], Response::HTTP_BAD_REQUEST);
        }


        try {
            $this->userRepository->registerUser($user);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al registrar el usuario: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Usuario registrado'], Response::HTTP_CREATED);
    }

    #[Route('/api/user/{id}', name: 'get_user', methods: "GET")]
    public function get($id, Request $request, SerializerInterface $serializer): JsonResponse
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

        if ($id !== $user->getId()) {
            return new JsonResponse(['error' => 'No puedes ver los datos de otro usuario'], Response::HTTP_UNAUTHORIZED);
        }

        // Buscar el usuario por ID
        $user = $this->userRepository->find($id);

        // Si no existe el usuario, devolver una respuesta 404
        if (!$user) {
            return new JsonResponse(['error' => 'Usuario no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }

        // Serializar el objeto User a JSON usando el serializer de Symfony
        $data = $serializer->serialize($user, 'json');

        return new JsonResponse($data, Response::HTTP_OK);
    }

    #[Route('/api/user/{id}', name: 'update_user', methods: "PUT")]
    public function update($id, Request $request): JsonResponse
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

        if ($id !== $user->getId()) {
            return new JsonResponse(['error' => 'No puedes modificar el usuario de otra persona'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->userRepository->find($id);

        // Manejo de error si no se encuentra el usuario
        if (!$user) {
            return new JsonResponse(['error' => 'Usuario no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }

        // Decodificar el contenido JSON de la solicitud
        $data = json_decode($request->getContent(), true);

        // Comprobar si la decodificación fue exitosa
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Datos JSON no válidos'], Response::HTTP_BAD_REQUEST);
        }

        // Buscar el usuario por ID


        empty($data['username']) ? true : $user->setUsername($data['username']);
        empty($data['name']) ? true : $user->setName($data['name']);
        empty($data['surname']) ? true : $user->setSurname($data['surname']);
        empty($data['email']) ? true : $user->setEmail($data['email']);

        if (!empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        // Validamos los campos del usuario después de actualizarlos
        $errors = $this->validator->validate($user);

        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            return new JsonResponse(['error' => $errorsString], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->userRepository->updateUser($user);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al actualizar el usuario: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }


        return new JsonResponse(['status' => 'Usuario actualizado'], Response::HTTP_OK);
    }

    #[Route('/api/user/{id}', name: 'delete_user', methods: "DELETE")]
    public function delete($id, Request $request): JsonResponse
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

        if ($id !== $user->getId()) {
            return new JsonResponse(['error' => 'No puedes eliminar el usuario de otra persona'], Response::HTTP_UNAUTHORIZED);
        }

        // Buscar el usuario por ID
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuario no encontrado con el ID: ' . $id], Response::HTTP_NOT_FOUND);
        }

        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return new JsonResponse(['error' => 'No existe token de autenticación ¿Has realizado el login?'], Response::HTTP_UNAUTHORIZED);
        }

        $tokenUser = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Usuario no válido'], Response::HTTP_UNAUTHORIZED);
        }

        if ($tokenUser->getId() != $user->getId()){
            return new JsonResponse(['error' => 'No puedes eliminar un usuario que no es el tuyo'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->userRepository->deleteUser($user);
        } catch (ORMException $e) {
            return new JsonResponse(['error' => 'Error al eliminar el usuario: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 'Usuario eliminado'], Response::HTTP_OK);
    }
}