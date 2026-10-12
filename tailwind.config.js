/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './index.php',
    './index1.php',
    './login.php',
    './scan.php',
    './admin/**/*.php',
    './assets/**/*.js',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
  safelist: [
    // Keep these classes even if not directly in templates
    'bg-emerald-50',
    'bg-rose-50',
    'bg-amber-50',
    'text-emerald-700',
    'text-rose-600',
  ],
}
