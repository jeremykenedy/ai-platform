<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TrainingJob;
use App\Models\User;
use App\Models\UserSetting;
use App\Notifications\WelcomeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return response()->json([
            'data' => [
                'total_users'         => User::count(),
                'total_conversations' => Conversation::count(),
                'total_messages'      => Message::count(),
                'active_models_count' => AiModel::active()->count(),
                'running_jobs_count'  => TrainingJob::whereIn('status', ['pending', 'running'])->count(),
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderByDesc('created_at')->cursorPaginate(20);

        return response()->json([
            'data'        => UserResource::collection($users),
            'next_cursor' => $users->nextCursor()?->encode(),
            'total'       => User::count(),
        ]);
    }

    public function show(Request $request, User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('invite', User::class);

        $inviteToken = Str::random(64);

        /** @var User $user */
        $user = User::create([
            'name'         => $request->validated('name'),
            'email'        => $request->validated('email'),
            'password'     => Hash::make(Str::random(32)),
            'invite_token' => $inviteToken,
        ]);

        $user->assignRole($request->validated('role', 'user'));

        UserSetting::create(['user_id' => $user->id]);

        if ($request->boolean('send_welcome', true)) {
            $user->notify(new WelcomeNotification($inviteToken));
        }

        return (new UserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    public function updateUser(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $data = $request->safe()->except('role');

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        if ($request->has('role')) {
            $user->syncRoles([$request->validated('role')]);
        }

        return new UserResource($user->load('roles'));
    }

    public function updateRole(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $request->validate([
            'role' => ['required', 'string', 'in:user,admin,super-admin'],
        ]);

        $user->syncRoles([$request->input('role')]);

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        /** @var User $currentUser */
        $currentUser = $request->user();

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    public function enable(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $user->update(['disabled_at' => null]);

        return new UserResource($user->load('roles'));
    }

    public function disable(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        /** @var User $currentUser */
        $currentUser = $request->user();

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot disable your own account.'], 403);
        }

        $user->update(['disabled_at' => now()]);

        return (new UserResource($user->load('roles')))->response();
    }

    public function resendWelcome(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        if ($user->invite_token === null) {
            $user->update(['invite_token' => Str::random(64)]);
            $user->refresh();
        }

        $user->notify(new WelcomeNotification($user->invite_token));

        return response()->json(['message' => 'Welcome email sent.']);
    }

    public function impersonate(Request $request, User $user): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        /** @var User $currentUser */
        $currentUser = $request->user();

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Cannot impersonate yourself.'], 403);
        }

        if ($user->hasRole('super-admin') && !$currentUser->hasRole('super-admin')) {
            return response()->json(['message' => 'Cannot impersonate a super admin.'], 403);
        }

        $request->session()->put('impersonator_id', $currentUser->id);

        Auth::login($user);

        return response()->json([
            'data' => [
                'user'            => new UserResource($user->load('roles')),
                'impersonator_id' => $currentUser->id,
            ],
        ]);
    }

    public function leaveImpersonation(Request $request): JsonResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if ($impersonatorId === null) {
            return response()->json(['message' => 'Not currently impersonating.'], 400);
        }

        $impersonator = User::findOrFail($impersonatorId);

        $request->session()->forget('impersonator_id');

        Auth::login($impersonator);

        return response()->json([
            'data' => new UserResource($impersonator->load('roles')),
        ]);
    }
}
