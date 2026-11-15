<?php

namespace App\Livewire\Portal;

use App\Exceptions\PlatformRestrictionException;
use App\Models\Application;
use App\Models\CustomDomain;
use App\Services\Commerce\CustomDomainService;
use App\Services\Commerce\PlatformDomainService;
use Livewire\Component;

/** Clients prove they own a domain here before they can put it on an app. */
class Domains extends Component
{
    public string $newDomain = '';

    private function team()
    {
        $team = currentTeam();
        abort_unless($team, 403, 'No active team.');

        return $team;
    }

    public function add(CustomDomainService $domains): void
    {
        $team = $this->team();
        abort_unless(auth()->user()->isAdminOfTeam($team->id), 403, 'You do not have permission to add domains to this team.');

        $this->validate(['newDomain' => 'required|string|max:253']);

        try {
            $domain = $domains->request($team, $this->newDomain);
            \App\Jobs\Commerce\PollCustomDomainDnsJob::dispatch($domain);
        } catch (PlatformRestrictionException $e) {
            $this->addError('newDomain', $e->getMessage());

            return;
        }

        $this->newDomain = '';
        $this->dispatch('success', 'Domain added. Point your DNS records as shown below; we will check automatically in the background.');
    }

    public function verify(string $uuid, CustomDomainService $domains): void
    {
        $team = $this->team();
        $domain = CustomDomain::where('uuid', $uuid)->where('team_id', $team->id)->firstOrFail();
        abort_unless(auth()->user()->isAdminOfTeam($team->id), 403, 'You do not have permission to verify domains for this team.');

        if ($domains->verify($domain)) {
            $this->dispatch('success', "{$domain->domain} is verified. You can now use it on your apps.");
        } else {
            $this->dispatch('error', 'We could not find the DNS record yet. DNS changes can take a while; try again in a few minutes.');
        }
    }

    public function remove(string $uuid): void
    {
        $team = $this->team();
        $domain = CustomDomain::where('uuid', $uuid)->where('team_id', $team->id)->firstOrFail();
        abort_unless(auth()->user()->isAdminOfTeam($team->id), 403, 'You do not have permission to remove domains from this team.');

        $inUse = Application::withoutGlobalScopes()
            ->whereHas('environment.project', fn ($q) => $q->where('team_id', $team->id))
            ->where('fqdn', 'like', '%'.$domain->domain.'%')
            ->get(['fqdn'])
            ->contains(fn ($app) => collect(PlatformDomainService::hostsOf($app->fqdn))
                ->contains(fn ($h) => $h === $domain->domain || str_ends_with($h, '.'.$domain->domain)));

        if ($inUse) {
            $this->dispatch('error', 'An app still uses this domain. Remove it from the app first.');

            return;
        }

        $domain->delete();
        $this->dispatch('success', 'Domain removed.');
    }

    public function render()
    {
        $domains = CustomDomain::where('team_id', $this->team()->id)->orderBy('domain')->get();
        $service = app(CustomDomainService::class);

        return view('livewire.portal.domains', [
            'domains' => $domains,
            'ingressIp' => config('hosting.ingress_ip'),
            'appsDomain' => PlatformDomainService::appsDomain(),
            'routing' => $domains->filter->isVerified()->mapWithKeys(fn ($d) => [$d->id => $service->pointsAtIngress($d)]),
        ]);
    }
}
