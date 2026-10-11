/**
 * Tailwind build for the citizen portal only (output: public/frontend/css/tailwind.css).
 * Admin and staff login pages still use the CDN, configured in resources/views/partials/header.blade.php.
 * Keep the theme below in sync with that inline config.
 *
 * After changing classes on a citizen page: npm run build:css (or watch:css while working).
 */
module.exports = {
  content: [
    './resources/views/citizen/**/*.blade.php',
    './resources/views/partials/header.blade.php',
    './resources/views/partials/navbar.blade.php',
    './resources/views/partials/citizen-footer.blade.php',
    './public/frontend/js/app.js',
    './public/frontend/js/citizen.js',
    './public/frontend/js/track.js',
    './public/frontend/js/success.js',
  ],
  theme: {
    extend: {
      colors: {
        navy: '#0f1e3d',
        teal: '#2563eb',
        lightteal: '#3b82f6',
        slate: '#475569',
        emerald: '#059669',
        amber: '#d97706',
        dangerred: '#dc2626',
        borderc: '#e2e8f0',
        pagebg: '#f1f5f9',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['Roboto Mono', 'monospace'],
      },
      borderRadius: { DEFAULT: '4px' },
    },
  },
};
