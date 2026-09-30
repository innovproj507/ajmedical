/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './public/**/*.php',
        './templates/**/*.php',
        './plugins/**/*.php',
    ],
    // El contenido de las páginas institucionales vive en la base de datos, no en un .php
    // escaneado — Tailwind nunca ve esas clases, así que sin safelist no las generaría
    // aunque el HTML las use. Las variantes de opacidad van listadas explícitas.
    safelist: [
        { pattern: /(bg|text|border)-aj-(teal|teal-dark|teal-light|olive|olive-dark|olive-light)/ },
        ...['5', '10', '15', '20', '30', '40', '50', '60', '70', '80', '90']
            .flatMap((o) => ['teal', 'teal-dark', 'olive', 'olive-light']
                .flatMap((c) => [`bg-aj-${c}/${o}`, `text-aj-${c}/${o}`, `border-aj-${c}/${o}`])),
        // Botón de "play" estilo YouTube del lightbox (galerías/video de contenido de noticias).
        'bg-red-600', 'hover:bg-red-700', 'group-hover:bg-red-700', 'group-hover:scale-110',
        'max-w-xl', 'w-16', 'h-16', 'sm:w-20', 'sm:h-20', 'ml-1', 'opacity-70', 'opacity-90',
    ],
    theme: {
        extend: {
            colors: {
                // Colores tomados del logo de AJ Medical Supply.
                aj: {
                    teal: '#326666',          // verde azulado del isotipo — primario (nav, botones, enlaces)
                    'teal-dark': '#244B4B',   // hover / fondos oscuros (pie de página)
                    'teal-light': '#EEF4F3',  // fondos suaves
                    olive: '#9A9864',         // oliva del texto "Medical Supply" — acento
                    'olive-dark': '#7F7D4F',
                    'olive-light': '#C9C79A',
                },
            },
            fontFamily: {
                sans: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
        },
    },
    plugins: [
        require('@tailwindcss/typography'),
    ],
};
