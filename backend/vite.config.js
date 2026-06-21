import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

import { viteStaticCopy } from 'vite-plugin-static-copy';

/** Bungkus output thr-payroll-batch agar minifier tidak pernah mengekspos `function $()` global yang menimpa jQuery. */
function vitePluginWrapThrPayrollBatchIife() {
    return {
        name: 'wrap-thr-payroll-batch-iife',
        generateBundle(_options, bundle) {
            for (const chunk of Object.values(bundle)) {
                if (chunk.type !== 'chunk') {
                    continue;
                }
                if (
                    chunk.fileName.endsWith('js/thr-payroll-batch.js') ||
                    chunk.fileName.endsWith('js/payroll-run.js')
                ) {
                    chunk.code = `(function(){\n"use strict";\n${chunk.code}\n})();`;
                }
            }
        },
    };
}

export default defineConfig({
    /** Hindari esbuild mem-minify nama fungsi jadi `$` — script ini dimuat tanpa `type="module"` sehingga `function $()` menimpa jQuery. */
    esbuild: {
        keepNames: true,
    },
    resolve: {
        alias: {
            react: fileURLToPath(new URL('./node_modules/react', import.meta.url)),
            'react-dom': fileURLToPath(new URL('./node_modules/react-dom', import.meta.url)),
            'react-dom/client': fileURLToPath(new URL('./node_modules/react-dom/client.js', import.meta.url)),
            // REMOVED: framer-motion & @phosphor-icons/react (old landing deprecated)
            // 'framer-motion': fileURLToPath(new URL('./node_modules/framer-motion', import.meta.url)),
            // '@phosphor-icons/react': fileURLToPath(new URL('./node_modules/@phosphor-icons/react', import.meta.url)),
        },
    },
    build: {
        manifest: true,
        rtl: true,
        outDir: 'public/build/',
        cssCodeSplit: true,
        rollupOptions: {
            output: {
                assetFileNames: (css) => {
                    if (css.name.split('.').pop() == 'css') {
                        return 'css/' + `[name]` + '.min.' + 'css';
                    } else {
                        return 'icons/' + css.name;
                    }
                },
                entryFileNames: 'js/' + `[name]` + `.js`,
            },
        },
    },
    plugins: [
        react(),
        laravel({
            input: [
                '../frontend/resources/css/style.css',
                '../frontend/resources/js/script.js',
                // REMOVED: public-landing-react.jsx (migrated to standalone landing app)
                '../frontend/resources/ts/thr-payroll-batch.ts',
                '../frontend/resources/ts/payroll-run.ts',
            ],
            refresh: ['resources/views/**', 'routes/**', '../frontend/resources/**'],
        }),
        vitePluginWrapThrPayrollBatchIife(),

        viteStaticCopy({
            targets: [
                {
                    src: '../frontend/resources/css',
                    dest: ''
                },
                {
                    src: '../frontend/resources/scss',
                    dest: ''
                },
                {
                    src: '../frontend/resources/fonts',
                    dest: ''
                },
                {
                    src: '../frontend/resources/img',
                    dest: ''
                },
                {
                    src: '../frontend/resources/js',
                    dest: ''
                },
               
                {
                    src: '../frontend/resources/plugins',
                    dest: ''
                },
                // REMOVED: Swiper & CountUp vendor copies (old landing deprecated)

            ]
        }),
    ],
});
