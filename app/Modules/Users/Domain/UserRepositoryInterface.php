<?php

declare(strict_types=1);

namespace App\Modules\Users\Domain;

/**
 * UserRepositoryInterface — domain-level persistence contract.
 *
 * BOUNDARY: Only the Users module may implement or inject this interface.
 * Cross-module consumers MUST use UserDirectoryQueryInterface instead.
 */
interface UserRepositoryInterface
{
    /**
     * Find an active user by their platform UUID.
     * Returns null when no user exists for the given id.
     */
    public function findById(string $id): ?User;

    /**
     * Find a user by their email address (any status).
     * Returns null when no user exists with the given email.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find a user by their identity-provider credentials.
     * Used for upsert-on-auth flows.
     */
    public function findByProvider(string $provider, string $providerSubject): ?User;

    /**
     * Persist a user entity. INSERT on first save; UPDATE on subsequent saves.
     * Implementations must be idempotent for the same user id.
     */
    public function save(User $user): void;

    /**
     * Check whether an email address is already registered.
     * More efficient than findByEmail when only existence is needed.
     */
    public function emailExists(string $email): bool;

    /**
     * Check whether a provider+subject pair is already registered.
     * Used for duplicate-detection during bulk imports.
     */
    public function providerSubjectExists(string $provider, string $providerSubject): bool;
}
