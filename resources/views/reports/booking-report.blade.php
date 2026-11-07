@extends('reports.layout')

@section('content')
    <!-- Summary Cards -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Total Bookings</div>
                    <div class="summary-value">{{ number_format($summary['total_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Confirmed</div>
                    <div class="summary-value" style="color: #16a34a;">{{ number_format($summary['confirmed_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Pending</div>
                    <div class="summary-value" style="color: #ea580c;">{{ number_format($summary['pending_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Completed</div>
                    <div class="summary-value" style="color: #0284c7;">{{ number_format($summary['completed_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Cancelled</div>
                    <div class="summary-value" style="color: #dc2626;">{{ number_format($summary['cancelled_bookings'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Total Guests</div>
                    <div class="summary-value">{{ number_format($summary['total_guests'] ?? 0) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Booking Records Manifest</div>

    @if(empty($records))
        <div class="empty-state">
            <strong>No booking records found matching the selected criteria.</strong>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 6%;">ID</th>
                    <th style="width: 18%;">Tourist / Contact</th>
                    <th style="width: 18%;">Package & Destination</th>
                    <th style="width: 12%;">Tour Date</th>
                    <th style="width: 18%;">Pickup Point</th>
                    <th style="width: 6%; text-align: center;">Guests</th>
                    <th style="width: 11%; text-align: center;">Booking</th>
                    <th style="width: 11%; text-align: center;">Payment</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $row)
                    <tr>
                        <td class="font-bold">#{{ $row['id'] }}</td>
                        <td>
                            <div class="font-bold">{{ $row['tourist_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">{{ $row['phone_number'] }} • {{ $row['nationality'] }}</div>
                        </td>
                        <td>
                            <div class="font-bold">{{ $row['package_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">{{ $row['destination'] }}</div>
                        </td>
                        <td>{{ $row['tour_date'] }}</td>
                        <td>
                            <div>{{ $row['pickup_location'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">Time: {{ $row['pickup_time'] }}</div>
                        </td>
                        <td class="text-center font-bold">{{ $row['number_of_guests'] }}</td>
                        <td class="text-center">
                            @if($row['booking_status'] === 'confirmed')
                                <span class="badge badge-green">Confirmed</span>
                            @elseif($row['booking_status'] === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @elseif($row['booking_status'] === 'cancelled')
                                <span class="badge badge-red">Cancelled</span>
                            @elseif($row['booking_status'] === 'completed')
                                <span class="badge badge-blue">Completed</span>
                            @else
                                <span class="badge badge-gray">{{ ucfirst($row['booking_status']) }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if(in_array($row['payment_status'], ['paid', 'confirmed']))
                                <span class="badge badge-green">Paid</span>
                            @elseif($row['payment_status'] === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @elseif(in_array($row['payment_status'], ['rejected', 'cancelled']))
                                <span class="badge badge-red">{{ ucfirst($row['payment_status']) }}</span>
                            @else
                                <span class="badge badge-gray">Unpaid</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

