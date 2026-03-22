/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './templates/**/*.html.twig',
        './assets/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                gray: {
                    950: '#0a0a0f',
                }
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
            }
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
}
