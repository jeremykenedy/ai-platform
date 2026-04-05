<?php

declare(strict_types=1);

namespace App\Services\Integrations\Finance;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalService extends AbstractIntegrationService
{
    protected string $integrationName = 'paypal';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'list_transactions',
                'description' => 'List PayPal transactions within a date range.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'startDate' => [
                            'type' => 'string',
                            'description' => 'Start date in ISO 8601 format (e.g. 2024-01-01T00:00:00Z).',
                        ],
                        'endDate' => [
                            'type' => 'string',
                            'description' => 'End date in ISO 8601 format (e.g. 2024-01-31T23:59:59Z).',
                        ],
                        'page' => [
                            'type' => 'integer',
                            'description' => 'Page number for pagination (default 1).',
                        ],
                    ],
                    'required' => ['startDate', 'endDate'],
                ],
            ],
            [
                'name' => 'get_transaction',
                'description' => 'Retrieve details of a specific PayPal transaction.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'transactionId' => [
                            'type' => 'string',
                            'description' => 'The PayPal transaction ID.',
                        ],
                    ],
                    'required' => ['transactionId'],
                ],
            ],
            [
                'name' => 'create_invoice',
                'description' => 'Create a PayPal invoice for a recipient with line items.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'description' => 'Array of invoice line items.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'quantity' => ['type' => 'string'],
                                    'unit_amount' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'currency_code' => ['type' => 'string'],
                                            'value' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'recipientEmail' => [
                            'type' => 'string',
                            'description' => 'Email address of the invoice recipient.',
                        ],
                    ],
                    'required' => ['items', 'recipientEmail'],
                ],
            ],
            [
                'name' => 'send_invoice',
                'description' => 'Send a previously created PayPal invoice to the recipient.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'invoiceId' => [
                            'type' => 'string',
                            'description' => 'The ID of the invoice to send.',
                        ],
                    ],
                    'required' => ['invoiceId'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_transactions' => $this->listTransactions($user, $params),
            'get_transaction' => $this->getTransaction($user, $params),
            'create_invoice' => $this->createInvoice($user, $params),
            'send_invoice' => $this->sendInvoice($user, $params),
            default => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listTransactions(User $user, array $params): array
    {
        $startDate = $params['startDate'] ?? throw new RuntimeException('startDate is required.');
        $endDate = $params['endDate'] ?? throw new RuntimeException('endDate is required.');
        $page = isset($params['page']) ? (int) $params['page'] : 1;

        $response = Http::withToken($this->getAccessToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get($this->baseUrl().'/v1/reporting/transactions', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'page' => $page,
                'page_size' => 100,
                'fields' => 'all',
            ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getTransaction(User $user, array $params): array
    {
        $transactionId = $params['transactionId'] ?? throw new RuntimeException('transactionId is required.');

        $response = Http::withToken($this->getAccessToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->get($this->baseUrl().'/v1/reporting/transactions', [
                'transaction_id' => $transactionId,
                'start_date' => now()->subYears(2)->toIso8601String(),
                'end_date' => now()->toIso8601String(),
                'fields' => 'all',
            ]);

        $response->throw();
        $data = $response->json();

        $transactions = $data['transaction_details'] ?? [];

        if (empty($transactions)) {
            throw new RuntimeException("Transaction {$transactionId} not found.");
        }

        return $transactions[0];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createInvoice(User $user, array $params): array
    {
        $items = $params['items'] ?? throw new RuntimeException('items is required.');
        $recipientEmail = $params['recipientEmail'] ?? throw new RuntimeException('recipientEmail is required.');

        if (! is_array($items) || $items === []) {
            throw new RuntimeException('items must be a non-empty array.');
        }

        $body = [
            'detail' => [
                'invoice_number' => uniqid('INV-', true),
                'currency_code' => 'USD',
            ],
            'primary_recipients' => [
                [
                    'billing_info' => [
                        'email_address' => $recipientEmail,
                    ],
                ],
            ],
            'items' => $items,
        ];

        $response = Http::withToken($this->getAccessToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post($this->baseUrl().'/v2/invoicing/invoices', $body);

        $response->throw();

        return $response->json() ?? ['href' => $response->header('Location')];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function sendInvoice(User $user, array $params): array
    {
        $invoiceId = $params['invoiceId'] ?? throw new RuntimeException('invoiceId is required.');

        $response = Http::withToken($this->getAccessToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->post($this->baseUrl().'/v2/invoicing/invoices/'.urlencode((string) $invoiceId).'/send', [
                'send_to_invoicer' => true,
            ]);

        $response->throw();

        return ['invoiceId' => $invoiceId, 'status' => 'sent'];
    }

    private function getAccessToken(User $user): string
    {
        $credentials = $this->getCredentials($user);

        if ($credentials === null || empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            throw new RuntimeException('PayPal credentials are not configured for this user.');
        }

        $clientId = (string) $credentials['client_id'];
        $clientSecret = (string) $credentials['client_secret'];
        $cacheKey = 'paypal_access_token_'.md5($clientId);

        /** @var string|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->timeout(15)
            ->connectTimeout(10)
            ->asForm()
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();
        $data = $response->json();

        $token = (string) $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 32400);

        Cache::put($cacheKey, $token, now()->addSeconds($expiresIn - 60));

        return $token;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.paypal.base_url', 'https://api-m.paypal.com'), '/');
    }
}
