@extends('reports.layout')

@section('content')
    <!-- Summary Cards -->
    <div class="summary-container">
        <table class="summary-table">
            <tr>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Total Transactions</div>
                    <div class="summary-value">{{ number_format($summary['total_payments_count'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Total Revenue (Verified)</div>
                    <div class="summary-value" style="color: #16a34a;">₱{{ number_format($summary['total_revenue'] ?? 0, 2) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Pending Verification</div>
                    <div class="summary-value" style="color: #ea580c;">₱{{ number_format($summary['pending_verification_amount'] ?? 0, 2) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Paid / Confirmed</div>
                    <div class="summary-value" style="color: #16a34a;">{{ number_format($summary['paid_confirmed_count'] ?? 0) }}</div>
                </td>
                <td class="summary-card" style="width: 20%;">
                    <div class="summary-label">Pending / Rejected</div>
                    <div class="summary-value" style="color: #dc2626;">{{ number_format(($summary['pending_count'] ?? 0) + ($summary['rejected_count'] ?? 0)) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Payment Channels Breakdown -->
    @if(!empty($summary['methods_breakdown']))
        <div style="margin-bottom: 12px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 8.5px;">
                <tr>
                    <td style="font-weight: bold; color: #475569; width: 120px;">Channels Distribution:</td>
                    @foreach($summary['methods_breakdown'] as $method => $data)
                        <td style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 3px 6px; text-align: center;">
                            <strong>{{ $method }}:</strong> {{ $data['count'] }} txns (₱{{ number_format($data['total'], 2) }})
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    <div class="section-title">Financial Transaction Audit</div>

    @if(empty($records))
        <div class="empty-state">
            <strong>No payment transactions found matching the selected criteria.</strong>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 7%;">ID</th>
                    <th style="width: 8%;">Booking</th>
                    <th style="width: 18%;">Tourist / Payer</th>
                    <th style="width: 20%;">Package & Tour Date</th>
                    <th style="width: 12%; text-align: right;">Amount (PHP)</th>
                    <th style="width: 13%;">Channel & Ref #</th>
                    <th style="width: 11%; text-align: center;">Status</th>
                    <th style="width: 11%;">Paid Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $row)
                    <tr>
                        <td class="font-bold">#{{ $row['id'] }}</td>
                        <td>#{{ $row['booking_id'] }}</td>
                        <td class="font-bold">{{ $row['tourist_name'] }}</td>
                        <td>
                            <div class="font-bold">{{ $row['package_name'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">Tour: {{ $row['tour_date'] }}</div>
                        </td>
                        <td class="text-right font-bold" style="color: #0c4a6e;">
                            ₱{{ number_format($row['amount'], 2) }}
                        </td>
                        <td>
                            <div>{{ $row['payment_method'] }}</div>
                            <div class="text-muted" style="font-size: 8px;">{{ $row['transaction_reference'] }}</div>
                        </td>
                        <td class="text-center">
                            @if(in_array($row['payment_status'], ['paid', 'confirmed']))
                                <span class="badge badge-green">Paid</span>
                            @elseif($row['payment_status'] === 'pending')
                                <span class="badge badge-yellow">Pending</span>
                            @elseif(in_array($row['payment_status'], ['rejected', 'cancelled']))
                                <span class="badge badge-red">{{ ucfirst($row['payment_status']) }}</span>
                            @else
                                <span class="badge badge-gray">{{ ucfirst($row['payment_status']) }}</span>
                            @endif
                        </td>
                        <td>{{ $row['paid_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

