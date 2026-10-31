<?php

namespace App\Livewire\Dashboard;

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\ApplicationDeploymentQueue;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The lists are computed, not public properties: Livewire would restore them with SELECT * on every poll.
 */
class ActiveDeployments extends Component
{
    private const COLUMNS = [
        'id',
        'application_id',
        'application_name',
        'deployment_url',
        'deployment_uuid',
        'pull_request_id',
        'server_name',
        'server_id',
        'status',
        'created_at',
        'finished_at',
    ];

    private const ACTIVE_STATUSES = [
        ApplicationDeploymentStatus::IN_PROGRESS->value,
        ApplicationDeploymentStatus::QUEUED->value,
        ApplicationDeploymentStatus::PENDING_APPROVAL->value,
    ];

    public function approveDeployment(int $id): void
    {
        try {
            if (! auth()->check() || ! RbacService::isOwner(auth()->user())) {
                throw new \Exception('Only the platform Owner can approve deployments.');
            }

            $deployment = ApplicationDeploymentQueue::findOrFail($id);
            if ($deployment->status !== ApplicationDeploymentStatus::PENDING_APPROVAL->value) {
                return;
            }

            $deployment->update([
                'status' => ApplicationDeploymentStatus::IN_PROGRESS->value,
                'reviewed_by_user_id' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            auditLog('deployment.approved', [
                'team_id' => $deployment->application?->team()?->id,
                'application_uuid' => $deployment->application?->uuid,
                'application_name' => $deployment->application_name,
                'deployment_uuid' => $deployment->deployment_uuid,
                'reviewed_by_user_id' => auth()->id(),
            ]);

            ApplicationDeploymentJob::dispatch(
                application_deployment_queue_id: $deployment->id,
            );

            $this->dispatch('success', 'Deployment approved and dispatched.');
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
    }

    public function rejectDeployment(int $id, ?string $reason = null): void
    {
        try {
            if (! auth()->check() || ! RbacService::isOwner(auth()->user())) {
                throw new \Exception('Only the platform Owner can reject deployments.');
            }

            $deployment = ApplicationDeploymentQueue::findOrFail($id);
            if ($deployment->status !== ApplicationDeploymentStatus::PENDING_APPROVAL->value) {
                return;
            }

            $deployment->update([
                'status' => ApplicationDeploymentStatus::REJECTED->value,
                'reviewed_by_user_id' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => $reason ?: 'Rejected by platform Owner.',
            ]);

            auditLog('deployment.rejected', [
                'team_id' => $deployment->application?->team()?->id,
                'application_uuid' => $deployment->application?->uuid,
                'application_name' => $deployment->application_name,
                'deployment_uuid' => $deployment->deployment_uuid,
                'reviewed_by_user_id' => auth()->id(),
                'rejection_reason' => $reason ?: 'Rejected by platform Owner.',
            ]);

            $this->dispatch('success', 'Deployment request rejected.');
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
    }

    /**
     * Not dehydrated, so it is loaded once per request and shared by both lists.
     *
     * @var Collection<int, string>|null
     */
    private ?Collection $teamApplicationIds = null;

    /**
     * Polling target. The lists are loaded again on every render.
     */
    public function refreshDeployments(): void {}

    #[Computed]
    public function activeDeployments(): Collection
    {
        return $this->deploymentsQuery()
            ->whereIn('application_id', $this->teamApplicationIds())
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('status')
            ->orderBy('id')
            ->limit(5)
            ->get(self::COLUMNS);
    }

    #[Computed]
    public function recentDeployments(): Collection
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return $this->deploymentsQuery()
                ->whereIn('application_id', $this->teamApplicationIds())
                ->whereNotIn('status', self::ACTIVE_STATUSES)
                ->orderByDesc('id')
                ->limit(5)
                ->get(self::COLUMNS);
        }

        // With "application_id IN (...) ORDER BY id DESC" PostgreSQL scans the primary key backwards and can
        // read most of the table for a team without recent deployments. Take the five newest of each
        // application from idx_deployment_queues_app_id instead; the five newest of the team are among them.
        $newestPerApplication = ApplicationDeploymentQueue::query()
            ->select(self::COLUMNS)
            ->whereColumn('application_id', 'team_applications.id')
            ->whereNotIn('status', self::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->limit(5);

        return $this->deploymentsQuery()
            ->fromRaw('unnest(?::varchar[]) as team_applications(id)', ['{'.$this->teamApplicationIds()->implode(',').'}'])
            ->joinLateral($newestPerApplication, 'application_deployment_queues')
            ->orderByDesc('application_deployment_queues.id')
            ->limit(5)
            ->get(array_map(fn (string $column) => "application_deployment_queues.{$column}", self::COLUMNS));
    }

    private function deploymentsQuery(): Builder
    {
        return ApplicationDeploymentQueue::query()->with([
            'application' => fn ($query) => $query->withoutGlobalScope('withRelations')->select(['id', 'environment_id']),
            'application.environment.project',
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function teamApplicationIds(): Collection
    {
        return $this->teamApplicationIds ??= ApplicationDeploymentQueue::applicationIdsOwnedByTeam(currentTeam()->id);
    }

    public function render()
    {
        return view('livewire.dashboard.active-deployments');
    }
}
