<?php

namespace App\Services;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class InvoicePdfService
{
    public function generate(array $invoice, string $locale = 'en'): Mpdf
    {
        $isRtl = $locale === 'fa';

        $defaultFontConfig = (new ConfigVariables())->getDefaults();
        $defaultFontData   = (new FontVariables())->getDefaults()['fontdata'];

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'margin_left'    => 12,
            'margin_right'   => 12,
            'margin_top'     => 10,
            'margin_bottom'  => 15,
            'margin_header'  => 0,
            'margin_footer'  => 8,
            'fontDir'        => array_merge($defaultFontConfig['fontDir'], [resource_path('fonts')]),
            'fontdata'       => array_merge($defaultFontData, [
                'iranyekan' => ['R' => 'Iranyekan.ttf'],
            ]),
            'default_font'   => $isRtl ? 'iranyekan' : 'dejavusans',
            'directionality' => $isRtl ? 'rtl' : 'ltr',
        ]);

        $mpdf->SetFooter(
            '<table width="100%" style="font-size:7pt;color:#9CA3AF;border-top:1px solid #E5E7EB;padding-top:4px;">'
            . '<tr><td width="33%">' . ($invoice['seller_name'] ?? '') . '</td>'
            . '<td width="34%" align="center">COMMERCIAL INVOICE</td>'
            . '<td width="33%" align="right">{PAGENO} / {nb}</td></tr></table>'
        );

        $html = view('pdf.commercial-invoice', compact('invoice', 'locale', 'isRtl'))->render();
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    public function download(array $invoice, string $locale = 'en'): \Illuminate\Http\Response
    {
        $mpdf     = $this->generate($invoice, $locale);
        $filename = 'Invoice-' . ($invoice['invoice_no'] ?? now()->format('YmdHis')) . '.pdf';

        return response(
            $mpdf->Output($filename, 'S'),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
    }
}
