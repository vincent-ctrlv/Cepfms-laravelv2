{{-- Admin sidebar. Expects $staff (current_staff()) to be available, and
     $activeNav set to one of: dashboard, submissions, proposals, complaints,
     moderation, verifications, responses, analytics, staff, audit, settings, or profile (the user card) --}}
@php
$activeNav = $activeNav ?? 'dashboard';

$navItems = [
    'dashboard'   => ['label' => 'Dashboard', 'sub' => 'Overview & activity feed', 'href' => 'dashboard'],
    'submissions' => ['label' => 'Feedback Submission', 'sub' => 'Submit & track public feedback', 'href' => 'submissions'],
    'proposals'   => ['label' => 'Proposals & Suggestions', 'sub' => 'Citizen proposals management', 'href' => 'proposals'],
    'complaints'  => ['label' => 'Complaints & Issues', 'sub' => 'Complaint and issue tracking', 'href' => 'complaints'],
    'moderation'  => ['label' => 'Moderation & Validation', 'sub' => 'Review and validate content', 'href' => 'moderation', 'lockedForStaff' => true],
    'verifications' => ['label' => 'ID Verification', 'sub' => 'Check citizen IDs', 'href' => 'verifications', 'adminOnly' => true],
    'responses'   => ['label' => 'Response Management', 'sub' => 'Draft and send responses', 'href' => 'responses'],
    'analytics'   => ['label' => 'Engagement Analytics', 'sub' => 'Reports and insights', 'href' => 'analytics'],
    'staff'       => ['label' => 'User Accounts', 'sub' => 'Create & manage logins', 'href' => 'staff', 'superAdminOnly' => true],
    'audit'       => ['label' => 'Audit Log', 'sub' => 'Sign-ins & security events', 'href' => 'audit-log', 'superAdminOnly' => true],
    'settings'    => ['label' => 'System Settings', 'sub' => 'Branding & defaults', 'href' => 'settings', 'superAdminOnly' => true],
];

// Sidebar grouping. Every key in $navItems must appear in exactly one section.
$navSections = [
    'Overview'       => ['dashboard'],
    'Citizen Input'  => ['submissions', 'proposals', 'complaints'],
    'Workflow'       => ['moderation', 'verifications', 'responses'],
    'Insights'       => ['analytics'],
    'Administration' => ['staff', 'audit', 'settings'],
];

$icons = [
    'dashboard'   => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
    'submissions' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
    'proposals'   => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    'complaints'  => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    'moderation'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    'verifications' => '<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="12" r="2.5"/><line x1="13" y1="10" x2="18" y2="10"/><line x1="13" y1="14" x2="17" y2="14"/>',
    'responses'   => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>',
    'analytics'   => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>',
    'staff'       => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
    'audit'       => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>',
    'settings'    => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
];

