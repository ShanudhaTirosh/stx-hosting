<?php

namespace App\Livewire\Admin\Operations;

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\ApplicationDeploymentQueue;
use App\Services\RbacService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DeploymentApprovals extends Component
{
    use AuthorizesRequests;

    public string $filterStatus = 'pending_approval';

    public ?int $rejectingDeploymentId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        if (! auth()->check() || (! RbacService::isAdmin(auth()->user()) && ! RbacService::isOwner(auth()->user()))) {
            abort(403, 'Unauthorized access to deployment approvals studio.');
        }
    }

    #[Computed]
    public function deployments(): Collection
    {
        $query = ApplicationDeploymentQueue::with(['application.environment.project', 'requester', 'reviewer'])
            ->orderByDesc('id')
            ->limit(50);

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        return $query->get();
    }

    public function approve(int $id): void
    {
        try {
            if (! auth()->check() || ! RbacService::isOwner(auth()->user())) {
                throw new \Exception('Only platform Owners are authorized to approve deployment requests.');
            }

            $deployment = ApplicationDeploymentQueue::findOrFail($id);

            if ($deployment->status !== ApplicationDeploymentStatus::PENDING_APPROVAL->value) {
                $this->dispatch('warning', 'Deployment is not in pending approval state.');

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

            $this->dispatch('success', "Deployment #{$deployment->id} approved and dispatched.");
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
    }

    public function promptReject(int $id): void
    {
        if (! auth()->check() || ! RbacService::isOwner(auth()->user())) {
            $this->dispatch('error', 'Only platform Owners are authorized to reject deployment requests.');

            return;
        }

        $this->rejectingDeploymentId = $id;
        $this->rejectionReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingDeploymentId = null;
        $this->rejectionReason = '';
    }

    public function confirmReject(): void
    {
        try {
            if (! auth()->check() || ! RbacService::isOwner(auth()->user())) {
                throw new \Exception('Only platform Owners are authorized to reject deployment requests.');
            }

            if (! $this->rejectingDeploymentId) {
                return;
            }

            $deployment = ApplicationDeploymentQueue::findOrFail($this->rejectingDeploymentId);

            if ($deployment->status !== ApplicationDeploymentStatus::PENDING_APPROVAL->value) {
                $this->dispatch('warning', 'Deployment is not in pending approval state.');
                $this->cancelReject();

                return;
            }

            $reason = trim($this->rejectionReason) ?: 'Rejected by platform Owner.';

            $deployment->update([
                'status' => ApplicationDeploymentStatus::REJECTED->value,
                'reviewed_by_user_id' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            auditLog('deployment.rejected', [
                'team_id' => $deployment->application?->team()?->id,
                'application_uuid' => $deployment->application?->uuid,
                'application_name' => $deployment->application_name,
                'deployment_uuid' => $deployment->deployment_uuid,
                'reviewed_by_user_id' => auth()->id(),
                'rejection_reason' => $reason,
            ]);

            $this->dispatch('success', "Deployment #{$deployment->id} rejected.");
            $this->cancelReject();
        } catch (\Throwable $e) {
            handleError($e, $this);
        }
    }

    public function render()
    {
        return view('livewire.admin.operations.deployment-approvals')
            ->layout('layouts.app', ['title' => 'Deployment Approvals Studio | STX Hosting']);
    }
}
