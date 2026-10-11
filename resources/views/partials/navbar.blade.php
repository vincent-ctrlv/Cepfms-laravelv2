@php
  // Which citizen page is open, for the active nav link.
  $current = ['citizen' => 'index', 'citizen/submit' => 'submit', 'citizen/track' => 'track'][request()->path()]
    ?? (request()->is('citizen/dashboard', 'citizen/my-submissions', 'citizen/submissions/*', 'citizen/profile', 'citizen/verify') ? 'portal' : '');
  $navCitizen = \App\Support\CitizenAuth::current();
@endphp
<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:bg-white focus:text-navy focus:px-3 focus:py-2 focus:rounded focus:text-xs focus:font-semibold">Skip to main content</a>

<nav class="c-navbar" id="c-navbar" aria-label="Main navigation">
  <div class="c-navbar-inner">

    <!-- Brand -->
    <a href="{{ app_href('citizen') }}" class="c-brand" aria-label="{{ setting('brand_short_name') }} home">
      <div class="c-brand-seal">
        <img src="{{ brand_logo_url() }}" alt="{{ setting('brand_government') }} logo">
      </div>
      <div class="c-brand-text">
        <span class="c-brand-sub">{{ setting('brand_government') }}</span>
        <span class="c-brand-name">{{ setting('brand_short_name') }}</span>
      </div>
    </a>

    <!-- Desktop nav -->
    <ul class="c-nav-links" role="list">
      <li><a href="{{ app_href('citizen') }}"  class="{!! $current === 'index'  ? 'c-nav-active' : '' !!}">Home</a></li>
      <li><a href="{{ app_href('citizen/submit') }}" class="{!! $current === 'submit' ? 'c-nav-active' : '' !!}">Submit Feedback</a></li>
      <li><a href="{{ app_href('citizen/track') }}"  class="{!! $current === 'track'  ? 'c-nav-active' : '' !!}">Track Submission</a></li>
      @if ($navCitizen)
      <li><a href="{{ app_href('citizen/dashboard') }}" class="{!! $current === 'portal' ? 'c-nav-active' : '' !!}">My Portal</a></li>
      @endif
    </ul>

    <!-- Citizen sign-in / sign-out + hamburger -->
    <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
      @if ($navCitizen)
      <form method="POST" action="{{ app_href('citizen/logout') }}" class="c-nav-account">
        @csrf
        <span class="c-nav-account-name" title="{{ $navCitizen['email'] }}">{{ $navCitizen['full_name'] }}</span>
        <button type="submit" class="c-btn-login" id="navbar-logout-btn">Sign out</button>
      </form>
      @else
      <a href="{{ app_href('citizen/login') }}" class="c-btn-login" id="navbar-login-btn">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Sign in
      </a>
      @endif
      <button class="c-nav-toggle" id="c-nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="c-mobile-nav">
        <svg id="c-menu-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="3" y1="7" x2="21" y2="7"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="17" x2="21" y2="17"/>
        </svg>
        <svg id="c-close-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:none;">
          <line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Mobile drawer -->
  <div id="c-mobile-nav" class="c-mobile-nav" role="navigation" aria-label="Mobile navigation">
    <a href="{{ app_href('citizen') }}"  class="{!! $current === 'index'  ? 'c-nav-active' : '' !!}">Home</a>
    <a href="{{ app_href('citizen/submit') }}" class="{!! $current === 'submit' ? 'c-nav-active' : '' !!}">Submit Feedback</a>
    <a href="{{ app_href('citizen/track') }}"  class="{!! $current === 'track'  ? 'c-nav-active' : '' !!}">Track Submission</a>
    @if ($navCitizen)
    <a href="{{ app_href('citizen/dashboard') }}" class="{!! $current === 'portal' ? 'c-nav-active' : '' !!}">My Portal</a>
    <form method="POST" action="{{ app_href('citizen/logout') }}">
      @csrf
      <button type="submit" class="c-mobile-login c-mobile-logout">Sign out ({{ $navCitizen['full_name'] }})</button>
    </form>
    @else
    <a href="{{ app_href('citizen/login') }}" class="c-mobile-login">Sign in with Google</a>
    @endif
  </div>
</nav>

<script>
(function(){
  var nav = document.getElementById('c-navbar');
  var btn = document.getElementById('c-nav-toggle');
  var mob = document.getElementById('c-mobile-nav');
  // Scroll shadow
  window.addEventListener('scroll', function(){
    nav.classList.toggle('scrolled', window.scrollY > 20);
  }, {passive: true});
  var menuIcon = document.getElementById('c-menu-icon');
  var closeIcon = document.getElementById('c-close-icon');
  function setOpen(open){
    mob.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open);
    menuIcon.style.display = open ? 'none' : '';
    closeIcon.style.display = open ? '' : 'none';
  }
  // Mobile toggle
  btn.addEventListener('click', function(){
    setOpen(!mob.classList.contains('open'));
  });
  // Desktop nav reappears at >768px, so an open drawer would duplicate it
  window.addEventListener('resize', function(){
    if (window.innerWidth > 768) setOpen(false);
  }, {passive: true});
  // Close on a tap outside the panel, or Escape
  document.addEventListener('click', function(e){
    if (mob.classList.contains('open') && !mob.contains(e.target) && !btn.contains(e.target)) setOpen(false);
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && mob.classList.contains('open')) { setOpen(false); btn.focus(); }
  });
  // Close mobile drawer when clicking anchor links
  mob.querySelectorAll('a').forEach(function(a){
    a.addEventListener('click', function(){ setOpen(false); });
  });
})();
</script>
