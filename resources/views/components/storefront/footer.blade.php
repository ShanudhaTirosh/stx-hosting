<footer class="foot">
    <div class="wrap">
        <div class="cols">
            <div>
                <h2>{{ config('storefront.name', 'STX Hosting') }}</h2>
                <p>Enterprise cloud infrastructure you can pay for with a standard bank transfer, priced transparently in Sri Lankan Rupees (LKR) or US Dollars (USD).</p>
                <p style="margin-top:0.75rem;font-size:0.875rem;color:var(--muted)">
                    Headquarters: Colombo, Sri Lanka<br>
                    Parent: {{ config('storefront.parent_brand', 'STX Enterprises') }}
                </p>
                <div style="margin-top:0.75rem;display:flex;gap:0.75rem">
                    <a href="https://discord.gg/shanutechx" target="_blank" rel="noopener" style="font-size:0.875rem">Discord</a>
                    <a href="{{ route('store.contact') }}" style="font-size:0.875rem">Support</a>
                    <a href="{{ route('status.public') }}" target="_blank" rel="noopener" style="font-size:0.875rem">Status</a>
                </div>
            </div>
            <div>
                <h2>Infrastructure</h2>
                <ul>
                    <li><a href="{{ route('store.vps') }}">Cloud VPS (24–72h SLA)</a></li>
                    <li><a href="{{ route('store.templates') }}">Curated App Templates</a></li>
                    <li><a href="{{ route('store.app-hosting') }}">App & Git Hosting</a></li>
                    <li><a href="{{ route('store.email-hosting') }}">Business Email Hosting</a></li>
                    <li><a href="{{ route('store.databases') }}">Managed Databases</a></li>
                    <li><a href="{{ route('store.pricing') }}">Full Pricing Ledger</a></li>
                </ul>
            </div>
            <div>
                <h2>Platform & Guides</h2>
                <ul>
                    <li><a href="{{ route('store.features') }}">Platform Features</a></li>
                    <li><a href="{{ route('store.how-it-works') }}">How Ordering Works</a></li>
                    <li><a href="{{ route('blog.index') }}">Engineering Blog</a></li>
                    <li><a href="{{ route('docs.index') }}">Help & Documentation</a></li>
                    <li><a href="{{ route('store.faqs') }}">Frequently Asked Questions</a></li>
                    <li><a href="{{ route('status.public') }}">90-Day Uptime Status</a></li>
                </ul>
            </div>
            <div>
                <h2>Company & Legal</h2>
                <ul>
                    <li><a href="{{ route('store.about') }}">About STX</a></li>
                    <li><a href="{{ route('store.contact') }}">Contact Sales & Support</a></li>
                    <li><a href="{{ route('store.terms') }}">Terms of Service</a></li>
                    <li><a href="{{ route('store.privacy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('store.refund') }}">Refund & Cancellation</a></li>
                    <li><a href="{{ route('store.cookies') }}">Cookie Policy</a></li>
                </ul>
            </div>
        </div>

        <div style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid var(--line);display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1rem;font-size:0.85rem;color:var(--muted)">
            <p style="margin:0">&copy; {{ date('Y') }} {{ config('storefront.parent_brand', 'STX Enterprises') }} (Pvt) Ltd. All rights reserved. Zero credit card required.</p>
            <p style="margin:0">
                <a href="{{ route('store.sitemap') }}" style="color:inherit">Sitemap (XML)</a> &middot; 
                <a href="{{ route('store.llms') }}" style="color:inherit">LLM Facts (llms.txt)</a>
            </p>
        </div>
    </div>
</footer>
