<?php
return [
    'status' => [
        'active' => 'فعال',
    ],
    'resources' => [
        'banks' => 'بانک‌ها',
        'companies' => 'شرکت‌ها',
        'statuses' => 'وضعیت‌ها',
        'products' => 'محصولات',
        'users' => 'کاربران',
        'categories' => 'دسته‌ها',
        'targets' => 'اهداف',
        'currencies' => 'ارزها',
        'purchase_requests' => 'درخواست‌های خرید',
        'purchase_orders' => 'سفارشات خرید',
        'proforma_invoices' => 'پروفورما',
        'payments' => 'پرداخت‌ها',
        'roles' => 'نقش‌ها',
        'permissions' => 'مجوزها',
        'notification_settings' => 'اطلاعیه ها',
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
        ],
        'logistics' => [
            'title' => 'لجستیک و ترخیص ',
            'description' => '❹ تکمیل فرایند: مدیریت محموله‌ها و ترخیص گمرکی',
        ],
    ],
    'view_requests' => ' درخواست‌های خرید',
    'view_orders'   => ' ثبت سفارش‌ها',      
    'purchase_orders' => ' سفارشهای خرید',
    'proforma' => ' پروفورما',
    'banks' => ' پروفایل بانکی',
    'payments' => ' پرداخت‌ها',
    'submodules' => [
        'shipment' => [
            'title' => 'محموله‌ها',
            'description' => 'ردیابی محموله‌ها',
        ],
        'custom_clearance' => [
            'title' => 'ترخیص گمرکی',
            'description' => 'رسیدگی به امور گمرکی',
        ],
    ],
    'flow' => [
        'request' => 'درخواست',
        'order' => 'سفارش',
        'payment' => 'پرداخت',
        'logistics' => 'لجستیک',
    ],
    'bms_dashboard' => ' BMS',
    'return_to_main_panel' => 'ورود به داشبورد اصلی',
];
