{{-- VIEW: User Accounts
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle, $accounts --}}
@include('partials.admin-header')
<?php
$roleBadge = ['super_admin' => 'badge-blue', 'admin' => 'badge-teal'];
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
  <div>
    <h1 class="text-xl font-semibold text-navy">User Accounts</h1>
    <p class="text-sm text-slate mt-1">Create logins for LGU staff. Only super admins can see this page.</p>
  </div>
  <button type="button" id="staff-add-btn" class="bg-teal text-white text-sm font-semibold py-2.5 px-5 rounded hover:bg-teal/90 transition">+ Add Account</button>
</div>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-pagebg text-[11px] uppercase tracking-wide text-slate">
        <tr>
          <th class="text-left px-4 py-3 font-medium">Name</th>
          <th class="text-left px-4 py-3 font-medium">Email</th>
          <th class="text-left px-4 py-3 font-medium">Role</th>
          <th class="text-left px-4 py-3 font-medium">Status</th>
          <th class="text-left px-4 py-3 font-medium">Last Login</th>
          <th class="text-right px-4 py-3 font-medium">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-borderc">
        @foreach ($accounts as $a)
        <tr class="{!! $a['is_active'] ? '' : 'opacity-60' !!}">
          <td class="px-4 py-3">
            <p class="font-medium text-navy">{{ $a['full_name'] }}</p>
            @if (!empty($a['title']))<p class="text-xs text-slate">{{ $a['title'] }}</p>@endif
          </td>
          <td class="px-4 py-3 text-slate">{{ $a['email'] }}</td>
          <td class="px-4 py-3"><span class="badge {{ $roleBadge[$a['role']] ?? 'badge-slate' }}">{{ role_label($a['role']) }}</span></td>
          <td class="px-4 py-3">
            @if (!$a['is_active'])
              <span class="badge badge-red">Inactive</span>
            @elseif ($a['must_change_password'])
              <span class="badge badge-amber">Awaiting first login</span>
            @else
              <span class="badge badge-green">Active</span>
            @endif
            @if ($a['is_active'] && !two_factor_required($a))
              <span class="badge badge-amber ml-1" title="Signs in with a password only (turned off in their Profile Settings)">2FA off</span>
            @endif
          </td>
          <td class="px-4 py-3 text-slate whitespace-nowrap">{!! $a['last_login_at'] ? e(time_ago($a['last_login_at'])) : 'Never' !!}</td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            @if ($a['role'] !== 'super_admin' && (int) $a['id'] !== (int) $staff['id'])
              <div class="inline-flex items-center gap-1.5">
                <button type="button" data-edit="{!! (int) $a['id'] !!}" data-name="{{ $a['full_name'] }}" data-email="{{ $a['email'] }}"
                  data-title="{{ $a['title'] ?? '' }}" data-role="{{ role_label($a['role']) }}"
                  class="text-[11px] font-medium py-1 px-2.5 rounded-md border border-borderc text-navy hover:bg-pagebg transition">
                  Edit
                </button>
                @if ($a['is_active'] && $a['must_change_password'])
                <button type="button" data-resend="{!! (int) $a['id'] !!}" data-name="{{ $a['full_name'] }}" data-email="{{ $a['email'] }}"
                  class="text-[11px] font-medium py-1 px-2.5 rounded-md border border-borderc text-navy hover:bg-pagebg transition">
                  Resend login
                </button>
                @endif
                <button type="button" data-set-active="{!! (int) $a['id'] !!}" data-active="{!! $a['is_active'] ? '0' : '1' !!}" data-name="{{ $a['full_name'] }}"
                  class="text-[11px] font-medium py-1 px-2.5 rounded-md border border-borderc text-slate hover:bg-pagebg hover:text-navy transition">
                  {!! $a['is_active'] ? 'Deactivate' : 'Reactivate' !!}
                </button>
                <button type="button" data-delete="{!! (int) $a['id'] !!}" data-name="{{ $a['full_name'] }}"
                  class="text-[11px] font-medium py-1 px-2.5 rounded-md border border-red-200 text-red-600 hover:bg-red-50 transition">
                  Delete
                </button>
              </div>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div id="staff-modal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4">
  <div class="bg-white rounded max-w-md w-full max-h-[90vh] overflow-y-auto p-6">

    <form id="staff-form" class="space-y-4">
      <h2 class="text-base font-semibold text-navy">Add User Account</h2>
      <div>
        <label for="staff-name" class="block text-xs font-medium text-navy mb-1">Full name</label>
        <input id="staff-name" type="text" required maxlength="150" class="w-full border border-borderc rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label for="staff-email" class="block text-xs font-medium text-navy mb-1">Email</label>
        <input id="staff-email" type="email" required maxlength="190" class="w-full border border-borderc rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label for="staff-role" class="block text-xs font-medium text-navy mb-1">Role</label>
        <input id="staff-role" type="text" value="Admin" readonly class="w-full border border-borderc rounded px-3 py-2 text-sm bg-pagebg text-slate cursor-default">
      </div>
      <div>
        <label for="staff-title" class="block text-xs font-medium text-navy mb-1">Position</label>
        <input id="staff-title" type="text" maxlength="120" placeholder="e.g. Legislative Staff Officer" class="w-full border border-borderc rounded px-3 py-2 text-sm">
      </div>
      <p class="text-xs text-slate">A temporary password is emailed to this address. The person must change it the first time they log in.</p>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" data-close class="text-sm font-medium py-2 px-4 rounded border border-borderc text-navy">Cancel</button>
        <button type="submit" id="staff-save-btn" class="bg-teal text-white text-sm font-semibold py-2 px-5 rounded hover:bg-teal/90 transition">Create Account</button>
      </div>
    </form>

    <div id="staff-created" class="hidden space-y-4">
      <h2 id="created-title" class="text-base font-semibold text-navy">Account created</h2>
      <!-- Emailed: just confirm where it went. -->
      <p id="created-sent" class="text-sm text-slate">The login details were emailed to <strong id="created-sent-email" class="text-navy break-all"></strong>. They'll choose their own password the first time they log in.</p>
      <!-- Email failed: fall back to showing the password once. -->
      <div id="created-manual" class="space-y-4">
        <div class="rounded border border-amber/40 bg-amber/10 p-3 text-xs text-navy">The email could not be sent. Give these login details to the person yourself. <strong>The password is shown only once</strong>, so copy it now.</div>
        <div class="rounded border border-borderc bg-pagebg p-3 text-sm space-y-1">
          <p><span class="text-slate">Email:</span> <span id="created-email" class="font-medium text-navy break-all"></span></p>
          <p><span class="text-slate">Temporary password:</span> <span id="created-password" class="font-mono font-semibold text-navy"></span></p>
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" id="staff-copy-btn" class="text-sm font-medium py-2 px-4 rounded border border-borderc text-navy">Copy</button>
        <button type="button" id="staff-done-btn" class="bg-teal text-white text-sm font-semibold py-2 px-5 rounded hover:bg-teal/90 transition">Done</button>
      </div>
    </div>

  </div>
