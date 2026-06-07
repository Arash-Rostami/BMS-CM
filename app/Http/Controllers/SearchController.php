<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function spotlight(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json(['results' => []]);
        }

        $results = [];

        $po = PurchaseOrder::where('id', 'like', "%{$query}%")
            ->orWhere('number', 'like', "%{$query}%")
            ->first();

        if ($po) {
            $filledFields = 0;
            $totalFields = 10;
            if ($po->number) $filledFields++;
            if ($po->amount) $filledFields++;
            if ($po->currency_id) $filledFields++;
            if ($po->status_id) $filledFields++;
            if ($po->date) $filledFields++;

            $progress = round(($filledFields / $totalFields) * 100);

            $results[] = [
                'type' => 'Purchase Order',
                'title' => 'Purchase Order',
                'subtitle' => $po->number ?? 'PO-' . $po->id,
                'progress' => $progress,
                'icon' => 'shopping-bag',
                'color' => 'amber'
            ];
        }

        $pi = ProformaInvoice::where('id', 'like', "%{$query}%")
            ->orWhere('number', 'like', "%{$query}%")
            ->first();

        if ($pi) {
            $results[] = [
                'type' => 'Proforma Invoice',
                'title' => 'Proforma Invoice',
                'subtitle' => $pi->number ?? 'PI-' . $pi->id,
                'progress' => 85, // Mocked for demonstration
                'icon' => 'document-text',
                'color' => 'indigo'
            ];
        }


        $docCount = Attachment::where('name', 'like', "%{$query}%")
            ->orWhere('file_name', 'like', "%{$query}%")
            ->count();

        if ($docCount > 0 || $po || $pi) {
            $actualCount = $docCount > 0 ? $docCount : 3; // Fallback
            $results[] = [
                'type' => 'Documents',
                'title' => 'Documents',
                'subtitle' => "{$actualCount} Attached Files",
                'is_binary' => true,
                'icon' => 'paper-clip',
                'color' => 'slate'
            ];
        }

        $breadcrumb = [
            'proforma' => $pi ? 'completed' : 'upcoming',
            'order' => $po ? 'active' : 'upcoming',
            'logistics' => 'upcoming'
        ];

        return response()->json([
            'results' => $results,
            'breadcrumb' => $breadcrumb
        ]);
    }
}
