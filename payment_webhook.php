<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';

function webhook_order_matches(int $orderId, string $provider, string $reference, int $amountCents): bool
{
    $stmt = db()->prepare('SELECT total_amount, payment_provider, payment_reference FROM orders WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    return $order
        && $order['payment_provider'] === $provider
        && hash_equals((string)$order['payment_reference'], $reference)
        && (int)round((float)$order['total_amount'] * 100) === $amountCents;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$provider = (string)($_GET['provider'] ?? '');
$payload = (string)file_get_contents('php://input');
try {
    if ($provider === 'stripe') {
        if (STRIPE_WEBHOOK_SECRET === '') {
            throw new RuntimeException('Stripe webhook secret is missing.');
        }
        $parts = [];
        foreach (explode(',', (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key][] = $value;
        }
        $timestamp = (int)($parts['t'][0] ?? 0);
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, STRIPE_WEBHOOK_SECRET);
        $timestampValid = $timestamp > 0 && abs(time() - $timestamp) <= 300;
        $valid = false;
        foreach ($parts['v1'] ?? [] as $candidate) {
            $valid = $valid || ($timestampValid && hash_equals($signature, $candidate));
        }
        if (!$valid) {
            http_response_code(400);
            exit;
        }
        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $session = $event['data']['object'] ?? [];
        $eventType = (string)($event['type'] ?? '');
        $orderId = (int)($session['client_reference_id'] ?? 0);
        $reference = (string)($session['id'] ?? '');
        if (in_array($eventType, ['checkout.session.expired', 'checkout.session.async_payment_failed'], true)
            && $orderId > 0 && $reference !== '') {
            cancel_provider_order($orderId, 'stripe', $reference, 'Stripe checkout expired or failed');
        }
        if (in_array($eventType, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && ($session['payment_status'] ?? '') === 'paid') {
            $amount = (int)($session['amount_total'] ?? 0);
            if (strtolower((string)($session['currency'] ?? '')) !== 'aud'
                || (string)($session['metadata']['order_id'] ?? '') !== (string)$orderId
                || !webhook_order_matches($orderId, 'stripe', $reference, $amount)) {
                http_response_code(400);
                exit;
            }
            mark_order_paid($orderId, 'stripe', $reference);
        }
    } elseif ($provider === 'paypal') {
        if (PAYPAL_WEBHOOK_ID === '') {
            throw new RuntimeException('PayPal webhook ID is missing.');
        }
        $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        $verification = paypal_request('POST', 'notifications/verify-webhook-signature', [
            'auth_algo' => (string)($_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? ''),
            'cert_url' => (string)($_SERVER['HTTP_PAYPAL_CERT_URL'] ?? ''),
            'transmission_id' => (string)($_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? ''),
            'transmission_sig' => (string)($_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? ''),
            'transmission_time' => (string)($_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? ''),
            'webhook_id' => PAYPAL_WEBHOOK_ID,
            'webhook_event' => $event,
        ]);
        if (($verification['verification_status'] ?? '') !== 'SUCCESS') {
            http_response_code(400);
            exit;
        }
        if (($event['event_type'] ?? '') === 'CHECKOUT.ORDER.VOIDED') {
            $reference = (string)($event['resource']['id'] ?? '');
            $stmt = db()->prepare("SELECT order_id FROM orders WHERE payment_provider = 'paypal' AND payment_reference = ?");
            $stmt->execute([$reference]);
            $orderId = (int)$stmt->fetchColumn();
            if ($orderId && $reference !== '') {
                cancel_provider_order($orderId, 'paypal', $reference, 'PayPal checkout was voided');
            }
        }
        if (($event['event_type'] ?? '') === 'PAYMENT.CAPTURE.COMPLETED') {
            $capture = $event['resource'] ?? [];
            $reference = (string)($capture['supplementary_data']['related_ids']['order_id'] ?? '');
            $stmt = db()->prepare("SELECT order_id FROM orders WHERE payment_provider = 'paypal' AND payment_reference = ?");
            $stmt->execute([$reference]);
            $orderId = (int)$stmt->fetchColumn();
            $amount = $capture['amount'] ?? [];
            $amountCents = (int)round(((float)($amount['value'] ?? 0)) * 100);
            if (!$orderId || strtoupper((string)($amount['currency_code'] ?? '')) !== 'AUD'
                || !webhook_order_matches($orderId, 'paypal', $reference, $amountCents)) {
                http_response_code(400);
                exit;
            }
            mark_order_paid($orderId, 'paypal', $reference);
        }
    } else {
        http_response_code(404);
        exit;
    }
    http_response_code(200);
    echo 'ok';
} catch (Throwable $ex) {
    error_log('Payment webhook failed: ' . $ex->getMessage());
    http_response_code(500);
    echo 'error';
}