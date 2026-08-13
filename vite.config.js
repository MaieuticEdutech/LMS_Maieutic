import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Downloaded at build time and self-hosted from our own domain —
            // no runtime CDN, so no user IP reaches a third party. All three
            // are SIL Open Font License 1.1; see docs/UI-GUIDE.md §5.
            //
            // Weights are deliberately narrow. Every extra weight is another
            // file on the critical path, so one is added only when a design
            // actually needs it.
            fonts: [
                // Serif display — headlines and pull quotes.
                bunny('Newsreader', {
                    weights: [400, 500, 600],
                }),
                // Sans — all UI and body copy.
                bunny('Hanken Grotesk', {
                    weights: [400, 500, 600, 700],
                }),
                // Mono — eyebrows, labels, metadata.
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
