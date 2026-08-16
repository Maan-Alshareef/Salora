<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ExchangeRateService
{
    public const SETTING_KEY = 'exchange_rate_usd_to_syp';
    public const DEFAULT_RATE = 14000.0;

    private ?float $cachedRate = null;

    public function rate(): float
    {
        if ($this->cachedRate !== null) {
            return $this->cachedRate;
        }

        $stored = Setting::query()->where('key', self::SETTING_KEY)->value('value');
        $fallback = (float) env('USD_TO_SYP', self::DEFAULT_RATE);
        $rate = is_numeric($stored) ? (float) $stored : $fallback;

        if (!is_finite($rate) || $rate <= 0) {
            $rate = self::DEFAULT_RATE;
        }

        return $this->cachedRate = $rate;
    }

    public function setRate(float $rate): Setting
    {
        $this->assertValidRate($rate);

        $setting = Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $this->formatRate($rate), 'type' => 'number'],
        );

        $this->cachedRate = $rate;

        return $setting;
    }

    public function toUsd(float|int|string|null $syp, ?float $rate = null): float
    {
        $resolvedRate = $this->normaliseRate($rate ?? $this->rate());
        return round(((float) ($syp ?? 0)) / $resolvedRate, 2);
    }

    public function toSyp(float|int|string|null $usd, ?float $rate = null): float
    {
        $resolvedRate = $this->normaliseRate($rate ?? $this->rate());
        return round(((float) ($usd ?? 0)) * $resolvedRate, 2);
    }

    public function resolveSnapshotRate(
        float|int|string|null $storedRate = null,
        float|int|string|null $syp = null,
        float|int|string|null $usd = null,
    ): float {
        $stored = (float) ($storedRate ?? 0);
        if (is_finite($stored) && $stored > 0) {
            return $stored;
        }

        $sypValue = (float) ($syp ?? 0);
        $usdValue = (float) ($usd ?? 0);
        if ($sypValue > 0 && $usdValue > 0) {
            $inferred = $sypValue / $usdValue;
            if (is_finite($inferred) && $inferred > 0) {
                return $inferred;
            }
        }

        return $this->rate();
    }

    /**
     * Recalculate derived USD mirrors for records that are still financially open.
     * Paid/refunded/cancelled invoices are deliberately not rewritten because they
     * are accounting snapshots and may already have an issued receipt.
     */
    public function syncOpenFinancialRecords(float $rate): array
    {
        $this->assertValidRate($rate);

        $counts = [
            'invoices' => 0,
            'bookings' => 0,
            'booking_services' => 0,
            'provider_requests' => 0,
            'transactions' => 0,
            'services' => 0,
            'venues' => 0,
            'venue_services' => 0,
            'platform_commissions' => 0,
        ];

        DB::transaction(function () use ($rate, &$counts): void {
            $this->syncCatalogPrices($rate, $counts);
            $this->syncOpenBookings($rate, $counts);
            $this->syncOpenBookingServices($rate, $counts);
            $this->syncOpenProviderRequests($rate, $counts);
            $this->syncOpenInvoices($rate, $counts);
            $this->syncOpenPlatformCommissions($rate, $counts);
        });

        return $counts;
    }

    private function syncCatalogPrices(float $rate, array &$counts): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'price_syp') && Schema::hasColumn('services', 'price_usd')) {
            DB::table('services')
                ->where(function ($query): void {
                    $query->where('price_syp', '>', 0)->orWhere('price_usd', '>', 0);
                })
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($rate, &$counts): void {
                    foreach ($rows as $row) {
                        $priceSyp = (float) ($row->price_syp ?? 0);
                        $priceUsd = (float) ($row->price_usd ?? 0);
                        $updates = ['updated_at' => now()];
                        if ($priceSyp > 0) {
                            $updates['price_usd'] = $this->toUsd($priceSyp, $rate);
                        } elseif ($priceUsd > 0) {
                            // Legacy USD-only services are normalized once into SYP;
                            // SYP becomes the canonical service price afterwards.
                            $updates['price_syp'] = $this->toSyp($priceUsd, $rate);
                        } else {
                            continue;
                        }
                        DB::table('services')->where('id', $row->id)->update($updates);
                        $counts['services']++;
                    }
                });
        }

        if (Schema::hasTable('venues') && Schema::hasColumn('venues', 'price_syp') && Schema::hasColumn('venues', 'price_usd')) {
            DB::table('venues')->orderBy('id')->chunkById(200, function ($rows) use ($rate, &$counts): void {
                foreach ($rows as $row) {
                    $currencyBase = strtoupper((string) ($row->currency_base ?? 'SYP'));
                    $updates = ['updated_at' => now()];

                    if ($currencyBase === 'USD' && (float) ($row->price_usd ?? 0) > 0) {
                        $updates['price_syp'] = $this->toSyp($row->price_usd, $rate);
                    } elseif ((float) ($row->price_syp ?? 0) > 0) {
                        $updates['price_usd'] = $this->toUsd($row->price_syp, $rate);
                    } else {
                        continue;
                    }

                    DB::table('venues')->where('id', $row->id)->update($updates);
                    $counts['venues']++;
                }
            });
        }

        if (Schema::hasTable('venue_services')
            && Schema::hasColumn('venue_services', 'custom_price_syp')
            && Schema::hasColumn('venue_services', 'custom_price_usd')) {
            DB::table('venue_services')
                ->where(function ($query): void {
                    $query->where('custom_price_syp', '>', 0)->orWhere('custom_price_usd', '>', 0);
                })
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($rate, &$counts): void {
                    foreach ($rows as $row) {
                        $customSyp = (float) ($row->custom_price_syp ?? 0);
                        $customUsd = (float) ($row->custom_price_usd ?? 0);
                        $updates = ['updated_at' => now()];
                        if ($customSyp > 0) {
                            $updates['custom_price_usd'] = $this->toUsd($customSyp, $rate);
                        } elseif ($customUsd > 0) {
                            $updates['custom_price_syp'] = $this->toSyp($customUsd, $rate);
                        } else {
                            continue;
                        }
                        DB::table('venue_services')->where('id', $row->id)->update($updates);
                        $counts['venue_services']++;
                    }
                });
        }
    }

    private function syncOpenBookings(float $rate, array &$counts): void
    {
        if (!Schema::hasTable('bookings')) {
            return;
        }

        $lockedBookingIds = Schema::hasTable('invoices')
            ? DB::table('invoices')
                ->where('source_type', 'venue_booking')
                ->whereIn('status', $this->finalInvoiceStatuses())
                ->whereNotNull('booking_id')
                ->pluck('booking_id')
                ->unique()
                ->values()
                ->all()
            : [];

        $query = DB::table('bookings')->orderBy('id');
        if ($lockedBookingIds !== []) {
            $query->whereNotIn('id', $lockedBookingIds);
        }
        if (Schema::hasColumn('bookings', 'payment_status')) {
            $query->whereNotIn('payment_status', ['approved', 'paid', 'verified', 'refunded']);
        }

        $query->chunkById(200, function ($rows) use ($rate, &$counts): void {
            foreach ($rows as $row) {
                $updates = [];
                $this->setUsdMirror($updates, 'subtotal', $row, $rate);
                $this->setUsdMirror($updates, 'discount', $row, $rate);
                $this->setUsdMirror($updates, 'total', $row, $rate);
                $this->setUsdMirror($updates, 'platform_commission', $row, $rate);
                $this->setUsdMirror($updates, 'owner_net', $row, $rate);

                if (Schema::hasColumn('bookings', 'exchange_rate_syp_per_usd')) {
                    $updates['exchange_rate_syp_per_usd'] = $rate;
                }
                if ($updates === []) {
                    continue;
                }
                $updates['updated_at'] = now();
                DB::table('bookings')->where('id', $row->id)->update($updates);
                $counts['bookings']++;
            }
        });
    }

    private function syncOpenBookingServices(float $rate, array &$counts): void
    {
        if (!Schema::hasTable('booking_services') || !Schema::hasTable('bookings')) {
            return;
        }
        if (!Schema::hasColumn('booking_services', 'unit_price_syp')
            || !Schema::hasColumn('booking_services', 'unit_price_usd')
            || !Schema::hasColumn('booking_services', 'total_syp')
            || !Schema::hasColumn('booking_services', 'total_usd')) {
            return;
        }

        $lockedBookingIds = Schema::hasTable('invoices')
            ? DB::table('invoices')
                ->where('source_type', 'venue_booking')
                ->whereIn('status', $this->finalInvoiceStatuses())
                ->whereNotNull('booking_id')
                ->pluck('booking_id')
                ->unique()
                ->values()
                ->all()
            : [];

        if (Schema::hasColumn('bookings', 'payment_status')) {
            $paidBookingIds = DB::table('bookings')
                ->whereIn('payment_status', ['approved', 'paid', 'verified', 'refunded'])
                ->pluck('id')
                ->all();
            $lockedBookingIds = array_values(array_unique([...$lockedBookingIds, ...$paidBookingIds]));
        }

        $query = DB::table('booking_services')->orderBy('id');
        if ($lockedBookingIds !== []) {
            $query->whereNotIn('booking_id', $lockedBookingIds);
        }

        $query->chunkById(200, function ($rows) use ($rate, &$counts): void {
            foreach ($rows as $row) {
                DB::table('booking_services')->where('id', $row->id)->update([
                    'unit_price_usd' => $this->toUsd($row->unit_price_syp ?? 0, $rate),
                    'total_usd' => $this->toUsd($row->total_syp ?? 0, $rate),
                    'updated_at' => now(),
                ]);
                $counts['booking_services']++;
            }
        });
    }

    private function syncOpenProviderRequests(float $rate, array &$counts): void
    {
        if (!Schema::hasTable('provider_service_requests')) {
            return;
        }

        $lockedRequestIds = Schema::hasTable('invoices')
            ? DB::table('invoices')
                ->where('source_type', 'provider_service')
                ->whereIn('status', $this->finalInvoiceStatuses())
                ->whereNotNull('source_id')
                ->pluck('source_id')
                ->unique()
                ->values()
                ->all()
            : [];

        $query = DB::table('provider_service_requests')->orderBy('id');
        if ($lockedRequestIds !== []) {
            $query->whereNotIn('id', $lockedRequestIds);
        }
        if (Schema::hasColumn('provider_service_requests', 'payment_status')) {
            $query->whereNotIn('payment_status', ['approved', 'paid', 'verified', 'refunded']);
        }

        $query->chunkById(200, function ($rows) use ($rate, &$counts): void {
            foreach ($rows as $row) {
                $updates = [];
                if (Schema::hasColumn('provider_service_requests', 'price_syp') && Schema::hasColumn('provider_service_requests', 'price_usd')) {
                    $updates['price_usd'] = $this->toUsd($row->price_syp ?? 0, $rate);
                }
                $this->setUsdMirror($updates, 'provider_commission', $row, $rate, 'provider_service_requests');
                $this->setUsdMirror($updates, 'provider_net', $row, $rate, 'provider_service_requests');
                if (Schema::hasColumn('provider_service_requests', 'exchange_rate_syp_per_usd')) {
                    $updates['exchange_rate_syp_per_usd'] = $rate;
                }
                if ($updates === []) {
                    continue;
                }
                $updates['updated_at'] = now();
                DB::table('provider_service_requests')->where('id', $row->id)->update($updates);
                $counts['provider_requests']++;
            }
        });
    }

    private function syncOpenInvoices(float $rate, array &$counts): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        DB::table('invoices')
            ->whereNotIn('status', $this->finalInvoiceStatuses())
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($rate, &$counts): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach (['subtotal', 'discount', 'total', 'commission', 'net'] as $prefix) {
                        $this->setUsdMirror($updates, $prefix, $row, $rate, 'invoices');
                    }
                    if (Schema::hasColumn('invoices', 'exchange_rate_syp_per_usd')) {
                        $updates['exchange_rate_syp_per_usd'] = $rate;
                    }
                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table('invoices')->where('id', $row->id)->update($updates);
                        $counts['invoices']++;
                    }

                    if (Schema::hasTable('payment_transactions')) {
                        $totalUsd = $updates['total_usd'] ?? $this->toUsd($row->total_syp ?? 0, $rate);
                        $affected = DB::table('payment_transactions')
                            ->where('invoice_id', $row->id)
                            ->where('currency', 'USD')
                            ->whereIn('status', ['pending', 'failed'])
                            ->update([
                                'amount' => $totalUsd,
                                'updated_at' => now(),
                            ]);
                        $counts['transactions'] += $affected;
                    }
                }
            });
    }

    private function syncOpenPlatformCommissions(float $rate, array &$counts): void
    {
        if (!Schema::hasTable('platform_commissions')) {
            return;
        }

        $lockedBookingIds = [];
        $lockedRequestIds = [];
        if (Schema::hasTable('invoices')) {
            $lockedBookingIds = DB::table('invoices')
                ->where('source_type', 'venue_booking')
                ->whereIn('status', $this->finalInvoiceStatuses())
                ->whereNotNull('booking_id')
                ->pluck('booking_id')
                ->unique()
                ->values()
                ->all();
            $lockedRequestIds = DB::table('invoices')
                ->where('source_type', 'provider_service')
                ->whereIn('status', $this->finalInvoiceStatuses())
                ->whereNotNull('source_id')
                ->pluck('source_id')
                ->unique()
                ->values()
                ->all();
        }

        DB::table('platform_commissions')
            ->whereIn('status', ['uncollected', 'cancelled'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($rate, &$counts, $lockedBookingIds, $lockedRequestIds): void {
                foreach ($rows as $row) {
                    $sourceType = (string) ($row->source_type ?? '');
                    $sourceId = (int) ($row->source_id ?? 0);
                    if (($sourceType === 'booking' && in_array($sourceId, $lockedBookingIds, true))
                        || ($sourceType === 'provider_service_request' && in_array($sourceId, $lockedRequestIds, true))) {
                        continue;
                    }

                    DB::table('platform_commissions')->where('id', $row->id)->update([
                        'gross_usd' => $this->toUsd($row->gross_syp ?? 0, $rate),
                        'commission_usd' => $this->toUsd($row->commission_syp ?? 0, $rate),
                        'net_usd' => $this->toUsd($row->net_syp ?? 0, $rate),
                        'updated_at' => now(),
                    ]);
                    $counts['platform_commissions']++;
                }
            });
    }

    private function setUsdMirror(array &$updates, string $prefix, object $row, float $rate, ?string $table = 'bookings'): void
    {
        $sypColumn = $prefix.'_syp';
        $usdColumn = $prefix.'_usd';
        if (!Schema::hasColumn($table, $sypColumn) || !Schema::hasColumn($table, $usdColumn)) {
            return;
        }
        $updates[$usdColumn] = $this->toUsd($row->{$sypColumn} ?? 0, $rate);
    }

    private function finalInvoiceStatuses(): array
    {
        return ['proof_uploaded', 'paid', 'refund_pending', 'refunded', 'cancelled'];
    }

    private function assertValidRate(float $rate): void
    {
        if (!is_finite($rate) || $rate < 1 || $rate > 1000000000) {
            throw new InvalidArgumentException('Invalid USD to SYP exchange rate.');
        }
    }

    private function normaliseRate(float $rate): float
    {
        $this->assertValidRate($rate);
        return $rate;
    }

    private function formatRate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.');
    }
}
