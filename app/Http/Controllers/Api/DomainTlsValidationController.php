<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CustomDomain;
use App\Models\MailDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DomainTlsValidationController extends Controller
{
    /**
     * Endpoint invoked by Caddy reverse proxy via on_demand_tls "ask".
     * Checks whether TLS certificate generation is permitted for the given domain.
     * Returns HTTP 200 if authorized, or HTTP 403 / 400 otherwise.
     */
    public function validateAsk(Request $request): Response
    {
        $domain = trim(strtolower((string) $request->query('domain', '')));

        if (empty($domain)) {
            return response('Bad Request: domain query parameter is required', 400);
        }

        // Strip port if present
        if (str_contains($domain, ':')) {
            $domain = explode(':', $domain)[0];
        }

        // Sanitize: ensure valid domain characters
        if (! preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-_]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $domain)) {
            return response('Bad Request: invalid domain syntax', 400);
        }

        // 1. Check Platform Root and Subdomains
        $platformDomain = \App\Services\Commerce\PlatformDomainService::basePlatformDomain() ?: strtolower((string) env('DOMAIN', 'ourdomain.com'));
        $appsDomain = \App\Services\Commerce\PlatformDomainService::appsDomain();
        $appUrlHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $webmailHost = parse_url((string) env('WEBMAIL_URL', 'https://webmail.'.$platformDomain), PHP_URL_HOST);
        $mailHost = strtolower((string) config('mail.mail_hostname', env('MAIL_HOSTNAME', 'mail.'.$platformDomain)));

        $allowedHosts = array_filter([
            $platformDomain,
            $appsDomain,
            $appUrlHost,
            $webmailHost,
            $mailHost,
            'api.'.$platformDomain,
            'studio.'.$platformDomain,
            'app.'.$platformDomain,
            'status.'.$platformDomain,
            'webmail.'.$platformDomain,
            'mail.'.$platformDomain,
            'localhost',
        ]);

        if (
            in_array($domain, $allowedHosts, true)
            || ($platformDomain && str_ends_with($domain, '.'.$platformDomain))
            || ($appsDomain && str_ends_with($domain, '.'.$appsDomain))
        ) {
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        // 2. Check CustomDomain model (customer custom domains)
        $customDomainExists = CustomDomain::where('domain', $domain)
            ->where(function ($query) {
                $query->whereIn('status', [
                    CustomDomain::STATUS_ACTIVE,
                    CustomDomain::STATUS_DNS_VERIFIED,
                    CustomDomain::STATUS_ISSUING_SSL,
                ])->orWhereNotNull('verified_at');
            })
            ->exists();

        if ($customDomainExists) {
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        // 3. Check MailDomain model (verified mail custom domains)
        $mailDomainExists = MailDomain::where('domain', $domain)
            ->where('is_verified', true)
            ->exists();

        if ($mailDomainExists) {
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        // 4. Check Applications with matching FQDN
        $appExists = Application::where('fqdn', 'LIKE', '%'.$domain.'%')->exists();
        if ($appExists) {
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        // 5. Check ServiceInstance (commercial portal applications)
        $serviceInstanceExists = \App\Models\Commerce\ServiceInstance::where('hostname', $domain)
            ->orWhereJsonContains('network_config->custom_domains', $domain)
            ->exists();

        if ($serviceInstanceExists) {
            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden: Domain not authorized for TLS issuance', 403)
            ->header('Content-Type', 'text/plain');
    }
}

