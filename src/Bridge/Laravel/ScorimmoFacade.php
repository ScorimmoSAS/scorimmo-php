<?php

namespace Scorimmo\Bridge\Laravel;

use Illuminate\Support\Facades\Facade;
use Scorimmo\Client\ScorimmoClient;

/**
 * Façade Laravel exposant le singleton {@see \Scorimmo\Client\ScorimmoClient}.
 *
 * Les ressources ($leads, $appointments, $comments, $reminders, $requests, $stores, $users,
 * $customers, $status, $origins, $additionalFields, $requestFields, $form, $webCallbacks)
 * sont des propriétés publiques readonly du client : accédez-y avec la syntaxe propriété
 * (`Scorimmo::leads()->list()` ne fonctionne pas — utilisez `app(ScorimmoClient::class)->leads->list()`
 * ou l'injection typée `ScorimmoClient $scorimmo` dans vos contrôleurs).
 *
 * La façade reste utile pour les méthodes du client (getToken, refreshAccessToken,
 * validateToken, revokeToken, request, requestUnauthenticated).
 *
 * @method static string          getToken()
 * @method static ?string         getRefreshToken()
 * @method static array           refreshAccessToken(string $refreshToken)
 * @method static array           revokeToken(?string $refreshToken = null)
 * @method static array           validateToken()
 * @method static array           request(string $method, string $path, mixed $body = null)
 * @method static array           requestUnauthenticated(string $method, string $path, mixed $body = null)
 *
 * @see \Scorimmo\Client\ScorimmoClient
 */
class ScorimmoFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ScorimmoClient::class;
    }
}
