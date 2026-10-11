/**
 * CivicConnect — Admin: Engagement Analytics
 * Filterable dashboard: date range / type / category / sentiment,
 * KPI cards with period-over-period trends, submissions-over-time by type,
 * submission distribution, citizen sentiment, top concerns, and proposal
 * outcomes.
 * Pure inline SVG — no chart library required.
 */
(() => {
  const root = document.getElementById('analytics-root');
  if (!root) return;

  /* -------------------------------------------------------------------------
   * Design tokens
   * ---------------------------------------------------------------------- */
  const TYPE_COLORS = {
    'Feedback':  '#06b6d4',
    'Complaint': '#f43f5e',
    'Proposal':  '#a78bfa',
  };
  const TYPE_LABELS = {
    'Feedback':  'Feedback',
    'Complaint': 'Complaints',
    'Proposal':  'Proposals',
  };

  // Sentiment is a polarity measure (negative <-> positive with a neutral
  // midpoint), so it reuses the app's existing good/neutral/critical status
  // colors rather than introducing a new hue family.
  const SENTIMENT_COLORS = {
    'Positive': '#10b981',
    'Neutral':  '#94a3b8',
    'Negative': '#ef4444',
  };

  // Single-series magnitude (Top Concerns): the brand blue. Amber is reserved
  // for warnings/pending states, so it shouldn't double as a plain bar color.
  const CONCERN_COLOR = '#2563eb';

  const KPI_ICON_COLORS = {
    'blue':   { bg: 'rgba(59,130,246,0.10)',  fg: '#3b82f6' },
    'green':  { bg: 'rgba(16,185,129,0.10)',  fg: '#10b981' },
    'amber':  { bg: 'rgba(245,158,11,0.10)',  fg: '#f59e0b' },
    'red':    { bg: 'rgba(239,68,68,0.10)',   fg: '#ef4444' },
    'purple': { bg: 'rgba(167,139,250,0.14)', fg: '#8b5cf6' },
    'teal':   { bg: 'rgba(20,184,166,0.12)',  fg: '#0d9488' },
    'indigo': { bg: 'rgba(99,102,241,0.12)',  fg: '#6366f1' },
    'pink':   { bg: 'rgba(236,72,153,0.12)',  fg: '#ec4899' },
    'gray':   { bg: 'rgba(148,163,184,0.14)', fg: '#64748b' },
  };

  /* -------------------------------------------------------------------------
   * Tooltip
   * ---------------------------------------------------------------------- */
  let tooltipEl = null;

  function getTooltip() {
    if (tooltipEl) return tooltipEl;
    tooltipEl = document.createElement('div');
    tooltipEl.id = 'analytics-tooltip';
    Object.assign(tooltipEl.style, {
      position: 'fixed', zIndex: '2000', pointerEvents: 'none', display: 'none',
      background: '#fff', fontSize: '12px', lineHeight: '1.55',
      padding: '8px 12px', borderRadius: '8px', border: '1px solid #e2e8f0',
      boxShadow: '0 8px 24px rgba(15,23,42,0.14)', maxWidth: '240px',
    });
    const content = document.createElement('div');
    content.className = 'tt-content';
    tooltipEl.appendChild(content);
    document.body.appendChild(tooltipEl);
    return tooltipEl;
  }

  function showTooltip(x, y, html) {
    const el = getTooltip();
    el.querySelector('.tt-content').innerHTML = html;
    el.style.display = 'block';
    const r = el.getBoundingClientRect();
    let left = x + 14;
    let top  = y - r.height / 2;
    if (left + r.width > window.innerWidth - 8) left = x - r.width - 14;
    top = Math.max(8, Math.min(top, window.innerHeight - r.height - 8));
    el.style.left = left + 'px';
    el.style.top  = top + 'px';
  }

  function hideTooltip() { if (tooltipEl) tooltipEl.style.display = 'none'; }

  function attachTooltips(container) {
    container.querySelectorAll('[data-tt]').forEach(el => {
      let data;
      try { data = JSON.parse(decodeURIComponent(el.getAttribute('data-tt'))); } catch { return; }
      el.style.cursor = 'default';
      el.addEventListener('mouseenter', e => showTooltip(e.clientX, e.clientY, data.html));
      el.addEventListener('mousemove',  e => showTooltip(e.clientX, e.clientY, data.html));
      el.addEventListener('mouseleave', hideTooltip);
    });
  }

  function tt(html) {
    return `data-tt="${encodeURIComponent(JSON.stringify({ html }))}"`;
  }

  /* -------------------------------------------------------------------------
   * Helpers
   * ---------------------------------------------------------------------- */
  function esc(s) { return CivicConnect.escapeHtml(String(s ?? '')); }

  function swatchHtml(color, label, value) {
    return `<div style="display:flex;align-items:center;gap:6px;margin-top:3px;">
      <span style="width:8px;height:8px;border-radius:50%;background:${color};flex-shrink:0;display:inline-block;"></span>
      <span style="color:${color};font-weight:600;">${label}</span>
      <span style="color:#64748b;margin-left:auto;padding-left:8px;">${value}</span>
    </div>`;
  }

  function fmtDate(iso) {
    if (!iso) return '';
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  /* -------------------------------------------------------------------------
   * Icon set
   * ---------------------------------------------------------------------- */
  const ICON_PATHS = {
    inbox: `<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/>`,
    plus: `<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>`,
    check: `<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>`,
    clock: `<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>`,
    watch: `<circle cx="12" cy="13" r="8"/><polyline points="12 9 12 13 14.5 14.5"/><path d="M9 3h6M12 3v3"/>`,
    heart: `<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>`,
    donut: `<path d="M21.21 15.89A10 10 0 118 2.83"/><path d="M22 12A10 10 0 0012 2v10z"/>`,
    trend: `<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>`,
    bars: `<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>`,
    dept: `<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>`,
    shield: `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>`,
    lightbulb: `<path d="M9 18h6M10 22h4M12 2a7 7 0 00-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0012 2z"/>`,
    grid: `<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>`,
    trash: `<path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>`,
    road: `<path d="M6 20L11 4h2l5 16M9 15h6"/>`,
    bus: `<rect x="3" y="6" width="18" height="11" rx="2"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="16.5" cy="19" r="1.5"/><line x1="3" y1="11" x2="21" y2="11"/>`,
    doc: `<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>`,
    tree: `<path d="M12 22v-6"/><path d="M8 14a4 4 0 118 0c0 2.5-4 3-4 3s-4-.5-4-3z"/><path d="M9 8a3 3 0 116 0c0 2-3 3-3 3s-3-1-3-3z"/>`,
    building: `<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>`,
    wrench: `<path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/>`,
    dots: `<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>`,
  };
  function icon(name, size = 14) {
    return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${ICON_PATHS[name] || ICON_PATHS.dots}</svg>`;
  }

  function categoryBadge(name) {
    const key = (name || '').toLowerCase();
    if (key.includes('sanit'))                          return { icon: 'trash',    color: 'green'  };
    if (key.includes('infrastructure') || key.includes('road')) return { icon: 'road', color: 'amber' };
    if (key.includes('transport') || key.includes('transit'))   return { icon: 'bus',  color: 'purple' };
    if (key.includes('safety'))                          return { icon: 'shield',   color: 'red'    };
    if (key.includes('licens') || key.includes('permit')) return { icon: 'doc',     color: 'blue'   };
    if (key.includes('park') || key.includes('recreation')) return { icon: 'tree',  color: 'teal'   };
    return { icon: 'dots', color: 'gray' };
  }
  function badgeHtml(name, size = 28) {
    const b = categoryBadge(name);
    const c = KPI_ICON_COLORS[b.color];
    return `<span style="display:inline-flex;align-items:center;justify-content:center;width:${size}px;height:${size}px;border-radius:8px;background:${c.bg};color:${c.fg};flex-shrink:0;">${icon(b.icon, Math.round(size * 0.55))}</span>`;
  }
  /* -------------------------------------------------------------------------
   * Filter bar
   * ---------------------------------------------------------------------- */
  const RANGE_OPTIONS = [['today', 'Today'], ['7d', 'Last 7 Days'], ['30d', 'Last 30 Days'], ['6m', 'Last 6 Months'], ['custom', 'Custom']];
  const TYPE_OPTIONS = [['all', 'All Types'], ['feedback', 'Feedback'], ['complaint', 'Complaints'], ['proposal', 'Proposals']];
  const SENTIMENT_OPTIONS = [['all', 'All Sentiments'], ['Positive', 'Positive'], ['Neutral', 'Neutral'], ['Negative', 'Negative']];

  const selectStyle = 'font-size:12px;font-weight:500;color:#334155;background:#fff;border:1px solid #e2e8f0;border-radius:7px;padding:7px 10px;cursor:pointer;';

  function renderSelect(id, opts, current) {
    return `<select id="${id}" style="${selectStyle}">
      ${opts.map(([v, l]) => `<option value="${esc(v)}" ${String(v) === String(current) ? 'selected' : ''}>${esc(l)}</option>`).join('')}
    </select>`;
  }

  function renderFilterBar(filters, options) {
    const catOpts  = [['all', 'All Categories'], ...options.categories.map(c => [c, c])];
    const isCustom = filters.range === 'custom';

    return `<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
      <div>
        <p style="font-size:11px;color:#94a3b8;margin:0;">Showing</p>
        <p style="font-size:12px;color:#334155;font-weight:600;margin:0;">${esc(fmtDate(filters.range_from))} &ndash; ${esc(fmtDate(filters.range_to))}</p>
        <p class="print-hidden" style="font-size:11px;color:#64748b;margin:2px 0 0;">
          ${[TYPE_OPTIONS, catOpts, SENTIMENT_OPTIONS].map((opts, i) => {
            const cur = [filters.type, filters.category, filters.sentiment][i];
            return esc((opts.find(([v]) => String(v) === String(cur)) || opts[0])[1]);
          }).join(' &middot; ')}
          &middot; Generated ${esc(new Date().toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }))}
        </p>
      </div>
      <div class="no-print" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        ${renderSelect('af-range', RANGE_OPTIONS, filters.range)}
        <div id="af-custom-dates" style="display:${isCustom ? 'flex' : 'none'};align-items:center;gap:6px;">
          <input type="date" id="af-from" value="${esc(filters.range_from)}" style="${selectStyle}">
          <span style="color:#94a3b8;font-size:12px;">to</span>
          <input type="date" id="af-to" value="${esc(filters.range_to)}" style="${selectStyle}">
        </div>
        ${renderSelect('af-type', TYPE_OPTIONS, filters.type)}
        ${renderSelect('af-category', catOpts, filters.category)}
        ${renderSelect('af-sentiment', SENTIMENT_OPTIONS, filters.sentiment)}
      </div>
    </div>`;
  }

  function readFilterBar() {
    const val = id => document.getElementById(id)?.value;
    return {
      range: val('af-range') || '30d',
      from: val('af-from') || '',
      to: val('af-to') || '',
      type: val('af-type') || 'all',
      category: val('af-category') || 'all',
      sentiment: val('af-sentiment') || 'all',
    };
  }

  function wireFilterBar() {
    const rangeSel = document.getElementById('af-range');
    const customWrap = document.getElementById('af-custom-dates');
    rangeSel?.addEventListener('change', () => {
      if (customWrap) customWrap.style.display = rangeSel.value === 'custom' ? 'flex' : 'none';
      if (rangeSel.value !== 'custom') load(readFilterBar());
    });
    ['af-from', 'af-to'].forEach(id => document.getElementById(id)?.addEventListener('change', () => load(readFilterBar())));
    ['af-type', 'af-category', 'af-sentiment'].forEach(id =>
      document.getElementById(id)?.addEventListener('change', () => load(readFilterBar())));
  }

  /* -------------------------------------------------------------------------
   * KPI Cards (with period-over-period trend)
   * ---------------------------------------------------------------------- */
  function trendHtml(k) {
    const trend = k.trend ?? 0;
    const isGood = k.trend_positive_is_good ? trend >= 0 : trend <= 0;
    const color = trend === 0 ? '#94a3b8' : (isGood ? '#059669' : '#dc2626');
    const arrow = trend > 0 ? '&#9650;' : (trend < 0 ? '&#9660;' : '&bull;');
    return `<div style="display:flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:${color};margin-top:4px;">
      <span>${arrow} ${Math.abs(trend)}%</span>
      <span style="color:#94a3b8;font-weight:400;">vs ${esc(k.trend_label)}</span>
    </div>`;
  }

  /** Where each KPI card leads: the submission list, pre-filtered to match the card. */
  function kpiHref(key, f) {
    if (key === 'avg_response') return 'responses';
    const q = { date_from: f.range_from, date_to: f.range_to };
    if (f.type && f.type !== 'all') q.type = f.type;
    if (f.category && f.category !== 'all') q.category = f.category;
    if (f.sentiment && f.sentiment !== 'all') q.sentiment = f.sentiment;
    if (key === 'new_submissions') {
      const d = new Date();
      q.date_to = isoDate(d);
      d.setDate(d.getDate() - 7);
      q.date_from = isoDate(d);
    } else if (key === 'resolved_issues') {
      q.type = 'complaint';
      q.status = 'Resolved';
    } else if (key === 'pending_review') {
      q.status = 'Unresolved';
    } else if (key === 'positive_sentiment') {
      q.sentiment = 'Positive';
    }
    return `submissions?${new URLSearchParams(q)}`;
  }

  function isoDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  function renderKpis(kpis, filters) {
    return `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:24px;">
      ${kpis.map(k => {
        const c = KPI_ICON_COLORS[k.color] || KPI_ICON_COLORS.blue;
        return `<a href="${esc(kpiHref(k.key, filters))}" class="card kpi-card-link" style="display:block;padding:16px 20px;color:inherit;">
          <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:40px;height:40px;border-radius:10px;background:${c.bg};
                 display:flex;align-items:center;justify-content:center;flex-shrink:0;color:${c.fg};">
              ${icon(k.icon, 18)}
            </div>
            <div>
              <p style="font-size:11px;color:#64748b;margin:0 0 2px;">${esc(k.label)}</p>
              <p style="font-size:20px;font-weight:700;color:#0f172a;margin:0;">${esc(k.value)}</p>
            </div>
          </div>
          ${trendHtml(k)}
        </a>`;
      }).join('')}
    </div>`;
  }

  /* -------------------------------------------------------------------------
   * Monotone cubic spline (Fritsch–Carlson) through a set of points.
   * Smooth like Catmull-Rom, but corrected so it never overshoots past a
   * peak/valley or dips below a flat run — important here since the values
   * are non-negative counts and a naive spline can swing below zero.
   * ---------------------------------------------------------------------- */
  function monotoneCubicPath(pts) {
    const n = pts.length;
    if (n === 0) return '';
    if (n === 1) return `M${pts[0].x},${pts[0].y}`;
    if (n === 2) return `M${pts[0].x},${pts[0].y} L${pts[1].x},${pts[1].y}`;

    const dx = [], d = [];
    for (let i = 0; i < n - 1; i++) {
      dx[i] = pts[i + 1].x - pts[i].x;
      d[i] = (pts[i + 1].y - pts[i].y) / dx[i];
    }

    const m = new Array(n);
    m[0] = d[0];
    m[n - 1] = d[n - 2];
    for (let i = 1; i < n - 1; i++) {
      m[i] = (d[i - 1] === 0 || d[i] === 0 || (d[i - 1] > 0) !== (d[i] > 0)) ? 0 : (d[i - 1] + d[i]) / 2;
    }
    for (let i = 0; i < n - 1; i++) {
      if (d[i] === 0) { m[i] = 0; m[i + 1] = 0; continue; }
      const a = m[i] / d[i], b = m[i + 1] / d[i];
      const s = a * a + b * b;
      if (s > 9) {
        const tau = 3 / Math.sqrt(s);
        m[i] = tau * a * d[i];
        m[i + 1] = tau * b * d[i];
      }
    }

    let path = `M${pts[0].x.toFixed(2)},${pts[0].y.toFixed(2)}`;
    for (let i = 0; i < n - 1; i++) {
      const cp1x = pts[i].x + dx[i] / 3;
      const cp1y = pts[i].y + (m[i] * dx[i]) / 3;
      const cp2x = pts[i + 1].x - dx[i] / 3;
      const cp2y = pts[i + 1].y - (m[i + 1] * dx[i]) / 3;
      path += ` C${cp1x.toFixed(2)},${cp1y.toFixed(2)} ${cp2x.toFixed(2)},${cp2y.toFixed(2)} ${pts[i + 1].x.toFixed(2)},${pts[i + 1].y.toFixed(2)}`;
    }
    return path;
  }

  /* -------------------------------------------------------------------------
   * Mini stat cards above the Over Time chart (Total / Feedback / Complaints
   * / Proposals) — each one's colored dot doubles as that series' legend key,
   * so the chart itself needs no separate legend.
   * ---------------------------------------------------------------------- */
  function renderOverTimeSummary(summary) {
    if (!summary || !summary.length) return '';
    return `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:16px;margin-bottom:18px;">
      ${summary.map(s => {
        const trend = s.trend ?? 0;
        const color = trend === 0 ? '#94a3b8' : (trend > 0 ? '#059669' : '#dc2626');
        const arrow = trend > 0 ? '&#9650;' : (trend < 0 ? '&#9660;' : '&bull;');
        return `<div>
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
            <span style="width:8px;height:8px;border-radius:50%;background:${s.color};display:inline-block;flex-shrink:0;"></span>
            <span style="font-size:11px;color:#64748b;font-weight:500;">${esc(s.label)}</span>
          </div>
          <div style="display:flex;align-items:baseline;gap:8px;">
            <span style="font-size:20px;font-weight:700;color:#0f172a;">${s.value}</span>
            <span style="font-size:11px;font-weight:600;color:${color};">${arrow} ${Math.abs(trend)}%</span>
          </div>
        </div>`;
      }).join('')}
    </div>`;
  }

  const OVER_TIME_DIMS = { W: 900, H: 340, padL: 36, padR: 12, padT: 16, padB: 30 };

  // Round the y-axis top up to a "nice" number so the 4 gridline steps are whole.
  function niceMax(v) {
    const step = Math.max(1, Math.ceil(v / 4));
    const mag = Math.pow(10, Math.floor(Math.log10(step)));
    const nice = [1, 2, 2.5, 5, 10].map(m => m * mag).find(s => s >= step) || step;
    return Math.max(4, Math.ceil(nice) * 4);
  }
  const OT_SERIES = [
    { key: 'total',      label: 'Total',      color: '#3b82f6',            width: 2.5, opacity: 1 },
    { key: 'feedback',   label: 'Feedback',   color: TYPE_COLORS.Feedback, width: 1.5, opacity: 0.8 },
    { key: 'complaint',  label: 'Complaints', color: TYPE_COLORS.Complaint,width: 1.5, opacity: 0.8 },
    { key: 'proposal',   label: 'Proposals',  color: TYPE_COLORS.Proposal, width: 1.5, opacity: 0.8 },
  ];
  const localDay = (iso) => new Date(String(iso).slice(0, 10) + 'T00:00:00');
  function otValue(r, key) { return key === 'total' ? r.feedback + r.complaint + r.proposal : r[key]; }
  function otPeriodLabel(r, unit) {
    return (unit === 'week' ? 'Week of ' : '') + localDay(r.day).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  /* -------------------------------------------------------------------------
   * Submissions Over Time — Total as the prominent filled line, Feedback /
   * Complaints / Proposals as thinner context lines underneath it, plus a
   * shared crosshair: hover anywhere in the plot and one tooltip lists all
   * four values for that date (see wireOverTimeCrosshair below).
   * ---------------------------------------------------------------------- */
  function renderSubmissionsOverTime(rows) {
    if (!rows || !rows.length) return '<p style="text-align:center;color:#94a3b8;padding:40px 0;font-size:13px;">No data yet.</p>';

    const { W, H, padL, padR, padT, padB } = OVER_TIME_DIMS;
    const iW = W - padL - padR, iH = H - padT - padB;
    const n = rows.length;
    const maxVal = niceMax(Math.max(1, ...rows.map(r => otValue(r, 'total'))));
    const xAt = i => padL + (n > 1 ? (i / (n - 1)) * iW : iW / 2);
    const yAt = v => padT + iH - (v / maxVal) * iH;

    const seriesPaths = OT_SERIES.map(sd => {
      const pts = rows.map((r, i) => ({ x: xAt(i), y: yAt(otValue(r, sd.key)) }));
      return { sd, pts, path: monotoneCubicPath(pts) };
    });

    const totalEntry = seriesPaths[0];
    const areaPath = `${totalEntry.path} L${totalEntry.pts[n - 1].x.toFixed(2)},${(padT + iH).toFixed(2)} L${totalEntry.pts[0].x.toFixed(2)},${(padT + iH).toFixed(2)} Z`;

    // Context series (Feedback/Complaints/Proposals) drawn first, Total last so it stays on top.
    const contextLines = seriesPaths.slice(1).map(({ sd, path }) =>
      `<path d="${path}" fill="none" stroke="${sd.color}" stroke-width="${sd.width}" stroke-linejoin="round" stroke-linecap="round" opacity="${sd.opacity}"/>`
    ).join('');

    const labelStep = Math.max(1, Math.floor(n / 7));
    const xLabels = rows.map((r, i) => {
      // Every labelStep-th day plus the last one — but drop a step label that would collide with the last.
      const isLast = i === n - 1;
      if (!isLast && (i % labelStep !== 0 || n - 1 - i < labelStep * 0.6)) return '';
      const d = localDay(r.day);
      const label = (d.getMonth() + 1) + '/' + d.getDate();
      return `<text x="${xAt(i)}" y="${H - 8}" font-size="11" fill="#64748b" text-anchor="middle">${label}</text>`;
    }).join('');

    const gridLines = [0, 0.25, 0.5, 0.75, 1].map(f => {
      const y = padT + iH * (1 - f);
      const v = Math.round(maxVal * f);
      return `<line x1="${padL}" y1="${y}" x2="${W - padR}" y2="${y}" stroke="${f === 0 ? '#e2e8f0' : '#f1f5f9'}" stroke-width="1"/>
              <text x="${padL - 6}" y="${y + 4}" font-size="11" fill="#64748b" text-anchor="end">${v}</text>`;
    }).join('');

    const crosshairLine = `<line class="ot-crosshair-line" x1="${padL}" y1="${padT}" x2="${padL}" y2="${padT + iH}" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="3,3" opacity="0"/>`;
    const crosshairDots = OT_SERIES.map(sd =>
      `<circle class="ot-crosshair-dot" data-series="${sd.key}" cx="${padL}" cy="${padT}" r="4" fill="${sd.color}" stroke="#fff" stroke-width="2" opacity="0"/>`
    ).join('');
    const hitRect = `<rect class="ot-hit-rect" x="${padL}" y="${padT}" width="${iW}" height="${iH}" fill="transparent" style="cursor:crosshair;"/>`;

    return `<svg class="ot-svg" viewBox="0 0 ${W} ${H}" style="width:100%;max-height:360px;overflow:visible;" role="img" aria-label="Submissions over time by type. Use Show data table below for the values.">
      <defs>
        <linearGradient id="ot-total-grad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.22"/>
          <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.02"/>
        </linearGradient>
      </defs>
      ${gridLines}
      <path d="${areaPath}" fill="url(#ot-total-grad)"/>
      ${contextLines}
      <path d="${totalEntry.path}" fill="none" stroke="${totalEntry.sd.color}" stroke-width="${totalEntry.sd.width}" stroke-linejoin="round" stroke-linecap="round"/>
      ${crosshairLine}
      ${crosshairDots}
      ${xLabels}
      ${hitRect}
    </svg>`;
  }

  /* Data-table view of the over-time chart (accessibility + exact numbers). */
  function renderOverTimeTable(rows, unit) {
    if (!rows || !rows.length) return '';
    const th = 'text-align:right;padding:6px 10px;font-weight:600;color:#64748b;white-space:nowrap;';
    const td = 'text-align:right;padding:6px 10px;color:#0f172a;font-variant-numeric:tabular-nums;';
    return `<details class="no-print" style="margin-top:10px;">
      <summary style="cursor:pointer;font-size:12px;font-weight:600;color:#2563eb;">Show data table</summary>
      <div style="max-height:260px;overflow:auto;margin-top:8px;border:1px solid #e2e8f0;border-radius:8px;">
        <table style="width:100%;font-size:12px;border-collapse:collapse;">
          <thead style="position:sticky;top:0;background:#f8fafc;"><tr>
            <th style="${th}text-align:left;">${unit === 'week' ? 'Week of' : 'Date'}</th>
            ${OT_SERIES.map(sd => `<th style="${th}">${esc(sd.label)}</th>`).join('')}
          </tr></thead>
          <tbody>${rows.map(r => `<tr style="border-top:1px solid #f1f5f9;">
            <td style="${td}text-align:left;color:#475569;">${esc(localDay(r.day).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }))}</td>
            ${OT_SERIES.map(sd => `<td style="${td}${sd.key === 'total' ? 'font-weight:600;' : ''}">${otValue(r, sd.key)}</td>`).join('')}
          </tr>`).join('')}</tbody>
        </table>
      </div>
    </details>`;
  }

  /**
   * Wires the shared crosshair for renderSubmissionsOverTime's SVG: hover
   * anywhere over the plot and one tooltip reports Total/Feedback/Complaints/
   * Proposals together for the nearest date (dataviz guidance: "one tooltip,
   * every series" — the reader never has to land precisely on a line).
   */
  function wireOverTimeCrosshair(svgEl, rows, unit) {
    if (!svgEl || !rows || !rows.length) return;
    const { W, padL, padT } = OVER_TIME_DIMS;
    const iW = OVER_TIME_DIMS.W - OVER_TIME_DIMS.padL - OVER_TIME_DIMS.padR;
    const n = rows.length;
    const xAt = i => padL + (n > 1 ? (i / (n - 1)) * iW : iW / 2);
    const maxVal = niceMax(Math.max(1, ...rows.map(r => otValue(r, 'total'))));
    const iH = OVER_TIME_DIMS.H - OVER_TIME_DIMS.padT - OVER_TIME_DIMS.padB;
    const yAt = v => padT + iH - (v / maxVal) * iH;

    const hitRect = svgEl.querySelector('.ot-hit-rect');
    const lineEl = svgEl.querySelector('.ot-crosshair-line');
    const dotEls = {};
    OT_SERIES.forEach(sd => { dotEls[sd.key] = svgEl.querySelector(`.ot-crosshair-dot[data-series="${sd.key}"]`); });
    if (!hitRect || !lineEl) return;

    function showAt(index, clientX, clientY) {
      const r = rows[index];
      const x = xAt(index);
      lineEl.setAttribute('x1', x); lineEl.setAttribute('x2', x); lineEl.setAttribute('opacity', '1');
      OT_SERIES.forEach(sd => {
        const el = dotEls[sd.key];
        if (!el) return;
        el.setAttribute('cx', x);
        el.setAttribute('cy', yAt(otValue(r, sd.key)));
        el.setAttribute('opacity', '1');
      });
      const rowsHtml = OT_SERIES.map(sd => swatchHtml(sd.color, sd.label, otValue(r, sd.key))).join('');
      showTooltip(clientX, clientY, `<div style="font-weight:700;color:#0f172a;margin-bottom:3px;">${otPeriodLabel(r, unit)}</div>${rowsHtml}`);
    }
    function hide() {
      lineEl.setAttribute('opacity', '0');
      OT_SERIES.forEach(sd => dotEls[sd.key]?.setAttribute('opacity', '0'));
      hideTooltip();
    }

    hitRect.addEventListener('mousemove', e => {
      const rect = svgEl.getBoundingClientRect();
      if (rect.width === 0) return;
      const scaleX = W / rect.width;
      const localX = (e.clientX - rect.left) * scaleX;
      const step = n > 1 ? iW / (n - 1) : iW;
      let index = Math.round((localX - padL) / step);
      index = Math.max(0, Math.min(n - 1, index));
      showAt(index, e.clientX, e.clientY);
    });
    hitRect.addEventListener('mouseleave', hide);
  }

  /* -------------------------------------------------------------------------
   * Donut (reusable — Submission Distribution, Citizen Sentiment)
   * ---------------------------------------------------------------------- */
  // center: { value, label } — what the hole says. Omit it to show the largest
  // slice's share under that slice's own name (never a fixed, unrelated label).
  function renderDonut(rows, { labelKey, colorMap, labelMap, center = null } = {}) {
    const total = rows.reduce((s, r) => s + r.total, 0);
    if (total === 0) return '<p style="text-align:center;color:#94a3b8;padding:40px 0;font-size:13px;">No data yet.</p>';

    const cx = 100, cy = 100, r = 72, strokeW = 28;
    const circumference = 2 * Math.PI * r;
    let offset = 0;

    const activeRows = rows.filter(row => row.total > 0);
    // 2px surface gap between segments (viewBox units ≈ px at the 170px render size).
    const gap = activeRows.length > 1 ? 2.4 : 0;
    const segments = activeRows.map(row => {
      const key = row[labelKey];
      const frac  = row.total / total;
      const pct   = row.pct !== undefined ? row.pct : Math.round(frac * 1000) / 10;
      const full  = frac * circumference;
      const dash  = Math.max(0.5, full - gap);
      const color = (colorMap && colorMap[key]) || '#94a3b8';
      const label = (labelMap && labelMap[key]) || key;
      const ttHtml = `<div style="font-weight:700;color:#0f172a;margin-bottom:4px;">${esc(label)}</div>`
        + swatchHtml(color, row.total + ' submissions', pct + '%');
      const seg = `<circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${color}"
        stroke-width="${strokeW}" stroke-dasharray="${dash} ${circumference - dash}"
        stroke-dashoffset="${-offset}" transform="rotate(-90 ${cx} ${cy})" stroke-linecap="butt"
        class="donut-seg" ${tt(ttHtml)} style="transition:opacity .2s;cursor:default;">
        <animate attributeName="stroke-dashoffset" from="${circumference}" to="${-offset}" dur=".9s" fill="freeze" begin="${(offset / circumference * 0.6).toFixed(2)}s"/>
      </circle>`;
      offset += full;
      return seg;
    }).join('');

    let centerValue, centerText;
    if (center) {
      centerValue = center.value;
      centerText = center.label;
    } else {
      const dominant = rows.reduce((a, b) => (b.total > a.total ? b : a), rows[0]);
      centerValue = (dominant.pct !== undefined ? dominant.pct : Math.round((dominant.total / total) * 1000) / 10) + '%';
      centerText = (labelMap && labelMap[dominant[labelKey]]) || dominant[labelKey];
    }

    const legend = rows.map(row => {
      const key = row[labelKey];
      const color = (colorMap && colorMap[key]) || '#94a3b8';
      const label = (labelMap && labelMap[key]) || key;
      const pct   = row.pct !== undefined ? row.pct : (total > 0 ? Math.round(row.total / total * 1000) / 10 : 0);
      return `<div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#475569;">
        <span style="width:8px;height:8px;border-radius:2px;background:${color};flex-shrink:0;"></span>
        <span>${esc(label)}</span>
        <span style="margin-left:auto;font-weight:600;color:#0f172a;">${pct}%</span>
        <span style="color:#94a3b8;">${row.total}</span>
      </div>`;
    }).join('');

    return `<div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
      <div style="flex-shrink:0;">
        <svg viewBox="0 0 200 200" style="width:170px;height:170px;" role="img" aria-label="${esc(rows.map(row => ((labelMap && labelMap[row[labelKey]]) || row[labelKey]) + ' ' + row.total).join(', '))}">
          <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="#f1f5f9" stroke-width="${strokeW}"/>
          ${segments}
          <text x="${cx}" y="${cy - 4}" text-anchor="middle" font-size="24" font-weight="700" fill="#0f172a">${esc(centerValue)}</text>
          <text x="${cx}" y="${cy + 16}" text-anchor="middle" font-size="11" fill="#64748b">${esc(centerText)}</text>
        </svg>
      </div>
      <div style="flex:1;min-width:140px;display:flex;flex-direction:column;gap:8px;">${legend}</div>
    </div>`;
  }

  /* -------------------------------------------------------------------------
   * Horizontal bar (Top Citizen Concerns) — with category icon badges
   * ---------------------------------------------------------------------- */
  function renderCategoryBars(rows) {
    if (!rows || !rows.length) return '<p style="text-align:center;color:#94a3b8;padding:40px 0;font-size:13px;">No data yet.</p>';
    const total  = rows.reduce((s, r) => s + r.total, 0);
    const maxVal = Math.max(1, ...rows.map(r => r.total));

    return `<div style="display:flex;flex-direction:column;gap:14px;">
      ${rows.map(r => {
        const pct   = Math.round((r.total / maxVal) * 100);
        const share = total > 0 ? Math.round(r.total / total * 1000) / 10 : 0;
        const ttHtml = `<div style="font-weight:700;color:#0f172a;margin-bottom:3px;">${esc(r.category)}</div>`
          + swatchHtml(CONCERN_COLOR, r.total + ' submissions', share + '% of total');
        return `<div style="display:flex;align-items:center;gap:10px;" ${tt(ttHtml)}>
          ${badgeHtml(r.category, 28)}
          <div style="flex:1;min-width:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
              <span style="font-size:12px;color:#334155;font-weight:600;">${esc(r.category)}</span>
              <span style="font-size:11px;color:#64748b;"><strong style="color:#0f172a;">${share}%</strong> &middot; ${r.total}</span>
            </div>
            <div style="height:8px;background:#f1f5f9;border-radius:99px;overflow:hidden;">
              <div class="hbar-fill" style="height:100%;width:0%;background:${CONCERN_COLOR};border-radius:99px;transition:width .8s cubic-bezier(.4,0,.2,1);" data-pct="${pct}"></div>
            </div>
          </div>
        </div>`;
      }).join('')}
    </div>`;
  }

  /* -------------------------------------------------------------------------
   * Proposal & Suggestion Outcomes — headline stats, process bar, status list
   * ---------------------------------------------------------------------- */
  function renderProposalOutcomes(stats) {
    if (!stats || !stats.total) return '<p style="text-align:center;color:#94a3b8;padding:40px 0;font-size:13px;">No proposals in this period.</p>';

    const decided  = stats.approved + stats.implemented + stats.rejected;
    const accepted = stats.approved + stats.implemented;
    const pctOf = (v, of) => (of > 0 ? Math.round((v / of) * 100) : 0);

    // Headlines summarize the flow; the per-status counts live in the list below.
    const heroes = [
      { label: 'Total proposals', value: stats.total, note: 'in this period' },
      { label: 'Decided', value: decided, note: `${pctOf(decided, stats.total)}% of proposals` },
      {
        label: 'Approval rate',
        value: decided ? pctOf(accepted, decided) + '%' : '—',
        note: decided ? `${accepted} approved of ${decided} decided` : 'none decided yet',
      },
    ];
    const heroHtml = `<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;">
      ${heroes.map((h, i) => `<div style="${i ? 'padding-left:12px;border-left:1px solid #f1f5f9;' : ''}min-width:0;">
        <p style="font-size:11px;color:#64748b;margin:0 0 2px;">${esc(h.label)}</p>
        <p style="font-size:22px;font-weight:700;color:#0f172a;margin:0;line-height:1.1;">${esc(h.value)}</p>
        <p style="font-size:11px;color:#94a3b8;margin:3px 0 0;">${esc(h.note)}</p>
      </div>`).join('')}
    </div>`;

    const parts = [
      { label: 'Under review', value: stats.under_review, color: '#f59e0b', desc: 'Waiting for a decision' },
      { label: 'Approved',     value: stats.approved,     color: '#10b981', desc: 'Accepted, not yet carried out' },
      { label: 'Implemented',  value: stats.implemented,  color: '#0d9488', desc: 'Accepted and carried out' },
      { label: 'Rejected',     value: stats.rejected,     color: '#ef4444', desc: 'Not moving forward' },
    ];
    const share = v => pctOf(v, stats.total);

    const bar = parts.filter(p => p.value > 0).map(p => {
      const ttHtml = `<div style="font-weight:700;color:#0f172a;margin-bottom:3px;">${p.label}</div>` + swatchHtml(p.color, p.value + ' proposals', share(p.value) + '%');
      return `<div ${tt(ttHtml)} style="flex:${p.value} 1 0;min-width:4px;background:${p.color};height:100%;"></div>`;
    }).join('');

    // Zero rows stay listed (muted) so every status is always accounted for.
    const row = p => {
      const zero = p.value === 0;
      return `<div style="display:flex;align-items:center;gap:10px;padding:7px 0;">
        <span style="width:10px;height:10px;border-radius:3px;background:${p.color};flex-shrink:0;${zero ? 'opacity:.35;' : ''}"></span>
        <div style="flex:1;min-width:0;">
          <p style="margin:0;font-size:12px;font-weight:600;color:${zero ? '#94a3b8' : '#334155'};">${p.label}</p>
          <p style="margin:1px 0 0;font-size:11px;color:#94a3b8;">${p.desc}</p>
        </div>
        <span style="font-size:14px;font-weight:700;color:${zero ? '#cbd5e1' : '#0f172a'};min-width:24px;text-align:right;">${p.value}</span>
        <span style="font-size:11px;color:#94a3b8;min-width:34px;text-align:right;">${share(p.value)}%</span>
      </div>`;
    };
    const groupLabel = (text, count) => `<p style="margin:0;padding-top:4px;font-size:10px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;">
        ${text} <span style="color:#cbd5e1;">&middot;</span> ${count}</p>`;

    // Full-width card: summary on the left, status list on the right; stacks when narrow.
    return `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px 32px;align-items:start;">
      <div style="display:flex;flex-direction:column;gap:16px;">
        ${heroHtml}
        <div role="img" aria-label="${parts.map(p => `${p.label} ${p.value}`).join(', ')}"
             style="display:flex;gap:2px;height:10px;border-radius:99px;overflow:hidden;background:#f1f5f9;">${bar}</div>
      </div>
      <div>
        ${groupLabel('Pending', stats.under_review)}
        ${row(parts[0])}
        <div style="border-top:1px solid #f1f5f9;margin-top:4px;"></div>
        ${groupLabel('Decided', decided)}
        ${parts.slice(1).map(row).join('')}
      </div>
    </div>`;
  }

  /* -------------------------------------------------------------------------
   * Animate horizontal bars after render
   * ---------------------------------------------------------------------- */
  function animateBars(container) {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        container.querySelectorAll('.hbar-fill').forEach(el => {
          el.style.width = (el.dataset.pct || 0) + '%';
        });
      });
    });
  }

  /* -------------------------------------------------------------------------
   * Chart card shell
   * ---------------------------------------------------------------------- */
  function card(title, iconName, accentColor, body) {
    return `<div class="card" style="padding:20px;display:flex;flex-direction:column;gap:16px;animation:analytics-fadein .4s ease both;">
      <h2 style="margin:0;font-size:13px;font-weight:600;color:#0f172a;display:flex;align-items:center;gap:8px;">
        <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:6px;
             background:${accentColor}18;color:${accentColor};">
          ${icon(iconName)}
        </span>
        ${title}
      </h2>
      <div style="flex:1;">${body}</div>
    </div>`;
  }

  /* -------------------------------------------------------------------------
   * Main render
   * ---------------------------------------------------------------------- */
  function render(d) {
    if (!document.getElementById('analytics-styles')) {
      const style = document.createElement('style');
      style.id = 'analytics-styles';
      style.textContent = `
        @keyframes analytics-fadein {
          from { opacity:0; transform:translateY(10px); }
          to   { opacity:1; transform:translateY(0); }
        }
        .donut-seg:hover { opacity: .8; }
      `;
      document.head.appendChild(style);
    }


    root.innerHTML = `
      ${renderFilterBar(d.filters, d.filter_options)}
      ${renderKpis(d.kpis, d.filters)}

      <div class="an-main-row" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:20px;margin-bottom:20px;align-items:stretch;">
        ${card('Submissions Over Time', 'trend', '#3b82f6', renderOverTimeSummary(d.over_time_summary) + renderSubmissionsOverTime(d.submissions_over_time) + renderOverTimeTable(d.submissions_over_time, d.over_time_unit))}
        <div class="an-donut-stack" style="display:flex;flex-direction:column;gap:20px;">
          ${card('Submission Distribution', 'donut', '#8b5cf6', renderDonut(d.by_type, { labelKey: 'type', colorMap: TYPE_COLORS, labelMap: TYPE_LABELS, center: { value: d.by_type.reduce((s, r) => s + r.total, 0), label: 'submissions' } }))}
          ${card('Citizen Sentiment', 'heart', '#ec4899', renderDonut(d.sentiment, { labelKey: 'sentiment', colorMap: SENTIMENT_COLORS }))}
        </div>
      </div>

      <div style="margin-bottom:20px;">
        ${card('Top Citizen Concerns / Categories', 'bars', '#f59e0b', renderCategoryBars(d.by_category))}
      </div>

      <div style="margin-bottom:20px;">
        ${card('Proposal & Suggestion Outcomes', 'lightbulb', '#a78bfa', renderProposalOutcomes(d.proposal_outcomes))}
      </div>

    `;

    attachTooltips(root);
    animateBars(root);
    wireOverTimeCrosshair(root.querySelector('.ot-svg'), d.submissions_over_time, d.over_time_unit);
    wireFilterBar();
  }

  /* -------------------------------------------------------------------------
   * Loading skeleton — same grid as render(), so the page keeps its shape
   * ---------------------------------------------------------------------- */
  function renderSkeleton() {
    const bar = (w, h = 12, extra = '') => `<div class="ls-skeleton" style="width:${w};height:${h}px;${extra}"></div>`;
    const skCard = (body, extra = '') => `<div class="card" style="padding:20px;display:flex;flex-direction:column;gap:16px;${extra}">
        <div style="display:flex;align-items:center;gap:8px;">${bar('26px', 26, 'border-radius:6px;')}${bar('40%', 13)}</div>
        <div style="flex:1;">${body}</div>
      </div>`;
    const donut = `<div style="display:flex;align-items:center;gap:20px;">
        ${bar('120px', 120, 'border-radius:50%;flex-shrink:0;')}
        <div style="flex:1;display:flex;flex-direction:column;gap:10px;">${[80, 65, 70].map(w => bar(w + '%')).join('')}</div>
      </div>`;
    const kpi = `<div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:12px;">
        ${bar('40px', 40, 'border-radius:10px;flex-shrink:0;')}
        <div style="flex:1;display:flex;flex-direction:column;gap:8px;">${bar('70%', 10)}${bar('45%', 18)}</div>
      </div>`;

    return `<div aria-busy="true"><span class="sr-only">Loading analytics…</span><div aria-hidden="true">
      <div class="card" style="padding:14px 16px;margin-bottom:20px;display:flex;flex-wrap:wrap;gap:10px;">
        ${['140px', '120px', '130px', '120px'].map(w => bar(w, 34, 'border-radius:6px;')).join('')}
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:24px;">
        ${kpi.repeat(6)}
      </div>
      <div class="an-main-row" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:20px;margin-bottom:20px;align-items:stretch;">
        ${skCard(bar('100%', 260, 'border-radius:8px;'))}
        <div class="an-donut-stack" style="display:flex;flex-direction:column;gap:20px;">
          ${skCard(donut)}
          ${skCard(donut)}
        </div>
      </div>
      <div style="margin-bottom:20px;">
        ${skCard(`<div style="display:flex;flex-direction:column;gap:12px;">${[90, 75, 60, 45, 30].map(w => bar(w + '%', 14)).join('')}</div>`)}
      </div>
      <div style="margin-bottom:20px;">
        ${skCard(`<div style="display:flex;flex-direction:column;gap:12px;">${[85, 70, 55].map(w => bar(w + '%', 14)).join('')}</div>`)}
      </div>
    </div></div>`;
  }

  /* -------------------------------------------------------------------------
   * Load
   * ---------------------------------------------------------------------- */
  async function load(filters) {
    const qs = new URLSearchParams();
    if (filters) {
      qs.set('range', filters.range);
      if (filters.range === 'custom') {
        if (filters.from) qs.set('from', filters.from);
        if (filters.to) qs.set('to', filters.to);
      }
      if (filters.type && filters.type !== 'all') qs.set('type', filters.type);
      if (filters.category && filters.category !== 'all') qs.set('category', filters.category);
      if (filters.sentiment && filters.sentiment !== 'all') qs.set('sentiment', filters.sentiment);
    }

    root.innerHTML = renderSkeleton();
    setExportEnabled(false);

    const qsStr = qs.toString();
    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/analytics/summary${qsStr ? '?' + qsStr : ''}`);

    if (!result.success) {
      root.innerHTML = `
        <div class="card" style="padding:40px;text-align:center;">
          <p style="font-size:14px;font-weight:600;color:#0f172a;margin-bottom:6px;">Couldn't load analytics.</p>
          <p style="font-size:12px;color:#64748b;margin-bottom:20px;">${esc(result.message || 'Something went wrong. Please try again.')}</p>
          <button id="analytics-retry" type="button"
            style="font-size:12px;font-weight:600;color:#fff;background:#2563eb;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;">
            Try Again
          </button>
        </div>`;
      document.getElementById('analytics-retry')?.addEventListener('click', () => load(filters));
      return;
    }

    render(result.data);
    lastData = result.data;
    lastQs = qsStr;
    setExportEnabled(true);
  }

  /* -------------------------------------------------------------------------
   * Export PDF / Print — the page as it looks, in a password-protected PDF
   * (CivicConnect.protectedPdf, frontend/js/app.js). The page itself never
   * prints (see the print CSS in admin/analytics.blade.php), since a browser print or
   * "Save as PDF" of the page would be an unprotected copy.
   * ---------------------------------------------------------------------- */
  let lastData = null;
  let lastQs = '';
  const exportBtn = document.getElementById('analytics-export-btn');
  const printBtn = document.getElementById('analytics-print-btn');

  function setExportEnabled(on) {
    [exportBtn, printBtn].forEach((btn) => {
      if (!btn) return;
      btn.disabled = !on;
      btn.style.opacity = on ? '1' : '.5';
      btn.style.cursor = on ? 'pointer' : 'not-allowed';
    });
  }

  async function getProtectedPdf(forPrint) {
    if (!lastData) return;
    setExportEnabled(false);
    try {
      // Export = a table report the server builds from these filters;
      // Print = the page as it looks, charts included.
      await CivicConnect.protectedPdf({
        root,
        endpoint: `${window.APP_URL}/api/analytics/pdf`,
        forPrint,
        body: { filters: lastData.filters },
        capture: forPrint,
      });
    } finally {
      setExportEnabled(true);
    }
  }

  exportBtn?.addEventListener('click', () => getProtectedPdf(false));
  printBtn?.addEventListener('click', () => getProtectedPdf(true));

  setExportEnabled(false);
  load();
})();
