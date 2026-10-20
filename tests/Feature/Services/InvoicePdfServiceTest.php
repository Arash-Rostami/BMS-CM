<?php

namespace Tests\Feature\Services;

use App\Services\InvoicePdfService;
use Illuminate\Http\Response;
use Mpdf\Mpdf;
use Tests\TestCase;

class InvoicePdfServiceTest extends TestCase
{
    private function invoice(array $overrides = []): array
    {
        return array_merge([
            'invoice_no' => 'INV-100',
            'invoice_date' => '2026-10-06',
            'seller_name' => 'ACME Exports',
            'buyer_name' => 'Importer Co',
            'currency' => 'USD',
            'grand_total' => 1250,
            'items' => [
                ['name' => 'Bolts', 'quantity' => 10, 'unit' => 'box', 'unit_price' => 125, 'total_amount' => 1250],
            ],
        ], $overrides);
    }

    public function test_generate_builds_an_ltr_pdf_for_non_fa_locales(): void
    {
        $mpdf = app(InvoicePdfService::class)->generate($this->invoice(), 'en');

        $this->assertInstanceOf(Mpdf::class, $mpdf);
        $this->assertSame('ltr', $mpdf->directionality);
    }

    public function test_generate_builds_an_rtl_pdf_for_the_fa_locale(): void
    {
        $mpdf = app(InvoicePdfService::class)->generate($this->invoice(), 'fa');

        $this->assertSame('rtl', $mpdf->directionality);
    }

    public function test_download_returns_an_inline_pdf_response_named_after_the_invoice_number(): void
    {
        $response = app(InvoicePdfService::class)->download($this->invoice(), 'en');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('inline; filename="Invoice-INV-100.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_view_prints_english_country_unit_and_item_names_and_falls_back_to_raw_values(): void
    {
        $invoice = $this->invoice([
            'origin_country' => 'CN',
            'destination_country' => 'IR',
            'items' => [
                ['name' => 'کالا', 'english_name' => 'Goods', 'origin' => 'CN', 'unit' => 'kg', 'quantity' => 2, 'unit_price' => 5, 'total_amount' => 10],
                ['name' => 'Misc', 'english_name' => 'Misc EN', 'origin' => 'Atlantis', 'unit' => 'box', 'quantity' => 1, 'unit_price' => 1, 'total_amount' => 1],
            ],
        ]);

        $html = view('pdf.commercial-invoice', ['invoice' => $invoice, 'locale' => 'en', 'isRtl' => false])->render();

        $kg = trans('resources/general/strings.metrics', [], 'en')['kg'];

        $this->assertStringContainsString('<td>China</td>', $html);
        $this->assertStringContainsString('<div class="terms-val">China</div>', $html);
        $this->assertStringContainsString('<div class="terms-val">Iran', $html);
        $this->assertStringContainsString('<td class="td-center">'.$kg.'</td>', $html);
        $this->assertStringContainsString('<td>Goods</td>', $html);
        $this->assertStringContainsString('<td>Atlantis</td>', $html);
        $this->assertStringContainsString('<td class="td-center">box</td>', $html);
    }

    public function test_download_falls_back_to_a_timestamped_filename_without_an_invoice_number(): void
    {
        $response = app(InvoicePdfService::class)->download($this->invoice(['invoice_no' => null]), 'en');

        $this->assertMatchesRegularExpression(
            '/inline; filename="Invoice-\d{14}\.pdf"/',
            $response->headers->get('Content-Disposition')
        );
    }
}