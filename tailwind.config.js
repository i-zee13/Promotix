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
                // Legacy tokens — follow Super Admin branding CSS variables at runtime.
                dark: 'var(--brand-background, #0D0D0D)',
                'dark-card': 'var(--brand-surface, #1A1A1A)',
                'dark-border': 'var(--brand-outline, #2D2D2D)',
                accent: 'var(--brand-primary, #6400B2)',
                'accent-hover': 'var(--brand-secondary, #56009C)',

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

                // Brand ramp — driven by --brand-primary / --brand-secondary from Super Admin.
                brand: {
                    50:  'color-mix(in srgb, var(--brand-primary, #6400B2) 8%, white)',
                    100: 'color-mix(in srgb, var(--brand-primary, #6400B2) 15%, white)',
                    200: 'color-mix(in srgb, var(--brand-primary, #6400B2) 30%, white)',
                    300: 'color-mix(in srgb, var(--brand-primary, #6400B2) 45%, white)',
                    400: 'color-mix(in srgb, var(--brand-primary, #6400B2) 70%, white)',
                    500: 'var(--brand-primary, #6400B2)',
                    600: 'var(--brand-secondary, #56009C)',
                    700: 'color-mix(in srgb, var(--brand-secondary, #45007E) 85%, black)',
                    800: 'color-mix(in srgb, var(--brand-secondary, #3A0D63) 90%, black)',
                    900: 'color-mix(in srgb, var(--brand-secondary, #240040) 95%, black)',
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
