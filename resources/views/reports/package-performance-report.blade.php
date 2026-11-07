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

    <div class="section-title">Package Performance & Popularity Ranking</div>

    @if(empty($records))
        <div class="empty-state">
            <strong>No package performance data found matching the selected criteria.</strong>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22%;">Package Name</th>
                    <th style="width: 14%;">Destination</th>
                    <th style="width: 11%; text-align: right;">Rate / Guest</th>
                    <th style="width: 9%; text-align: center;">Bookings</th>
                    <th style="width: 9%; text-align: center;">Confirmed</th>
                    <th style="width: 8%; text-align: center;">Cancelled</th>
                    <th style="width: 8%; text-align: center;">Guests</th>
                    <th style="width: 11%; text-align: center;">Conversion</th>
                    <th style="width: 14%; text-align: right;">Verified Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $row)
                    <tr>
                        <td class="font-bold">
                            <div>{{ $row['package_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">Status: {{ ucfirst($row['status']) }}</div>
                        </td>
                        <td>{{ $row['destination'] }}</td>
                        <td class="text-right">₱{{ number_format($row['price'], 2) }}</td>
                        <td class="text-center font-bold">{{ $row['total_bookings'] }}</td>
                        <td class="text-center font-bold" style="color: #16a34a;">{{ $row['confirmed_bookings'] }}</td>
                        <td class="text-center" style="color: #dc2626;">{{ $row['cancelled_bookings'] }}</td>
                        <td class="text-center font-bold" style="color: #0369a1;">{{ $row['total_guests'] }}</td>
                        <td class="text-center">
                            <span class="badge {{ $row['conversion_rate'] >= 70 ? 'badge-green' : ($row['conversion_rate'] >= 40 ? 'badge-yellow' : 'badge-gray') }}">
                                {{ $row['conversion_rate'] }}%
                            </span>
                        </td>
                        <td class="text-right font-bold" style="color: #16a34a;">
                            ₱{{ number_format($row['revenue'], 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

