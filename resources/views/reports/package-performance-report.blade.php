@extends('reports.layout')

@section('content')
    <!-- Summary Cards -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Active Packages</div>
                    <div class="summary-value">{{ number_format($summary['total_packages_count'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Total Reservations</div>
                    <div class="summary-value">{{ number_format($summary['overall_total_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Total Guests Catered</div>
                    <div class="summary-value" style="color: #0369a1;">{{ number_format($summary['overall_total_guests'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Total Revenue</div>
                    <div class="summary-value" style="color: #16a34a;">₱{{ number_format($summary['overall_total_revenue'] ?? 0, 2) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Top Package (Revenue)</div>
                    <div class="summary-value" style="font-size: 10px; color: #0284c7;">{{ $summary['highest_revenue_package'] ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Bookings per Package</div>
    <p class="text-muted" style="margin-bottom: 10px; font-size: 10px;">
        GCash (PayMongo Test Mode): ₱{{ number_format($summary['overall_gcash_revenue'] ?? 0, 2) }}
        · Walk-in Cash: ₱{{ number_format($summary['overall_cash_revenue'] ?? 0, 2) }}
    </p>

    @if(empty($records))
        <div class="empty-state">
            <strong>No package performance data found matching the selected criteria.</strong>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Package Name</th>
                    <th style="width: 14%;">Tour Operator</th>
                    <th style="width: 10%;">Destination</th>
                    <th style="width: 8%; text-align: center;">Bookings</th>
                    <th style="width: 8%; text-align: center;">Confirmed</th>
                    <th style="width: 8%; text-align: center;">Cancelled</th>
                    <th style="width: 8%; text-align: center;">Guests</th>
                    <th style="width: 12%; text-align: right;">GCash</th>
                    <th style="width: 12%; text-align: right;">Cash</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $row)
                    <tr>
                        <td class="font-bold">
                            <div>{{ $row['package_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">Status: {{ ucfirst($row['status']) }}</div>
                        </td>
                        <td>{{ $row['operator_name'] ?? 'Unassigned' }}</td>
                        <td>{{ $row['destination'] }}</td>
                        <td class="text-center font-bold">{{ $row['total_bookings'] }}</td>
                        <td class="text-center font-bold" style="color: #16a34a;">{{ $row['confirmed_bookings'] }}</td>
                        <td class="text-center" style="color: #dc2626;">{{ $row['cancelled_bookings'] }}</td>
                        <td class="text-center font-bold" style="color: #0369a1;">{{ $row['total_guests'] }}</td>
                        <td class="text-right">₱{{ number_format($row['gcash_revenue'] ?? 0, 2) }}</td>
                        <td class="text-right">₱{{ number_format($row['cash_revenue'] ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

