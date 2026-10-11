{{-- VIEW: Audit Log
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle, $events,
     $scope, $page, $perPage, $total, $totalPages --}}
@include('partials.admin-header')
<?php
/** Readable description and badge for one security event. */
$describe = function (array $ev): array {
    $meta = json_decode((string) ($ev['metadata'] ?? ''), true) ?: [];
    $action = $ev['action'];
    $target = $ev['target_name'] ?: ($meta['email'] ?? 'unknown');
    if (str_ends_with($action, 'logged in'))  return [isset($meta['two_factor']) && !$meta['two_factor'] ? 'Logged in (password only, two-factor off)' : 'Logged in', 'badge-green'];
    if ($action === 'Two-factor turned on')  return ['Turned on two-factor sign-in', 'badge-green'];
    if ($action === 'Two-factor turned off') return ['Turned off two-factor sign-in', 'badge-amber'];
    if (str_ends_with($action, 'logged out')) return ['Logged out', 'badge-slate'];
    if ($action === 'Staff account created') {
        return ['Created account for ' . $target . (isset($meta['role']) ? ' (' . role_label($meta['role']) . ')' : '')
            . (isset($meta['emailed']) ? ($meta['emailed'] ? ' · login details emailed' : ' · email not sent') : ''), 'badge-blue'];
    }
    if ($action === 'Staff login details resent') {
        return ['Sent new login details to ' . $target . (empty($meta['emailed']) ? ' · email not sent' : ''), 'badge-amber'];
    }
    if ($action === 'Staff account deactivated') return ['Deactivated ' . $target, 'badge-red'];
    if ($action === 'Staff account reactivated') return ['Reactivated ' . $target, 'badge-green'];
    if ($action === 'Staff account deleted') return ['Deleted ' . ($meta['name'] ?? $target) . (isset($meta['email']) ? ' (' . $meta['email'] . ')' : ''), 'badge-red'];
    if ($action === 'Staff account updated') {
        $labels = ['full_name' => 'name', 'email' => 'email', 'title' => 'position'];
        $parts = [];
        foreach ($labels as $key => $label) {
            if (isset($meta[$key]['to'])) {
                $parts[] = $label . ': ' . (($meta[$key]['from'] ?? '') ?: 'none') . ' → ' . ($meta[$key]['to'] ?: 'none');
            }
        }
        return ['Edited ' . $target . ($parts ? ' (' . implode('; ', $parts) . ')' : ''), 'badge-blue'];
    }
    if ($action === 'Branding updated') {
        $labels = [
            'brand_short_name' => 'short name', 'brand_full_name' => 'full name', 'brand_government' => 'government name',
            'brand_office' => 'office name', 'brand_logo' => 'logo', 'contact_address' => 'address', 'contact_phone' => 'phone',
            'contact_email' => 'email', 'contact_hours' => 'office hours', 'email_signoff' => 'reply sign-off',
        ];
        $fields = array_values(array_intersect_key($labels, array_flip($meta['changed'] ?? [])));
        return ['Updated branding' . ($fields ? ' (' . implode(', ', $fields) . ')' : ''), 'badge-blue'];
    }
    if ($action === 'Profile updated') {
        $fields = array_intersect_key(['full_name' => 'name', 'title' => 'position'], $meta);
        return ['Updated own profile' . ($fields ? ' (' . implode(', ', $fields) . ')' : ''), 'badge-slate'];
    }
    if ($action === 'export_csv') return ['Exported submissions to CSV', 'badge-amber'];
    if ($action === 'Analytics exported') return ['Exported analytics to a password-protected PDF', 'badge-amber'];
    if ($action === 'Analytics printed') return ['Opened analytics as a password-protected PDF to print', 'badge-amber'];
    if ($action === 'Dashboard exported') return ['Exported the dashboard to a password-protected PDF', 'badge-amber'];
    if ($action === 'Dashboard printed') return ['Opened the dashboard as a password-protected PDF to print', 'badge-amber'];
    if (str_starts_with($action, 'Password')) return [$action, 'badge-amber'];
    return [$action, 'badge-slate'];
};

// Work events reuse the dashboard feed's wording and links. id 0 = never
// "You …": the Account column already says who did it.
$feedViewer = ['id' => 0, 'role' => $staff['role']];
$toneDot = ['new' => 'bg-teal', 'good' => 'bg-emerald', 'bad' => 'bg-dangerred', 'neutral' => 'bg-slate/50']; // same as dashboard

