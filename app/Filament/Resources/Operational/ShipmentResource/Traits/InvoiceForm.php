<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Models\EntityAttribute;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Country;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait InvoiceForm
{
    public static function getInvoiceFormTab(): Tab
    {
        return Tab::make(__('resources/shipment/strings.invoice.tab_label'))
            ->icon('heroicon-o-document-text')
            ->schema([
                Group::make()
                    ->schema([
                        Section::make(__('resources/shipment/strings.invoice.section_pi_selector'))
                            ->schema([
                                Select::make('_inv_pi_id')
                                    ->label(__('resources/shipment/strings.invoice.pi_selector'))
                                    ->helperText(__('resources/shipment/strings.invoice.pi_selector_hint'))
                                    ->options(function (Get $get) {
                                        $roId = $get('registered_order_id');
                                        if (! $roId) {
                                            return [];
                                        }

                                        return ProformaInvoice::whereHas(
                                            'registeredOrders',
                                            fn ($q) => $q->where('registered_orders.id', $roId)
                                        )
                                            ->get()
                                            ->mapWithKeys(fn ($pi) => [
                                                $pi->id => ($pi->invoice_no ?? '—').' — '.($pi->invoice_date ? adaptiveDate($pi->invoice_date) : ''),
                                            ]);
                                    })
                                    ->live()
                                    ->dehydrated(false)
                                    ->columnSpanFull()
                                    ->validationMessages([
                                        'in' => __('resources/shipment/strings.form.validation.in'),
                                    ])
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (! $state) {
                                            return;
                                        }
                                        $pi = ProformaInvoice::with(['sellerCompany', 'buyerCompany', 'mainCurrency', 'items.product'])
                                            ->find($state);
                                        if (! $pi) {
                                            return;
                                        }

                                        $set('_inv_invoice_no', $pi->invoice_no);
                                        $set('_inv_invoice_date', $pi->invoice_date?->format('Y-m-d'));
                                        $set('_inv_seller_name', $pi->sellerCompany?->english_name ?? $pi->sellerCompany?->name);
                                        $set('_inv_buyer_name', $pi->buyerCompany?->english_name ?? $pi->buyerCompany?->name);
                                        $set('_inv_buyer_comm_card_no', $pi->buyer_comm_card_num);
                                        $set('_inv_currency', $pi->mainCurrency?->english_name ?? $pi->mainCurrency?->name);
                                        $set('_inv_transport_mode', $pi->transport_mode);
                                        $set('_inv_incoterms', $pi->delivery_terms ? strtoupper($pi->delivery_terms) : null);
                                        $set('_inv_port_of_loading', $pi->port_of_loading);
                                        $set('_inv_port_of_discharge', $pi->port_of_discharge);
                                        $set('_inv_origin_country', $pi->origin_country);
                                        $set('_inv_destination_country', $pi->destination_country);
                                        $set('_inv_discount', (float) ($pi->discount ?? 0));
                                        $set('_inv_freight_charges', (float) ($pi->freight_charges ?? 0));
                                        $set('_inv_other_charges', (float) ($pi->other_charges ?? 0));

                                        $items = $pi->items->map(fn ($item, $i) => [
                                            'row_num' => $i + 1,
                                            'product_id' => $item->product_id,
                                            'name' => $item->product?->name,
                                            'english_name' => $item->product?->english_name,
                                            'hs_code' => $item->hs_code,
                                            'origin' => $item->origin,
                                            'quantity' => (float) $item->quantity,
                                            'unit' => $item->unit,
                                            'unit_price' => (float) $item->unit_price,
                                            'total_amount' => (float) $item->total_amount,
                                            'net_weight' => (float) ($item->net_weight ?? 0),
                                            'gross_weight' => (float) ($item->gross_weight ?? 0),
                                        ])->values()->all();
                                        $set('_inv_items', $items);

                                        $subtotal = $pi->items->sum(fn ($i) => (float) $i->total_amount);
                                        $discount = (float) ($pi->discount ?? 0);
                                        $freight = (float) ($pi->freight_charges ?? 0);
                                        $other = (float) ($pi->other_charges ?? 0);
                                        $set('_inv_subtotal', round($subtotal, 5));
                                        $set('_inv_grand_total', round($subtotal - $discount + $freight + $other, 5));
                                        $set('_inv_total_net_weight', round($pi->items->sum(fn ($i) => (float) ($i->net_weight ?? 0)), 5));
                                        $set('_inv_total_gross_weight', round($pi->items->sum(fn ($i) => (float) ($i->gross_weight ?? 0)), 5));
                                    }),
                            ]),

                        Section::make(__('resources/shipment/strings.invoice.section_parties'))
                            ->schema([
                                TextInput::make('_inv_invoice_no')
                                    ->label(__('resources/shipment/strings.invoice.invoice_no'))
                                    ->dehydrated(false),
                                maybeJalali(
                                    DatePicker::make('_inv_invoice_date')
                                        ->label(__('resources/shipment/strings.invoice.invoice_date'))
                                        ->native(false)
                                        ->adaptive()
                                        ->validationMessages([
                                            'date' => __('resources/shipment/strings.form.validation.date'),
                                        ])
                                        ->dehydrated(false)
                                ),
                                TextInput::make('_inv_seller_name')
                                    ->label(__('resources/shipment/strings.invoice.seller_name'))
                                    ->dehydrated(false),
                                Textarea::make('_inv_seller_address')
                                    ->label(__('resources/shipment/strings.invoice.seller_address'))
                                    ->rows(2)
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                                TextInput::make('_inv_buyer_name')
                                    ->label(__('resources/shipment/strings.invoice.buyer_name'))
                                    ->dehydrated(false),
                                TextInput::make('_inv_buyer_comm_card_no')
                                    ->label(__('resources/shipment/strings.invoice.buyer_comm_card_no'))
                                    ->dehydrated(false),
                                Textarea::make('_inv_buyer_address')
                                    ->label(__('resources/shipment/strings.invoice.buyer_address'))
                                    ->rows(2)
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                                TextInput::make('_inv_currency')
                                    ->label(__('resources/shipment/strings.invoice.currency'))
                                    ->dehydrated(false),
                                TextInput::make('_inv_payment_terms')
                                    ->label(__('resources/shipment/strings.invoice.payment_terms'))
                                    ->dehydrated(false),
                            ])->columns(2),

                        Section::make(__('resources/shipment/strings.invoice.section_items'))
                            ->schema([
                                Repeater::make('_inv_items')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->schema([
                                        Hidden::make('product_id'),
                                        ...static::invoiceItemNameFields(),
                                        TextInput::make('hs_code')
                                            ->label(__('resources/shipment/strings.invoice.item_hs_code'))
                                            ->columnSpan(2),
                                        Select::make('origin')
                                            ->label(__('resources/shipment/strings.invoice.item_origin'))
                                            ->options(fn (Get $get) => static::optionsWithCurrent((new Country)->getCountriesList(), $get('origin')))
                                            ->placeholder('')
                                            ->searchable()
                                            ->columnSpan(2),
                                        TextInput::make('quantity')
                                            ->label(__('resources/shipment/strings.invoice.item_qty'))
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                            ])
                                            ->validationAttribute(__('resources/shipment/strings.invoice.item_qty'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                $set('total_amount', round((float) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0), 5));
                                            })
                                            ->columnSpan(2),
                                        Select::make('unit')
                                            ->label(__('resources/shipment/strings.invoice.item_unit'))
                                            ->options(fn (Get $get) => static::optionsWithCurrent(__('resources/general/strings.metrics'), $get('unit')))
                                            ->placeholder('')
                                            ->columnSpan(2),
                                        TextInput::make('unit_price')
                                            ->label(__('resources/shipment/strings.invoice.item_unit_price'))
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                            ])
                                            ->validationAttribute(__('resources/shipment/strings.invoice.item_unit_price'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                $set('total_amount', round((float) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0), 5));
                                            })
                                            ->columnSpan(2),
                                        TextInput::make('net_weight')
                                            ->label(__('resources/shipment/strings.invoice.item_net_weight'))
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                            ])
                                            ->validationAttribute(__('resources/shipment/strings.invoice.item_net_weight'))
                                            ->columnSpan(2),
                                        TextInput::make('gross_weight')
                                            ->label(__('resources/shipment/strings.invoice.item_gross_weight'))
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                            ])
                                            ->validationAttribute(__('resources/shipment/strings.invoice.item_gross_weight'))
                                            ->rules([
                                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                                    if (filled($value) && filled($get('net_weight')) && (float) $value < (float) $get('net_weight')) {
                                                        $fail(__('resources/shipment/strings.form.validation.gross_below_net'));
                                                    }
                                                },
                                            ])
                                            ->columnSpan(2),
                                        TextInput::make('total_amount')
                                            ->label(__('resources/shipment/strings.invoice.item_total'))
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                            ])
                                            ->validationAttribute(__('resources/shipment/strings.invoice.item_total'))
                                            ->readOnly()
                                            ->columnSpan(4),
                                    ])
                                    ->addActionLabel(__('resources/shipment/strings.invoice.add_item'))
                                    ->columns(10)
                                    ->columnSpanFull()
                                    ->reorderable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => ($state['english_name'] ?? $state['name'] ?? null)),
                            ]),

                        Section::make(__('resources/shipment/strings.invoice.section_notes'))
                            ->schema([
                                Textarea::make('_inv_notes')
                                    ->hiddenLabel()
                                    ->rows(4)
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make(__('resources/shipment/strings.invoice.section_shipment'))
                            ->schema([
                                Select::make('_inv_transport_mode')
                                    ->label(__('resources/shipment/strings.invoice.transport_mode'))
                                    ->options(__('resources/shipment/strings.invoice.transport_options'))
                                    ->validationMessages([
                                        'in' => __('resources/shipment/strings.form.validation.in'),
                                    ])
                                    ->dehydrated(false),
                                Select::make('_inv_incoterms')
                                    ->label(__('resources/shipment/strings.invoice.incoterms'))
                                    ->options(__('resources/shipment/strings.invoice.incoterms_options'))
                                    ->validationMessages([
                                        'in' => __('resources/shipment/strings.form.validation.in'),
                                    ])
                                    ->dehydrated(false),
                                TextInput::make('_inv_port_of_loading')
                                    ->label(__('resources/shipment/strings.invoice.port_of_loading'))
                                    ->dehydrated(false),
                                TextInput::make('_inv_port_of_discharge')
                                    ->label(__('resources/shipment/strings.invoice.port_of_discharge'))
                                    ->dehydrated(false),
                                Select::make('_inv_origin_country')
                                    ->label(__('resources/shipment/strings.invoice.origin_country'))
                                    ->options(fn (Get $get) => static::optionsWithCurrent((new Country)->getCountriesList(), $get('_inv_origin_country')))
                                    ->searchable()
                                    ->dehydrated(false),
                                Select::make('_inv_destination_country')
                                    ->label(__('resources/shipment/strings.invoice.destination_country'))
                                    ->options(fn (Get $get) => static::optionsWithCurrent((new Country)->getCountriesList(), $get('_inv_destination_country')))
                                    ->searchable()
                                    ->dehydrated(false),
                                TextInput::make('_inv_bl_number')
                                    ->label(__('resources/shipment/strings.invoice.bl_number'))
                                    ->dehydrated(false),
                                maybeJalali(
                                    DatePicker::make('_inv_etd')
                                        ->label(__('resources/shipment/strings.invoice.etd'))
                                        ->native(false)
                                        ->adaptive()
                                        ->validationMessages([
                                            'date' => __('resources/shipment/strings.form.validation.date'),
                                        ])
                                        ->dehydrated(false)
                                ),
                                maybeJalali(
                                    DatePicker::make('_inv_eta')
                                        ->label(__('resources/shipment/strings.invoice.eta'))
                                        ->native(false)
                                        ->adaptive()
                                        ->validationMessages([
                                            'date' => __('resources/shipment/strings.form.validation.date'),
                                        ])
                                        ->dehydrated(false)
                                ),
                            ])->columns(1),

                        Section::make(__('resources/shipment/strings.invoice.section_totals'))
                            ->schema([
                                TextInput::make('_inv_subtotal')
                                    ->label(__('resources/shipment/strings.invoice.subtotal'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.subtotal'))
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->hint(fn (Get $get) => preciseNumber($get('_inv_subtotal'))),
                                TextInput::make('_inv_discount')
                                    ->label(__('resources/shipment/strings.invoice.discount'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.discount'))
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->hint(fn (Get $get) => preciseNumber($get('_inv_discount'))),
                                TextInput::make('_inv_freight_charges')
                                    ->label(__('resources/shipment/strings.invoice.freight_charges'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.freight_charges'))
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->hint(fn (Get $get) => preciseNumber($get('_inv_freight_charges'))),
                                TextInput::make('_inv_other_charges')
                                    ->label(__('resources/shipment/strings.invoice.other_charges'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.other_charges'))
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->hint(fn (Get $get) => preciseNumber($get('_inv_other_charges'))),
                                TextInput::make('_inv_grand_total')
                                    ->label(__('resources/shipment/strings.invoice.grand_total'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.grand_total'))
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->hint(fn (Get $get) => preciseNumber($get('_inv_grand_total'))),
                                TextInput::make('_inv_total_net_weight')
                                    ->label(__('resources/shipment/strings.invoice.total_net_weight'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.total_net_weight'))
                                    ->readOnly()
                                    ->dehydrated(false),
                                TextInput::make('_inv_total_gross_weight')
                                    ->label(__('resources/shipment/strings.invoice.total_gross_weight'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.total_gross_weight'))
                                    ->readOnly()
                                    ->dehydrated(false),
                            ])->columns(1),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    protected static function invoiceItemNameFields(): array
    {
        $farsi = app()->getLocale() === 'fa';

        return [
            Hidden::make($farsi ? 'english_name' : 'name'),
            TextInput::make($farsi ? 'name' : 'english_name')
                ->label(__($farsi ? 'resources/shipment/strings.invoice.item_name' : 'resources/shipment/strings.invoice.item_english_name'))
                ->columnSpan(6),
        ];
    }

    public static function buildInvoiceEavData(array $formData): array
    {
        $productIds = Product::withTrashed()->whereIn('id', collect($formData['_inv_items'] ?? [])->pluck('product_id')->filter()->all())->pluck('id')->all();

        $items = collect($formData['_inv_items'] ?? [])->map(fn ($item) => [
            'product_id' => in_array((int) ($item['product_id'] ?? 0), $productIds, true) ? (int) $item['product_id'] : null,
            'name' => $item['name'] ?? null,
            'english_name' => $item['english_name'] ?? null,
            'hs_code' => $item['hs_code'] ?? null,
            'origin' => $item['origin'] ?? null,
            'quantity' => (float) ($item['quantity'] ?? 0),
            'unit' => $item['unit'] ?? null,
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'total_amount' => (float) ($item['total_amount'] ?? 0),
            'net_weight' => (float) ($item['net_weight'] ?? 0),
            'gross_weight' => (float) ($item['gross_weight'] ?? 0),
        ])->values()->all();

        $subtotal = array_sum(array_column($items, 'total_amount'));
        $discount = (float) ($formData['_inv_discount'] ?? 0);
        $freight = (float) ($formData['_inv_freight_charges'] ?? 0);
        $other = (float) ($formData['_inv_other_charges'] ?? 0);

        return [
            'proforma_invoice_id' => $formData['_inv_pi_id'] ?? null,
            'invoice_no' => $formData['_inv_invoice_no'] ?? null,
            'invoice_date' => $formData['_inv_invoice_date'] ?? null,
            'seller_name' => $formData['_inv_seller_name'] ?? null,
            'seller_address' => $formData['_inv_seller_address'] ?? null,
            'buyer_name' => $formData['_inv_buyer_name'] ?? null,
            'buyer_address' => $formData['_inv_buyer_address'] ?? null,
            'buyer_comm_card_no' => $formData['_inv_buyer_comm_card_no'] ?? null,
            'currency' => $formData['_inv_currency'] ?? null,
            'payment_terms' => $formData['_inv_payment_terms'] ?? null,
            'transport_mode' => $formData['_inv_transport_mode'] ?? null,
            'incoterms' => $formData['_inv_incoterms'] ?? null,
            'port_of_loading' => $formData['_inv_port_of_loading'] ?? null,
            'port_of_discharge' => $formData['_inv_port_of_discharge'] ?? null,
            'origin_country' => $formData['_inv_origin_country'] ?? null,
            'destination_country' => $formData['_inv_destination_country'] ?? null,
            'bl_number' => $formData['_inv_bl_number'] ?? null,
            'etd' => $formData['_inv_etd'] ?? null,
            'eta' => $formData['_inv_eta'] ?? null,
            'items' => $items,
            'subtotal' => round($subtotal, 5),
            'discount' => $discount,
            'freight_charges' => $freight,
            'other_charges' => $other,
            'grand_total' => round($subtotal - $discount + $freight + $other, 5),
            'total_net_weight' => round(array_sum(array_column($items, 'net_weight')), 5),
            'total_gross_weight' => round(array_sum(array_column($items, 'gross_weight')), 5),
            'notes' => $formData['_inv_notes'] ?? null,
        ];
    }

    public static function persistInvoice(array $formData, Shipment $record): void
    {
        foreach (array_values($formData['_inv_items'] ?? []) as $index => $item) {
            if (filled($item['gross_weight'] ?? null) && filled($item['net_weight'] ?? null) && (float) $item['gross_weight'] < (float) $item['net_weight']) {
                throw ValidationException::withMessages(["data._inv_items.{$index}.gross_weight" => __('resources/shipment/strings.form.validation.gross_below_net')]);
            }
        }

        $data = static::buildInvoiceEavData($formData);

        DB::transaction(function () use ($data, $record) {
            $attr = EntityAttribute::withTrashed()
                ->where('entity_type', Shipment::class)
                ->where('entity_id', $record->id)
                ->where('key', 'commercial_invoice')
                ->first();

            $header = Arr::except($data, ['items']);

            if ($attr) {
                $attr->trashed() && $attr->restore();
                $attr->update(['value' => $header, 'updated_by_id' => auth()->id()]);
            } else {
                EntityAttribute::create([
                    'entity_type' => Shipment::class,
                    'entity_id' => $record->id,
                    'key' => 'commercial_invoice',
                    'value' => $header,
                    'user_id' => auth()->id(),
                    'updated_by_id' => auth()->id(),
                ]);
            }

            $record->items()->delete();
            $record->items()->createMany($data['items']);
            $record->update(Arr::only($data, ['total_net_weight', 'total_gross_weight', 'grand_total']));
        });
    }

    public static function loadInvoiceData(Shipment $shipment): ?array
    {
        $header = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $shipment->id)
            ->where('key', 'commercial_invoice')
            ->value('value');

        $header = is_string($header) ? json_decode($header, true) : $header;

        if (! is_array($header)) {
            return null;
        }

        $rows = $shipment->items()->orderBy('id')->get();

        $header['items'] = $rows->isEmpty()
            ? collect($header['items'] ?? [])->filter(fn ($line) => is_array($line))->values()->map(fn ($line) => Arr::except($line, ['description', 'english_description']) + [
                'name' => $line['name'] ?? $line['description'] ?? null,
                'english_name' => $line['english_name'] ?? $line['english_description'] ?? null,
            ])->all()
            : $rows->map(fn (ShipmentItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->name,
                'english_name' => $item->english_name,
                'hs_code' => $item->hs_code,
                'origin' => $item->origin,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'total_amount' => (float) $item->total_amount,
                'net_weight' => (float) $item->net_weight,
                'gross_weight' => (float) $item->gross_weight,
            ])->all();

        foreach (['total_net_weight', 'total_gross_weight', 'grand_total'] as $column) {
            $header[$column] = $shipment->{$column} !== null ? (float) $shipment->{$column} : ($header[$column] ?? 0);
        }

        return static::normalizeInvoiceKeys($header);
    }

    public static function normalizeInvoiceKeys(array $invoice): array
    {
        $countries = collect((new Country)->all())->mapWithKeys(fn (array $country) => [$country['code'] => [$country['name'], $country['name_english']]])->all();
        $units = [];

        foreach (['en', 'fa', 'fr'] as $locale) {
            foreach (trans('resources/general/strings.metrics', [], $locale) as $key => $label) {
                $units[$key][] = $label;
            }
        }

        foreach (['origin_country', 'destination_country'] as $field) {
            $invoice[$field] = static::matchOptionKey($invoice[$field] ?? null, $countries);
        }

        $invoice['incoterms'] = is_string($invoice['incoterms'] ?? null) ? strtoupper($invoice['incoterms']) : ($invoice['incoterms'] ?? null);
        $invoice['items'] = array_map(fn (array $item) => [
            ...$item,
            'origin' => static::matchOptionKey($item['origin'] ?? null, $countries),
            'unit' => static::matchOptionKey($item['unit'] ?? null, $units),
        ], $invoice['items'] ?? []);

        return $invoice;
    }

    protected static function optionsWithCurrent(array $options, mixed $current): array
    {
        return is_string($current) && $current !== '' && ! isset($options[$current]) ? $options + [$current => $current] : $options;
    }

    protected static function matchOptionKey(mixed $value, array $candidates): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $needle = mb_strtolower(trim($value));

        foreach ($candidates as $key => $labels) {
            if (mb_strtolower((string) $key) === $needle || in_array($needle, array_map('mb_strtolower', $labels), true)) {
                return $key;
            }
        }

        return $value;
    }
}
