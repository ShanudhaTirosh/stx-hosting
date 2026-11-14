<?php

namespace App\Services\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Every customer email is a template: a subject, a heading, a markdown body and an optional button.
 * The owner can edit them; if they have not, the built-in text below is used.
 *
 * Variables are written {like_this}. Only the variables listed for a template are replaced, values are
 * escaped so a customer's name cannot inject markup, and the body cannot contain HTML.
 */
class EmailTemplates
{
    /** Marks a value that is already markdown written by us or the owner (not customer input). */
    public static function raw(string $markdown): object
    {
        return new class($markdown)
        {
            public function __construct(public string $markdown) {}
        };
    }

    /** @return array<string, array{label: string, variables: array<string, string>, defaults: array<string, string|null>}> */
    public static function catalog(): array
    {
        return [
            'order_placed' => [
                'label' => 'Order placed (invoice)',
                'variables' => ['name' => 'Customer name', 'invoice_number' => 'INV-202611-ABC123', 'amount' => '$19.99', 'due_date' => '10 Nov 2026', 'payment_instructions' => 'The payment methods you configured'],
                'defaults' => [
                    'subject' => 'Order confirmed: invoice {invoice_number}',
                    'heading' => 'Your order is placed',
                    'body' => "Hi {name},\n\nThank you for your order. Invoice **{invoice_number}** for **{amount}** is ready and due by {due_date}. The PDF invoice is attached when available.\n\n## How to pay\n\n{payment_instructions}\n\nWhen you have paid, upload your receipt from your billing page. We check every receipt by hand and email you at each step.",
                    'cta_text' => 'View invoice and upload receipt',
                    'cta_path' => '/portal/billing',
                ],
            ],
            'proof_received' => [
                'label' => 'Payment proof received',
                'variables' => ['name' => 'Customer name', 'invoice_number' => 'INV-202611-ABC123', 'amount' => '$19.99'],
                'defaults' => [
                    'subject' => 'We received your payment proof for {invoice_number}',
                    'heading' => 'We received your payment proof',
                    'body' => "Hi {name},\n\nWe received your payment proof for invoice **{invoice_number}** ({amount}). A person will check it and email you as soon as it is approved, or tell you what is wrong so you can send a new one.",
                    'cta_text' => 'View your invoices',
                    'cta_path' => '/portal/billing',
                ],
            ],
            'payment_approved' => [
                'label' => 'Payment approved',
                'variables' => ['name' => 'Customer name', 'invoice_number' => 'INV-202611-ABC123', 'amount' => '$19.99', 'fulfillment_note' => 'What happens next for this order'],
                'defaults' => [
                    'subject' => 'Payment approved: {invoice_number}',
                    'heading' => 'Payment approved',
                    'body' => "Hi {name},\n\nGood news: your payment for invoice **{invoice_number}** ({amount}) is approved.\n\n{fulfillment_note}",
                    'cta_text' => 'Open my services',
                    'cta_path' => '/portal/services',
                ],
            ],
            'payment_rejected' => [
                'label' => 'Payment rejected',
                'variables' => ['name' => 'Customer name', 'invoice_number' => 'INV-202611-ABC123', 'reason' => 'Reason entered by staff'],
                'defaults' => [
                    'subject' => 'We could not verify your payment for {invoice_number}',
                    'heading' => 'We could not verify your payment',
                    'body' => "Hi {name},\n\nWe checked the payment proof you sent for invoice **{invoice_number}** and could not accept it.\n\n**Reason:** {reason}\n\nYour invoice is still open. Please upload a corrected proof from your billing page and we will look at it again.",
                    'cta_text' => 'Upload a new proof',
                    'cta_path' => '/portal/billing',
                ],
            ],
            'service_delivered' => [
                'label' => 'VPS delivered',
                'variables' => ['name' => 'Customer name', 'hostname' => 'vps-01', 'ip_address' => '203.0.113.10', 'ssh_port' => '22', 'os_image' => 'debian-12', 'ssh_command' => 'ssh root@203.0.113.10', 'service_uuid' => 'uuid'],
                'defaults' => [
                    'subject' => 'Your VPS is ready: {hostname}',
                    'heading' => 'Your VPS is ready',
                    'body' => "Hi {name},\n\nYour server **{hostname}** is set up.\n\n- IP address: **{ip_address}**\n- SSH port: **{ssh_port}**\n- Operating system: **{os_image}**\n- Connect with: `{ssh_command}`\n\nYour root password is in your client panel, under the service. You will be asked for your account password before it is shown. Change it after your first login.",
                    'cta_text' => 'Open my server',
                    'cta_path' => '/portal/services/{service_uuid}',
                ],
            ],
            'ticket_reply' => [
                'label' => 'Support reply (offline fallback)',
                'variables' => ['name' => 'Customer name', 'subject' => 'Thread subject', 'message_excerpt' => 'First part of the reply', 'thread_uuid' => 'uuid'],
                'defaults' => [
                    'subject' => 'New reply to your support request: {subject}',
                    'heading' => 'We replied to your request',
                    'body' => "Hi {name},\n\nOur team replied to **{subject}**:\n\n> {message_excerpt}\n\nOpen the conversation to read everything and answer.",
                    'cta_text' => 'Open the conversation',
                    'cta_path' => '/portal/support/{thread_uuid}',
                ],
            ],
            'staff_proof_submitted' => [
                'label' => 'Staff: new payment proof',
                'variables' => ['name' => 'Staff name', 'invoice_number' => 'INV-202611-ABC123', 'amount' => '$19.99', 'method' => 'bank_transfer', 'attempt' => '1'],
                'defaults' => [
                    'subject' => 'New payment proof to verify: {invoice_number}',
                    'heading' => 'A payment proof needs review',
                    'body' => "Hi {name},\n\nA customer uploaded a payment proof.\n\n- Invoice: **{invoice_number}**\n- Amount: **{amount}**\n- Method: {method}\n- Attempt: {attempt}",
                    'cta_text' => 'Open the proof queue',
                    'cta_path' => '/admin/billing/proofs',
                ],
            ],
            'upcoming_renewal' => [
                'label' => 'Upcoming renewal invoice',
                'variables' => ['name' => 'Customer name', 'invoice_number' => 'INV-202611-ABC123', 'amount' => '$19.99', 'due_date' => '10 Nov 2026', 'product_name' => 'Web App Hosting'],
                'defaults' => [
                    'subject' => 'Upcoming renewal for {product_name}: invoice {invoice_number}',
                    'heading' => 'Your service renews soon',
                    'body' => "Hi {name},\n\nYour subscription for **{product_name}** is due for renewal. Renewal invoice **{invoice_number}** for **{amount}** is ready and due on **{due_date}**.\n\nPlease pay and upload your receipt before the due date to ensure continuous operation without service interruption.",
                    'cta_text' => 'View renewal invoice',
                    'cta_path' => '/portal/billing',
                ],
            ],
            'service_suspended' => [
                'label' => 'Service suspended for non-payment',
                'variables' => ['name' => 'Customer name', 'service_name' => 'App / Server name', 'reason' => 'Reason for suspension'],
                'defaults' => [
                    'subject' => 'Urgent: Service {service_name} has been suspended',
                    'heading' => 'Service Suspended',
                    'body' => "Hi {name},\n\nYour service **{service_name}** has been suspended due to an overdue payment.\n\nRunning containers or virtual machine instances have been halted. To restore your service immediately, please settle your outstanding renewal invoice.",
                    'cta_text' => 'Pay overdue invoice',
                    'cta_path' => '/portal/billing',
                ],
            ],
            'service_reactivated' => [
                'label' => 'Service reactivated',
                'variables' => ['name' => 'Customer name', 'service_name' => 'App / Server name'],
                'defaults' => [
                    'subject' => 'Service {service_name} has been reactivated',
                    'heading' => 'Service Reactivated',
                    'body' => "Hi {name},\n\nYour payment has been received and verified. Your service **{service_name}** has been reactivated and running workloads have been resumed.",
                    'cta_text' => 'Open client portal',
                    'cta_path' => '/portal/services',
                ],
            ],
            'payment_refunded' => [
                'label' => 'Payment refunded',
                'variables' => ['name' => 'Customer name', 'invoice_number' => 'INV-202611-ABC123', 'amount' => '$19.99', 'reason' => 'Reason for refund'],
                'defaults' => [
                    'subject' => 'Refund processed for invoice {invoice_number}',
                    'heading' => 'Refund Processed',
                    'body' => "Hi {name},\n\nA refund of **{amount}** has been processed for invoice **{invoice_number}**.\n\n**Reason:** {reason}\n\nDepending on your original payment method, the funds should reflect in your account according to standard banking timelines.",
                    'cta_text' => 'View invoices',
                    'cta_path' => '/portal/billing',
                ],
            ],
            'welcome_verify' => [
                'label' => 'Welcome and email verification',
                'variables' => ['name' => 'Customer name', 'verify_url' => '/verify', 'expires_in' => '24 hours'],
                'defaults' => [
                    'subject' => 'Confirm your STX Enterprises account',
                    'heading' => 'Welcome to STX Enterprises',
                    'body' => "Hi {name},\n\nThank you for signing up with STX Enterprises. To activate your account and start deploying your cloud services, please verify your email address.\n\nThis confirmation link expires in {expires_in}.",
                    'cta_text' => 'Verify My Email',
                    'cta_path' => '{verify_url}',
                ],
            ],
            'login_code' => [
                'label' => 'Login verification code (2FA)',
                'variables' => ['name' => 'Customer name', 'code' => '482910', 'ip_address' => '203.0.113.1', 'device' => 'Chrome on Windows'],
                'defaults' => [
                    'subject' => 'Your STX Enterprises verification code: {code}',
                    'heading' => 'Sign-in Verification Code',
                    'body' => "Hi {name},\n\nYour one-time sign-in code is:\n\n# **{code}**\n\nThis code is valid for 10 minutes. If you did not initiate this login from **{device}** (IP: `{ip_address}`), please secure your account immediately.",
                    'cta_text' => 'Open STX Hosting',
                    'cta_path' => '/login',
                ],
            ],
            'password_reset' => [
                'label' => 'Password reset request',
                'variables' => ['name' => 'Customer name', 'reset_url' => '/reset-password', 'ip_address' => '203.0.113.1'],
                'defaults' => [
                    'subject' => 'Reset your STX Enterprises password',
                    'heading' => 'Password Reset Request',
                    'body' => "Hi {name},\n\nWe received a request to reset the password for your STX Enterprises account from `{ip_address}`.\n\nClick the button below to choose a new password. This link expires in 15 minutes and can only be used once.",
                    'cta_text' => 'Reset My Password',
                    'cta_path' => '{reset_url}',
                ],
            ],
            'password_changed' => [
                'label' => 'Password changed security notification',
                'variables' => ['name' => 'Customer name', 'time' => '10 Nov 2026, 14:30 UTC', 'ip_address' => '203.0.113.1', 'support_url' => '/portal/support'],
                'defaults' => [
                    'subject' => 'Security Alert: Your password was changed',
                    'heading' => 'Password Changed',
                    'body' => "Hi {name},\n\nYour STX Enterprises account password was successfully changed on **{time}** from `{ip_address}`.\n\nAll other active sessions have been signed out. If you did not make this change, please contact our support team immediately to secure your account.",
                    'cta_text' => 'Contact Support',
                    'cta_path' => '{support_url}',
                ],
            ],
            'deploy_success' => [
                'label' => 'Deployment succeeded',
                'variables' => ['name' => 'Customer name', 'app_name' => 'API Service', 'commit_sha' => 'c891a2f', 'app_url' => '/portal/apps'],
                'defaults' => [
                    'subject' => 'Deployment succeeded: {app_name}',
                    'heading' => 'Deployment Live',
                    'body' => "Hi {name},\n\nGood news: your application **{app_name}** deployed successfully from commit `{commit_sha}`.\n\nYour application containers are running healthy and routing traffic.",
                    'cta_text' => 'Open Application',
                    'cta_path' => '{app_url}',
                ],
            ],
            'deploy_failed' => [
                'label' => 'Deployment failed',
                'variables' => ['name' => 'Customer name', 'app_name' => 'API Service', 'commit_sha' => 'c891a2f', 'logs_url' => '/portal/apps'],
                'defaults' => [
                    'subject' => 'Build failed: {app_name}',
                    'heading' => 'Deployment Failed',
                    'body' => "Hi {name},\n\nThe deployment for **{app_name}** on commit `{commit_sha}` encountered a build or startup error.\n\nYour previous stable version (if running) remains active. Please review the build logs to diagnose the issue.",
                    'cta_text' => 'View Build Logs',
                    'cta_path' => '{logs_url}',
                ],
            ],
            'domain_ssl_warning' => [
                'label' => 'Domain and SSL warning',
                'variables' => ['name' => 'Customer name', 'domain' => 'app.example.com', 'issue' => 'DNS check failed or SSL certificate renewal is pending', 'action_url' => '/portal/domains'],
                'defaults' => [
                    'subject' => 'Action required: Domain & SSL warning for {domain}',
                    'heading' => 'Domain / SSL Alert',
                    'body' => "Hi {name},\n\nWe detected an issue with your custom domain **{domain}**:\n\n> {issue}\n\nPlease check your DNS records and SSL configuration in your customer dashboard to avoid downtime.",
                    'cta_text' => 'Manage Domain',
                    'cta_path' => '{action_url}',
                ],
            ],
            'system_incident' => [
                'label' => 'System incident or maintenance notice',
                'variables' => ['name' => 'Customer name', 'title' => 'Scheduled maintenance', 'status' => 'investigating', 'impact' => 'Minor', 'status_url' => '/status'],
                'defaults' => [
                    'subject' => 'STX Status Alert: {title}',
                    'heading' => 'System Status Notice',
                    'body' => "Hi {name},\n\nOur automated monitoring systems detected an incident:\n\n- **Incident:** {title}\n- **Current Status:** {status}\n- **Impact:** {impact}\n\nOur engineering team is actively monitoring the situation. Real-time updates are published on our public status page.",
                    'cta_text' => 'View Status Page',
                    'cta_path' => '{status_url}',
                ],
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /** The template in force: the owner's edit, or the built-in text. @return array<string, string|null> */
    public static function get(string $key): array
    {
        $catalog = self::catalog()[$key] ?? throw new \InvalidArgumentException("Unknown email template '{$key}'.");
        $override = EmailTemplate::where('key', $key)->first();

        return $override
            ? $override->only(['subject', 'heading', 'body', 'cta_text', 'cta_path'])
            : $catalog['defaults'];
    }

    /** Variable names a template may use. */
    public static function variables(string $key): array
    {
        return array_keys(self::catalog()[$key]['variables'] ?? []);
    }

    /** Variables used in some text that this template does not provide. */
    public static function unknownVariables(string $key, string ...$texts): array
    {
        preg_match_all('/\{([a-z_]+)\}/', implode("\n", $texts), $found);

        return array_values(array_diff(array_unique($found[1]), self::variables($key)));
    }

    /** @return array{subject: string, heading: string, html: string, cta_text: ?string, cta_url: ?string} */
    public static function render(string $key, array $vars): array
    {
        return self::renderDraft($key, self::get($key), $vars);
    }

    /** Render a template that is not saved yet (the owner's editor preview). */
    public static function renderDraft(string $key, array $template, array $vars): array
    {
        $allowed = self::variables($key);

        $plain = function (string $text) use ($vars, $allowed): string {
            return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars, $allowed) {
                if (! in_array($m[1], $allowed, true)) {
                    return '';
                }
                $value = $vars[$m[1]] ?? '';

                return is_object($value) ? strip_tags($value->markdown) : strip_tags((string) $value);
            }, $text);
        };

        $markdown = preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($vars, $allowed) {
            if (! in_array($m[1], $allowed, true)) {
                return '';
            }
            $value = $vars[$m[1]] ?? '';

            // Customer-supplied text is escaped; markdown we wrote ourselves passes through.
            return is_object($value) ? $value->markdown : self::escapeMarkdown((string) $value);
        }, (string) $template['body']);

        $ctaPath = $template['cta_path'] ? preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => rawurlencode((string) ($vars[$m[1]] ?? '')), $template['cta_path']) : null;

