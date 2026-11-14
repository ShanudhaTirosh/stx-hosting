<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Enhance mail_mailboxes table for platform management, purpose, and credentials
        if (Schema::hasTable('mail_mailboxes')) {
            Schema::table('mail_mailboxes', function (Blueprint $table) {
                if (! Schema::hasColumn('mail_mailboxes', 'purpose')) {
                    $table->string('purpose')->default('shared_inbox')->index(); // system_sender, shared_inbox, personal_mailbox
                }
                if (! Schema::hasColumn('mail_mailboxes', 'is_platform_managed')) {
                    $table->boolean('is_platform_managed')->default(false)->index();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'password_encrypted')) {
                    $table->text('password_encrypted')->nullable();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'auto_reply_enabled')) {
                    $table->boolean('auto_reply_enabled')->default(false);
                }
                if (! Schema::hasColumn('mail_mailboxes', 'auto_reply_subject')) {
                    $table->string('auto_reply_subject')->nullable();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'auto_reply_body')) {
                    $table->text('auto_reply_body')->nullable();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'auto_reply_start_at')) {
                    $table->timestamp('auto_reply_start_at')->nullable();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'auto_reply_end_at')) {
                    $table->timestamp('auto_reply_end_at')->nullable();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'forwarding_targets')) {
                    $table->json('forwarding_targets')->nullable();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'signature')) {
                    $table->text('signature')->nullable();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'is_sensitive')) {
                    $table->boolean('is_sensitive')->default(false)->index();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'hourly_send_limit')) {
                    $table->integer('hourly_send_limit')->default(100);
                }
                if (! Schema::hasColumn('mail_mailboxes', 'hourly_sent_count')) {
                    $table->integer('hourly_sent_count')->default(0);
                }
                if (! Schema::hasColumn('mail_mailboxes', 'suspended_at')) {
                    $table->timestamp('suspended_at')->nullable();
                }
                if (! Schema::hasColumn('mail_mailboxes', 'suspension_reason')) {
                    $table->string('suspension_reason')->nullable();
                }
            });
        }

        // 2. App-Specific Passwords for mail clients (Apple Mail, Outlook, Thunderbird, Scripts)
        if (! Schema::hasTable('mail_app_passwords')) {
            Schema::create('mail_app_passwords', function (Blueprint $table) {
                $table->id();
                $table->uuid('mailbox_id')->index();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('label'); // e.g. "MacBook Mail", "iPhone Outlook", "Billing Cron Script"
                $table->text('password_encrypted');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_revoked')->default(false)->index();
                $table->timestamps();

                $table->foreign('mailbox_id')->references('id')->on('mail_mailboxes')->cascadeOnDelete();
            });
        }

        // 3. System Email Type to Mailbox Mapping
        if (! Schema::hasTable('mail_system_mappings')) {
            Schema::create('mail_system_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('email_type')->unique(); // default, system, billing, deployments, support, security
                $table->uuid('mailbox_id');
                $table->string('from_name');
                $table->string('reply_to_address')->nullable();
                $table->boolean('fallback_to_relay')->default(true);
                $table->timestamps();

                $table->foreign('mailbox_id')->references('id')->on('mail_mailboxes')->cascadeOnDelete();
            });
        }

        // 4. Admin Filtered Delegated Mailbox Permissions
        if (! Schema::hasTable('mailbox_permissions')) {
            Schema::create('mailbox_permissions', function (Blueprint $table) {
                $table->id();
                $table->uuid('mailbox_id');
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('permission_level')->default('read_only')->index(); // read_only, read_write, send_as, manage
                $table->json('allowed_folders')->nullable(); // null = all folders, or ["INBOX", "Support"]
                $table->json('allowed_labels')->nullable(); // null = all labels
                $table->boolean('is_sensitive_read_only')->default(true);
                $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();

                $table->foreign('mailbox_id')->references('id')->on('mail_mailboxes')->cascadeOnDelete();
                $table->unique(['mailbox_id', 'user_id']);
            });
        }

        // 5. Shared Inbox Thread Collaborations
        if (! Schema::hasTable('mailbox_collaborations')) {
            Schema::create('mailbox_collaborations', function (Blueprint $table) {
                $table->id();
                $table->uuid('mailbox_id');
                $table->string('thread_id')->index();
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('open')->index(); // open, in_progress, resolved, closed
                $table->foreignId('last_read_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_read_at')->nullable();
                $table->foreignId('last_replied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_replied_at')->nullable();
                $table->text('internal_notes')->nullable();
                $table->timestamps();

                $table->foreign('mailbox_id')->references('id')->on('mail_mailboxes')->cascadeOnDelete();
                $table->unique(['mailbox_id', 'thread_id']);
            });
        }

        // 6. Tamper-Evident Mailbox Audit Logs
        if (! Schema::hasTable('mailbox_audit_logs')) {
            Schema::create('mailbox_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('mailbox_id')->nullable()->index();
                $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
                $table->string('action')->index(); // mailbox_created, mailbox_updated, mailbox_suspended, mailbox_deleted, permission_granted, permission_revoked, webmail_opened, app_password_generated, app_password_revoked, credentials_viewed
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('details')->nullable();
                $table->timestamp('created_at')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailbox_audit_logs');
        Schema::dropIfExists('mailbox_collaborations');
        Schema::dropIfExists('mailbox_permissions');
        Schema::dropIfExists('mail_system_mappings');
        Schema::dropIfExists('mail_app_passwords');

        if (Schema::hasTable('mail_mailboxes')) {
            Schema::table('mail_mailboxes', function (Blueprint $table) {
                $table->dropColumn([
                    'purpose',
                    'is_platform_managed',
                    'password_encrypted',
                    'auto_reply_enabled',
                    'auto_reply_subject',
                    'auto_reply_body',
                    'auto_reply_start_at',
                    'auto_reply_end_at',
                    'forwarding_targets',
                    'signature',
                    'is_sensitive',
                    'hourly_send_limit',
                    'hourly_sent_count',
                    'suspended_at',
                    'suspension_reason',
                ]);
            });
        }
    }
};
