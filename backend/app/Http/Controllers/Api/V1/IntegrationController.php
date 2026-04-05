<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Integration\ConnectIntegrationAction;
use App\Actions\Integration\DisconnectIntegrationAction;
use App\Actions\Integration\ExecuteIntegrationToolAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\ConnectIntegrationRequest;
use App\Http\Requests\Integration\ExecuteToolRequest;
use App\Http\Resources\IntegrationResource;
use App\Models\IntegrationDefinition;
use App\Models\User;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly ConnectIntegrationAction $connectIntegrationAction,
        private readonly DisconnectIntegrationAction $disconnectIntegrationAction,
        private readonly ExecuteIntegrationToolAction $executeIntegrationToolAction,
        private readonly IntegrationManager $integrationManager,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $connectedIds = $user->integrations()->pluck('integration_id');

        $definitions = IntegrationDefinition::where('is_active', true)
            ->get()
            ->map(function (IntegrationDefinition $definition) use ($connectedIds): IntegrationResource {
                $definition->setAttribute('is_connected', $connectedIds->contains($definition->id));

                return new IntegrationResource($definition);
            });

        return response()->json(['data' => $definitions]);
    }

    public function connect(ConnectIntegrationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $integration = $this->connectIntegrationAction->handle(
            $user,
            $request->validated('integration'),
            $request->validated('credentials', []),
        );

        $definition = IntegrationDefinition::find($integration->integration_id);

        return (new IntegrationResource($definition))->response()->setStatusCode(201);
    }

    public function disconnect(Request $request, string $integrationName): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->disconnectIntegrationAction->handle($user, $integrationName);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $service = $this->integrationManager->resolve($provider);
        $service->handleCallback($user, $request->all());

        return redirect(config('app.frontend_url').'/integrations?connected='.$provider);
    }

    public function executeTools(ExecuteToolRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->executeIntegrationToolAction->handle(
            $user,
            $request->validated('integration'),
            $request->validated('tool'),
            $request->validated('params', []),
            $request->validated('conversation_id'),
            $request->validated('message_id'),
        );

        return response()->json(['data' => $result]);
    }
}
