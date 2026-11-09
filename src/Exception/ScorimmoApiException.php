<?php

namespace Scorimmo\Exception;

use RuntimeException;

/**
 * Levée lorsque l'API Scorimmo renvoie une réponse HTTP non-2xx, ou lorsqu'une erreur
 * réseau (timeout, DNS, connexion refusée) empêche d'atteindre l'API.
 *
 * - `$statusCode` : code HTTP renvoyé par l'API (0 pour une erreur réseau)
 * - `$apiCode`    : identifiant d'erreur applicatif éventuellement retourné dans le body JSON
 *                   (ex: 'VALIDATION_ERROR', 'FORBIDDEN', 'NOT_FOUND', ou un entier)
 * - `$body`       : corps JSON décodé de la réponse d'erreur (vide pour une erreur réseau)
 * - `$retryAfter` : délai en secondes indiqué par le header `Retry-After` (429), sinon null
 */
class ScorimmoApiException extends RuntimeException
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string|int|null $apiCode = null,
        public readonly array $body = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Retourne le détail des champs refusés d'une erreur de validation (422).
     *
     * @return array<int, array{field: string, message: string}>
     */
    public function getFieldErrors(): array
    {
        return $this->body['errors'] ?? [];
    }
}
