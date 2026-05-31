<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { margin-bottom: 12px; color: #444; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: bold; }
        .footnote { margin-top: 16px; font-size: 9px; color: #555; font-style: italic; }
    </style>
</head>
<body>
    <h1>{{ $document->title }}</h1>

    @if(isset($document->meta['applicant']))
        <div class="meta">
            <div>{{ __('export.applicant', ['name' => $document->meta['applicant']], 'de') }}</div>
            <div>{{ __('export.generated', ['date' => $document->meta['exported_at']], 'de') }}</div>
        </div>
    @endif

    @if(count($document->headers) > 0)
        <table>
            <thead>
                <tr>
                    @foreach($document->headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($document->rows as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{!! nl2br(e($cell)) !!}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(isset($document->meta['footnote']))
        <p class="footnote">{{ $document->meta['footnote'] }}</p>
    @endif
</body>
</html>
