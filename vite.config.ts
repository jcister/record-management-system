import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { existsSync } from 'fs';

// Detect if running inside Docker container
const isInsideDocker = existsSync('/.dockerenv') || process.env.CONTAINER_ENV === 'docker';

/**
 * Wayfinder Vite plugin executes this command to generate TS types.
 *
 * Default to `php artisan ...` so `composer run dev` works on a host machine
 * where PHP is available. Only use `docker compose exec ...` when explicitly
 * opted-in.
 */
const wayfinderCommand = (() => {
    const explicitCommand = process.env.WAYFINDER_GENERATE_COMMAND;
    if (explicitCommand && explicitCommand.trim().length > 0) return explicitCommand.trim();

    if (isInsideDocker) {
        return 'php /var/www/html/artisan wayfinder:generate';
    }

    const shouldUseDockerCompose =
        process.env.WAYFINDER_USE_DOCKER_COMPOSE?.toLowerCase() === 'true' ||
        process.env.USE_DOCKER_COMPOSE?.toLowerCase() === 'true';

    return shouldUseDockerCompose
        ? 'docker compose exec -T -w /var/www/html laravel.test php artisan wayfinder:generate'
        : 'php artisan wayfinder:generate';
})();

export default defineConfig({
    build: {
        rollupOptions: {
            // Prevent EMFILE errors in containerized environments.
            maxParallelFileOps: 256,
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            command: wayfinderCommand,
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
