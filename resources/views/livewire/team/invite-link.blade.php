<div>
    @can('manageInvitations', currentTeam())
        <form wire:submit="viaLink">
            <x-application.settings-section title="Invite a member"
                description="Create a shareable invitation link or deliver it directly by email.">
                <x-slot:actions>
                    @if (is_transactional_emails_enabled())
                        <x-forms.button type="button" wire:click.prevent="viaEmail" wire:target="viaEmail">
                            <x-reicon name="notifications" class="size-3.5" />
                            Send email
                        </x-forms.button>
                    @endif
                    <x-forms.button type="submit" wire:target="viaLink"
                        defaultClass="button button-highlighted">
                        <x-reicon name="plus" class="size-3.5" />
                        Generate link
                    </x-forms.button>
                </x-slot:actions>

                @if (!is_transactional_emails_enabled() && isInstanceAdmin())
                    <x-callout type="warning" title="Email delivery is not configured">
                        Configure transactional email in instance settings to send invitations directly by email. Shareable links work without email configured.
                    </x-callout>
                @endif

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <x-forms.input id="email" type="email" label="Email address (Optional for shareable link)"
                        placeholder="teammate@example.com (leave blank for open link)" />
                    <x-forms.listbox id="role" label="Role" :options="array_values(array_filter([
                        auth()->user()->role() === 'owner' ? ['value' => 'owner', 'label' => 'Owner (Full Control)'] : null,
                        ['value' => 'admin', 'label' => 'Admin (Management & Deploy)'],
                        ['value' => 'developer', 'label' => 'Developer (Deploy & Domains)'],
                        ['value' => 'billing', 'label' => 'Billing (Invoices & Payments)'],
                        ['value' => 'viewer', 'label' => 'Viewer (Read-Only)'],
                        ['value' => 'member', 'label' => 'Member (Standard)'],
                    ]))" />
                    <x-forms.listbox id="expires_in_days" label="Expiration" :options="[
                        ['value' => 1, 'label' => 'Expires in 24 hours'],
                        ['value' => 3, 'label' => 'Expires in 3 days'],
                        ['value' => 7, 'label' => 'Expires in 7 days'],
                        ['value' => 30, 'label' => 'Expires in 30 days'],
                    ]" />
                    <x-forms.listbox id="max_uses" label="Usage Limit" :options="[
                        ['value' => 1, 'label' => 'Single use (1 acceptance)'],
                        ['value' => 5, 'label' => 'Limited (Up to 5 acceptances)'],
                        ['value' => 10, 'label' => 'Limited (Up to 10 acceptances)'],
                        ['value' => 0, 'label' => 'Unlimited until expiration'],
                    ]" />
                </div>
            </x-application.settings-section>
        </form>
    @endcan
</div>
