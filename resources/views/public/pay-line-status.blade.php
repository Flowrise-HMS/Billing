<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }}</title>
</head>
<body style="font-family: system-ui, sans-serif; background: #f9fafb; margin: 0; padding: 40px 16px;">
    <div style="max-width: 420px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
        @php
            $colors = [
                'success' => '#10b981',
                'warning' => '#f59e0b',
                'danger' => '#ef4444',
            ];
            $color = $colors[$tone] ?? '#6b7280';
        @endphp
        <div style="width: 48px; height: 48px; border-radius: 50%; background: {{ $color }}22; color: {{ $color }}; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 24px;">
            @if($tone === 'success') &#10003; @elseif($tone === 'danger') &times; @else ! @endif
        </div>
        <h1 style="font-size: 18px; margin: 0 0 8px;">{{ $title }}</h1>
        <p style="color: #4b5563; font-size: 14px; line-height: 1.5; margin: 0;">{{ $message }}</p>
    </div>
</body>
</html>
