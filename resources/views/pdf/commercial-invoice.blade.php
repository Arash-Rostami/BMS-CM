<!DOCTYPE html>
<html dir="{{ $isRtl ? 'rtl' : 'ltr' }}" lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<style>
* {
    font-family: {{ $isRtl ? "'iranyekan'" : "'dejavusans'" }}, sans-serif;
    box-sizing: border-box;
}
body { font-size: 9pt; color: #1F2937; margin: 0; padding: 0; }

.w100 { width: 100%; }
.border-collapse { border-collapse: collapse; }

/* ── Header ── */
.hdr { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.hdr-left { vertical-align: top; padding: 0 8px 0 0; }
.hdr-right { vertical-align: top; text-align: {{ $isRtl ? 'left' : 'right' }}; }
.doc-title { font-size: 20pt; font-weight: bold; color: #1E3A8A; text-transform: uppercase; letter-spacing: 1px; }
.doc-meta { font-size: 8.5pt; color: #6B7280; margin-top: 4px; }
.doc-meta strong { color: #374151; }
.seller-name { font-size: 13pt; font-weight: bold; color: #1E3A8A; }
.seller-detail { font-size: 8pt; color: #4B5563; margin-top: 2px; }
.accent-line { height: 3px; background: linear-gradient(to right, #1E3A8A, #3B82F6, #BFDBFE); border: none; margin: 8px 0; }

/* ── Parties ── */
.parties { width: 100%; border-collapse: collapse; margin: 8px 0; }
.party-cell { width: 50%; vertical-align: top; padding: 8px 10px; border: 1px solid #E5E7EB; }
.party-cell-gap { width: 8px; }
.party-label { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.5px; color: #9CA3AF; font-weight: bold; margin-bottom: 3px; }
.party-name { font-size: 10.5pt; font-weight: bold; color: #111827; }
.party-detail { font-size: 8pt; color: #4B5563; margin-top: 2px; line-height: 1.4; }
.party-left { background: #F8FAFF; border-{{ $isRtl ? 'right' : 'left' }}: 3px solid #1E3A8A; }
.party-right { background: #FAFAF9; border-{{ $isRtl ? 'right' : 'left' }}: 3px solid #059669; }

/* ── Info bar ── */
.info-bar { background: #F3F4F6; border: 1px solid #E5E7EB; padding: 6px 10px; margin: 8px 0; }
.info-grid { width: 100%; border-collapse: collapse; }
.info-cell { padding: 2px 10px 2px 0; font-size: 8pt; white-space: nowrap; }
.info-lbl { color: #9CA3AF; font-size: 7pt; text-transform: uppercase; }
.info-val { font-weight: bold; color: #374151; }

/* ── Items table ── */
.items-wrap { margin: 10px 0; }
.items { width: 100%; border-collapse: collapse; }
.items thead th {
    background: #1E3A8A;
    color: #FFFFFF;
    font-size: 7.5pt;
    font-weight: bold;
    padding: 5px 4px;
    border: 1px solid #1E3A8A;
    text-align: {{ $isRtl ? 'right' : 'left' }};
}
.items thead th.num { text-align: center; width: 24px; }
.items tbody td {
    padding: 5px 4px;
    font-size: 8.5pt;
    border: 1px solid #E5E7EB;
    vertical-align: top;
}
.items tbody tr:nth-child(even) td { background: #F9FAFB; }
.items tbody tr:last-child td { border-bottom: 2px solid #CBD5E1; }
.td-center { text-align: center; }
.td-right { text-align: right; }
.td-mono { font-size: 8pt; }

/* ── Totals ── */
.totals-wrap { width: 100%; border-collapse: collapse; margin-top: 10px; }
.totals-weights { vertical-align: top; padding-right: 10px; }
.totals-summary { vertical-align: top; width: 280px; }

.wt-box { background: #F8FAFF; border: 1px solid #E5E7EB; padding: 8px 10px; }
.wt-label { font-size: 7pt; color: #9CA3AF; text-transform: uppercase; }
.wt-value { font-size: 10pt; font-weight: bold; color: #374151; margin-top: 2px; }

.sum-table { width: 100%; border-collapse: collapse; }
.sum-row td { padding: 3px 4px; font-size: 8.5pt; border-bottom: 1px solid #F3F4F6; }
.sum-lbl { color: #6B7280; text-align: {{ $isRtl ? 'right' : 'left' }}; }
.sum-val { text-align: {{ $isRtl ? 'left' : 'right' }}; font-weight: bold; color: #374151; white-space: nowrap; }
.sum-discount { color: #DC2626; }
.grand-row td { padding: 6px 4px; background: #EFF6FF; border-top: 2px solid #1E3A8A; }
.grand-lbl { font-size: 11pt; font-weight: bold; color: #1E3A8A; text-align: {{ $isRtl ? 'right' : 'left' }}; }
.grand-val { font-size: 11pt; font-weight: bold; color: #1E3A8A; text-align: {{ $isRtl ? 'left' : 'right' }}; white-space: nowrap; }

/* ── Terms ── */
.terms-bar { margin-top: 10px; border: 1px solid #E5E7EB; background: #FAFAFA; padding: 6px 10px; }
.terms-grid { width: 100%; border-collapse: collapse; }
.terms-cell { font-size: 8pt; padding: 2px 12px 2px 0; vertical-align: top; }
.terms-lbl { font-size: 7pt; text-transform: uppercase; color: #9CA3AF; }
.terms-val { font-weight: bold; color: #374151; }

/* ── Remarks ── */
.remarks { margin-top: 8px; padding: 6px 10px; border-{{ $isRtl ? 'right' : 'left' }}: 3px solid #D1D5DB; }
.remarks-lbl { font-size: 7pt; text-transform: uppercase; color: #9CA3AF; margin-bottom: 3px; }
.remarks-text { font-size: 8.5pt; color: #4B5563; line-height: 1.5; }
</style>
</head>
<body>

{{-- ─── HEADER ─── --}}
<table class="hdr">
<tr>
<td class="hdr-left" width="55%">
    <div class="seller-name">{{ $invoice['seller_name'] ?? '—' }}</div>
    @if(!empty($invoice['seller_address']))
    <div class="seller-detail">{{ $invoice['seller_address'] }}</div>
    @endif
</td>
<td class="hdr-right" width="45%">
    <div class="doc-title">Commercial Invoice</div>
    <div class="doc-meta">
        <strong>{{ $invoice['invoice_no'] ?? '—' }}</strong>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        {{ $invoice['invoice_date'] ?? '—' }}
    </div>
</td>
</tr>
</table>

<hr class="accent-line">

{{-- ─── PARTIES ─── --}}
<table class="parties">
<tr>
<td class="party-cell party-left">
    <div class="party-label">Seller / Exporter</div>
    <div class="party-name">{{ $invoice['seller_name'] ?? '—' }}</div>
    @if(!empty($invoice['seller_address']))
    <div class="party-detail">{{ $invoice['seller_address'] }}</div>
    @endif
</td>
<td class="party-cell-gap"></td>
<td class="party-cell party-right">
    <div class="party-label">Buyer / Importer</div>
    <div class="party-name">{{ $invoice['buyer_name'] ?? '—' }}</div>
    @if(!empty($invoice['buyer_address']))
    <div class="party-detail">{{ $invoice['buyer_address'] }}</div>
    @endif
    @if(!empty($invoice['buyer_comm_card_no']))
    <div class="party-detail">Commercial Card: <strong>{{ $invoice['buyer_comm_card_no'] }}</strong></div>
    @endif
</td>
</tr>
</table>

{{-- ─── INFO BAR ─── --}}
<div class="info-bar">
<table class="info-grid">
<tr>
    @if(!empty($invoice['bl_number']))
    <td class="info-cell">
        <div class="info-lbl">B/L No.</div>
        <div class="info-val">{{ $invoice['bl_number'] }}</div>
    </td>
    @endif
    @if(!empty($invoice['transport_mode']))
    <td class="info-cell">
        <div class="info-lbl">Transport</div>
        <div class="info-val">{{ strtoupper($invoice['transport_mode']) }}</div>
    </td>
    @endif
    @if(!empty($invoice['incoterms']))
    <td class="info-cell">
        <div class="info-lbl">Incoterms</div>
        <div class="info-val">{{ $invoice['incoterms'] }}</div>
    </td>
    @endif
    @if(!empty($invoice['port_of_loading']))
    <td class="info-cell">
        <div class="info-lbl">Port of Loading</div>
        <div class="info-val">{{ $invoice['port_of_loading'] }}</div>
    </td>
    @endif
    @if(!empty($invoice['port_of_discharge']))
    <td class="info-cell">
        <div class="info-lbl">Port of Discharge</div>
        <div class="info-val">{{ $invoice['port_of_discharge'] }}</div>
    </td>
    @endif
    @if(!empty($invoice['etd']))
    <td class="info-cell">
        <div class="info-lbl">ETD</div>
        <div class="info-val">{{ $invoice['etd'] }}</div>
    </td>
    @endif
    @if(!empty($invoice['eta']))
    <td class="info-cell">
        <div class="info-lbl">ETA</div>
        <div class="info-val">{{ $invoice['eta'] }}</div>
    </td>
    @endif
    @if(!empty($invoice['currency']))
    <td class="info-cell">
        <div class="info-lbl">Currency</div>
        <div class="info-val">{{ $invoice['currency'] }}</div>
    </td>
    @endif
</tr>
</table>
</div>

{{-- ─── LINE ITEMS ─── --}}
<div class="items-wrap">
<table class="items">
<thead>
<tr>
    <th class="num">#</th>
    <th style="width:22%">Description</th>
    <th style="width:18%">Description (EN)</th>
    <th style="width:7%">HS Code</th>
    <th style="width:6%">Origin</th>
    <th style="width:6%">Qty</th>
    <th style="width:5%">Unit</th>
    <th style="width:9%" class="td-right">Unit Price</th>
    <th style="width:10%" class="td-right">Total</th>
    <th style="width:8%" class="td-right">Net Wt.</th>
    <th style="width:9%" class="td-right">Gross Wt.</th>
</tr>
</thead>
<tbody>
@php $rowNum = 0; @endphp
@forelse($invoice['items'] ?? [] as $item)
@php $rowNum++; @endphp
<tr>
    <td class="td-center td-mono">{{ $rowNum }}</td>
    <td>{{ $item['description'] ?? '' }}</td>
    <td>{{ $item['english_description'] ?? '' }}</td>
    <td class="td-mono">{{ $item['hs_code'] ?? '' }}</td>
    <td>{{ $item['origin'] ?? '' }}</td>
    <td class="td-center">{{ number_format((float)($item['quantity'] ?? 0), 2) }}</td>
    <td class="td-center">{{ $item['unit'] ?? '' }}</td>
    <td class="td-right td-mono">{{ number_format((float)($item['unit_price'] ?? 0), 2) }}</td>
    <td class="td-right td-mono"><strong>{{ number_format((float)($item['total_amount'] ?? 0), 2) }}</strong></td>
    <td class="td-right td-mono">{{ number_format((float)($item['net_weight'] ?? 0), 2) }}</td>
    <td class="td-right td-mono">{{ number_format((float)($item['gross_weight'] ?? 0), 2) }}</td>
</tr>
@empty
<tr><td colspan="11" style="text-align:center;color:#9CA3AF;padding:12px;">No items</td></tr>
@endforelse
</tbody>
</table>
</div>

{{-- ─── TOTALS ─── --}}
<table class="totals-wrap">
<tr>
<td class="totals-weights">
    <table style="border-collapse:collapse;width:100%">
    <tr>
    <td style="padding-right:8px;">
        <div class="wt-box">
            <div class="wt-label">Total Net Weight</div>
            <div class="wt-value">{{ number_format((float)($invoice['total_net_weight'] ?? 0), 2) }} kg</div>
        </div>
    </td>
    <td>
        <div class="wt-box">
            <div class="wt-label">Total Gross Weight</div>
            <div class="wt-value">{{ number_format((float)($invoice['total_gross_weight'] ?? 0), 2) }} kg</div>
        </div>
    </td>
    </tr>
    </table>
</td>
<td class="totals-summary">
    <table class="sum-table">
    <tr class="sum-row">
        <td class="sum-lbl">Subtotal</td>
        <td class="sum-val">{{ $invoice['currency'] ?? '' }} {{ number_format((float)($invoice['subtotal'] ?? 0), 2) }}</td>
    </tr>
    @if(($invoice['discount'] ?? 0) != 0)
    <tr class="sum-row">
        <td class="sum-lbl">Discount</td>
        <td class="sum-val sum-discount">({{ number_format((float)$invoice['discount'], 2) }})</td>
    </tr>
    @endif
    @if(($invoice['freight_charges'] ?? 0) != 0)
    <tr class="sum-row">
        <td class="sum-lbl">Freight Charges</td>
        <td class="sum-val">{{ number_format((float)$invoice['freight_charges'], 2) }}</td>
    </tr>
    @endif
    @if(($invoice['other_charges'] ?? 0) != 0)
    <tr class="sum-row">
        <td class="sum-lbl">Other Charges</td>
        <td class="sum-val">{{ number_format((float)$invoice['other_charges'], 2) }}</td>
    </tr>
    @endif
    <tr class="grand-row">
        <td class="grand-lbl">GRAND TOTAL</td>
        <td class="grand-val">{{ $invoice['currency'] ?? '' }} {{ number_format((float)($invoice['grand_total'] ?? 0), 2) }}</td>
    </tr>
    </table>
</td>
</tr>
</table>

{{-- ─── TERMS ─── --}}
@if(!empty($invoice['payment_terms']) || !empty($invoice['origin_country']) || !empty($invoice['destination_country']))
<div class="terms-bar">
<table class="terms-grid">
<tr>
    @if(!empty($invoice['payment_terms']))
    <td class="terms-cell">
        <div class="terms-lbl">Payment Terms</div>
        <div class="terms-val">{{ $invoice['payment_terms'] }}</div>
    </td>
    @endif
    @if(!empty($invoice['origin_country']))
    <td class="terms-cell">
        <div class="terms-lbl">Country of Origin</div>
        <div class="terms-val">{{ $invoice['origin_country'] }}</div>
    </td>
    @endif
    @if(!empty($invoice['destination_country']))
    <td class="terms-cell">
        <div class="terms-lbl">Country of Destination</div>
        <div class="terms-val">{{ $invoice['destination_country'] }}</div>
    </td>
    @endif
</tr>
</table>
</div>
@endif

{{-- ─── REMARKS ─── --}}
@if(!empty($invoice['notes']))
<div class="remarks">
    <div class="remarks-lbl">Remarks</div>
    <div class="remarks-text">{{ $invoice['notes'] }}</div>
</div>
@endif

</body>
</html>
