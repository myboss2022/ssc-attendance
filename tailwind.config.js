/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './admin/**/*.php',
    './api/**/*.php',
    './assets/**/*.js'
  ],
  theme: {
    extend: {}
  },
  plugins: [],
  safelist: [
    'bg-emerald-50',
    'bg-rose-50',
    'bg-amber-50',
    'text-emerald-700',
    'text-rose-600',
    'text-amber-700',
    'bg-blue-50',
    'text-blue-700',
    'bg-indigo-700',
    'bg-indigo-600',
    'text-indigo-700'
  ]
};
