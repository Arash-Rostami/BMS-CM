<?php

namespace App\Http\Controllers;

use App\Models\EntityAttribute;
use App\Models\Shipment;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function shipmentPdf(Request $request, Shipment $shipment): \Illuminate\Http\Response
    {
        abort_unless(auth()->check(), 403);

        $attr = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $shipment->id)
            ->where('key', 'commercial_invoice')
            ->first();

        if (!$attr || !is_array($attr->value)) {
            abort(404, 'No saved invoice for this shipment.');
        }

        $locale = session('locale', app()->getLocale());

        return app(InvoicePdfService::class)->download($attr->value, $locale);
    }
}
