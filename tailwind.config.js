// Tailwind CSS settings — plain-language guide.
 // This file teaches the design system which pages to scan and which
 // custom colors, fonts, shadows, and animations are available.
 // Non-technical summary: it keeps every page looking consistent.
 module.exports = {
  // Dark mode: flipped by adding/removing the "dark" class on <html> (theme button in app.js).
  // "class" (not "media") lets the visitor's saved choice beat the device setting.
  darkMode: 'class',
   // Content paths: files Tailwind scans to know which styles are used.
   // If a page is not listed here, its styles may be missing.
  content: ['./*.php', './admin/**/*.php', './student/**/*.php', './includes/**/*.php', './assets/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        // Navy: main brand color for headers, footers, and headings.
        navy: { DEFAULT: '#0F2A43', light: '#163a5c', dark: '#0a1f33', 50: '#eef4f9', 100: '#d9e6f2', 800: '#12344f', 900: '#0F2A43' },
        // Brand blue: buttons, links, and highlights.
        brand: { 50: '#eff6ff', 100: '#dbeafe', 500: '#2563eb', 600: '#1d4ed8', 700: '#1e40af' },
      },
      fontFamily: {
        // Default text font: clean and readable on all devices.
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'],
      },
      boxShadow: {
        // Soft shadow: gentle lift for cards and dialogs.
        soft: '0 1px 2px 0 rgb(15 42 67 / 0.05), 0 8px 24px -12px rgb(15 42 67 / 0.18)',
        // Lift shadow: stronger emphasis for banners and popups.
        lift: '0 12px 32px -12px rgb(15 42 67 / 0.28)',
        // Card shadow: light outline used on everyday content boxes.
        card: '0 1px 3px 0 rgb(15 23 42 / 0.06), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
      },
      // Rounded corners: preset corner sizes for buttons and cards.
      borderRadius: { '2xl': '1rem', '3xl': '1.5rem' },
      keyframes: {
        // Fade-up motion: content gently rises into view.
        'fade-up': { '0%': { opacity: '0', transform: 'translateY(8px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
        // Grow-bar motion: progress bars fill from empty to full.
        'grow-bar': { '0%': { width: '0%' } },
      },
      animation: {
        // Ready-to-use animation names built from the motions above.
        'fade-up': 'fade-up .4s ease-out both',
        'grow-bar': 'grow-bar .8s ease-out both',
      },
    },
  },
  plugins: [],
};
