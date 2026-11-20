<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: a4 landscape;
            margin: 0.4in 0.5in;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5pt;
            color: #000;
            line-height: 1.1;
            margin: 0;
            padding: 0;
        }

        .form-number {
            font-size: 9pt;
            margin-bottom: 5px;
        }

        .main-table {
            width: 100%;
            border: 1pt solid black;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-table {
            margin-bottom: 0;
            border-bottom: none;
        }

        .records-table {
            margin-top: -1px;
        }

        .main-table td,
        .main-table th {
            border: 1pt solid black;
            padding: 3px 5px;
            vertical-align: top;
        }

        .main-table .body-cell {
            border-top: none;
            border-bottom: none;
            border-left: 1pt solid black;
            border-right: 1pt solid black;
            padding: 2px 4px;
            vertical-align: top;
            text-align: center;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            overflow: hidden;
        }

        .main-table .body-cell-left {
            text-align: left;
        }

        .main-table .body-cell-last {
            border-bottom: 1pt solid black;
        }

        .nap-header {
            width: 35%;
        }

        .agency-title {
            font-family: "Book Antiqua", Palatino, serif;
            font-weight: bold;
            font-size: 10pt;
            margin: 0;
        }

        .agency-subtitle {
            font-family: Verdana, sans-serif;
            font-style: italic;
            font-size: 8pt;
            display: block;
        }

        .form-title {
            font-weight: bold;
            font-size: 11pt;
            margin-top: 5px;
            text-transform: uppercase;
        }

        .label {
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            display: block;
        }

        .input-text {
            font-size: 9pt;
            min-height: 12pt;
            text-align: center;
        }

        .col-title {
            font-size: 7.5pt;
            font-weight: bold;
            text-align: center;
            vertical-align: middle !important;
            line-height: 1.05;
        }

        .row-item {
            height: 14px;
        }

        .page-break {
            page-break-before: always;
        }

        .records-body tr:last-child .body-cell {
            border-bottom: 1pt solid black;
        }

        .legend-section {
            margin-top: 5px;
            font-size: 8pt;
            width: 100%;
        }

        .end-section {
            page-break-inside: avoid;
        }

        .footer-table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
        }

        .footer-table td {
            width: 33%;
            text-align: center;
            vertical-align: top;
            padding: 0 12px;
        }

        .label-signatory {
            display: block;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
            margin: 0 0 75px 0;
        }

        .sig-name {
            min-height: 14px;
            font-weight: bold;
            font-size: 9pt;
            line-height: 1.1;
        }

        .sig-position {
            min-height: 12px;
            font-size: 8pt;
            line-height: 1.1;
        }

        .sig-line {
            width: 92%;
            border-top: 1pt solid black;
            margin: 4px auto 2px auto;
        }

        .sig-role {
            font-size: 7.5pt;
            display: none;
        }
    </style>
</head>

