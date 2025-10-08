import defaultTheme from 'tailwindcss/defaultTheme'

export default {
  content: ['./resources/**/*.{js,vue,blade.php}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#faf5ff',
          100: '#f3e8ff',
          200: '#e9d5ff',
          300: '#d8b4fe',
          400: '#c084fc',
          500: '#a855f7',
          600: '#9333ea',
          700: '#7e22ce',
          800: '#6b21a8',
          900: '#581c87',
          950: '#3b0764',
        },
        magenta: {
          400: '#e879f9',
          500: '#d946ef',
          600: '#c026d3',
          700: '#a21caf',
        },
        dark: {
          900: '#0f0520',
          800: '#1a0b2e',
          700: '#2d1b4e',
          600: '#3d2563',
        },
      },
      fontFamily: {
        sans: ['"Cerebri Sans"', ...defaultTheme.fontFamily.sans],
      },
    },
  },
}
