<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: a4;
            margin: 0.4in 0.4in 0.55in 0.4in;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }

        .top-meta {
            width: 100%;
            font-size: 8pt;
            margin-bottom: 5px;
            border-collapse: collapse;
        }

        .top-meta td {
            border: none;
            padding: 0;
        }

        .main-container {
            width: 100%;
            border: 1.5pt solid black;
            border-bottom: none;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .main-container td,
        .main-container th {
            border: 1pt solid black;
            padding: 6px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        /* Centered Header Box-in-Cell */
        .header-cell {
            padding: 10px !important;
            vertical-align: middle !important;
            text-align: center;
        }

        .inner-header-box {
            width: 90%;
            margin: 0 auto;
            border: 1pt solid black;
            padding: 15px 5px;
            text-align: center;
        }

        .nap-title {
            font-family: "Book Antiqua", Palatino, serif;
            font-weight: bold;
            font-size: 11pt;
            margin: 0;
            text-transform: uppercase;
        }

        .nap-subtitle {
            font-family: Verdana, sans-serif;
            font-style: italic;
            font-size: 8.5pt;
            display: block;
            margin-bottom: 8px;
        }

        .header-title {
            font-weight: bold;
            font-size: 10.5pt;
            margin-top: 8px;
            text-transform: uppercase;
        }

        .label {
            font-size: 7.5pt;
            font-weight: bold;
            display: block;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        /* Column Width Definitions */
        .col-item-no {
            width: 15%;
        }

        .col-description {
            width: 35%;
        }

        .col-period {
            width: 25%;
        }

        .col-retention {
            width: 25%;
        }

        /* HIDING HORIZONTAL BORDERS FOR RECORDS SECTION */
        .record-row td {
            border-top: none !important;
            border-bottom: none !important;
            /* Keep vertical lines */
            border-left: 1pt solid black;
            border-right: 1pt solid black;
            text-align: center;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        /* Restore borders for the very first row of the records block */
        .record-row:first-of-type td {
            border-top: 1pt solid black !important;
        }

        /* Last record row needs bottom border */
        .record-row:last-of-type td {
            border-bottom: 1pt solid black !important;
        }

        /* Spacer row to push footer to bottom of page */
        .spacer-row td {
            border-top: none !important;
            border-bottom: 1pt solid black !important;
            border-left: 1pt solid black;
            border-right: 1pt solid black;
            padding: 0 !important;
            font-size: 0;
            line-height: 0;
        }

        .text-left {
            text-align: left !important;
        }

        /* Footer table styling */
        .footer-container {
            width: 100%;
            border: 1.5pt solid black;
            border-top: none;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .footer-container td {
            border: 1pt solid black;
            padding: 6px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .footer-container tr:first-child td {
            border-top: none;
        }

        .footer-section {
            page-break-inside: avoid;
        }

        .cert-container {
            text-align: center;
            padding: 20px 10px !important;
        }

        .signature-block {
            width: 65%;
            margin-left: 35%;
            text-align: center;
            margin-top: 40px;
        }

        .sig-line {
            border-top: 1pt solid black;
            margin: 10px auto 5px auto;
            width: 100%;
        }

        .sig-name {
            font-weight: bold;
            font-size: 10pt;
        }

        .sig-sub {
            font-size: 8pt;
            line-height: 1.1;
        }
    </style>
</head>

<body>
    @php
        $items = array_values($data['items'] ?? []);
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

        // Calculate spacer height based on ACTUAL content height estimation
        // A4 usable area with margins (0.4in top, 0.55in bottom, 0.4in sides):
        // Width: 8.27in - 0.8in = 7.47in ≈ 538px at 72dpi
        // Height: 11.69in - 0.95in = 10.74in ≈ 773px at 72dpi
        $pageHeight = 773;
        $headerHeight = 220; // Top meta + NAP header + agency info + column headers
        $footerHeight = 200; // Footer section height
        $firstPageContentArea = $pageHeight - $headerHeight - $footerHeight; // ~353px
        $nextPageContentArea = $pageHeight - $footerHeight; // ~573px (no header on continuation pages)

        // Column widths for text wrapping estimation (in characters)
        // Description column is 35% of ~538px = ~188px ≈ 25 chars per line at 10pt
        // Retention column is 25% of ~538px = ~135px ≈ 18 chars per line at 10pt
        $descColCharsPerLine = 25;
        $retentionColCharsPerLine = 18;
        $baseRowHeight = 22; // Base height for single-line row (padding + line)
        $lineHeight = 14; // Height per additional line

        // Estimate height for each item based on content length
        $estimateRowHeight = static function (array $item) use ($descColCharsPerLine, $retentionColCharsPerLine, $baseRowHeight, $lineHeight): int {
            $descText = $item['record_series_title'] ?? '';
            $retentionText = $item['retention_period'] ?? '';

            // Calculate lines needed for each wrapping column
            $descLines = max(1, (int) ceil(mb_strlen($descText) / $descColCharsPerLine));
            $retentionLines = max(1, (int) ceil(mb_strlen($retentionText) / $retentionColCharsPerLine));

            // Row height is determined by the tallest cell
            $maxLines = max($descLines, $retentionLines);

            return $baseRowHeight + (($maxLines - 1) * $lineHeight);
        };

        // Calculate total content height and track page breaks
        $currentPageHeight = 0;
        $currentPage = 1;
        $contentAreaForCurrentPage = $firstPageContentArea;

        foreach ($items as $item) {
            $rowHeight = $estimateRowHeight($item);

            if ($currentPageHeight + $rowHeight > $contentAreaForCurrentPage) {
                // Move to next page
                $currentPage++;
                $contentAreaForCurrentPage = $nextPageContentArea;
                $currentPageHeight = $rowHeight;
            } else {
                $currentPageHeight += $rowHeight;
            }
        }

        // Calculate spacer to fill remaining space on last page
        $remainingSpace = $contentAreaForCurrentPage - $currentPageHeight;
        $spacerHeight = max(0, $remainingSpace);
    @endphp

    {{-- Top meta info --}}
    <table class="top-meta">
        <tr>
            <td style="text-align: left;">NAP Form No. 3<br>Revised 2012</td>
            <td style="text-align: right;">Accomplish in 3 copies</td>
        </tr>
    </table>

    {{-- Main content table (header + records + spacer) --}}
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

        @foreach ($items as $item)
            <tr class="record-row">
                <td>{{ $item['grds_rds_item_no'] ?? '' }}</td>
                <td class="text-left">{{ $item['record_series_title'] ?? '' }}</td>
                <td>{{ $item['period_covered'] ?? '' }}</td>
                <td>{{ $item['retention_period'] ?? '' }}</td>
            </tr>
        @endforeach

        @if ($spacerHeight > 0)
            <tr class="spacer-row">
                <td style="height: {{ $spacerHeight }}px;">&nbsp;</td>
                <td style="height: {{ $spacerHeight }}px;">&nbsp;</td>
                <td style="height: {{ $spacerHeight }}px;">&nbsp;</td>
                <td style="height: {{ $spacerHeight }}px;">&nbsp;</td>
            </tr>
        @endif
    </table>

    {{-- Footer table (Location, Prepared By, Certified) --}}
    <div class="footer-section">
        <table class="footer-container">
            <tr>
                <td colspan="2" style="width: 50%;">
                    <span class="label">LOCATION OF RECORDS:</span>
                    <div style="font-size: 9.5pt;">{{ $data['location_of_records'] ?? '' }}</div>
                </td>
                <td colspan="2" style="width: 50%;">
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
                    <p style="margin: 15px 0;">This is to certify that the above mentioned records are no longer
                        needed
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
