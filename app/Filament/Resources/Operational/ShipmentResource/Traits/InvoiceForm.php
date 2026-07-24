<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use App\Models\EntityAttribute;
use App\Models\ProformaInvoice;
use App\Models\Shipment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

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
                                                $pi->id => ($pi->invoice_no ?? '—').' — '.($pi->invoice_date?->format('Y-m-d') ?? ''),
                                            ]);
                                    })
                                    ->live()
                                    ->dehydrated(false)
                                    ->columnSpanFull()
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
                                        $set('_inv_incoterms', $pi->delivery_terms);
                                        $set('_inv_port_of_loading', $pi->port_of_loading);
                                        $set('_inv_port_of_discharge', $pi->port_of_discharge);
                                        $set('_inv_origin_country', $pi->origin_country);
                                        $set('_inv_destination_country', $pi->destination_country);
                                        $set('_inv_discount', (float) ($pi->discount ?? 0));
                                        $set('_inv_freight_charges', (float) ($pi->freight_charges ?? 0));
                                        $set('_inv_other_charges', (float) ($pi->other_charges ?? 0));

                                        $items = $pi->items->map(fn ($item, $i) => [
                                            'row_num' => $i + 1,
                                            'description' => $item->description,
                                            'english_description' => $item->english_description ?? $item->product?->english_name,
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
                                        $set('_inv_subtotal', round($subtotal, 2));
                                        $set('_inv_grand_total', round($subtotal - $discount + $freight + $other, 2));
                                        $set('_inv_total_net_weight', round($pi->items->sum(fn ($i) => (float) ($i->net_weight ?? 0)), 2));
                                        $set('_inv_total_gross_weight', round($pi->items->sum(fn ($i) => (float) ($i->gross_weight ?? 0)), 2));
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
                                        TextInput::make('description')
                                            ->label(__('resources/shipment/strings.invoice.item_description'))
                                            ->columnSpan(3),
                                        TextInput::make('english_description')
                                            ->label(__('resources/shipment/strings.invoice.item_english_description'))
                                            ->columnSpan(3),
                                        TextInput::make('hs_code')
                                            ->label(__('resources/shipment/strings.invoice.item_hs_code'))
                                            ->columnSpan(2),
                                        TextInput::make('origin')
                                            ->label(__('resources/shipment/strings.invoice.item_origin'))
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
                                                $set('total_amount', round((float) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0), 2));
                                            })
                                            ->columnSpan(2),
                                        TextInput::make('unit')
                                            ->label(__('resources/shipment/strings.invoice.item_unit'))
                                            ->columnSpan(1),
                                        TextInput::make('unit_price')
                                            ->label(__('resources/shipment/strings.invoice.item_unit_price'))
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                            ])
                                            ->validationAttribute(__('resources/shipment/strings.invoice.item_unit_price'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                $set('total_amount', round((float) ($get('quantity') ?? 0) * (float) ($get('unit_price') ?? 0), 2));
                                            })
                                            ->columnSpan(2),
                                        TextInput::make('total_amount')
                                            ->label(__('resources/shipment/strings.invoice.item_total'))
                                            ->numeric()
                                            ->validationMessages([
                                                'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                            ])
                                            ->validationAttribute(__('resources/shipment/strings.invoice.item_total'))
                                            ->readOnly()
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
                                            ->columnSpan(2),
                                    ])
                                    ->addActionLabel(__('resources/shipment/strings.invoice.add_item'))
                                    ->columns(12)
                                    ->columnSpanFull()
                                    ->reorderable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => ($state['english_description'] ?? $state['description'] ?? null)),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make(__('resources/shipment/strings.invoice.section_shipment'))
                            ->schema([
                                Select::make('_inv_transport_mode')
                                    ->label(__('resources/shipment/strings.invoice.transport_mode'))
                                    ->options(__('resources/shipment/strings.invoice.transport_options'))
                                    ->dehydrated(false),
                                Select::make('_inv_incoterms')
                                    ->label(__('resources/shipment/strings.invoice.incoterms'))
                                    ->options(__('resources/shipment/strings.invoice.incoterms_options'))
                                    ->dehydrated(false),
                                TextInput::make('_inv_port_of_loading')
                                    ->label(__('resources/shipment/strings.invoice.port_of_loading'))
                                    ->dehydrated(false),
                                TextInput::make('_inv_port_of_discharge')
                                    ->label(__('resources/shipment/strings.invoice.port_of_discharge'))
                                    ->dehydrated(false),
                                TextInput::make('_inv_origin_country')
                                    ->label(__('resources/shipment/strings.invoice.origin_country'))
                                    ->dehydrated(false),
                                TextInput::make('_inv_destination_country')
                                    ->label(__('resources/shipment/strings.invoice.destination_country'))
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
                                    ->hint(fn (Get $get) => delimiter($get('_inv_subtotal'))),
                                TextInput::make('_inv_discount')
                                    ->label(__('resources/shipment/strings.invoice.discount'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.discount'))
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->hint(fn (Get $get) => delimiter($get('_inv_discount'))),
                                TextInput::make('_inv_freight_charges')
                                    ->label(__('resources/shipment/strings.invoice.freight_charges'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.freight_charges'))
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->hint(fn (Get $get) => delimiter($get('_inv_freight_charges'))),
                                TextInput::make('_inv_other_charges')
                                    ->label(__('resources/shipment/strings.invoice.other_charges'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.other_charges'))
                                    ->dehydrated(false)
                                    ->default(0)
                                    ->hint(fn (Get $get) => delimiter($get('_inv_other_charges'))),
                                TextInput::make('_inv_grand_total')
                                    ->label(__('resources/shipment/strings.invoice.grand_total'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.grand_total'))
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->hint(fn (Get $get) => delimiter($get('_inv_grand_total'))),
                                TextInput::make('_inv_total_net_weight')
                                    ->label(__('resources/shipment/strings.invoice.total_net_weight'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.total_net_weight'))
                                    ->dehydrated(false),
                                TextInput::make('_inv_total_gross_weight')
                                    ->label(__('resources/shipment/strings.invoice.total_gross_weight'))
                                    ->numeric()
                                    ->validationMessages([
                                        'numeric' => __('resources/shipment/strings.form.validation.numeric'),
                                    ])
                                    ->validationAttribute(__('resources/shipment/strings.invoice.total_gross_weight'))
                                    ->dehydrated(false),
                            ])->columns(1),

                        Section::make(__('resources/shipment/strings.invoice.section_notes'))
                            ->schema([
                                Textarea::make('_inv_notes')
                                    ->hiddenLabel()
                                    ->rows(4)
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                            ])
                            ->footerActions([
                                Action::make('saveInvoice')
                                    ->label(__('resources/shipment/strings.invoice.action_save'))
                                    ->icon('heroicon-o-cloud-arrow-up')
                                    ->color('success')
                                    ->tooltip(__('resources/shipment/strings.invoice.action_save_tooltip'))
                                    ->action(function (Get $get, $record) {
                                        if (! $record?->id) {
                                            Notification::make()
                                                ->title(__('resources/shipment/strings.invoice.no_record_notification'))
                                                ->warning()
                                                ->send();

                                            return;
                                        }
                                        static::persistInvoiceToEav($get, $record);
                                        Notification::make()
                                            ->title(__('resources/shipment/strings.invoice.saved_notification'))
                                            ->success()
                                            ->send();
                                    }),

                                Action::make('printInvoice')
                                    ->label(__('resources/shipment/strings.invoice.action_print'))
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('info')
                                    ->tooltip(__('resources/shipment/strings.invoice.action_print_tooltip'))
                                    ->action(function (Get $get, $record) {
                                        if (! $record?->id) {
                                            Notification::make()
                                                ->title(__('resources/shipment/strings.invoice.no_record_notification'))
                                                ->warning()
                                                ->send();

                                            return;
                                        }
                                        static::persistInvoiceToEav($get, $record);
                                    })
                                    ->url(fn ($record) => $record?->id
                                        ? route('shipments.invoice.pdf', ['shipment' => $record->id])
                                        : null
                                    )
                                    ->openUrlInNewTab(),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function persistInvoiceToEav(Get $get, $record): void
    {
        $items = collect($get('_inv_items') ?? [])->map(fn ($item) => [
            'description' => $item['description'] ?? null,
            'english_description' => $item['english_description'] ?? null,
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
        $discount = (float) ($get('_inv_discount') ?? 0);
        $freight = (float) ($get('_inv_freight_charges') ?? 0);
        $other = (float) ($get('_inv_other_charges') ?? 0);

        $data = [
            'proforma_invoice_id' => $get('_inv_pi_id'),
            'invoice_no' => $get('_inv_invoice_no'),
            'invoice_date' => $get('_inv_invoice_date'),
            'seller_name' => $get('_inv_seller_name'),
            'seller_address' => $get('_inv_seller_address'),
            'buyer_name' => $get('_inv_buyer_name'),
            'buyer_address' => $get('_inv_buyer_address'),
            'buyer_comm_card_no' => $get('_inv_buyer_comm_card_no'),
            'currency' => $get('_inv_currency'),
            'payment_terms' => $get('_inv_payment_terms'),
            'transport_mode' => $get('_inv_transport_mode'),
            'incoterms' => $get('_inv_incoterms'),
            'port_of_loading' => $get('_inv_port_of_loading'),
            'port_of_discharge' => $get('_inv_port_of_discharge'),
            'origin_country' => $get('_inv_origin_country'),
            'destination_country' => $get('_inv_destination_country'),
            'bl_number' => $get('_inv_bl_number'),
            'etd' => $get('_inv_etd'),
            'eta' => $get('_inv_eta'),
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'discount' => $discount,
            'freight_charges' => $freight,
            'other_charges' => $other,
            'grand_total' => round($subtotal - $discount + $freight + $other, 2),
            'total_net_weight' => (float) ($get('_inv_total_net_weight') ?? 0),
            'total_gross_weight' => (float) ($get('_inv_total_gross_weight') ?? 0),
            'notes' => $get('_inv_notes'),
        ];

        $attr = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $record->id)
            ->where('key', 'commercial_invoice')
            ->first();

        if ($attr) {
            $attr->update(['value' => $data, 'updated_by_id' => auth()->id()]);
        } else {
            EntityAttribute::create([
                'entity_type' => Shipment::class,
                'entity_id' => $record->id,
                'key' => 'commercial_invoice',
                'value' => $data,
                'user_id' => auth()->id(),
                'updated_by_id' => auth()->id(),
            ]);
        }
    }
}
