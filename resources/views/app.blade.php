<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title inertia>{{ config('app.name', 'InCare CRM') }}</title>
    <meta name="description" content="CRM-интерфейс для обработки лидов и сделок клиники InCare." />
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=sora:400,500,600,700,800|ibm-plex-mono:400,500" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @inertiaHead
</head>
<body class="crm-body antialiased">
    @inertia
</body>
</html>