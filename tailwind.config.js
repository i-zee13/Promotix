/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './app/View/Components/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                dark: {
                    DEFAULT: '#0D0D0D',
                    card: '#161616',
                    border: '#262626',
                },
                accent: {
                    DEFAULT: '#7C3AED',
                    hover: '#6D28D9',
                    light: '#8B5CF6',
                },
            },
            borderRadius: {
                'container': '20px',
            },
        },
    },
    plugins: [],
};
