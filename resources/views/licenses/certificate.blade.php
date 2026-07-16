<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">

    <title>
        {{ $license->license_no ?? 'Lesen Aktiviti' }}
    </title>

    <style>
        @page {
            margin: 22px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 12px;
        }

        .license-frame {
            position: relative;
            min-height: 1015px;
            padding: 30px;
            border: 5px double #0b5cad;
        }

        .qr-box {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 112px;
            text-align: center;
        }

        .qr-box img {
            display: block;
            width: 108px;
            height: 108px;
            margin: 0 auto;
        }

        .qr-placeholder {
            width: 108px;
            height: 108px;
            padding-top: 42px;
            border: 1px solid #b9c8d8;
            background: #f4f8fc;
            color: #687589;
            font-size: 9px;
            text-align: center;
        }

        .qr-label {
            margin-top: 4px;
            font-size: 8px;
            color: #5f6d7e;
        }

        .header {
            min-height: 135px;
            padding: 6px 120px 20px;
            text-align: center;
        }

        .agency-name {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .agency-subtitle {
            margin-top: 8px;
            font-size: 13px;
            font-weight: bold;
        }

        .license-title {
            margin-top: 24px;
            font-size: 24px;
            font-weight: bold;
            color: #0b5cad;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .license-number {
            margin-top: 8px;
            font-size: 14px;
            font-weight: bold;
        }

        .intro {
            margin: 24px 0 18px;
            line-height: 1.7;
            text-align: justify;
        }

        .info-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 10px 9px;
            border-bottom: 1px solid #d8e2ee;
            vertical-align: top;
        }

        .info-table td:first-child {
            width: 34%;
            font-weight: bold;
            background: #f4f8fc;
        }

        .conditions {
            margin-top: 26px;
            padding: 15px 16px;
            border: 1px solid #b9d5f1;
            background: #eef6ff;
            line-height: 1.6;
        }

        .conditions-title {
            margin-bottom: 8px;
            font-weight: bold;
            color: #0b5cad;
        }

        .signature {
            margin-top: 62px;
            text-align: right;
            line-height: 1.7;
        }

        .footer {
            position: absolute;
            right: 30px;
            bottom: 20px;
            left: 30px;
            padding-top: 10px;
            border-top: 1px solid #d8e2ee;
            font-size: 8px;
            color: #687589;
            text-align: center;
        }

        .verification-url {
            margin-top: 3px;
            font-size: 7px;
            word-break: break-all;
        }
    </style>
</head>

<body>
@php
    $statusName = $license->status?->status_name ?? 'Aktif';

    $startDate = $license->start_date
        ? $license->start_date->format('d/m/Y')
        : '-';

    $expiryDate = $license->expiry_date
        ? $license->expiry_date->format('d/m/Y')
        : '-';

    $applicationReference =
        $application?->application_ref_no
        ?? $license->file_no
        ?? '-';
@endphp

<div class="license-frame">
    <div class="qr-box">
        @if (!empty($qrDataUri))
            <img
                src="{{ $qrDataUri }}"
                alt="Kod QR Lesen"
            >
        @else
            <div class="qr-placeholder">
                Kod QR
            </div>
        @endif

        <div class="qr-label">
            Imbas untuk pengesahan
        </div>
    </div>

    <div class="header">
        <div class="agency-name">
            Lembaga Sumber Air Negeri Kedah
        </div>

        <div class="agency-subtitle">
            Kerajaan Negeri Kedah Darul Aman
        </div>

        <div class="license-title">
            Lesen Aktiviti
        </div>

        <div class="license-number">
            No. Lesen:
            {{ $license->license_no ?? '-' }}
        </div>
    </div>

    <div class="intro">
        Dengan ini diperakui bahawa pemegang lesen yang dinyatakan
        di bawah telah diluluskan untuk menjalankan aktiviti tertakluk
        kepada syarat, tempoh sah dan ketetapan Lembaga Sumber Air
        Negeri Kedah.
    </div>

    <table class="info-table">
        <tr>
            <td>No. Fail</td>
            <td>{{ $license->file_no ?? '-' }}</td>
        </tr>

        <tr>
            <td>Pemegang Lesen</td>
            <td>{{ $license->holder_name ?? '-' }}</td>
        </tr>

        <tr>
            <td>Jenis Lesen</td>
            <td>{{ $license->license_type ?? '-' }}</td>
        </tr>

        <tr>
            <td>Aktiviti</td>
            <td>{{ $license->activity_name ?? '-' }}</td>
        </tr>

        <tr>
            <td>Lokasi Aktiviti</td>
            <td>
                {{ $license->activity_location ?: '-' }}
            </td>
        </tr>

        <tr>
            <td>Tarikh Mula</td>
            <td>{{ $startDate }}</td>
        </tr>

        <tr>
            <td>Tarikh Tamat</td>
            <td>{{ $expiryDate }}</td>
        </tr>

        <tr>
            <td>Status Lesen</td>
            <td>{{ $statusName }}</td>
        </tr>

        <tr>
            <td>Rujukan Permohonan</td>
            <td>{{ $applicationReference }}</td>
        </tr>
    </table>

    <div class="conditions">
        <div class="conditions-title">
            Syarat Penggunaan Lesen
        </div>

        Lesen ini hanya sah untuk aktiviti dan lokasi yang dinyatakan.
        Lesen tidak boleh dipindah milik tanpa kebenaran bertulis.
        Pemegang lesen hendaklah mematuhi semua syarat, undang-undang,
        garis panduan dan arahan semasa yang ditetapkan oleh pihak
        berkuasa.
    </div>

    <div class="signature">
        ............................................................
        <br>

        Ketua Pengarah
        <br>

        Lembaga Sumber Air Negeri Kedah
    </div>

    <div class="footer">
        Dokumen ini dijana secara elektronik.
        Pengesahan lesen boleh dibuat melalui kod QR unik
        di bahagian kiri atas.

        @if (!empty($verificationUrl))
            <div class="verification-url">
                {{ $verificationUrl }}
            </div>
        @endif
    </div>
</div>
</body>
</html>