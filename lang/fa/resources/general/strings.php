<?php

return [
    'actions' => [
        'view_tooltip' => 'مشاهده',
        'edit_tooltip' => 'ویرایش',
        'delete_tooltip' => 'حذف',
        'restore_tooltip' => 'بازیابی',
        'create' => 'ایجاد',
        'view' => 'مشاهده',
        'edit' => 'ویرایش',
        'delete' => 'حذف',
    ],
    'bulk' => [
        'activate' => [
            'label' => 'فعال سازی',
            'notification' => 'موارد انتخاب شده با موفقیت فعال شدند.',
        ],
        'deactivate' => [
            'label' => 'غیرفعال سازی',
            'notification' => 'موارد انتخاب شده با موفقیت غیرفعال شدند.',
        ],
    ],
    'relevant_module' => [
        'form' => [
            'purchase_requests' => 'درخواست‌های خرید',
            'purchase_orders' => 'سفارش‌های خرید',
            'proforma_invoices' => 'پیش‌فاکتورها',
            'registered_orders' => 'سفارش‌های ثبت‌شده',
            'related_to' => 'مرتبط با',
            'purchase_requests_related' => 'درخواست‌های خرید',
            'purchase_orders_related' => 'سفارش‌های خرید',
            'proforma_invoices_related' => 'پیش‌فاکتورها',
            'registered_orders_related' => 'سفارش‌های ثبت‌شده',
        ],
        'table' => [
            'related_to' => 'مرتبط با',
            'purchase_requests' => 'درخواست‌های خرید مرتبط',
            'purchase_orders' => 'سفارش‌های خرید مرتبط',
            'proforma_invoices' => 'پیش‌فاکتورهای مرتبط',
            'registered_orders' => 'سفارش‌های ثبت‌شده مرتبط',
        ],
    ],
    'attachments' => [
        'attachments' => 'پیوست‌ها',
        'attachment_name' => 'نام پیوست',
        'item_attachments' => 'پیوست‌های قلم',
        'has_item_attachments' => 'دارای پیوست‌های قلم',
        'add_item_attachments' => 'افزودن پیوست‌های قلم',

        'validation' => [
            'attachments_max_files' => 'حداکثر ۱۰ فایل مجاز است',
            'attachments_type' => 'فرمت فایل معتبر نیست (تصاویر، PDF، اکسل)',
            'attachments_size' => 'حجم فایل نباید بیشتر از ۲.۵ مگابایت باشد',
            'invalid_filename_chars_hint' => 'لطفاً نام فایل را فقط با استفاده از کاراکترهای مجاز (حروف انگلیسی، فارسی، اعداد، فاصله، نقطه، خط تیره یا زیرخط) و به صورت کوتاه‌تر تغییر نام دهید.',
            'invalid_filename_chars' => 'نام فایل نامعتبر یا بیش از حد طولانی است.',
            'file_not_available_hint' => 'فایل بارگذاری‌شده دیگر در دسترس نیست. لطفاً دوباره بارگذاری کنید.',
            'file_not_available' => 'فایل منقضی شده است. لطفاً دوباره بارگذاری کنید.',
            'metadata_unreadable_hint' => 'امکان اعتبارسنجی فایل آپلودشده وجود ندارد. لطفاً دوباره تلاش کنید.',
            'metadata_unreadable' => 'اعتبارسنجی فایل ناموفق بود.',
            'processing_failed' => 'امکان پردازش فایل پیوست وجود ندارد. لطفاً از سالم بودن فایل اطمینان حاصل کرده و دوباره تلاش کنید.',
        ],

    ],
];
