<div class="p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-neutral-700">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Deployment Approvals & Governance Studio</h1>
            <p class="text-sm text-neutral-400">Review, validate, approve, or reject administrator-initiated deployment requests across managed server nodes.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-neutral-400">Filter Status:</span>
            <select wire:model.live="filterStatus" class="bg-neutral-900 border border-neutral-700 text-white rounded-lg px-3 py-1.5 text-xs focus:ring-1 focus:ring-purple-500">
                <option value="pending_approval">Pending Approval</option>
                <option value="in_progress">In Progress</option>
                <option value="rejected">Rejected</option>
                <option value="queued">Queued</option>
                <option value="finished">Finished</option>
                <option value="all">All Deployments</option>
            </select>
        </div>
    </div>

    @if (\App\Services\RbacService::isOwner(auth()->user()))
        <div class="p-3 bg-purple-500/10 border border-purple-500/20 rounded-lg text-xs text-purple-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-purple-400 animate-pulse"></span>
                <strong>Platform Owner Mode:</strong> You have full governance authority to approve or reject deployment requests.
            </span>
            <span class="font-mono text-[11px] text-purple-400">RBAC: OWNER</span>
        </div>
    @else
        <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-lg text-xs text-amber-300 flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                <strong>Administrator View:</strong> Your deployments require Platform Owner approval prior to execution.
            </span>
            <span class="font-mono text-[11px] text-amber-400">RBAC: ADMIN</span>
        </div>
    @endif

    <!-- Deployments Table -->
    <div class="overflow-x-auto rounded-xl border border-neutral-800 bg-neutral-900 shadow-sm">
        <table class="w-full text-left text-sm text-neutral-300">
            <thead class="bg-neutral-800/60 text-xs uppercase tracking-wider text-neutral-400 border-b border-neutral-800">
                <tr>
                    <th class="px-4 py-3 font-semibold">ID / Application</th>
                    <th class="px-4 py-3 font-semibold">Target Server</th>
                    <th class="px-4 py-3 font-semibold">Git Commit / Ref</th>
                    <th class="px-4 py-3 font-semibold">Requested By</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Review Details</th>
                    <th class="px-4 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800/80">
                @forelse ($this->deployments as $deployment)
                    <tr class="hover:bg-neutral-800/40 transition-colors">
                        <td class="px-4 py-3">
                            <div class="font-semibold text-white">
                                {{ $deployment->application_name }}
                            </div>
                            <div class="text-[11px] font-mono text-neutral-500">
                                {{ $deployment->deployment_uuid }}
                            </div>
                            @if ($deployment->pull_request_id)
                                <div class="text-[11px] text-purple-400">PR #{{ $deployment->pull_request_id }}</div>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-neutral-800 text-xs text-neutral-300 font-mono">
                                {{ $deployment->server_name ?: 'Default Node' }}
                            </span>
                        </td>

                        <td class="px-4 py-3 font-mono text-xs">
                            <span class="text-purple-300">{{ substr($deployment->commit ?? 'HEAD', 0, 8) }}</span>
                            @if ($deployment->commit_message)
                                <div class="text-[11px] font-sans text-neutral-400 truncate max-w-xs" title="{{ $deployment->commit_message }}">
                                    {{ $deployment->commit_message }}
                                </div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-xs">
                            @if ($deployment->requester)
                                <div class="text-white font-medium">{{ $deployment->requester->name }}</div>
                                <div class="text-[11px] text-neutral-500">{{ $deployment->requester->email }}</div>
                            @elseif ($deployment->is_webhook)
                                <span class="px-2 py-0.5 rounded text-[10px] bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">GitHub Webhook</span>
                            @else
                                <span class="text-neutral-500">System</span>
                            @endif
                            <div class="text-[10px] text-neutral-600 mt-0.5">{{ $deployment->created_at->diffForHumans() }}</div>
                        </td>

                        <td class="px-4 py-3">
                            @if ($deployment->status === 'pending_approval')
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20 inline-flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                    Pending Approval
                                </span>
                            @elseif ($deployment->status === 'in_progress')
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/20 inline-flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                                    In Progress
                                </span>
                            @elseif ($deployment->status === 'rejected')
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-500/10 text-red-400 border border-red-500/20">
                                    Rejected
                                </span>
                            @elseif ($deployment->status === 'finished')
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    Success
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-neutral-800 text-neutral-400 border border-neutral-700">
                                    {{ ucfirst($deployment->status) }}
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-xs">
                            @if ($deployment->reviewer)
                                <div class="text-neutral-300">By: <span class="text-white">{{ $deployment->reviewer->name }}</span></div>
                                <div class="text-[10px] text-neutral-500">{{ $deployment->reviewed_at?->diffForHumans() }}</div>
                            @endif
                            @if ($deployment->rejection_reason)
                                <div class="mt-1 text-[11px] text-red-400 italic font-sans border-l-2 border-red-500 pl-2 max-w-xs">
                                    "{{ $deployment->rejection_reason }}"
                                </div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-right">
                            @if ($deployment->status === 'pending_approval' && \App\Services\RbacService::isOwner(auth()->user()))
                                <div class="inline-flex items-center gap-2">
                                    <button 
                                        type="button"
                                        wire:click="approve({{ $deployment->id }})"
                                        wire:loading.attr="disabled"
                                        class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs shadow transition-colors"
                                    >
                                        Approve
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="promptReject({{ $deployment->id }})"
                                        wire:loading.attr="disabled"
                                        class="px-2.5 py-1 rounded bg-red-600/80 hover:bg-red-600 text-white font-medium text-xs shadow transition-colors"
                                    >
                                        Reject
                                    </button>
                                </div>
                            @else
                                <a 
                                    href="{{ $deployment->deployment_url }}" 
                                    class="text-xs text-purple-400 hover:text-purple-300 underline"
                                >
                                    View Logs
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-neutral-500">
                            No deployments matching filter status "{{ $filterStatus }}".
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Rejection Reason Modal -->
    @if ($rejectingDeploymentId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-xl border border-neutral-700 bg-neutral-900 p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                    <h3 class="text-base font-bold text-white">Reject Deployment Request #{{ $rejectingDeploymentId }}</h3>
                    <button wire:click="cancelReject" class="text-neutral-400 hover:text-white">&times;</button>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-neutral-400 mb-1">
                        Reason for Rejection <span class="text-red-400">*</span>
                    </label>
                    <textarea 
                        wire:model="rejectionReason"
                        rows="3"
                        class="w-full rounded-lg border border-neutral-700 bg-neutral-800 p-2.5 text-sm text-white focus:border-red-500 focus:ring-1 focus:ring-red-500"
                        placeholder="Explain why this deployment request was rejected (e.g. pending branch freeze, invalid configuration, unauthorized node target)..."
                    ></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-neutral-800">
                    <button 
                        type="button" 
                        wire:click="cancelReject" 
                        class="px-3 py-1.5 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        wire:click="confirmReject" 
                        class="px-3 py-1.5 rounded bg-red-600 hover:bg-red-500 text-white text-xs font-medium shadow"
                    >
                        Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

