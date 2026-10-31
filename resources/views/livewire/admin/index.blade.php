<div>
    <x-slot:title>Admin | STX Hosting</x-slot>
    <div class="mt-8 flex w-full max-w-none flex-col gap-6 lg:mt-3">
        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-[10px] border border-neutral-200 bg-white p-4 dark:border-white/[0.07] dark:bg-surface">
                <div class="text-xs font-medium text-neutral-500 dark:text-fg-dim">Current user</div>
                <div class="mt-2 truncate text-sm font-semibold text-black dark:text-fg">
                    {{ auth()->user()->name }}
                </div>
                <div class="mt-0.5 truncate text-xs text-neutral-500 dark:text-fg-faint">
                    {{ auth()->user()->email }}
                </div>
            </div>
            <div class="rounded-[10px] border border-neutral-200 bg-white p-4 dark:border-white/[0.07] dark:bg-surface">
                <div class="text-xs font-medium text-neutral-500 dark:text-fg-dim">Active subscribers</div>
                <div class="mt-2 text-2xl font-semibold tracking-tight text-black dark:text-fg">
                    {{ $activeSubscribers }}
                </div>
            </div>
            <div class="rounded-[10px] border border-neutral-200 bg-white p-4 dark:border-white/[0.07] dark:bg-surface">
                <div class="text-xs font-medium text-neutral-500 dark:text-fg-dim">Inactive subscribers</div>
                <div class="mt-2 text-2xl font-semibold tracking-tight text-black dark:text-fg">
                    {{ $inactiveSubscribers }}
                </div>
            </div>
        </section>

        <!-- Admin & Operations Hub Quick Nav -->
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ route('admin.operations.approvals') }}" class="group block rounded-[10px] border border-purple-500/30 bg-purple-500/5 p-4 hover:bg-purple-500/10 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-purple-400 uppercase tracking-wider">Governance</span>
                    <span class="h-2 w-2 rounded-full bg-purple-400"></span>
                </div>
                <div class="mt-2 text-sm font-semibold text-white group-hover:text-purple-300">Deployment Approvals</div>
                <div class="mt-0.5 text-xs text-neutral-400">Review and authorize admin deployment requests</div>
            </a>

            <a href="{{ route('admin.billing.proofs') }}" class="group block rounded-[10px] border border-blue-500/30 bg-blue-500/5 p-4 hover:bg-blue-500/10 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-blue-400 uppercase tracking-wider">Billing</span>
                    <span class="h-2 w-2 rounded-full bg-blue-400"></span>
                </div>
                <div class="mt-2 text-sm font-semibold text-white group-hover:text-blue-300">Proof Review Queue</div>
                <div class="mt-0.5 text-xs text-neutral-400">Verify manual customer bank payment receipts</div>
            </a>

            <a href="{{ route('admin.inventory.vps') }}" class="group block rounded-[10px] border border-emerald-500/30 bg-emerald-500/5 p-4 hover:bg-emerald-500/10 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Inventory</span>
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                </div>
                <div class="mt-2 text-sm font-semibold text-white group-hover:text-emerald-300">VPS Inventory Studio</div>
                <div class="mt-0.5 text-xs text-neutral-400">Manage unallocated VPS nodes and IP pools</div>
            </a>

            <a href="{{ route('admin.operations.provisioning') }}" class="group block rounded-[10px] border border-amber-500/30 bg-amber-500/5 p-4 hover:bg-amber-500/10 transition-colors">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Operations</span>
                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                </div>
                <div class="mt-2 text-sm font-semibold text-white group-hover:text-amber-300">VPS Delivery Queue</div>
                <div class="mt-0.5 text-xs text-neutral-400">Fulfill pending paid server orders</div>
            </a>
        </section>

        @if (session('impersonating'))
            <x-callout type="warning" title="Impersonation is active">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>You are viewing STX Hosting as {{ auth()->user()->name }}.</span>
                    <x-forms.button wire:click="back">Return to root user</x-forms.button>
                </div>
            </x-callout>
        @endif

        <section class="application-settings-section">
            <div class="application-settings-section-header">
                <div>
                    <h2>User lookup</h2>
                    <p>Find an account and switch into it for support or administration.</p>
                </div>
            </div>
            <div class="application-settings-section-body p-0!">
                <form wire:submit="submitSearch"
                    class="flex items-end gap-2 border-b border-neutral-200 p-4 dark:border-white/[0.06]">
                    <div class="max-w-md flex-1">
                        <x-forms.input wire:model="search" label="Name or email"
                            placeholder="Search for a user…" />
                    </div>
                    <x-forms.button type="submit">
                        <x-reicon name="search" class="size-4" />
                        Search
                    </x-forms.button>
                </form>

                @if ($search)
                    @if ($foundUsers->isEmpty())
                        <x-empty size="sm" title="No users found"
                            description="No account matches {{ $search }}." icon-name="profile" />
                    @else
                        <div class="data-table">
                            <div class="data-table-header admin-search-table-grid">
                                <span>Name</span>
                                <span>Email</span>
                                <span>Subscription</span>
                                <span class="text-right">Action</span>
                            </div>
                            @foreach ($foundUsers as $user)
                                @php
                                    $hasActiveSubscription = $user->teams()
                                        ->whereRelation('subscription', 'stripe_invoice_paid', true)
                                        ->exists();
                                @endphp
                                <div
                                    class="data-table-row admin-search-table-grid border-b border-neutral-200 last:border-b-0 dark:border-white/[0.06]">
                                    <div class="truncate text-[12px] font-semibold text-black dark:text-fg">
                                        {{ $user->name }}
                                    </div>
                                    <div class="truncate text-[11px] text-neutral-600 dark:text-fg-dim">
                                        {{ $user->email }}
                                    </div>
                                    <div>
                                        <x-status-badge :status="$hasActiveSubscription ? 'Active' : 'Inactive'"
                                            :type="$hasActiveSubscription ? 'success' : 'neutral'" />
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="button" class="button"
                                            wire:click="switchUser({{ $user->id }})">
                                            Switch user
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                            <div
                                class="flex min-h-11 items-center border-t border-neutral-200 px-4 text-[11px] text-neutral-500 dark:border-white/[0.08] dark:text-fg-faint">
                                {{ $foundUsers->count() }} {{ Str::plural('matching user', $foundUsers->count()) }}
                            </div>
                        </div>
                    @endif
                @else
                    <x-empty size="sm" title="Search for an account"
                        description="Enter a name or email address to begin." icon-name="profile" />
                @endif
            </div>
        </section>
    </div>
</div>
