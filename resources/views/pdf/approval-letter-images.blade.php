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
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            color: #000;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        .page-wrapper {
            min-height: 100vh;
            padding: 0.15in 0.2in 0.25in;
            box-sizing: border-box;
            position: relative;
        }

        .page-title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 8px;
        }

        .page-number {
            position: absolute;
            right: 0.2in;
            bottom: 0.2in;
            font-size: 11pt;
        }

        .images-grid {
            width: 100%;
            margin-top: 12px;
        }

        .image-item {
            text-align: center;
            margin-bottom: 16px;
            page-break-inside: avoid;
        }

        .image-item img {
            display: block;
            margin: 0 auto;
            max-width: 100%;
            max-height: 280px;
            width: auto;
            height: auto;
        }
    </style>
</head>

<body>
    @php
        $images = $imageDataUris ?? [];
        $maxPageWidth = 495; // ~6.5 inches at 76 DPI (safe area within 0.4in margins)
        $maxPageHeight = 280;
    @endphp

    @php
        $pages = $imagePages ?? ($images ? [array_values($images)] : []);
        $totalPages = count($pages);
    @endphp

    @foreach ($pages as $pageIndex => $pageImages)
        <div class="page-wrapper">
            <div class="page-title">Photo Documentations</div>
            <div class="images-grid">
                @foreach ($pageImages as $image)
                    @php
                        $origWidth = $image['width'] ?: 400;
                        $origHeight = $image['height'] ?: 300;

                        $scale = min($maxPageWidth / $origWidth, $maxPageHeight / $origHeight, 1);
                        $displayWidth = (int) round($origWidth * $scale);
                        $displayHeight = (int) round($origHeight * $scale);
                    @endphp
                    <div class="image-item">
                        <img src="{{ $image['data_uri'] }}" alt="{{ $image['name'] }}" width="{{ $displayWidth }}"
                            height="{{ $displayHeight }}" />
                    </div>
                @endforeach
            </div>
            <div class="page-number">{{ $pageIndex + 1 }} of {{ $totalPages }}</div>
        </div>

        @if ($pageIndex + 1 < $totalPages)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach
</body>

</html>
