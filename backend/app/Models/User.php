<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string      $id
 * @property string      $name
 * @property string      $email
 * @property Carbon|null $email_verified_at
 * @property string      $password
 * @property string|null $avatar
 * @property string      $locale
 * @property string      $timezone
 * @property string|null $invite_token
 * @property string|null $invited_by
 * @property string|null $subscription_tier
 * @property Carbon|null $last_active_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CausesActivity;
    use HasFactory;
    use HasRoles;
    use HasUlids;
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'locale',
        'timezone',
        'invite_token',
        'invited_by',
        'subscription_tier',
        'last_active_at',
        'email_verified_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'invite_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'last_active_at'    => 'datetime',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    public function trainingDatasets(): HasMany
    {
        return $this->hasMany(TrainingDataset::class);
    }

    public function trainingJobs(): HasMany
    {
        return $this->hasMany(TrainingJob::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class);
    }

    /**
     * @param Builder<User> $query
     *
     * @return Builder<User>
     */
    public function scopeActive($query): Builder
    {
        return $query->where('last_active_at', '>=', now()->subDays(30));
    }
}
