<div>
    <x-slot:title>Owner Console | STX Hosting</x-slot>
    <div class="mt-8 flex w-full max-w-none flex-col gap-6 lg:mt-3">
        <!-- Top Metrics Cards -->
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-[10px] border border-neutral-200 bg-white p-4 dark:border-white/[0.07] dark:bg-surface">
                <div class="text-xs font-medium text-neutral-500 dark:text-fg-dim">Platform Owner</div>
                <div class="mt-2 truncate text-sm font-semibold text-black dark:text-fg">
                    {{ auth()->user()->name }}
                </div>
                <div class="mt-0.5 truncate text-xs text-neutral-500 dark:text-fg-faint">
                    {{ auth()->user()->email }} (Root Team #0)
                </div>
            </div>
            <div class="rounded-[10px] border border-neutral-200 bg-white p-4 dark:border-white/[0.07] dark:bg-surface">
                <div class="text-xs font-medium text-neutral-500 dark:text-fg-dim">Customer Teams</div>
                <div class="mt-2 text-2xl font-semibold tracking-tight text-black dark:text-fg">
                    {{ $totalTeams }}
                </div>
                <div class="mt-0.5 text-xs text-neutral-500 dark:text-fg-faint">
                    {{ $totalUsers }} registered accounts
                </div>
            </div>
            <div class="rounded-[10px] border border-neutral-200 bg-white p-4 dark:border-white/[0.07] dark:bg-surface">
                <div class="text-xs font-medium text-neutral-500 dark:text-fg-dim">Active Entitlements</div>
                <div class="mt-2 text-2xl font-semibold tracking-tight text-emerald-600 dark:text-emerald-400">
                    {{ $activeEntitlements }}
                </div>
                <div class="mt-0.5 text-xs text-neutral-500 dark:text-fg-faint">
                    Paid active services
                </div>
            </div>
            <div class="rounded-[10px] border border-neutral-200 bg-white p-4 dark:border-white/[0.07] dark:bg-surface">
                <div class="text-xs font-medium text-neutral-500 dark:text-fg-dim">Pending Action Queues</div>
                <div class="mt-2 text-2xl font-semibold tracking-tight text-amber-500">
                    {{ $pendingProofsCount + $pendingOrdersCount }}
                </div>
                <div class="mt-0.5 text-xs text-neutral-500 dark:text-fg-faint">
                    {{ $pendingProofsCount }} proofs, {{ $pendingOrdersCount }} VPS delivery
                </div>
            </div>
        </section>

        <!-- Platform Hub Quick Nav -->
        <div>
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-neutral-500 dark:text-fg-dim">Platform Control & Governance</h3>
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('owner.admins') }}" class="group block rounded-[10px] border border-indigo-500/30 bg-indigo-500/5 p-4 hover:bg-indigo-500/10 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">Governance</span>
                        <span class="h-2 w-2 rounded-full bg-indigo-400"></span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-indigo-300">Admin Staff & Invariants</div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Manage platform admins and ownership transfer</div>
                </a>

                <a href="{{ route('owner.commerce.products') }}" class="group block rounded-[10px] border border-sky-500/30 bg-sky-500/5 p-4 hover:bg-sky-500/10 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-sky-400 uppercase tracking-wider">Catalog</span>
                        <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-sky-300">Products & Pricing Studio</div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Configure VPS specs, RAM/Disk tiers, and prices</div>
                </a>

                <a href="{{ route('owner.billing.methods') }}" class="group block rounded-[10px] border border-emerald-500/30 bg-emerald-500/5 p-4 hover:bg-emerald-500/10 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Finance</span>
                        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-emerald-300">Payment Methods</div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Bank accounts, SWIFT, wallets, and deposit instructions</div>
                </a>

                <a href="{{ route('owner.billing.verification-settings') }}" class="group block rounded-[10px] border border-purple-500/30 bg-purple-500/5 p-4 hover:bg-purple-500/10 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-purple-400 uppercase tracking-wider">PayVerify AI</span>
                        <span class="h-2 w-2 rounded-full bg-purple-400"></span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-purple-300">Verification Engine</div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">PayVerify API, confidence gates, Gemini Vision keys</div>
                </a>

                <a href="{{ route('owner.billing.proofs') }}" class="group block rounded-[10px] border border-blue-500/30 bg-blue-500/5 p-4 hover:bg-blue-500/10 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-blue-400 uppercase tracking-wider">Operations</span>
                        <span class="h-2 w-2 rounded-full bg-blue-400"></span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-blue-300">Proof Review Queue</div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Review manual customer bank slips ({{ $pendingProofsCount }} pending)</div>
                </a>

                <a href="{{ route('owner.operations.provisioning') }}" class="group block rounded-[10px] border border-amber-500/30 bg-amber-500/5 p-4 hover:bg-amber-500/10 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Operations</span>
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-amber-300">VPS Delivery Queue</div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Fulfill pending paid server orders ({{ $pendingOrdersCount }} pending)</div>
                </a>

                <a href="{{ $studioUrl }}" target="_blank" rel="noopener noreferrer" class="group block rounded-[10px] border border-emerald-500/40 bg-emerald-500/10 p-4 hover:bg-emerald-500/20 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Supabase BaaS</span>
                        <span class="flex items-center gap-1.5 text-[10px] font-semibold text-emerald-400">
                            <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Web UI (:8001)
                        </span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-emerald-300 flex items-center gap-1.5">
                        <span>Supabase Studio</span>
                        <x-reicon name="external-link" class="size-3.5" />
                    </div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Manage database tables, auth users, RLS, storage, and SQL editor</div>
                </a>

                <a href="{{ route('owner.platform.domains') }}" class="group block rounded-[10px] border border-cyan-500/30 bg-cyan-500/5 p-4 hover:bg-cyan-500/10 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-cyan-400 uppercase tracking-wider">Networking</span>
                        <span class="h-2 w-2 rounded-full bg-cyan-400"></span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-cyan-300">Platform Domains & SSL</div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Cloudflare API, ingress IP, wildcard app DNS</div>
                </a>

                <a href="{{ route('owner.settings.index') }}" class="group block rounded-[10px] border border-neutral-500/30 bg-neutral-500/5 p-4 hover:bg-neutral-500/10 transition-colors">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-neutral-400 uppercase tracking-wider">System</span>
                        <span class="h-2 w-2 rounded-full bg-neutral-400"></span>
                    </div>
                    <div class="mt-2 text-sm font-semibold text-black dark:text-white group-hover:text-neutral-300">Instance Settings</div>
                    <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">FQDN, IP allowlists, SMTP, Supabase keys, backups</div>
                </a>
            </section>
        </div>

        @if (session('impersonating'))
            <x-callout type="warning" title="Impersonation is active">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>You are viewing STX Hosting as {{ auth()->user()->name }}.</span>
                    <x-forms.button wire:click="back">Return to Owner Console</x-forms.button>
                </div>
            </x-callout>
        @endif

        <!-- Customer Accounts Lookup & Impersonation (Owner-Only) -->
        <section class="application-settings-section">
            <div class="application-settings-section-header">
                <div>
                    <h2>User lookup & customer support</h2>
                    <p>Find an account and assume their session for diagnostic and support purposes.</p>
                </div>
            </div>
            <div class="application-settings-section-body p-0!">
                <form wire:submit="submitSearch"
                    class="flex items-end gap-2 border-b border-neutral-200 p-4 dark:border-white/[0.06]">
                    <div class="max-w-md flex-1">
                        <x-forms.input wire:model="search" label="Name or email"
                            placeholder="Search by name or email…" />
                    </div>
                    <x-forms.button type="submit">
                        <x-reicon name="search" class="size-4" />
                        Search
                    </x-forms.button>
                </form>

                @if ($search)
                    @if ($foundUsers->isEmpty())
                        <x-empty size="sm" title="No users found"
                            description="No account matches '{{ $search }}'." icon-name="profile" />
                    @else
                        <div class="data-table">
                            <div class="data-table-header admin-search-table-grid">
                                <span>Name</span>
                                <span>Email</span>
                                <span>Teams</span>
                                <span class="text-right">Action</span>
                            </div>
                            @foreach ($foundUsers as $user)
                                <div
                                    class="data-table-row admin-search-table-grid border-b border-neutral-200 last:border-b-0 dark:border-white/[0.06]">
                                    <div class="truncate text-[12px] font-semibold text-black dark:text-fg">
                                        {{ $user->name }}
                                    </div>
                                    <div class="truncate text-[11px] text-neutral-600 dark:text-fg-dim">
                                        {{ $user->email }}
                                    </div>
                                    <div class="text-[11px] text-neutral-500">
                                        {{ $user->teams->pluck('name')->implode(', ') ?: 'No teams' }}
                                    </div>
                                    <div class="flex justify-end">
                                        @if ($user->id !== auth()->id())
                                            <button type="button" class="button"
                                                wire:click="switchUser({{ $user->id }})">
                                                Switch into user
                                            </button>
                                        @else
                                            <span class="text-xs text-neutral-400">Current user</span>
                                        @endif
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
                        description="Enter a name or email address to search customer accounts." icon-name="profile" />
                @endif
            </div>
        </section>
    </div>
</div>
