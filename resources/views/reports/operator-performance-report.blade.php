@extends('reports.layout')

@section('content')
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Operators</div>
                    <div class="summary-value">{{ number_format($summary['total_operators'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Total Bookings</div>
                    <div class="summary-value">{{ number_format($summary['overall_total_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Confirmed / Completed</div>
                    <div class="summary-value" style="color: #0369a1;">{{ number_format($summary['overall_confirmed_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Cancelled</div>
                    <div class="summary-value" style="color: #dc2626;">{{ number_format($summary['overall_cancelled_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Verified Revenue</div>
                    <div class="summary-value" style="color: #16a34a;">₱{{ number_format($summary['overall_total_revenue'] ?? 0, 2) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Bookings per Tour Operator</div>
    <p class="text-muted" style="margin-bottom: 10px; font-size: 10px;">
        GCash (PayMongo Test Mode): ₱{{ number_format($summary['overall_gcash_revenue'] ?? 0, 2) }}
        · Walk-in Cash: ₱{{ number_format($summary['overall_cash_revenue'] ?? 0, 2) }}
    </p>

    @if(empty($records))
        <div class="empty-state">
            <strong>No operator booking data found matching the selected criteria.</strong>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 28%;">Tour Operator</th>
                    <th style="width: 12%; text-align: center;">Status</th>
                    <th style="width: 12%; text-align: center;">Total Bookings</th>
                    <th style="width: 12%; text-align: center;">Confirmed</th>
                    <th style="width: 12%; text-align: center;">Cancelled</th>
                    <th style="width: 12%; text-align: right;">GCash Revenue</th>
                    <th style="width: 12%; text-align: right;">Cash Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $row)
                    <tr>
                        <td class="font-bold">{{ $row['operator_name'] }}</td>
                        <td style="text-align: center;">{{ ucfirst((string) ($row['approval_status'] ?? 'n/a')) }}</td>
                        <td style="text-align: center;">{{ number_format($row['total_bookings']) }}</td>
                        <td style="text-align: center;">{{ number_format($row['confirmed_bookings']) }}</td>
                        <td style="text-align: center;">{{ number_format($row['cancelled_bookings']) }}</td>
                        <td style="text-align: right;">₱{{ number_format($row['gcash_revenue'], 2) }}</td>
                        <td style="text-align: right;">₱{{ number_format($row['cash_revenue'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
