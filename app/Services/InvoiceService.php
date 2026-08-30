<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoiceService
{
    public function pdf(Sale $sale)
    {
        $sale->load(['items.product.unit', 'customer', 'payments.receiver', 'creator']);

        return Pdf::loadView('pdf.invoice', [
            'sale' => $sale,
            'company' => $this->company(),
        ])->setPaper('a4');
    }

    public function download(Sale $sale): Response
    {
        $filename = ($sale->invoice_no ?? 'draft').'.pdf';

        return $this->pdf($sale)->download($filename);
    }

    public function stream(Sale $sale): Response
    {
        $filename = ($sale->invoice_no ?? 'draft').'.pdf';

        return $this->pdf($sale)->stream($filename);
    }

    /**
     * @return array<string, mixed>
     */
    public function company(): array
    {
        return [
            'name' => Setting::get('company_name', config('brand.name')),
            'tagline' => config('brand.tagline'),
            'address' => Setting::get('company_address', config('brand.address')),
            'phone' => Setting::get('company_phone', config('brand.phone', '')),
            'email' => Setting::get('company_email', ''),
            'currency' => Setting::get('currency', 'Rs.'),
            'logo' => Setting::get('company_logo'),
        ];
    }
}
