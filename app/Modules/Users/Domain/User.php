<?php

declare(strict_types=1);

namespace App\Modules\Users\Domain;

/**
 * User aggregate root — authoritative identity record.
 *
 * Invariants:
 *  - email must be 1-320 characters
 *  - status must be one of: active, suspended, deactivated
 *  - displayName must be 1-100 characters
 *  - bio must be ≤ 1000 characters
 *  - location must be ≤ 200 characters
 *
 * This entity is internal to the Users module.
 * Cross-module consumers must use UserDto via UserDirectoryQueryInterface.
 */
final class User
{
    public const STATUS_ACTIVE      = 'active';
    public const STATUS_SUSPENDED   = 'suspended';
    public const STATUS_DEACTIVATED = 'deactivated';

    /** @var list<string> */
    private const VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_DEACTIVATED,
    ];

    private function __construct(
        private readonly string  $id,
        private readonly string  $email,
        private string           $displayName,
        private string           $status,
        private ?string          $bio,
        private ?string          $avatarPath,
        private ?string          $location,
        private readonly string  $provider,
        private readonly string  $providerSubject,
        /** @var string[] */
        private array            $roles,
    ) {}

    // ------------------------------------------------------------------
    // Factory methods
    // ------------------------------------------------------------------

    /**
     * Create a brand-new user. Status defaults to 'active'.
     */
    public static function create(
        string $id,
        string $email,
        string $displayName,
        string $provider,
        string $providerSubject,
    ): self {
        self::assertEmail($email);
        self::assertDisplayName($displayName);

        return new self(
            id:              $id,
            email:           $email,
            displayName:     $displayName,
            status:          self::STATUS_ACTIVE,
            bio:             null,
            avatarPath:      null,
            location:        null,
            provider:        $provider,
            providerSubject: $providerSubject,
            roles:           [],
        );
    }

    /**
     * Reconstitute a user from persisted data. Skips invariant re-validation
     * for fields owned by the DB (status, roles) to allow admin-set values.
     *
     * @param string[] $roles
     */
    public static function reconstitute(
        string  $id,
        string  $email,
        string  $displayName,
        string  $status,
        ?string $bio,
        ?string $avatarPath,
        ?string $location,
        string  $provider,
        string  $providerSubject,
        array   $roles,
    ): self {
        return new self(
            id:              $id,
            email:           $email,
            displayName:     $displayName,
            status:          $status,
            bio:             $bio,
            avatarPath:      $avatarPath,
            location:        $location,
            provider:        $provider,
            providerSubject: $providerSubject,
            roles:           array_values($roles),
        );
    }

    // ------------------------------------------------------------------
    // Business methods
    // ------------------------------------------------------------------

    /**
     * Update mutable profile fields. Only non-null arguments are applied.
     */
    public function updateProfile(
        ?string $displayName,
        ?string $bio,
        ?string $avatarPath,
        ?string $location,
    ): void {
        if ($displayName !== null) {
            self::assertDisplayName($displayName);
            $this->displayName = $displayName;
        }

        if ($bio !== null) {
            if (strlen($bio) > 1000) {
                throw new \InvalidArgumentException('bio must be ≤ 1000 characters.');
            }
            $this->bio = $bio;
        }

        if ($avatarPath !== null) {
            $this->avatarPath = $avatarPath;
        }

        if ($location !== null) {
            if (strlen($location) > 200) {
                throw new \InvalidArgumentException('location must be ≤ 200 characters.');
            }
            $this->location = $location;
        }
    }

    /**
     * Deactivate the user. Idempotent if already deactivated.
     */
    public function deactivate(): void
    {
        if ($this->status === self::STATUS_DEACTIVATED) {
            return;
        }

        $this->status = self::STATUS_DEACTIVATED;
    }

    // ------------------------------------------------------------------
    // Accessors
    // ------------------------------------------------------------------

    public function getId(): string { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getDisplayName(): string { return $this->displayName; }
    public function getStatus(): string { return $this->status; }
    public function getBio(): ?string { return $this->bio; }
    public function getAvatarPath(): ?string { return $this->avatarPath; }
    public function getLocation(): ?string { return $this->location; }
    public function getProvider(): string { return $this->provider; }
    public function getProviderSubject(): string { return $this->providerSubject; }

    /** @return string[] */
    public function getRoles(): array { return $this->roles; }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // ------------------------------------------------------------------
    // Private guards
    // ------------------------------------------------------------------

    private static function assertEmail(string $email): void
    {
        if ($email === '' || strlen($email) > 320) {
            throw new \InvalidArgumentException('email must be 1-320 characters.');
        }
    }

    private static function assertDisplayName(string $name): void
    {
        if ($name === '' || strlen($name) > 100) {
            throw new \InvalidArgumentException('displayName must be 1-100 characters.');
        }
    }
}
