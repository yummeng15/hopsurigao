@extends('reports.layout')

@section('content')
    <!-- Summary Cards -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-card" style="width: 25%;">
                    <div class="summary-label">Lead Bookers</div>
                    <div class="summary-value">{{ number_format($summary['total_lead_travelers'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <div class="summary-label">Total Individual Guests</div>
                    <div class="summary-value" style="color: #0369a1;">{{ number_format($summary['total_guests'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <div class="summary-label">Active Manifest Guests</div>
                    <div class="summary-value" style="color: #16a34a;">{{ number_format($summary['active_guests'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <div class="summary-label">Represented Nationalities</div>
                    <div class="summary-value">{{ number_format($summary['nationalities_count'] ?? 0) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Nationality Demographics Table -->
    @if(!empty($summary['nationalities_breakdown']))
        <div style="margin-bottom: 14px;">
            <div class="section-title">Nationality Demographics Summary</div>
            <table class="data-table" style="margin-bottom: 8px;">
                <thead>
                    <tr>
                        <th style="width: 50%;">Nationality / Country</th>
                        <th style="width: 25%; text-align: center;">Bookings Count</th>
                        <th style="width: 25%; text-align: center;">Total Guests</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['nationalities_breakdown'] as $nat => $data)
                        <tr>
                            <td class="font-bold">{{ $nat }}</td>
                            <td class="text-center">{{ $data['bookings_count'] }}</td>
                            <td class="text-center font-bold" style="color: #0369a1;">{{ $data['guests_count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="section-title">Tourist & Guest Manifest</div>

    @if(empty($records))
        <div class="empty-state">
            <strong>No tourist records found matching the selected criteria.</strong>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 6%;">ID</th>
                    <th style="width: 20%;">Lead Guest Name</th>
                    <th style="width: 14%;">Contact Number</th>
                    <th style="width: 12%;">Nationality</th>
                    <th style="width: 18%;">Package Destination</th>
                    <th style="width: 12%;">Tour Date</th>
                    <th style="width: 6%; text-align: center;">Party</th>
                    <th style="width: 12%; text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $row)
                    <tr>
                        <td class="font-bold">#{{ $row['id'] }}</td>
                        <td>
                            <div class="font-bold">{{ $row['tourist_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">{{ $row['tourist_email'] }}</div>
                        </td>
                        <td>{{ $row['phone_number'] }}</td>
                        <td>{{ $row['nationality'] }}</td>
                        <td>
                            <div>{{ $row['package_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">Pickup: {{ $row['pickup_location'] }}</div>
                        </td>
                        <td>{{ $row['tour_date'] }}</td>
                        <td class="text-center font-bold">{{ $row['number_of_guests'] }}</td>
                        <td class="text-center">
                            @if($row['booking_status'] === 'confirmed')
                                <span class="badge badge-green">Confirmed</span>
                            @elseif($row['booking_status'] === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @elseif($row['booking_status'] === 'cancelled')
                                <span class="badge badge-red">Cancelled</span>
                            @else
                                <span class="badge badge-blue">{{ ucfirst($row['booking_status']) }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

