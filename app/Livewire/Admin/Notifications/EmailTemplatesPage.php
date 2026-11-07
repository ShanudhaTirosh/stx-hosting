<?php

namespace App\Livewire\Admin\Notifications;

use App\Models\Commerce\CommerceAuditLog;
use App\Models\EmailTemplate;
use App\Notifications\Commerce\EmailTemplatePreviewNotification;
use App\Services\Notifications\EmailTemplates;
use App\Services\RbacService;
use Livewire\Component;

/** Owner edits the wording of every customer email. Preview and test send use sample values. */
class EmailTemplatesPage extends Component
{
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

    public function render()
    {
        // Preview uses what is typed now, with sample values.
        $preview = EmailTemplates::renderDraft($this->key, [
            'subject' => $this->subject, 'heading' => $this->heading, 'body' => $this->body,
            'cta_text' => $this->ctaText, 'cta_path' => $this->ctaPath,
        ], EmailTemplates::sampleVars($this->key));

        return view('livewire.admin.notifications.email-templates', [
            'catalog' => EmailTemplates::catalog(),
            'overridden' => EmailTemplate::pluck('key')->all(),
            'variables' => EmailTemplates::catalog()[$this->key]['variables'],
            'preview' => $preview,
        ]);
    }
}
