@extends('reports.layout')

@section('content')
    <!-- Summary Cards -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Forecasted Dates</div>
                    <div class="summary-value">{{ number_format($summary['total_forecasted_dates'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Safe (Green)</div>
                    <div class="summary-value" style="color: #16a34a;">{{ number_format($summary['safe_green_count'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Caution (Yellow)</div>
                    <div class="summary-value" style="color: #ca8a04;">{{ number_format($summary['caution_yellow_count'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Warning (Orange)</div>
                    <div class="summary-value" style="color: #ea580c;">{{ number_format($summary['warning_orange_count'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Severe (Red)</div>
                    <div class="summary-value" style="color: #dc2626;">{{ number_format($summary['severe_red_count'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 16.6%;">
                    <div class="summary-label">Affected Guests</div>
                    <div class="summary-value" style="color: #ea580c;">{{ number_format($summary['affected_guests_in_advisory'] ?? 0) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Maritime Weather Safety & PAGASA Synchronized Manifest</div>

    @if(empty($records))
        <div class="empty-state">
            <strong>No weather advisory records found matching the selected criteria.</strong>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 13%;">Forecast Date</th>
                    <th style="width: 18%;">Package Destination</th>
                    <th style="width: 14%;">Weather Condition</th>
                    <th style="width: 11%;">Temperature</th>
                    <th style="width: 11%;">Wind & Rain</th>
                    <th style="width: 12%; text-align: center;">Advisory Level</th>
                    <th style="width: 13%;">Maritime Advisory Note</th>
                    <th style="width: 8%; text-align: center;">Guests</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $row)
                    <tr>
                        <td class="font-bold">{{ $row['forecast_date'] }}</td>
                        <td>
                            <div class="font-bold">{{ $row['package_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">{{ $row['destination'] }}</div>
                        </td>
                        <td>{{ $row['weather_condition'] }}</td>
                        <td>{{ $row['temperature_range'] }}</td>
                        <td>
                            <div>Wind: {{ $row['wind_speed'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">Rain: {{ $row['rainfall_probability'] }}</div>
                        </td>
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
                                <span class="badge badge-gray">{{ ucfirst($row['advisory_level']) }}</span>
                            @endif
                        </td>
                        <td style="font-size: 8.5px;">{{ $row['advisory_message'] }}</td>
                        <td class="text-center font-bold" style="color: {{ in_array($row['advisory_level'], ['yellow', 'orange', 'red']) ? '#ea580c' : '#16a34a' }};">
                            {{ $row['booked_guests'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

