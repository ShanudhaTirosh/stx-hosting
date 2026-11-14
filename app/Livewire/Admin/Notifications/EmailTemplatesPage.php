<?php

namespace App\Livewire\Admin\Notifications;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\EmailDeliveryLog;
use App\Models\EmailTemplate;
use App\Notifications\Commerce\EmailTemplatePreviewNotification;
use App\Services\Notifications\EmailTemplates;
use App\Services\RbacService;
use Livewire\Component;

/** Owner edits the wording of every customer email, reviews delivery logs, and inspects SMTP health. */
class EmailTemplatesPage extends Component
{
    public string $tab = 'templates'; // 'templates', 'logs', 'health'

    public string $logFilter = 'all'; // 'all', 'delivered', 'sent', 'failed'

    public string $logSearch = '';

    public string $key = 'order_placed';

    public string $subject = '';

    public string $heading = '';

    public string $body = '';

    public string $ctaText = '';

    public string $ctaPath = '';

    public function boot(): void
    {
        abort_unless(RbacService::can(auth()->user(), 'email_templates.manage'), 403, 'Only the owner can edit emails.');
    }

    public function mount(): void
    {
        $this->load($this->key);
    }

    public function select(string $key): void
    {
        abort_unless(in_array($key, EmailTemplates::keys(), true), 404);
        $this->resetValidation();
        $this->load($key);
    }

    private function load(string $key): void
    {
        $t = EmailTemplates::get($key);
        $this->key = $key;
        $this->subject = (string) $t['subject'];
        $this->heading = (string) $t['heading'];
        $this->body = (string) $t['body'];
        $this->ctaText = (string) ($t['cta_text'] ?? '');
        $this->ctaPath = (string) ($t['cta_path'] ?? '');
    }

    public function save(): void
    {
        $this->validate([
            'subject' => 'required|string|max:200',
            'heading' => 'required|string|max:200',
            'body' => 'required|string|max:8000',
            'ctaText' => 'nullable|string|max:80',
            // Buttons can only point inside this site.
            'ctaPath' => ['nullable', 'string', 'max:200', 'regex:#^/(?!/)[A-Za-z0-9/_\-{}.?=&]*$#'],
        ], ['ctaPath.regex' => 'The button link must be a path on this site, like /portal/billing.']);

        $unknown = EmailTemplates::unknownVariables($this->key, $this->subject, $this->heading, $this->body, $this->ctaPath);
        if ($unknown !== []) {
            $this->addError('body', 'These variables are not available in this email: {'.implode('}, {', $unknown).'}');

            return;
        }

        EmailTemplate::updateOrCreate(['key' => $this->key], [
            'subject' => trim($this->subject),
            'heading' => trim($this->heading),
            'body' => $this->body,
            'cta_text' => trim($this->ctaText) ?: null,
            'cta_path' => trim($this->ctaPath) ?: null,
            'updated_by' => auth()->id(),
        ]);

        CommerceAuditLog::record('email_template.saved', EmailTemplate::class, null, ['key' => $this->key]);
        $this->dispatch('success', 'Email saved.');
    }

    public function resetToDefault(): void
    {
        EmailTemplate::where('key', $this->key)->delete();
        CommerceAuditLog::record('email_template.reset', EmailTemplate::class, null, ['key' => $this->key]);
        $this->load($this->key);
        $this->dispatch('success', 'Back to the built-in wording.');
    }

    public function sendTest(): void
    {
        try {
            auth()->user()->notify(new EmailTemplatePreviewNotification($this->key));
            $this->dispatch('success', 'Test email sent to '.auth()->user()->email.'.');
        } catch (\Throwable $e) {
            $this->dispatch('error', 'Could not send: '.$e->getMessage());
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['templates', 'logs', 'health'], true) ? $tab : 'templates';
    }

    public function setLogFilter(string $filter): void
    {
        $this->logFilter = in_array($filter, ['all', 'delivered', 'sent', 'failed'], true) ? $filter : 'all';
    }

    public function retryLog(int $logId): void
    {
        $log = EmailDeliveryLog::find($logId);
        if (! $log) {
            $this->dispatch('error', 'Log entry not found.');

            return;
        }

        try {
            if ($log->template_key) {
                EmailTemplates::send($log->template_key, $log->recipient, $log->meta['vars'] ?? []);
            } else {
                \Illuminate\Support\Facades\Mail::raw("Retry of {$log->subject}", function ($msg) use ($log) {
                    $msg->to($log->recipient)->subject($log->subject);
                });
            }
            $log->update(['status' => 'delivered', 'error_message' => null]);
            $this->dispatch('success', "Email resent successfully to {$log->recipient}!");
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            $this->dispatch('error', 'Retry failed: '.$e->getMessage());
        }
    }

    public function render()
    {
        // Preview uses what is typed now, with sample values.
        $preview = EmailTemplates::renderDraft($this->key, [
            'subject' => $this->subject, 'heading' => $this->heading, 'body' => $this->body,
            'cta_text' => $this->ctaText, 'cta_path' => $this->ctaPath,
        ], EmailTemplates::sampleVars($this->key));

        $logs = null;
        $healthStats = null;

        if ($this->tab === 'logs') {
            try {
                $query = EmailDeliveryLog::latest();
                if ($this->logFilter !== 'all') {
                    $query->where('status', $this->logFilter);
                }
                if ($this->logSearch) {
                    $term = '%'.strtolower(trim($this->logSearch)).'%';
                    $query->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(recipient) LIKE ?', [$term])
                          ->orWhereRaw('LOWER(subject) LIKE ?', [$term])
                          ->orWhereRaw('LOWER(COALESCE(template_key, \'\')) LIKE ?', [$term]);
                    });
                }
                $logs = $query->take(50)->get();
            } catch (\Throwable) {
                $logs = collect();
            }
        } elseif ($this->tab === 'health') {
            try {
                $totalSent24h = EmailDeliveryLog::where('created_at', '>=', now()->subHours(24))->count();
                $delivered24h = EmailDeliveryLog::where('created_at', '>=', now()->subHours(24))->whereIn('status', ['sent', 'delivered'])->count();
                $failed24h = EmailDeliveryLog::where('created_at', '>=', now()->subHours(24))->where('status', 'failed')->count();
                $successRate = $totalSent24h > 0 ? round(($delivered24h / $totalSent24h) * 100, 1) : 100.0;
            } catch (\Throwable) {
                $totalSent24h = 0;
                $delivered24h = 0;
                $failed24h = 0;
                $successRate = 100.0;
            }

            $healthStats = [
                'total_sent_24h' => $totalSent24h,
                'delivered_24h' => $delivered24h,
                'failed_24h' => $failed24h,
                'success_rate' => $successRate,
                'default_mailer' => config('mail.default'),
                'smtp_host' => config('mail.mailers.smtp.host', 'mail.stxenterprises.com'),
                'smtp_port' => config('mail.mailers.smtp.port', 587),
                'smtp_encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                'relay_host' => config('mail.mailers.relay.host') ?: 'Standby (Failover pool ready)',
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ];
        }

        return view('livewire.admin.notifications.email-templates', [
            'tab' => $this->tab,
            'catalog' => EmailTemplates::catalog(),
            'overridden' => EmailTemplate::pluck('key')->all(),
            'variables' => EmailTemplates::catalog()[$this->key]['variables'],
            'preview' => $preview,
            'logs' => $logs,
            'healthStats' => $healthStats,
        ]);
    }
}