<body>
    <div class="form-number">NAP Form No. 1</div>

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

            $parts = explode(',', $value, 2);
            $mainName = trim((string) ($parts[0] ?? ''));
            $extension = trim((string) ($parts[1] ?? ''));
            $tokens = preg_split('/\s+/', $mainName, -1, PREG_SPLIT_NO_EMPTY) ?: [];

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

            $coreParts = array_values(
                array_filter([
                    $prefix !== '' ? mb_strtoupper($prefix) : '',
                    $firstName !== '' ? mb_strtoupper($firstName) : '',
                    $middleInitial,
                    $lastName !== '' ? mb_strtoupper($lastName) : '',
                ]),
            );

            $coreName = trim(preg_replace('/\s+/', ' ', implode(' ', $coreParts)) ?? '');

            return $extension !== '' ? $coreName . ', ' . mb_strtoupper($extension) : $coreName;
        };

        $preparedByNameFormatted = $formatSignatoryName($data['prepared_by_name'] ?? '');
        $approvedByNameFormatted = $formatSignatoryName($data['approved_by_name'] ?? '');
        $personInChargeValue = (string) ($data['prepared_by_position'] ?? ($data['person_in_charge'] ?? ''));

        // Smart dynamic chunking based on content height estimation (character counts)
        $pages = [];
        $currentPage = [];
        
        // Define safe layout height budgets (units of text lines)
        $firstPageMaxWeight = 14;      // Optimized for A4 landscape with metadata header
        $subsequentPageMaxWeight = 22; // Optimized for subsequent pages (column headers only)
        
        $currentWeight = 0;
        $isFirstPage = true;

        if (empty($items)) {
            $pages[] = [];
        } else {
            foreach ($items as $item) {
                $title = (string) ($item['title'] ?? '');
                $period = (string) ($item['period'] ?? '');
                $location = (string) ($item['location'] ?? '');
                $provision = (string) ($item['provision'] ?? '');
                
                // Estimate lines of wrapped text based on column widths:
                // Column 9 (title) has 16% width (~25 chars per line)
                // Column 10 (period) has 9% width (~14 chars per line)
                // Column 14 (location) has 8% width (~12 chars per line)
                // Column 20 (provision) has 8% width (~12 chars per line)
                $titleLines = ceil(strlen($title) / 25);
                $periodLines = ceil(strlen($period) / 14);
                $locationLines = ceil(strlen($location) / 12);
                $provisionLines = ceil(strlen($provision) / 12);
                
                // The item's weight is the maximum lines it wraps to, min 1 line
                $itemWeight = max(1, $titleLines, $periodLines, $locationLines, $provisionLines);
                
                $maxWeight = $isFirstPage ? $firstPageMaxWeight : $subsequentPageMaxWeight;
                
                // If this item will overflow the current page, split to next page
                if ($currentWeight + $itemWeight > $maxWeight && !empty($currentPage)) {
                    $pages[] = $currentPage;
                    $currentPage = [];
                    $currentWeight = 0;
                    $isFirstPage = false;
                    $maxWeight = $subsequentPageMaxWeight;
                }
                
                $currentPage[] = $item;
                $currentWeight += $itemWeight;
            }
            
            if (!empty($currentPage)) {
                $pages[] = $currentPage;
            }
        }
    @endphp

    @foreach ($pages as $pageIndex => $pageItems)
        @if ($pageIndex > 0)
            <div class="page-break"></div>
        @endif

        @if ($pageIndex === 0)
            <table class="main-table header-table">
                <colgroup>
                    <col style="width:16%;">
                    <col style="width:9%;">
                    <col style="width:5%;">
                    <col style="width:8%;">
                    <col style="width:8%;">
                    <col style="width:8%;">
                    <col style="width:7%;">
                    <col style="width:7%;">
                    <col style="width:5%;">
                    <col style="width:10%;">
                    <col style="width:9%;">
                    <col style="width:8%;">
                </colgroup>
                <tbody>
                    <!-- Unified Header Row 1 -->
                    <tr>
                        <td colspan="3" rowspan="3" class="nap-header" style="vertical-align: middle; text-align: left; padding: 10px 15px;">
                            <div class="agency-title">NATIONAL ARCHIVES OF THE PHILIPPINES</div>
                            <span class="agency-subtitle">Pambansang Sinupan ng Pilipinas</span>
                            <div class="form-title" style="margin-top: 10px;">RECORDS INVENTORY AND APPRAISAL</div>
                        </td>
                        <td colspan="4" rowspan="2">
                            <span class="label">1. NAME OF OFFICE:</span>
                            <div class="input-text" style="text-align: left; font-weight: bold; font-size: 8.5pt;">{{ strtoupper($data['agency'] ?? '') }}</div>
                        </td>
                        <td colspan="3" rowspan="1">
                            <span class="label">2. DEPARTMENT/DIVISION:</span>
                            <div class="input-text" style="text-align: left; font-weight: bold; font-size: 8.5pt;">{{ strtoupper($data['organizational_unit'] ?? '') }}</div>
                        </td>
                        <td colspan="2" rowspan="1">
                            <span class="label">4. TELEPHONE NO.:</span>
                            <div class="input-text" style="text-align: left; font-weight: bold; font-size: 8.5pt;">{{ $data['telephone'] ?? '' }}</div>
                        </td>
                    </tr>
                    <!-- Unified Header Row 2 -->
                    <tr>
                        <td colspan="3" rowspan="1">
                            <span class="label">3. SECTION/UNIT:</span>
                            <div class="input-text" style="text-align: left; font-weight: bold; font-size: 8.5pt;">{{ strtoupper($data['section_unit'] ?? '') }}</div>
                        </td>
                        <td colspan="2" rowspan="1">
                            <span class="label">5. EMAIL ADDRESS:</span>
                            <div class="input-text" style="text-align: left; font-weight: bold; font-size: 8.5pt;">{{ $data['email'] ?? '' }}</div>
                        </td>
                    </tr>
                    <!-- Unified Header Row 3 -->
                    <tr>
                        <td colspan="4" rowspan="1" style="border-bottom: 1pt solid black;">
                            <span class="label">6. ADDRESS:</span>
                            <div class="input-text" style="text-align: left; font-weight: bold; font-size: 8.5pt;">{{ strtoupper($data['address'] ?? '') }}</div>
                        </td>
                        <td colspan="3" rowspan="1" style="border-bottom: 1pt solid black;">
                            <span class="label">7. PERSON-IN-CHARGE OF FILES:</span>
                            <div class="input-text" style="text-align: left; font-weight: bold; font-size: 8.5pt;">{{ strtoupper($personInChargeValue) }}</div>
                        </td>
                        <td colspan="2" rowspan="1" style="border-bottom: 1pt solid black;">
                            <span class="label">8. DATE PREPARED:</span>
                            <div class="input-text" style="text-align: left; font-weight: bold; font-size: 8.5pt;">{{ $data['date_prepared'] ?? '' }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif

        <table class="main-table records-table" style="{{ $pageIndex > 0 ? 'margin-top: 0;' : '' }}">
            <colgroup>
                <col style="width:16%;">
                <col style="width:9%;">
                <col style="width:5%;">
                <col style="width:8%;">
                <col style="width:8%;">
                <col style="width:8%;">
                <col style="width:7%;">
                <col style="width:7%;">
                <col style="width:5%;">
                <col style="width:10%;">
                <col style="width:9%;">
                <col style="width:8%;">
            </colgroup>

            <thead>
                <!-- Columns Title Row -->
                <tr class="col-title" style="background-color: #f5f5f5;">
                    <td style="font-size: 6.8pt; font-weight: bold; height: 35px; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">9. RECORDS</span> SERIES TITLE AND DESCRIPTION</td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">10. PERIOD</span> COVERED/ INCLUSIVE DATES</td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">11. VOLUME</span></td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">12. RECORDS</span> MEDIUM</td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">13. RESTRICTION/S</span></td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">14. LOCATION</span> OF RECORDS</td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">15. FREQUENCY</span> OF USE</td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">16. DUPLICATION</span></td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">17. TIME</span> VALUE (T/P)</td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">18. UTILITY</span> VALUE<br><span style="font-size: 6pt; font-weight: normal;">(Adm/F/L/Arc)</span></td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">19. RETENTION</span> PERIOD</td>
                    <td style="font-size: 6.8pt; font-weight: bold; vertical-align: middle; line-height: 1.15; padding: 4px 1px;"><span style="white-space: nowrap;">20. DISPOSITION</span> PROVISION</td>
                </tr>
            </thead>

            <tbody class="records-body">
                @forelse ($pageItems as $item)
                    <tr class="row-item">
                        <td class="body-cell body-cell-left">{{ $item['title'] ?? '' }}</td>
                        <td class="body-cell">{{ $item['period'] ?? '' }}</td>
                        <td class="body-cell">{{ !empty($item['volume']) ? $item['volume'] . ' m³' : '' }}</td>
                        <td class="body-cell">{{ $item['medium'] ?? '' }}</td>
                        <td class="body-cell">{{ $item['restriction'] ?? '' }}</td>
                        <td class="body-cell">{{ $item['location'] ?? '' }}</td>
                        <td class="body-cell">{{ $item['frequency'] ?? '' }}</td>
                        <td class="body-cell">{{ $item['duplication'] ?? '' }}</td>
                        <td class="body-cell">{{ $item['time_value'] ?? '' }}</td>
                        <td class="body-cell">{{ $item['utility_value'] ?? '' }}</td>
                        <td class="body-cell">
                            @if(!empty($item['retention_total']))
                                {{ $item['retention_total'] }}
                            @else
                                {{ trim(($item['retention_active'] ?? '') . ' / ' . ($item['retention_storage'] ?? ''), ' /') }}
                            @endif
                        </td>
                        <td class="body-cell">{{ $item['provision'] ?? '' }}</td>
                    </tr>
                @empty
                    <tr class="row-item">
                        <td class="body-cell body-cell-left">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                        <td class="body-cell">&nbsp;</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <div class="end-section">
        <div class="legend-section">
            <strong>LEGEND:</strong><br>
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="width: 80px;">TIME VALUE:</td>
                    <td>T - Temporary &nbsp;&nbsp;&nbsp; P - Permanent</td>
                </tr>
                <tr>
                    <td>UTILITY VALUE:</td>
                    <td>Adm - Administrative &nbsp;&nbsp;&nbsp; F - Fiscal &nbsp;&nbsp;&nbsp; L - Legal
                        &nbsp;&nbsp;&nbsp;
                        Arc - Archival</td>
                </tr>
            </table>
        </div>

        <table class="footer-table">
            <tr>
                <td>
                    <span class="label-signatory">PREPARED BY:</span>
                    <div class="sig-name">&nbsp;&nbsp;&nbsp;{{ $preparedByNameFormatted }}</div>
                    <div class="sig-position">
                        {{ trim(($data['prepared_by_position'] ?? '') . (!empty($data['prepared_by_role']) ? ', ' . $data['prepared_by_role'] : '')) }}
                    </div>
                    <div class="sig-line"></div>
                </td>
                <td>
                    <span class="label-signatory">ASSISTED BY:</span>
                    <div class="sig-name">&nbsp;&nbsp;&nbsp;{{ $data['assisted_by_name'] ?? '' }}</div>
                    <div class="sig-position">&nbsp;</div>
                    <div class="sig-line"></div>
                    <span class="sig-role" style="display: block;">NAP Records Management Analyst</span>
                </td>
                <td>
                    <span class="label-signatory">APPROVED BY:</span>
                    <div class="sig-name">&nbsp;&nbsp;&nbsp;{{ $approvedByNameFormatted }}</div>
                    <div class="sig-position">
                        {{ trim(($data['approved_by_designation'] ?? '') . (!empty($data['approved_by_role']) ? ', ' . $data['approved_by_role'] : '')) }}
                    </div>
                    <div class="sig-line"></div>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
