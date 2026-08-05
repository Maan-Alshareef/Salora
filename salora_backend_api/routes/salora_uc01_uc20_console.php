<?php

use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('salora:sync-payment-methods', function (): int {
    $methods = [
        ['slug' => 'sham_cash', 'name_ar' => 'شام كاش', 'name_en' => 'Sham Cash', 'logo_path' => '/payment-methods/sham-cash.svg', 'instructions' => 'حوّل المبلغ كاملاً إلى الحساب الظاهر ثم ارفع صورة الوصل ورقم العملية.', 'sort_order' => 1],
        ['slug' => 'syriatel_cash', 'name_ar' => 'سيريتل كاش', 'name_en' => 'Syriatel Cash', 'logo_path' => '/payment-methods/syriatel-cash.svg', 'instructions' => 'حوّل المبلغ كاملاً إلى محفظة سيريتل كاش الظاهرة ثم ارفع إثبات التحويل.', 'sort_order' => 2],
        ['slug' => 'al_haram', 'name_ar' => 'الهرم للحوالات المالية', 'name_en' => 'Al Haram Transfer', 'logo_path' => '/payment-methods/al-haram.svg', 'instructions' => 'نفّذ الحوالة باسم المستلم والفرع الظاهرين ثم ارفع صورة الوصل ورقم الحوالة.', 'sort_order' => 3],
    ];

    foreach ($methods as $method) {
        PaymentMethod::query()->updateOrCreate(
            ['slug' => $method['slug']],
            $method + ['type' => 'manual_transfer', 'is_active' => true, 'for_venues' => true, 'for_providers' => true],
        );
    }

    PaymentMethod::query()
        ->whereNotIn('slug', array_column($methods, 'slug'))
        ->update(['is_active' => false]);

    $this->info('تم اعتماد شام كاش وسيريتل كاش والهرم فقط.');

    return self::SUCCESS;
})->purpose('Sync the three approved Salora payment methods and disable all others.');

Schedule::command('salora:send-todo-reminders')->everyTenMinutes()->withoutOverlapping();
Schedule::command('salora:process-payment-deadlines')->everyTenMinutes()->withoutOverlapping();
