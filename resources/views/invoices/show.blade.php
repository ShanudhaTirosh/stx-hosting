<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice {{ $invoice->invoice_number }} — STX Enterprises</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#07060d;
    --paper:#ffffff;
    --line:#111318;
    --teal:#20c6e0;
    --teal-deep:#146a8a;
    --purple:#7146eb;
    --purple-deep:#4a32a8;
    --blue:#4574e1;
    --accent-grad:linear-gradient(115deg, var(--purple) 0%, var(--blue) 52%, var(--teal) 100%);
    --muted:#5b6472;
    --faint:#eef1f4;
    --card:#f7f8fa;
    --danger:#c0392b;
    --radius:2px;
  }

  *{box-sizing:border-box;}
  html,body{margin:0;padding:0;}
  body{
    background:#1b1e24;
    font-family:'Space Grotesk', sans-serif;
    color:var(--ink);
    padding:40px 16px 80px;
  }

  /* Screen toolbar */
  .chrome{
    max-width:840px;
    margin:0 auto 24px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
  }
  .backlink{
    color:#9aa3af;
    text-decoration:none;
    font-size:13px;
    font-weight:600;
    transition:color .15s;
  }
  .backlink:hover{ color:var(--teal); }
  .printbtn{
    appearance:none;
    border:1px solid var(--teal);
    background:transparent;
    color:var(--teal);
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:12.5px;
    letter-spacing:.06em;
    text-transform:uppercase;
    padding:10px 20px;
    border-radius:999px;
    cursor:pointer;
    transition:all .15s ease;
  }
  .printbtn:hover{ background:var(--teal); color:#06110f; }

  .stage{ max-width:840px; margin:0 auto; }

  .page{
    background:var(--paper);
    width:100%;
    min-height:1050px;
    padding:64px 60px 56px;
    box-shadow:0 30px 60px -20px rgba(0,0,0,.55), 0 0 0 1px #2a2e37;
    position:relative;
    overflow:hidden;
  }

  /* Brand logo */
  .brand-logo {
    display: inline-block;
  }
  .brand-logo svg {
    height: 48px;
    width: auto;
    display: block;
  }
  .brand-logo .sub {
    margin-top: 6px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    font-size: 10px;
    letter-spacing: 0.38em;
    color: var(--ink);
    border-top: 2.5px solid var(--ink);
    padding-top: 5px;
    display: block;
  }

  /* Invoice layout */
  .inv-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:36px;
  }
  .inv-tag{
    text-align:right;
  }
  .inv-tag .label{
    font-family:'Space Grotesk',sans-serif;
    font-weight:700;
    font-size:28px;
    letter-spacing:.06em;
    color:var(--ink);
  }
  .inv-tag .num{
    font-family:'JetBrains Mono', monospace;
    font-size:13px;
    color:var(--purple-deep);
    margin-top:4px;
    font-weight:700;
  }
  .inv-tealbar{
    height:5px;
    background:var(--ink);
    position:relative;
    margin-bottom:34px;
  }
  .inv-tealbar::after{
    content:"";
    position:absolute; left:0; top:0; height:100%;
    width:34%;
    background:var(--accent-grad);
  }
  .inv-meta{
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:20px;
    margin-bottom:36px;
  }
  .inv-meta .block .h{
    font-family:'JetBrains Mono', monospace;
    font-size:10px;
    letter-spacing:.14em;
    text-transform:uppercase;
    color:var(--teal-deep);
    font-weight:700;
    margin-bottom:8px;
  }
  .inv-meta .block .l{
    font-size:13px;
    line-height:1.7;
    color:#20242b;
  }
  .inv-meta .block .l strong{ display:block; font-size:14px; }

  table.items{
    width:100%;
    border-collapse:collapse;
    margin-bottom:8px;
  }
  table.items thead th{
    text-align:left;
    font-family:'JetBrains Mono', monospace;
    font-size:10px;
    letter-spacing:.1em;
    text-transform:uppercase;
    color:#fff;
    background:var(--ink);
    padding:11px 14px;
    font-weight:700;
  }
  table.items thead th:last-child, table.items tbody td:last-child{ text-align:right; }
  table.items thead th.qty, table.items thead th.rate,
  table.items tbody td.qty, table.items tbody td.rate{ text-align:center; }
  table.items tbody td{
    padding:13px 14px;
    font-size:13px;
    border-bottom:1px solid var(--faint);
    color:#20242b;
  }
  table.items tbody tr:nth-child(even){ background:var(--card); }
  table.items tbody td.desc strong{ display:block; }
  table.items tbody td.desc span{ color:var(--muted); font-size:11.5px; }

  .inv-totals{
    display:flex;
    justify-content:flex-end;
    margin-top:22px;
  }
  .inv-totals .box{ width:280px; }
  .inv-totals .row{
    display:flex;
    justify-content:space-between;
    font-size:13px;
    padding:8px 0;
    color:var(--muted);
  }
  .inv-totals .row.total{
    border-top:2.5px solid var(--ink);
    margin-top:6px;
    padding-top:14px;
    color:var(--ink);
    font-weight:700;
    font-size:17px;
  }
  .inv-totals .row.total .amt{ font-family:'JetBrains Mono', monospace; color:var(--teal-deep); }

  .inv-bottom{
    display:grid;
    grid-template-columns:1.2fr 1fr;
    gap:36px;
    margin-top:48px;
    padding-top:24px;
    border-top:1px solid #d8dce1;
  }
  .inv-bottom .h{
    font-family:'JetBrains Mono', monospace;
    font-size:10px;
    letter-spacing:.14em;
    text-transform:uppercase;
    color:var(--teal-deep);
    font-weight:700;
    margin-bottom:10px;
  }
  .inv-bottom .l{ font-size:12.5px; line-height:1.85; color:#3a4048; }
  .inv-status{
    display:inline-block;
    margin-top:2px;
    font-family:'JetBrains Mono', monospace;
    font-size:11px;
    letter-spacing:.12em;
    font-weight:700;
    text-transform:uppercase;
    padding:7px 16px;
    border-radius:999px;
  }
  .inv-status.awaiting {
    border:1.5px solid var(--purple-deep);
    color:var(--purple-deep);
    background:#faf5ff;
  }
  .inv-status.paid {
    border:1.5px solid #27ae60;
    color:#27ae60;
    background:#f0fdf4;
  }
  .inv-status.rejected {
    border:1.5px solid var(--danger);
    color:var(--danger);
    background:#fef2f2;
  }

  @media print{
    body{ background:#fff; padding:0; margin:0; }
    .chrome{ display:none !important; }
    .stage{ max-width:none; }
    .page{ box-shadow:none !important; min-height:0; padding:15mm 15mm; }
    @page{ margin:0; size:A4; }
  }
</style>
</head>
<body>

  <div class="chrome">
    <a href="{{ route('portal.billing') }}" class="backlink">&larr; Back to Billing Portal</a>
    <button class="printbtn" onclick="window.print()">Print / Save PDF</button>
  </div>

  <div class="stage">
    <section class="page">
      <div class="inv-top">
        <div class="brand-logo">
          <svg viewBox="0 0 10000 10000" xmlns="http://www.w3.org/2000/svg">
            <g fill="black">
              <polygon points="8500,6616.67 7576.67,6616.67 6763.33,5756.67 5910,6616.67 4290,6616.67 4660,4530 5390,4530 5030,6580 5030,6590 6333.33,5293.33 5603.33,4530 6526.67,4526.67 "/>
              <polygon points="8166.67,3383.33 9160,3383.33 7320,5156.67 6873.33,4670 "/>
              <polygon points="2140,4963.33 2006.67,4826.67 1963.33,4750 1943.33,4676.67 1940,4610 1946.67,4563.33 1963.33,4503.33 1996.67,4446.67 2040,4403.33 2086.67,4360 2136.67,4326.67 2193.33,4303.33 2243.33,4290 2286.67,4286.67 6346.67,4286.67 7043.33,3620 2290,3616.67 2183.33,3633.33 2096.67,3656.67 2003.33,3683.33 1900,3730 1813.33,3776.67 1723.33,3840 1666.67,3876.67 1590,3943.33 1520,4023.33 1470,4083.33 1413.33,4160 1353.33,4273.33 1313.33,4370 1280,4483.33 1260,4583.33 1256.67,4690 1263.33,4793.33 1283.33,4896.67 1320,5013.33 1350,5070 1393.33,5143.33 1450,5216.67 1496.67,5263.33 1556.67,5310 1640,5363.33 1716.67,5406.67 1790,5433.33 1850,5450 1910,5466.67 1963.33,5473.33 2003.33,5476.67 2083.33,5480 2640,5480 "/>
              <polygon points="2213.33,4776.67 2873.33,5476.67 3373.33,5476.67 3396.67,5480 3426.67,5493.33 3456.67,5510 3476.67,5526.67 3496.67,5543.33 3516.67,5570 3533.33,5600 3540,5620 3546.67,5643.33 3546.67,5670 3546.67,5700 3540,5730 3526.67,5770 3513.33,5800 3493.33,5830 3473.33,5853.33 3440,5883.33 3406.67,5906.67 3363.33,5926.67 3330,5940 1526.67,5943.33 840,6613.33 3220,6610 3260,6610 3313.33,6606.67 3366.67,6596.67 3420,6580 3493.33,6560 3550,6536.67 3606.67,6513.33 3666.67,6476.67 3743.33,6430 3816.67,6383.33 3880,6326.67 3933.33,6276.67 3990,6213.33 4056.67,6130 4116.67,6030 4153.33,5960 4186.67,5873.33 4216.67,5780 4226.67,5716.67 4233.33,5663.33 4230,5496.67 4210,5390 4176.67,5286.67 4143.33,5206.67 4090,5116.67 4040,5056.67 3980,4993.33 3920,4943.33 3840,4890 3756.67,4846.67 3653.33,4810 3573.33,4793.33 3523.33,4786.67 3443.33,4783.33 "/>
            </g>
          </svg>
          <div class="sub">DIGITAL EMPIRE STUDIO</div>
        </div>
        <div class="inv-tag">
          <div class="label">INVOICE</div>
          <div class="num">No. {{ $invoice->invoice_number }}</div>
        </div>
      </div>
      <div class="inv-tealbar"></div>

      <div class="inv-meta">
        <div class="block">
          <div class="h">Billed to</div>
          <div class="l">
            <strong>{{ $invoice->team->name ?? ($invoice->user->name ?? 'Valued Customer') }}</strong>
            Attn: {{ $invoice->user->name ?? 'Account Contact' }}<br>
            {{ $invoice->user->email ?? 'customer@domain.com' }}
          </div>
        </div>
        <div class="block">
          <div class="h">Invoice details</div>
          <div class="l">
            Issue date: <strong>{{ $invoice->created_at->format('d M Y') }}</strong><br>
            Due date: <strong>{{ $invoice->due_at ? $invoice->due_at->format('d M Y') : $invoice->created_at->addDays(2)->format('d M Y') }}</strong><br>
            Order ID: <strong>#{{ $invoice->order_id }}</strong>
          </div>
        </div>
        <div class="block">
          <div class="h">Pay to</div>
          <div class="l">
            <strong>STX Enterprises</strong> (Shanudha Tirosh)<br>
            Bank: Commercial Bank / Sampath Bank<br>
            Acc No: 8012 3456 7890<br>
            eZCash / Dialog: +94 7X XXX XXXX
          </div>
        </div>
      </div>

      <table class="items">
        <thead>
          <tr>
            <th style="width:52%;">Description</th>
            <th class="qty" style="width:12%;">Qty</th>
            <th class="rate" style="width:18%;">Rate</th>
            <th style="width:18%;">Amount</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($invoice->items as $item)
            <tr>
              <td class="desc">
                <strong>{{ $item->description }}</strong>
                <span>Cloud infrastructure deployment with dedicated server resources</span>
              </td>
              <td class="qty">{{ $item->quantity }}</td>
              <td class="rate">
                {{ $invoice->currency === 'LKR' ? 'LKR ' . number_format($item->unit_price_cents / 100, 2) : '$' . number_format($item->unit_price_cents / 100, 2) }}
              </td>
              <td>
                {{ $invoice->currency === 'LKR' ? 'LKR ' . number_format($item->total_price_cents / 100, 2) : '$' . number_format($item->total_price_cents / 100, 2) }}
              </td>
            </tr>
          @empty
            <tr>
              <td class="desc">
                <strong>Cloud Hosting Infrastructure Allocation</strong>
                <span>Dedicated VPS Instance</span>
              </td>
              <td class="qty">1</td>
              <td class="rate">
                {{ $invoice->currency === 'LKR' ? 'LKR ' . number_format($invoice->total_cents / 100, 2) : '$' . number_format($invoice->total_cents / 100, 2) }}
              </td>
              <td>
                {{ $invoice->currency === 'LKR' ? 'LKR ' . number_format($invoice->total_cents / 100, 2) : '$' . number_format($invoice->total_cents / 100, 2) }}
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>

      <div class="inv-totals">
        <div class="box">
          <div class="row">
            <span>Subtotal</span>
            <span>{{ $invoice->currency === 'LKR' ? 'LKR ' . number_format($invoice->subtotal_cents / 100, 2) : '$' . number_format($invoice->subtotal_cents / 100, 2) }}</span>
          </div>
          <div class="row">
            <span>Discount</span>
            <span>&mdash; {{ $invoice->currency === 'LKR' ? 'LKR ' . number_format($invoice->discount_cents / 100, 2) : '$' . number_format($invoice->discount_cents / 100, 2) }}</span>
          </div>
          <div class="row">
            <span>Tax (0%)</span>
            <span>{{ $invoice->currency === 'LKR' ? 'LKR 0.00' : '$0.00' }}</span>
          </div>
          <div class="row total">
            <span>Total Due</span>
            <span class="amt">{{ $invoice->currency === 'LKR' ? 'LKR ' . number_format($invoice->total_cents / 100, 2) : '$' . number_format($invoice->total_cents / 100, 2) }}</span>
          </div>
        </div>
      </div>

      <div class="inv-bottom">
        <div>
          <div class="h">Notes &amp; Policies</div>
          <div class="l">
            Thank you for working with STX Enterprises. Please make payments to the designated account and upload your deposit slip in your portal. For technical support or invoice inquiries, email hello@stxenterprises.com.
          </div>
        </div>
        <div>
          <div class="h">Invoice Status</div>
          @if ($invoice->status === 'paid')
            <div class="inv-status paid">&#10003; Paid &amp; Verified</div>
          @elseif ($invoice->status === 'rejected')
            <div class="inv-status rejected">&#10005; Payment Rejected</div>
          @else
            <div class="inv-status awaiting">Awaiting Payment</div>
          @endif
        </div>
      </div>
    </section>
  </div>

</body>
</html>

