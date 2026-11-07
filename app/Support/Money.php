<?php

namespace App\Support;

class Money
{
    /** 150000 + LKR -> "LKR 1,500", 1999 + USD -> "$19.99". Whole rupees drop the decimals. */
    public static function format(int $cents, string $currency = 'USD'): string
    {
        if (strtoupper($currency) === 'LKR') {
            $whole = $cents % 100 === 0;

            return 'LKR '.number_format($cents / 100, $whole ? 0 : 2);
        }

        return '$'.number_format($cents / 100, 2);
    }
}
