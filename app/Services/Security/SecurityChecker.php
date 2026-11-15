<?php

namespace App\Services\Security;

use App\Models\Commerce\PaymentMethod;
use App\Models\NotificationRoute;
use App\Models\Team;
use App\Services\Backups\PlatformBackupStorage;
use App\Services\Commerce\VpsFulfillmentService;
use App\Services\Notifications\StaffEvents;
use Dompdf\Dompdf;

/**
 * A plain checklist of the settings that decide whether the platform is safe and working in production.
 * Each line is ok, warn (works, but you should fix it) or fail (fix before taking customers).
 */
class SecurityChecker
{
    /** @return array<int, array{level: 'ok'|'warn'|'fail', check: string, detail: string}> */
    public function run(): array
    {
        $production = app()->environment('production');
        $rows = [];
        $add = function (string $level, string $check, string $detail = '') use (&$rows) {
            $rows[] = ['level' => $level, 'check' => $check, 'detail' => $detail];
        };

        // Application basics
        $add(blank(config('app.key')) ? 'fail' : 'ok', 'Application key is set');
        $add($production && config('app.debug') ? 'fail' : 'ok', 'Debug mode is off in production', config('app.debug') ? 'APP_DEBUG is on: errors show internal details to visitors.' : '');
        $add($production && ! str_starts_with((string) config('app.url'), 'https://') ? 'fail' : 'ok', 'Site address uses HTTPS', (string) config('app.url'));
        $add($production && config('session.secure') !== true ? 'warn' : 'ok', 'Session cookies are HTTPS-only', 'Set SESSION_SECURE_COOKIE=true.');
        $add($production && config('queue.default') === 'sync' ? 'fail' : 'ok', 'Background jobs run on a queue', 'Emails, alerts, backups and restores need a queue worker (QUEUE_CONNECTION is sync).');
        $add(in_array('high', array_map('trim', explode(',', (string) config('security.queues'))), true) ? 'ok' : 'fail', 'The worker listens to the "high" queue', 'Customer emails and alerts use it.');
        $add($production && in_array(config('mail.default'), ['log', 'array'], true) ? 'warn' : 'ok', 'Email is sent for real', 'MAIL_MAILER is '.config('mail.default'));

        // Staff access
        $add(config('security.require_staff_2fa') ? 'ok' : 'warn', 'Staff must use two-factor sign-in', 'STX_REQUIRE_STAFF_2FA is off.');
        $root = Team::find(0);
        $add($root && $root->is_two_factor_required ? 'ok' : 'warn', 'The platform team requires two-factor');
        $without = $root ? $root->members()->whereNull('two_factor_confirmed_at')->pluck('email')->all() : [];
        $add($without === [] ? 'ok' : 'warn', 'Every staff member has two-factor on', $without === [] ? '' : 'Without it: '.implode(', ', $without));

        // Money and plans
        $add(config('hosting.enforce_entitlements') ? 'ok' : 'fail', 'Plans are enforced (paying unlocks deployments)', 'HOSTING_ENFORCE_ENTITLEMENTS is off: anyone can deploy for free.');
        $add(PaymentMethod::active()->exists() ? 'ok' : 'warn', 'At least one payment method is published', 'Customers see "contact support" until you add one.');
        $add(class_exists(Dompdf::class) ? 'ok' : 'warn', 'PDF invoices are available', 'Run: composer require dompdf/dompdf');

        // Where customer files live
        $add(in_array(config('filesystems.disks.'.config('commerce.proof_disk', 'payment_proofs').'.visibility'), ['public'], true) ? 'fail' : 'ok', 'Payment proofs are stored privately');
        $add(in_array(config('filesystems.disks.'.config('commerce.chat_disk', 'local').'.visibility'), ['public'], true) ? 'fail' : 'ok', 'Chat attachments are stored privately');

        // Hosting
        $add(filled(config('hosting.apps_domain')) ? 'ok' : 'warn', 'Apps get their own subdomain', 'Set HOSTING_APPS_DOMAIN and a wildcard DNS record.');
        $add(filled(config('hosting.ingress_ip')) ? 'ok' : 'warn', 'Custom domains can be pointed at us', 'Set HOSTING_INGRESS_IP.');

        // Backups
        $add(PlatformBackupStorage::storage() ? 'ok' : 'warn', 'Customer backups have a working storage', 'Pick the R2 bucket on the staff Backups page.');

        // Alerts
        $missing = collect([StaffEvents::ORDER_CREATED, StaffEvents::PROOF_UPLOADED, StaffEvents::BACKUP_FAILED])
            ->reject(fn ($e) => NotificationRoute::where('event', $e)->where('is_enabled', true)->exists());
        $add($missing->isEmpty() ? 'ok' : 'warn', 'Staff are alerted on Discord for orders, proofs and backup failures', $missing->isEmpty() ? '' : 'No channel for: '.$missing->implode(', '));

        // Service levels
        $overdue = VpsFulfillmentService::pendingQuery()->with('invoice')->get()
            ->filter(fn ($o) => VpsFulfillmentService::dueAt($o)->isPast())->count();
        $add($overdue === 0 ? 'ok' : 'warn', 'No VPS delivery is overdue', "{$overdue} paid VPS order(s) are past 72 hours.");

        return $rows;
    }
}
