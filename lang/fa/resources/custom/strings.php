<?php

return [
    'general' => [
        'model_label' => 'امور گمرکی و ترخیص',
        'plural_model_label' => 'امور گمرکی و ترخیص',
        'navigation_group' => 'داده‌های عملیاتی',
        'add_record' => '˙⋆✮ ایجاد جدید',
        'clearance_status' => [
            'declared' => 'اظهار شده',
            'documents_submitted' => 'ارائه اسناد',
            'pending_payment' => 'در انتظار پرداخت',
            'pending_editing' => 'در انتظار ویرایش',
            'exit_gate' => 'درب خروج',
            'ten_percent_remaining' => 'مانده ۱۰ درصد',
            'appraisal' => 'کارشناسی',
            'completed' => 'پایان یافته',
        ],
        'bank_guarantee_status' => [
            'returned' => 'عودت شده',
            'not_returned' => 'عودت نشده',
        ],
        'commitment_status' => [
            'completed' => 'انجام شده',
            'not_completed' => 'انجام نشده',
        ],
        'clearance_types' => [
            'definitive' => 'ترخیص قطعی',
            'percentage' => 'ترخیص درصدی',
        ],
    ],
    'form' => [
        'tabs' => [
            'general' => 'اطلاعات عمومی',
            'dates' => 'زمان‌بندی و تاریخ‌ها',
            'status_financial' => 'وضعیت و تعهدات',
        ],
        'section_general' => 'اطلاعات اظهارنامه',
        'section_dates' => 'جدول زمانی فرآیند',
        'section_status' => 'بررسی وضعیت‌ها',
        'section_financial' => 'اطلاعات مالی و تعهدات',
        'section_notes' => 'یادداشت‌ها و پیوست‌ها',

        'custom_no' => 'شماره امور گمرکی و ترخیص',
        'registered_order' => 'ثبت سفارش',
        'shipment' => 'محموله',
        'contract_no' => 'شماره قرارداد',
        'declaration_no' => 'شماره اظهارنامه (کوتاژ)',
        'clearance_type' => 'نوع ترخیص',

        'clearance_date' => 'تاریخ ترخیص',
        'doc_submission_date' => 'تاریخ تحویل اسناد',
        'ten_percent_exit_date' => 'تاریخ خروج ۱۰٪',
        'payment_due_date' => 'تاریخ سررسید پرداخت',
        'commitment_payment_date' => 'تاریخ پرداخت تعهد',
        'rial_return_date' => 'تاریخ بازگشت ریال',

        'commitment_balance' => 'مانده تعهد',

        'clearance_status' => 'وضعیت ترخیص',
        'bank_guarantee_status' => 'وضعیت ضمانت بانکی',
        'commitment_status' => 'وضعیت تعهد',

        'notes' => 'یادداشت‌ها',
        'attachments' => 'پیوست‌ها',

        'helper_custom_no' => 'این شماره به‌صورت خودکار تولید می‌شود و قابل ویرایش نیست.',
        'helper_shipment' => 'با انتخاب محموله، ثبت سفارش و شماره قرارداد آن به‌طور خودکار برای شما تکمیل می‌شود.',

        'validation_required' => 'این فیلد الزامی است.',
        'validation_unique' => 'این مقدار تکراری است.',
        'validation_numeric' => 'این فیلد باید عدد باشد.',
        'validation_date' => 'لطفاً یک تاریخ معتبر وارد کنید.',
        'validation_contract_no_max' => 'شماره قرارداد نباید بیش از ۲۵۵ نویسه باشد.',
        'validation_declaration_no_max' => 'شماره اظهارنامه نباید بیش از ۲۵۵ نویسه باشد.',
    ],
    'table' => [
        'declaration_no' => 'شماره کوتاژ',
        'registered_order' => 'سفارش مرتبط',
        'shipment' => 'مرتبط با ',
        'contract_no' => 'شماره قرارداد',
        'clearance_date' => 'تاریخ ترخیص',
        'clearance_status' => 'وضعیت ترخیص',
        'commitment_balance' => 'مانده تعهد',
        'created_by' => 'ایجادکننده',
        'updated_by' => 'ویرایش‌کننده',
        'created_at' => 'تاریخ ایجاد',
        'updated_at' => 'تاریخ بروزرسانی',
    ],
    'filters' => [
        'contract_no' => 'شماره قرارداد',
        'registered_order' => 'ثبت سفارش',
        'shipment' => 'محموله',
        'clearance_status' => 'وضعیت ترخیص',
        'bank_guarantee_status' => 'وضعیت ضمانت بانکی',
        'commitment_status' => 'وضعیت تعهد',
        'created_from' => 'ایجاد از تاریخ',
        'created_until' => 'ایجاد تا تاریخ',
    ],
    'infolist' => [
        'tab_general' => 'اطلاعات عمومی',
        'tab_dates_status' => 'تاریخ‌ها و وضعیت',
        'tab_documents' => 'پیوست‌ها',
    ],
];
