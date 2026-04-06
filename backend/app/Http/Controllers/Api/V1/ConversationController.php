<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversation\CreateConversationAction;
use App\Actions\Conversation\ExportConversationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Conversation\UpdateConversationRequest;
use App\Http\Resources\ConversationCollection;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConversationController extends Controller
{
    public function __construct(
        private readonly CreateConversationAction $createConversationAction,
        private readonly ExportConversationAction $exportConversationAction,
    ) {
    }

    public function index(Request $request): ConversationCollection
    {
        /** @var User $user */
        $user = $request->user();

        $conversations = $user->conversations()
            ->with(['project', 'persona'])
            ->latest()
            ->cursorPaginate(20);

        return new ConversationCollection($conversations);
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $conversation = $this->createConversationAction->handle(
            $user,
            $request->validated(),
        );

        return (new ConversationResource($conversation))->response()->setStatusCode(201);
    }

    public function show(Conversation $conversation): ConversationResource
    {
        $this->authorize('view', $conversation);

        $conversation->loadMissing(['messages' => function ($query): void {
            $query->latest()->limit(50);
        }, 'project', 'persona']);

        return new ConversationResource($conversation);
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): ConversationResource
    {
        $this->authorize('update', $conversation);

        $conversation->update($request->validated());

        return new ConversationResource($conversation);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function export(Request $request, Conversation $conversation): Response
    {
        $this->authorize('export', $conversation);

        $request->validate(['format' => ['sometimes', 'string', 'in:json,markdown']]);

        $format = $request->string('format', 'json')->toString();

        $result = $this->exportConversationAction->handle($conversation, $format);

        $content = is_array($result['content'])
            ? json_encode($result['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : $result['content'];

        return response((string) $content, 200, [
            'Content-Type'        => $result['mime_type'],
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }
}
