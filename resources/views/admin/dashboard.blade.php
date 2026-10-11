{{-- VIEW: Admin Dashboard
     Expects (set by Admin\PageController):
       $kpis, $feedbackCount, $complaintCount, $proposalCount, $donutTotal,
       $weekDays, $weekTotal, $lastWeekTotal,
       $needsAttention, $needsAttentionCount, $recentActivity,
       $staff, $activeNav, $pageTitle --}}
@include('partials.admin-header')
<?php
// Prepare SVG donut chart math
$fbPct = round(($feedbackCount / $donutTotal) * 100);
$cmPct = round(($complaintCount / $donutTotal) * 100);
$prPct = 100 - $fbPct - $cmPct;
if ($donutTotal === 1 && ($feedbackCount + $complaintCount + $proposalCount === 0)) {
    $fbPct = 0; $cmPct = 0; $prPct = 0;
}

// Circumference for r=15.9155 is ~100
$c = 100;
$fbDash = ($fbPct / 100) * $c;
$cmDash = ($cmPct / 100) * $c;
$prDash = ($prPct / 100) * $c;

$fbOffset = 25;
$cmOffset = $fbOffset - $fbDash;
$prOffset = $cmOffset - $cmDash;
?>

<!-- Everything inside #dashboard-root goes into the PDF, one top-level block per section. -->
<div id="dashboard-root">
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
  <p class="text-xs text-slate">As of {{ date('M j, Y g:i A') }}</p>
  <button id="dashboard-export-btn" type="button" title="Downloads a PDF that opens with a password sent to your email"
    class="no-print inline-flex items-center gap-1.5 text-xs font-semibold text-slate bg-white border border-borderc rounded-md px-3.5 py-2 hover:bg-pagebg transition disabled:opacity-50 disabled:cursor-not-allowed">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
    </svg>
    Export PDF
  </button>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
  @foreach ($kpis as $k) @php $trend = $k['trend'] ?? 0;
    $isGood = $k['trend_positive_is_good'] ? ($trend >= 0) : ($trend <= 0);
    $trendColor = $trend === 0 ? '#64748b' : ($isGood ? '#059669' : '#dc2626');
    $trendArrow = $trend > 0 ? '▲' : ($trend < 0 ? '▼' : '•'); @endphp
  <a href="{{ $k['href'] }}" class="card p-4 kpi-card-link block hover:shadow-md transition-all group relative overflow-hidden" data-kpi-idx="{{ $k['key'] }}">
    <div class="flex items-center justify-between gap-2 mb-1.5">
      <p class="text-[11px] font-medium text-slate truncate group-hover:text-navy transition-colors">{{ $k['label'] }}</p>
      <div class="w-6 h-6 rounded flex items-center justify-center bg-pagebg text-slate group-hover:bg-blue-50 group-hover:text-primary transition-colors flex-shrink-0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          {!! $k['icon'] !!}
        </svg>
      </div>
    </div>
    <p class="text-2xl font-bold text-navy kpi-value" data-kpi-key="{{ $k['key'] }}">{{ (string)$k['value'] }}</p>
    <div class="kpi-trend-wrap mt-1.5 flex items-center gap-1 text-[11px] font-medium" style="color: {!! $trendColor !!}">
      @if ($trend !== 0)
        <span>{!! $trendArrow !!} {!! abs($trend) !!}%</span>
        <span class="text-[10px] text-slate/70 font-normal">vs last wk</span>
      @else
        <span class="text-[10px] text-slate font-normal">No change vs last wk</span>
      @endif
    </div>
  </a>
  @endforeach
</div>

