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
use App\Services\SearchExtractorService;
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
                'resource' => \App\Filament\Resources\PurchaseRequestResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'proformaInvoice' => [
                'model'    => ProformaInvoice::class,
                'resource' => \App\Filament\Resources\ProformaInvoiceResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'registeredOrder' => [
                'model'    => RegisteredOrder::class,
                'resource' => \App\Filament\Resources\RegisteredOrderResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'bankProfile' => [
                'model'    => BankProfile::class,
                'resource' => \App\Filament\Resources\BankProfileResource::class,
                'icon'     => 'building-office',
                'color'    => 'emerald',
                'theme'    => 'from-emerald-500 to-emerald-600',
                'by_user'  => true,
                'url'      => fn($r) => route('filament.dashboard.resources.bank-profiles.edit', ['record' => $r->id]),
                'label'    => __('resources/bankProfile/strings.general.model_label'),
                'search'   => ['bp_number', 'order_number'],
                'with'     => ['bank', 'company', 'status'],
                'progress' => ['bp_number', 'bank_id', 'company_id', 'status_id', 'order_number', 'payment_due_date'],
                'title'    => fn($r) => $r->bp_number ?? ('#' . $r->id),
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'purchaseOrder' => [
                'model'    => PurchaseOrder::class,
                'resource' => \App\Filament\Resources\PurchaseOrderResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'payment' => [
                'model'    => Payment::class,
                'resource' => \App\Filament\Resources\PaymentResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'shipment' => [
                'model'    => Shipment::class,
                'resource' => \App\Filament\Resources\ShipmentResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'custom' => [
                'model'    => Custom::class,
                'resource' => \App\Filament\Resources\CustomResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            // ── Master Data ───────────────────────────────────────────────────

            'company' => [
                'model'    => Company::class,
                'resource' => \App\Filament\Resources\CompanyResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'bank' => [
                'model'    => Bank::class,
                'resource' => \App\Filament\Resources\BankResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'currency' => [
                'model'    => Currency::class,
                'resource' => \App\Filament\Resources\CurrencyResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'product' => [
                'model'    => Product::class,
                'resource' => \App\Filament\Resources\ProductResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'category' => [
                'model'    => Category::class,
                'resource' => \App\Filament\Resources\CategoryResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

            'status' => [
                'model'    => Status::class,
                'resource' => \App\Filament\Resources\StatusResource::class,
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
                'details'  => [], // Details generated dynamically via SearchExtractorService
            ],

        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildResult(string $key, array $cfg, Model $record): array
    {
        $extractor = new SearchExtractorService();
        $resourceClass = $cfg['resource'] ?? null;

        $details = [];
        if ($resourceClass && class_exists($resourceClass)) {
            $details = $extractor->extractDetails($record, $resourceClass);
        } else if (isset($cfg['details'])) {
            // Fallback for resources without a filament resource or hardcoded details
            foreach ($cfg['details'] as [$label, $fn]) {
                $value = $fn($record);
                if (! is_null($value) && $value !== '') {
                    $details[] = ['label' => $label, 'value' => (string) $value];
                }
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

        $proforma  = $has('proformaInvoice') ? 'completed' : ($has('purchaseRequest') ? 'active' : 'upcoming');
        $order     = $has('registeredOrder') ? 'completed' : ($has('bankProfile')     ? 'active' : 'upcoming');
        $logistics = $has('shipment')        ? 'completed' : ($has('custom')          ? 'active' : 'upcoming');

        if ($proforma === 'completed' && $order === 'upcoming')    $order     = 'active';
        if ($order === 'completed'    && $logistics === 'upcoming') $logistics = 'active';

        return compact('proforma', 'order', 'logistics');
    }

    private function emptyBreadcrumb(): array
    {
        return ['proforma' => 'upcoming', 'order' => 'upcoming', 'logistics' => 'upcoming'];
    }
}
