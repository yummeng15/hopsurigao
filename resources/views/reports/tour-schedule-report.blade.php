@extends('reports.layout')

@section('content')
    <!-- Summary Cards -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Total Schedules</div>
                    <div class="summary-value">{{ number_format($summary['total_schedules'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Total Capacity</div>
                    <div class="summary-value">{{ number_format($summary['total_capacity'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Booked Guests</div>
                    <div class="summary-value" style="color: #16a34a;">{{ number_format($summary['total_booked_guests'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Available Slots</div>
                    <div class="summary-value" style="color: #0284c7;">{{ number_format($summary['total_available_slots'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Avg. Occupancy</div>
                    <div class="summary-value" style="color: #0369a1;">{{ $summary['average_occupancy_rate'] ?? 0 }}%</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Tour Schedules & Boat Manifests</div>

    @if(empty($records))
        <div class="empty-state">
            <strong>No tour schedules found matching the selected criteria.</strong>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Tour Date</th>
                    <th style="width: 22%;">Package & Destination</th>
                    <th style="width: 8%; text-align: center;">Capacity</th>
                    <th style="width: 8%; text-align: center;">Booked</th>
                    <th style="width: 8%; text-align: center;">Available</th>
                    <th style="width: 10%; text-align: center;">Occupancy</th>
                    <th style="width: 18%;">Pickup Points</th>
                    <th style="width: 12%; text-align: center;">Weather Advisory</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $row)
                    <tr>
                        <td class="font-bold">{{ $row['tour_date'] }}</td>
                        <td>
                            <div class="font-bold">{{ $row['package_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">{{ $row['destination'] }}</div>
                        </td>
                        <td class="text-center">{{ $row['capacity'] }}</td>
                        <td class="text-center font-bold" style="color: #16a34a;">{{ $row['booked_guests'] }}</td>
                        <td class="text-center font-bold" style="color: #0284c7;">{{ $row['available_slots'] }}</td>
                        <td class="text-center">
                            <span class="badge {{ $row['occupancy_percent'] >= 80 ? 'badge-red' : ($row['occupancy_percent'] >= 50 ? 'badge-yellow' : 'badge-blue') }}">
                                {{ $row['occupancy_percent'] }}%
                            </span>
                        </td>
                        <td style="font-size: 8.5px;">{{ $row['pickup_points'] }}</td>
                        <td class="text-center">
                            @if($row['advisory_level'] === 'green')
                                <span class="badge badge-green">Normal / Safe</span>
                            @elseif($row['advisory_level'] === 'yellow')
                                <span class="badge badge-yellow">Caution</span>
                            @elseif($row['advisory_level'] === 'orange')
                                <span class="badge badge-orange">Warning</span>
                            @elseif($row['advisory_level'] === 'red')
                                <span class="badge badge-red">Severe</span>
                            @else
                                <span class="badge badge-gray">N/A</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

