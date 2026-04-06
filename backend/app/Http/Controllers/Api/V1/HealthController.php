<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $services = [];

        $services['database'] = $this->checkDatabase();
        $services['redis'] = $this->checkRedis();
        $services['ollama'] = $this->checkOllama();
        $services['minio'] = $this->checkMinio();

        $overallStatus = collect($services)->every(fn (string $s): bool => $s === 'ok') ? 'ok' : 'degraded';

        return response()->json([
            'status'    => $overallStatus,
            'services'  => $services,
            'timestamp' => now()->toIso8601String(),
        ], $overallStatus === 'ok' ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::select('SELECT 1');

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkRedis(): string
    {
        try {
            Cache::store('redis')->get('health');

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkOllama(): string
    {
        try {
            $baseUrl = (string) config('services.ollama.base_url', 'http://ollama:11434');

            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->get("{$baseUrl}/api/tags");

            return $response->successful() ? 'ok' : 'error';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkMinio(): string
    {
        try {
            $endpoint = (string) config('filesystems.disks.s3.endpoint', 'http://minio:9000');

            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->get("{$endpoint}/minio/health/live");

            return $response->successful() ? 'ok' : 'error';
        } catch (\Throwable) {
            return 'error';
        }
    }
}
