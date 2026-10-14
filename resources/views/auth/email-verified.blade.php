<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ok ? 'Email confirmed' : 'Link not valid' }} - SJBAC</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: #f4f7f5; font-family: Inter, system-ui, sans-serif; color: #1b2420; }
        .card { width: min(440px, 100%); padding: 32px 28px; border: 1px solid #dfe6e1; border-radius: 16px; background: #fff; text-align: center; box-shadow: 0 10px 30px rgba(15, 25, 21, .08); }
        .icon { display: grid; width: 56px; height: 56px; margin: 0 auto 16px; place-items: center; border-radius: 50%; font-size: 26px; background: {{ $ok ? '#e3f0ea' : '#fbeaea' }}; color: {{ $ok ? '#1f5c45' : '#b42318' }}; }
        h1 { margin: 0 0 8px; font-size: 21px; }
        p { margin: 0 0 20px; color: #4b5b53; font-size: 14.5px; line-height: 1.6; }
        a { display: inline-block; padding: 11px 22px; border-radius: 10px; background: #1f5c45; color: #fff; font-weight: 700; font-size: 14px; text-decoration: none; }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon" aria-hidden="true">{{ $ok ? '✓' : '!' }}</div>
        <h1>{{ $ok ? 'Email confirmed' : 'This link is not valid' }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}">Go to the SJBAC portal</a>
    </main>
</body>
</html>
