{{-- Shared top of every admin page (behind the `staff` middleware).
     Expects $pageTitle and $activeNav to be set beforehand. --}}
@php $pageTitle = $pageTitle ?? 'Admin'; @endphp
@include('partials.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
  // The header partial maps `amber` / `emerald` to single colors, so shade classes like
  // bg-amber-50 or text-emerald-700 silently produce nothing. Admin pages use
  // those shades for warnings, so give them full scales here (DEFAULT keeps
  // plain `text-amber` working). Admin-only: the citizen portal is unaffected.
  (function () {
    var cfg = window.tailwind && tailwind.config;
    if (!cfg) return;
    var c = cfg.theme.extend.colors;
    c.amber = { DEFAULT: '#d97706', 50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 300: '#fcd34d', 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e', 900: '#78350f' };
    c.emerald = { DEFAULT: '#059669', 50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7', 400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857', 800: '#065f46', 900: '#064e3b' };
    tailwind.config = cfg;
  })();
</script>
<link rel="stylesheet" href="{{ asset_v('frontend/css/admin.css') }}">
<div class="admin-shell flex min-h-screen">
  @include('partials.sidebar')
  <div class="flex-1 min-w-0 flex flex-col lg:ml-64">
    <header class="admin-topbar no-print bg-white border-b border-borderc sticky top-0 z-30 px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <button id="sidebar-toggle" type="button" class="lg:hidden text-navy flex-shrink-0 p-1.5 -ml-1.5 rounded-lg hover:bg-pagebg" aria-label="Open menu">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <h1 class="text-base font-semibold text-navy truncate">{{ $pageTitle }}</h1>
      </div>

      <div class="flex items-center gap-3 sm:gap-4">
        <span class="text-xs text-slate font-mono hidden lg:block flex-shrink-0">{!! date('l, F j, Y') !!}</span>

        <form action="{{ app_href('admin/submissions') }}" method="get" class="relative hidden md:block">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate pointer-events-none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="search" placeholder="Search submissions, complaints, proposals…"
                 class="w-72 lg:w-96 pl-9 pr-3 py-2 text-sm rounded-lg bg-pagebg border border-borderc focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
        </form>

        <div class="relative flex-shrink-0">
          <button id="notif-bell" type="button" class="relative text-slate hover:text-navy transition p-1.5 rounded-lg hover:bg-pagebg" title="Notifications" aria-label="Notifications" data-user="{!! (int) ($staff['id'] ?? 0) !!}">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            <span id="notif-dot" class="hidden absolute -top-0.5 -right-0.5 min-w-[17px] h-[17px] px-1 rounded-full bg-dangerred text-white text-[10px] font-bold leading-[17px] text-center ring-2 ring-white"></span>
          </button>
          <div id="notif-panel" class="hidden absolute right-0 mt-2 w-80 max-w-[90vw] bg-white border border-borderc rounded-lg shadow-lg z-40">
            <div class="px-4 py-3 border-b border-borderc flex items-center justify-between">
              <p class="text-sm font-semibold text-navy">Notifications</p>
              <a id="notif-view-all" href="{{ app_href(is_admin() ? 'admin/moderation' : 'admin/submissions') }}" class="text-[11px] text-teal hover:underline">View all</a>
            </div>
            <div id="notif-list" class="max-h-80 overflow-y-auto divide-y divide-borderc">
              <p class="px-4 py-6 text-xs text-slate text-center">Loading…</p>
            </div>
          </div>
        </div>
      </div>
    </header>
    <main class="flex-1 p-4 sm:p-6">
