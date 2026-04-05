<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IntegrationDefinition;
use Illuminate\Database\Seeder;

class IntegrationDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        $integrations = [
            // Productivity
            [
                'name' => 'google_calendar',
                'display_name' => 'Google Calendar',
                'description' => 'Access and manage your Google Calendar events and schedules.',
                'category' => 'productivity',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            [
                'name' => 'gmail',
                'display_name' => 'Gmail',
                'description' => 'Read, compose, and send emails through your Gmail account.',
                'category' => 'productivity',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            [
                'name' => 'google_drive',
                'display_name' => 'Google Drive',
                'description' => 'Browse, read, and manage files stored in your Google Drive.',
                'category' => 'productivity',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            [
                'name' => 'slack',
                'display_name' => 'Slack',
                'description' => 'Send messages and interact with your Slack workspaces and channels.',
                'category' => 'productivity',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            [
                'name' => 'apple_notes',
                'display_name' => 'Apple Notes',
                'description' => 'Read and create notes in the Apple Notes application on macOS.',
                'category' => 'productivity',
                'auth_type' => 'none',
                'is_active' => true,
            ],
            [
                'name' => 'imessages',
                'display_name' => 'iMessages',
                'description' => 'Read your iMessages conversation history on macOS.',
                'category' => 'productivity',
                'auth_type' => 'none',
                'is_active' => true,
            ],
            [
                'name' => 'calendly',
                'display_name' => 'Calendly',
                'description' => 'View and manage your Calendly scheduling links and appointments.',
                'category' => 'productivity',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            [
                'name' => 'notion',
                'display_name' => 'Notion',
                'description' => 'Read and write pages, databases, and blocks in your Notion workspace.',
                'category' => 'productivity',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'microsoft_calendar',
                'display_name' => 'Microsoft Calendar',
                'description' => 'Access and manage events in your Microsoft Outlook calendar.',
                'category' => 'productivity',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            // Developer
            [
                'name' => 'github',
                'display_name' => 'GitHub',
                'description' => 'Interact with GitHub repositories, issues, pull requests, and actions.',
                'category' => 'developer',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            [
                'name' => 'gitlab',
                'display_name' => 'GitLab',
                'description' => 'Interact with GitLab repositories, merge requests, and CI/CD pipelines.',
                'category' => 'developer',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            [
                'name' => 'postman',
                'display_name' => 'Postman',
                'description' => 'Manage and run API collections stored in your Postman workspace.',
                'category' => 'developer',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'cloudflare',
                'display_name' => 'Cloudflare',
                'description' => 'Manage Cloudflare zones, DNS records, workers, and other resources.',
                'category' => 'developer',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'jira',
                'display_name' => 'Jira',
                'description' => 'Create and manage issues, sprints, and projects in Jira.',
                'category' => 'developer',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'linear',
                'display_name' => 'Linear',
                'description' => 'Manage issues, projects, and cycles in your Linear workspace.',
                'category' => 'developer',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'vercel',
                'display_name' => 'Vercel',
                'description' => 'Deploy and manage projects, domains, and environment variables on Vercel.',
                'category' => 'developer',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            // Design
            [
                'name' => 'miro',
                'display_name' => 'Miro',
                'description' => 'Create and collaborate on visual boards and diagrams in Miro.',
                'category' => 'design',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            [
                'name' => 'figma',
                'display_name' => 'Figma',
                'description' => 'Access and inspect design files, components, and assets in Figma.',
                'category' => 'design',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            // Finance
            [
                'name' => 'paypal',
                'display_name' => 'PayPal',
                'description' => 'View transactions, create invoices, and manage payments through PayPal.',
                'category' => 'finance',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'stripe',
                'display_name' => 'Stripe',
                'description' => 'Manage Stripe payments, subscriptions, customers, and invoices.',
                'category' => 'finance',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            // Search
            [
                'name' => 'brave_search',
                'display_name' => 'Brave Search',
                'description' => 'Perform privacy-focused web searches using the Brave Search API.',
                'category' => 'search',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'searxng',
                'display_name' => 'SearXNG',
                'description' => 'Query your self-hosted SearXNG metasearch engine for web results.',
                'category' => 'search',
                'auth_type' => 'none',
                'is_active' => true,
            ],
            [
                'name' => 'apify',
                'display_name' => 'Apify',
                'description' => 'Run web scraping and automation actors hosted on the Apify platform.',
                'category' => 'search',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'microsoft_learn',
                'display_name' => 'Microsoft Learn',
                'description' => 'Search and fetch official Microsoft and Azure documentation.',
                'category' => 'search',
                'auth_type' => 'none',
                'is_active' => true,
            ],
            // Career
            [
                'name' => 'indeed',
                'display_name' => 'Indeed',
                'description' => 'Search job listings and retrieve job details from Indeed.',
                'category' => 'career',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'dice',
                'display_name' => 'Dice',
                'description' => 'Search technology job listings on the Dice job board.',
                'category' => 'career',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            [
                'name' => 'linkedin',
                'display_name' => 'LinkedIn',
                'description' => 'Access your LinkedIn profile, connections, and job recommendations.',
                'category' => 'career',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            // Legal
            [
                'name' => 'harvey',
                'display_name' => 'Harvey',
                'description' => 'Use Harvey AI for advanced legal research and document analysis.',
                'category' => 'legal',
                'auth_type' => 'api_key',
                'is_active' => true,
            ],
            // Entertainment
            [
                'name' => 'spotify',
                'display_name' => 'Spotify',
                'description' => 'Control playback and browse your Spotify music library and playlists.',
                'category' => 'entertainment',
                'auth_type' => 'oauth2',
                'is_active' => true,
            ],
            // Local
            [
                'name' => 'filesystem',
                'display_name' => 'Filesystem',
                'description' => 'Read and write files on the local filesystem within allowed directories.',
                'category' => 'local',
                'auth_type' => 'none',
                'is_active' => true,
            ],
            [
                'name' => 'macos',
                'display_name' => 'macOS',
                'description' => 'Interact with macOS system features such as notifications and apps.',
                'category' => 'local',
                'auth_type' => 'none',
                'is_active' => true,
            ],
        ];

        foreach ($integrations as $integration) {
            IntegrationDefinition::updateOrCreate(
                ['name' => $integration['name']],
                $integration,
            );
        }
    }
}
