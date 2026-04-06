<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conversation\RegenerateMessageAction;
use App\Actions\Conversation\SendMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\MessageCollection;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MessageController extends Controller
{
    public function __construct(
        private readonly SendMessageAction $sendMessageAction,
        private readonly RegenerateMessageAction $regenerateMessageAction,
    ) {}

    public function index(Conversation $conversation): MessageCollection
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->with(['attachments'])
            ->cursorPaginate(50);

        return new MessageCollection($messages);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $message = $this->sendMessageAction->handle(
            $conversation,
            (string) $request->validated('content'),
            $request->has('model') ? (string) $request->validated('model') : null,
        );

        return (new MessageResource($message))->response()->setStatusCode(202);
    }

    public function destroy(Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->authorize('delete', $message);

        $message->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function regenerate(Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('view', $conversation);

        $this->regenerateMessageAction->handle($message);

        return response()->json(null, 202);
    }
}
