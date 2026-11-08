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
                    DEFAULT: '#082B50',
                    50: '#E8EEF5',
                    100: '#D1DDEB',
                    200: '#A3BBD7',
                    300: '#7599C3',
                    400: '#4777AF',
                    500: '#082B50',
                    600: '#061F3B',
                    700: '#051833',
                    800: '#041126',
                    900: '#020A18',
                },
                teal: {
                    DEFAULT: '#08B7A5',
                    50: '#F0FDFA',
                    100: '#CCFBF1',
                    200: '#99F6E4',
                    300: '#5EEAD4',
                    400: '#2DD4BF',
                    500: '#08B7A5',
                    600: '#0D9488',
                    700: '#0F766E',
                    800: '#115E59',
                    900: '#134E4A',
                },
                brand: {
                    blue: '#2878D0',
                    bright: '#2878D0',
                    green: '#16A34A',
                    ink: '#102A43',
                    mute: '#64748B',
                    line: '#E3EAF2',
                },
                surface: {
                    DEFAULT: '#F3F7FC',
                    muted: '#E3EAF2',
                    card: '#FFFFFF',
                },
            },
            fontFamily: {
                sans: [
                    'Plus Jakarta Sans',
                    'ui-sans-serif',
                    'system-ui',
                    'Segoe UI',
                    ...defaultTheme.fontFamily.sans,
                ],
            },
            borderRadius: {
                xl: '0.75rem',
                '2xl': '1rem',
                '3xl': '1.5rem',
            },
            boxShadow: {
                soft: '0 1px 3px 0 rgb(8 43 85 / 0.06), 0 1px 2px -1px rgb(8 43 85 / 0.06)',
                card: '0 8px 30px -12px rgb(8 43 85 / 0.18)',
                nav: '0 4px 20px -8px rgb(8 43 85 / 0.15)',
            },
            maxWidth: {
                'landing': '72rem',
            },
        },
    },

    plugins: [forms],
};