</div>

<div id="staff-edit-modal" class="hidden fixed inset-0 z-50 modal-backdrop flex items-center justify-center p-4">
  <div class="bg-white rounded max-w-md w-full max-h-[90vh] overflow-y-auto p-6">
    <form id="staff-edit-form" class="space-y-4">
      <h2 class="text-base font-semibold text-navy">Edit User Account</h2>
      <input type="hidden" id="edit-id">
      <div>
        <label for="edit-name" class="block text-xs font-medium text-navy mb-1">Full name</label>
        <input id="edit-name" type="text" required maxlength="150" class="w-full border border-borderc rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label for="edit-email" class="block text-xs font-medium text-navy mb-1">Email</label>
        <input id="edit-email" type="email" required maxlength="190" class="w-full border border-borderc rounded px-3 py-2 text-sm">
        <p id="edit-email-note" class="hidden text-[11px] text-amber mt-1">They'll sign in and get login codes at the new address from now on.</p>
      </div>
      <div>
        <label for="edit-role" class="block text-xs font-medium text-navy mb-1">Role</label>
        <input id="edit-role" type="text" readonly class="w-full border border-borderc rounded px-3 py-2 text-sm bg-pagebg text-slate cursor-default">
      </div>
      <div>
        <label for="edit-title" class="block text-xs font-medium text-navy mb-1">Position</label>
        <input id="edit-title" type="text" maxlength="120" placeholder="e.g. Legislative Staff Officer" class="w-full border border-borderc rounded px-3 py-2 text-sm">
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" data-close class="text-sm font-medium py-2 px-4 rounded border border-borderc text-navy">Cancel</button>
        <button type="submit" id="edit-save-btn" class="bg-teal text-white text-sm font-semibold py-2 px-5 rounded hover:bg-teal/90 transition">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
