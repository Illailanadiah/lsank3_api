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
            width: 100%;
            min-height: 100%;
            position: relative;
            background: #ffffff;
        }

        /* ==============================
           HEADER
        ============================== */

        .header {
            height: 190px;
            position: relative;
            background-image: url('{{ public_path("images/pdf/water-header.jpg") }}');
            background-size: cover;
            background-position: center;
            padding: 18px 28px;
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
            font-size: 13px;
            font-weight: bold;
            line-height: 1.45;
            color: #000;
        }

        /* ==============================
           BODY
        ============================== */

        .content {
            padding: 20px 30px 80px 30px;
            position: relative;
        }

        .watermark {
            position: absolute;
            width: 180px;
            height: 180px;
            left: 50%;
            top: 330px;
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
            vertical-align: top;
            padding: 6px 5px;
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
           PAYMENT TABLE
        ============================== */

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .payment-table th {
            text-align: left;
            padding: 7px 5px;
            font-size: 11px;
            font-weight: bold;
            color: #111827;
        }

        .payment-table td {
            padding: 7px 5px;
            vertical-align: top;
            font-size: 12px;
            line-height: 1.4;
        }

        .payment-col {
            width: 31%;
        }

        .description-col {
            width: 44%;
        }

        .amount-col {
            width: 25%;
        }

        .amount {
            white-space: nowrap;
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
                            RESIT RASMI
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
                            ?? $application->address
                            ?? $application->business_address
                            ?? $application->company_address
                            ?? $application->mailing_address
                            ?? '-'
                            )
                            )
                            ) !!}
                        </td>
                    </tr>

                </table>

                <div class="divider"></div>


                <!-- RECEIPT INFO -->
                <table class="info-table">

                    <tr>
                        <td class="label">
                            NOMBOR RESIT
                        </td>

                        <td class="value">
                            {{ $receipt->receipt_no ?? '-' }}
                        </td>
                    </tr>

                    <tr>
                        <td class="label">
                            TARIKH RESIT
                        </td>

                        <td class="value">
                            @php
                            $resolvedReceiptDate =
                            $receiptDate
                            ?? $receipt->receipt_date
                            ?? $receipt->issued_at
                            ?? $receipt->paid_at
                            ?? $payment?->paid_at
                            ?? $payment?->payment_date
                            ?? $receipt->created_at;
                            @endphp

                            @if(!empty($resolvedReceiptDate))
                            {{ \Carbon\Carbon::parse($resolvedReceiptDate)->format('d/m/Y') }}
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

                                ?? $application->real_file_no

                                ?? $application->draft_file_no

                                ?? $application->reference_no

                                ?? $application->application_no

                                ?? $invoice->file_no

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
                            ?? $receipt->payment_type
                            ?? 'Fi Pemprosesan'
                        }}
                        </td>
                    </tr>

                </table>


                <div class="divider"></div>


                <!-- PAYMENT -->
                <table class="payment-table">

                    <thead>
                        <tr>
                            <th class="payment-col">
                                Jenis Pembayaran
                            </th>

                            <th class="description-col">
                                Perkara
                            </th>

                            <th class="amount-col">
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
                                ?? $receipt->payment_type
                                ?? 'Fi Pemprosesan'
                            }}
                            </td>

                            <td>
                                {{
                                $activityName
                                ?? $receipt->description
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
                                $receipt->amount
                                ?? $payment?->amount
                                ?? $payment?->payment_amount
                                ?? $invoice->amount
                                ?? $invoice->total_amount
                                ?? $invoice->total
                                ?? 0;
                                @endphp

                                RM {{ number_format((float) $amount, 2) }}
                            </td>

                        </tr>
                    </tbody>

                </table>


                <div class="divider"></div>


                <!-- PAYMENT METHOD -->
                <table class="info-table">

                    <tr>

                        <td class="label">
                            Cara Bayaran
                        </td>

                        <td class="value">
                            {{
                            $payment?->paymentMethod?->name
                            ?? $payment?->paymentMethod?->method_name
                            ?? $payment?->payment_method
                            ?? $receipt->payment_method
                            ?? 'FPX Online Banking'
                        }}
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
                            ?? $application->company_email
                            ?? $application->user?->email
                            ?? '-'
                        }}
                        </td>

                    </tr>

                    @if(
                    !empty($phone)
                    && $phone !== '-'
                    )
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