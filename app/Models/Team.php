<?php

namespace App\Models;

use App\Actions\User\RevokeUserTeamTokens;
use App\Contracts\ThrottledNotification;
use App\Events\ServerReachabilityChanged;
use App\Notifications\Channels\SendsDiscord;
use App\Notifications\Channels\SendsEmail;
use App\Notifications\Channels\SendsPushover;
use App\Notifications\Channels\SendsSlack;
use App\Notifications\Server\Unreachable;
use App\Services\RbacService;
use App\Services\TeamAvatarStorageService;
use App\Traits\Auditable;
use App\Traits\HasNotificationSettings;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Team model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer', 'description' => 'The unique identifier of the team.'],
        'name' => ['type' => 'string', 'description' => 'The name of the team.'],
        'description' => ['type' => 'string', 'description' => 'The description of the team.'],
        'personal_team' => ['type' => 'boolean', 'description' => 'Whether the team is personal or not.'],
        'created_at' => ['type' => 'string', 'description' => 'The date and time the team was created.'],
        'updated_at' => ['type' => 'string', 'description' => 'The date and time the team was last updated.'],
        'show_boarding' => ['type' => 'boolean', 'description' => 'Whether to show the boarding screen or not.'],
        'custom_server_limit' => ['type' => 'string', 'description' => 'The custom server limit.'],
        'is_build_server_fallback_enabled' => ['type' => 'boolean', 'description' => 'Whether deployments can fall back to the deployment server when no usable dedicated build server is available.'],
        'scan_images_on_new_servers' => ['type' => 'boolean', 'description' => 'Whether image scanning is turned on for servers added to the team from now on.'],
        'members' => new OA\Property(
            property: 'members',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/User'),
            description: 'The members of the team.'
        ),
    ]
)]

