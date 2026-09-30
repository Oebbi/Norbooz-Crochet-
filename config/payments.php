<?php

function online_payment_options(): array
{
    $options = ['manual' => 'PayID or bank transfer'];
    if (is_payment_demo_request()) {
        return $options + [
            'demo_apple_pay' => 'Apple Pay sample (no charge)',
            'demo_afterpay' => 'Afterpay sample (no charge)',
            'demo_paypal' => 'PayPal sample (no charge)',
        ];
    }
    if (STRIPE_SECRET_KEY !== '' && STRIPE_WEBHOOK_SECRET !== '' && function_exists('curl_init')) {
        $options['stripe'] = 'Card, Apple Pay or Afterpay (where available)';
    }
    if (PAYPAL_CLIENT_ID !== '' && PAYPAL_CLIENT_SECRET !== '' && PAYPAL_WEBHOOK_ID !== '' && function_exists('curl_init')) {
        $options['paypal'] = 'PayPal';
    }
    return $options;
}

function is_demo_payment_method(string $method): bool
{
    return is_payment_demo_request() && in_array($method, ['demo_apple_pay', 'demo_afterpay', 'demo_paypal'], true);
}

function is_payment_demo_request(): bool
{
    if (!is_local_request()) {
        return false;
    }
    $host = parse_url('http://' . (string)($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    return in_array(strtolower((string)$host), ['localhost', '127.0.0.1', '::1'], true);
}

function payment_icon(string $method): string
{
    $apple = '<path d="M16.3 12.6c0-2.1 1.7-3.1 1.8-3.2-1-1.5-2.6-1.7-3.2-1.7-1.4-.1-2.7.8-3.4.8-.7 0-1.8-.8-3-.8-1.6 0-3.1.9-3.9 2.3-1.7 2.9-.4 7.1 1.2 9.4.8 1.1 1.6 2.3 2.7 2.2 1.1 0 1.5-.7 2.9-.7s1.8.7 3 .7c1.2 0 1.9-1.1 2.6-2.2.8-1.3 1.2-2.5 1.2-2.6 0 0-2.9-1.1-2.9-4.2ZM14.1 6.2c.6-.7 1-1.7.9-2.7-.9 0-2 .6-2.6 1.3-.6.7-1 1.6-.9 2.6 1 .1 2-.5 2.6-1.2Z"/>';
    return match ($method) {
        'demo_apple_pay' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $apple . '</svg>',
        'demo_afterpay' => '<svg viewBox="0 0 32 24" aria-hidden="true" focusable="false"><path d="M3 3h12.5c7 0 11.5 3.7 11.5 9s-4.5 9-11.5 9H3V3Zm5 5v8h7.5c3.8 0 6-1.3 6-4s-2.2-4-6-4H8Z"/><path d="M3 3h5v18H3z" opacity=".45"/></svg>',
        'demo_paypal' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 3h7.1c4 0 6 2.1 5.4 5.7-.6 3.7-3.2 5.5-7.2 5.5h-2.1l-1 5.8H5.8L8 3Zm4.2 4-1 4h2.1c1.7 0 2.7-.7 3-2.2.2-1.2-.4-1.8-2-1.8h-2.1Z"/><path d="m5.9 5.3-2.3 13.4h4.2l.4-2.2h2.4l.6-3.5H8.8l1.3-7.7H5.9Z" opacity=".58"/></svg>',
        'stripe' => '<svg viewBox="0 0 32 24" aria-hidden="true" focusable="false">' . $apple . '<path d="M20 5h9v14h-9z" opacity=".35"/></svg>',
        'paypal' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 3h7.1c4 0 6 2.1 5.4 5.7-.6 3.7-3.2 5.5-7.2 5.5h-2.1l-1 5.8H5.8L8 3Zm4.2 4-1 4h2.1c1.7 0 2.7-.7 3-2.2.2-1.2-.4-1.8-2-1.8h-2.1Z"/><path d="m5.9 5.3-2.3 13.4h4.2l.4-2.2h2.4l.6-3.5H8.8l1.3-7.7H5.9Z" opacity=".58"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="2.5" y="5" width="19" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 9h18" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
    };
}

function payment_http(string $url, string $method, array $headers, string $body = ''): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Online payments require the PHP cURL extension.');
    }
    $curl = curl_init($url);
    $settings = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ];
    if ($method !== 'GET' || $body !== '') {
        $settings[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($curl, $settings);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $failed = $response === false;
    curl_close($curl);
    if ($failed || $status < 200 || $status >= 300) {
        error_log('Payment provider request failed with HTTP ' . $status);
        throw new RuntimeException('The payment provider is unavailable. Please try again or choose another payment method.');
    }
    $data = json_decode((string)$response, true);
    if (!is_array($data)) {
        throw new RuntimeException('The payment provider returned an invalid response.');
    }
    return $data;
}

function stripe_request(string $method, string $path, array $params = []): array
{
    $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
    if ($method === 'GET' && $body !== '') {
        $url .= '?' . $body;
        $body = '';
    }
    return payment_http($url, $method, [
        'Authorization: Basic ' . base64_encode(STRIPE_SECRET_KEY . ':'),
        'Content-Type: application/x-www-form-urlencoded',
    ], $body);
}

function paypal_api_base(): string
{
    return PAYPAL_MODE === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

function paypal_access_token(): string
{
    $response = payment_http(paypal_api_base() . '/v1/oauth2/token', 'POST', [
        'Authorization: Basic ' . base64_encode(PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET),
        'Content-Type: application/x-www-form-urlencoded',
    ], 'grant_type=client_credentials');
    return (string)($response['access_token'] ?? '');
}

function paypal_request(string $method, string $path, array $payload = []): array
{
    $token = paypal_access_token();
    if ($token === '') {
        throw new RuntimeException('PayPal authentication failed.');
    }
    return payment_http(paypal_api_base() . '/v2/' . ltrim($path, '/'), $method, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'PayPal-Request-Id: ' . bin2hex(random_bytes(16)),
    ], $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : '');
}

function create_payment_session(string $provider, int $orderId, int $amountCents, string $email): array
{
    if ($provider === 'stripe') {
        $session = stripe_request('POST', 'checkout/sessions', [
            'mode' => 'payment',
            'customer_email' => $email,
            'client_reference_id' => (string)$orderId,
            'metadata[order_id]' => (string)$orderId,
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => 'aud',
            'line_items[0][price_data][unit_amount]' => (string)$amountCents,
            'line_items[0][price_data][product_data][name]' => 'Norbooz Crochet order #' . $orderId,
            'success_url' => absolute_url('payment_return.php?provider=stripe&order_id=' . $orderId . '&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => absolute_url('payment_cancel.php?provider=stripe&order_id=' . $orderId),
        ]);
        if (empty($session['id']) || empty($session['url'])) {
            throw new RuntimeException('Stripe did not create a checkout session.');
        }
        if (parse_url((string)$session['url'], PHP_URL_HOST) !== 'checkout.stripe.com') {
            throw new RuntimeException('Stripe returned an unexpected checkout address.');
        }
        return ['reference' => (string)$session['id'], 'url' => (string)$session['url']];
    }

    if ($provider === 'paypal') {
        $order = paypal_request('POST', 'checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string)$orderId,
                'custom_id' => (string)$orderId,
                'description' => 'Norbooz Crochet order #' . $orderId,
                'amount' => ['currency_code' => 'AUD', 'value' => number_format($amountCents / 100, 2, '.', '')],
            ]],
            'application_context' => [
                'brand_name' => APP_NAME,
                'user_action' => 'PAY_NOW',
                'return_url' => absolute_url('payment_return.php?provider=paypal&order_id=' . $orderId),
                'cancel_url' => absolute_url('payment_cancel.php?provider=paypal&order_id=' . $orderId),
            ],
        ]);
        foreach (($order['links'] ?? []) as $link) {
            $host = parse_url((string)($link['href'] ?? ''), PHP_URL_HOST);
            if (($link['rel'] ?? '') === 'approve' && !empty($link['href']) && !empty($order['id'])
                && is_string($host) && (str_ends_with($host, '.paypal.com') || $host === 'paypal.com')) {
                return ['reference' => (string)$order['id'], 'url' => (string)$link['href']];
            }
        }
        throw new RuntimeException('PayPal did not create an approval link.');
    }

    throw new RuntimeException('Choose a valid payment method.');
}

