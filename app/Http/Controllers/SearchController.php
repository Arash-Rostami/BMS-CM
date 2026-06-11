<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function spotlight(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        if (strlen($term) < 2) {
            return response()->json(['results' => [], 'breadcrumb' => $this->emptyBreadcrumb(), 'by_user' => null]);
        }

        $escaped = addcslashes($term, '%_\\');
        $byUser  = User::where('name', 'like', "%{$escaped}%")->first();

        $results    = [];
        $foundTypes = [];

        foreach ($this->registry() as $key => $cfg) {
            $base = (new $cfg['model'])->newQuery()->with($cfg['with']);

            $fieldRecord = (clone $base)
                ->where(function ($q) use ($cfg, $escaped) {
                    foreach ($cfg['search'] as $col) {
                        $q->orWhere($col, 'like', "%{$escaped}%");
                    }
                })
                ->latest()
                ->first();

            $userRecord = ($byUser && ($cfg['by_user'] ?? false))
                ? (clone $base)->where('user_id', $byUser->id)->latest()->first()
                : null;

            $record = $fieldRecord ?? $userRecord;
            if (! $record) continue;

            $foundTypes[] = $key;
            $results[]    = $this->buildResult($key, $cfg, $record);
        }

        return response()->json([
            'results'    => $results,
            'breadcrumb' => $this->buildBreadcrumb($foundTypes),
            'by_user'    => $byUser ? ['id' => $byUser->id, 'name' => $byUser->name] : null,
        ]);
    }

    // ── Registry ──────────────────────────────────────────────────────────────

    private function registry(): array
    {
        return [

            // ── Operational ──────────────────────────────────────────────────

            'purchaseRequest' => [
                'model'    => PurchaseRequest::class,
                'icon'     => 'shopping-cart',
                'color'    => 'blue',
                'theme'    => 'from-blue-500 to-blue-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.purchase-requests.edit', ['record' => $r->id]),
                'label'    => __('resources/purchaseRequest/strings.general.model_label'),
                'search'   => ['pr_number', 'rejection_reason'],
                'with'     => ['status', 'requester', 'department', 'costCenter'],
                'progress' => ['pr_number', 'status_id', 'requester_id', 'required_by_date', 'urgency_level', 'department_id', 'cost_center_id'],
                'title'    => fn($r) => $r->pr_number ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/purchaseRequest/strings.form.status'),           fn($r) => $r->status?->localized_name],
                    [__('resources/purchaseRequest/strings.form.requester'),        fn($r) => $r->requester?->name],
                    [__('resources/purchaseRequest/strings.form.required_by_date'), fn($r) => self::d($r->required_by_date)],
                    [__('resources/purchaseRequest/strings.form.urgency_level'),    fn($r) => $r->urgency_level],
                    [__('resources/purchaseRequest/strings.form.department'),       fn($r) => $r->department?->localized_name],
                    [__('resources/purchaseRequest/strings.form.rejection_reason'), fn($r) => $r->rejection_reason],
                    [__('resources/purchaseRequest/strings.form.total_estimated_cost'), fn($r) => $r->total_estimated_cost],
                ],
            ],

            'proformaInvoice' => [
                'model'    => ProformaInvoice::class,
                'icon'     => 'document-text',
                'color'    => 'indigo',
                'theme'    => 'from-indigo-500 to-indigo-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.proforma-invoices.edit', ['record' => $r->id]),
                'label'    => __('resources/proformaInvoice/strings.general.model_label'),
                'search'   => ['invoice_no', 'contract_no'],
                'with'     => ['sellerCompany', 'buyerCompany', 'mainCurrency'],
                'progress' => ['invoice_no', 'contract_no', 'seller_id', 'buyer_id', 'invoice_date', 'validity_date', 'main_currency_id'],
                'title'    => fn($r) => $r->invoice_no ?? $r->contract_no ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/proformaInvoice/strings.form.invoice_no'),     fn($r) => $r->invoice_no],
                    [__('resources/proformaInvoice/strings.form.seller_company'), fn($r) => $r->sellerCompany?->localized_name],
                    [__('resources/proformaInvoice/strings.form.buyer_company'),  fn($r) => $r->buyerCompany?->localized_name],
                    [__('resources/proformaInvoice/strings.form.contract_no'),    fn($r) => $r->contract_no],
                    [__('resources/proformaInvoice/strings.form.invoice_date'),   fn($r) => self::d($r->invoice_date)],
                    [__('resources/proformaInvoice/strings.form.validity_date'),  fn($r) => self::d($r->validity_date)],
                    [__('resources/proformaInvoice/strings.form.total_amount'),   fn($r) => $r->total_amount],
                    [__('resources/proformaInvoice/strings.form.delivery_terms'), fn($r) => $r->delivery_terms],
                    [__('resources/proformaInvoice/strings.form.main_currency'),  fn($r) => $r->mainCurrency?->localized_name],
                ],
            ],

            'registeredOrder' => [
                'model'    => RegisteredOrder::class,
                'icon'     => 'document-check',
                'color'    => 'green',
                'theme'    => 'from-green-500 to-green-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.registered-orders.edit', ['record' => $r->id]),
                'label'    => __('resources/registeredOrder/strings.general.model_label'),
                'search'   => ['ro_number', 'contract_no', 'official_registration_no', 'insurance_number', 'insurance_provider'],
                'with'     => ['sellerCompanyExclusive', 'buyerCompany', 'status', 'currency'],
                'progress' => ['ro_number', 'contract_no', 'seller_id', 'buyer_id', 'status_id', 'order_date'],
                'title'    => fn($r) => $r->ro_number ?? $r->contract_no ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/registeredOrder/strings.form.seller'),             fn($r) => $r->sellerCompanyExclusive?->localized_name],
                    [__('resources/registeredOrder/strings.form.buyer'),              fn($r) => $r->buyerCompany?->localized_name],
                    [__('resources/registeredOrder/strings.form.status'),             fn($r) => $r->status?->localized_name],
                    [__('resources/registeredOrder/strings.form.contract_number'),    fn($r) => $r->contract_no],
                    [__('resources/registeredOrder/strings.form.order_date'),         fn($r) => self::d($r->order_date)],
                    [__('resources/registeredOrder/strings.form.insurance_number'),   fn($r) => $r->insurance_number],
                    [__('resources/registeredOrder/strings.form.insurance_provider'), fn($r) => $r->insurance_provider],
                    [__('resources/registeredOrder/strings.form.expected_delivery_date'), fn($r) => self::d($r->expected_delivery_date)],
                    [__('resources/registeredOrder/strings.form.total_amount'),       fn($r) => $r->total_amount],
                    [__('resources/registeredOrder/strings.form.currency'),           fn($r) => $r->currency?->localized_name],
                ],
            ],

            'bankProfile' => [
                'model'    => BankProfile::class,
                'icon'     => 'building-office',
                'color'    => 'emerald',
                'theme'    => 'from-emerald-500 to-emerald-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.bank-profiles.edit', ['record' => $r->id]),
                'label'    => __('resources/bankProfile/strings.general.model_label'),
                'search'   => ['bp_number', 'order_number'],
                'with'     => ['bank', 'company', 'status', 'requestedCurrency'],
                'progress' => ['bp_number', 'bank_id', 'company_id', 'status_id', 'order_number', 'payment_due_date'],
                'title'    => fn($r) => $r->bp_number ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/bankProfile/strings.form.bank'),             fn($r) => $r->bank?->localized_name],
                    [__('resources/bankProfile/strings.form.company'),          fn($r) => $r->company?->localized_name],
                    [__('resources/bankProfile/strings.form.status'),           fn($r) => $r->status?->localized_name],
                    [__('resources/bankProfile/strings.form.order_number'),     fn($r) => $r->order_number],
                    [__('resources/bankProfile/strings.form.payment_due_date'), fn($r) => self::d($r->payment_due_date)],
                    [__('resources/bankProfile/strings.form.requested_amount'), fn($r) => $r->requested_amount],
                    [__('resources/bankProfile/strings.form.purchased_equivalent'), fn($r) => $r->purchased_equivalent],
                    [__('resources/bankProfile/strings.form.requested_currency'), fn($r) => $r->requestedCurrency?->localized_name],
                ],
            ],

            'purchaseOrder' => [
                'model'    => PurchaseOrder::class,
                'icon'     => 'shopping-bag',
                'color'    => 'amber',
                'theme'    => 'from-amber-500 to-amber-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.purchase-orders.edit', ['record' => $r->id]),
                'label'    => __('resources/purchaseOrder/strings.general.model_label'),
                'search'   => ['po_number'],
                'with'     => ['sellerCompanyExclusive', 'buyerCompany', 'currency', 'status'],
                'progress' => ['po_number', 'seller_id', 'buyer_id', 'currency_id', 'status_id', 'order_date'],
                'title'    => fn($r) => $r->po_number ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/purchaseOrder/strings.form.seller'),     fn($r) => $r->sellerCompanyExclusive?->localized_name],
                    [__('resources/purchaseOrder/strings.form.buyer'),      fn($r) => $r->buyerCompany?->localized_name],
                    [__('resources/purchaseOrder/strings.form.currency'),   fn($r) => $r->currency?->localized_name],
                    [__('resources/purchaseOrder/strings.form.status'),     fn($r) => $r->status?->localized_name],
                    [__('resources/purchaseOrder/strings.form.order_date'), fn($r) => self::d($r->order_date)],
                    [__('resources/purchaseOrder/strings.form.expected_delivery_date'), fn($r) => self::d($r->expected_delivery_date)],
                    [__('resources/purchaseOrder/strings.form.total_amount'), fn($r) => $r->total_amount],
                ],
            ],

            'payment' => [
                'model'    => Payment::class,
                'icon'     => 'banknotes',
                'color'    => 'orange',
                'theme'    => 'from-orange-500 to-orange-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.payments.edit', ['record' => $r->id]),
                'label'    => __('resources/payment/strings.general.model_label'),
                'search'   => ['payment_no', 'beneficiary_name', 'account_no', 'swift', 'iban'],
                'with'     => ['payor', 'payee', 'status'],
                'progress' => ['payment_no', 'payor_id', 'payee_id', 'status_id', 'payment_date', 'beneficiary_name', 'account_no'],
                'title'    => fn($r) => $r->payment_no ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/payment/strings.form.payor'),            fn($r) => $r->payor?->localized_name],
                    [__('resources/payment/strings.form.payee'),            fn($r) => $r->payee?->localized_name],
                    [__('resources/payment/strings.form.status'),           fn($r) => $r->status?->localized_name],
                    [__('resources/payment/strings.form.payment_date'),     fn($r) => self::d($r->payment_date)],
                    [__('resources/payment/strings.form.beneficiary_name'), fn($r) => $r->beneficiary_name],
                    [__('resources/payment/strings.form.account_no'),       fn($r) => $r->account_no],
                    [__('resources/payment/strings.form.swift'),            fn($r) => $r->swift],
                    [__('resources/payment/strings.form.iban'),             fn($r) => $r->iban],
                    [__('resources/payment/strings.form.payable_amount'),   fn($r) => $r->payable_amount],
                ],
            ],

            'shipment' => [
                'model'    => Shipment::class,
                'icon'     => 'truck',
                'color'    => 'purple',
                'theme'    => 'from-purple-500 to-purple-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.shipments.edit', ['record' => $r->id]),
                'label'    => __('resources/shipment/strings.general.model_label'),
                'search'   => ['shipment_no', 'bl_number', 'booking_no', 'container_no'],
                'with'     => ['carrier', 'status', 'registeredOrder'],
                'progress' => ['shipment_no', 'bl_number', 'company_id', 'status_id', 'contract_no', 'booking_no'],
                'title'    => fn($r) => $r->shipment_no ?? $r->bl_number ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/shipment/strings.form.bl_number'),   fn($r) => $r->bl_number],
                    [__('resources/shipment/strings.form.booking_no'),  fn($r) => $r->booking_no],
                    [__('resources/shipment/strings.form.carrier'),     fn($r) => $r->carrier?->localized_name],
                    [__('resources/shipment/strings.form.status'),      fn($r) => $r->status?->localized_name],
                    [__('resources/shipment/strings.form.contract_no'), fn($r) => $r->contract_no],
                    [__('resources/shipment/strings.form.container_no'), fn($r) => $r->container_no],
                    [__('resources/shipment/strings.form.eta'),         fn($r) => self::d($r->eta)],
                    [__('resources/shipment/strings.form.etd'),         fn($r) => self::d($r->etd)],
                ],
            ],

            'custom' => [
                'model'    => Custom::class,
                'icon'     => 'clipboard-document-check',
                'color'    => 'violet',
                'theme'    => 'from-violet-500 to-violet-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.customs.edit', ['record' => $r->id]),
                'label'    => __('resources/custom/strings.general.model_label'),
                'search'   => ['declaration_no', 'shipment_no', 'contract_no', 'custom_no'],
                'with'     => ['clearanceStatus', 'shipment'],
                'progress' => ['declaration_no', 'custom_no', 'contract_no', 'clearance_status_id', 'shipment_id'],
                'title'    => fn($r) => $r->declaration_no ?? $r->custom_no ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/custom/strings.form.contract_no'),      fn($r) => $r->contract_no],
                    [__('resources/custom/strings.form.shipment'),         fn($r) => $r->shipment?->shipment_no],
                    [__('resources/custom/strings.form.clearance_status'), fn($r) => $r->clearanceStatus?->localized_name],
                    [__('resources/custom/strings.form.declaration_no'),   fn($r) => $r->declaration_no],
                    [__('resources/custom/strings.form.custom_no'),        fn($r) => $r->custom_no],
                    [__('resources/custom/strings.form.clearance_type'),   fn($r) => $r->clearance_type],
                    [__('resources/custom/strings.form.clearance_date'),   fn($r) => self::d($r->clearance_date)],
                ],
            ],

            // ── Master Data ───────────────────────────────────────────────────

            'company' => [
                'model'    => Company::class,
                'icon'     => 'building-office-2',
                'color'    => 'sky',
                'theme'    => 'from-sky-500 to-sky-600',
                'by_user'  => false,
                'url'      => fn($r) => route('filament.dashboard.resources.companies.index', ['search' => $r->english_name ?? $r->name]),
                'label'    => __('resources/company/strings.general.model_label'),
                'search'   => ['name', 'english_name'],
                'with'     => [],
                'progress' => [],
                'title'    => fn($r) => $r->localized_name ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/company/strings.form.english_name'), fn($r) => $r->english_name],
                    [__('resources/company/strings.form.name'),         fn($r) => $r->name],
                    [__('resources/company/strings.form.description'),  fn($r) => $r->description],
                ],
            ],

            'bank' => [
                'model'    => Bank::class,
                'icon'     => 'building-library',
                'color'    => 'teal',
                'theme'    => 'from-teal-500 to-teal-600',
                'by_user'  => false,
                'url'      => fn($r) => route('filament.dashboard.resources.banks.index', ['search' => $r->english_name ?? $r->name]),
                'label'    => __('resources/bank/strings.general.model_label'),
                'search'   => ['name', 'english_name'],
                'with'     => [],
                'progress' => [],
                'title'    => fn($r) => $r->localized_name ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/bank/strings.form.english_name'), fn($r) => $r->english_name],
                    [__('resources/bank/strings.form.name'),         fn($r) => $r->name],
                    [__('resources/bank/strings.form.description'),  fn($r) => $r->description],
                ],
            ],

            'currency' => [
                'model'    => Currency::class,
                'icon'     => 'currency-dollar',
                'color'    => 'lime',
                'theme'    => 'from-lime-500 to-lime-600',
                'by_user'  => false,
                'url'      => fn($r) => route('filament.dashboard.resources.currencies.index', ['search' => $r->english_name ?? $r->name]),
                'label'    => __('resources/currency/strings.general.model_label'),
                'search'   => ['name', 'english_name'],
                'with'     => [],
                'progress' => [],
                'title'    => fn($r) => $r->localized_name ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/currency/strings.form.english_name'), fn($r) => $r->english_name],
                    [__('resources/currency/strings.form.name'),         fn($r) => $r->name],
                    [__('resources/currency/strings.form.description'),  fn($r) => $r->description],
                ],
            ],

            'product' => [
                'model'    => Product::class,
                'icon'     => 'cube',
                'color'    => 'rose',
                'theme'    => 'from-rose-500 to-rose-600',
                'by_user'  => false,
                'url'      => fn($r) => route('filament.dashboard.resources.products.index', ['search' => $r->english_name ?? $r->name]),
                'label'    => __('resources/product/strings.general.model_label'),
                'search'   => ['name', 'english_name', 'code'],
                'with'     => ['category'],
                'progress' => [],
                'title'    => fn($r) => $r->localized_name ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/product/strings.form.english_name'), fn($r) => $r->english_name],
                    [__('resources/product/strings.form.name'),         fn($r) => $r->name],
                    [__('resources/product/strings.form.code'),         fn($r) => $r->code],
                    [__('resources/product/strings.form.category'),     fn($r) => $r->category?->localized_name],
                ],
            ],

            'category' => [
                'model'    => Category::class,
                'icon'     => 'tag',
                'color'    => 'fuchsia',
                'theme'    => 'from-fuchsia-500 to-fuchsia-600',
                'by_user'  => false,
                'url'      => fn($r) => route('filament.dashboard.resources.categories.index', ['search' => $r->english_name ?? $r->name]),
                'label'    => __('resources/category/strings.general.model_label'),
                'search'   => ['name', 'english_name'],
                'with'     => ['parent'],
                'progress' => [],
                'title'    => fn($r) => $r->localized_name ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/category/strings.form.english_name'), fn($r) => $r->english_name],
                    [__('resources/category/strings.form.name'),         fn($r) => $r->name],
                    [__('resources/category/strings.form.parent'),       fn($r) => $r->parent?->localized_name],
                ],
            ],

            'status' => [
                'model'    => Status::class,
                'icon'     => 'check-badge',
                'color'    => 'cyan',
                'theme'    => 'from-cyan-500 to-cyan-600',
                'by_user'  => false,
                'url'      => fn($r) => route('filament.dashboard.resources.statuses.index', ['search' => $r->english_name ?? $r->name]),
                'label'    => __('resources/status/strings.general.model_label'),
                'search'   => ['name', 'english_name', 'english_type'],
                'with'     => [],
                'progress' => [],
                'title'    => fn($r) => $r->localized_name ?? ('#' . $r->id),
                'details'  => [
                    [__('resources/status/strings.form.english_name'), fn($r) => $r->english_name],
                    [__('resources/status/strings.form.name'),         fn($r) => $r->name],
                    [__('resources/status/strings.form.english_type'), fn($r) => $r->english_type],
                ],
            ],

        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
        $total      = count($progFields);
        $filled     = array_reduce($progFields, fn($c, $f) => $c + (! is_null($record->{$f}) && $record->{$f} !== '' ? 1 : 0), 0);

        return [
            'type'     => $key,
            'title'    => (string) ($cfg['title'])($record),
            'subtitle' => $cfg['label'],
            'icon'     => $cfg['icon'],
            'color'    => $cfg['color'],
            'theme'    => $cfg['theme'],
            'progress' => $total > 0 ? (int) round(($filled / $total) * 100) : 0,
            'url'      => ($cfg['url'])($record),
            'details'  => $details,
        ];
    }

    private static function d(mixed $value): ?string
    {
        if (is_null($value)) return null;
        try {
            return ($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildBreadcrumb(array $found): array
    {
        $has = fn(string $k) => in_array($k, $found);

        $breadcrumb = [];

        $operationalStages = ['purchaseRequest', 'proformaInvoice', 'purchaseOrder', 'registeredOrder', 'bankProfile', 'payment', 'shipment', 'custom'];
        $foundIndex = -1;
        foreach ($operationalStages as $i => $stage) {
            if ($has($stage)) $foundIndex = $i;
        }

        foreach ($operationalStages as $i => $stage) {
            if ($has($stage)) $breadcrumb[$stage] = 'completed';
            elseif ($i < $foundIndex) $breadcrumb[$stage] = 'missing';
            else $breadcrumb[$stage] = 'upcoming';
        }

        return $breadcrumb;
    }

    private function emptyBreadcrumb(): array
    {
        return [
            'purchaseRequest' => 'upcoming',
            'proformaInvoice' => 'upcoming',
            'purchaseOrder' => 'upcoming',
            'registeredOrder' => 'upcoming',
            'bankProfile' => 'upcoming',
            'payment' => 'upcoming',
            'shipment' => 'upcoming',
            'custom' => 'upcoming'
        ];
    }
}
