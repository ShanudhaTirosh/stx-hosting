<div class="max-w-6xl mx-auto p-6 space-y-6">
    {{-- Header --}}
    <div class="pb-4 border-b border-neutral-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-mono uppercase tracking-wider text-purple-400 font-bold px-2 py-0.5 rounded bg-purple-500/10 border border-purple-500/20">
                    Staff Inbox Portal
                </span>
                <span class="text-xs text-neutral-400 font-mono">Delegated Access</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight mt-1">My Delegated Platform Mailboxes</h1>
            <p class="text-xs text-neutral-400 mt-1">Access shared inboxes and system mailboxes granted to you by the platform owner with zero shared passwords.</p>
        </div>

        @if (auth()->user()->isInstanceAdmin() && \App\Services\RbacService::isPlatformOwner(auth()->user()))
            <a href="{{ route('admin.mailboxes') }}"
                class="px-3.5 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold transition-colors flex items-center gap-1.5 self-start md:self-auto">
                <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Owner Mailbox Studio</span>
            </a>
        @endif
    </div>

    {{-- Mailboxes Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        @forelse ($mailboxes as $mailbox)
            @php
                $perm = $grantedPermissions->get($mailbox->id);
                $level = $perm ? $perm->permission_level : (\App\Services\RbacService::isPlatformOwner(auth()->user()) ? 'owner_root' : 'read_only');
            @endphp

            <div class="rounded-xl border border-neutral-800 bg-neutral-900/90 p-5 space-y-4 shadow-sm hover:border-neutral-700 transition-colors">
                <div class="flex justify-between items-start gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 font-bold shrink-0">
                            {{ strtoupper(substr($mailbox->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-white text-base tracking-tight font-mono">{{ $mailbox->address }}</h3>
                                @if ($mailbox->is_sensitive)
                                    <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-400 border border-rose-500/20" title="Enforces 2FA Step-up Verification">
                                        2FA SENSITIVE
                                    </span>
                                @endif
                            </div>
                            <span class="text-xs text-neutral-400 block">{{ $mailbox->name }}</span>
                        </div>
                    </div>

                    {{-- Permission Badge --}}
                    <span @class([
                        'px-2 py-0.5 rounded-full text-[10px] font-bold font-mono uppercase tracking-wider',
                        'bg-purple-500/10 text-purple-400 border border-purple-500/20' => in_array($level, ['read_write', 'owner_root']),
                        'bg-sky-500/10 text-sky-400 border border-sky-500/20' => $level === 'send_as',
                        'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' => $level === 'manage',
                        'bg-neutral-800 text-neutral-400 border border-neutral-700' => $level === 'read_only',
                    ])>
                        {{ str_replace('_', ' ', $level) }}
                    </span>
                </div>

                {{-- Folder Limitations if configured --}}
                @if ($perm && $perm->allowed_folders)
                    <div class="text-[11px] font-mono text-neutral-400 flex items-center gap-1.5">
                        <span class="text-neutral-500">Allowed Folders:</span>
                        <span class="text-purple-300">{{ implode(', ', $perm->allowed_folders) }}</span>
                    </div>
                @endif

                {{-- Actions Row --}}
                <div class="flex items-center justify-between pt-3 border-t border-neutral-800/80">
                    <button type="button" wire:click="openCredentials('{{ $mailbox->id }}')"
                        class="text-xs font-semibold text-neutral-300 hover:text-white transition-colors flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span>Connect to Apps &amp; Scripts</span>
                    </button>

                    <button type="button" wire:click="launchWebmail('{{ $mailbox->id }}')"
                        class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-colors flex items-center gap-1.5 shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        <span>Open Webmail (SSO)</span>
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-2 py-12 text-center text-neutral-400 bg-neutral-900 border border-neutral-800 rounded-xl space-y-2">
                <div class="text-base font-semibold text-white">No mailboxes currently delegated to you</div>
                <p class="text-xs text-neutral-500">The platform owner has not assigned any shared inboxes to your staff account yet.</p>
            </div>
        @endforelse
    </div>

    {{-- MODAL 1: CONNECTION CREDENTIALS & APP PASSWORDS --}}
    @if ($showCredentialsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl w-full max-w-2xl p-6 space-y-5 shadow-2xl overflow-y-auto max-h-[90vh]">
                <div class="flex justify-between items-center pb-3 border-b border-neutral-800">
                    <div>
                        <h3 class="text-lg font-bold text-white tracking-tight">Connect External Email Clients &amp; Scripts</h3>
                        <p class="text-xs text-neutral-400 font-mono">{{ $selectedMailboxAddress }}</p>
                    </div>
                    <button type="button" wire:click="$set('showCredentialsModal', false)" class="text-neutral-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Connection Parameters --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-mono">
                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-xl space-y-1.5">
                        <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">INCOMING SERVER (IMAP)</span>
                        <div class="flex justify-between text-neutral-300">
                            <span>Host:</span>
                            <span class="text-white font-bold">{{ config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com')) }}</span>
                        </div>
                        <div class="flex justify-between text-neutral-300">
                            <span>Port / Security:</span>
                            <span class="text-emerald-400 font-bold">993 (SSL/TLS)</span>
                        </div>
                        <div class="flex justify-between text-neutral-300">
                            <span>Username:</span>
                            <span class="text-white truncate max-w-[180px]">{{ $selectedMailboxAddress }}</span>
                        </div>
                    </div>

                    <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-xl space-y-1.5">
                        <span class="text-[10px] font-bold text-neutral-400 uppercase tracking-wider block">OUTGOING SERVER (SMTP)</span>
                        <div class="flex justify-between text-neutral-300">
                            <span>Host:</span>
                            <span class="text-white font-bold">{{ config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com')) }}</span>
                        </div>
                        <div class="flex justify-between text-neutral-300">
                            <span>Port / Security:</span>
                            <span class="text-emerald-400 font-bold">587 (STARTTLS)</span>
                        </div>
                        <div class="flex justify-between text-neutral-300">
                            <span>Auth:</span>
                            <span class="text-emerald-400 font-bold">Required (Same as incoming)</span>
                        </div>
                    </div>
                </div>

                {{-- 1-Click Apple Profile Download --}}
                <div class="p-3 bg-neutral-950 border border-neutral-800 rounded-xl flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-white block">Apple Devices Auto-Setup (iOS &amp; macOS)</span>
                        <span class="text-[11px] text-neutral-400">Download a configuration profile to set up Apple Mail in 1-click</span>
                    </div>
                    <a href="{{ route('mail.profiles.apple', $selectedMailboxId) }}" download
                        class="px-3.5 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold transition-colors flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.84c.66-.82 1.11-1.96.99-3.1-.97.04-2.14.65-2.83 1.46-.61.7-.1.14 1.83 1.02 1.84.1.01.21-.02.32-.05.02-.05.05-.1.08-.15z"/></svg>
                        <span>Download Profile</span>
                    </a>
                </div>

                {{-- App-Specific Passwords (Admins create their own password for Outlook/Thunderbird) --}}
                <div class="space-y-3 pt-2 border-t border-neutral-800">
                    <div>
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider font-mono">My App Passwords</h4>
                        <p class="text-[11px] text-neutral-400 mt-0.5">Generate a dedicated app password for your Thunderbird, Outlook, or smartphone mail app.</p>
                    </div>

                    <div class="flex gap-2">
                        <input type="text" wire:model="newAppPasswordLabel" placeholder="e.g. My Work Laptop Thunderbird"
                            class="input input-sm flex-1 bg-neutral-950 border-neutral-700 text-white text-xs rounded-lg">
                        <button type="button" wire:click="generateAppPassword" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-colors whitespace-nowrap">
                            Create App Password
                        </button>
                    </div>

                    @if ($justGeneratedAppPassword)
                        <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs space-y-1">
                            <span class="text-emerald-400 font-bold block">Your New App Password:</span>
                            <div class="p-2 bg-neutral-950 rounded font-mono text-emerald-300 font-bold select-all text-sm">
                                {{ $justGeneratedAppPassword }}
                            </div>
                            <span class="text-[10px] text-neutral-400">Copy this password now into your mail client's password field. It will not be shown again.</span>
                        </div>
                    @endif

                    @if ($userAppPasswords->isNotEmpty())
                        <div class="space-y-1.5">
                            @foreach ($userAppPasswords as $ap)
                                <div class="flex justify-between items-center p-2 rounded-lg bg-neutral-950 border border-neutral-800 text-xs font-mono">
                                    <div class="flex items-center gap-2">
                                        <span class="text-white font-bold">{{ $ap->label }}</span>
                                        <span class="text-[10px] text-neutral-500">Created {{ $ap->created_at->format('M d, Y') }}</span>
                                        @if ($ap->is_revoked)
                                            <span class="text-[9px] text-rose-400 uppercase font-bold">(Revoked)</span>
                                        @endif
                                    </div>
                                    @if (! $ap->is_revoked)
                                        <button type="button" wire:click="revokeAppPassword({{ $ap->id }})" class="text-rose-400 hover:text-rose-300 text-xs font-bold">
                                            Revoke
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Developer Code Snippets --}}
                <div class="space-y-2 pt-2 border-t border-neutral-800">
                    <div class="flex items-center gap-2">
                        @foreach (['laravel' => 'Laravel .env', 'python' => 'Python', 'nodejs' => 'Node.js', 'wordpress' => 'WordPress'] as $sKey => $sLabel)
                            <button type="button" wire:click="$set('activeSnippet', '{{ $sKey }}')"
                                @class([
                                    'px-2.5 py-1 rounded text-xs font-mono font-medium',
                                    'bg-purple-600 text-white' => $activeSnippet === $sKey,
                                    'text-neutral-400 hover:text-white bg-neutral-950' => $activeSnippet !== $sKey,
                                ])>
                                {{ $sLabel }}
                            </button>
                        @endforeach
                    </div>

                    @php
                        $host = config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.stxenterprises.com'));
                    @endphp

                    <div class="p-3 bg-neutral-950 rounded-xl font-mono text-[11px] text-neutral-300 border border-neutral-800 overflow-x-auto select-all">
                        @if ($activeSnippet === 'laravel')
MAIL_MAILER=smtp
MAIL_HOST={{ $host }}
MAIL_PORT=587
MAIL_USERNAME={{ $selectedMailboxAddress }}
MAIL_PASSWORD=your-generated-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS={{ $selectedMailboxAddress }}
MAIL_FROM_NAME="STX Staff"
                        @elseif ($activeSnippet === 'python')
import smtplib
from email.mime.text import MIMEText

msg = MIMEText('Hello from STX staff script')
msg['Subject'] = 'Automated Staff Notification'
msg['From'] = '{{ $selectedMailboxAddress }}'
msg['To'] = 'customer@example.com'

with smtplib.SMTP('{{ $host }}', 587) as server:
    server.starttls()
    server.login('{{ $selectedMailboxAddress }}', 'your-generated-app-password')
    server.send_message(msg)
                        @elseif ($activeSnippet === 'nodejs')
const nodemailer = require('nodemailer');

const transporter = nodemailer.createTransport({
    host: '{{ $host }}',
    port: 587,
    secure: false,
    auth: {
        user: '{{ $selectedMailboxAddress }}',
        pass: 'your-generated-app-password'
    }
});
                        @else
// WordPress wp-config.php or SMTP plugin:
define('SMTP_HOST', '{{ $host }}');
define('SMTP_PORT', 587);
define('SMTP_AUTH', true);
define('SMTP_USER', '{{ $selectedMailboxAddress }}');
define('SMTP_PASS', 'your-generated-app-password');
define('SMTP_SECURE', 'tls');
                        @endif
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-neutral-800">
                    <button type="button" wire:click="$set('showCredentialsModal', false)" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL 2: 2FA STEP-UP FOR SENSITIVE MAILBOXES --}}
    @if ($showStepUpModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-neutral-900 border border-neutral-800 rounded-2xl w-full max-w-sm p-6 space-y-4 shadow-2xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white tracking-tight">Step-Up 2FA Required</h3>
                        <p class="text-[11px] text-neutral-400">This mailbox contains sensitive platform data.</p>
                    </div>
                </div>

                <p class="text-xs text-neutral-300">
                    Please enter the 6-digit verification code sent to your registered account email to continue.
                </p>

                <div>
                    <input type="text" wire:model="stepUpCode" maxlength="6" placeholder="123456"
                        class="input w-full bg-neutral-950 border-neutral-700 text-white text-center font-mono text-lg tracking-widest rounded-xl">
                    @error('stepUpCode') <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-neutral-800">
                    <button type="button" wire:click="$set('showStepUpModal', false)" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-white text-xs font-semibold">
                        Cancel
                    </button>
                    <button type="button" wire:click="verifyStepUp" class="px-5 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold">
                        Confirm &amp; Proceed
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
