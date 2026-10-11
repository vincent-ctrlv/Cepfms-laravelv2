{{-- Shared <head> + opening <body>. Expects optional $pageTitle to be set before include. --}}
@php
$pageTitle = $pageTitle ?? 'CEPFMS';
// Citizen portal pages load Tailwind pre-built (public/frontend/css/tailwind.css, see
// tailwind.config.js), so their dark pages don't flash while the CDN builds
// styles in the browser. Admin and staff login pages still use the CDN.
$prebuiltTailwind = request()->is('citizen', 'citizen/*');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $pageTitle }} — {{ setting('brand_short_name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;500;600&display=swap" rel="stylesheet">
@if (!$prebuiltTailwind)
<script src="https://cdn.tailwindcss.com"></script>
<script>
  // Keep in sync with tailwind.config.js (the citizen portal's build).
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          navy: '#0f1e3d',
          teal: '#2563eb',
          lightteal: '#3b82f6',
          slate: '#475569',
          emerald: '#059669',
          amber: '#d97706',
          dangerred: '#dc2626',
          borderc: '#e2e8f0',
          pagebg: '#f1f5f9',
        },
        fontFamily: {
          sans: ['Inter', 'system-ui', 'sans-serif'],
          mono: ['Roboto Mono', 'monospace'],
        },
        borderRadius: { DEFAULT: '4px' },
      },
    },
  };
</script>
@endif
<link rel="stylesheet" href="{{ asset_v('frontend/css/styles.css') }}">
<link rel="stylesheet" href="{{ asset_v('frontend/css/app.css') }}">
<link rel="stylesheet" href="{{ asset_v('frontend/css/citizen.css') }}">
<script>window.APP_URL = {!! json_encode(rtrim(request()->getBasePath(), '/')) !!};</script>
@if ($prebuiltTailwind)
<!-- Last in <head>, where the CDN puts its styles, so the same rules win as before. -->
<link rel="stylesheet" href="{{ asset_v('frontend/css/tailwind.css') }}">
@else
<!-- The Tailwind CDN builds its CSS a moment after the page starts drawing. Until
     then these two utilities would be missing, so the skip link showed as a light
     bar at the top and hidden elements appeared: a flash on every refresh.
     Tailwind's own CSS lands after this, so its variants (focus:, sm:) still win. -->
<style>
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0; }
.hidden { display: none; }
</style>
@endif
</head>
<body class="bg-pagebg text-navy font-sans antialiased min-h-screen citizen-portal">
<div id="toast-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-2"></div>
