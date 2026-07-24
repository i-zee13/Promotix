<?php

namespace App\Support;

class CardBrand
{
    /**
     * Detect card brand from PAN digits (no spaces/dashes).
     */
    public static function detect(string $digits): string
    {
        $digits = preg_replace('/\D/', '', $digits) ?: '';

        if ($digits === '') {
            return 'Card';
        }

        // American Express
        if (preg_match('/^3[47]/', $digits)) {
            return 'Amex';
        }

        // Discover
        if (preg_match('/^(6011|65|64[4-9])/', $digits)) {
            return 'Discover';
        }

        // UnionPay (mostly 62…)
        if (preg_match('/^62/', $digits)) {
            return 'UnionPay';
        }

        // Mastercard (51–55 and 2221–2720)
        if (preg_match('/^5[1-5]/', $digits)) {
            return 'Mastercard';
        }
        if (strlen($digits) >= 4) {
            $bin4 = (int) substr($digits, 0, 4);
            if ($bin4 >= 2221 && $bin4 <= 2720) {
                return 'Mastercard';
            }
        }

        // Visa
        if (preg_match('/^4/', $digits)) {
            return 'Visa';
        }

        return 'Card';
    }

    /** @return list<string> */
    public static function accepted(): array
    {
        return ['Mastercard', 'Visa', 'Amex', 'UnionPay'];
    }
}
