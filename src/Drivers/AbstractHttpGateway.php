<?php

declare(strict_types=1);

namespace Alsuhaibi\SaudiPayments\Drivers;

use Alsuhaibi\SaudiPayments\Contracts\PaymentGateway;
use Alsuhaibi\SaudiPayments\Enums\PaymentStatus;
use Alsuhaibi\SaudiPayments\Exceptions\GatewayRequestException;
use Alsuhaibi\SaudiPayments\Exceptions\InvalidWebhookException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;

/**
 * Shared plumbing for HTTP-based gateways: configured HTTP client, error handling
 * and HMAC-SHA256 webhook verification.
 *
 * Each concrete driver maps our DTOs to the provider's payload. Those payloads are
 * intentionally minimal: adapt field names, endpoints and signing to the provider's
 * current API documentation before going live.
 */
abstract class AbstractHttpGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config  the gateway's block from config/saudi-payments.php
     * @param  array{timeout?: int, retries?: int, retry_sleep_ms?: int}  $httpOptions
     */
    public function __construct(
        protected readonly string $key,
        protected readonly array $config,
        protected readonly HttpFactory $http,
        protected readonly array $httpOptions = [],
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * Default verification: hex HMAC-SHA256 of the raw body, compared in constant time.
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) ($this->config['webhook_secret'] ?? '');
        $header = (string) ($this->config['signature_header'] ?? 'X-Signature');
        $provided = (string) $request->header($header, '');

        if ($secret === '' || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, strtolower(trim($provided)));
    }

    protected function client(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->timeout($this->httpOptions['timeout'] ?? 15)
            // Retry only on connection errors: retrying a POST that reached the provider could create a second checkout.
            ->retry(
                $this->httpOptions['retries'] ?? 2,
                $this->httpOptions['retry_sleep_ms'] ?? 300,
                fn (\Throwable $e): bool => $e instanceof ConnectionException,
                throw: false,
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(PendingRequest $client, string $path, array $payload): array
    {
        try {
            $response = $client->post($path, $payload);
        } catch (ConnectionException $e) {
            throw GatewayRequestException::failed($this->key, 0, $e->getMessage());
        }

        if ($response->failed()) {
            throw GatewayRequestException::failed($this->key, $response->status(), $this->errorReason($response));
        }

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function requireField(array $data, string $path): mixed
    {
        $value = data_get($data, $path);

        if ($value === null || $value === '') {
            throw GatewayRequestException::unexpectedResponse($this->key, $path);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function requireWebhookField(array $payload, string $path): string
    {
        $value = data_get($payload, $path);

        if ($value === null || $value === '') {
            throw InvalidWebhookException::missingField($this->key, $path);
        }

        return (string) $value;
    }

    /**
     * @param  array<string, PaymentStatus>  $map  provider status (lower-case) => normalized status
     */
    protected function mapStatus(string $providerStatus, array $map): PaymentStatus
    {
        return $map[strtolower($providerStatus)] ?? PaymentStatus::Pending;
    }

    protected function errorReason(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            foreach (['message', 'error', 'errors.0.message', 'result'] as $path) {
                $value = data_get($json, $path);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return substr((string) $response->body(), 0, 200);
    }
}
