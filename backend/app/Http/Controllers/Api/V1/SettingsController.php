<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Resources\SettingsResource;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): SettingsResource
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $user->settings ?? UserSetting::create(['user_id' => $user->id]);

        return new SettingsResource($settings);
    }

    public function update(UpdateSettingsRequest $request): SettingsResource
    {
        /** @var User $user */
        $user = $request->user();

        $settings = UserSetting::updateOrCreate(
            ['user_id' => $user->id],
            $request->validated(),
        );

        return new SettingsResource($settings);
    }
}