$scopeNote = [
    'security' => 'Sign-ins, password changes, account changes, system settings and data exports.',
    'work'     => 'Everything staff did on submissions, moderation and responses, plus new citizen submissions.',
    'all'      => 'Security events and work activity together.',
][$scope];
$scopeLink = fn (string $s, int $p = 1) => e(app_href('admin/audit-log') . '?' . http_build_query(
    array_filter(['scope' => $s === 'security' ? null : $s, 'page' => $p > 1 ? $p : null])
));
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="text-xl font-semibold text-navy">Audit Log</h1>
    <p class="text-sm text-slate mt-1">{{ $scopeNote }} Only super admins can see this page.</p>
  </div>
  <nav class="inline-flex rounded border border-borderc bg-white p-0.5 text-xs font-medium" aria-label="Filter events">
    @foreach (ACTIVITY_AUDIT_SCOPES as $key => $label)
      <a href="{!! $scopeLink($key) !!}" {!! $key === $scope ? 'aria-current="page"' : '' !!}
         class="px-3 py-1.5 rounded {!! $key === $scope ? 'bg-navy text-white' : 'text-slate hover:text-navy hover:bg-pagebg' !!}">{{ $label }}</a>
    @endforeach
  </nav>
</div>

<div class="card overflow-hidden">
  @if (!$events)
    <p class="px-4 py-12 text-center text-sm text-slate">No events logged yet.</p>
  @else
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-pagebg text-[11px] uppercase tracking-wide text-slate">
        <tr>
          <th class="text-left px-4 py-3 font-medium">When</th>
          <th class="text-left px-4 py-3 font-medium">Account</th>
          <th class="text-left px-4 py-3 font-medium">Event</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-borderc">
        @foreach ($events as $ev)
        <tr>
          <td class="px-4 py-3 text-slate whitespace-nowrap" title="{{ time_ago($ev['created_at']) }}">{{ date('M j, Y g:i A', strtotime($ev['created_at'])) }}</td>
          <td class="px-4 py-3">
            @if ($ev['staff_name'])
              <p class="font-medium text-navy">{{ $ev['staff_name'] }}</p>
              <p class="text-xs text-slate">{{ $ev['staff_email'] }} &middot; {{ role_label($ev['staff_role']) }}</p>
            @elseif ($ev['staff_id'] === null)
              <p class="text-slate">{!! $ev['action'] === 'Submission created' ? 'Citizen portal' : 'System' !!}</p>
            @else
              <p class="text-slate">Deleted account</p>
            @endif
          </td>
          <td class="px-4 py-3">
            @if (activity_is_security($ev)) @php [$label, $badge] = $describe($ev); @endphp
              <span class="badge {{ $badge }}">{{ $label }}</span>
            @else @php $item = activity_feed_item($ev, $feedViewer); @endphp
              <span class="inline-flex items-start gap-2">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $toneDot[$item['tone']] ?? $toneDot['neutral'] }}" aria-hidden="true"></span>
                @if ($item['href'])
                  <a href="{{ app_href('admin/' . $item['href']) }}" class="text-navy hover:underline">{{ $item['text'] }}</a>
                @else
                  <span class="text-navy">{{ $item['text'] }}</span>
                @endif
              </span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <?php
    // Same look as CivicConnect.renderPagination, but plain links: this page is server-rendered.
    $start = ($page - 1) * $perPage + 1;
    $end = $start + count($events) - 1;
    $pageLink = fn (int $p) => $scopeLink($scope, $p);
    $btnBase = 'flex items-center justify-center w-7 h-7 rounded border border-borderc transition-all';
    $btnOn = $btnBase . ' bg-white hover:bg-pagebg text-navy hover:border-slate-300';
    $btnOff = $btnBase . ' opacity-35 cursor-not-allowed bg-white text-slate-400';
  
?>
  <div class="flex items-center justify-between px-4 py-2.5 border-t border-borderc text-xs text-slate bg-white">
    <span>Showing {!! $start !!}&ndash;{!! $end !!} of {!! $total !!}</span>
    <div class="flex items-center gap-1.5">
      @if ($page > 1)
        <a href="{!! $pageLink($page - 1) !!}" class="{!! $btnOn !!}" title="Previous page">
      @else
        <span class="{!! $btnOff !!}" aria-disabled="true">
      @endif
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      {!! $page > 1 ? '</a>' : '</span>' !!}
      <span class="font-semibold text-navy text-xs px-1.5">{!! $page !!} / {!! $totalPages !!}</span>
      @if ($page < $totalPages)
        <a href="{!! $pageLink($page + 1) !!}" class="{!! $btnOn !!}" title="Next page">
      @else
        <span class="{!! $btnOff !!}" aria-disabled="true">
      @endif
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      {!! $page < $totalPages ? '</a>' : '</span>' !!}
    </div>
  </div>
  @endif
</div>

@include('partials.admin-footer')