<!-- Main Operational Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

  <!-- Submissions This Week -->
  <?php
    // Hex colors match the donut; the theme overrides Tailwind's slate/emerald
    // scales with single colors, so classes like bg-slate-500 don't exist.
    $weekSeries = [
      'feedback'  => ['label' => 'Feedback',   'color' => '#64748b'],
      'complaint' => ['label' => 'Complaints', 'color' => '#dc2626'],
      'proposal'  => ['label' => 'Proposals',  'color' => '#059669'],
    ];
    $dayTotals = array_map(fn($d) => $d['feedback'] + $d['complaint'] + $d['proposal'], $weekDays);
    // Round the scale up so the 4 guide lines land on whole numbers.
    $weekStep = max(1, (int) ceil(max($dayTotals) / 4));
    $weekTop  = $weekStep * 4;
    $weekDiff = $weekTotal - $lastWeekTotal;
    $busiest  = $weekTotal > 0 ? array_search(max($dayTotals), $dayTotals, true) : null;
  
?>
  <div class="card p-5 flex flex-col justify-between">
    <div>
      <div class="flex items-start justify-between gap-3 mb-5">
        <div>
          <h2 class="text-sm font-semibold text-navy">Submissions This Week</h2>
          <p class="text-xs text-slate mt-0.5">
            @if ($busiest !== null)
              Avg {!! round($weekTotal / 7, 1) !!}/day &middot; Busiest: {!! $busiest === 6 ? 'today' : date('l', strtotime($weekDays[$busiest]['day'])) !!} ({!! $dayTotals[$busiest] !!})
            @else
              Received per day, last 7 days
            @endif
          </p>
        </div>
        <div class="text-right flex-shrink-0">
          <p class="text-2xl font-bold text-navy leading-none">{!! $weekTotal !!}</p>
          <p class="text-[11px] text-slate mt-1">
            @if ($weekDiff !== 0)
              <span class="font-semibold text-navy">{!! $weekDiff > 0 ? '+' : '−' !!}{!! abs($weekDiff) !!}</span> vs last week ({!! $lastWeekTotal !!})
            @else
              Same as last week
            @endif
          </p>
        </div>
      </div>

      @if ($weekTotal === 0)
        <div class="h-44 flex flex-col items-center justify-center text-center border border-dashed border-borderc rounded-lg">
          <p class="text-xs font-semibold text-navy">No submissions in the last 7 days</p>
          <p class="text-[11px] text-slate mt-0.5">New reports will show up here as they arrive.</p>
        </div>
      @else
      <div class="flex gap-2">
        <!-- Y axis -->
        <div class="w-5 h-44 pt-5 flex-shrink-0 text-[9px] text-slate/70 text-right" aria-hidden="true">
          <div class="relative h-full">
            @for ($g = 4; $g >= 0; $g--)
              <span class="absolute right-0 -translate-y-1/2" style="top: {!! (4 - $g) * 25 !!}%">{!! $g * $weekStep !!}</span>
            @endfor
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <!-- Plot; pt-5 leaves room for the tallest bar's label -->
          <div class="relative h-44 pt-5" id="week-chart">
            <div class="absolute inset-x-0 top-5 bottom-0 flex flex-col justify-between pointer-events-none" aria-hidden="true">
              @for ($g = 0; $g < 4; $g++)<div class="border-t border-dashed border-borderc"></div>@endfor
              <div class="border-t border-borderc"></div>
            </div>
            <div class="relative h-full flex items-end gap-1.5">
              @foreach ($weekDays as $i => $d) @php $dayTotal = $dayTotals[$i];
                $dayTitle = ($i === 6 ? 'Today' : date('l', strtotime($d['day']))) . ', ' . date('M j', strtotime($d['day'])); @endphp
              <div class="week-col flex-1 h-full flex items-end justify-center rounded-t outline-none focus-visible:ring-2 focus-visible:ring-teal/30 {!! $i === 6 ? 'bg-blue-50/60' : 'hover:bg-pagebg' !!} transition-colors"
                   tabindex="0"
                   aria-label="{{ "$dayTitle: $dayTotal submission" . ($dayTotal === 1 ? '' : 's') }}"
                   data-title="{{ $dayTitle }}"
                   data-total="{!! $dayTotal !!}"
                   @foreach ($weekSeries as $k => $sr)data-{!! $k !!}="{!! $d[$k] !!}" @endforeach>
                @if ($dayTotal > 0)
                  <div class="week-bar relative w-3/5 max-w-[34px] flex flex-col-reverse origin-bottom" style="height: {!! round($dayTotal / $weekTop * 100, 1) !!}%; min-height: 4px;">
                    <span class="absolute -top-4 inset-x-0 text-center text-[10px] font-semibold text-navy">{!! $dayTotal !!}</span>
                    @foreach ($weekSeries as $k => $sr) @php if ($d[$k] === 0) continue; @endphp
                      <div style="height: {!! round($d[$k] / $dayTotal * 100, 1) !!}%; background: {!! $sr['color'] !!};" class="border-white/80 [&:not(:last-child)]:border-t last:rounded-t"></div>
                    @endforeach
                  </div>
                @endif
              </div>
              @endforeach
            </div>

            <!-- Hover tooltip (filled in by the script at the bottom of the page) -->
            <div id="week-tip" class="hidden absolute top-0 z-10 pointer-events-none bg-navy text-white rounded-md shadow-lg px-3 py-2 text-[11px] min-w-[140px]" role="tooltip"></div>
          </div>
          <div class="flex gap-1.5 mt-2">
            @foreach ($weekDays as $i => $d)
              <span class="flex-1 text-center text-[10px] leading-tight {!! $i === 6 ? 'text-navy font-semibold' : 'text-slate' !!}">
                {!! $i === 6 ? 'Today' : date('D', strtotime($d['day'])) !!}
                <span class="block text-[9px] font-normal text-slate/70">{!! date('M j', strtotime($d['day'])) !!}</span>
              </span>
            @endforeach
          </div>
        </div>
      </div>
      @endif
    </div>

    <div class="border-t border-borderc pt-3 mt-4 flex items-center justify-between gap-3 flex-wrap text-[11px] text-slate">
      <span class="flex items-center gap-3 flex-wrap">
        @foreach ($weekSeries as $k => $sr)
          <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-sm" style="background: {!! $sr['color'] !!}"></span>
            {!! $sr['label'] !!} <span class="font-semibold text-navy">{!! array_sum(array_column($weekDays, $k)) !!}</span>
          </span>
        @endforeach
      </span>
      <a href="{{ app_href('admin/submissions') }}" class="text-teal hover:underline font-medium">All Submissions &rarr;</a>
    </div>
  </div>

  <!-- Submission Type Breakdown Donut -->
  <div class="card p-5 flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-sm font-semibold text-navy">Submission Type Breakdown</h2>
          <p class="text-xs text-slate mt-0.5">Moderation-approved engagement split</p>
        </div>
        <a href="{{ app_href('admin/analytics') }}" class="text-[11px] text-teal hover:underline font-medium">Full Analytics &rarr;</a>
      </div>
      
      <div class="flex items-center justify-center gap-8 py-4">
        <!-- Inline SVG Donut -->
        <div class="relative w-32 h-32 flex-shrink-0 flex items-center justify-center">
          <svg viewBox="0 0 42 42" class="w-full h-full transform -rotate-90">
            <circle cx="21" cy="21" r="15.9155" fill="transparent" stroke="#f1f5f9" stroke-width="5"></circle>
            @if ($donutTotal > 0 && ($feedbackCount + $complaintCount + $proposalCount > 0))
              <!-- Feedback: Slate/Blue -->
              <circle cx="21" cy="21" r="15.9155" fill="transparent" stroke="#64748b" stroke-width="5" stroke-dasharray="{!! $fbDash !!} {!! $c - $fbDash !!}" stroke-dashoffset="{!! $fbOffset !!}"></circle>
              <!-- Complaints: Red -->
              <circle cx="21" cy="21" r="15.9155" fill="transparent" stroke="#dc2626" stroke-width="5" stroke-dasharray="{!! $cmDash !!} {!! $c - $cmDash !!}" stroke-dashoffset="{!! $cmOffset !!}"></circle>
              <!-- Proposals: Teal -->
              <circle cx="21" cy="21" r="15.9155" fill="transparent" stroke="#059669" stroke-width="5" stroke-dasharray="{!! $prDash !!} {!! $c - $prDash !!}" stroke-dashoffset="{!! $prOffset !!}"></circle>
            @endif
          </svg>
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <span class="text-sm font-bold text-navy">{!! $feedbackCount + $complaintCount + $proposalCount !!}</span>
            <span class="text-[9px] text-slate uppercase tracking-wider font-medium">Approved</span>
          </div>
        </div>

        <!-- Legend -->
        <div class="space-y-3 text-xs flex-1 min-w-0">
          <div class="p-2 rounded-lg bg-pagebg/70 border border-borderc/50 flex items-center justify-between gap-1">
            <span class="flex items-center gap-2 truncate text-slate font-medium">
              <span class="w-2.5 h-2.5 rounded-full inline-block flex-shrink-0" style="background:#64748b"></span>
              General Feedback
            </span>
            <span class="font-bold text-navy">{!! $feedbackCount !!} <span class="text-[10px] text-slate font-normal">({!! $fbPct !!}%)</span></span>
          </div>
          <div class="p-2 rounded-lg bg-pagebg/70 border border-borderc/50 flex items-center justify-between gap-1">
            <span class="flex items-center gap-2 truncate text-slate font-medium">
              <span class="w-2.5 h-2.5 rounded-full bg-red-600 inline-block flex-shrink-0"></span>
              Complaints & Issues
            </span>
            <span class="font-bold text-navy">{!! $complaintCount !!} <span class="text-[10px] text-slate font-normal">({!! $cmPct !!}%)</span></span>
          </div>
          <div class="p-2 rounded-lg bg-pagebg/70 border border-borderc/50 flex items-center justify-between gap-1">
            <span class="flex items-center gap-2 truncate text-slate font-medium">
              <span class="w-2.5 h-2.5 rounded-full inline-block flex-shrink-0" style="background:#059669"></span>
              Citizen Proposals
            </span>
            <span class="font-bold text-navy">{!! $proposalCount !!} <span class="text-[10px] text-slate font-normal">({!! $prPct !!}%)</span></span>
          </div>
        </div>
      </div>
    </div>

    <div class="border-t border-borderc pt-3 mt-4 text-[11px] text-slate text-center">
      Aggregated from all moderation-approved records
    </div>
  </div>

