/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',  // IMPORTANT
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.{vue,js,ts}',
    ],
    theme: {
        extend: {
            fontFamily: {
                chewy: ['Chewy', 'cursive'],
                baloo: ['"Baloo 2"', 'sans-serif'],
                fredoka: ['Fredoka', 'sans-serif'],
            },
        },
    },
    plugins: [],
}
