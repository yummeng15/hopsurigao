<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Carbon\Carbon;
use Illuminate\Support\Str;

abstract class BaseReportService
{
    abstract public function getReportSlug(): string;

    abstract public function getReportTitle(): string;

    abstract public function getViewName(): string;

    abstract public function getData(array $filters): array;

    /**
     * Parse and normalize the date range from filters.
     *
     * @return array{0: string|null, 1: string|null, 2: string} [date_from, date_to, period_label]
     */
    protected function parseDateRange(array $filters, string $defaultRange = 'this_month'): array
    {
        $dateFrom = !empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $dateTo = !empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null;

        if (!$dateFrom && !$dateTo) {
            if ($defaultRange === 'this_month') {
                $dateFrom = Carbon::now()->startOfMonth();
                $dateTo = Carbon::now()->endOfMonth();
            } elseif ($defaultRange === 'all') {
                return [null, null, 'All Time'];
            }
        } elseif ($dateFrom && !$dateTo) {
            $dateTo = (clone $dateFrom)->endOfMonth();
        } elseif (!$dateFrom && $dateTo) {
            $dateFrom = (clone $dateTo)->startOfMonth();
        }

        // If dateFrom is after dateTo, swap them
        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            $temp = $dateFrom;
            $dateFrom = $dateTo;
            $dateTo = $temp;
        }

        $fromStr = $dateFrom ? $dateFrom->format('Y-m-d') : null;
        $toStr = $dateTo ? $dateTo->format('Y-m-d') : null;

        $periodLabel = ($dateFrom && $dateTo)
            ? $dateFrom->format('M d, Y') . ' — ' . $dateTo->format('M d, Y')
            : 'All Time';

        return [$fromStr, $toStr, $periodLabel];
    }

    /**
     * Generate the downloadable PDF instance.
     */
    public function generatePdf(array $filters, ?string $generatedBy = null): DomPdfWrapper
    {
        $reportData = $this->getData($filters);

        $payload = [
            'reportTitle' => $this->getReportTitle(),
            'reportSlug' => $this->getReportSlug(),
            'periodLabel' => $reportData['period_label'] ?? 'All Time',
            'generatedAt' => Carbon::now()->format('F d, Y h:i A'),
            'generatedBy' => $generatedBy ?? (auth()->user()?->name ?? 'System Operator'),
            'filters' => $filters,
            'summary' => $reportData['summary'] ?? [],
            'records' => $reportData['records'] ?? [],
            'meta' => $reportData['meta'] ?? [],
        ];

        /** @var DomPdfWrapper $pdf */
        $pdf = Pdf::loadView($this->getViewName(), $payload);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'sans-serif',
        ]);

        return $pdf;
    }

    /**
     * Build standard descriptive filename.
     */
    public function getFilename(array $filters): string
    {
        [$from, $to] = $this->parseDateRange($filters);
        $slug = Str::slug($this->getReportSlug());

        if ($from && $to) {
            return "{$slug}-report-{$from}-to-{$to}.pdf";
        }

        return "{$slug}-report-" . Carbon::now()->format('Y-m-d') . ".pdf";
    }
}

