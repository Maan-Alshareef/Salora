<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OfferAnnouncementService
{
    private ?array $offerColumns = null;

    public function processAll(bool $prime = false): array
    {
        $processed = 0;
        $announced = 0;

        Offer::query()
            ->orderBy((new Offer())->getKeyName())
            ->cursor()
            ->each(function (Offer $offer) use (
                $prime,
                &$processed,
                &$announced,
            ): void {
                $processed++;

                if ($this->process($offer, $prime)) {
                    $announced++;
                }
            });

        return compact('processed', 'announced');
    }

    public function process(Offer $offer, bool $prime = false): bool
    {
        $key = $offer->getKey();

        if ($key === null) {
            return false;
        }

        return Cache::lock(
            'salora:offer-announcement:'.$key,
            120,
        )->block(10, function () use ($offer, $prime): bool {
            $fresh = Offer::query()->find($offer->getKey());

            if ($fresh === null || !$this->isPublishable($fresh)) {
                return false;
            }

            $details = $this->details($fresh);
            $signature = hash(
                'sha256',
                json_encode(
                    $details,
                    JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES,
                ) ?: '',
            );

            $oldSignature = trim(
                (string) $fresh->getAttribute(
                    'push_announcement_signature',
                ),
            );

            if ($oldSignature === $signature) {
                return false;
            }

            DB::table($fresh->getTable())
                ->where($fresh->getKeyName(), $fresh->getKey())
                ->update([
                    'push_announcement_signature' => $signature,
                    'push_announced_at' => now(),
                ]);

            if ($prime) {
                return true;
            }

            $isFirstAnnouncement = $oldSignature === '';
            $title = $isFirstAnnouncement
                ? 'عرض جديد في '.$details['venue_name']
                : 'تم تحديث عرض '.$details['venue_name'];

            $bodyParts = [
                'الصالة: '.$details['venue_name'],
            ];

            if ($details['location'] !== '') {
                $bodyParts[] = 'الموقع: '.$details['location'];
            }

            $bodyParts[] = 'الخصم: '.$details['discount'];
            $bodyParts[] = 'من '.$details['starts_at'].' حتى '.$details['ends_at'];

            $body = implode(' — ', $bodyParts).'.';

            $query = User::query()
                ->whereRaw('LOWER(role) = ?', ['customer']);

            $userColumns = Schema::getColumnListing(
                (new User())->getTable(),
            );

            if (in_array('status', $userColumns, true)) {
                $query->whereRaw('LOWER(status) = ?', ['active']);
            } elseif (in_array('is_active', $userColumns, true)) {
                $query->where('is_active', true);
            }

            $query
                ->orderBy((new User())->getKeyName())
                ->chunkById(
                    100,
                    function ($users) use (
                        $title,
                        $body,
                        $details,
                        $fresh,
                    ): void {
                        foreach ($users as $user) {
                            NotificationService::send(
                                (int) $user->getKey(),
                                $title,
                                $body,
                                'offer',
                                [
                                    'offer_id' => (string) $fresh->getKey(),
                                    'venue_id' => (string) (
                                        $details['venue_id'] ?? ''
                                    ),
                                    'venue_name' => $details['venue_name'],
                                    'location' => $details['location'],
                                    'discount' => $details['discount'],
                                    'starts_at' => $details['starts_at'],
                                    'ends_at' => $details['ends_at'],
                                ],
                            );
                        }
                    },
                );

            return true;
        });
    }

    private function isPublishable(Offer $offer): bool
    {
        $columns = $this->columns();

        if ($columns['status'] !== null) {
            $statusValue = $offer->getAttribute($columns['status']);

            if ($statusValue instanceof \BackedEnum) {
                $statusValue = $statusValue->value;
            }

            $status = strtolower(trim((string) $statusValue));

            if (!in_array($status, ['approved', 'active', 'published'], true)) {
                return false;
            }
        } elseif ($columns['active'] !== null) {
            if (!(bool) $offer->getAttribute($columns['active'])) {
                return false;
            }
        } else {
            return false;
        }

        if ($columns['end'] !== null) {
            try {
                $end = Carbon::parse(
                    $offer->getAttribute($columns['end']),
                )->endOfDay();

                if ($end->isPast()) {
                    return false;
                }
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }

    private function details(Offer $offer): array
    {
        $columns = $this->columns();
        $venueId = $columns['venue'] !== null
            ? $offer->getAttribute($columns['venue'])
            : null;
        $venue = $venueId !== null
            ? Venue::query()->find($venueId)
            : null;

        $venueName = $venue !== null
            ? $this->firstAttribute(
                $venue,
                ['name', 'title', 'name_ar'],
                'صالة Salora',
            )
            : 'صالة Salora';

        $city = $venue !== null
            ? $this->firstAttribute($venue, ['city'], '')
            : '';
        $address = $venue !== null
            ? $this->firstAttribute(
                $venue,
                ['address', 'location', 'address_text'],
                '',
            )
            : '';

        $location = implode(
            ' - ',
            array_values(
                array_filter(
                    [$city, $address],
                    fn (string $value): bool => $value !== '',
                ),
            ),
        );

        $discount = $this->discountText($offer, $columns);
        $startsAt = $this->dateText(
            $columns['start'] !== null
                ? $offer->getAttribute($columns['start'])
                : null,
            'الآن',
        );
        $endsAt = $this->dateText(
            $columns['end'] !== null
                ? $offer->getAttribute($columns['end'])
                : null,
            'حتى إشعار آخر',
        );

        return [
            'offer_id' => $offer->getKey(),
            'venue_id' => $venueId,
            'venue_name' => $venueName,
            'location' => $location,
            'discount' => $discount,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    private function discountText(Offer $offer, array $columns): string
    {
        if ($columns['discount_percent'] !== null) {
            return rtrim(
                rtrim(
                    number_format(
                        (float) $offer->getAttribute(
                            $columns['discount_percent'],
                        ),
                        2,
                        '.',
                        '',
                    ),
                    '0',
                ),
                '.',
            ).'%';
        }

        if ($columns['discount_value'] !== null) {
            $value = (float) $offer->getAttribute(
                $columns['discount_value'],
            );
            $type = $columns['discount_type'] !== null
                ? strtolower(
                    trim(
                        (string) $offer->getAttribute(
                            $columns['discount_type'],
                        ),
                    ),
                )
                : '';

            if (in_array($type, ['percent', 'percentage'], true)) {
                return rtrim(rtrim((string) $value, '0'), '.').'%';
            }

            return number_format($value, 0, '.', ',').' ل.س';
        }

        return 'خصم خاص';
    }

    private function dateText(mixed $value, string $fallback): string
    {
        if ($value === null || trim((string) $value) === '') {
            return $fallback;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return trim((string) $value);
        }
    }

    private function firstAttribute(
        object $model,
        array $candidates,
        string $fallback,
    ): string {
        foreach ($candidates as $candidate) {
            $value = trim((string) $model->getAttribute($candidate));

            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private function columns(): array
    {
        if ($this->offerColumns !== null) {
            return $this->offerColumns;
        }

        $listing = Schema::getColumnListing((new Offer())->getTable());

        $this->offerColumns = [
            'venue' => $this->pick(
                $listing,
                ['venue_id', 'hall_id'],
            ),
            'status' => $this->pick(
                $listing,
                ['status', 'approval_status'],
            ),
            'active' => $this->pick(
                $listing,
                ['is_active', 'active'],
            ),
            'discount_percent' => $this->pick(
                $listing,
                [
                    'discount_percentage',
                    'discount_percent',
                    'percentage',
                    'discount',
                ],
            ),
            'discount_value' => $this->pick(
                $listing,
                ['discount_value', 'amount'],
            ),
            'discount_type' => $this->pick(
                $listing,
                ['discount_type', 'type'],
            ),
            'start' => $this->pick(
                $listing,
                ['start_date', 'starts_at', 'starts_on', 'valid_from', 'from_date'],
            ),
            'end' => $this->pick(
                $listing,
                ['end_date', 'ends_at', 'ends_on', 'valid_until', 'to_date'],
            ),
        ];

        return $this->offerColumns;
    }

    private function pick(array $listing, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $listing, true)) {
                return $candidate;
            }
        }

        return null;
    }
}