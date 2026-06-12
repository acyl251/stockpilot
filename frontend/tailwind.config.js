/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        navy:  '#1F3864',
        gold:  '#C9A84C',
        'navy-light': '#2A4A82',
        'navy-dark':  '#152848',
        'gold-light': '#D4B96A',
      },
      fontFamily: {
        sans: ['Inter', 'DM Sans', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
