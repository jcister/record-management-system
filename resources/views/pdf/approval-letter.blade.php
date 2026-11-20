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
            font-family: Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.3;
        }

        p {
            margin: 0;
            padding: 0;
        }

        .approval-letter-section {
            padding: 1.4in 0.8in 1.2in 0.8in;
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

        .body-text--notes {
            font-style: italic;
        }

        .attachment-block {
            margin: 6px 0 15px 70px;
        }

        .attachment-list {
            margin: 0;
            padding-left: 1.2rem;
            list-style-position: outside;
        }

        .attachment-list li {
            margin: 0;
            font-style: italic;
            font-weight: normal;
            line-height: 1.1;
        }

        .attachment-sublist {
            margin: 4px 0 0 1.8rem;
            padding: 0;
            list-style-type: disc;
            font-style: normal;
        }

        .attachment-sublist li {
            margin: 0;
            line-height: 1.1;
        }

        .closing-section {
            margin-left: auto;
            margin-top: 28px;
            width: 55%;
            text-align: left;
        }

        .signature-wrapper {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            width: auto;
        }

        .signature-gap {
            height: 45px;
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

        .director-name {
            font-weight: bold;
            text-align: center;
        }

        .director-designation {
            width: 100%;
            text-align: center;
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
            $lastName = $nameParts && count($nameParts) > 0 ? rtrim((string) end($nameParts), ',') : "Sir/Ma'am";
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

        $actualDisposalDate = trim((string) ($summary['actual_disposal_date'] ?? ''));
        if ($actualDisposalDate === '' && !empty($summary['completed_at'])) {
            $actualDisposalDate = \Illuminate\Support\Carbon::parse($summary['completed_at'])->format('F j, Y');
        }
        if ($actualDisposalDate === '') {
            $actualDisposalDate = now()->format('F j, Y');
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

    <div class="approval-letter-section">
        <div class="letter-body">
            <div class="date-container">
                {{ $letter['date'] ?? now()->format('F j, Y') }}
            </div>

            <div class="recipient-info">
                <p class="recipient-name">
                    <strong>{{ $recipientName !== '' ? strtoupper($recipientName) : 'NAME' }}@if (!empty($recipientExtension))
                            <span class="no-transform">, {{ $recipientExtension }}</span>
                        @endif
                    </strong>
                </p>
                <p>{{ $letter['recipient_position'] ?? 'Position' }}</p>
                @if (!empty($letter['recipient_designation']))
                    <p>{{ $letter['recipient_designation'] }}</p>
                @endif
                <p>Commission on Audit XI</p>
                @if (!empty($letter['recipient_address']))
                    <p>{{ $letter['recipient_address'] }}</p>
                @endif
            </div>

            <div class="salutation">
                Dear {{ $salutationPrefix !== '' ? $salutationPrefix . ' ' : '' }}{{ $lastName }},
            </div>

            <div class="body-text">
                Greetings from CHED RO XI!
            </div>

            <div class="body-text">
                This is to submit the documentary requirements in relation to the conducted CHED XI Disposal of
                Valueless Records on {{ $actualDisposalDate }}.
            </div>

            @if (!empty($letter['notes']))
                <div class="body-text body-text--notes">
                    Approval Notes: {{ $letter['notes'] }}
                </div>
            @endif

            <div class="body-text">
                Attached herewith:
            </div>

            @if (!empty($attachmentSummary))
                <div class="attachment-block">
                    <ol class="attachment-list">
                        @foreach ($attachmentSummary as $attachment)
                            @php
                                $children = $attachment['children'] ?? [];
                            @endphp
                            <li>
                                {{ $attachment['label'] }}
                                @if (!empty($children))
                                    <ul class="attachment-sublist">
                                        @foreach ($children as $child)
                                            <li>{{ $child['label'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @else
                <div class="attachment-block">
                    No attachment summaries are available at this time.
                </div>
            @endif

            <div class="body-text">
                For your reference.
            </div>

            <div class="closing-section">
                <div class="signature-wrapper">
                    <p class="closing-text">Very truly yours,</p>
                    <div class="signature-gap"></div>
                    <div class="signature-block">
                        <p class="director-name">
                            <strong>{{ $director['name'] ?? 'MARICAR R. CASQUEJO, Ph.D., CESO III' }}</strong>
                        </p>
                        <p class="director-designation">{{ $director['designation'] ?? 'Director IV' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