class Team extends Model implements SendsDiscord, SendsEmail, SendsPushover, SendsSlack
{
    use Auditable, HasFactory, HasNotificationSettings, HasSafeStringAttribute;
    use Notifiable {
        notify as sendNotification;
    }

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'personal_team',
        'show_boarding',
        'custom_server_limit',
        'seat_limit',
        'is_mcp_server_enabled',
        'is_build_server_fallback_enabled',
        'is_two_factor_required',
        'scan_images_on_new_servers',
    ];

    protected $attributes = [
        'seat_limit' => 5,
        'is_mcp_server_enabled' => true,
        'is_build_server_fallback_enabled' => true,
        'is_two_factor_required' => false,
        'scan_images_on_new_servers' => false,
    ];

    protected $casts = [
        'personal_team' => 'boolean',
        'seat_limit' => 'integer',
        'is_mcp_server_enabled' => 'boolean',
        'is_build_server_fallback_enabled' => 'boolean',
        'is_two_factor_required' => 'boolean',
        'scan_images_on_new_servers' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function (Team $team) {
            if (! $team->uuid) {
                $team->uuid = ($team->id === 0)
                    ? '00000000-0000-0000-0000-000000000000'
                    : (string) Str::uuid();
            }
        });

        static::created(function (Team $team) {
            $team->createDefaultNotificationSettings();
        });

        static::updating(function ($team) {
            if (auth()->user() && ! auth()->user()->isAdminOfTeam($team->id)) {
                throw new \Exception('You are not allowed to update this team.');
            }
        });

        static::deleting(function (Team $team) {
            if ($team->id === 0) {
                throw new \Exception('The root team (id = 0) is a protected platform sentinel and cannot be deleted.');
            }
            $team->deleteOwnedRecords();
        });

        static::deleted(function (Team $team) {
            app(TeamAvatarStorageService::class)->deleteTeamFile($team);
        });
    }

    /**
     * Role of a specific user within this team ('owner', 'admin', 'member', or null).
     */
    public function userRole(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $member = $this->members()->where('users.id', $user->id)->first();

        return data_get($member, 'pivot.role');
    }

    /**
     * Whether the given user has management authority over this team.
     */
    public function canBeManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (RbacService::isStaff($user)) {
            return true;
        }

        $role = $this->userRole($user);

        return in_array($role, ['owner', 'admin'], true);
    }

    /**
     * Whether the given user may delete this team.
     */
    public function canBeDeletedBy(?User $user): bool
    {
        if (! $user || $this->id === 0) {
            return false;
        }

        if (RbacService::isStaff($user)) {
            return true;
        }

        return $this->userRole($user) === 'owner';
    }

    /**
     * Transfer team ownership from current owner to a target member.
     */
    public function transferOwnership(User $newOwner, User $currentOwner): bool
    {
        if ($this->id === 0) {
            throw new \Exception('Root team ownership cannot be transferred.');
        }

        if (! $this->canBeDeletedBy($currentOwner)) {
            throw new \Exception('Only the current team owner can transfer ownership.');
        }

        if (! $this->members()->where('users.id', $newOwner->id)->exists()) {
            throw new \Exception('The target recipient must be an existing member of the team.');
        }

        return DB::transaction(function () use ($newOwner, $currentOwner): bool {
            // Demote old owner to admin
            $this->members()->updateExistingPivot($currentOwner->id, ['role' => 'admin']);
            // Promote new owner
            $this->members()->updateExistingPivot($newOwner->id, ['role' => 'owner']);

            return true;
        });
    }

    /**
     * Total number of members currently attached to this team.
     */
    public function memberCount(): int
    {
        return $this->members()->count();
    }

    /**
     * Whether the team has reached its member seat limit.
     */
    public function hasReachedSeatLimit(): bool
    {
        if ($this->id === 0) {
            return false;
        }

        $limit = $this->seat_limit ?? 5;

        return $this->memberCount() >= $limit;
    }

    /**
     * Creates the notification settings rows every team needs, with their default values.
     */
    public function createDefaultNotificationSettings(): void
    {
        $this->emailNotificationSettings()->create([
            'use_instance_email_settings' => isDev(),
        ]);
        $this->discordNotificationSettings()->create();
        $this->slackNotificationSettings()->create();
        $this->telegramNotificationSettings()->create();
        $this->pushoverNotificationSettings()->create();
        $this->webhookNotificationSettings()->create();
    }

    /**
     * Deletes the API tokens, private keys, sources, tags, shared variables, and S3 storages of the
     * team. Instance-wide sources move to the root team instead.
     */
    public function deleteOwnedRecords(): void
    {
        RevokeUserTeamTokens::forTeam($this->id);

        foreach ($this->privateKeys()->get() as $key) {
            $key->delete();
        }

        // Transfer instance-wide sources to root team so they remain available
        $systemWideSources = GithubApp::where('team_id', $this->id)->where('is_system_wide', true)->get()
            ->concat(GitlabApp::where('team_id', $this->id)->where('is_system_wide', true)->get())
            ->concat(GiteaApp::where('team_id', $this->id)->where('is_system_wide', true)->get());
        foreach ($systemWideSources as $source) {
            $source->update(['team_id' => 0]);
        }

        // Delete non-instance-wide sources owned by this team
        $teamSources = GithubApp::where('team_id', $this->id)->get()
            ->concat(GitlabApp::where('team_id', $this->id)->get())
            ->concat(GiteaApp::where('team_id', $this->id)->get());
        foreach ($teamSources as $source) {
            $source->delete();
        }

        foreach (Tag::whereTeamId($this->id)->get() as $tag) {
            $tag->delete();
        }

        foreach ($this->environment_variables()->get() as $sharedVariable) {
            $sharedVariable->delete();
        }

        // Also unusable storages, which the s3s relation hides.
        foreach (S3Storage::query()->where('team_id', $this->id)->get() as $s3) {
            $s3->delete();
        }

        ImageScanPolicy::query()->where('scope_type', $this->getMorphClass())->where('scope_id', $this->id)->delete();
        ImageScanIgnore::query()->where('team_id', $this->id)->delete();
    }

    public static function serverLimitReached(?Team $team = null)
    {
        $team = $team ?? currentTeam();
        if (! $team) {
            return true;
        }
        $serverLimit = Team::serverLimit($team);
        $servers = $team->managedServers()->count();

        return $servers >= $serverLimit;
    }

    public static function createServerWithinLimit(int $teamId, array $attributes): Server
    {
        return DB::transaction(function () use ($teamId, $attributes): Server {
            self::ensureServerCapacity($teamId);

            return Server::create($attributes);
        });
    }

    /** Call within a transaction so the team lock lasts through the server insert. */
    public static function ensureServerCapacity(int $teamId): void
    {
        $team = self::query()->lockForUpdate()->findOrFail($teamId);
        if (self::serverLimitReached($team)) {
            throw ValidationException::withMessages(['server' => 'Server limit reached for your subscription.']);
        }
    }

    public function subscriptionPastOverDue()
    {
        if (isCloud()) {
            return $this->subscription?->stripe_past_due;
        }

        return false;
    }

    public function serverOverflow()
    {
        if (Team::serverLimit($this) < $this->managedServers()->count()) {
            return true;
        }

        return false;
    }

    public static function serverLimit(?Team $team = null)
    {
        $team = $team ?? currentTeam();
        if (! $team) {
            return 0;
        }
        if ($team->id === 0 && isDev()) {
            return 9999999;
        }
        $team = Team::find($team->id);
        if (! $team) {
            return 0;
        }

        return data_get($team, 'limits', 0);
    }

    public function limits(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (config('constants.coolify.self_hosted') || $this->id === 0) {
                    return 999999999999;
                }

                return $this->custom_server_limit ?? 2;
            }
        );
    }

    public function getUuidAttribute(?string $value): string
    {
        return $value ?: ($this->id === 0 ? '00000000-0000-0000-0000-000000000000' : 'team-'.$this->id);
    }

    public function routeNotificationForDiscord()
    {
        return data_get($this, 'discord_webhook_url', null);
    }

    public function routeNotificationForTelegram()
    {
        return [
            'token' => data_get($this, 'telegram_token', null),
            'chat_id' => data_get($this, 'telegram_chat_id', null),
        ];
    }

    public function routeNotificationForSlack()
    {
        return data_get($this, 'slack_webhook_url', null);
    }

    public function routeNotificationForPushover()
    {
        return [
            'user' => data_get($this, 'pushover_user_key', null),
            'token' => data_get($this, 'pushover_api_token', null),
        ];
    }

    public function getRecipients(): array
    {
        $recipients = $this->members()->pluck('email')->toArray();
        $validatedEmails = array_filter($recipients, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        if (is_null($validatedEmails)) {
            return [];
        }

        return array_values($validatedEmails);
    }

    public function isAnyNotificationEnabled()
    {
        if (isCloud()) {
            return true;
        }

        return $this->getNotificationSettings('email')?->isEnabled() ||
            $this->getNotificationSettings('discord')?->isEnabled() ||
            $this->getNotificationSettings('slack')?->isEnabled() ||
            $this->getNotificationSettings('telegram')?->isEnabled() ||
            $this->getNotificationSettings('pushover')?->isEnabled() ||
            $this->getNotificationSettings('webhook')?->isEnabled();
    }

    /**
     * Ends one subscription row. The servers stay usable while another row of the team still pays.
     */
    public function subscriptionEnded(Subscription $subscription): void
    {
        $subscription->update([
            'stripe_subscription_id' => null,
            'stripe_cancel_at_period_end' => false,
            'stripe_invoice_paid' => false,
            'stripe_trial_already_ended' => false,
            'stripe_past_due' => false,
        ]);
        if ($this->subscriptions()->get()->contains(fn (Subscription $other): bool => $other->isPaying())) {
            return;
        }
        foreach ($this->servers as $server) {
            $server->settings()->update([
                'is_usable' => false,
                'is_reachable' => false,
            ]);
            ServerReachabilityChanged::dispatch($server);
            $server->unreachable_count = 3;
            $server->save();
            NotificationThrottle::record($server, Unreachable::class);
        }
    }

    /**
     * Send a notification, unless it is throttled and was already sent within its interval.
     * A throttle claim is released when sending throws, so a failed send is retried on the next check.
     */
    public function notify($instance): void
    {
        $subject = $instance instanceof ThrottledNotification ? $instance->throttleSubject() : null;
        if ($subject === null) {
            $this->sendNotification($instance);

            return;
        }

        NotificationThrottle::sendOnce(
            $subject,
            $instance::class,
            now()->subMinutes($instance->throttleIntervalMinutes()),
            fn () => $this->sendNotification($instance),
        );
    }

    public function environment_variables()
    {
        return $this->hasMany(SharedEnvironmentVariable::class)->where('type', 'team');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_user', 'team_id', 'user_id')->withPivot('role', 'access');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * A team can have more than one subscription row, for example after it subscribed again.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function applications()
    {
        return $this->hasManyThrough(Application::class, Project::class);
    }

    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * @return array<string, int>
     */
    public function deletionBlockers(): array
    {
        return array_filter([
            'projects' => $this->projects()->count(),
            'servers' => $this->servers()->count(),
            'sources' => GithubApp::query()->where('team_id', $this->id)->where('is_system_wide', false)->count()
                + GitlabApp::query()->where('team_id', $this->id)->where('is_system_wide', false)->count()
                + GiteaApp::query()->where('team_id', $this->id)->where('is_system_wide', false)->count(),
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->deletionBlockers() === [];
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Servers that use a server slot. Archives of transferred servers are left out.
     */
    public function managedServers()
    {
        return $this->servers()->notTransferredAway();
    }

    public function usesSwarm(): bool
    {
        return $this->servers()
            ->where(function ($query) {
                $query->whereHas('settings', function ($settings) {
                    $settings->where('is_swarm_manager', true)
                        ->orWhere('is_swarm_worker', true);
                })->orWhereHas('swarmDockers');
            })
            ->exists();
    }

    public function privateKeys()
    {
        return $this->hasMany(PrivateKey::class);
    }

    public function cloudProviderTokens()
    {
        return $this->hasMany(CloudProviderToken::class);
    }

    public function integrationTokens()
    {
        return $this->hasMany(IntegrationToken::class);
    }

    public function sources()
    {
        $sources = collect([]);
        $github_apps = GithubApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', $this->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', false);
        })->get();

        $gitlab_apps = GitlabApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', $this->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', false);
        })->get();

        $gitea_apps = GiteaApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', $this->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', false);
        })->get();

        return $sources->merge($github_apps)->merge($gitlab_apps)->merge($gitea_apps);
    }

    public function s3s()
    {
        return $this->hasMany(S3Storage::class)->where('is_usable', true);
    }

    public function emailNotificationSettings()
    {
        return $this->hasOne(EmailNotificationSettings::class);
    }

    public function discordNotificationSettings()
    {
        return $this->hasOne(DiscordNotificationSettings::class);
    }

    public function telegramNotificationSettings()
    {
        return $this->hasOne(TelegramNotificationSettings::class);
    }

    public function slackNotificationSettings()
    {
        return $this->hasOne(SlackNotificationSettings::class);
    }

    public function pushoverNotificationSettings()
    {
        return $this->hasOne(PushoverNotificationSettings::class);
    }

    public function webhookNotificationSettings()
    {
        return $this->hasOne(WebhookNotificationSettings::class);
    }
}
