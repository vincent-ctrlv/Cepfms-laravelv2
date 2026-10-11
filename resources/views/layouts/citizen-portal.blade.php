{{-- Citizen portal app shell, laid out like the staff portal: a fixed navy side menu
     (slides in on phones), a white top bar with the page title, and the content.
     Pages: @extends('layouts.citizen-portal'), set $portalNav (dashboard | submissions |
     verify | profile) and $pageTitle, and fill @section('content'). --}}
@include('partials.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
  $portalCitizen = \App\Support\CitizenAuth::current();
  $portalStatus = \App\Support\CitizenAuth::status();
  $portalNav = $portalNav ?? '';
  $icon = fn (string $paths) => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
  $portalSections = [
      'Overview' => [
          'dashboard' => ['Dashboard', 'Your reports at a glance', 'citizen/dashboard', '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>'],
      ],
      'My Reports' => [
          'submit'      => ['Submit Feedback', 'Report an issue or idea', 'citizen/submit', '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
          'submissions' => ['My Submissions', 'Progress and replies', 'citizen/my-submissions', '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'],
      ],
      'Account' => [
          'verify'  => ['ID Verification', $portalStatus === 'Approved' ? 'Verified' : ($portalStatus === 'Pending' ? 'Under review' : 'Not verified yet'), 'citizen/verify', '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2.5"/><line x1="13" y1="10" x2="18" y2="10"/><line x1="13" y1="14" x2="17" y2="14"/>'],
          'profile' => ['Profile', 'Contact details', 'citizen/profile', '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/>'],
      ],
  ];
  $portalInitials = mb_strtoupper(collect(explode(' ', trim($portalCitizen['full_name'] ?? '')))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''));
@endphp

<a href="#cp-content" class="sr-only focus:not-sr-only">Skip to main content</a>
<div class="cp-app">
  <aside id="cp-sidebar" class="cp-sidebar" aria-label="Citizen portal">
    <div class="cp-brand">
      <a href="{{ app_href('citizen') }}" class="cp-brand-link" title="Back to the public website">
        <span class="cp-brand-seal"><img src="{{ brand_logo_url() }}" alt="{{ setting('brand_government') }} logo"></span>
        <span class="min-w-0">
          <span class="cp-brand-name">{{ setting('brand_short_name') }}</span>
          <span class="cp-brand-sub">Citizen Portal</span>
        </span>
      </a>
      <p class="cp-brand-gov">{{ setting('brand_government') }}</p>
    </div>

    <nav class="cp-menu" aria-label="Portal pages">
      @foreach ($portalSections as $section => $items)
        <p class="cp-menu-section">{{ $section }}</p>
        <ul>
          @foreach ($items as $key => [$label, $sub, $href, $paths])
            @php $active = $portalNav === $key; @endphp
            <li>
              <a href="{{ app_href($href) }}" class="cp-menu-link{{ $active ? ' is-active' : '' }}" @if ($active) aria-current="page" @endif>
                <span class="cp-menu-icon">{!! $icon($paths) !!}</span>
                <span class="cp-menu-text">
                  <span class="cp-menu-label">{{ $label }}</span>
                  <span class="cp-menu-sub">{{ $sub }}</span>
                </span>
                @if ($key === 'verify' && $portalStatus !== 'Approved')<span class="cp-menu-dot" aria-label="Needs attention"></span>@endif
              </a>
            </li>
          @endforeach
        </ul>
      @endforeach

      <p class="cp-menu-section">Public site</p>
      <ul>
        <li>
          <a href="{{ app_href('citizen/track') }}" class="cp-menu-link">
            <span class="cp-menu-icon">{!! $icon('<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>') !!}</span>
            <span class="cp-menu-text"><span class="cp-menu-label">Track by Reference</span><span class="cp-menu-sub">No sign-in needed</span></span>
          </a>
        </li>
        <li>
          <a href="{{ app_href('citizen') }}" class="cp-menu-link">
            <span class="cp-menu-icon">{!! $icon('<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>') !!}</span>
            <span class="cp-menu-text"><span class="cp-menu-label">Portal Home</span><span class="cp-menu-sub">Back to the website</span></span>
          </a>
        </li>
      </ul>
    </nav>

    <div class="cp-usercard">
      <a href="{{ app_href('citizen/profile') }}" class="cp-usercard-link{{ $portalNav === 'profile' ? ' is-active' : '' }}" title="Profile">
        <span class="cp-usercard-avatar" aria-hidden="true">{{ $portalInitials ?: '?' }}</span>
        <span class="min-w-0">
          <span class="cp-usercard-name">{{ $portalCitizen['full_name'] ?? '' }}</span>
          <span class="cp-usercard-sub">{{ $portalCitizen['email'] ?? '' }}</span>
        </span>
      </a>
      <form method="POST" action="{{ app_href('citizen/logout') }}">
        @csrf
        <button type="submit" class="cp-usercard-out" title="Sign out" aria-label="Sign out">
          {!! $icon('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>') !!}
        </button>
      </form>
    </div>
  </aside>
  <div id="cp-backdrop" class="cp-backdrop" hidden></div>

  <div class="cp-body-col">
    <header class="cp-topbar">
      <div class="cp-topbar-left">
        <button id="cp-sidebar-toggle" type="button" class="cp-topbar-menu" aria-label="Open menu" aria-controls="cp-sidebar" aria-expanded="false">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <h1 class="cp-topbar-title">{{ $pageTitle ?? 'My Portal' }}</h1>
      </div>
      <div class="cp-topbar-right">
        <span class="cp-topbar-date">{{ date('l, F j, Y') }}</span>
        @if ($portalStatus === 'Approved')
          <span class="cp-topbar-status cp-tag-ok" title="Your ID is verified">
            {!! $icon('<polyline points="20 6 9 17 4 12"/>') !!} Verified
          </span>
          @if ($portalNav !== 'submit')
            <a href="{{ app_href('citizen/submit') }}" class="cp-btn cp-btn-primary cp-btn-sm">
              {!! $icon('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>') !!}
              <span class="cp-hide-xs">New submission</span>
            </a>
          @endif
        @else
          <a href="{{ app_href('citizen/verify') }}" class="cp-topbar-status {{ $portalStatus === 'Pending' ? 'cp-tag-wait' : 'cp-tag-todo' }}">
            {{ $portalStatus === 'Pending' ? 'ID under review' : 'Verify your ID' }}
          </a>
        @endif
      </div>
    </header>

    <main id="cp-content" class="cp-content">
      @foreach (flash_get() as $f)
        <div class="ca-flash {{ $f['type'] === 'warning' ? 'ca-flash-warning' : 'ca-flash-success' }}" role="status">{{ $f['message'] }}</div>
      @endforeach
      @yield('content')
    </main>

    {{-- Phones/tablets: the main pages within thumb reach; ☰ still opens the full menu. --}}
    <nav class="cp-tabbar" aria-label="Quick navigation">
      @php
        $tabs = [
            'dashboard'   => ['Home', 'citizen/dashboard', '<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>'],
            'submissions' => ['My Reports', 'citizen/my-submissions', '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'],
            'submit'      => ['Submit', $portalStatus === 'Approved' ? 'citizen/submit' : 'citizen/verify', '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
            'verify'      => ['ID', 'citizen/verify', '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2.5"/><line x1="13" y1="10" x2="18" y2="10"/>'],
            'profile'     => ['Profile', 'citizen/profile', '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/>'],
        ];
      @endphp
      @foreach ($tabs as $key => [$label, $href, $paths])
        @php $active = $portalNav === $key; @endphp
        <a href="{{ app_href($href) }}" class="cp-tabbar-link{{ $key === 'submit' ? ' is-primary' : '' }}{{ $active ? ' is-active' : '' }}" @if ($active) aria-current="page" @endif>
          <span class="cp-tabbar-icon">
            {!! $icon($paths) !!}
            @if ($key === 'verify' && $portalStatus !== 'Approved')<span class="cp-tabbar-dot" aria-hidden="true"></span>@endif
          </span>
          <span class="cp-tabbar-label">{{ $label }}</span>
        </a>
      @endforeach
    </nav>

    <footer class="cp-foot">
      © {{ date('Y') }} {{ setting('brand_government') }} — {{ setting('brand_office') }} ·
      <a href="{{ app_href('citizen/privacy') }}">Privacy Policy</a> ·
      <a href="{{ app_href('citizen/terms') }}">Terms</a>
    </footer>
  </div>
</div>

<script src="{{ asset_v('frontend/js/app.js') }}"></script>
<script>
(() => {
  // Phones/tablets: the side menu slides in over the page.
  const sidebar = document.getElementById('cp-sidebar');
  const toggle = document.getElementById('cp-sidebar-toggle');
  const backdrop = document.getElementById('cp-backdrop');
  const setOpen = (open) => {
    sidebar.classList.toggle('is-open', open);
    backdrop.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
  };
  toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('is-open')));
  backdrop.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && sidebar.classList.contains('is-open')) { setOpen(false); toggle.focus(); } });
  window.addEventListener('resize', () => { if (window.innerWidth >= 1024) setOpen(false); }, { passive: true });
})();
</script>
@stack('scripts')
</body>
</html>
