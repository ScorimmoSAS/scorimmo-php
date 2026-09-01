<?php

namespace Scorimmo\Exception;

use RuntimeException;

/**
 * Levée lorsqu'une authentification API échoue : identifiants email/password rejetés,
 * refresh token invalide ou révoqué, ou réponse 401 sur un endpoint non authentifié.
 */
class ScorimmoAuthException extends RuntimeException {}
