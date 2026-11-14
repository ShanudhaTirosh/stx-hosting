<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="pb-4 border-b border-neutral-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Email System &amp; Notifications</h1>
            <p class="text-xs text-neutral-400 mt-1">Manage branded email templates, inspect real-time delivery logs, and monitor SMTP deliverability.</p>
        </div>

        {{-- Top Tab Navigation --}}
        <div class="flex items-center gap-1 bg-neutral-900/90 border border-neutral-800 p-1 rounded-xl self-start md:self-auto">
            <button type="button" wire:click="setTab('templates')"
                @class([
                    'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5',
                    'bg-purple-600 text-white shadow-sm' => $tab === 'templates',
                    'text-neutral-400 hover:text-white' => $tab !== 'templates',
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span>Templates</span>
            </button>
            <button type="button" wire:click="setTab('logs')"
                @class([
                    'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5',
                    'bg-purple-600 text-white shadow-sm' => $tab === 'logs',
                    'text-neutral-400 hover:text-white' => $tab !== 'logs',
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                <span>Delivery Logs</span>
            </button>
            <button type="button" wire:click="setTab('health')"
                @class([
                    'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5',
                    'bg-purple-600 text-white shadow-sm' => $tab === 'health',
                    'text-neutral-400 hover:text-white' => $tab !== 'health',
                ])>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>SMTP &amp; Deliverability</span>
            </button>
        </div>
    </div>

    {{-- TAB 1: TEMPLATES --}}
    @if ($tab === 'templates')
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <nav class="lg:col-span-1 space-y-1" aria-label="Emails">
                @foreach ($catalog as $k => $item)
                    <button type="button" wire:click="select('{{ $k }}')" @if ($k === $key) aria-current="true" @endif
                        class="w-full text-left px-3 py-2 rounded text-xs transition-colors {{ $k === $key ? 'bg-purple-600 text-white font-bold' : 'bg-neutral-900 text-neutral-300 hover:bg-neutral-800' }}">
                        {{ $item['label'] }}
                        @if (in_array($k, $overridden, true))<span class="ml-1 text-[10px] opacity-70">(edited)</span>@endif
                    </button>
                @endforeach
            </nav>

            <div class="lg:col-span-3 grid grid-cols-1 xl:grid-cols-2 gap-6">
                <form wire:submit.prevent="save" class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-3">
                    <div>
                        <label for="et-subject" class="block text-xs font-bold text-neutral-300 mb-1">Subject</label>
                        <input id="et-subject" type="text" wire:model.live.debounce.400ms="subject" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        @error('subject') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="et-heading" class="block text-xs font-bold text-neutral-300 mb-1">Heading</label>
                        <input id="et-heading" type="text" wire:model.live.debounce.400ms="heading" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        @error('heading') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="et-body" class="block text-xs font-bold text-neutral-300 mb-1">Message (markdown)</label>
                        <textarea id="et-body" wire:model.live.debounce.400ms="body" rows="12" class="textarea textarea-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono"></textarea>
                        @error('body') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="et-cta" class="block text-xs font-bold text-neutral-300 mb-1">Button text</label>
                            <input id="et-cta" type="text" wire:model.live.debounce.400ms="ctaText" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs">
                        </div>
                        <div>
                            <label for="et-path" class="block text-xs font-bold text-neutral-300 mb-1">Button link (on this site)</label>
                            <input id="et-path" type="text" wire:model.live.debounce.400ms="ctaPath" class="input input-sm w-full bg-neutral-950 border-neutral-700 text-white rounded text-xs font-mono">
                            @error('ctaPath') <span class="text-rose-400 text-xs" role="alert">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="text-[11px] text-neutral-400">
                        <span class="font-bold text-neutral-300">Variables you can use:</span>
                        @foreach ($variables as $name => $sample)<code class="ml-1 text-emerald-400">{{ '{'.$name.'}' }}</code>@endforeach
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-neutral-800">
                        <div class="flex gap-3 text-xs">
                            <button type="button" wire:click="sendTest" class="text-purple-400 hover:text-purple-300">Send me a test</button>
                            <button type="button" wire:click="resetToDefault" wire:confirm="Go back to the built-in wording?" class="text-neutral-300 hover:text-white">Reset</button>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold rounded-lg text-xs">Save</button>
                    </div>
                </form>

                <section class="bg-white text-neutral-900 rounded-xl p-5 overflow-auto shadow-sm" aria-label="Preview">
                    <div class="text-[11px] text-neutral-500 font-mono uppercase tracking-wider">Subject Preview</div>
                    <div class="font-semibold text-neutral-900 mb-3">{{ $preview['subject'] }}</div>
                    <h2 class="text-lg font-bold text-neutral-950 mb-2">{{ $preview['heading'] }}</h2>
                    <div class="text-sm leading-relaxed text-neutral-800 [&_a]:underline [&_ul]:list-disc [&_ul]:pl-5 [&_blockquote]:border-l-4 [&_blockquote]:pl-3 [&_blockquote]:text-neutral-600">{!! $preview['html'] !!}</div>
                    @if ($preview['cta_text'] && $preview['cta_url'])
                        <span class="inline-block mt-4 px-4 py-2 bg-[#7146eb] text-white text-sm font-semibold rounded-lg shadow-sm">{{ $preview['cta_text'] }}</span>
                    @endif
                </section>
            </div>
        </div>
    @endif

    {{-- TAB 2: DELIVERY LOGS --}}
    @if ($tab === 'logs')
        <div class="space-y-4">
            {{-- Search & Filter Controls --}}
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-neutral-900 border border-neutral-800 p-4 rounded-xl">
                <div class="flex items-center gap-1.5 flex-wrap">
                    @foreach (['all' => 'All Statuses', 'delivered' => 'Delivered', 'sent' => 'Sent', 'failed' => 'Failed'] as $fKey => $fLabel)
                        <button type="button" wire:click="setLogFilter('{{ $fKey }}')"
                            @class([
                                'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                'bg-purple-600 text-white font-bold' => $logFilter === $fKey,
                                'bg-neutral-950 text-neutral-400 hover:text-white border border-neutral-800' => $logFilter !== $fKey,
                            ])>
                            {{ $fLabel }}
                        </button>
                    @endforeach
                </div>

                <div class="relative w-full sm:w-72">
                    <input type="text" wire:model.live.debounce.300ms="logSearch" placeholder="Search recipient or subject..."
                        class="w-full pl-9 pr-3 py-1.5 bg-neutral-950 border border-neutral-800 rounded-lg text-xs text-white placeholder-neutral-500 focus:outline-none focus:border-purple-500">
                    <svg class="w-4 h-4 text-neutral-500 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            {{-- Logs Table --}}
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-neutral-950 text-neutral-400 font-mono uppercase text-[10px] tracking-wider border-b border-neutral-800">
                            <tr>
                                <th class="py-3 px-4">Recipient</th>
                                <th class="py-3 px-4">Subject / Template</th>
                                <th class="py-3 px-4">Transport</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Sent At</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-800/60 font-sans">
                            @forelse ($logs as $log)
                                <tr class="hover:bg-neutral-800/40 transition-colors">
                                    <td class="py-3 px-4 font-mono text-white">
                                        {{ $log->recipient }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="text-white font-medium">{{ $log->subject }}</div>
                                        @if ($log->template_key)
                                            <span class="inline-block mt-0.5 text-[10px] font-mono px-1.5 py-0.5 rounded bg-neutral-800 text-purple-400">
                                                {{ $log->template_key }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 font-mono text-neutral-400">
                                        {{ $log->mailer ?: 'smtp' }}
                                    </td>
                                    <td class="py-3 px-4">
                                        @if ($log->status === 'delivered')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                Delivered
                                            </span>
                                        @elseif ($log->status === 'sent')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                                                <span class="w-1.5 h-1.5 rounded-full bg-sky-400"></span>
                                                Sent
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20" title="{{ $log->error_message }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                                Failed
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-neutral-400 font-mono text-[11px]">
                                        {{ $log->created_at ? $log->created_at->format('M d, H:i') : '—' }}
                                    </td>
                                    <td class="py-3 px-4 text-right">
                                        <button type="button" wire:click="retryLog({{ $log->id }})" wire:loading.attr="disabled"
                                            class="px-2.5 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-medium transition-colors">
                                            Resend
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-neutral-400">
                                        No email delivery logs recorded yet. Logs populate automatically as platform transactional emails are dispatched.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 3: SMTP & DELIVERABILITY HEALTH --}}
    @if ($tab === 'health' && $healthStats)
        <div class="space-y-6">
            {{-- Metrics Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 font-bold block">24H DISPATCHES</span>
                    <span class="text-2xl font-bold text-white font-mono">{{ $healthStats['total_sent_24h'] }}</span>
                    <span class="text-[11px] text-neutral-400 block">Total transactional emails</span>
                </div>
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 font-bold block">DELIVERED</span>
                    <span class="text-2xl font-bold text-emerald-400 font-mono">{{ $healthStats['delivered_24h'] }}</span>
                    <span class="text-[11px] text-neutral-400 block">Successfully accepted</span>
                </div>
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 font-bold block">FAILED / BOUNCES</span>
                    <span class="text-2xl font-bold text-rose-400 font-mono">{{ $healthStats['failed_24h'] }}</span>
                    <span class="text-[11px] text-neutral-400 block">Undelivered attempts</span>
                </div>
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-4 space-y-1">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-neutral-500 font-bold block">DELIVERY RATE</span>
                    <span class="text-2xl font-bold text-purple-400 font-mono">{{ $healthStats['success_rate'] }}%</span>
                    <span class="text-[11px] text-neutral-400 block">Outbound success ratio</span>
                </div>
            </div>

            {{-- Topology & Transport Configuration --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-neutral-800">
                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
                        <h3 class="font-bold text-white text-sm">Primary In-House Mailer (WildDuck)</h3>
                    </div>
                    <div class="space-y-2 text-xs font-mono">
                        <div class="flex justify-between py-1 border-b border-neutral-800/40">
                            <span class="text-neutral-400">SMTP Host:</span>
                            <span class="text-white">{{ $healthStats['smtp_host'] }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-neutral-800/40">
                            <span class="text-neutral-400">Port &amp; Security:</span>
                            <span class="text-white">{{ $healthStats['smtp_port'] }} ({{ strtoupper($healthStats['smtp_encryption']) }})</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-neutral-800/40">
                            <span class="text-neutral-400">Default Sender:</span>
                            <span class="text-white">{{ $healthStats['from_name'] }} &lt;{{ $healthStats['from_address'] }}&gt;</span>
                        </div>
                        <div class="flex justify-between py-1">
                            <span class="text-neutral-400">Active Mailer Driver:</span>
                            <span class="text-emerald-400">{{ strtoupper($healthStats['default_mailer']) }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-neutral-800">
                        <div class="w-2.5 h-2.5 rounded-full bg-sky-500"></div>
                        <h3 class="font-bold text-white text-sm">External Relay &amp; Failover Transport</h3>
                    </div>
                    <div class="space-y-2 text-xs font-mono">
                        <div class="flex justify-between py-1 border-b border-neutral-800/40">
                            <span class="text-neutral-400">Failover Stack:</span>
                            <span class="text-white font-bold">smtp &rarr; relay &rarr; log</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-neutral-800/40">
                            <span class="text-neutral-400">External Relay Host:</span>
                            <span class="text-white">{{ $healthStats['relay_host'] }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-neutral-800/40">
                            <span class="text-neutral-400">Automatic Failover:</span>
                            <span class="text-emerald-400 font-bold">Enabled</span>
                        </div>
                        <div class="flex justify-between py-1">
                            <span class="text-neutral-400">Plain-Text Alternative:</span>
                            <span class="text-emerald-400 font-bold">RFC Multipart Enforced</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SPF, DKIM, DMARC Deliverability Status --}}
            <div class="bg-neutral-900 border border-neutral-800 rounded-xl p-5 space-y-4">
                <h3 class="font-bold text-white text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Inbox Deliverability Protocols (SPF, DKIM, DMARC)</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs font-mono">
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-lg space-y-1">
                        <div class="flex justify-between text-neutral-400 font-bold">
                            <span>SPF Alignment</span>
                            <span class="text-emerald-400">PASS</span>
                        </div>
                        <p class="text-[11px] text-neutral-400 font-sans">TXT: v=spf1 include:_spf.stxenterprises.com ~all</p>
                    </div>
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-lg space-y-1">
                        <div class="flex justify-between text-neutral-400 font-bold">
                            <span>DKIM Signature</span>
                            <span class="text-emerald-400">2048-bit RSA</span>
                        </div>
                        <p class="text-[11px] text-neutral-400 font-sans">Selector: s1._domainkey (ZoneMTA / WildDuck)</p>
                    </div>
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-lg space-y-1">
                        <div class="flex justify-between text-neutral-400 font-bold">
                            <span>DMARC Enforcement</span>
                            <span class="text-emerald-400">p=reject</span>
                        </div>
                        <p class="text-[11px] text-neutral-400 font-sans">v=DMARC1; p=reject; rua=mailto:dmarc@stxenterprises.com</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
