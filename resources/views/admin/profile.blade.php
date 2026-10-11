{{-- VIEW: Profile Settings
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle, $account, $canEditPosition,
     $twoFactorOn, $twoFactorLocked --}}
@include('partials.admin-header')
<?php
$roleBadge = ['super_admin' => 'badge-blue', 'admin' => 'badge-teal', 'staff' => 'badge-slate'];
// Same rule as the sidebar: first letter of the first and last word of the name.
$nameParts = explode(' ', trim($staff['full_name']));
$profileInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr(end($nameParts), 0, 1));
$inputClass = 'w-full border border-borderc rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal';
?>

<div class="max-w-3xl">
  <!-- Who you are: updates live while editing below -->
  <div class="card p-6 mb-5 flex flex-col sm:flex-row sm:items-center gap-4">
    <span id="profile-avatar" class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold text-white flex-shrink-0" style="background:#2563eb" aria-hidden="true">{{ $profileInitials ?: 'ST' }}</span>
    <div class="min-w-0 flex-1">
      <p id="profile-preview-name" class="text-lg font-semibold text-navy truncate">{{ $staff['full_name'] }}</p>
      <p id="profile-preview-sub" class="text-sm text-slate truncate">{{ ($staff['title'] ?? '') ?: role_label($staff['role']) }}</p>
      <div class="flex flex-wrap items-center gap-2 mt-2">
        <span class="badge {{ $roleBadge[$staff['role']] ?? 'badge-slate' }}">{{ role_label($staff['role']) }}</span>
        <span class="text-xs text-slate truncate">{{ $staff['email'] }}</span>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
    <!-- Editable -->
    <form id="profile-form" class="card p-6 lg:col-span-3" novalidate>
      <h2 class="text-sm font-semibold text-navy">Personal information</h2>
      <p class="text-xs text-slate mt-0.5 mb-5">Shown in the sidebar and on your activity in the Audit Log.</p>

      <div class="space-y-4">
        <div>
          <label for="profile-name" class="block text-xs font-medium text-navy mb-1">Full name</label>
          <input id="profile-name" type="text" required maxlength="150" autocomplete="name" value="{{ $staff['full_name'] }}" class="{!! $inputClass !!}">
          <p id="profile-name-error" class="hidden text-[11px] text-red-600 mt-1">Please enter your full name.</p>
        </div>
        <div>
          <label for="profile-title" class="block text-xs font-medium text-navy mb-1">Position</label>
          @if ($canEditPosition)
            <input id="profile-title" type="text" maxlength="120" value="{{ $staff['title'] ?? '' }}" placeholder="e.g. Legislative Staff Officer" class="{!! $inputClass !!}">
          @else
            <input id="profile-title" type="text" value="{{ $staff['title'] ?? '' }}" placeholder="Not set" readonly class="w-full border border-borderc rounded-lg px-3 py-2.5 text-sm bg-pagebg text-slate cursor-default">
            <p class="text-[11px] text-slate mt-1">Only a super admin can change your position.</p>
          @endif
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 mt-6 pt-4 border-t border-borderc">
        <span id="profile-dirty" class="hidden text-xs text-amber mr-auto">Unsaved changes</span>
        <button type="button" id="profile-cancel-btn" class="hidden text-sm font-medium py-2 px-4 rounded-lg border border-borderc text-navy hover:bg-pagebg transition">Cancel</button>
        <button type="submit" id="profile-save-btn" class="bg-teal text-white text-sm font-semibold py-2 px-5 rounded-lg hover:bg-teal/90 transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>Save changes</button>
      </div>
    </form>

    <!-- Read-only -->
    <div class="card p-6 lg:col-span-2">
      <h2 class="text-sm font-semibold text-navy">Sign-in &amp; account</h2>
      <p class="text-xs text-slate mt-0.5 mb-5">{!! $staff['role'] === 'super_admin' ? "Super admin email and role can't be changed in the app." : 'Managed by a super admin.' !!}</p>
      <dl class="space-y-4 text-sm">
        <div>
          <dt class="text-xs text-slate">Email</dt>
          <dd class="text-navy font-medium break-all">{{ $staff['email'] }}</dd>
          <dd class="text-[11px] text-slate mt-0.5">Your sign-in address, where login codes are sent.</dd>
        </div>
        <div>
          <dt class="text-xs text-slate">Role</dt>
          <dd class="text-navy font-medium">{{ role_label($staff['role']) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate">Last sign-in</dt>
          <dd class="text-navy font-medium">{!! !empty($account['last_login_at']) ? e(date('M j, Y · g:i A', strtotime($account['last_login_at']))) : 'Never' !!}</dd>
        </div>
        <div>
          <dt class="text-xs text-slate">Member since</dt>
          <dd class="text-navy font-medium">{!! !empty($account['created_at']) ? e(date('M j, Y', strtotime($account['created_at']))) : '—' !!}</dd>
        </div>
      </dl>
    </div>
  </div>

  <!-- Two-factor sign-in -->
  <section id="tfa-card" class="card p-6 mt-5" data-on="{!! $twoFactorOn ? '1' : '0' !!}">
    <div class="flex items-start gap-4">
      <span class="w-10 h-10 rounded-lg bg-teal/10 text-teal flex items-center justify-center flex-shrink-0" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
      </span>
      <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-sm font-semibold text-navy">Two-factor sign-in</h2>
          <span id="tfa-badge" class="badge {!! $twoFactorOn ? 'badge-green' : 'badge-amber' !!}">{!! $twoFactorOn ? 'On' : 'Off' !!}</span>
        </div>
        <p class="text-xs text-slate mt-1">When on, signing in needs your password <strong class="text-navy font-medium">and</strong> a 6-digit code sent to {{ $staff['email'] }}. Someone who learns your password still can't get in without your email.</p>
        @if ($twoFactorLocked)
          <p class="text-[11px] text-slate mt-2">Always on for super admins, since this account can manage every other account.</p>
        @else
          <p id="tfa-off-warning" class="{!! $twoFactorOn ? 'hidden ' : '' !!}text-[11px] text-amber mt-2">Your password alone signs you in right now. We recommend turning this back on.</p>
        @endif
      </div>
      <button type="button" id="tfa-switch" role="switch" aria-checked="{!! $twoFactorOn ? 'true' : 'false' !!}" aria-label="Two-factor sign-in"
        {!! $twoFactorLocked ? 'disabled title="Always on for super admins"' : '' !!}
        class="tfa-switch flex-shrink-0"><span></span></button>
    </div>

    @if (!$twoFactorLocked)
    <!-- Turning it off asks for the password first -->
    <form id="tfa-confirm" class="hidden mt-5 pt-4 border-t border-borderc" novalidate>
      <label for="tfa-password" class="block text-xs font-medium text-navy mb-1">Enter your password to turn off two-factor sign-in</label>
      <div class="flex flex-col sm:flex-row gap-2">
        <input id="tfa-password" type="password" autocomplete="current-password" class="{!! $inputClass !!} sm:max-w-xs">
        <div class="flex gap-2">
          <button type="submit" id="tfa-confirm-btn" class="text-sm font-semibold py-2 px-4 rounded-lg bg-red-600 text-white hover:bg-red-700 transition">Turn off</button>
          <button type="button" id="tfa-cancel-btn" class="text-sm font-medium py-2 px-4 rounded-lg border border-borderc text-navy hover:bg-pagebg transition">Cancel</button>
        </div>
      </div>
    </form>
    @endif
  </section>
</div>

<style>
  .tfa-switch { position: relative; width: 44px; height: 24px; border-radius: 999px; background: #cbd5e1; border: none; cursor: pointer; transition: background .2s; }
  .tfa-switch span { position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .2s; }
  .tfa-switch[aria-checked="true"] { background: #0d9488; }
  .tfa-switch[aria-checked="true"] span { transform: translateX(20px); }
  .tfa-switch:disabled { opacity: .55; cursor: not-allowed; }
  .tfa-switch:focus-visible { outline: 2px solid #0d9488; outline-offset: 2px; }
</style>

<script>window.PROFILE_ROLE_LABEL = {!! json_encode(role_label($staff['role']), JSON_HEX_TAG | JSON_HEX_AMP) !!};</script>
@include('partials.admin-footer')
<script src="{{ asset_v('frontend/js/profile.js') }}"></script>
