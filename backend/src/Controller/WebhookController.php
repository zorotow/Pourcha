<?php

namespace App\Controller;

use App\Service\Payment\PayPalService;
use App\Service\Payment\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/webhooks')]
class WebhookController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private PayPalService $payPalService
    ) {
    }

    #[Route('/stripe', name: 'api_webhook_stripe', methods: ['POST'])]
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('stripe-signature');

        if (!$signature) {
            return $this->json([
                'error' => 'Missing signature header',
            ], 400);
        }

        try {
            $this->stripeService->handleWebhook($payload, $signature);

            return $this->json([
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route('/paypal', name: 'api_webhook_paypal', methods: ['POST'])]
    public function paypalWebhook(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!$payload) {
            return $this->json([
                'error' => 'Invalid payload',
            ], 400);
        }

        try {
            $this->payPalService->handleWebhook($payload);

            return $this->json([
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
