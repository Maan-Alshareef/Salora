<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProviderProfileController extends BaseApiController
{
    private const WEEKDAYS = [
        'saturday',
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
    ];

    public function show(Request $request)
    {
        $profile = ProviderProfile::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'business_name' => $request->user()->name,
                'contact_phone' => $request->user()->phone,
                'whatsapp_phone' => $request->user()->phone,
                'allow_phone' => true,
                'allow_whatsapp' => true,
            ],
        );

        return $this->ok([
            'user' => $request->user()->fresh(),
            'profile' => $profile,
        ]);
    }

    public function update(Request $request)
    {
        if ($request->has('days_off')) {
            $request->merge([
                'days_off' => collect($request->input('days_off', []))
                    ->map(fn ($day) => $this->normaliseWeekday((string) $day))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }

        $data = $request->validate([
            'business_name' => 'nullable|string|max:180',
            'city' => 'nullable|string|max:120',
            'bio' => 'nullable|string|max:2000',
            'coverage_areas' => 'nullable|array|max:50',
            'coverage_areas.*' => 'string|max:120',
            'working_hours' => 'nullable|array',
            'days_off' => 'nullable|array|max:7',
            'days_off.*' => ['string', Rule::in(self::WEEKDAYS)],
            'contact_phone' => ['nullable', 'regex:/^\d{10}$/'],
            'whatsapp_phone' => ['nullable', 'regex:/^\d{10}$/'],
            'allow_phone' => 'sometimes|boolean',
            'allow_whatsapp' => 'sometimes|boolean',
        ]);

        $profile = ProviderProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return $this->ok([
            'user' => $request->user()->fresh(),
            'profile' => $profile->fresh(),
        ], 'تم تحديث بيانات مقدم الخدمة وخيارات ظهور رقم التواصل.');
    }

    private function normaliseWeekday(string $value): ?string
    {
        $value = trim(mb_strtolower($value));
        $aliases = [
            'السبت' => 'saturday',
            'الأحد' => 'sunday',
            'الاحد' => 'sunday',
            'الاثنين' => 'monday',
            'الإثنين' => 'monday',
            'الثلاثاء' => 'tuesday',
            'الأربعاء' => 'wednesday',
            'الاربعاء' => 'wednesday',
            'الخميس' => 'thursday',
            'الجمعة' => 'friday',
        ];

        $normalised = $aliases[$value] ?? $value;

        return in_array($normalised, self::WEEKDAYS, true)
            ? $normalised
            : null;
    }
}
