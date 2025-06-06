<?php

return [
    'general' => [
        'model_label' => 'Product',
        'plural_model_label' => 'Products',
        'navigation_group' => 'Master Data',
    ],
    'form' => [
        'name' => 'Name (Persian)',
        'english_name' => 'Name (English)',
        'slug' => 'Slug',
        'description' => 'Description',
        'code' => 'Code',
        'in_stock' => 'In Stock',
        'category' => 'Category',
        'attributes' => 'Attributes',
        'creator' => 'Created By',
        'updater' => 'Last Updated By',
        'validation_name' => 'Only Persian characters are allowed.',
        'validation_name_unique' => 'This Persian name already exists.',
        'validation_name_required' => 'Please enter the product’s name.',
        'validation_name_placeholder' => 'Enter Persian name',
        'validation_english_name' => 'Only English letters, numbers, spaces, and hyphens are allowed.',
        'validation_english_name_unique' => 'This English name already exists.',
        'validation_english_name_required' => 'Please enter the product’s English name.',
        'validation_english_name_placeholder' => 'Enter English name',
        'validation_code_unique' => 'This code already exists.',
        'validation_code_required' => 'This code must be entered.',
        'validation_code_placeholder' => 'Enter the product code',
        'classify_by_name' => 'Activate custom naming',
    ],
    'table' => [
        'name' => 'Name (Persian)',
        'english_name' => 'Name (English)',
        'code' => 'Code',
        'attributes' => 'Specifications',
        'in_stock' => 'In Stock',
        'in_stock_true' => 'Yes',
        'in_stock_false' => 'No',
        'category' => 'Category',
        'creator' => 'Created By',
        'updater' => 'Last Updated By',
        'created_at' => 'Date Created',
        'updated_at' => 'Last Updated',
        'deleted_at' => 'Date Deleted',
    ],
    'filters' => [
        'category' => 'Category',
        'category_placeholder' => 'Select category…',
        'in_stock' => 'Stock Status',
        'code' => 'Code',
        'code_placeholder' => 'Enter code',
    ],
    'attributes_manager' => [
        'key' => 'Attribute Key',
        'value' => 'Attribute Value',
        'brand' => 'Brand',
        'purity' => 'Purity',
        'length_mm' => 'Length (mm)',
        'width_mm' => 'Width (mm)',
        'thickness_mm' => 'Thickness (mm)',
        'finish' => 'Finish',
        'edge_profile' => 'Edge Profile',
        'size' => 'Size',
        'length_cm' => 'Length (cm)',
        'weight' => 'Weight',
        'packaging' => 'Packaging',
    ],
];
