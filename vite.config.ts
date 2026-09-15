import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const vitePort = Number(env.VITE_PORT ?? 5175);
    const appUrl = new URL(env.APP_URL ?? 'http://localhost');
    const serverPort = Number(env.SERVER_PORT ?? appUrl.port ?? 8003);
    const corsOrigins = env.VITE_CORS_ORIGINS
        ? env.VITE_CORS_ORIGINS.split(',').map((origin) => origin.trim())
        : [
              appUrl.origin,
              `http://localhost:${serverPort}`,
              `http://127.0.0.1:${serverPort}`,
          ];

    return {
        server: {
            host: env.VITE_HOST ?? '0.0.0.0',
            port: vitePort,
            cors: {
                origin: corsOrigins,
            },
            hmr: {
                host: appUrl.hostname,
            },
        },
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            inertia(),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            tailwindcss(),
            wayfinder({
                formVariants: true,
            }),
        ],
    };
});
