<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\User;
use App\Services\Integrations\Career\DiceService;
use App\Services\Integrations\Career\IndeedService;
use App\Services\Integrations\Contracts\IntegrationServiceInterface;
use App\Services\Integrations\Design\FigmaService;
use App\Services\Integrations\Design\MiroService;
use App\Services\Integrations\Developer\CloudflareService;
use App\Services\Integrations\Developer\GitHubService;
use App\Services\Integrations\Developer\GitLabService;
use App\Services\Integrations\Developer\JiraService;
use App\Services\Integrations\Developer\LinearService;
use App\Services\Integrations\Developer\PostmanService;
use App\Services\Integrations\Developer\VercelService;
use App\Services\Integrations\Entertainment\SpotifyService;
use App\Services\Integrations\Finance\PayPalService;
use App\Services\Integrations\Finance\StripeService;
use App\Services\Integrations\Legal\HarveyService;
use App\Services\Integrations\Local\FilesystemService;
use App\Services\Integrations\Local\MacOSService;
use App\Services\Integrations\Productivity\AppleNotesService;
use App\Services\Integrations\Productivity\CalendlyService;
use App\Services\Integrations\Productivity\GmailService;
use App\Services\Integrations\Productivity\GoogleCalendarService;
use App\Services\Integrations\Productivity\GoogleDriveService;
use App\Services\Integrations\Productivity\MicrosoftCalendarService;
use App\Services\Integrations\Productivity\NotionService;
use App\Services\Integrations\Productivity\SlackService;
use App\Services\Integrations\Search\ApifyService;
use App\Services\Integrations\Search\BraveSearchService;
use App\Services\Integrations\Search\SearXNGService;
use Illuminate\Contracts\Container\Container;

class IntegrationManager
{
    /**
     * Resolved service instances, keyed by integration name.
     *
     * @var array<string, IntegrationServiceInterface>
     */
    private array $services = [];

    /**
     * Map of integration names to their concrete service class names.
     *
     * @var array<string, class-string<IntegrationServiceInterface>>
     */
    private array $serviceMap = [
        'google_calendar'    => GoogleCalendarService::class,
        'gmail'              => GmailService::class,
        'google_drive'       => GoogleDriveService::class,
        'slack'              => SlackService::class,
        'apple_notes'        => AppleNotesService::class,
        'calendly'           => CalendlyService::class,
        'notion'             => NotionService::class,
        'microsoft_calendar' => MicrosoftCalendarService::class,
        'github'             => GitHubService::class,
        'gitlab'             => GitLabService::class,
        'postman'            => PostmanService::class,
        'cloudflare'         => CloudflareService::class,
        'jira'               => JiraService::class,
        'linear'             => LinearService::class,
        'vercel'             => VercelService::class,
        'miro'               => MiroService::class,
        'figma'              => FigmaService::class,
        'paypal'             => PayPalService::class,
        'stripe'             => StripeService::class,
        'brave_search'       => BraveSearchService::class,
        'searxng'            => SearXNGService::class,
        'apify'              => ApifyService::class,
        'indeed'             => IndeedService::class,
        'dice'               => DiceService::class,
        'harvey'             => HarveyService::class,
        'spotify'            => SpotifyService::class,
        'filesystem'         => FilesystemService::class,
        'macos'              => MacOSService::class,
    ];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Resolve and cache the service instance for the given integration name.
     *
     * @throws \InvalidArgumentException When the integration name is not registered.
     */
    public function resolve(string $integrationName): IntegrationServiceInterface
    {
        if (isset($this->services[$integrationName])) {
            return $this->services[$integrationName];
        }

        if (!isset($this->serviceMap[$integrationName])) {
            throw new \InvalidArgumentException(
                sprintf('No integration service registered for "%s".', $integrationName),
            );
        }

        /** @var IntegrationServiceInterface $service */
        $service = $this->container->make($this->serviceMap[$integrationName]);

        $this->services[$integrationName] = $service;

        return $service;
    }

    /**
     * Return the list of integrations for which the user currently has an
     * active, enabled connection.
     *
     * @return array<int, string>
     */
    public function getAvailableIntegrations(User $user): array
    {
        $available = [];

        foreach (array_keys($this->serviceMap) as $name) {
            try {
                if ($this->resolve($name)->isConnected($user)) {
                    $available[] = $name;
                }
            } catch (\Throwable) {
                // Skip integrations whose service class cannot be resolved.
            }
        }

        return $available;
    }

    /**
     * Collect and return all tool definitions from the user's enabled
     * integrations. Pass a list of integration names to restrict results, or
     * omit to collect from all connected integrations.
     *
     * Each entry in the returned array includes an extra 'integration' key so
     * the caller can route the tool call back to the correct service.
     *
     * @param string[]|null $enabledIntegrations Limit to these integration names when provided.
     *
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>, integration: string}>
     */
    public function getToolsForUser(User $user, ?array $enabledIntegrations = null): array
    {
        $integrations = $enabledIntegrations ?? $this->getAvailableIntegrations($user);

        $tools = [];

        foreach ($integrations as $name) {
            try {
                $service = $this->resolve($name);

                if (!$service->isConnected($user)) {
                    continue;
                }

                foreach ($service->getTools() as $tool) {
                    $tools[] = array_merge($tool, ['integration' => $name]);
                }
            } catch (\Throwable) {
                // Skip integrations that fail to load or report tools.
            }
        }

        return $tools;
    }

    /**
     * Route a tool call to the named integration service and return the result.
     *
     * @param array<string, mixed> $params
     */
    public function executeTool(string $integrationName, string $toolName, array $params, User $user): mixed
    {
        return $this->resolve($integrationName)->executeTool($toolName, $params, $user);
    }
}
