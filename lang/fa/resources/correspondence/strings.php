<?php

return [
    'general' => [
        'model_label' => 'مکاتبه و لاگ',
        'plural_model_label' => 'مکاتبات و لاگها',
        'navigation_group' => 'اطلاعات عملیاتی',
        'create_new' => '˙⋆✮ پیام و لاگ جدید',
    ],
    'enums' => [
        'type' => [
            'report' => 'گزارش',
            'inquiry' => 'استعلام',
            'warning' => 'هشدار',
            'note' => 'یادداشت',
            'announcement' => 'اطلاعیه',
        ],
        'priority' => [
            'low' => 'پایین',
            'normal' => 'عادی',
            'high' => 'بالا',
            'urgent' => 'فوری',
        ],
    ],
    'form' => [
        'section_main' => 'محتوای پیام',
        'section_settings' => 'تنظیمات و دسترسی',
        'section_recipients' => 'گیرندگان و ارجاعات',

        'subject' => 'موضوع',
        'body' => 'متن پیام',
        'type' => 'نوع',
        'priority' => 'اولویت',
        'status' => 'وضعیت',
        'is_internal' => 'یادداشت داخلی',
        'is_private' => 'محرمانه (محدود)',
        'parent' => 'پاسخ به',

        'recipients' => 'گیرندگان',
        'recipients_to' => 'گیرنده اصلی (جهت اقدام)',
        'recipients_cc' => 'رونوشت (جهت اطلاع)',

        'helper_internal' => 'فقط برای کارکنان داخلی قابل مشاهده است.',
        'helper_private' => 'فقط برای شما و گیرندگان انتخاب‌شده قابل مشاهده است.',

        'validation_required' => 'این فیلد الزامی است.',
        'validation_unique' => 'این مقدار تکراری است.',
    ],
    'table' => [
        'subject' => 'موضوع',
        'type' => 'نوع',
        'priority' => 'اولویت',
        'status' => 'وضعیت',
        'visibility' => 'قابلیت مشاهده',
        'recipients' => 'گیرندگان',
        'creator' => 'ایجاد کننده',
        'updater' => 'بروزرسانی کننده',
        'created_at' => 'تاریخ ایجاد',
        'updated_at' => 'تاریخ بروزرسانی',
        'thread' => 'رشته گفتگو',
        'thread_prefix' => 'گفتگو: ',
    ],
    'filters' => [
        'type' => 'نوع',
        'priority' => 'اولویت',
        'status' => 'وضعیت',
        'creator' => 'نویسنده',
        'my_mentions' => 'پیام‌های من (دریافتی)',
        'created_from' => 'ایجاد از تاریخ',
        'created_until' => 'ایجاد تا تاریخ',
    ],
    'infolist' => [
        'tab_general' => 'جزئیات پیام',
        'attachments' => 'پیوست‌ها',
        'creator' => 'ایجاد کننده',
    ],
    'export' => [
        'filename' => 'خروجی-مکاتبات',
    ]
];
