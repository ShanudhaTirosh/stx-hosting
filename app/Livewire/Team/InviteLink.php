<?php

namespace App\Livewire\Team;

use App\Enums\Role;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class InviteLink extends Component
{
    use AuthorizesRequests;

    public ?string $email = null;

    public string $role = 'member';

    public int $expires_in_days = 3;

    public int $max_uses = 1;

    public function mount()
    {
        $this->email = isDev() ? 'test3@example.com' : '';
    }

    public function viaEmail()
    {
        $this->generateInviteLink(sendEmail: true);
    }

    public function viaLink()
    {
        $this->generateInviteLink(sendEmail: false);
    }

    private function invitationUrl(string $routeName, array $parameters): string
    {
        $fqdn = instanceSettings()->fqdn;
        if (filled($fqdn)) {
            return rtrim($fqdn, '/').route($routeName, $parameters, false);
        }

        return route($routeName, $parameters);
    }

    private function generateInviteLink(bool $sendEmail = false)
    {
        try {
            $team = currentTeam();
            $this->authorize('manageInvitations', $team);

            if ($team->hasReachedSeatLimit()) {
                throw new \Exception("This team has reached its seat limit of {$team->seat_limit} members.");
            }

            // Role escalation validation
            $currentUserRoleStr = auth()->user()->roleInTeam($team->id) ?? auth()->user()->role();
            $currentUserRole = Role::tryFrom((string) $currentUserRoleStr) ?? Role::MEMBER;

            if ($currentUserRole->lt(Role::ADMIN) && in_array($this->role, ['admin', 'owner'], true)) {
                throw new \Exception('Members cannot invite admins or owners.');
            }
            if ($currentUserRole->lt(Role::OWNER) && $this->role === 'owner') {
                throw new \Exception('Only team owners can invite another owner.');
            }

            $email = filled($this->email) ? strtolower(trim($this->email)) : null;

            if ($sendEmail && empty($email)) {
                $this->addError('email', 'An email address is required to send an invitation via email.');

                return;
            }

            if ($email) {
                $memberEmails = $team->members()->pluck('email')->map(fn ($e) => strtolower($e));
                if ($memberEmails->contains($email)) {
                    return handleError(livewire: $this, customErrorMessage: "{$email} is already a member of {$team->name}.");
                }
            }

            $uuid = new_public_id(32);
            $link = $this->invitationUrl('team.invitation.show', ['uuid' => $uuid]);

            if ($email) {
                $user = User::whereEmail($email)->first();
                if (is_null($user)) {
                    $password = Str::password();
                    $user = User::create([
                        'name' => str($email)->before('@'),
                        'email' => $email,
                        'password' => Hash::make($password),
                        'force_password_reset' => true,
                    ]);
                    $token = Crypt::encryptString("{$user->email}@@@{$uuid}@@@{$password}");
                    $link = $this->invitationUrl('auth.link', ['token' => $token]);
                }

                $existingInvitation = TeamInvitation::ownedByCurrentTeam()->whereEmail($email)->first();
                if (! is_null($existingInvitation)) {
                    if ($existingInvitation->isValid()) {
                        return handleError(livewire: $this, customErrorMessage: "Pending invitation already exists for {$email}.");
                    }
                    $existingInvitation->delete();
                }
            }

            $expiresAt = Carbon::now()->addDays($this->expires_in_days);

            $invitation = TeamInvitation::create([
                'team_id' => $team->id,
                'uuid' => $uuid,
                'email' => $email,
                'role' => $this->role,
                'link' => $link,
                'via' => $sendEmail ? 'email' : 'link',
                'max_uses' => $this->max_uses,
                'uses_count' => 0,
                'expires_at' => $expiresAt,
            ]);

            auditLog('ui.team_invitation.created', [
                'team_id' => $team->id,
                'invitation_uuid' => $invitation->uuid,
                'invitation_email' => $invitation->email,
                'role' => $invitation->role,
                'via' => $invitation->via,
                'max_uses' => $invitation->max_uses,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ]);

            if ($sendEmail && $email) {
                $mail = new MailMessage;
                $mail->view('emails.invitation-link', [
                    'team' => $team->name,
                    'invitation_link' => $link,
                ]);
                $mail->subject('You have been invited to '.$team->name.' on '.config('app.name').'.');
                send_user_an_email($mail, $email);
                $this->dispatch('success', "Invitation sent to {$email}.");
            } else {
                $this->dispatch('success', 'Shareable invitation link generated.');
            }

            $this->dispatch('refreshInvitations');
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            if ($e->getCode() === '23505') {
                $errorMessage = 'Invitation already exists.';
            }

            return handleError(error: $e, livewire: $this, customErrorMessage: $errorMessage);
        }
    }
}
