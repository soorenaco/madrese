<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#7c3aed">

    @vite(['resources/css/app.css', 'resources/js/inertia.js'])
    @inertiaHead
</head>
<body class="min-h-screen text-slate-800 antialiased">
    @inertia
</body>
</html>
