<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ICT Incident Resolution Report</title>
    <style>
        @page {
            margin: 14mm 18mm 14mm 18mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 11px;
            line-height: 1.45;
        }

        /* ---- Outer document table: dompdf reliably repeats <thead> and <tfoot>
             on every generated page. This is a native, well-tested dompdf feature,
             unlike position:fixed which can drift to the wrong vertical position
             on later pages once tables/page-break-inside are involved. ---- */
        table.doc {
            width: 100%;
            border-collapse: collapse;
        }

        table.doc > thead > tr > td,
        table.doc > tfoot > tr > td,
        table.doc > tbody > tr > td {
            padding: 0;
        }

        table.doc > tbody > tr {
            page-break-inside: avoid;
        }

        /* ---- Header (repeats via thead) ---- */
        .header {
            text-align: center;
            padding: 0 0 3mm;
            border-bottom: 2px solid #1F3864;
            margin-bottom: 6mm;
        }

        .header img {
            max-width: 90px;
            max-height: 26mm;
            object-fit: contain;
            margin-bottom: 4px;
        }

        .header-title {
            font-size: 14px;
            font-weight: 700;
            color: #1F3864;
            margin: 0;
            letter-spacing: 0.3px;
        }

        .header-subtitle {
            font-size: 10px;
            color: #444444;
            margin: 3px 0 0;
        }

        /* ---- Footer (repeats via tfoot) ---- */
        .footer {
            text-align: center;
            font-size: 9px;
            color: #888888;
            padding-top: 3mm;
            margin-top: 6mm;
            border-top: 1px solid #e5e7eb;
        }

        /* ---- Report title block (page 1 only) ---- */
        .report-title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            color: #1F3864;
            margin: 4px 0 2px;
        }

        .report-subtitle {
            text-align: center;
            font-size: 11px;
            font-style: italic;
            color: #444444;
            margin: 0 0 20px;
        }

        /* ---- Sections: keep each one intact across a page break ---- */
        .section {
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #1F3864;
            margin: 0 0 8px;
            padding-bottom: 4px;
            border-bottom: 1.5px solid #1F3864;
        }

        /* ---- Field tables (Sections 1, 3 & Customer Rating) ---- */
        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            page-break-inside: avoid;
        }

        table.meta tr {
            page-break-inside: avoid;
        }

        table.meta td {
            border: 1px solid #d1d5db;
            padding: 6px 10px;
            vertical-align: top;
        }

        td.meta-label {
            width: 34%;
            background-color: #1F3864;
            color: #ffffff;
            font-weight: 700;
        }

        td.meta-value {
            width: 66%;
            background-color: #ffffff;
            color: #111827;
        }

        /* ---- Free-text boxes (Sections 2, 4, 6, 7) ---- */
        .box {
            border: 1px solid #d1d5db;
            padding: 10px 12px;
            border-radius: 4px;
            background: #ffffff;
            min-height: 14px;
            page-break-inside: avoid;
        }

        .plain-lines {
            white-space: pre-line;
        }

        /* ---- Sign-off (Section 9) ---- */
        table.signoff-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            page-break-inside: avoid;
        }

        table.signoff-table td.signoff-header {
            background-color: #1F3864;
            color: #ffffff;
            font-weight: 700;
            border: 1px solid #1F3864;
            padding: 6px 10px;
            width: 33.33%;
        }

        table.signoff-table td.signoff-value {
            border: 1px solid #d1d5db;
            padding: 22px 10px 6px;
            font-style: italic;
            color: #808080;
            width: 33.33%;
        }
    </style>
</head>
<body>

    <table class="doc">
        <thead>
            <tr>
                <td>
                    <div class="header">
                        @if (file_exists($logo_path))
                            <img src="{{ $logo_path }}" alt="CPHL logo">
                        @endif
                        <p class="header-title">NATIONAL HEALTH LABORATORIES & DIAGNOSTIC SERVICES (NHLDS)</p>
                        <div class="header-title">ICT TICKET RESOLUTION REPORT</div>
                    </div>
                </td>
            </tr>
        </thead>

        <tbody>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">1. Ticket / Incident Reference</p>
                        <table class="meta">
                            <tr><td class="meta-label">Ticket / Reference No.</td><td class="meta-value">{{ $ticket_number }}</td></tr>
                            <tr><td class="meta-label">Date Reported</td><td class="meta-value">{{ $date_reported }}</td></tr>
                            <tr><td class="meta-label">Date Resolved</td><td class="meta-value">{{ $date_resolved }}</td></tr>
                            <tr><td class="meta-label">Reported By</td><td class="meta-value">{{ $reported_by }}</td></tr>
                            <tr><td class="meta-label">Facility</td><td class="meta-value">{{ $facility }}</td></tr>
                            <tr><td class="meta-label">System Affected</td><td class="meta-value">{{ $system_affected }}</td></tr>
                            <tr><td class="meta-label">Severity</td><td class="meta-value">{{ $severity }}</td></tr>
                            <tr><td class="meta-label">Resolved By</td><td class="meta-value">{{ $resolved_by }}</td></tr>
                            <tr><td class="meta-label">Turnaround Time</td><td class="meta-value">{{ $turnaround_time }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">2. Incident Description</p>
                        <div class="box">{{ $incident_description }}</div>
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">3. Impact Assessment</p>
                        <table class="meta">
                            <tr><td class="meta-label">Services Disrupted</td><td class="meta-value">{{ $services_disrupted }}</td></tr>
                            <tr><td class="meta-label">Data Loss Risk</td><td class="meta-value">{{ $data_loss_risk }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">4. Root Cause Analysis</p>
                        <div class="box">{{ $root_cause_analysis }}</div>
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">5. Resolution Steps Taken</p>
                        <div class="box plain-lines">{{ $resolution_steps }}</div>
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">6. Verification / Testing</p>
                        <div class="box">{{ $verification_testing }}</div>
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">7. Recommendations / Preventive Measures</p>
                        <div class="box">{{ $recommendations }}</div>
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">8. Customer Rating Summary</p>
                        <table class="meta">
                            <tr><td class="meta-label">Timeliness Rating</td><td class="meta-value">{{ $timeliness_rating }}</td></tr>
                            <tr><td class="meta-label">Completeness Rating</td><td class="meta-value">{{ $completeness_rating }}</td></tr>
                            <tr><td class="meta-label">Overall Satisfaction Rating</td><td class="meta-value">{{ $overall_rating }}</td></tr>
                            <tr><td class="meta-label">Average Rating</td><td class="meta-value">{{ $average_rating }}</td></tr>
                            <tr><td class="meta-label">Comments</td><td class="meta-value">{{ $feedback_comments }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>

            <tr>
                <td>
                    <div class="section">
                        <p class="section-title">9. Sign-Off</p>
                        <table class="signoff-table">
                            <tr>
                                <td class="signoff-header">Resolved By</td>
                                <td class="signoff-header">Reviewed By</td>
                                <td class="signoff-header">Approved By</td>
                            </tr>
                            <tr>
                                <td class="signoff-value">{{ $resolved_by }}</td>
                                <td class="signoff-value">{{ $reviewed_by }}</td>
                                <td class="signoff-value">&nbsp;</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>