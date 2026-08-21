<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class DailyAccountReportService
{
    public function __construct(
        private readonly DailyAccountService $accounts,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(string $date): array
    {
        $pack = $this->accounts->dayReport($date);

        return [
            ...$pack,
            'date' => $date,
            'company' => $this->invoices->company(),
            'printed_at' => now(),
        ];
    }

    public function pdf(string $date)
    {
        return Pdf::loadView('pdf.daily-account-day', $this->report($date))
            ->setPaper('a4');
    }

    public function download(string $date): Response
    {
        $filename = 'daily-accounts-'.$date.'.pdf';

        return $this->pdf($date)->download($filename);
    }
}
