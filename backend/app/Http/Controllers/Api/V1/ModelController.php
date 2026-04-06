<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Model\PullModelAction;
use App\Actions\Model\SyncModelsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Model\PullModelRequest;
use App\Http\Resources\AiModelCollection;
use App\Http\Resources\AiModelResource;
use App\Models\AiModel;
use App\Services\AI\Providers\OllamaProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModelController extends Controller
{
    public function __construct(
        private readonly PullModelAction $pullModelAction,
        private readonly SyncModelsAction $syncModelsAction,
    ) {
    }

    public function index(Request $request): AiModelCollection
    {
        $models = AiModel::with('provider')
            ->active()
            ->cursorPaginate(50);

        return new AiModelCollection($models);
    }

    public function show(AiModel $model): AiModelResource
    {
        $model->loadMissing(['provider']);

        return new AiModelResource($model);
    }

    public function pull(PullModelRequest $request): JsonResponse
    {
        $this->authorize('pull', AiModel::class);

        $this->pullModelAction->handle($request->validated('model'));

        return response()->json(['message' => 'Model pull initiated.'], 202);
    }

    public function destroy(AiModel $model): JsonResponse
    {
        $this->authorize('delete', $model);

        if ($model->is_local) {
            $ollamaProvider = app(OllamaProvider::class);

            try {
                $ollamaProvider->deleteModel($model->name);
            } catch (\Throwable) {
                // Non-fatal: delete DB record regardless.
            }
        }

        $model->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function running(Request $request): JsonResponse
    {
        $ollamaProvider = app(OllamaProvider::class);

        $runningModels = $ollamaProvider->getRunningModels();

        return response()->json(['data' => $runningModels]);
    }
}
