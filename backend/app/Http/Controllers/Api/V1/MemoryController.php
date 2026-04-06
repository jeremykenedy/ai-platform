<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Memory\ResolveMemoryConflictAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateMemoryRequest;
use App\Http\Resources\MemoryCollection;
use App\Http\Resources\MemoryResource;
use App\Models\Memory;
use App\Models\MemoryConflict;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemoryController extends Controller
{
    public function __construct(
        private readonly ResolveMemoryConflictAction $resolveMemoryConflictAction,
    ) {
    }

    public function index(Request $request): MemoryCollection
    {
        /** @var User $user */
        $user = $request->user();

        $sortBy = $request->string('sort_by', 'created_at')->toString();
        $allowedSorts = ['importance', 'created_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $query = $user->memories()->where('is_active', true);

        if ($request->has('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        $memories = $query->orderByDesc($sortBy)->cursorPaginate(20);

        return new MemoryCollection($memories);
    }

    public function store(UpdateMemoryRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memory = $user->memories()->create($request->validated());

        return (new MemoryResource($memory))->response()->setStatusCode(201);
    }

    public function update(UpdateMemoryRequest $request, Memory $memory): MemoryResource
    {
        if ($memory->user_id !== $request->user()?->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $memory->update($request->validated());

        return new MemoryResource($memory);
    }

    public function destroy(Request $request, Memory $memory): JsonResponse
    {
        if ($memory->user_id !== $request->user()?->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $memory->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['string']]);

        /** @var User $user */
        $user = $request->user();

        $user->memories()
            ->whereIn('id', $request->input('ids', []))
            ->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function resolveConflict(Request $request, MemoryConflict $conflict): JsonResponse
    {
        $validated = $request->validate(['resolution' => ['required', 'string', 'in:keep_new,keep_old,merge,dismiss']]);

        $this->resolveMemoryConflictAction->handle($conflict, (string) $validated['resolution']);

        return response()->json(['message' => 'Conflict resolved.']);
    }
}
