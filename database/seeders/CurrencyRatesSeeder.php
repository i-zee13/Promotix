<?php

namespace Database\Seeders;

use App\Support\CurrencyConverter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CurrencyRatesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('currency_rates')) {
            $this->command?->error('currency_rates table missing — run migrations first.');

            return;
        }

        $now = now();

        foreach ($this->rows() as $row) {
            DB::table('currency_rates')->updateOrInsert(
                ['code' => $row['code']],
                array_merge($row, [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]),
            );
        }

        CurrencyConverter::forgetCache();
        $this->command?->info('Currency rates seeded ('.count($this->rows()).').');
    }

    /**
     * Units per 1 USD (e.g. PKR 278.50).
     *
     * @return list<array{code: string, country_code: string, country_name: string, name: string, symbol: string, units_per_usd: float|int}>
     */
    public function rows(): array
    {
        return [
            ['code' => 'USD', 'country_code' => 'US', 'country_name' => 'United States', 'name' => 'US Dollar', 'symbol' => '$', 'units_per_usd' => 1],
            ['code' => 'PKR', 'country_code' => 'PK', 'country_name' => 'Pakistan', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs ', 'units_per_usd' => 278.50],
            ['code' => 'AED', 'country_code' => 'AE', 'country_name' => 'United Arab Emirates', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'units_per_usd' => 3.6725],
            ['code' => 'SAR', 'country_code' => 'SA', 'country_name' => 'Saudi Arabia', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'units_per_usd' => 3.75],
            ['code' => 'GBP', 'country_code' => 'GB', 'country_name' => 'United Kingdom', 'name' => 'British Pound', 'symbol' => '£', 'units_per_usd' => 0.78],
            ['code' => 'EUR', 'country_code' => 'EU', 'country_name' => 'Eurozone', 'name' => 'Euro', 'symbol' => '€', 'units_per_usd' => 0.92],
            ['code' => 'INR', 'country_code' => 'IN', 'country_name' => 'India', 'name' => 'Indian Rupee', 'symbol' => '₹', 'units_per_usd' => 83.50],
            ['code' => 'AUD', 'country_code' => 'AU', 'country_name' => 'Australia', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'units_per_usd' => 1.52],
            ['code' => 'CAD', 'country_code' => 'CA', 'country_name' => 'Canada', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'units_per_usd' => 1.36],
            ['code' => 'NZD', 'country_code' => 'NZ', 'country_name' => 'New Zealand', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'units_per_usd' => 1.66],
            ['code' => 'JPY', 'country_code' => 'JP', 'country_name' => 'Japan', 'name' => 'Japanese Yen', 'symbol' => '¥', 'units_per_usd' => 149.00],
            ['code' => 'CNY', 'country_code' => 'CN', 'country_name' => 'China', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'units_per_usd' => 7.25],
            ['code' => 'CHF', 'country_code' => 'CH', 'country_name' => 'Switzerland', 'name' => 'Swiss Franc', 'symbol' => 'CHF ', 'units_per_usd' => 0.88],
            ['code' => 'SGD', 'country_code' => 'SG', 'country_name' => 'Singapore', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'units_per_usd' => 1.34],
            ['code' => 'HKD', 'country_code' => 'HK', 'country_name' => 'Hong Kong', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'units_per_usd' => 7.80],
            ['code' => 'ZAR', 'country_code' => 'ZA', 'country_name' => 'South Africa', 'name' => 'South African Rand', 'symbol' => 'R', 'units_per_usd' => 18.50],
            ['code' => 'BRL', 'country_code' => 'BR', 'country_name' => 'Brazil', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'units_per_usd' => 5.05],
            ['code' => 'MXN', 'country_code' => 'MX', 'country_name' => 'Mexico', 'name' => 'Mexican Peso', 'symbol' => 'MX$', 'units_per_usd' => 17.20],
            ['code' => 'SEK', 'country_code' => 'SE', 'country_name' => 'Sweden', 'name' => 'Swedish Krona', 'symbol' => 'kr', 'units_per_usd' => 10.80],
            ['code' => 'NOK', 'country_code' => 'NO', 'country_name' => 'Norway', 'name' => 'Norwegian Krone', 'symbol' => 'kr', 'units_per_usd' => 10.90],
            ['code' => 'DKK', 'country_code' => 'DK', 'country_name' => 'Denmark', 'name' => 'Danish Krone', 'symbol' => 'kr', 'units_per_usd' => 6.90],
            ['code' => 'TRY', 'country_code' => 'TR', 'country_name' => 'Turkey', 'name' => 'Turkish Lira', 'symbol' => '₺', 'units_per_usd' => 34.50],
            ['code' => 'QAR', 'country_code' => 'QA', 'country_name' => 'Qatar', 'name' => 'Qatari Riyal', 'symbol' => '﷼', 'units_per_usd' => 3.64],
            ['code' => 'KWD', 'country_code' => 'KW', 'country_name' => 'Kuwait', 'name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'units_per_usd' => 0.31],
            ['code' => 'BHD', 'country_code' => 'BH', 'country_name' => 'Bahrain', 'name' => 'Bahraini Dinar', 'symbol' => 'BD', 'units_per_usd' => 0.376],
            ['code' => 'OMR', 'country_code' => 'OM', 'country_name' => 'Oman', 'name' => 'Omani Rial', 'symbol' => '﷼', 'units_per_usd' => 0.385],
            ['code' => 'EGP', 'country_code' => 'EG', 'country_name' => 'Egypt', 'name' => 'Egyptian Pound', 'symbol' => 'E£', 'units_per_usd' => 48.50],
            ['code' => 'BDT', 'country_code' => 'BD', 'country_name' => 'Bangladesh', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'units_per_usd' => 110.00],
            ['code' => 'LKR', 'country_code' => 'LK', 'country_name' => 'Sri Lanka', 'name' => 'Sri Lankan Rupee', 'symbol' => 'Rs ', 'units_per_usd' => 295.00],
            ['code' => 'MYR', 'country_code' => 'MY', 'country_name' => 'Malaysia', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'units_per_usd' => 4.45],
            ['code' => 'THB', 'country_code' => 'TH', 'country_name' => 'Thailand', 'name' => 'Thai Baht', 'symbol' => '฿', 'units_per_usd' => 34.50],
            ['code' => 'IDR', 'country_code' => 'ID', 'country_name' => 'Indonesia', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'units_per_usd' => 15800.00],
            ['code' => 'PHP', 'country_code' => 'PH', 'country_name' => 'Philippines', 'name' => 'Philippine Peso', 'symbol' => '₱', 'units_per_usd' => 57.50],
            ['code' => 'VND', 'country_code' => 'VN', 'country_name' => 'Vietnam', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'units_per_usd' => 25400.00],
            ['code' => 'NGN', 'country_code' => 'NG', 'country_name' => 'Nigeria', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'units_per_usd' => 1600.00],
            ['code' => 'KES', 'country_code' => 'KE', 'country_name' => 'Kenya', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'units_per_usd' => 129.00],
        ];
    }
}
