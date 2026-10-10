<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Infrastructure contract tracking · SJBAC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/ui.css'])
    <style>
        html, body { min-height: 100%; background: #fff !important; }
        body.infra-embedded { margin: 0; }
        .infra-embedded__content { max-width: none; padding: 20px; background: #fff; }
        .infra-embedded .infra-card { margin: 0; background: #fff !important; box-shadow: none; }
        .infra-embedded .infra-panel,
        .infra-embedded .infra-meta--boxed,
        .infra-embedded .infra-changes { background: #fff !important; }
        .infra-embedded__content > .ui-alert { margin-bottom: 14px; }
        @media (max-width: 640px) { .infra-embedded__content { padding: 12px; } }
    </style>
</head>
<body class="portal infra-embedded">
    <main class="infra-embedded__content ui-main" id="main">
        @if(session('success'))
            <div class="ui-alert ui-alert--success" role="status"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="ui-alert ui-alert--danger" role="alert">
                <strong>Please check the submitted contract details.</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @include('infrastructure-contracts.card', ['award' => $award, 'record' => $record, 'mode' => $mode])
    </main>
    @if(session('success'))
        <script>window.parent.postMessage({ type: 'sjbac:infrastructure-updated', awardId: {{ (int) $award->id }} }, window.location.origin);</script>
    @endif
</body>
</html>