</div>

<!-- Attention & Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

  <!-- Recent Activity -->
  <div class="card p-5 flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-sm font-semibold text-navy">Recent Activity</h2>
          <p class="text-xs text-slate mt-0.5">{!! role_is_admin($staff['role'] ?? null) ? 'Work across all modules' : 'Updates on your work and new submissions' !!}</p>
        </div>
        @if (($staff['role'] ?? null) === 'super_admin')
          <a href="{{ app_href('admin/audit-log') }}" class="text-[11px] text-teal hover:underline">Audit log &rarr;</a>
        @endif
      </div>

      @if (!$recentActivity)
        <div class="flex flex-col items-center justify-center py-12 text-center my-auto">
          <div class="w-12 h-12 rounded-full bg-pagebg text-slate flex items-center justify-center mb-2">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 14 14"></polyline>
            </svg>
          </div>
          <p class="text-xs font-medium text-slate">No activity relevant to you yet.</p>
        </div>
      @else
        <?php $dotClass = ['new' => 'bg-teal', 'good' => 'bg-emerald', 'bad' => 'bg-dangerred', 'neutral' => 'bg-slate/50']; ?>
        <ul class="space-y-1 overflow-y-auto max-h-[310px] pr-1">
          @foreach ($recentActivity as $a) @php $tag = $a['href'] ? 'a' : 'div'; @endphp
          <li>
            <{!! $tag !!} {!! $a['href'] ? 'href="' . e(app_href('admin/' . $a['href'])) . '"' : '' !!}
               class="flex items-start gap-2.5 text-xs rounded px-1.5 py-1.5 {!! $a['href'] ? 'hover:bg-pagebg group' : '' !!}">
              <span class="w-2 h-2 rounded-full {!! $dotClass[$a['tone']] !!} mt-1.5 flex-shrink-0"></span>
              <div class="flex-1 min-w-0">
                <p class="text-navy font-medium truncate {!! $a['href'] ? 'group-hover:underline' : '' !!}" title="{{ $a['text'] }}">{{ $a['text'] }}</p>
                <p class="text-slate text-[11px]">
                  {{ $a['actor'] }} &middot;
                  <span title="{{ date('M j, Y g:i A', strtotime($a['created_at'])) }}">{!! time_ago($a['created_at']) !!}</span>
                </p>
              </div>
            </{!! $tag !!}>
          </li>
          @endforeach
        </ul>
      @endif
    </div>

    <div class="border-t border-borderc pt-3 mt-4 text-xs text-slate flex items-center justify-between gap-3 flex-wrap">
      <span>Showing activity relevant to you</span>
      <span class="flex items-center gap-3 text-[11px]">
        <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-teal"></span>New</span>
        <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald"></span>Approved / done</span>
        <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-dangerred"></span>Rejected / escalated</span>
      </span>
    </div>
  </div>

  <!-- Needs Attention Panel -->
  <div class="card p-5 flex flex-col justify-between">
    <div>
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <h2 class="text-sm font-semibold text-navy">Needs Attention</h2>
          @if ($needsAttentionCount > 0)
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">
              {!! $needsAttentionCount !!}
            </span>
          @endif
        </div>
        <span class="text-[11px] text-slate">Action required today</span>
      </div>

      @if (!$needsAttention)
        <div class="flex flex-col items-center justify-center py-12 text-center my-auto">
          <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          <p class="text-xs font-semibold text-navy">All clear!</p>
          <p class="text-[11px] text-slate mt-0.5">No urgent complaints, overdue responses, or stale backlog.</p>
        </div>
      @else
        <div class="space-y-2 overflow-y-auto max-h-[310px] pr-1">
          @foreach ($needsAttention as $n)
          <a href="{{ $n['href'] }}" class="flex items-start gap-2.5 p-2.5 rounded-lg border border-borderc/60 hover:border-borderc hover:bg-pagebg transition group">
            <span class="badge {!! $n['severity'] === 'red' ? 'badge-red' : 'badge-amber' !!} flex-shrink-0 mt-0.5 font-semibold text-[10px]">
              {{ $n['type'] }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-xs font-medium text-navy group-hover:text-primary transition-colors truncate">{!! $n['text'] !!}</span>
              <span class="block text-[10px] text-slate mt-0.5">{{ $n['sub'] }}</span>
            </span>
          </a>
          @endforeach
        </div>
      @endif
    </div>
    
    <div class="border-t border-borderc pt-3 mt-4 flex items-center justify-between text-xs text-slate">
      <span>Critical and high-priority complaints, oldest first</span>
      <a href="{{ app_href('admin/complaints') }}" class="text-teal hover:underline font-medium text-[11px]">View Complaints Queue &rarr;</a>
    </div>
  </div>

</div>

</div><!-- /#dashboard-root -->

<!-- Submissions This Week: grow-in animation + hover/focus tooltip -->
<style>
  @keyframes week-grow { from { transform: scaleY(0); } to { transform: scaleY(1); } }
  #week-chart .week-bar { animation: week-grow .5s ease-out both; }
  @media (prefers-reduced-motion: reduce) { #week-chart .week-bar { animation: none; } }
</style>
<script>
(function initWeekChart() {
  const chart = document.getElementById('week-chart');
  const tip = document.getElementById('week-tip');
  if (!chart || !tip) return;

  const series = {!! json_encode($weekSeries) !!};

  function show(col) {
    const total = +col.dataset.total;
    const rows = Object.entries(series)
      .filter(([k]) => +col.dataset[k] > 0)
      .map(([k, s]) => `<div class="flex items-center justify-between gap-3 mt-1">
          <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm" style="background:${s.color}"></span>${s.label}</span>
          <span class="font-semibold">${col.dataset[k]}</span></div>`)
      .join('');
    tip.innerHTML = `<div class="font-semibold">${col.dataset.title}</div>
      <div class="text-white/70">${total ? `${total} submission${total === 1 ? '' : 's'}` : 'No submissions'}</div>${rows}`;
    tip.classList.remove('hidden');

    // Centre over the column, kept inside the chart.
    const c = chart.getBoundingClientRect(), r = col.getBoundingClientRect();
    const left = Math.min(Math.max(r.left - c.left + r.width / 2 - tip.offsetWidth / 2, 0), c.width - tip.offsetWidth);
    tip.style.left = left + 'px';
  }
  const hide = () => tip.classList.add('hidden');

  chart.querySelectorAll('.week-col').forEach((col, i) => {
    const bar = col.querySelector('.week-bar');
    if (bar) bar.style.animationDelay = (i * 50) + 'ms';
    col.addEventListener('mouseenter', () => show(col));
    col.addEventListener('focus', () => show(col));
    col.addEventListener('mouseleave', hide);
    col.addEventListener('blur', hide);
  });
})();
</script>

<!-- Live Silent Auto-Refresh for KPI cards every 60s -->
<script>
(function initKpiAutoRefresh() {
  const REFRESH_INTERVAL_MS = 60000;

  async function fetchKpis() {
    try {
      const res = await fetch('{{ app_href('api/dashboard/kpis') }}', {
        // Background poll: must not reset the idle timeout (see the StaffIdleTimeout middleware).
        headers: { 'Accept': 'application/json', 'X-Background-Refresh': '1' }
      });
      if (CivicConnect.redirectIfSessionExpired(res)) return;
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data || !Array.isArray(json.data.kpis)) return;

      json.data.kpis.forEach((kpi) => {
        const valEl = document.querySelector(`.kpi-value[data-kpi-key="${kpi.key}"]`);
        const cardEl = document.querySelector(`.kpi-card-link[data-kpi-idx="${kpi.key}"]`);
        if (!valEl || !cardEl) return;

        // Update value
        valEl.textContent = kpi.value;

        // Update trend arrow and text
        const trendWrap = cardEl.querySelector('.kpi-trend-wrap');
        if (trendWrap) {
          const trend = kpi.trend || 0;
          const isGood = kpi.trend_positive_is_good ? (trend >= 0) : (trend <= 0);
          const color = trend === 0 ? '#64748b' : (isGood ? '#059669' : '#dc2626');
          const arrow = trend > 0 ? '▲' : (trend < 0 ? '▼' : '•');

          trendWrap.style.color = color;
          if (trend !== 0) {
            trendWrap.innerHTML = `<span>${arrow} ${Math.abs(trend)}%</span> <span class="text-[10px] text-slate/70 font-normal">vs last wk</span>`;
          } else {
            trendWrap.innerHTML = `<span class="text-[10px] text-slate font-normal">No change vs last wk</span>`;
          }
        }
      });
    } catch (e) {
      // Silent error: do not interrupt user
    }
  }

  setInterval(fetchKpis, REFRESH_INTERVAL_MS);
})();
</script>

<script>
// Export PDF: the dashboard as it looks, password-protected (CivicConnect.protectedPdf in app.js).
(function initDashboardExport() {
  const btn = document.getElementById('dashboard-export-btn');
  btn?.addEventListener('click', async () => {
    btn.disabled = true;
    try {
      await CivicConnect.protectedPdf({
        root: document.getElementById('dashboard-root'),
        endpoint: `${window.APP_URL}/api/dashboard/pdf`,
      });
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>

@include('partials.admin-footer')