function store_payment_reference(int $orderId, string $provider, string $reference): void
{
    $stmt = db()->prepare("UPDATE orders SET payment_reference = ? WHERE order_id = ? AND payment_provider = ? AND payment_status = 'unpaid'");
    $stmt->execute([$reference, $orderId, $provider]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('The order could not be linked to its payment session.');
    }
}

function cancel_unpaid_order(int $orderId, int $userId, string $note): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status, payment_status FROM orders WHERE order_id = ? AND user_id = ? FOR UPDATE');
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }
        if ($order['payment_status'] === 'paid') {
            throw new RuntimeException('This order has already been paid.');
        }
        if ($order['status'] !== 'cancelled') {
            change_order_status($pdo, $orderId, 'cancelled', $userId, $note);
        }
        $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE order_id = ? AND payment_status = 'unpaid'")
            ->execute([$orderId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function cancel_provider_order(int $orderId, string $provider, string $reference, string $note): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT user_id, status, payment_status, payment_provider, payment_reference FROM orders WHERE order_id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || $order['payment_provider'] !== $provider
            || !hash_equals((string)$order['payment_reference'], $reference)) {
            throw new RuntimeException('The payment does not match the order.');
        }
        if ($order['payment_status'] === 'paid' || $order['status'] === 'cancelled') {
            $pdo->commit();
            return false;
        }
        if ($order['payment_status'] !== 'unpaid' || $order['status'] !== 'pending') {
            throw new RuntimeException('This order is no longer awaiting payment.');
        }
        change_order_status($pdo, $orderId, 'cancelled', (int)$order['user_id'], $note);
        $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE order_id = ?")
            ->execute([$orderId]);
        $pdo->commit();
        return true;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

function complete_provider_payment(string $provider, int $orderId, string $reference): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT o.*, u.full_name, u.email FROM orders o JOIN users u ON u.user_id = o.user_id WHERE o.order_id = ? AND o.user_id = ? AND o.payment_provider = ? FOR UPDATE');
        $stmt->execute([$orderId, current_user()['user_id'], $provider]);
        $order = $stmt->fetch();
        if (!$order || !hash_equals((string)$order['payment_reference'], $reference)) {
            throw new RuntimeException('This payment does not match the order.');
        }
        if ($order['payment_status'] === 'paid') {
            $pdo->commit();
            return;
        }
        if ($order['payment_status'] !== 'unpaid' || $order['status'] !== 'pending') {
            throw new RuntimeException('This order is no longer awaiting payment.');
        }

        $amountCents = (int)round((float)$order['total_amount'] * 100);
        if ($provider === 'stripe') {
            $session = stripe_request('GET', 'checkout/sessions/' . rawurlencode($reference));
            if (($session['payment_status'] ?? '') !== 'paid'
                || (string)($session['client_reference_id'] ?? '') !== (string)$orderId
                || (int)($session['amount_total'] ?? 0) !== $amountCents
                || strtolower((string)($session['currency'] ?? '')) !== 'aud') {
                throw new RuntimeException('Stripe has not confirmed this payment.');
            }
        } elseif ($provider === 'paypal') {
            $capture = paypal_request('POST', 'checkout/orders/' . rawurlencode($reference) . '/capture');
            $purchase = $capture['purchase_units'][0] ?? [];
            $captureDetails = $purchase['payments']['captures'][0] ?? [];
            $amount = $captureDetails['amount'] ?? [];
            if (($capture['status'] ?? '') !== 'COMPLETED'
                || (string)($purchase['custom_id'] ?? '') !== (string)$orderId
                || ($captureDetails['status'] ?? '') !== 'COMPLETED'
                || strtoupper((string)($amount['currency_code'] ?? '')) !== 'AUD'
                || (int)round(((float)($amount['value'] ?? 0)) * 100) !== $amountCents) {
                throw new RuntimeException('PayPal has not confirmed this payment.');
            }
        } else {
            throw new RuntimeException('Unknown payment provider.');
        }

        $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE order_id = ?")
            ->execute([$orderId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
    notify_order_paid($order, $orderId);
}

function notify_order_paid(array $order, int $orderId): void
{
    $message = "Hi {$order['full_name']},\n\nPayment for order #$orderId has been received. We will email you as your order progresses.\n\nTotal: " . money($order['total_amount']) . "\n\nTrack your order: " . absolute_url('order_detail.php?id=' . $orderId);
    send_email($order['email'], "Payment received for order #$orderId", $message);
    send_email(SHOP_EMAIL, "Payment received for order #$orderId", $message);
}

function mark_order_paid(int $orderId, string $provider, string $reference): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT o.*, u.full_name, u.email FROM orders o JOIN users u ON u.user_id = o.user_id WHERE o.order_id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || $order['status'] !== 'pending' || $order['payment_provider'] !== $provider
            || !hash_equals((string)$order['payment_reference'], $reference)) {
            throw new RuntimeException('The payment does not match an open order.');
        }
        if ($order['payment_status'] === 'paid') {
            $pdo->commit();
            return false;
        }
        if ($order['payment_status'] !== 'unpaid') {
            throw new RuntimeException('This order is not awaiting online payment.');
        }
        $pdo->prepare("UPDATE orders SET payment_status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE order_id = ?")
            ->execute([$orderId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }

    notify_order_paid($order, $orderId);
    return true;
}