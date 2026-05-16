import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Legacy tokens kept for backward compat with un-revamped pages.
                dark: '#0D0D0D',
                'dark-card': '#1A1A1A',
                'dark-border': '#2D2D2D',
                accent: '#6400B2',
                'accent-hover': '#56009C',

                // New "night" palette (dark UI surfaces).
                night: {
                    50:  '#F4F5FB',
                    100: '#E5E6F2',
                    200: '#C7C8E0',
                    300: '#9FA1C2',
                    400: '#7B7DA3',
                    500: '#52557E',
                    600: '#373A60',
                    700: '#252A4D',
                    800: '#171B36',
                    900: '#10142A',
                    950: '#0B0E1F',
                },

                // Brand purple ramp (Figma primary #6400B2).
                brand: {
                    50:  '#F5F0FF',
                    100: '#E8DCFF',
                    200: '#D1B8FF',
                    300: '#B894F0',
                    400: '#8B4DDB',
                    500: '#6400B2',
                    600: '#56009C',
                    700: '#45007E',
                    800: '#3A0D63',
                    900: '#240040',
                },
            },
            boxShadow: {
                card: '0 1px 2px rgb(0 0 0 / 0.06), 0 6px 24px rgb(15 18 38 / 0.18)',
                'card-lg': '0 10px 40px rgb(15 18 38 / 0.28)',
                pill: 'inset 0 0 0 1px rgb(255 255 255 / 0.04)',
            },
            borderRadius: {
                xl2: '1.25rem',
            },
        },
    },

    plugins: [forms],
};
