<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title') · LaravelAPI</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font: 16px/1.5 system-ui, sans-serif; background: Canvas; color: CanvasText; }
        main { width: 100%; max-width: 440px; border: 1px solid GrayText; border-radius: 12px; padding: 28px; }
        h1 { margin-top: 0; font-size: 24px; }
        label { display: block; margin-top: 16px; }
        input, button { font: inherit; border-radius: 6px; padding: 10px 12px; }
        input { width: 100%; border: 1px solid GrayText; margin-top: 6px; }
        button { cursor: pointer; margin-top: 20px; }
        .error { color: light-dark(#b42318, #ffb4ab); }
        a { color: LinkText; }
    </style>
</head>
<body>
    <main>@yield('content')</main>
</body>
</html>
