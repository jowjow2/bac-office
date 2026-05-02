<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.55;
            margin: 34px;
        }

        .content p {
            margin: 0 0 12px;
        }

        .image-preview {
            max-width: 100%;
        }

        .empty-state {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            padding: 18px;
        }
    </style>
</head>
<body>
    <div class="content">
        @if(($preview['mode'] ?? null) === 'text')
            {!! $preview['html'] ?? '<p>No preview text is available for this document.</p>' !!}
        @elseif(($preview['mode'] ?? null) === 'image')
            <img src="{{ $preview['embed_url'] }}" alt="{{ $preview['title'] ?? 'Document preview' }}" class="image-preview">
        @else
            <div class="empty-state">
                {{ $preview['message'] ?? 'This document is available only as a PDF preview.' }}
            </div>
        @endif
    </div>
</body>
</html>
