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
 */
class ScorimmoApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string|int|null $apiCode = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}
