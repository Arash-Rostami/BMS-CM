<?php
return [
    'status' => [
        'active' => 'فعال',
    ],
    'steps' => [
        'request_approval' => [
            'title' => 'درخواست و تأیید',
            'description' => '❶ آغاز پیش فرایند: ایجاد درخواست خرید یا ثبت پیش فاکتور',
            'pending_requests' => 'درخواست‌ خرید',
        ],
        'order_processing' => [
            'title' => 'پردازش سفارش',
            'description' => '❷ آغاز فرایند: ثبت سفارش و ایجاد پروفایل بانکی',
            'active_orders' => 'سفارش‌های فعال',
        ],
        'procurement_payment' => [
            'title' => 'تدارکات و پرداخت',
            'description' => '❸ ادامه فرایند: ایجاد سفارش خرید و پردازش پرداخت ها',
            'pending_payments' => 'پرداخت‌ها',
        ],
        'logistics' => [
            'title' => 'لجستیک و ترخیص',
            'description' => '❹ تکمیل فرایند: ایجاد و پردازش داده‌های محموله، لجستیک و ترخیص گمرک',
        ],
    ],
    'view_requests' => 'مشاهده درخواست‌ها',
    'view_orders' => 'مشاهده سفارش‌ها',
    'proforma' => 'مشاهده پروفورما',
    'banks' => 'مشاهده بانک‌ها',
    'payments' => 'مشاهده پرداخت‌ها',
    'coming_soon' => 'به‌زودی',
    'submodules' => [
        'shipment' => [
            'title' => 'محموله‌',
            'description' => 'ردیابی محموله‌ها',
        ],
        'logistics' => [
            'title' => 'لجستیک',
            'description' => 'مدیریت عملیات',
        ],
        'custom_clearance' => [
            'title' => 'ترخیص گمرکی',
            'description' => 'رسیدگی به امور گمرکی',
        ],
    ],
    'show_submodules' => 'نمایش ماژول‌ها',
    'hide_submodules' => 'پنهان کردن ماژول‌ها',
    'flow' => [
        'request' => 'درخواست',
        'order' => 'سفارش',
        'payment' => 'پرداخت',
        'logistics' => 'لجستیک',
    ],
    'bms_dashboard' => ' BMS',
    'return_to_main_panel' => 'ورود به داشبورد اصلی',
];
