<?php

namespace Scorimmo\Exception;

use RuntimeException;

/**
 * Levée par {@see \Scorimmo\Webhook\ScorimmoWebhook::parse()} lorsque le corps de la requête
 * webhook n'est pas un JSON valide, ou lorsque le champ obligatoire `event` est absent
 * ou vide. À convertir en HTTP 400 côté récepteur.
 */
class WebhookValidationException extends RuntimeException {}
