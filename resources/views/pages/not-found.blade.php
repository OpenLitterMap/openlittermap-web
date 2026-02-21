{{-- not-found.blade.php --}}
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenLitterMap</title>
    <link rel="icon" href="{{ asset('assets/favicon/favicon.ico') }}" type="image/x-icon">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f7fa;
            text-align: center;
            color: #333;
        }
        .container { max-width: 650px; padding: 2rem; }
        h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        h2 { font-size: 1.25rem; color: #666; font-weight: normal; }
        img { max-width: 100%; height: auto; margin: 2rem 0; }
        a.button {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #00d1b2;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 1.1rem;
        }
        a.button:hover { background: #00b89c; }
    </style>
</head>
<body>
<div class="container">
    <h1>Thanks for checking out OpenLitterMap!</h1>
    <h2>Oops! This impact report is still chilling in the future.</h2>
    <h2>Come back with a time-machine or try again later.</h2>
    <img src="/assets/images/cleaning-planet.webp" alt="Cleaning Planet" />
    <p><a href="/" class="button">Return to OpenLitterMap</a></p>
</div>
</body>
</html>
