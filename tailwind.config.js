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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                gray: {
                    950: '#0a0a0f',
                },
            },
        },
    },

    // Safelist for dynamic color classes used in Blade templates
    safelist: [
        { pattern: /bg-(violet|blue|emerald|yellow|purple|red|orange|indigo|gray)-(400|500|600|700|800|900)/ },
        { pattern: /text-(violet|blue|emerald|yellow|purple|red|orange|indigo|gray)-(300|400|500|600)/ },
        { pattern: /border-(violet|blue|emerald|yellow|purple|red|orange|indigo|gray)-(600|700|800)/ },
        { pattern: /bg-(violet|blue|emerald|yellow|purple|red|orange|indigo|gray)-(500|600|900)\/(10|20|30|40|50)/ },
        { pattern: /border-(violet|blue|emerald|yellow|purple|red|orange|indigo|gray)-(500|600|700)\/(20|30|40|50)/ },
        { pattern: /hover:border-(violet|blue|emerald|yellow|purple|red|orange|indigo|gray)-(600)\/(40)/ },
    ],

    plugins: [forms],
};
