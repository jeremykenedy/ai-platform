<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\InviteUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TrainingJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private readonly InviteUserAction $inviteUserAction,
    ) {}

    public function users(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('roles')->cursorPaginate(20);

        return response()->json(UserResource::collection($users));
    }

    public function updateUser(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        if ($request->has('role')) {
            $user->syncRoles([$request->validated('role')]);
        }

        $user->update($request->safe()->except('role'));

        return new UserResource($user->load('roles'));
    }

    public function invite(InviteUserRequest $request): JsonResponse
    {
        $this->authorize('invite', User::class);

        $user = $this->inviteUserAction->handle(
            $request->validated('email'),
            $request->validated('name'),
            $request->validated('role', 'user'),
        );

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return response()->json([
            'data' => [
                'total_users' => User::count(),
                'total_conversations' => Conversation::count(),
                'total_messages' => Message::count(),
                'active_models_count' => AiModel::active()->count(),
                'running_jobs_count' => TrainingJob::whereIn('status', ['pending', 'running'])->count(),
            ],
        ]);
    }
}
