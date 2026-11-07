<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $reportTitle }}</title>
    <style>
        @page {
            margin: 36px 36px 48px 36px;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        /* Header Area */
        .report-header {
            width: 100%;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .brand-title {
            font-size: 16px;
            font-weight: bold;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 2px 0;
        }

        .brand-subtitle {
            font-size: 9px;
            color: #64748b;
            margin: 0;
        }

        .report-title-cell {
            text-align: right;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .report-period {
            font-size: 9px;
            color: #475569;
            font-weight: bold;
        }

        /* Meta details */
        .meta-bar {
            width: 100%;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 16px;
            font-size: 9px;
            color: #475569;
        }

        /* Summary Cards Grid */
        .summary-container {
            width: 100%;
            margin-bottom: 16px;
        }

        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-left: -6px;
            margin-right: -6px;
        }

        .summary-card {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 4px;
            padding: 8px 10px;
            text-align: center;
            vertical-align: top;
        }

        .summary-label {
            font-size: 8px;
            text-transform: uppercase;
            font-weight: bold;
            color: #0369a1;
            margin-bottom: 2px;
        }

        .summary-value {
            font-size: 13px;
            font-weight: bold;
            color: #0c4a6e;
        }

        /* Section Heading */
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .data-table th {
            background-color: #0284c7;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #0284c7;
        }

        .data-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9px;
            vertical-align: top;
        }

        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-green {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        .badge-yellow {
            background-color: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }

        .badge-orange {
            background-color: #ffedd5;
            color: #c2410c;
            border: 1px solid #fdba74;
        }

        .badge-red {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        .badge-blue {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #7dd3fc;
        }

        .badge-gray {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 24px;
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            color: #64748b;
            font-size: 10px;
            margin-top: 10px;
        }

        /* Footer */
        .report-footer {
            position: fixed;
            bottom: -32px;
            left: 0;
            right: 0;
            height: 24px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 8px;
            color: #94a3b8;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .text-muted { color: #64748b; }
    </style>
</head>
<body>
    <!-- Report Header -->
    <div class="report-header">
        <table class="header-table">
            <tr>
                <td style="width: 55%; vertical-align: top;">
                    <div class="brand-title">Island Hopping Reservation System</div>
                    <div class="brand-subtitle">Official Administrative Manifest & Operational Analytics Report</div>
                </td>
                <td class="report-title-cell" style="width: 45%; vertical-align: top;">
                    <div class="report-title">{{ $reportTitle }}</div>
                    <div class="report-period">Coverage: {{ $periodLabel }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Metadata Bar -->
    <div class="meta-bar">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%;">
                    <strong>Generated On:</strong> {{ $generatedAt }}
                </td>
                <td style="width: 50%; text-align: right;">
                    <strong>Generated By:</strong> {{ $generatedBy }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Body Content injected from subviews -->
    @yield('content')

    <!-- Report Footer with Page Numbering -->
    <div class="report-footer">
        <table class="footer-table">
            <tr>
                <td style="width: 50%;">
                    Island Hopping Reservation System • Confidential Administrative Document
                </td>
                <td style="width: 50%; text-align: right;">
                    Generated: {{ $generatedAt }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

