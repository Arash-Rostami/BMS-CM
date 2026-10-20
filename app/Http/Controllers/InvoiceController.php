<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function shipmentPdf(Request $request, Shipment $shipment): \Illuminate\Http\Response
    {
        abort_unless(userCan(Shipment::class), 403);

        $invoice = ShipmentResource::loadInvoiceData($shipment);

        abort_if($invoice === null, 404, 'No saved invoice for this shipment.');

        $locale = session('locale', app()->getLocale());

        return app(InvoicePdfService::class)->download($invoice, $locale);
    }
}
