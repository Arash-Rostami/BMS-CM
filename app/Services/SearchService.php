<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class SearchService
{
    private const THEME = [
        'blue' => 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20',
        'green' => 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-500/20',
        'yellow' => 'bg-yellow-50 dark:bg-yellow-500/10 text-yellow-800 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-500/20',
        'red' => 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-500/20',
        'slate' => 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-white/10',
    ];

    private const PIPELINE = [
        'purchaseRequest', 'proformaInvoice', 'purchaseOrder', 'registeredOrder',
        'bankProfile', 'payment', 'shipment', 'custom',
    ];

    public function emptyResponse(): array
    {
        return [
            'results' => [],
            'breadcrumb' => $this->buildBreadcrumb([]),
            'by_user' => null,
        ];
    }

    public function search(string $term): array
    {
        $escaped = addcslashes($term, '%_\\');
        $byUser = User::select(['id', 'name'])->where('name', 'like', "%{$escaped}%")->first();

        $results = [];
        $foundTypes = [];

        foreach ($this->registry() as $key => $cfg) {
            $base = (new $cfg['model'])->newQuery()->with($cfg['with']);

            $record = (clone $base)
                ->where(function ($q) use ($cfg, $escaped) {
                    foreach ($cfg['search'] as $col) {
                        $q->orWhere($col, 'like', "%{$escaped}%");
                    }
                })
                ->latest()
                ->first();

            if (! $record && $byUser && ($cfg['by_user'] ?? false)) {
                $record = (clone $base)->where('user_id', $byUser->id)->latest()->first();
            }

            if (! $record) {
                continue;
            }

            $foundTypes[] = $key;
            $results[] = $this->buildResult($key, $cfg, $record);
        }

        return [
            'results' => $results,
            'breadcrumb' => $this->buildBreadcrumb($foundTypes),
            'by_user' => $byUser ? ['id' => $byUser->id, 'name' => $byUser->name] : null,
        ];
    }

    public function chain(string $type, int $id): array
    {
        $registry = $this->registry();
        $meta = $this->chainMeta();

        if (! in_array($type, self::PIPELINE, true) || ! isset($registry[$type], $meta[$type])) {
            return ['anchor' => null, 'chain' => []];
        }

        $anchor = $registry[$type]['model']::find($id);
        if (! $anchor) {
            return ['anchor' => null, 'chain' => []];
        }

        $ctx = [
            'roIds' => $this->resolveRoIds($anchor, $meta[$type]['ros']),
            'poIds' => [],
            'shipmentIds' => [],
        ];

        $chain = [];
        $refs = ['status' => [], 'company' => [], 'bank' => [], 'currency' => []];

        foreach (self::PIPELINE as $key) {
            $records = $meta[$key]['fetch']($ctx)->get();

            if ($key === 'purchaseOrder') {
                $ctx['poIds'] = $records->modelKeys();
            }
            if ($key === 'shipment') {
                $ctx['shipmentIds'] = $records->modelKeys();
            }

            if ($key === $type && ! $records->contains(fn ($r) => $r->id === $anchor->id)) {
                $records->push($anchor);
            }

            $entry = [
                'key' => $key,
                'label' => $registry[$key]['label'],
                'icon' => $registry[$key]['icon'],
                'color' => $registry[$key]['color'],
                'attached' => $records->isNotEmpty(),
                'records' => [],
            ];

            foreach ($records as $r) {
                $entry['records'][] = $this->buildChainRecord($key, $registry[$key], $meta[$key], $r, $refs);
            }

            $chain[] = $entry;
        }

        $statusMap = $this->resolveMap(Status::class, $refs['status'], fn ($s) => $s->localized_name);
        $companyMap = $this->resolveMap(Company::class, $refs['company'], fn ($c) => $c->localized_name);
        $bankMap = $this->resolveMap(Bank::class, $refs['bank'], fn ($b) => $b->localized_name);
        $currencyMap = $this->resolveMap(Currency::class, $refs['currency'], fn ($c) => $c->localized_name);

        foreach ($chain as &$entry) {
            foreach ($entry['records'] as &$rec) {
                $rec['statuses'] = array_map(
                    fn ($s) => ['label' => $s['label'], 'value' => $s['id'] ? ($statusMap[$s['id']] ?? null) : null],
                    $rec['statuses'],
                );
                foreach ($rec['extras'] as &$ex) {
                    $ex['value'] = $this->resolveExtra($ex, $companyMap, $bankMap, $currencyMap);
                    unset($ex['type'], $ex['currency_id'], $ex['ref']);
                }
            }
        }
        unset($entry, $rec, $ex);

        $breadcrumb = [];
        foreach ($chain as $entry) {
            $breadcrumb[$entry['key']] = [
                'state' => $entry['attached'] ? 'completed' : 'missing',
                'label' => $entry['label'],
            ];
        }

        return ['anchor' => ['type' => $type, 'id' => $id], 'chain' => $chain, 'breadcrumb' => $breadcrumb];
    }

    private function resolveRoIds(Model $anchor, array $ros): array
    {
        if (! empty($ros['self'])) {
            return [$anchor->id];
        }

        if (! empty($ros['payment'])) {
            return match ($anchor->targetable_type) {
                RegisteredOrder::class => [$anchor->targetable_id],
                PurchaseOrder::class => PurchaseOrder::find($anchor->targetable_id)
                    ?->registeredOrders()->allRelatedIds()->all() ?? [],
                default => [],
            };
        }

        $relation = $ros['relation'] ?? null;
        if (! $relation) {
            return [];
        }

        $anchor->loadMissing($relation);
        $related = $anchor->{$relation};

        if ($related instanceof \Illuminate\Support\Collection) {
            return $related->modelKeys();
        }

        return $related ? [$related->id] : [];
    }

    private function buildChainRecord(string $key, array $cfg, array $meta, Model $r, array &$refs): array
    {
        $progFields = $cfg['progress'] ?? [];
        $total = count($progFields);
        $filled = $total
            ? collect($progFields)->filter(fn ($f) => ! is_null($r->{$f}) && $r->{$f} !== '')->count()
            : 0;

        $statuses = [];
        foreach ($meta['status_columns'] as $col) {
            $val = $r->{$col};
            if ($val) {
                $refs['status'][] = $val;
            }
            $statuses[] = ['label' => $this->fieldLabel($key, $col), 'id' => $val];
        }

        $extras = [];
        foreach ($meta['extra'] ?? [] as $e) {
            $raw = $r->{$e['col']} ?? null;
            $ex = ['label' => $e['label'] ?? $this->fieldLabel($key, $e['col']), 'type' => $e['type'], 'value' => $raw];

            if (($e['type'] === 'company' || $e['type'] === 'bank' || $e['type'] === 'currency') && $raw) {
                $refs[$e['type']][] = $raw;
                $ex['ref'] = $raw;
            } elseif ($e['type'] === 'money') {
                $cur = ! empty($e['currency']) ? ($r->{$e['currency']} ?? null) : null;
                $ex['currency_id'] = $cur;
                if ($cur) {
                    $refs['currency'][] = $cur;
                }
            } elseif ($e['type'] === 'date') {
                $ex['value'] = $this->formatDate($raw);
            }

            $extras[] = $ex;
        }

        return [
            'id' => $r->id,
            'identifier' => $this->primaryIdentifier($r, $meta['identifier']),
            'identifiers' => collect($meta['identifier'])->map(fn ($c) => $r->{$c})->filter()->values()->all(),
            'progress' => $total ? (int) round(($filled / $total) * 100) : 0,
            'url' => ($cfg['url'])($r),
            'statuses' => $statuses,
            'extras' => $extras,
        ];
    }

    private function primaryIdentifier(Model $r, array $cols): string
    {
        foreach ($cols as $c) {
            if (! is_null($r->{$c}) && $r->{$c} !== '') {
                return (string) $r->{$c};
            }
        }

        return '#'.$r->id;
    }

    private function resolveMap(string $model, array $ids, callable $label): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        return $model::whereIn('id', $ids)->get()->mapWithKeys(fn ($m) => [$m->id => $label($m)])->all();
    }

    private function resolveExtra(array $ex, array $companyMap, array $bankMap, array $currencyMap): mixed
    {
        return match ($ex['type']) {
            'company' => $companyMap[$ex['ref'] ?? null] ?? null,
            'bank' => $bankMap[$ex['ref'] ?? null] ?? null,
            'currency' => $currencyMap[$ex['ref'] ?? null] ?? null,
            'money' => $this->formatMoney($ex['value'] ?? null, $currencyMap[$ex['currency_id'] ?? null] ?? null),
            default => $ex['value'] ?? null,
        };
    }

    private function formatMoney(mixed $amount, ?string $currency = null): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return delimiter($amount, $currency);
    }

    private function formatDate(mixed $val): ?string
    {
        if (! $val) {
            return null;
        }

        return app()->getLocale() === 'fa' ? toPersianDate($val) : self::d($val);
    }

    private function fieldLabel(string $key, string $col): string
    {
        $field = preg_replace('/_id$/', '', $col);
        $resolved = __("resources/{$key}/strings.form.{$field}");

        return str_starts_with($resolved, "resources/{$key}/") ? ucfirst(str_replace('_', ' ', $field)) : $resolved;
    }

    private function chainMeta(): array
    {
        return [
            'purchaseRequest' => [
                'identifier' => ['pr_number'],
                'status_columns' => ['status_id'],
                'extra' => [
                    ['col' => 'required_by_date', 'type' => 'date'],
                    ['col' => 'urgency_level', 'type' => 'text'],
                    ['col' => 'total_estimated_cost', 'type' => 'money'],
                ],
                'ros' => ['relation' => 'registeredOrders'],
                'fetch' => fn ($ctx) => PurchaseRequest::whereHas('registeredOrders', fn ($q) => $q->whereIn('registered_orders.id', $ctx['roIds'])),
            ],
            'proformaInvoice' => [
                'identifier' => ['invoice_no', 'contract_no'],
                'status_columns' => [],
                'extra' => [
                    ['col' => 'seller_id', 'type' => 'company', 'label' => __('resources/proformaInvoice/strings.form.seller_company')],
                    ['col' => 'total_amount', 'type' => 'money', 'currency' => 'main_currency_id'],
                    ['col' => 'invoice_date', 'type' => 'date'],
                ],
                'ros' => ['relation' => 'registeredOrders'],
                'fetch' => fn ($ctx) => ProformaInvoice::whereHas('registeredOrders', fn ($q) => $q->whereIn('registered_orders.id', $ctx['roIds'])),
            ],
            'purchaseOrder' => [
                'identifier' => ['po_number'],
                'status_columns' => ['status_id'],
                'extra' => [
                    ['col' => 'seller_id', 'type' => 'company'],
                    ['col' => 'order_date', 'type' => 'date'],
                    ['col' => 'incoterms', 'type' => 'text'],
                ],
                'ros' => ['relation' => 'registeredOrders'],
                'fetch' => fn ($ctx) => PurchaseOrder::whereHas('registeredOrders', fn ($q) => $q->whereIn('registered_orders.id', $ctx['roIds'])),
            ],
            'registeredOrder' => [
                'identifier' => ['ro_number', 'official_registration_no'],
                'status_columns' => ['status_id'],
                'extra' => [
                    ['col' => 'currency_id', 'type' => 'currency'],
                    ['col' => 'incoterms', 'type' => 'text'],
                ],
                'ros' => ['self' => true],
                'fetch' => fn ($ctx) => RegisteredOrder::whereIn('id', $ctx['roIds']),
            ],
            'bankProfile' => [
                'identifier' => ['bp_number'],
                'status_columns' => ['status_id'],
                'extra' => [
                    ['col' => 'bank_id', 'type' => 'bank'],
                    ['col' => 'requested_amount', 'type' => 'money', 'currency' => 'requested_currency_id'],
                    ['col' => 'exchange_rate', 'type' => 'text'],
                ],
                'ros' => ['relation' => 'registeredOrder'],
                'fetch' => fn ($ctx) => BankProfile::whereIn('registered_order_id', $ctx['roIds']),
            ],
            'payment' => [
                'identifier' => ['payment_no'],
                'status_columns' => ['status_id'],
                'extra' => [
                    ['col' => 'total_amount', 'type' => 'money', 'currency' => 'currency_id'],
                    ['col' => 'payment_date', 'type' => 'date'],
                    ['col' => 'payment_deadline', 'type' => 'date'],
                ],
                'ros' => ['payment' => true],
                'fetch' => fn ($ctx) => Payment::where(fn ($q) => $q->where('targetable_type', RegisteredOrder::class)->whereIn('targetable_id', $ctx['roIds']))
                    ->orWhere(fn ($q) => $q->where('targetable_type', PurchaseOrder::class)->whereIn('targetable_id', $ctx['poIds'])),
            ],
            'shipment' => [
                'identifier' => ['shipment_no'],
                'status_columns' => ['status_id', 'container_status_id', 'operation_status_id', 'shipment_status_id', 'doc_status_id'],
                'extra' => [
                    ['col' => 'container_no', 'type' => 'text'],
                    ['col' => 'eta', 'type' => 'date'],
                    ['col' => 'bl_number', 'type' => 'text'],
                ],
                'ros' => ['relation' => 'registeredOrder'],
                'fetch' => fn ($ctx) => Shipment::whereIn('registered_order_id', $ctx['roIds']),
            ],
            'custom' => [
                'identifier' => ['declaration_no', 'custom_no'],
                'status_columns' => ['clearance_status_id', 'bank_guarantee_status_id', 'commitment_status_id'],
                'extra' => [
                    ['col' => 'declaration_no', 'type' => 'text'],
                    ['col' => 'clearance_date', 'type' => 'date'],
                    ['col' => 'clearance_type', 'type' => 'text'],
                ],
                'ros' => ['relation' => 'registeredOrder'],
                'fetch' => fn ($ctx) => Custom::where(fn ($q) => $q->whereIn('registered_order_id', $ctx['roIds']))
                    ->orWhereIn('shipment_id', $ctx['shipmentIds']),
            ],
        ];
    }

    private function buildBreadcrumb(array $found): array
    {
        $has = fn (string $k) => in_array($k, $found);

        $lastFoundIndex = -1;
        $stages = self::PIPELINE;

        foreach ($stages as $i => $stage) {
            if ($has($stage)) {
                $lastFoundIndex = $i;
            }
        }
        $breadcrumb = [];

        $registry = $this->registry();

        foreach ($stages as $i => $stage) {
            $breadcrumb[$stage] = [
                'state' => $has($stage)
                    ? 'completed'
                    : ($i < $lastFoundIndex ? 'missing' : 'upcoming'),
                'label' => $registry[$stage]['label'] ?? $stage,
            ];
        }

        return $breadcrumb;
    }

    private function buildResult(string $key, array $cfg, Model $record): array
    {
        $details = [];
        foreach ($cfg['details'] as [$label, $fn]) {
            $value = $fn($record);
            if (! is_null($value) && $value !== '') {
                $details[] = ['label' => $label, 'value' => (string) $value];
            }
        }

        $progFields = $cfg['progress'] ?? [];
        $total = count($progFields);
        $filled = array_reduce($progFields, fn ($c, $f) => $c + (! is_null($record->{$f}) && $record->{$f} !== '' ? 1 : 0), 0);

        return [
            'type' => $key,
            'id' => $record->getKey(),
            'title' => (string) ($cfg['title'])($record),
            'subtitle' => $cfg['label'],
            'icon' => $cfg['icon'],
            'color' => $cfg['color'],
            'theme' => self::THEME[$cfg['color']],
            'progress' => $total > 0 ? (int) round(($filled / $total) * 100) : 0,
            'url' => ($cfg['url'])($record),
            'details' => $details,
        ];
    }

    private static function d(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }
        try {
            return ($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function registry(): array
    {
        return [

            // ── Operational ──────────────────────────────────────────────────

            'purchaseRequest' => [
                'model' => PurchaseRequest::class,
                'icon' => 'shopping-cart',
                'color' => 'blue',
                'by_user' => true,
                'url' => fn ($r) => route('filament.dashboard.resources.purchase-requests.edit', ['record' => $r->id]),
                'label' => __('resources/purchaseRequest/strings.general.model_label'),
                'search' => ['pr_number', 'rejection_reason'],
                'with' => ['status', 'requester', 'department', 'costCenter'],
                'progress' => ['pr_number', 'status_id', 'requester_id', 'required_by_date', 'urgency_level', 'department_id', 'cost_center_id'],
                'title' => fn ($r) => $r->pr_number ?? ('#'.$r->id),
                'details' => [
                    [__('resources/purchaseRequest/strings.form.status'), fn ($r) => $r->status?->localized_name],
                    [__('resources/purchaseRequest/strings.form.requester'), fn ($r) => $r->requester?->name],
                    [__('resources/purchaseRequest/strings.form.department'), fn ($r) => $r->department?->localized_name],
                    [__('resources/purchaseRequest/strings.form.rejection_reason'), fn ($r) => $r->rejection_reason],
                ],
            ],

            'proformaInvoice' => [
                'model' => ProformaInvoice::class,
                'icon' => 'document-text',
                'color' => 'blue',
                'by_user' => true,
                'url' => fn ($r) => route('filament.dashboard.resources.proforma-invoices.edit', ['record' => $r->id]),
                'label' => __('resources/proformaInvoice/strings.general.model_label'),
                'search' => ['invoice_no', 'contract_no'],
                'with' => ['sellerCompany', 'buyerCompany', 'mainCurrency'],
                'progress' => ['invoice_no', 'contract_no', 'seller_id', 'buyer_id', 'invoice_date', 'validity_date', 'main_currency_id'],
                'title' => fn ($r) => $r->invoice_no ?? $r->contract_no ?? ('#'.$r->id),
                'details' => [
                    [__('resources/proformaInvoice/strings.form.invoice_no'), fn ($r) => $r->invoice_no],
                    [__('resources/proformaInvoice/strings.form.buyer_company'), fn ($r) => $r->buyerCompany?->localized_name],
                    [__('resources/proformaInvoice/strings.form.contract_no'), fn ($r) => $r->contract_no],
                    [__('resources/proformaInvoice/strings.form.validity_date'), fn ($r) => self::d($r->validity_date)],
                    [__('resources/proformaInvoice/strings.form.delivery_terms'), fn ($r) => $r->delivery_terms],
                ],
            ],

            'registeredOrder' => [
                'model' => RegisteredOrder::class,
                'icon' => 'document-check',
                'color' => 'green',
                'by_user' => true,
                'url' => fn ($r) => route('filament.dashboard.resources.registered-orders.edit', ['record' => $r->id]),
                'label' => __('resources/registeredOrder/strings.general.model_label'),
                'search' => ['ro_number', 'contract_no', 'official_registration_no', 'insurance_number', 'insurance_provider'],
                'with' => ['sellerCompanyExclusive', 'buyerCompany', 'status', 'currency'],
                'progress' => ['ro_number', 'contract_no', 'seller_id', 'buyer_id', 'status_id', 'order_date'],
                'title' => fn ($r) => $r->ro_number ?? $r->contract_no ?? ('#'.$r->id),
                'details' => [
                    [__('resources/registeredOrder/strings.form.seller'), fn ($r) => $r->sellerCompanyExclusive?->localized_name],
                    [__('resources/registeredOrder/strings.form.buyer'), fn ($r) => $r->buyerCompany?->localized_name],
                    [__('resources/registeredOrder/strings.form.status'), fn ($r) => $r->status?->localized_name],
                    [__('resources/registeredOrder/strings.form.official_registration_no'), fn ($r) => $r->official_registration_no],
                    [__('resources/registeredOrder/strings.form.order_date'), fn ($r) => self::d($r->order_date)],
                    [__('resources/registeredOrder/strings.form.validity_date'), fn ($r) => self::d($r->validity_date)],
                    [__('resources/registeredOrder/strings.form.insurance_number'), fn ($r) => $r->insurance_number],
                    [__('resources/registeredOrder/strings.form.insurance_provider'), fn ($r) => $r->insurance_provider],
                    [__('resources/registeredOrder/strings.form.expected_delivery_date'), fn ($r) => self::d($r->expected_delivery_date)],
                    [__('resources/registeredOrder/strings.form.total_amount'), fn ($r) => $r->total_amount],
                ],
            ],

            'bankProfile' => [
                'model' => BankProfile::class,
                'icon' => 'building-office',
                'color' => 'green',
                'by_user' => true,
                'url' => fn ($r) => route('filament.dashboard.resources.bank-profiles.edit', ['record' => $r->id]),
                'label' => __('resources/bankProfile/strings.general.model_label'),
                'search' => ['bp_number', 'order_number'],
                'with' => ['bank', 'company', 'status', 'requestedCurrency'],
                'progress' => ['bp_number', 'bank_id', 'company_id', 'status_id', 'order_number', 'payment_due_date'],
                'title' => fn ($r) => $r->bp_number ?? ('#'.$r->id),
                'details' => [
                    [__('resources/bankProfile/strings.form.company'), fn ($r) => $r->company?->localized_name],
                    [__('resources/bankProfile/strings.form.status'), fn ($r) => $r->status?->localized_name],
                    [__('resources/bankProfile/strings.form.order_number'), fn ($r) => $r->order_number],
                    [__('resources/bankProfile/strings.form.payment_due_date'), fn ($r) => self::d($r->payment_due_date)],
                    [__('resources/bankProfile/strings.form.purchased_equivalent'), fn ($r) => $r->purchased_equivalent],
                ],
            ],

            'purchaseOrder' => [
                'model' => PurchaseOrder::class,
                'icon' => 'shopping-bag',
                'color' => 'yellow',
                'by_user' => true,
                'url' => fn ($r) => route('filament.dashboard.resources.purchase-orders.edit', ['record' => $r->id]),
                'label' => __('resources/purchaseOrder/strings.general.model_label'),
                'search' => ['po_number'],
                'with' => ['sellerCompanyExclusive', 'buyerCompany', 'currency', 'status'],
                'progress' => ['po_number', 'seller_id', 'buyer_id', 'currency_id', 'status_id', 'order_date'],
                'title' => fn ($r) => $r->po_number ?? ('#'.$r->id),
                'details' => [
                    [__('resources/purchaseOrder/strings.form.buyer'), fn ($r) => $r->buyerCompany?->localized_name],
                    [__('resources/purchaseOrder/strings.form.currency'), fn ($r) => $r->currency?->localized_name],
                    [__('resources/purchaseOrder/strings.form.status'), fn ($r) => $r->status?->localized_name],
                    [__('resources/purchaseOrder/strings.form.expected_delivery_date'), fn ($r) => self::d($r->expected_delivery_date)],
                    [__('resources/purchaseOrder/strings.form.total_amount'), fn ($r) => $r->total_amount],
                ],
            ],

            'payment' => [
                'model' => Payment::class,
                'icon' => 'banknotes',
                'color' => 'yellow',
                'by_user' => true,
                'url' => fn ($r) => route('filament.dashboard.resources.payments.edit', ['record' => $r->id]),
                'label' => __('resources/payment/strings.general.model_label'),
                'search' => ['payment_no', 'beneficiary_name', 'account_no', 'swift', 'iban'],
                'with' => ['payor', 'payee', 'status'],
                'progress' => ['payment_no', 'payor_id', 'payee_id', 'status_id', 'payment_date', 'beneficiary_name', 'account_no'],
                'title' => fn ($r) => $r->payment_no ?? ('#'.$r->id),
                'details' => [
                    [__('resources/payment/strings.form.payor'), fn ($r) => $r->payor?->localized_name],
                    [__('resources/payment/strings.form.payee'), fn ($r) => $r->payee?->localized_name],
                    [__('resources/payment/strings.form.status'), fn ($r) => $r->status?->localized_name],
                    [__('resources/payment/strings.form.beneficiary_name'), fn ($r) => $r->beneficiary_name],
                    [__('resources/payment/strings.form.account_no'), fn ($r) => $r->account_no],
                    [__('resources/payment/strings.form.swift'), fn ($r) => $r->swift],
                    [__('resources/payment/strings.form.iban'), fn ($r) => $r->iban],
                    [__('resources/payment/strings.form.payable_amount'), fn ($r) => $r->payable_amount],
                ],
            ],

            'shipment' => [
                'model' => Shipment::class,
                'icon' => 'truck',
                'color' => 'red',
                'by_user' => true,
                'url' => fn ($r) => route('filament.dashboard.resources.shipments.edit', ['record' => $r->id]),
                'label' => __('resources/shipment/strings.general.model_label'),
                'search' => ['shipment_no', 'bl_number', 'booking_no', 'container_no'],
                'with' => ['carrier', 'status', 'registeredOrder'],
                'progress' => ['shipment_no', 'bl_number', 'company_id', 'status_id', 'contract_no', 'booking_no'],
                'title' => fn ($r) => $r->shipment_no ?? $r->bl_number ?? ('#'.$r->id),
                'details' => [
                    [__('resources/shipment/strings.form.booking_no'), fn ($r) => $r->booking_no],
                    [__('resources/shipment/strings.form.carrier'), fn ($r) => $r->carrier?->localized_name],
                    [__('resources/shipment/strings.form.status'), fn ($r) => $r->status?->localized_name],
                    [__('resources/shipment/strings.form.contract_no'), fn ($r) => $r->contract_no],
                    [__('resources/shipment/strings.form.etd'), fn ($r) => self::d($r->etd)],
                ],
            ],

            'custom' => [
                'model' => Custom::class,
                'icon' => 'clipboard-document-check',
                'color' => 'red',
                'by_user' => true,
                'url' => fn ($r) => route('filament.dashboard.resources.customs.edit', ['record' => $r->id]),
                'label' => __('resources/custom/strings.general.model_label'),
                'search' => ['declaration_no', 'shipment_no', 'contract_no', 'custom_no'],
                'with' => ['clearanceStatus', 'shipment'],
                'progress' => ['declaration_no', 'custom_no', 'contract_no', 'clearance_status_id', 'shipment_id'],
                'title' => fn ($r) => $r->declaration_no ?? $r->custom_no ?? ('#'.$r->id),
                'details' => [
                    [__('resources/custom/strings.form.contract_no'), fn ($r) => $r->contract_no],
                    [__('resources/custom/strings.form.shipment'), fn ($r) => $r->shipment?->shipment_no],
                    [__('resources/custom/strings.form.clearance_status'), fn ($r) => $r->clearanceStatus?->localized_name],
                    [__('resources/custom/strings.form.custom_no'), fn ($r) => $r->custom_no],
                ],
            ],

            // ── Master Data ───────────────────────────────────────────────────

            'company' => [
                'model' => Company::class,
                'icon' => 'building-office-2',
                'color' => 'slate',
                'by_user' => false,
                'url' => fn ($r) => route('filament.dashboard.resources.companies.index', ['search' => $r->english_name ?? $r->name]),
                'label' => __('resources/company/strings.general.model_label'),
                'search' => ['name', 'english_name'],
                'with' => [],
                'progress' => [],
                'title' => fn ($r) => $r->localized_name ?? ('#'.$r->id),
                'details' => [
                    [__('resources/company/strings.form.english_name'), fn ($r) => $r->english_name],
                    [__('resources/company/strings.form.name'), fn ($r) => $r->name],
                    [__('resources/company/strings.form.description'), fn ($r) => $r->description],
                ],
            ],

            'bank' => [
                'model' => Bank::class,
                'icon' => 'building-library',
                'color' => 'slate',
                'by_user' => false,
                'url' => fn ($r) => route('filament.dashboard.resources.banks.index', ['search' => $r->english_name ?? $r->name]),
                'label' => __('resources/bank/strings.general.model_label'),
                'search' => ['name', 'english_name'],
                'with' => [],
                'progress' => [],
                'title' => fn ($r) => $r->localized_name ?? ('#'.$r->id),
                'details' => [
                    [__('resources/bank/strings.form.english_name'), fn ($r) => $r->english_name],
                    [__('resources/bank/strings.form.name'), fn ($r) => $r->name],
                    [__('resources/bank/strings.form.description'), fn ($r) => $r->description],
                ],
            ],

            'currency' => [
                'model' => Currency::class,
                'icon' => 'currency-dollar',
                'color' => 'slate',
                'by_user' => false,
                'url' => fn ($r) => route('filament.dashboard.resources.currencies.index', ['search' => $r->english_name ?? $r->name]),
                'label' => __('resources/currency/strings.general.model_label'),
                'search' => ['name', 'english_name'],
                'with' => [],
                'progress' => [],
                'title' => fn ($r) => $r->localized_name ?? ('#'.$r->id),
                'details' => [
                    [__('resources/currency/strings.form.english_name'), fn ($r) => $r->english_name],
                    [__('resources/currency/strings.form.name'), fn ($r) => $r->name],
                    [__('resources/currency/strings.form.description'), fn ($r) => $r->description],
                ],
            ],

            'product' => [
                'model' => Product::class,
                'icon' => 'cube',
                'color' => 'slate',
                'by_user' => false,
                'url' => fn ($r) => route('filament.dashboard.resources.products.index', ['search' => $r->english_name ?? $r->name]),
                'label' => __('resources/product/strings.general.model_label'),
                'search' => ['name', 'english_name', 'code'],
                'with' => ['category'],
                'progress' => [],
                'title' => fn ($r) => $r->localized_name ?? ('#'.$r->id),
                'details' => [
                    [__('resources/product/strings.form.english_name'), fn ($r) => $r->english_name],
                    [__('resources/product/strings.form.name'), fn ($r) => $r->name],
                    [__('resources/product/strings.form.code'), fn ($r) => $r->code],
                    [__('resources/product/strings.form.category'), fn ($r) => $r->category?->localized_name],
                ],
            ],

            'category' => [
                'model' => Category::class,
                'icon' => 'tag',
                'color' => 'slate',
                'by_user' => false,
                'url' => fn ($r) => route('filament.dashboard.resources.categories.index', ['search' => $r->english_name ?? $r->name]),
                'label' => __('resources/category/strings.general.model_label'),
                'search' => ['name', 'english_name'],
                'with' => ['parent'],
                'progress' => [],
                'title' => fn ($r) => $r->localized_name ?? ('#'.$r->id),
                'details' => [
                    [__('resources/category/strings.form.english_name'), fn ($r) => $r->english_name],
                    [__('resources/category/strings.form.name'), fn ($r) => $r->name],
                    [__('resources/category/strings.form.parent'), fn ($r) => $r->parent?->localized_name],
                ],
            ],

            'status' => [
                'model' => Status::class,
                'icon' => 'check-badge',
                'color' => 'slate',
                'by_user' => false,
                'url' => fn ($r) => route('filament.dashboard.resources.statuses.index', ['search' => $r->english_name ?? $r->name]),
                'label' => __('resources/status/strings.general.model_label'),
                'search' => ['name', 'english_name', 'english_type'],
                'with' => [],
                'progress' => [],
                'title' => fn ($r) => $r->localized_name ?? ('#'.$r->id),
                'details' => [
                    [__('resources/status/strings.form.english_name'), fn ($r) => $r->english_name],
                    [__('resources/status/strings.form.name'), fn ($r) => $r->name],
                    [__('resources/status/strings.form.english_type'), fn ($r) => $r->english_type],
                ],
            ],
        ];
    }
}
