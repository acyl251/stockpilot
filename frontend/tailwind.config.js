/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        // Palette StockPilot — pilotée par les CSS variables de main.css
        navy:         '#0F172A',   // primary — sidebar, headers, texte principal
        'navy-light': '#1E293B',   // secondary — éléments secondaires
        'navy-dark':  '#0B1120',   // navy le plus foncé (gradients)
        gold:         '#F59E0B',   // accent — boutons principaux, highlights
        'gold-light': '#FBBF24',   // accent clair
        'gold-dark':  '#D97706',   // accent hover
        surface:      '#FFFFFF',   // cartes, modals
        'app-bg':     '#F8FAFC',   // fond général
        'app-border': '#E2E8F0',   // bordures
        'text-muted': '#64748B',   // texte secondaire
        success:      '#10B981',
        danger:       '#EF4444',
        warning:      '#F59E0B',
      },
      fontFamily: {
        sans: ['Inter', 'DM Sans', 'sans-serif'],
      },
      boxShadow: {
        card:       '0 1px 3px rgba(0,0,0,0.08)',
        'card-hover': '0 4px 12px rgba(0,0,0,0.12)',
      },
    },
  },
  plugins: [],
}
