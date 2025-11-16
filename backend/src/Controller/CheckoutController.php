<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Entity\User;
use App\Service\Payment\CryptoPaymentService;
use App\Service\Payment\PayPalService;
use App\Service\Payment\StripeService;
use App\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/checkout')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
        private PayPalService $payPalService,
        private CryptoPaymentService $cryptoService,
        private SubscriptionService $subscriptionService
    ) {
    }

    #[Route('/{plan}', name: 'api_checkout_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createCheckout(
        string $plan,
        Request $request
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $gateway = $data['gateway'] ?? 'stripe';
        $interval = $data['interval'] ?? 'monthly';
        $currency = $data['currency'] ?? 'usd';

        // Validate plan
        if (!in_array($plan, [Subscription::PLAN_PRO, Subscription::PLAN_ENTERPRISE])) {
            return $this->json([
                'error' => 'Invalid plan. Must be pro or enterprise.',
            ], 400);
        }

        try {
            switch ($gateway) {
                case 'stripe':
                    $session = $this->stripeService->createCheckoutSession(
                        $user,
                        $plan,
                        $interval
                    );

                    return $this->json([
                        'session_id' => $session->id,
                        'session_url' => $session->url,
                        'gateway' => 'stripe',
                    ]);

                case 'paypal':
                    $subscription = $this->payPalService->createSubscription(
                        $user,
                        $plan,
                        $interval
                    );

                    $approveLink = null;
                    foreach ($subscription['links'] as $link) {
                        if ($link['rel'] === 'approve') {
                            $approveLink = $link['href'];
                            break;
                        }
                    }

                    return $this->json([
                        'subscription_id' => $subscription['id'],
                        'approve_url' => $approveLink,
                        'gateway' => 'paypal',
                    ]);

                case 'crypto':
                    $cryptoCurrency = strtoupper($data['crypto_currency'] ?? 'USDT_SOLANA');
                    $amount = $this->cryptoService->calculateCryptoAmount($plan, $interval, $cryptoCurrency);
                    $payment = $this->cryptoService->createPaymentRecord($user, $cryptoCurrency, $amount, $plan);

                    return $this->json([
                        'payment_id' => $payment->getId(),
                        'wallet_address' => $this->cryptoService->getWalletAddress($cryptoCurrency),
                        'amount' => $amount,
                        'currency' => $cryptoCurrency,
                        'gateway' => 'crypto',
                    ]);

                default:
                    return $this->json([
                        'error' => 'Invalid gateway. Must be stripe, paypal, or crypto.',
                    ], 400);
            }
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to create checkout session: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/plans', name: 'api_checkout_plans', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function getPlans(): JsonResponse
    {
        $plans = $this->subscriptionService->getPlans();
        return $this->json($plans);
    }

    #[Route('/crypto/wallets', name: 'api_crypto_wallets', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function getCryptoWallets(): JsonResponse
    {
        $wallets = $this->cryptoService->getAllWallets();
        return $this->json([
            'wallets' => $wallets,
            'supported_currencies' => $this->cryptoService->getSupportedCurrencies(),
        ]);
    }

    #[Route('/crypto/payment/{id}', name: 'api_crypto_payment_details', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getCryptoPaymentDetails(int $id): JsonResponse
    {
        try {
            $details = $this->cryptoService->getPaymentDetails($id);
            return $this->json($details);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 404);
        }
    }
}
