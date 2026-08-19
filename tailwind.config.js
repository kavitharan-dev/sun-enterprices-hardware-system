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
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
                display: ['Playfair Display', 'Georgia', 'serif'],
            },
            colors: {
                sun: {
                    50: '#fff8eb',
                    100: '#fdecc8',
                    200: '#f6d48a',
                    300: '#e8b84a',
                    400: '#d4a017',
                    500: '#c08a0a',
                    600: '#9a6c08',
                    700: '#7a550a',
                    800: '#5c400c',
                    900: '#3d2b08',
                },
                walnut: {
                    800: '#2a2118',
                    900: '#1c1510',
                    950: '#120e0b',
                },
            },
        },
    },

    plugins: [forms],
};
