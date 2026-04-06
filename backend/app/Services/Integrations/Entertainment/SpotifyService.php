<?php

declare(strict_types=1);

namespace App\Services\Integrations\Entertainment;

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\AbstractIntegrationService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SpotifyService extends AbstractIntegrationService
{
    protected string $integrationName = 'spotify';

    private const BASE_URL = 'https://api.spotify.com/v1';

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array
    {
        return [
            [
                'name'        => 'search',
                'description' => 'Search for tracks, albums, artists, or playlists on Spotify.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'The search query.',
                        ],
                        'type' => [
                            'type'        => 'string',
                            'description' => 'Comma-separated types to search (e.g. "track,artist,album"). Default "track".',
                        ],
                        'limit' => [
                            'type'        => 'integer',
                            'description' => 'Number of results per type (default 10, max 50).',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'get_track',
                'description' => 'Retrieve details of a Spotify track.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'trackId' => [
                            'type'        => 'string',
                            'description' => 'The Spotify track ID.',
                        ],
                    ],
                    'required' => ['trackId'],
                ],
            ],
            [
                'name'        => 'get_playlist',
                'description' => 'Retrieve a Spotify playlist with its tracks.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'playlistId' => [
                            'type'        => 'string',
                            'description' => 'The Spotify playlist ID.',
                        ],
                    ],
                    'required' => ['playlistId'],
                ],
            ],
            [
                'name'        => 'get_currently_playing',
                'description' => "Retrieve the track currently playing on the user's Spotify account.",
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'get_top_items',
                'description' => "Get the user's top tracks or artists over a time range.",
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'type' => [
                            'type'        => 'string',
                            'enum'        => ['tracks', 'artists'],
                            'description' => 'Whether to return top tracks or top artists.',
                        ],
                        'timeRange' => [
                            'type'        => 'string',
                            'enum'        => ['short_term', 'medium_term', 'long_term'],
                            'description' => 'Time range: short_term (~4 weeks), medium_term (~6 months), long_term (all time). Default "medium_term".',
                        ],
                        'limit' => [
                            'type'        => 'integer',
                            'description' => 'Number of items to return (default 20, max 50).',
                        ],
                    ],
                    'required' => ['type'],
                ],
            ],
            [
                'name'        => 'get_recommendations',
                'description' => 'Get track recommendations based on seed tracks, artists, and/or genres.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'seedTracks' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Up to 5 Spotify track IDs to use as seeds.',
                        ],
                        'seedArtists' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Up to 5 Spotify artist IDs to use as seeds.',
                        ],
                        'seedGenres' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Up to 5 genre strings to use as seeds.',
                        ],
                        'limit' => [
                            'type'        => 'integer',
                            'description' => 'Number of recommendations to return (default 20, max 100).',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    public function executeTool(string $toolName, array $params, User $user): mixed
    {
        return match ($toolName) {
            'search'                => $this->search($user, $params),
            'get_track'             => $this->getTrack($user, $params),
            'get_playlist'          => $this->getPlaylist($user, $params),
            'get_currently_playing' => $this->getCurrentlyPlaying($user),
            'get_top_items'         => $this->getTopItems($user, $params),
            'get_recommendations'   => $this->getRecommendations($user, $params),
            default                 => throw new RuntimeException("Unknown tool: {$toolName}"),
        };
    }

    public function getAuthUrl(User $user): ?string
    {
        $clientId = config('services.spotify.client_id');
        $redirectUri = config('services.spotify.redirect_uri');
        $state = bin2hex(random_bytes(16));

        $scopes = implode(' ', [
            'user-read-currently-playing',
            'user-top-read',
            'playlist-read-private',
            'playlist-read-collaborative',
        ]);

        return 'https://accounts.spotify.com/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => $scopes,
        ]);
    }

    public function handleCallback(User $user, array $params): void
    {
        $code = $params['code'] ?? throw new RuntimeException('Missing OAuth code.');

        $response = Http::withBasicAuth(
            (string) config('services.spotify.client_id'),
            (string) config('services.spotify.client_secret'),
        )
            ->timeout(15)
            ->asForm()
            ->post('https://accounts.spotify.com/api/token', [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => config('services.spotify.redirect_uri'),
            ]);

        $response->throw();
        $data = $response->json();

        $integration = $this->getUserIntegration($user);

        if ($integration === null) {
            $definition = $this->getDefinition();

            $user->integrations()->create([
                'integration_id'      => $definition->getKey(),
                'is_enabled'          => true,
                'oauth_token'         => $data['access_token'],
                'oauth_refresh_token' => $data['refresh_token'] ?? null,
                'oauth_expires_at'    => isset($data['expires_in'])
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
                'scopes_granted' => isset($data['scope'])
                    ? explode(' ', (string) $data['scope'])
                    : [],
            ]);
        } else {
            $integration->update([
                'is_enabled'          => true,
                'oauth_token'         => $data['access_token'],
                'oauth_refresh_token' => $data['refresh_token'] ?? null,
                'oauth_expires_at'    => isset($data['expires_in'])
                    ? now()->addSeconds((int) $data['expires_in'])
                    : null,
                'scopes_granted' => isset($data['scope'])
                    ? explode(' ', (string) $data['scope'])
                    : [],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function search(User $user, array $params): array
    {
        $query = $params['query'] ?? throw new RuntimeException('query is required.');
        $type = $params['type'] ?? 'track';
        $limit = min((int) ($params['limit'] ?? 10), 50);

        $response = $this->client($user)->get(self::BASE_URL.'/search', [
            'q'     => $query,
            'type'  => $type,
            'limit' => $limit,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getTrack(User $user, array $params): array
    {
        $trackId = $params['trackId'] ?? throw new RuntimeException('trackId is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/tracks/'.urlencode((string) $trackId));

        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getPlaylist(User $user, array $params): array
    {
        $playlistId = $params['playlistId'] ?? throw new RuntimeException('playlistId is required.');

        $response = $this->client($user)
            ->get(self::BASE_URL.'/playlists/'.urlencode((string) $playlistId));

        $response->throw();

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function getCurrentlyPlaying(User $user): array
    {
        $response = $this->client($user)
            ->get(self::BASE_URL.'/me/player/currently-playing');

        if ($response->status() === 204) {
            return ['is_playing' => false, 'item' => null];
        }

        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getTopItems(User $user, array $params): array
    {
        $type = $params['type'] ?? throw new RuntimeException('type is required.');

        if (!in_array($type, ['tracks', 'artists'], true)) {
            throw new RuntimeException('type must be "tracks" or "artists".');
        }

        $response = $this->client($user)->get(self::BASE_URL.'/me/top/'.$type, [
            'time_range' => $params['timeRange'] ?? 'medium_term',
            'limit'      => min((int) ($params['limit'] ?? 20), 50),
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getRecommendations(User $user, array $params): array
    {
        $queryParams = [
            'limit' => min((int) ($params['limit'] ?? 20), 100),
        ];

        if (!empty($params['seedTracks']) && is_array($params['seedTracks'])) {
            $queryParams['seed_tracks'] = implode(',', array_slice($params['seedTracks'], 0, 5));
        }

        if (!empty($params['seedArtists']) && is_array($params['seedArtists'])) {
            $queryParams['seed_artists'] = implode(',', array_slice($params['seedArtists'], 0, 5));
        }

        if (!empty($params['seedGenres']) && is_array($params['seedGenres'])) {
            $queryParams['seed_genres'] = implode(',', array_slice($params['seedGenres'], 0, 5));
        }

        $totalSeeds = (int) isset($queryParams['seed_tracks'])
            + (int) isset($queryParams['seed_artists'])
            + (int) isset($queryParams['seed_genres']);

        if ($totalSeeds === 0) {
            throw new RuntimeException('At least one seed (seedTracks, seedArtists, or seedGenres) is required.');
        }

        $response = $this->client($user)->get(self::BASE_URL.'/recommendations', $queryParams);
        $response->throw();

        return $response->json();
    }

    private function getAccessToken(User $user): string
    {
        $integration = $this->getUserIntegration($user);

        if ($integration === null || $integration->oauth_token === null) {
            throw new RuntimeException('Spotify integration is not connected for this user.');
        }

        // Refresh token if expiring within 60 seconds.
        if (
            $integration->oauth_expires_at !== null
            && $integration->oauth_expires_at->diffInSeconds(now(), false) > -60
            && $integration->oauth_refresh_token !== null
        ) {
            return $this->refreshAccessToken($user, $integration);
        }

        return (string) $integration->oauth_token;
    }

    private function refreshAccessToken(User $user, UserIntegration $integration): string
    {
        $cacheKey = 'spotify_refresh_'.$user->getKey();

        /** @var string|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $response = Http::withBasicAuth(
            (string) config('services.spotify.client_id'),
            (string) config('services.spotify.client_secret'),
        )
            ->timeout(15)
            ->asForm()
            ->post('https://accounts.spotify.com/api/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $integration->oauth_refresh_token,
            ]);

        $response->throw();
        $data = $response->json();

        $newToken = (string) $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        $integration->update([
            'oauth_token'      => $newToken,
            'oauth_expires_at' => now()->addSeconds($expiresIn),
        ]);

        Cache::put($cacheKey, $newToken, now()->addSeconds($expiresIn - 60));

        return $newToken;
    }

    private function client(User $user): PendingRequest
    {
        return Http::withToken($this->getAccessToken($user))
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 500)
            ->acceptJson();
    }
}
