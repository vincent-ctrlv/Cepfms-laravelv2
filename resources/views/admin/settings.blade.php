{{-- VIEW: System Settings (Branding)
     Expects (set by Admin\PageController): $staff, $activeNav, $pageTitle, $brand, $hasCustomLogo --}}
@include('partials.admin-header')
<?php
$inputClass = 'w-full border border-borderc rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal';

/** One labelled text field bound to a branding setting. */
$field = function (string $key, string $label, int $max, string $hint = '', string $type = 'text') use ($brand, $inputClass) {
    $id = 'set-' . $key;
    
?>
    <div>
      <label for="{{ $id }}" class="block text-xs font-medium text-navy mb-1">{{ $label }}</label>
      <input id="{{ $id }}" name="{{ $key }}" type="{{ $type }}" required maxlength="{!! $max !!}"
             value="{{ $brand[$key] }}" data-default="{{ BRANDING_DEFAULTS[$key] }}" class="{!! $inputClass !!}">
      @if ($hint !== '')<p class="text-[11px] text-slate mt-1">{{ $hint }}</p>@endif
    </div>
    <?php
};
?>

<div class="max-w-3xl">
  <div class="mb-6">
    <h1 class="text-xl font-semibold text-navy">System Settings</h1>
    <p class="text-sm text-slate mt-1">Branding shown on the citizen portal, staff pages and emails. Only super admins can see this page, and every change is recorded in the Audit Log.</p>
  </div>

  <form id="branding-form" novalidate>
    <!-- Logo -->
    <section class="card p-6 mb-5">
      <h2 class="text-sm font-semibold text-navy">Logo</h2>
      <p class="text-xs text-slate mt-0.5 mb-5">Shown in the citizen navbar and footer, the staff sidebar and the login pages.</p>
      <div class="flex flex-col sm:flex-row sm:items-center gap-5">
        <div class="w-20 h-20 rounded-full bg-white border border-borderc shadow-sm overflow-hidden flex items-center justify-center flex-shrink-0">
          <img id="logo-preview" src="{{ brand_logo_url() }}" data-default-src="{{ app_href(BRAND_DEFAULT_LOGO) }}" alt="Current logo" class="w-full h-full object-contain">
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap gap-2">
            <label for="logo-input" class="cursor-pointer text-sm font-medium py-2 px-4 rounded-lg border border-borderc text-navy hover:bg-pagebg transition">Upload new logo</label>
            <input id="logo-input" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only">
            <button type="button" id="logo-reset-btn" class="{!! $hasCustomLogo ? '' : 'hidden ' !!}text-sm font-medium py-2 px-4 rounded-lg border border-borderc text-slate hover:bg-pagebg hover:text-navy transition">Use the city seal</button>
          </div>
          <p class="text-[11px] text-slate mt-2">PNG, JPG or WebP, up to 1 MB. A square image works best; it's shown in a circle.</p>
          <p id="logo-status" class="hidden text-[11px] font-semibold text-amber mt-1"></p>
          <input type="hidden" name="remove_logo" id="remove-logo" value="0">
        </div>
      </div>
    </section>

    <!-- Names -->
    <section class="card p-6 mb-5">
      <h2 class="text-sm font-semibold text-navy">Names</h2>
      <p class="text-xs text-slate mt-0.5 mb-5">Used in page titles, headers, footers and email subjects.</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php $field('brand_short_name', 'System short name', 40, 'e.g. in the navbar, sidebar and browser tab.'); ?>
        <?php $field('brand_office', 'Office name', 120, 'e.g. in the footer and contact details.'); ?>
        <div class="sm:col-span-2"><?php $field('brand_full_name', 'System full name', 150, 'On the login page, sidebar and emails.'); ?></div>
        <div class="sm:col-span-2"><?php $field('brand_government', 'Government name', 150, 'Above the short name in the citizen navbar, and in the footer.'); ?></div>
      </div>
    </section>

    <!-- Contact -->
    <section class="card p-6 mb-5">
      <h2 class="text-sm font-semibold text-navy">Contact details</h2>
      <p class="text-xs text-slate mt-0.5 mb-5">Shown to citizens on the Home page, in the footer, and in the Privacy Policy and Terms.</p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><?php $field('contact_address', 'Address', 200, 'Shown after the office name.'); ?></div>
        <?php $field('contact_phone', 'Phone', 60); ?>
        <?php $field('contact_email', 'Email', 190, '', 'email'); ?>
        <div class="sm:col-span-2"><?php $field('contact_hours', 'Office hours', 100); ?></div>
      </div>
    </section>

    <!-- Replies -->
    <section class="card p-6 mb-5">
      <h2 class="text-sm font-semibold text-navy">Replies</h2>
      <p class="text-xs text-slate mt-0.5 mb-5">Ends every Quick Template in Response Management, after "Sincerely,".</p>
      <div class="sm:w-1/2"><?php $field('email_signoff', 'Reply sign-off', 80); ?></div>
    </section>

    <!-- Save bar -->
    <div class="card px-6 py-4 flex flex-wrap items-center justify-end gap-2 sticky bottom-4">
      <span id="branding-dirty" class="hidden text-xs text-amber mr-auto">Unsaved changes</span>
      <button type="button" id="branding-cancel-btn" class="hidden text-sm font-medium py-2 px-4 rounded-lg border border-borderc text-navy hover:bg-pagebg transition">Cancel</button>
      <button type="submit" id="branding-save-btn" class="bg-teal text-white text-sm font-semibold py-2 px-5 rounded-lg hover:bg-teal/90 transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>Save changes</button>
    </div>
  </form>
</div>

@include('partials.admin-footer')
<script src="{{ asset_v('frontend/js/settings.js') }}"></script>
