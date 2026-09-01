<?php

namespace Scorimmo\Exception;

use RuntimeException;

/**
 * Levée par {@see \Scorimmo\Webhook\ScorimmoWebhook::parse()} lorsque la signature HMAC
 * d'une requête webhook entrante est manquante ou invalide. À convertir en HTTP 401
 * côté récepteur.
 */
class WebhookAuthException extends RuntimeException {}