        return [
            'subject' => trim($plain((string) $template['subject'])),
            'heading' => trim($plain((string) $template['heading'])),
            'html' => (string) Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'cta_text' => $template['cta_text'] ?: null,
            // Buttons only ever point at this site.
            'cta_url' => ($ctaPath && str_starts_with($ctaPath, '/') && ! str_starts_with($ctaPath, '//')) ? url($ctaPath) : null,
        ];
    }

    public static function message(string $key, array $vars): MailMessage
    {
        $rendered = self::render($key, $vars);
        $sender = \App\Models\Commerce\MailSystemMapping::resolveSender($key);

        $msg = (new MailMessage)
            ->from($sender['from_address'], $sender['from_name'])
            ->subject($rendered['subject'])
            ->view('emails.templated', $rendered)
            ->text('emails.templated-text', $rendered);

        if (! empty($sender['reply_to'])) {
            $msg->replyTo($sender['reply_to']);
        }

        return $msg;
    }

    /** Sample values for previews and test sends. */
    public static function sampleVars(string $key): array
    {
        $vars = self::catalog()[$key]['variables'];

        if (isset($vars['payment_instructions'])) {
            $vars['payment_instructions'] = self::raw("**Example Bank transfer**\n\n- Account name: Example\n- Account number: 123456\n");
        }
        if (isset($vars['fulfillment_note'])) {
            $vars['fulfillment_note'] = 'Your service is ready.';
        }

        return $vars;
    }

    public static function escapeMarkdown(string $text): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()#+\-.!|>~<])/', '\\\\$1', strip_tags($text));
    }
}
