@props([
    'eyebrow' => 'Hosting Service Notification',
    'title' => 'STX Enterprises Update',
    'ctaText' => null,
    'ctaUrl' => null,
])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #07060d;
            --paper: #ffffff;
            --teal: #20c6e0;
            --teal-deep: #146a8a;
            --purple: #7146eb;
            --blue: #4574e1;
            --accent-grad: linear-gradient(115deg, #7146eb 0%, #4574e1 52%, #20c6e0 100%);
            --muted: #5b6472;
            --faint: #eef1f4;
            --card: #f7f8fa;
        }

        body {
            margin: 0;
            padding: 20px 10px;
            background-color: #eef1f4;
            font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #20242b;
            -webkit-font-smoothing: antialiased;
        }

        .email-frame {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .email-band {
            background-color: #07060d;
            padding: 32px 36px 26px;
            position: relative;
            overflow: hidden;
        }

        .brand-logo {
            display: inline-block;
        }

        .brand-logo svg {
            height: 38px;
            width: auto;
            display: block;
        }

        .brand-logo .sub {
            margin-top: 6px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 9px;
            letter-spacing: 0.38em;
            color: #ffffff;
            border-top: 2px solid #20c6e0;
            padding-top: 4px;
            display: block;
        }

        .email-slash {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 120px;
            background: linear-gradient(135deg, transparent 46%, #20c6e0 46.6%, #7146eb 49%, transparent 49.6%);
            opacity: 0.9;
        }

        .email-body {
            padding: 36px 36px 12px;
        }

        .email-eyebrow {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10.5px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #146a8a;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .email-body h1 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 16px;
            color: #07060d;
            line-height: 1.3;
        }

        .email-content {
            font-size: 14px;
            line-height: 1.8;
            color: #3a4048;
        }

        .email-content p {
            margin: 0 0 16px;
        }

        .email-cta-box {
            margin: 20px 0 28px;
        }

        .email-cta {
            display: inline-block;
            background: linear-gradient(115deg, #7146eb 0%, #4574e1 52%, #20c6e0 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            letter-spacing: 0.03em;
            padding: 13px 26px;
            border-radius: 4px;
        }

        .email-divider {
            height: 1px;
            background-color: #eef1f4;
            margin: 16px 36px 0;
        }

        .email-sign {
            padding: 24px 36px 10px;
            font-size: 13px;
            color: #3a4048;
            line-height: 1.8;
        }

        .email-sign strong {
            display: block;
            color: #07060d;
            font-size: 14px;
        }

        .email-footer {
            background-color: #f7f8fa;
            padding: 24px 36px 28px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            color: #8b929c;
            line-height: 1.9;
            letter-spacing: 0.02em;
            border-top: 1px solid #eef1f4;
        }

        .email-footer a {
            color: #146a8a;
            text-decoration: none;
        }

        .email-social {
            margin-top: 8px;
        }

        .email-social span {
            margin-right: 14px;
        }
    </style>
</head>
<body>
    <div class="email-frame">
        <div class="email-band">
            <div class="email-slash"></div>
            <div class="brand-logo">
                <svg viewBox="0 0 10000 10000" xmlns="http://www.w3.org/2000/svg">
                    <g fill="#ffffff">
                        <polygon points="8500,6616.67 7576.67,6616.67 6763.33,5756.67 5910,6616.67 4290,6616.67 4660,4530 5390,4530 5030,6580 5030,6590 6333.33,5293.33 5603.33,4530 6526.67,4526.67 "/>
                        <polygon points="8166.67,3383.33 9160,3383.33 7320,5156.67 6873.33,4670 "/>
                        <polygon points="2140,4963.33 2006.67,4826.67 1963.33,4750 1943.33,4676.67 1940,4610 1946.67,4563.33 1963.33,4503.33 1996.67,4446.67 2040,4403.33 2086.67,4360 2136.67,4326.67 2193.33,4303.33 2243.33,4290 2286.67,4286.67 6346.67,4286.67 7043.33,3620 2290,3616.67 2183.33,3633.33 2096.67,3656.67 2003.33,3683.33 1900,3730 1813.33,3776.67 1723.33,3840 1666.67,3876.67 1590,3943.33 1520,4023.33 1470,4083.33 1413.33,4160 1353.33,4273.33 1313.33,4370 1280,4483.33 1260,4583.33 1256.67,4690 1263.33,4793.33 1283.33,4896.67 1320,5013.33 1350,5070 1393.33,5143.33 1450,5216.67 1496.67,5263.33 1556.67,5310 1640,5363.33 1716.67,5406.67 1790,5433.33 1850,5450 1910,5466.67 1963.33,5473.33 2003.33,5476.67 2083.33,5480 2640,5480 "/>
                        <polygon points="2213.33,4776.67 2873.33,5476.67 3373.33,5476.67 3396.67,5480 3426.67,5493.33 3456.67,5510 3476.67,5526.67 3496.67,5543.33 3516.67,5570 3533.33,5600 3540,5620 3546.67,5643.33 3546.67,5670 3546.67,5700 3540,5730 3526.67,5770 3513.33,5800 3493.33,5830 3473.33,5853.33 3440,5883.33 3406.67,5906.67 3363.33,5926.67 3330,5940 1526.67,5943.33 840,6613.33 3220,6610 3260,6610 3313.33,6606.67 3366.67,6596.67 3420,6580 3493.33,6560 3550,6536.67 3606.67,6513.33 3666.67,6476.67 3743.33,6430 3816.67,6383.33 3880,6326.67 3933.33,6276.67 3990,6213.33 4056.67,6130 4116.67,6030 4153.33,5960 4186.67,5873.33 4216.67,5780 4226.67,5716.67 4233.33,5663.33 4230,5496.67 4210,5390 4176.67,5286.67 4143.33,5206.67 4090,5116.67 4040,5056.67 3980,4993.33 3920,4943.33 3840,4890 3756.67,4846.67 3653.33,4810 3573.33,4793.33 3523.33,4786.67 3443.33,4783.33 "/>
                    </g>
                </svg>
                <div class="sub">DIGITAL EMPIRE STUDIO</div>
            </div>
        </div>

        <div class="email-body">
            <div class="email-eyebrow">{{ $eyebrow }}</div>
            <h1>{{ $title }}</h1>
            <div class="email-content">
                {{ $slot }}
            </div>

            @if ($ctaUrl && $ctaText)
                <div class="email-cta-box">
                    <a href="{{ $ctaUrl }}" class="email-cta">{{ $ctaText }} &rarr;</a>
                </div>
            @endif
        </div>

        <div class="email-divider"></div>

        <div class="email-sign">
            <strong>Shanudha Tirosh</strong>
            Founder, STX Enterprises / Digital Empire Studio<br>
            hello@stxenterprises.com &nbsp;&middot;&nbsp; www.stxenterprises.com
        </div>

        <div class="email-footer">
            STX Enterprises &mdash; Digital Empire Studio, Negombo, Western Province, Sri Lanka<br>
            You are receiving this transaction notice regarding your cloud infrastructure services.<br>
            <div class="email-social">
                <span><a href="https://shanutechx.com">Website</a></span>
                <span><a href="https://github.com/ShanudhaTirosh">GitHub</a></span>
                <span><a href="https://discord.gg">Discord</a></span>
            </div>
        </div>
    </div>
</body>
</html>
