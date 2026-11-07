@extends('reports.layout')

@section('content')
    <!-- Summary Cards -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-card" style="width: 33.3%;">
                    <div class="summary-label">Total Dispatch Runs</div>
                    <div class="summary-value">{{ number_format($summary['total_pickup_runs'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 33.3%;">
                    <div class="summary-label">Total Guests to Pickup</div>
                    <div class="summary-value" style="color: #0369a1;">{{ number_format($summary['total_guests_to_pickup'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 33.3%;">
                    <div class="summary-label">Designated Pickup Points</div>
                    <div class="summary-value" style="color: #16a34a;">{{ number_format($summary['distinct_locations_count'] ?? 0) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Driver Dispatch & Passenger Manifests</div>

    @if(empty($records))
        <div class="empty-state">
            <strong>No pickup dispatch schedules found matching the selected criteria.</strong>
        </div>
    @else
        @foreach($records as $run)
            <div style="margin-bottom: 12px; page-break-inside: avoid;">
                <table style="width: 100%; border-collapse: collapse; background-color: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 8px; border-radius: 4px; margin-bottom: 4px;">
                    <tr>
                        <td style="width: 45%;">
                            <span class="font-bold" style="font-size: 11px; color: #0f172a;">{{ $run['location_name'] }}</span>
                            <div class="text-muted" style="font-size: 8px;">{{ $run['location_address'] }}</div>
                        </td>
                        <td style="width: 25%;">
                            <div class="font-bold">Tour: {{ $run['tour_date'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">{{ $run['package_name'] }}</div>
                        </td>
                        <td style="width: 15%; text-align: center;">
                            <span class="badge badge-blue">Time: {{ $run['pickup_time'] }}</span>
                        </td>
                        <td style="width: 15%; text-align: right;">
                            <span class="font-bold" style="font-size: 11px; color: #0284c7;">{{ $run['guests_count'] }} Guests</span>
                        </td>
                    </tr>
                </table>

                @if(empty($run['passengers']))
                    <div style="padding: 6px 10px; background-color: #fafafa; border: 1px solid #e2e8f0; font-size: 8.5px; color: #94a3b8; margin-bottom: 8px;">
                        No active guest bookings currently assigned to this pickup run.
                    </div>
                @else
                    <table class="data-table" style="margin-bottom: 8px;">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Booking ID</th>
                                <th style="width: 40%;">Lead Passenger Name</th>
                                <th style="width: 25%;">Contact Number</th>
                                <th style="width: 10%; text-align: center;">Guests</th>
                                <th style="width: 10%; text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($run['passengers'] as $p)
                                <tr>
                                    <td class="font-bold">#{{ $p['booking_id'] }}</td>
                                    <td class="font-bold">{{ $p['lead_name'] }}</td>
                                    <td>{{ $p['phone_number'] }}</td>
                                    <td class="text-center font-bold">{{ $p['guests'] }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $p['status'] === 'confirmed' ? 'badge-green' : 'badge-yellow' }}">
                                            {{ ucfirst($p['status']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    @endif
@endsection

