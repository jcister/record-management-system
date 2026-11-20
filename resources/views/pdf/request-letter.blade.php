<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: a4;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .request-letter-section {
            padding: 1.4in 0.8in 1.2in 0.8in;
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.3;
        }

        .letter-body {
            display: block;
        }

        .request-letter-section p {
            margin: 0;
            padding: 0;
        }

        .letter-header {
            position: absolute;
            top: 0;
            left: 0.8in;
            right: 0.8in;
            width: auto;
            box-sizing: border-box;
        }

        .letter-footer {
            position: fixed;
            bottom: 0;
            left: 0.8in;
            right: 0.8in;
            width: auto;
            height: 1in;
            text-align: center;
            box-sizing: border-box;
        }

        .header-image,
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
            margin-top: 28px;
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
            margin-top: 50px;
            font-family: 'Arial', sans-serif;
            font-weight: bold;
            font-size: 10pt;
        }
    </style>
</head>

<body>
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
    <div class="letter-header">
        <img src="{{ $headerImagePath }}" alt="CHED Header" class="header-image">
    </div>

    @if ($footerImagePath)
        <div class="letter-footer">
            <img src="{{ $footerImagePath }}" alt="CHED Footer" class="footer-image">
        </div>
    @endif

    <div class="request-letter-section">

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
                CONTROL ID{{ !empty($letter['control_id']) ? ' ' . $letter['control_id'] : '' }}
            </div>
        </div>
    </div>
</body>

</html>
