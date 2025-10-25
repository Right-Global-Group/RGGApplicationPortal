import defaultTheme from 'tailwindcss/defaultTheme'

export default {
  content: ['./resources/**/*.{js,vue,blade.php}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f5f9ff',
          100: '#e5f0fe',
          200: '#cfe1fe',
          300: '#a3cbfd',
          400: '#75b3fa',
          500: '#1a7bb5',
          600: '#006fa3',
          700: '#006091',
          800: '#00507b',
          900: '#004266',
          950: '#00304a',
        },
        magenta: {
          400: '#f78e60',
          500: '#eb6635',
          600: '#d95228',
          700: '#bf441f',
        },
        dark: {
          900: '#0a1320',
          800: '#101a2d',
          700: '#16243b',
          600: '#1e3252',
        },
      },
      fontFamily: {
        sans: ['"Cerebri Sans"', ...defaultTheme.fontFamily.sans],
      },
    },
  },
}
