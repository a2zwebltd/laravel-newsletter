<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Newsletter')) — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 0; background: #f9fafb; color: #111827; }
        .container { max-width: 720px; margin: 0 auto; padding: 32px 16px; }
        a { color: #4f46e5; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 16px; }
        h1 { font-size: 28px; margin: 0 0 16px; }
        .muted { color: #6b7280; }
        input[type=email] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; }
        button { background: #4f46e5; color: #fff; border: 0; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')
    </div>
</body>
</html>
