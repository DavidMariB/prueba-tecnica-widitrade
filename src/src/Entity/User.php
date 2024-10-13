<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "El username no puede estar vacío")]
    #[Groups(['user'])]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La contraseña no puede estar vacía")]
    #[Groups(['user'])]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "El nombre no puede estar vacío")]
    #[Groups(['user'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "El apellido no puede estar vacío")]
    #[Groups(['user'])]
    private ?string $surname = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "El email no puede estar vacío")]
    #[Assert\Email(message: "El email '{{ value }}' no es válido.")]
    #[Groups(['user'])]
    private ?string $email = null;

    #[ORM\Column(type: "json")]
    #[Groups(['user'])]
    private ?array $roles = ["ROLE_USER"];

    // Métodos requeridos por las interfaces

    public function getId(): int
    {
        return $this->id;
    }
    
    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): static
    {
        $this->surname = $surname;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    // Métodos de UserInterface
    public function getRoles(): array
    {
        return array_unique(array_merge($this->roles, ['ROLE_USER']));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }


    public function eraseCredentials(): void
    {
        // En un principio no lo voy a usar, pero es necesario para la implementación de UserInterface
    }

    public function getSalt(): ?string
    {
        return null;
    }

    // Métodos de PasswordAuthenticatedUserInterface
    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}