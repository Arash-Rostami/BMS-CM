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
                ['description' => 'Bolts', 'quantity' => 10, 'unit' => 'box', 'unit_price' => 125, 'total_amount' => 1250],
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

    public function test_download_falls_back_to_a_timestamped_filename_without_an_invoice_number(): void
    {
        $response = app(InvoicePdfService::class)->download($this->invoice(['invoice_no' => null]), 'en');

        $this->assertMatchesRegularExpression(
            '/inline; filename="Invoice-\d{14}\.pdf"/',
            $response->headers->get('Content-Disposition')
        );
    }
}