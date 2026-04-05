<?php

declare(strict_types=1);

namespace App\Services\Integrations\Finance;

use App\Models\User;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeService extends AbstractIntegrationService
{
    protected string $integrationName = 'stripe';

    private const BASE_URL = 'https://api.stripe.com/v1';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name' => 'list_charges',
                'description' => 'List Stripe charges, optionally filtering by creation date.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of charges to return (default 10, max 100).',
                        ],
                        'created_after' => [
                            'type' => 'integer',
                            'description' => 'Unix timestamp; only return charges created after this time.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_charge',
                'description' => 'Retrieve details of a specific Stripe charge.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'chargeId' => [
                            'type' => 'string',
                            'description' => 'The Stripe charge ID (e.g. ch_...).',
                        ],
                    ],
                    'required' => ['chargeId'],
                ],
            ],
            [
                'name' => 'list_customers',
                'description' => 'List Stripe customers, optionally filtering by email.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of customers to return (default 10, max 100).',
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => 'Filter customers by exact email address.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_customer',
                'description' => 'Retrieve details of a specific Stripe customer.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customerId' => [
                            'type' => 'string',
                            'description' => 'The Stripe customer ID (e.g. cus_...).',
                        ],
                    ],
                    'required' => ['customerId'],
                ],
            ],
            [
                'name' => 'list_invoices',
                'description' => 'List Stripe invoices, optionally filtered by customer and/or status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'customerId' => [
                            'type' => 'string',
                            'description' => 'Filter invoices by Stripe customer ID.',
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['draft', 'open', 'paid', 'uncollectible', 'void'],
                            'description' => 'Filter invoices by status.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of invoices to return (default 10, max 100).',
                        ],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'create_payment_link',
                'description' => 'Create a Stripe payment link for a price and quantity.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'priceId' => [
                            'type' => 'string',
                            'description' => 'The Stripe price ID (e.g. price_...).',
                        ],
                        'quantity' => [
                            'type' => 'integer',
                            'description' => 'Quantity of the item (default 1).',
                        ],
                    ],
                    'required' => ['priceId'],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'list_charges' => $this->listCharges($user, $params),
            'get_charge' => $this->getCharge($user, $params),
            'list_customers' => $this->listCustomers($user, $params),
            'get_customer' => $this->getCustomer($user, $params),
            'list_invoices' => $this->listInvoices($user, $params),
            'create_payment_link' => $this->createPaymentLink($user, $params),
            default => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listCharges(User $user, array $params): array
    {
        $query = ['limit' => min((int) ($params['limit'] ?? 10), 100)];

        if (isset($params['created_after'])) {
            $query['created'] = ['gt' => (int) $params['created_after']];
        }

        $response = $this->client($user)->get(self::BASE_URL.'/charges', $query);
        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getCharge(User $user, array $params): array
    {
        $chargeId = $params['chargeId'] ?? throw new RuntimeException('chargeId is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/charges/'.urlencode((string) $chargeId));

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listCustomers(User $user, array $params): array
    {
        $query = ['limit' => min((int) ($params['limit'] ?? 10), 100)];

        if (isset($params['email'])) {
            $query['email'] = $params['email'];
        }

        $response = $this->client($user)->get(self::BASE_URL.'/customers', $query);
        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getCustomer(User $user, array $params): array
    {
        $customerId = $params['customerId'] ?? throw new RuntimeException('customerId is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/customers/'.urlencode((string) $customerId));

        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function listInvoices(User $user, array $params): array
    {
        $query = ['limit' => min((int) ($params['limit'] ?? 10), 100)];

        if (isset($params['customerId'])) {
            $query['customer'] = $params['customerId'];
        }

        if (isset($params['status'])) {
            $query['status'] = $params['status'];
        }

        $response = $this->client($user)->get(self::BASE_URL.'/invoices', $query);
        $response->throw();

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function createPaymentLink(User $user, array $params): array
    {
        $priceId = $params['priceId'] ?? throw new RuntimeException('priceId is required.');
        $quantity = isset($params['quantity']) ? max(1, (int) $params['quantity']) : 1;

        $response = $this->client($user)->asForm()->post(self::BASE_URL.'/payment_links', [
            'line_items[0][price]' => $priceId,
            'line_items[0][quantity]' => $quantity,
        ]);

        $response->throw();

        return $response->json();
    }

    private function getSecretKey(User $user): string
    {
        $credentials = $this->getCredentials($user);

        if ($credentials === null || empty($credentials['secret_key'])) {
            throw new RuntimeException('Stripe secret key is not configured for this user.');
        }

        return (string) $credentials['secret_key'];
    }

    private function client(User $user): PendingRequest
    {
        return Http::withToken($this->getSecretKey($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->acceptJson();
    }
}
