/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        wp: {
          blue       : '#2271b1',
          'blue-dark': '#135e96',
          'blue-light': '#d0e8f5',
        },
        brand: {
          purple: '#6b4ff4',
          pink  : '#e2498a',
          green : '#1eaa6e',
        },
      },
    },
  },
  plugins: [],
}
