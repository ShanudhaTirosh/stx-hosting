@php
    $breadcrumbs = [
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'How It Works', 'url' => route('store.how-it-works')],
    ];
@endphp

<x-storefront.layout 
    :title="'How Ordering & Bank Transfer Payment Works'" 
    :description="'Step-by-step walkthrough of ordering cloud servers on STX Hosting: cart checkout, bank transfer in LKR or USD, PayVerify slip inspection, and 24 to 72 hour VPS fulfillment.'"
    :breadcrumbs="$breadcrumbs"
>
    <div class="wrap band">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span style="color:var(--ink);font-weight:600">How It Works</span>
        </nav>

        <div class="pagehead" data-reveal>
            <div>
                <span class="pill emerald" style="margin-bottom:1rem">
                    <span class="pill-dot"></span> Transparent &bull; Human-Verified &bull; Zero FX Surcharges
                </span>
                <h1>How Ordering &amp; Service Activation Works</h1>
                <p class="lead">A transparent, human-reviewed, and chargeback-free commercial workflow designed specifically for Sri Lankan businesses and global developers.</p>
            </div>
            <div>
                <a href="{{ route('store.pricing') }}" class="btn">Browse Server Catalog &rarr;</a>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.75rem;max-width:860px;margin: 3.5rem 0;" data-reveal>
            <!-- Step 1 -->
            <div class="card card-lift" style="display:flex;gap:1.75rem;align-items:flex-start">
                <div style="width:44px;height:44px;border-radius:var(--radius-full);background:var(--ink);color:#fff;display:flex;align-items:center;justify-content:center;font:700 1.2rem/1 var(--display);flex-shrink:0">1</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">Select Your Plan &amp; Specs</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem">Browse our <a href="{{ route('store.pricing') }}">Plans &amp; Pricing Catalog</a>. Choose from high-performance NVMe Cloud VPS instances, 1-click Docker application hosting, managed databases, or custom domain business email.</p>
                    <p style="color:var(--muted);font-size:0.92rem;margin:0">Use the currency switcher at the top of the page to view prices in <strong>Sri Lankan Rupees (LKR)</strong> or <strong>US Dollars (USD)</strong>. What you see is exactly what your invoice will say.</p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="card card-lift" style="display:flex;gap:1.75rem;align-items:flex-start">
                <div style="width:44px;height:44px;border-radius:var(--radius-full);background:var(--ink);color:#fff;display:flex;align-items:center;justify-content:center;font:700 1.2rem/1 var(--display);flex-shrink:0">2</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">Checkout &amp; Generate Official Invoice</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem">Proceeding through the checkout creates an official, numbered invoice (e.g. <code>INV-202611-0042</code>) in your client portal. The invoice displays our verified corporate bank account details (Bank Name, Branch, Account Number, Account Name, and Swift Code).</p>
                    <p style="color:var(--muted);font-size:0.92rem;margin:0">You can also download a tax-ready PDF copy of the invoice immediately.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="card card-lift" style="display:flex;gap:1.75rem;align-items:flex-start">
                <div style="width:44px;height:44px;border-radius:var(--radius-full);background:var(--ink);color:#fff;display:flex;align-items:center;justify-content:center;font:700 1.2rem/1 var(--display);flex-shrink:0">3</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">Pay by Bank Transfer &amp; Upload Receipt</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem">Transfer funds using your preferred bank app (Commercial Bank, Sampath Bank, HNB, BOC, Nations Trust) or cash deposit counter. Interbank transfers via CEFT or SLIPS are fully supported. Use your Invoice Number as the transfer remark.</p>
                    <p style="color:var(--muted);font-size:0.92rem;margin:0">Take a screenshot or save the PDF receipt, and upload it via the invoice page in your portal. File uploads are validated via binary magic bytes, sanitized to pure pixels, and stored on private, encrypted disks.</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="card card-lift" style="display:flex;gap:1.75rem;align-items:flex-start">
                <div style="width:44px;height:44px;border-radius:var(--radius-full);background:var(--ink);color:#fff;display:flex;align-items:center;justify-content:center;font:700 1.2rem/1 var(--display);flex-shrink:0">4</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">PayVerify AI Inspection &amp; Staff Approval</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem">Our PayVerify AI pipeline immediately analyzes the deposit slip using Gemini 2.5 Flash and local perceptual dHash matching to detect duplicates and verify amounts. Overpayments and rounded transfers are fully supported without rejection.</p>
                    <p style="color:var(--muted);font-size:0.92rem;margin:0">Once verified under row-level database locks (eliminating duplicate activations), your invoice is marked as paid and the provisioning job is dispatched.</p>
                </div>
            </div>

            <!-- Step 5 -->
            <div class="card card-lift" style="display:flex;gap:1.75rem;align-items:flex-start">
                <div style="width:44px;height:44px;border-radius:var(--radius-full);background:var(--signal);color:#fff;display:flex;align-items:center;justify-content:center;font:700 1.2rem/1 var(--display);flex-shrink:0">5</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">Service Goes Live &amp; Credentials Delivered</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem"><strong>Git Apps, Templates &amp; Databases</strong>: Container resources are unlocked immediately, and you can build and deploy your repository or launch your database in seconds.</p>
                    <p style="color:var(--muted);line-height:1.6;margin:0"><strong>Cloud VPS Servers</strong>: Dedicated virtual machines are hand-allocated, kernel-hardened, and provisioned by our systems team within <strong>24 to 72 hours</strong>. When ready, you receive an email alert, and your encrypted root SSH credentials appear in your portal under password re-authentication.</p>
                </div>
            </div>
        </div>

        <div style="margin-top:3.5rem" data-reveal>
            <a href="{{ route('store.pricing') }}" class="btn">Explore Catalog &amp; Place Your Order &rarr;</a>
        </div>
    </div>
</x-storefront.layout>
