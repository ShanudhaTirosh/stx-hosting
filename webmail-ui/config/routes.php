<?php
declare(strict_types=1);

use Webmail\Core\Router;
use Webmail\Http\Controllers\{AuthController, BulkController, ComposeController, ContactController, FolderController, LabelController, MailController, NotifyController, RuleController, SettingsController, SignatureController, SystemController, ThreadController};

return static function (Router $r): void {
    $auth = ['auth'];
    $r->add('GET', '/health', [SystemController::class, 'health']);
    $r->add('GET', '/', [MailController::class, 'root']);
    $r->add('GET', '/login', [AuthController::class, 'showLogin']);
    $r->add('POST', '/login', [AuthController::class, 'login']);
    $r->add('GET', '/login/2fa', [AuthController::class, 'showTwoFactor']);
    $r->add('POST', '/login/2fa', [AuthController::class, 'verifyTwoFactor']);
    $r->add('POST', '/logout', [AuthController::class, 'logout'], $auth);
    $r->add('POST', '/logout/all', [AuthController::class, 'logoutAll'], $auth);
    $r->add('GET', '/accounts/add', [AuthController::class, 'showAdd'], $auth);
    $r->add('POST', '/accounts/add', [AuthController::class, 'login'], $auth);
    $r->add('POST', '/accounts/switch', [AuthController::class, 'switchAccount'], $auth);
    $r->add('POST', '/accounts/{id}/signout', [AuthController::class, 'signOutAccount'], $auth);

    $r->add('GET', '/mail', [MailController::class, 'root'], $auth);
    $r->add('GET', '/search', [MailController::class, 'search'], $auth);
    $r->add('GET', '/mail/{box}', [MailController::class, 'list'], $auth);
    $r->add('GET', '/mail/{box}/{uid}', [MailController::class, 'show'], $auth);
    $r->add('GET', '/mail/{box}/{uid}/body', [MailController::class, 'body'], $auth);
    $r->add('GET', '/mail/{box}/{uid}/attachment/{att}', [MailController::class, 'attachment'], $auth);
    $r->add('POST', '/mail/{box}/{uid}/action', [MailController::class, 'action'], $auth);

    $r->add('POST', '/mail/bulk', [BulkController::class, 'apply'], $auth);
    $r->add('GET', '/thread/{id}', [ThreadController::class, 'show'], $auth);
    $r->add('GET', '/folders', [FolderController::class, 'index'], $auth);
    $r->add('POST', '/folders', [FolderController::class, 'create'], $auth);
    $r->add('POST', '/folders/{id}/rename', [FolderController::class, 'rename'], $auth);
    $r->add('GET', '/folders/{id}/delete', [FolderController::class, 'confirmDelete'], $auth);
    $r->add('POST', '/folders/{id}/delete', [FolderController::class, 'delete'], $auth);
    $r->add('GET', '/labels', [LabelController::class, 'index'], $auth);
    $r->add('POST', '/labels', [LabelController::class, 'create'], $auth);
    $r->add('POST', '/labels/{id}/update', [LabelController::class, 'update'], $auth);
    $r->add('GET', '/labels/{id}/delete', [LabelController::class, 'confirmDelete'], $auth);
    $r->add('POST', '/labels/{id}/delete', [LabelController::class, 'delete'], $auth);
    $r->add('GET', '/label/{id}', [LabelController::class, 'show'], $auth);

    $r->add('GET', '/contacts', [ContactController::class, 'index'], $auth);
    $r->add('POST', '/contacts', [ContactController::class, 'create'], $auth);
    $r->add('POST', '/contacts/quick', [ContactController::class, 'quick'], $auth);
    $r->add('POST', '/contacts/{id}/update', [ContactController::class, 'update'], $auth);
    $r->add('GET', '/contacts/{id}/delete', [ContactController::class, 'confirmDelete'], $auth);
    $r->add('POST', '/contacts/{id}/delete', [ContactController::class, 'delete'], $auth);
    $r->add('GET', '/api/notify', [NotifyController::class, 'poll'], $auth);
    $r->add('GET', '/api/suggest', [ContactController::class, 'suggest'], $auth);
    $r->add('GET', '/signatures', [SignatureController::class, 'index'], $auth);
    $r->add('POST', '/signatures', [SignatureController::class, 'create'], $auth);
    $r->add('POST', '/signatures/default', [SignatureController::class, 'setDefault'], $auth);
    $r->add('POST', '/signatures/{id}/update', [SignatureController::class, 'update'], $auth);
    $r->add('GET', '/signatures/{id}/delete', [SignatureController::class, 'confirmDelete'], $auth);
    $r->add('POST', '/signatures/{id}/delete', [SignatureController::class, 'delete'], $auth);

    $r->add('GET', '/settings', [SettingsController::class, 'root'], $auth);
    $r->add('GET', '/settings/profile', [SettingsController::class, 'profile'], $auth);
    $r->add('POST', '/settings/profile', [SettingsController::class, 'saveName'], $auth);
    $r->add('POST', '/settings/password', [SettingsController::class, 'changePassword'], $auth);
    $r->add('GET', '/settings/identities', [SettingsController::class, 'identities'], $auth);
    $r->add('POST', '/settings/identities/{id}', [SettingsController::class, 'renameIdentity'], $auth);
    $r->add('GET', '/settings/autoreply', [SettingsController::class, 'autoreply'], $auth);
    $r->add('POST', '/settings/autoreply', [SettingsController::class, 'saveAutoreply'], $auth);

    $r->add('GET', '/rules', [RuleController::class, 'index'], $auth);
    $r->add('GET', '/rules/new', [RuleController::class, 'newForm'], $auth);
    $r->add('POST', '/rules', [RuleController::class, 'create'], $auth);
    $r->add('GET', '/rules/{id}/edit', [RuleController::class, 'editForm'], $auth);
    $r->add('POST', '/rules/{id}', [RuleController::class, 'update'], $auth);
    $r->add('POST', '/rules/{id}/toggle', [RuleController::class, 'toggle'], $auth);
    $r->add('GET', '/rules/{id}/delete', [RuleController::class, 'confirmDelete'], $auth);
    $r->add('POST', '/rules/{id}/delete', [RuleController::class, 'delete'], $auth);
    $r->add('POST', '/blocked', [RuleController::class, 'block'], $auth);
    $r->add('POST', '/blocked/quick', [RuleController::class, 'quickBlock'], $auth);

    $r->add('GET', '/compose', [ComposeController::class, 'blank'], $auth);
    $r->add('POST', '/compose', [ComposeController::class, 'submit'], $auth);
    $r->add('GET', '/mail/{box}/{uid}/reply', [ComposeController::class, 'reply'], $auth);
    $r->add('GET', '/mail/{box}/{uid}/reply-all', [ComposeController::class, 'replyAll'], $auth);
    $r->add('GET', '/mail/{box}/{uid}/forward', [ComposeController::class, 'forward'], $auth);
    $r->add('GET', '/mail/{box}/{uid}/edit', [ComposeController::class, 'edit'], $auth);
};
