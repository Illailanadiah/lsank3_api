<!DOCTYPE html>
<html lang="ms">

<head>
    <meta charset="UTF-8">

    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 12px;
            background: #ffffff;
        }

        .page {
            position: relative;
            width: 100%;
            min-height: 100%;
            background: #ffffff;
        }

        /* ==============================
           HEADER
        ============================== */

        .header {
            height: 190px;
            position: relative;
            padding: 18px 28px;
            background-image: url('{{ public_path("images/pdf/water-header.jpg") }}');
            background-size: cover;
            background-position: center;
        }

        .header-overlay {
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.10);
        }

        .header-table {
            width: 100%;
            position: relative;
            z-index: 2;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .logo {
            height: 58px;
            margin-right: 8px;
        }

        .title {
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            color: #000000;
            margin-top: 7px;
        }

        .document-type {
            text-align: right;
            font-size: 9px;
            font-weight: bold;
            margin-top: 18px;
        }

        .agency-info {
            position: absolute;
            left: 28px;
            bottom: 24px;
            z-index: 2;
            font-weight: bold;
            font-size: 13px;
            line-height: 1.45;
            color: #000000;
        }

        /* ==============================
           CONTENT
        ============================== */

        .content {
            padding: 20px 30px 90px 30px;
            position: relative;
        }

        .watermark {
            position: absolute;
            width: 180px;
            height: 180px;
            left: 50%;
            top: 340px;
            margin-left: -90px;
            opacity: 0.13;
            z-index: 0;
        }

        .section {
            position: relative;
            z-index: 1;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 6px 5px;
            vertical-align: top;
            line-height: 1.45;
        }

        .label {
            width: 31%;
            font-weight: bold;
            font-size: 12px;
        }

        .value {
            width: 69%;
            font-size: 12px;
        }

        .divider {
            border-top: 1px solid #a7a7a7;
            margin: 10px 0 14px 0;
        }

        /* ==============================
           ITEM TABLE
        ============================== */

        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .item-table th {
            padding: 7px 5px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            color: #111827;
        }

        .item-table td {
            padding: 8px 5px;
            vertical-align: top;
            font-size: 12px;
            line-height: 1.4;
        }

        .item-type {
            width: 31%;
        }

        .item-description {
            width: 44%;
        }

        .item-amount {
            width: 25%;
        }

        .amount {
            white-space: nowrap;
        }

        /* ==============================
           SUMMARY
        ============================== */

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        .summary-table td {
            padding: 7px 5px;
            vertical-align: middle;
        }

        .summary-spacer {
            width: 50%;
        }

        .summary-label {
            width: 30%;
            font-weight: bold;
            text-align: left;
            white-space: nowrap;
        }

        .summary-amount {
            width: 20%;
            text-align: right;
            white-space: nowrap;
        }

        /* Line pendek hanya pada bahagian total */
        .grand-total-label {
            width: 30%;
            font-size: 12px;
            font-weight: bold;
            text-align: left;
            white-space: nowrap;
            border-top: 1px solid #d1d5db;
        }

        .grand-total-amount {
            width: 20%;
            font-size: 12px;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
            border-top: 1px solid #d1d5db;
        }

        /* ==============================
           FOOTER
        ============================== */

        .footer {
            position: absolute;
            left: 30px;
            right: 30px;
            bottom: 18px;
            text-align: center;
            color: #666666;
            font-size: 9px;
            line-height: 1.45;
        }

        .footer a {
            color: #315bea;
            text-decoration: underline;
        }

        .official-note {
            margin-top: 2px;
            font-weight: bold;
            font-size: 9px;
        }
    </style>
</head>

<body>

    <div class="page">

        <!-- HEADER -->
        <div class="header">

            <div class="header-overlay"></div>

            <table class="header-table">
                <tr>
                    <td>
                        <img
                            src="{{ public_path('images/pdf/logo-lsank.png') }}"
                            class="logo">

                        <img
                            src="{{ public_path('images/pdf/logo-kedah.png') }}"
                            class="logo">
                    </td>

                    <td>
                        <div class="title">
                            INVOIS RASMI
                        </div>

                        <div class="document-type">
                            ASAL
                        </div>
                    </td>
                </tr>
            </table>

            <div class="agency-info">
                Lembaga Sumber Air Negeri Kedah<br>
                Aras 1, Blok E, Wisma Darul Aman<br>
                05503 Alor Setar, Kedah<br>
                04-7027677 | +6012 638 3980
            </div>

        </div>

        <!-- CONTENT -->
        <div class="content">

            <img
                src="{{ public_path('images/pdf/logo-lsank.png') }}"
                class="watermark">

            <div class="section">

                <!-- APPLICANT -->
                <table class="info-table">

                    <tr>
                        <td class="label">
                            NAMA
                        </td>

                        <td class="value">
                            {{ strtoupper(
                            $displayName
                            ?? $companyName
                            ?? $applicantName
                            ?? '-'
                        ) }}
                        </td>
                    </tr>

                    <tr>
                        <td class="label">
                            ALAMAT
                        </td>

                        <td class="value">
                            {!! nl2br(
                            e(
                            strtoupper(
                            $address
                            ?? $application->business_address
                            ?? $application->address
                            ?? '-'
                            )
                            )
                            ) !!}
                        </td>
                    </tr>

                </table>

                <div class="divider"></div>

                <!-- INVOICE INFO -->
                <table class="info-table">

                    <tr>
                        <td class="label">
                            NOMBOR INVOIS
                        </td>

                        <td class="value">
                            {{ $invoice->invoice_no ?? '-' }}
                        </td>
                    </tr>

                    <tr>
                        <td class="label">
                            TARIKH INVOIS
                        </td>

                        <td class="value">
                            @php
                            $resolvedInvoiceDate =
                            $invoiceDate
                            ?? $invoice->invoice_date
                            ?? $invoice->issued_at
                            ?? $invoice->created_at;
                            @endphp

                            @if(!empty($resolvedInvoiceDate))
                            {{ \Carbon\Carbon::parse($resolvedInvoiceDate)->format('d/m/Y') }}
                            @else
                            -
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td class="label">
                            NO FAIL
                        </td>

                        <td class="value">
                            {{
                            $fileNo
                            ?? $application->application_ref_no
                            ?? $application->file_no
                            ?? $application->reference_no
                            ?? $application->application_no
                            ?? '-'
                        }}
                        </td>
                    </tr>

                    <tr>
                        <td class="label">
                            BAYARAN
                        </td>

                        <td class="value">
                            {{
                            $invoice->fee_type
                            ?? $invoice->payment_type
                            ?? 'Fi Pemprosesan'
                        }}
                        </td>
                    </tr>

                </table>

                <div class="divider"></div>

                <!-- ITEM -->
                <table class="item-table">

                    <thead>
                        <tr>
                            <th class="item-type">
                                Jenis Pembayaran
                            </th>

                            <th class="item-description">
                                Perkara
                            </th>

                            <th class="item-amount">
                                Harga
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>

                            <td>
                                {{
                                $invoice->fee_type
                                ?? $invoice->payment_type
                                ?? 'Fi Pemprosesan'
                            }}
                            </td>

                            <td>
                                {{
                                $activityName
                                ?? $invoice->description
                                ?? $application->activity_name
                                ?? $application->activity_type
                                ?? $application->license_type
                                ?? 'Bayaran Permohonan'
                            }}
                            </td>

                            <td class="amount">
                                @php
                                $amount =
                                $invoice->amount
                                ?? $invoice->total_amount
                                ?? $invoice->total
                                ?? $payment?->amount
                                ?? 0;
                                @endphp

                                RM {{ number_format((float) $amount, 2) }}
                            </td>

                        </tr>
                    </tbody>

                </table>

                <div class="divider"></div>

                <!-- TOTAL -->
                <!-- TOTAL -->

                <!-- TOTAL -->

                <table class="summary-table">

                    <tr>
                        <td class="summary-spacer"></td>

                        <td class="summary-label">
                            Jumlah
                        </td>

                        <td class="summary-amount">
                            RM {{ number_format((float) $amount, 2) }}
                        </td>
                    </tr>

                    <tr>
                        <td class="summary-spacer"></td>

                        <td class="grand-total-label">
                            JUMLAH PERLU DIBAYAR
                        </td>

                        <td class="grand-total-amount">
                            RM {{ number_format((float) $amount, 2) }}
                        </td>
                    </tr>

                </table>

                <div class="divider"></div>

                <!-- PAYMENT INFORMATION -->
                <table class="info-table">

                    <tr>
                        <td class="label">
                            STATUS BAYARAN
                        </td>

                        <td class="value">
                            @if($isPaid ?? false)
                            SUDAH BAYAR
                            @else
                            BELUM BAYAR
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td class="label">
                            E-MEL
                        </td>

                        <td class="value">
                            {{
                            $email
                            ?? $application->email
                            ?? $application->business_email
                            ?? $application->user?->email
                            ?? '-'
                        }}
                        </td>
                    </tr>

                    @if(!empty($phone) && $phone !== '-')
                    <tr>
                        <td class="label">
                            NO. TELEFON
                        </td>

                        <td class="value">
                            {{ $phone }}
                        </td>
                    </tr>
                    @endif

                </table>

                <div class="divider"></div>

            </div>

        </div>

        <!-- FOOTER -->
        <div class="footer">

            Sila layari
            <a href="https://www.lsank.gov.my/">
                https://www.lsank.gov.my/
            </a>
            untuk mengemaskini, melihat dan mencetak penyata anda.

            <br>

            Jika ada sebarang kemusykilan sila hubungi di talian
            +604-702 7667

            <div class="official-note">
                (CETAKAN KOMPUTER TIDAK MEMERLUKAN TANDATANGAN)
            </div>

            Lembaga Sumber Air Negeri Kedah

        </div>

    </div>

</body>

</html>