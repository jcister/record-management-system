<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        /*
         * DomPDF only supports a single global @page rule.
         * Use standard 0.4in margins and rely on positioning
         * to pin the request-letter header/footer.
         */
        @page {
            size: a4;
            margin: 0.4in;
        }

        body {
            margin: 0;
            padding: 0;
        }

        /* ===== REQUEST LETTER STYLES ===== */
        .request-letter-section {
            padding: 0 0.6in 1.5in;
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1;
        }

        .letter-body {
            display: block;
        }

        .request-letter-section p {
            margin: 0;
            padding: 0;
        }

        .letter-header {
            width: 100%;
            margin-bottom: 20px;
        }

        .letter-footer {
            position: fixed;
            bottom: -0.2in;
            left: 0;
            right: 0;
            width: 100%;
            height: 100px;
            text-align: center;
        }

        .header-image {
            width: 100%;
            height: auto;
            display: block;
        }

        .footer-image {
            width: 100%;
            height: auto;
            display: block;
        }

        .date-container {
            margin-top: 20px;
            margin-bottom: 30px;
            margin-left: auto;
            width: 55%;
            text-align: center;
        }

        .recipient-info {
            margin-bottom: 25px;
            line-height: 1.1;
        }

        .recipient-name {
            font-weight: bold;
            text-transform: uppercase;
        }

        .recipient-name .no-transform {
            text-transform: none;
        }

        .salutation {
            margin-bottom: 20px;
            font-weight: bold;
        }

        .body-text {
            text-indent: 50px;
            margin-bottom: 15px;
            text-align: justify;
        }

        .closing-section {
            margin-left: auto;
            width: 55%;
            text-align: left;
        }

        .signature-gap {
            height: 45px;
        }

        .director-name {
            font-weight: bold;
            text-align: center;
        }


        .signature-wrapper {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            width: auto;
        }

        .signature-block {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        .closing-text {
            margin-bottom: 10px;
        }

        .director-designation {
            width: 100%;
            text-align: center;
        }

        .footer-control {
            margin-top: 24px;
            font-family: 'Arial', sans-serif;
            font-weight: bold;
            font-size: 10pt;
        }

        /* ===== NAP FORM 3 STYLES ===== */
        .nap-form-section {
            page-break-before: always;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.2;
            padding: 0.4in 0;
        }

        .nap-form-section .top-meta {
            width: 100%;
            font-size: 8pt;
            margin-bottom: 5px;
            border-collapse: collapse;
        }

        .nap-form-section .top-meta td {
            border: none;
            padding: 0;
        }

        .nap-form-section .main-container {
            width: 100%;
            border: 1.5pt solid black;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .nap-form-section .main-container td,
        .nap-form-section .main-container th {
            border: 1pt solid black;
            padding: 6px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .nap-form-section .header-cell {
            padding: 10px !important;
            vertical-align: middle !important;
            text-align: center;
        }

        .nap-form-section .inner-header-box {
            width: 90%;
            margin: 0 auto;
            border: 1pt solid black;
            padding: 15px 5px;
            text-align: center;
        }

        .nap-form-section .nap-title {
            font-family: "Book Antiqua", Palatino, serif;
            font-weight: bold;
            font-size: 11pt;
            margin: 0;
            text-transform: uppercase;
        }

        .nap-form-section .nap-subtitle {
            font-family: Verdana, sans-serif;
            font-style: italic;
            font-size: 8.5pt;
            display: block;
            margin-bottom: 8px;
        }

        .nap-form-section .header-title {
            font-weight: bold;
            font-size: 10.5pt;
            margin-top: 8px;
            text-transform: uppercase;
        }

        .nap-form-section .label {
            font-size: 7.5pt;
            font-weight: bold;
            display: block;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .nap-form-section .col-item-no {
            width: 15%;
        }

        .nap-form-section .col-description {
            width: 35%;
        }

        .nap-form-section .col-period {
            width: 25%;
        }

        .nap-form-section .col-retention {
            width: 25%;
        }

        .nap-form-section .record-row td {
            border-top: none !important;
            border-bottom: none !important;
            border-left: 1pt solid black;
            border-right: 1pt solid black;
            text-align: center;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .nap-form-section .record-row:first-of-type td {
            border-top: 1pt solid black !important;
        }

        .nap-form-section .text-left {
            text-align: left !important;
        }

        .nap-form-section .cert-container {
            text-align: center;
            padding: 20px 10px !important;
            page-break-inside: avoid;
        }

        .nap-form-section .signature-block {
            width: 65%;
            margin-left: 35%;
            text-align: center;
            margin-top: 40px;
        }

        .nap-form-section .sig-line {
            border-top: 1pt solid black;
            margin: 10px auto 5px auto;
            width: 100%;
        }

        .nap-form-section .sig-name {
            font-weight: bold;
            font-size: 10pt;
        }

        .nap-form-section .sig-sub {
            font-size: 8pt;
            line-height: 1.1;
        }
    </style>
</head>

<body>
    {{-- ── PAGE 1: Request Letter ── --}}
    @php
        $recipientExtension = trim((string) ($letter['recipient_extension'] ?? ''));

        $segments = array_filter(
            [
                trim((string) ($letter['recipient_first_name'] ?? '')),
                trim((string) ($letter['recipient_middle_name'] ?? '')),
                trim((string) ($letter['recipient_last_name'] ?? '')),
            ],
            fn($value) => $value !== '',
        );

        $recipientName = '';
        if (!empty($segments)) {
            $recipientName = implode(' ', $segments);
        }

        if ($recipientName === '') {
            $recipientName = trim((string) ($letter['recipient_name'] ?? ''));
        }

        if ($recipientExtension !== '' && $recipientName !== '') {
            $possibleSuffix = ', ' . $recipientExtension;
            if (str_ends_with(strtolower($recipientName), strtolower($possibleSuffix))) {
                $recipientName = rtrim(substr($recipientName, 0, -strlen($possibleSuffix)));
            }
        }

        $lastName = trim((string) ($letter['recipient_last_name'] ?? ''));
        if ($lastName === '') {
            $nameParts = $recipientName !== '' ? preg_split('/\s+/', $recipientName) : [];
            $lastName = $nameParts && count($nameParts) > 0 ? rtrim((string) end($nameParts), ',') : 'Sir/Ma\'am';
        } else {
            $lastName = rtrim($lastName, ',');
        }

        $salutationPrefix = trim((string) ($letter['honorific'] ?? ''));

        $headerImagePath = public_path('images/letters/ched-letter-header.png');
        if (!file_exists($headerImagePath)) {
            $headerImagePath = public_path('images/letters/endorsement-letter-to-coa-header.png');
            if (!file_exists($headerImagePath)) {
                $headerImagePath = public_path('Frame.png');
            }
        }

        $footerImagePath = public_path('images/letters/ched-letter-footer.png');
        if (!file_exists($footerImagePath)) {
            $footerImagePath = null;
        }
    @endphp

    @if ($footerImagePath)
        <div class="letter-footer">
            <img src="{{ $footerImagePath }}" alt="CHED Footer" class="footer-image">
        </div>
    @endif

    <div class="request-letter-section">
        <div class="letter-header">
            <img src="{{ $headerImagePath }}" alt="CHED Header" class="header-image">
        </div>

        <div class="letter-body">
            <div class="date-container">
                {{ $letter['date'] ?? now()->format('F j, Y') }}
            </div>

            <div class="recipient-info">
                <p class="recipient-name"><strong>{{ $recipientName ?? ($letter['recipient_name'] ?? '') }}@if (!empty($recipientExtension))
                            <span class="no-transform">, {{ $recipientExtension }}</span>
                        @endif
                    </strong></p>
                <p>{{ $letter['recipient_position'] ?? '' }}</p>
                <p>{{ $letter['recipient_designation'] ?? '' }}</p>
                <p>{{ $letter['address_1'] ?? '' }}</p>
                @if (!empty($letter['address_2']))
                    <p>{{ $letter['address_2'] }}</p>
                @endif
                @if (!empty($letter['telephone']))
                    <p>Tel. No. {{ $letter['telephone'] }}</p>
                @endif
            </div>

            <div class="salutation">
                Dear {{ $salutationPrefix !== '' ? $salutationPrefix . ' ' : '' }}{{ $lastName }},
            </div>

            <div class="body-text">
                Greetings from CHED RO XI!
            </div>

            <div class="body-text">
                This has reference to the request of {{ $letter['user_name'] ?? '' }},
                {{ $letter['user_position'] ?? 'Administrative Officer' }}, this Office, for authority to dispose
                valueless
                records in compliance with Section 18, Article III of RA 9470 which states that &ldquo;no government
                agency
                and
                instrumentality shall dispose of, destroy or authorized the disposal or destruction of any public
                records,
                which
                are in custody or under its control except with prior written authority of the Executive
                Director&rdquo;. In
                view
                hereof, may we request for approval of the aforementioned conduct of disposal.
            </div>

            <div class="body-text">
                Attached is the NAP Form No. 3 &ndash; Request for Authority to Dispose of Records for your reference.
            </div>

            <div class="body-text">
                Thank you for your continued support.
            </div>

            <div class="closing-section">
                <div class="signature-wrapper">
                    <p class="closing-text">Very truly yours,</p>
                    <div class="signature-gap"></div>
                    <div class="signature-block">
                        <p class="director-name">
                            <strong>{{ $director['name'] ?? 'MARICAR R. CASQUEJO, PhD, CESO III' }}</strong>
                        </p>
                        <p class="director-designation">{{ $director['designation'] ?? 'Director IV' }}</p>
                    </div>
                </div>
            </div>

            <div class="footer-control">
                CONTROL ID {{ !empty($letter['control_id']) ? $letter['control_id'] : '________________' }}
            </div>
        </div>
    </div>

    {{-- ── PAGE 2+: NAP Form 3 ── --}}
    @php
        $data = $napForm3Data;
        $signatoryPrefixes = [
            'mr',
            'mr.',
            'mrs',
            'mrs.',
            'ms',
            'ms.',
            'dr',
            'dr.',
            'engr',
            'engr.',
            'atty',
            'atty.',
            'prof',
            'prof.',
            'hon',
            'hon.',
        ];

        $formatSignatoryName = static function (?string $rawValue) use ($signatoryPrefixes): string {
            $value = trim((string) $rawValue);

            if ($value === '') {
                return '';
            }

            [$mainName, $extension] = array_pad(array_map('trim', explode(',', $value, 2)), 2, '');
            $tokens = array_values(array_filter(preg_split('/\s+/', $mainName) ?: []));

            if ($tokens === []) {
                return $extension !== '' ? ', ' . $extension : '';
            }

            $hasPrefix = in_array(mb_strtolower($tokens[0]), $signatoryPrefixes, true);
            $prefix = '';
            $firstName = '';
            $middleName = '';
            $lastName = '';
            $nameTokens = $tokens;

            if ($hasPrefix) {
                $prefix = (string) array_shift($nameTokens);
            }

            if (count($nameTokens) >= 3) {
                $lastName = (string) ($nameTokens[count($nameTokens) - 1] ?? '');
                $middleName = (string) ($nameTokens[count($nameTokens) - 2] ?? '');
                $firstName = implode(' ', array_slice($nameTokens, 0, -2));
            } elseif (count($nameTokens) === 2) {
                $firstName = (string) ($nameTokens[0] ?? '');
                $lastName = (string) ($nameTokens[1] ?? '');
            } else {
                $firstName = (string) ($nameTokens[0] ?? '');
            }

            $middleInitial = '';
            $trimmedMiddle = trim($middleName);
            if ($trimmedMiddle !== '') {
                $middleInitial = mb_strtoupper(mb_substr($trimmedMiddle, 0, 1)) . '.';
            }

            $coreName = trim(
                implode(
                    ' ',
                    array_filter([
                        $prefix !== '' ? mb_strtoupper($prefix) : '',
                        $firstName !== '' ? mb_strtoupper($firstName) : '',
                        $middleInitial,
                        $lastName !== '' ? mb_strtoupper($lastName) : '',
                    ]),
                ),
            );

            if ($coreName === '') {
                $coreName = $value;
            }

            if ($extension === '') {
                return $coreName;
            }

            return $coreName . ', ' . mb_strtoupper($extension);
        };

        $preparedByNameFormatted = $formatSignatoryName($data['prepared_by_name'] ?? '');
        $certifiedByNameFormatted = $data['certified_by_name'] ?? '';
    @endphp

    <div class="nap-form-section">
        <table class="top-meta">
            <tr>
                <td style="text-align: left;">NAP Form No. 3<br>Revised 2012</td>
                <td style="text-align: right;">Accomplish in 3 copies</td>
            </tr>
        </table>

        <table class="main-container">
            <tr>
                <td colspan="2" rowspan="2" class="header-cell">
                    <div class="inner-header-box">
                        <div class="nap-title">NATIONAL ARCHIVES OF THE PHILIPPINES</div>
                        <span class="nap-subtitle">Pambansang Sinupan ng Pilipinas</span>
                        <div class="header-title">REQUEST FOR AUTHORITY TO DISPOSE<br>OF RECORDS</div>
                    </div>
                </td>
                <td colspan="2">
                    <span class="label">AGENCY NAME:</span>
                    <div style="font-size: 9.5pt;">{{ $data['agency_name'] ?? '' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">ADDRESS:</span>
                    <div style="font-size: 9.5pt;">{{ $data['address'] ?? '' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">DATE:</span>
                    <div style="font-size: 9.5pt;">{{ $data['date'] ?? '' }}</div>
                </td>
                <td colspan="2">
                    <span class="label">TELEPHONE NUMBER:</span>
                    <div style="font-size: 9.5pt;">{{ $data['telephone_number'] ?? '' }}</div>
                </td>
            </tr>

            <tr style="text-align: center; font-weight: bold; font-size: 10pt;">
                <td class="col-item-no">GRDS/ RDS ITEM NO.</td>
                <td class="col-description">RECORD SERIES TITLE AND DESCRIPTION</td>
                <td class="col-period">PERIOD COVERED</td>
                <td class="col-retention">RETENTION PERIOD AND PROVISION/S COMPLIED (If Any)</td>
            </tr>

            @foreach ($data['items'] ?? [] as $item)
                <tr class="record-row">
                    <td>{{ $item['grds_rds_item_no'] ?? '' }}</td>
                    <td class="text-left">{{ $item['record_series_title'] ?? '' }}</td>
                    <td>{{ $item['period_covered'] ?? '' }}</td>
                    <td>{{ $item['retention_period'] ?? '' }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="2">
                    <span class="label">LOCATION OF RECORDS:</span>
                    <div style="font-size: 9.5pt;">{{ $data['location_of_records'] ?? '' }}</div>
                </td>
                <td colspan="2">
                    <span class="label">VOLUME IN CUBIC METER:</span>
                    <div style="font-size: 9.5pt;">
                        {{ !empty($data['volume_in_cubic_meter']) ? $data['volume_in_cubic_meter'] . ' m³' : '' }}
                    </div>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <span class="label">PREPARED BY: (Name & Signature)</span>
                    <div class="sig-name" style="margin-top: 15px;">{{ $preparedByNameFormatted }}</div>
                </td>
                <td colspan="2">
                    <span class="label">POSITION:</span>
                    <div style="margin-top: 15px;">{{ $data['prepared_by_position'] ?? '' }}</div>
                </td>
            </tr>

            <tr>
                <td colspan="4" class="cert-container">
                    <div style="text-align: left;"><span class="label">CERTIFIED AND APPROVED BY:</span></div>
                    <p style="margin: 15px 0;">This is to certify that the above mentioned records are no longer needed
                        and<br>
                        not involved nor connected in any administrative or judicial cases.</p>

                    <div class="signature-block">
                        <div class="sig-name">{{ $certifiedByNameFormatted }}</div>
                        <div style="font-size: 9pt;">{{ $data['certified_by_designation'] ?? '' }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
