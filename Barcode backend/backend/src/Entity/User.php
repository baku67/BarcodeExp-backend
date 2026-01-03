<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?bool $isVerified = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verificationEmailLastSentAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $preferences = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $preferencesUpdatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getVerificationEmailLastSentAt(): ?\DateTimeImmutable
    {
        return $this->verificationEmailLastSentAt;
    }

    public function setVerificationEmailLastSentAt(?\DateTimeImmutable $verificationEmailLastSentAt): static
    {
        $this->verificationEmailLastSentAt = $verificationEmailLastSentAt;

        return $this;
    }

    public function getPreferences(): array
    {
        return $this->preferences ?? [];
    }

    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    public function getPreferencesUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->preferencesUpdatedAt;
    }

    public function setPreferencesUpdatedAt(): self
    {
        $this->preferencesUpdatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPrefTheme(): string
    {
        $t = $this->getPreferences()['theme'] ?? 'system';
        return in_array($t, ['system','light','dark'], true) ? $t : 'system';
    }

    public function getPrefLang(): string
    {
        $l = $this->getPreferences()['lang'] ?? 'fr';
        return is_string($l) && $l !== '' ? $l : 'fr';
    }

    public function getPrefFrigoLayout(): string
    {
        $v = $this->getPreferences()['frigo_layout'] ?? 'list';
        return in_array($v, ['list','design'], true) ? $v : 'list';
    }

    public function setPref(string $key, mixed $value): self
    {
        $prefs = $this->getPreferences();
        $prefs[$key] = $value;
        $this->setPreferences($prefs);
        $this->setPreferencesUpdatedAt();
        return $this;
    }
}
