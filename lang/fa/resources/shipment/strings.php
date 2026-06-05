<?php

return [
    'general' => [
        'model_label' => 'محموله و لاجیستیک',
        'plural_model_label' => ' محموله‌ها و لاجیستیک',
        'navigation_group' => 'داده‌های عملیاتی',
        'add_record' => '˙⋆✮ ایجاد جدید',
    ],
    'form' => [
        'tabs' => [
            'general' => 'اطلاعات عمومی',
            'logistics' => 'لجستیک و جزئیات',
        ],
        'section_general' => 'اطلاعات اصلی',
        'section_docs_notes' => 'اسناد و پیوست‌ها',
        'section_dates' => 'زمان‌بندی',
        'section_logistics' => 'داده‌های لجستیک',
        'section_status' => 'وضعیت‌ها',
        'section_amounts' => 'مبالغ و مقادیر',

        'shipment_no' => 'شماره محموله',
        'registered_order' => 'ثبت سفارش',
        'contract_no' => 'شماره قرارداد',
        'carrier' => 'حمل‌کننده (کریر)',
        'part' => 'پارت',
        'helper_part' => 'یک سفارش می‌تواند در چند پارت ارسال شود — شمارهٔ پارت بعدیِ آزاد به‌صورت خودکار پیشنهاد می‌شود و تنها شماره‌هایی که هنوز ارسال نشده‌اند قابل انتخاب‌اند.',
        'case_number' => 'شماره پرونده',

        'warehouse_date' => 'تاریخ قبض انبار',
        'exit_date' => 'تاریخ خروج',
        'eta' => 'تاریخ ورود (ETA)',
        'etd' => 'تاریخ حرکت (ETD)',

        'bl_number' => 'شماره بارنامه',
        'booking_no' => 'شماره بوکینگ',
        'container_no' => 'تعداد کانتینر',
        'container_type' => 'نوع کانتینر',
        'container_types_with_opt' => [
            'استاندارد' => [
                '20ft Standard' => '۲۰ فوت استاندارد',
                '40ft Standard' => '۴۰ فوت استاندارد',
                '40ft High Cube' => '۴۰ فوت های‌کیوب',
            ],
            'یخچالی' => [
                '20ft Refrigerated' => '۲۰ فوت یخچالی',
                '40ft Refrigerated' => '۴۰ فوت یخچالی',
            ],
            'روباز' => [
                '20ft Open Top' => '۲۰ فوت روباز',
                '40ft Open Top' => '۴۰ فوت روباز',
            ],
            'فلت رک' => [
                '20ft Flat Rack' => '۲۰ فوت فلت رک',
                '40ft Flat Rack' => '۴۰ فوت فلت رک',
            ],
            'سایر' => [
                'LCL' => 'خرده‌بار (LCL)',
                'Bulk' => 'فله',
            ],
        ],
        'container_types' => ['20ft Standard' => '۲۰ فوت استاندارد', '40ft Standard' => '۴۰ فوت استاندارد', '40ft High Cube' => '۴۰ فوت‌های کیوب', '20ft Refrigerated' => '۲۰ فوت یخچالی', '40ft Refrigerated' => '۴۰ فوت یخچالی', '20ft Open Top' => '۲۰ فوت روباز', '40ft Open Top' => '۴۰ فوت روباز', '20ft Flat Rack' => '۲۰ فوت فلت رک', '40ft Flat Rack' => '۴۰ فوت فلت رک', 'LCL' => 'خرده‌بار (LCL)', 'Bulk' => 'فله',],


        'remittance_amount' => 'مبلغ حواله',
        'customs_quantity' => 'مقدار باقیمانده در گمرک',
        'shipped_quantity' => 'مقدار حمل‌شده',

        'status' => 'وضعیت کلی',
        'shipment_status' => 'وضعیت حمل ',
        'operation_status' => 'وضعیت عملیات',
        'container_status' => 'وضعیت کانتینر',
        'guarantee_status' => 'وضعیت ضمانت',
        'doc_status' => 'وضعیت اسناد',

        'smart_tracer'      => 'ردیاب هوشمند اسناد',
        'smart_tracer_hint' => 'با روشن بودن، چک‌لیست به‌صورت خودکار از روی فایل‌های پیوست به‌روزرسانی می‌شود؛ با خاموش بودن، کاملاً دستی است.',
        'docs' => 'چک‌لیست اسناد',
        'doc_name' => 'نام سند',
        'doc_name_placeholder' => 'مثال: گواهی بازرسی',
        'doc_received' => 'دریافت شده',
        'add_doc' => 'افزودن سند',
        'docs_options' => [
            'track' => 'رهگیری هوشمند',
            'inspection' => 'بازرسی',
            'bank_commitment' => 'تعهد بانکی',
            'insurance' => 'بیمه',
            'ci' => 'فاکتور تجاری (CI)',
            'pl' => 'لیست بسته‌بندی (PL)',
            'co' => 'گواهی مبدا (CO)',
            'bl' => 'بارنامه (BL)',
            'do' => 'ترخیصیه (DO)',
        ],
        'attachments' => 'پیوست‌ها',
        'notes' => 'یادداشت‌ها',

        'validation' => [
            'required' => 'این فیلد الزامی است.',
            'unique' => 'این مقدار قبلاً ثبت شده است.',
            'unique_part' => 'این پارت قبلاً برای این سفارش و قرارداد ثبت شده است.',
            'english_only' => 'فقط حروف انگلیسی، اعداد، پرانتز و خط تیره مجاز هستند.',
            'numeric' => 'لطفاً یک عدد معتبر وارد کنید.',
            'min_numeric_zero' => 'این مقدار نمی‌تواند منفی باشد.',
            'max' => 'لطفاً این مقدار را در ۲۵۵ نویسه یا کمتر نگه دارید.',
        ],
    ],
    'table' => [
        'id' => 'شناسه',
        'shipment_no' => 'شماره محموله',
        'registered_order' => 'مرتبط با ',
        'carrier' => 'حمل‌کننده',
        'part' => 'پارت',
        'bl_number' => 'شماره بارنامه',
        'booking_no' => 'شماره بوکینگ',
        'container_no' => 'تعداد کانتینر',
        'container_status' => 'وضعیت کانتینر',
        'eta' => 'تاریخ ورود',
        'status' => 'وضعیت',
        'tracking_status' => 'رهگیری',
        'created_by' => 'ایجادکننده',
        'created_at' => 'تاریخ ایجاد',
        'updated_by' => 'ویرایش‌کننده',
        'updated_at' => 'تاریخ بروزرسانی',
    ],
    'filters' => [
        'created_from' => 'ایجاد از تاریخ',
        'created_until' => 'ایجاد تا تاریخ',
        'eta_from' => 'تاریخ ورود از',
        'eta_until' => 'تاریخ ورود تا',
    ],
    'infolist' => [
        'tab_general' => 'اطلاعات عمومی',
        'tab_logistics' => 'لجستیک',
        'tab_docs' => 'اسناد',
        'tab_documents' => 'پیوستها',
    ]
];