$initials = '';
if (!empty($staff['full_name'])) {
    $parts = explode(' ', trim($staff['full_name']));
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
}
@endphp
<aside id="admin-sidebar" class="bg-navy flex flex-col h-screen w-64 min-w-[256px] flex-shrink-0 z-50">
  <div class="px-5 pt-6 pb-5 border-b" style="border-color: rgba(255,255,255,0.08)">
    <div class="flex items-center gap-2.5 mb-3">
      <div class="flex items-center justify-center rounded-full flex-shrink-0 overflow-hidden bg-white" style="width:40px;height:40px">
        <img src="{{ brand_logo_url() }}" alt="{{ setting('brand_government') }} logo" class="w-full h-full object-contain">
      </div>
      <div>
        <p class="text-sm font-semibold leading-none mb-1 text-white">{{ setting('brand_short_name') }}</p>
        <p class="text-[10px] leading-snug font-semibold tracking-wide uppercase" style="color:rgba(255,255,255,0.7)">{{ setting('brand_office') }}</p>
      </div>
    </div>
    <p class="text-[11px] font-medium leading-snug" style="color:#93c5fd">{{ setting('brand_full_name') }}</p>
  </div>

  <nav class="flex-1 px-3 py-4 overflow-y-auto" aria-label="Admin modules">
    @php $isAdminUser = role_is_admin($staff['role'] ?? null); @endphp
    @foreach ($navSections as $sectionLabel => $sectionKeys)
    @php
      // Drop items this user can't see at all; skip the heading if none remain.
      $visibleKeys = array_values(array_filter($sectionKeys, function ($key) use ($navItems, $staff, $isAdminUser) {
          $item = $navItems[$key];
          if (!empty($item['adminOnly']) && !$isAdminUser) return false;
          if (!empty($item['superAdminOnly']) && ($staff['role'] ?? null) !== 'super_admin') return false;
          return true;
      }));
    @endphp
    @continue(!$visibleKeys)
    <p class="admin-nav-section">{{ $sectionLabel }}</p>
    <ul class="space-y-0.5">
      @foreach ($visibleKeys as $key)
      @php
        $item = $navItems[$key];
        $isActive = $key === $activeNav;
      @endphp
      @if (!empty($item['lockedForStaff']) && !$isAdminUser)
      {{-- Admin-only module that non-admins still see, shown with a lock and not clickable. --}}
      <li>
        <div class="admin-nav-link admin-nav-locked" title="Admins only" aria-disabled="true">
          <span class="admin-nav-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$key] !!}</svg>
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-xs font-semibold leading-tight">{{ $item['label'] }}</span>
            <span class="admin-nav-sub block text-[10px] mt-0.5 leading-tight">Admins only</span>
          </span>
          <span class="admin-nav-icon flex-shrink-0" aria-label="Locked">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          </span>
        </div>
      </li>
      @else
      <li>
        <a href="{{ app_href('admin/' . $item['href']) }}"
           class="admin-nav-link{{ $isActive ? ' is-active' : '' }}"{!! $isActive ? ' aria-current="page"' : '' !!}>
          <span class="admin-nav-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$key] !!}</svg>
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-xs font-semibold leading-tight">{{ $item['label'] }}</span>
            <span class="admin-nav-sub block text-[10px] mt-0.5 leading-tight">{{ $item['sub'] }}</span>
          </span>
        </a>
      </li>
      @endif
      @endforeach
    </ul>
    @endforeach
  </nav>

  <div class="px-4 py-4 border-t" style="border-color: rgba(255,255,255,0.08)">
    <div class="flex items-center gap-1.5">
      <!-- The user card opens Profile Settings. -->
      <a href="{{ app_href('admin/profile') }}" title="Profile settings"
         class="admin-user-card flex items-center gap-3 min-w-0 flex-1 rounded-lg p-1 -m-1{!! $activeNav === 'profile' ? ' is-active' : '' !!}"{!! $activeNav === 'profile' ? ' aria-current="page"' : '' !!}>
        <span id="sidebar-user-initials" class="rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="width:32px;height:32px;background:#2563eb;color:white">
          {{ $initials ?: 'ST' }}
        </span>
        <span class="min-w-0 flex-1">
          <span id="sidebar-user-name" class="block text-xs font-semibold truncate" style="color:rgba(255,255,255,0.9)">{{ $staff['full_name'] ?? 'Staff' }}</span>
          <span id="sidebar-user-sub" class="block text-[10px] truncate" style="color:rgba(255,255,255,0.35)">{{ ($staff['title'] ?? '') ?: role_label($staff['role'] ?? null) }}</span>
        </span>
        <svg class="admin-user-gear flex-shrink-0" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      </a>
      <a href="{{ app_href('auth/logout') }}" title="Log out" aria-label="Log out" class="admin-user-logout flex-shrink-0 p-1.5 rounded-lg">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>
  </div>
</aside>
<div id="sidebar-backdrop" class="fixed inset-0 bg-black/40 z-40 hidden"></div>
