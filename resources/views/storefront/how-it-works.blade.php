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
    <div class="wrap" style="padding-top:2.5rem;padding-bottom:4rem">
        <nav aria-label="Breadcrumb" style="margin-bottom:1.5rem;font-size:0.875rem;color:var(--muted)">
            <a href="{{ url('/') }}">Home</a> &rarr; <span>How It Works</span>
        </nav>

        <div class="pagehead" style="margin-bottom:3rem">
            <h1>How Ordering &amp; Service Activation Works</h1>
            <p style="font-size:1.15rem;color:var(--muted);max-width:75ch">A transparent, human-reviewed, and chargeback-free commercial workflow designed for Sri Lanka and global developers.</p>
        </div>

        <div style="display:flex;flex-direction:column;gap:2.5rem;max-width:800px">
            <!-- Step 1 -->
            <div style="display:flex;gap:1.5rem;background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--signal);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;flex-shrink:0">1</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">Select Your Plan &amp; Specs</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem">Browse our <a href="{{ route('store.pricing') }}">Plans &amp; Pricing Catalog</a>. Choose from high-performance NVMe Cloud VPS instances, 1-click Docker application hosting, managed databases, or custom domain business email.</p>
                    <p style="color:var(--muted);font-size:0.95rem;margin:0">Use the currency switcher at the top of the page to view prices in <strong>Sri Lankan Rupees (LKR)</strong> or <strong>US Dollars (USD)</strong>. What you see is exactly what your invoice will say.</p>
                </div>
            </div>

            <!-- Step 2 -->
            <div style="display:flex;gap:1.5rem;background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--signal);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;flex-shrink:0">2</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">Checkout &amp; Generate Official Invoice</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem">Proceeding through the checkout creates an official, numbered invoice (e.g. <code>INV-202611-0042</code>) in your client portal. The invoice displays our verified corporate bank account details (Bank Name, Branch, Account Number, Account Name, and Swift Code).</p>
                    <p style="color:var(--muted);font-size:0.95rem;margin:0">You can also download a tax-ready PDF copy of the invoice immediately.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div style="display:flex;gap:1.5rem;background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--signal);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;flex-shrink:0">3</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">Pay by Bank Transfer &amp; Upload Receipt</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem">Transfer the exact invoice balance using your preferred bank app (Commercial Bank, Sampath Bank, HNB, BOC, etc.) or cash deposit counter. Use your Invoice Number as the transfer remark.</p>
                    <p style="color:var(--muted);font-size:0.95rem;margin:0">Take a screenshot or save the PDF receipt, and upload it via the invoice page in your portal. File uploads are validated via binary magic bytes and stored on private, encrypted disks.</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div style="display:flex;gap:1.5rem;background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--signal);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;flex-shrink:0">4</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">PayVerify AI Inspection &amp; Staff Approval</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem">Our PayVerify AI pipeline immediately analyzes the deposit slip to detect duplicate hashes, verify amounts, and match bank ledger transactions. A finance staff member reviews the slip side-by-side with your invoice.</p>
                    <p style="color:var(--muted);font-size:0.95rem;margin:0">Once approved under row-level database locking (preventing double activations), your invoice is marked as paid and the provisioning job is dispatched.</p>
                </div>
            </div>

            <!-- Step 5 -->
            <div style="display:flex;gap:1.5rem;background:var(--card);padding:2rem;border-radius:var(--radius);border:1px solid var(--line)">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--signal);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;flex-shrink:0">5</div>
                <div>
                    <h2 style="font-size:1.35rem;margin-bottom:0.5rem">Service Goes Live &amp; Credentials Delivered</h2>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0.75rem"><strong>Git Apps, Templates &amp; Databases</strong>: Container resources are unlocked immediately, and you can build and deploy your repository or launch your database in seconds.</p>
                    <p style="color:var(--muted);line-height:1.6;margin-bottom:0"><strong>Cloud VPS Servers</strong>: Dedicated virtual machines are hand-allocated, kernel-hardened, and provisioned by our systems team within <strong>24 to 72 hours</strong>. When ready, you receive an email alert, and your encrypted root SSH credentials appear in your portal under password re-authentication.</p>
                </div>
            </div>
        </div>

        <div style="margin-top:3.5rem">
            <a href="{{ route('store.pricing') }}" class="btn">Explore Catalog &amp; Place Your Order &rarr;</a>
        </div>
    </div>
</x-storefront.layout>
