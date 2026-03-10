<?php

namespace Scorimmo\Bridge\Laravel\Http;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;
use Scorimmo\Webhook\ScorimmoWebhook;

class ScorimmoWebhookController extends Controller
{
    public function __construct(private readonly ScorimmoWebhook $webhook) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->webhook->parse(
                $request->headers->all(),
                $request->getContent(),
            );
        } catch (WebhookAuthException) {
            return response()->json(['error' => 'Unauthorized'], 401);
        } catch (WebhookValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        // Dispatch un événement Laravel que l'intégrateur écoute librement
        event('scorimmo.' . $event['event'], $event);

        return response()->json(['ok' => true]);
    }
}
