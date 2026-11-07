<?php

namespace App\Services\Commerce;

use App\Models\Commerce\PaymentMethod;

/** The owner's active payment methods for a currency, as markdown for emails. Nothing is made up. */
class PaymentInstructions
{
    public static function markdown(string $currency): string
    {
        $methods = PaymentMethod::forCurrency($currency)->get();

        if ($methods->isEmpty()) {
            return 'Payment details are not published yet. Reply to this email or contact support and we will send them to you.';
        }

        return $methods->map(function (PaymentMethod $m) {
            $lines = ["**{$m->label}** ({$m->typeLabel()})", ''];

            foreach ($m->details ?? [] as $row) {
                $lines[] = '- '.($row['label'] ?? '').': **'.($row['value'] ?? '').'**';
            }

            if ($m->instructions) {
                $lines[] = '';
                $lines[] = $m->instructions;
            }

            return implode("\n", $lines);
        })->implode("\n\n");
    }
}
