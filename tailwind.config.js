import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            colors: {
                navy: {
                    DEFAULT: '#082746',
                    50: '#E8EEF5',
                    100: '#D1DDEB',
                    200: '#A3BBD7',
                    300: '#7599C3',
                    400: '#4777AF',
                    500: '#082746',
                    600: '#071F3B',
                    700: '#051833',
                    800: '#041126',
                    900: '#020A18',
                },
                teal: {
                    DEFAULT: '#0D9488',
                    50: '#F0FDFA',
                    100: '#CCFBF1',
                    200: '#99F6E4',
                    300: '#5EEAD4',
                    400: '#2DD4BF',
                    500: '#0D9488',
                    600: '#0F766E',
                    700: '#0F766E',
                    800: '#115E59',
                    900: '#134E4A',
                },
                primary: {
                    DEFAULT: '#155EEF',
                    50: '#EFF6FF',
                    100: '#DBEAFE',
                    200: '#BFDBFE',
                    300: '#93C5FD',
                    400: '#60A5FA',
                    500: '#155EEF',
                    600: '#2563EB',
                    700: '#1D4ED8',
                    800: '#1E40AF',
                    900: '#1E3A8A',
                },
                brand: {
                    blue: '#155EEF',
                    bright: '#2563EB',
                    green: '#16A34A',
                    ink: '#0F172A',
                    mute: '#64748B',
                    line: '#E3EAF2',
                },
                surface: {
                    DEFAULT: '#F3F7FC',
                    muted: '#E3EAF2',
                    card: '#FFFFFF',
                    light: '#EFF6FF',
                },
            },
            fontFamily: {
                sans: [
                    'Inter',
                    'ui-sans-serif',
                    'system-ui',
                    'Segoe UI',
                    ...defaultTheme.fontFamily.sans,
                ],
            },
            borderRadius: {
                xl: '0.75rem',
                '2xl': '1rem',
                '3xl': '1.125rem',
            },
            boxShadow: {
                soft: '0 1px 3px 0 rgb(8 39 70 / 0.06), 0 1px 2px -1px rgb(8 39 70 / 0.06)',
                card: '0 8px 28px -14px rgb(8 39 70 / 0.16)',
                nav: '0 4px 20px -8px rgb(8 39 70 / 0.15)',
            },
            maxWidth: {
                landing: '72rem',
            },
        },
    },

    plugins: [forms],
};
