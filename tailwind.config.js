import defaultTheme from 'tailwindcss/defaultTheme'

export default {
  content: ['./resources/**/*.{js,vue,blade.php}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#0066a1',
          600: '#005a8f',
          700: '#004d7a',
          800: '#003f66',
          900: '#003454',
          950: '#002238',
        },
        magenta: {
          400: '#f57845',
          500: '#e8541e',
          600: '#d4441a',
          700: '#b83716',
        },
        dark: {
          900: '#050b14',
          800: '#0a1220',
          700: '#0f1a2d',
          600: '#162540',
        },
      },
      fontFamily: {
        sans: ['"Cerebri Sans"', ...defaultTheme.fontFamily.sans],
      },
    },
  },
}