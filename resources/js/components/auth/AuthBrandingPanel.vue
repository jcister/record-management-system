<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import LogoFull from '@/components/icons/LogoFull.vue';

interface Props {
    badge?: string;
    title: string;
    description: string;
    features?: string[];
    gradientFrom?: string;
    gradientTo?: string;
}

withDefaults(defineProps<Props>(), {
    badge: 'Welcome',
    gradientFrom: 'from-blue-600',
    gradientTo: 'to-purple-600',
});
</script>

<template>
    <div class="relative hidden min-h-screen overflow-hidden bg-gradient-to-br lg:flex" :class="[gradientFrom, gradientTo]">
        <!-- Animated gradient overlay -->
        <div class="absolute inset-0 bg-gradient-to-br from-purple-600/30 via-pink-500/20 to-blue-600/30 animate-gradient-shift"></div>

        <!-- Decorative shapes -->
        <div class="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-20 h-[500px] w-[500px] rounded-full bg-white/10 blur-3xl"></div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col items-start justify-center px-12 py-16 lg:px-16 xl:px-24">
            <div class="max-w-lg space-y-8">
                <!-- Logo -->
                <Link href="/" class="inline-block transition-transform hover:scale-105">
                    <div class="flex items-center gap-4">
                        <LogoFull class="h-16 w-16 text-white drop-shadow-lg" />
                        <div class="space-y-1">
                            <span class="block text-2xl font-bold leading-tight text-white xl:text-3xl">CHED ONSE KMIS</span>
                            <span class="block text-xs uppercase tracking-wide text-white/85 xl:text-sm">Knowledge Management Information System</span>
                        </div>
                    </div>
                </Link>

                <!-- Badge -->
                <div class="inline-flex items-center gap-2 rounded-full bg-white/20 px-4 py-2 backdrop-blur-sm">
                    <div class="h-2 w-2 animate-pulse rounded-full bg-white"></div>
                    <span class="text-sm font-semibold uppercase tracking-wider text-white">{{ badge }}</span>
                </div>

                <!-- Title and Description -->
                <div class="space-y-4">
                    <h1 class="text-5xl font-bold leading-tight text-white drop-shadow-lg">
                        {{ title }}
                    </h1>
                    <p class="text-xl leading-relaxed text-white/90">
                        {{ description }}
                    </p>
                </div>

                <!-- Features -->
                <div v-if="features && features.length > 0" class="space-y-4 pt-4">
                    <div
                        v-for="(feature, index) in features"
                        :key="index"
                        class="flex items-start gap-3 text-white/90"
                    >
                        <div class="mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm">
                            <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <span class="text-base">{{ feature }}</span>
                    </div>
                </div>

                <!-- Decorative quote or testimonial slot -->
                <slot name="extra-content" />
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes gradient-shift {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.8;
    }
}

.animate-gradient-shift {
    animation: gradient-shift 8s ease-in-out infinite;
}
</style>

