module.exports = {
  content: ['./*.php', './admin/**/*.php', './student/**/*.php', './includes/**/*.php', './assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        navy: { DEFAULT: '#0F2A43', light: '#163a5c', dark: '#0a1f33', 50: '#eef4f9', 100: '#d9e6f2', 800: '#12344f', 900: '#0F2A43' },
        brand: { 50: '#eff6ff', 100: '#dbeafe', 500: '#2563eb', 600: '#1d4ed8', 700: '#1e40af' },
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'],
      },
      boxShadow: {
        soft: '0 1px 2px 0 rgb(15 42 67 / 0.05), 0 8px 24px -12px rgb(15 42 67 / 0.18)',
        lift: '0 12px 32px -12px rgb(15 42 67 / 0.28)',
        card: '0 1px 3px 0 rgb(15 23 42 / 0.06), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
      },
      borderRadius: { '2xl': '1rem', '3xl': '1.5rem' },
      keyframes: {
        'fade-up': { '0%': { opacity: '0', transform: 'translateY(8px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
        'grow-bar': { '0%': { width: '0%' } },
      },
      animation: {
        'fade-up': 'fade-up .4s ease-out both',
        'grow-bar': 'grow-bar .8s ease-out both',
      },
    },
  },
  plugins: [],
};
