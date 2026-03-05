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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                dark: '#0D0D0D',
                'dark-card': '#1A1A1A',
                'dark-border': '#2D2D2D',
                accent: '#7C3AED',
                'accent-hover': '#6D28D9',
            },
        },
    },

    plugins: [forms],
};