(() => {
  const modal = document.getElementById('staff-modal');
  const form = document.getElementById('staff-form');
  const created = document.getElementById('staff-created');

  document.getElementById('staff-add-btn').addEventListener('click', () => {
    form.reset();
    form.classList.remove('hidden');
    created.classList.add('hidden');
    modal.classList.remove('hidden');
    document.getElementById('staff-name').focus();
  });
  modal.querySelector('[data-close]').addEventListener('click', () => modal.classList.add('hidden'));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('staff-save-btn');
    btn.disabled = true;
    btn.textContent = 'Creating…';

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/staff/create`, {
      method: 'POST',
      body: JSON.stringify({
        full_name: document.getElementById('staff-name').value,
        email: document.getElementById('staff-email').value,
        role: 'admin',
        title: document.getElementById('staff-title').value,
      }),
    });

    btn.disabled = false;
    btn.textContent = 'Create Account';

    if (result.success) showResult(result.data, 'Account created', 'Account created — email not sent');
  });

  // Result panel for a new account or resent login details: confirm the email,
  // or (if it couldn't be sent) show the password once to pass on by hand.
  function showResult(data, sentTitle, failedTitle) {
    document.getElementById('created-title').textContent = data.emailed ? sentTitle : failedTitle;
    document.getElementById('created-sent-email').textContent = data.email;
    document.getElementById('created-email').textContent = data.email;
    document.getElementById('created-password').textContent = data.temp_password || '';
    document.getElementById('created-sent').classList.toggle('hidden', !data.emailed);
    document.getElementById('created-manual').classList.toggle('hidden', data.emailed);
    document.getElementById('staff-copy-btn').classList.toggle('hidden', data.emailed);
    form.classList.add('hidden');
    created.classList.remove('hidden');
    modal.classList.remove('hidden');
  }

  document.querySelectorAll('[data-resend]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const ok = await CivicConnect.confirmModal({
        title: 'Resend login details?',
        message: `A new temporary password will be emailed to ${btn.dataset.name} (${btn.dataset.email}). The password in any earlier email will stop working.`,
        confirmText: 'Send',
      });
      if (!ok) return;

      btn.disabled = true;
      const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/staff/resend-invite`, {
        method: 'POST',
        body: JSON.stringify({ id: Number(btn.dataset.resend) }),
      });
      btn.disabled = false;
      if (result.success) showResult(result.data, 'Login details sent', 'New password made — email not sent');
    });
  });

  document.getElementById('staff-copy-btn').addEventListener('click', async () => {
    const text = `Email: ${document.getElementById('created-email').textContent}\nTemporary password: ${document.getElementById('created-password').textContent}`;
    try {
      await navigator.clipboard.writeText(text);
      CivicConnect.toast('Copied.', 'success');
    } catch (err) {
      CivicConnect.toast('Copy failed. Please select and copy the password manually.', 'error');
    }
  });

  // ---- Edit an account (name, email, position) ----
  const editModal = document.getElementById('staff-edit-modal');
  const editForm = document.getElementById('staff-edit-form');
  const editEmail = document.getElementById('edit-email');
  const editEmailNote = document.getElementById('edit-email-note');
  let originalEmail = '';

  document.querySelectorAll('[data-edit]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.getElementById('edit-id').value = btn.dataset.edit;
      document.getElementById('edit-name').value = btn.dataset.name;
      editEmail.value = originalEmail = btn.dataset.email;
      document.getElementById('edit-role').value = btn.dataset.role;
      document.getElementById('edit-title').value = btn.dataset.title;
      editEmailNote.classList.add('hidden');
      editModal.classList.remove('hidden');
      document.getElementById('edit-name').focus();
    });
  });
  editModal.querySelector('[data-close]').addEventListener('click', () => editModal.classList.add('hidden'));
  editEmail.addEventListener('input', () => {
    editEmailNote.classList.toggle('hidden', editEmail.value.trim().toLowerCase() === originalEmail.toLowerCase());
  });

  editForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('edit-save-btn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/staff/update`, {
      method: 'POST',
      body: JSON.stringify({
        id: Number(document.getElementById('edit-id').value),
        full_name: document.getElementById('edit-name').value,
        email: editEmail.value,
        title: document.getElementById('edit-title').value,
      }),
    });

    btn.disabled = false;
    btn.textContent = 'Save Changes';
    if (result.success) location.reload();
  });

  document.querySelectorAll('[data-set-active]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const activate = btn.dataset.active === '1';
      const ok = await CivicConnect.confirmModal(activate ? {
        title: 'Reactivate account?',
        message: `${btn.dataset.name} will be able to log in again with their existing password.`,
        confirmText: 'Reactivate',
      } : {
        title: 'Deactivate account?',
        message: `${btn.dataset.name} will be logged out and can no longer log in. Their past responses and activity stay on record. You can reactivate the account later.`,
        confirmText: 'Deactivate',
        danger: true,
      });
      if (!ok) return;

      btn.disabled = true;
      const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/staff/set-active`, {
        method: 'POST',
        body: JSON.stringify({ id: Number(btn.dataset.setActive), active: activate }),
      });
      if (result.success) location.reload();
      else btn.disabled = false;
    });
  });

  document.querySelectorAll('[data-delete]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const ok = await CivicConnect.confirmModal({
        title: 'Delete account permanently?',
        message: `${btn.dataset.name} will be logged out and the account removed for good. This cannot be undone. Their past responses and activity stay on record but will no longer show their name. To keep their name on record, deactivate the account instead.`,
        confirmText: 'Delete',
        danger: true,
      });
      if (!ok) return;

      btn.disabled = true;
      const result = await CivicConnect.apiFetch(`${window.APP_URL}/api/staff/delete`, {
        method: 'POST',
        body: JSON.stringify({ id: Number(btn.dataset.delete) }),
      });
      if (result.success) location.reload();
      else btn.disabled = false;
    });
  });

  // Reload so a new account shows in the table (and any password leaves the page).
  document.getElementById('staff-done-btn').addEventListener('click', () => location.reload());
})();
</script>

@include('partials.admin-footer')
