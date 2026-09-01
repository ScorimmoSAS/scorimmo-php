<?php

namespace Scorimmo\Bridge\Laravel\Http;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;
use Scorimmo\Webhook\ScorimmoWebhook;

/**
 * Contrôleur générique de réception des webhooks Scorimmo.
 *
 * Parse et vérifie la requête via {@see ScorimmoWebhook}, puis dispatche un événement Laravel
 * `scorimmo.<event>` que l'intégrateur écoute librement (ex: `scorimmo.new_lead`,
 * `scorimmo.update_lead`). Renvoie 401 en cas de signature invalide, 400 en cas de payload
 * mal formé, 200 sinon.
 *
 * La route associée est enregistrée automatiquement par {@see \Scorimmo\Bridge\Laravel\ScorimmoServiceProvider}
 * sur `POST {webhook_path}` (par défaut `webhook/scorimmo`).
 */
class ScorimmoWebhookController extends Controller
{
    public function __construct(private readonly ScorimmoWebhook $webhook) {}

    public function __invoke(Request $request): JsonResponse
    {
        // headers->all() renvoie array<string, string[]> ; on aplatit en array<string, string>
        $headers = array_map(fn(array $v) => $v[0] ?? '', $request->headers->all());

        try {
            $event = $this->webhook->parse(
                $headers,
                $request->getContent(),
            );
        } catch (WebhookAuthException) {
            return response()->json(['error' => 'Unauthorized'], 401);
        } catch (WebhookValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        // Dispatch d'un événement Laravel que l'intégrateur écoute librement.
        event('scorimmo.' . $event['event'], $event);

        return response()->json(['ok' => true]);
    }
}
