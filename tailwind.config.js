import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** Colours read CSS variables (RGB triplets) from resources/css/app.css so dark mode swaps them in one place. */
const v = (name) => `rgb(var(--${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/Support/**/*.php',
        './app/Http/Controllers/**/*.php',
    ],

    // Colour utilities built dynamically on the UI kit page.
    safelist: [{ pattern: /^bg-(primary|success|warning|danger|info|sidebar)(\/10)?$/ }],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: v('color-primary'),
                    hover: v('color-primary-hover'),
                    soft: v('color-primary-soft'),
                    50: '#EEF2FF', 100: '#E0E7FF', 200: '#C7D2FE', 300: '#A5B4FC', 400: '#818CF8',
                    500: '#6366F1', 600: '#4F46E5', 700: '#4338CA', 800: '#3730A3', 900: '#312E81',
                },
                success: { DEFAULT: v('color-success') },
                warning: { DEFAULT: v('color-warning') },
                danger: { DEFAULT: v('color-danger') },
                info: { DEFAULT: v('color-info') },
                page: v('bg-page'),
                card: v('bg-card'),
                subtle: v('bg-subtle'),
                line: v('border-line'),
                heading: v('text-heading'),
                body: v('text-body'),
                muted: v('text-muted'),
                sidebar: v('bg-sidebar'),
            },
            fontSize: {
                'page-title': ['24px', { lineHeight: '32px', fontWeight: '600' }],
                'card-title': ['16px', { lineHeight: '24px', fontWeight: '600' }],
                kpi: ['28px', { lineHeight: '36px', fontWeight: '700' }],
            },
            boxShadow: {
                card: '0 1px 3px rgba(15, 23, 42, .06)',
                'card-hover': '0 10px 24px -8px rgba(15, 23, 42, .14)',
                drawer: '-12px 0 32px -8px rgba(15, 23, 42, .18)',
            },
            width: { sidebar: '270px', rail: '80px', drawer: '480px' },
            spacing: { sidebar: '270px', rail: '80px', header: '70px' },
            height: { header: '70px', btn: '40px', input: '42px' },
            keyframes: {
                'slide-in': { from: { opacity: 0, transform: 'translateY(-6px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
                shimmer: { '100%': { transform: 'translateX(100%)' } },
            },
            animation: {
                'slide-in': 'slide-in .18s ease-out',
                shimmer: 'shimmer 1.4s infinite',
            },
        },
    },

    plugins: [forms],
};
