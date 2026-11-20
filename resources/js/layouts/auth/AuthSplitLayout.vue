<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

import AppLogoIcon from '@/components/layout/AppLogoIcon.vue';
import { home } from '@/routes';
import type { AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
const name = page.props.name;
const subtitle =
    typeof page.props.subtitle === 'string'
        ? page.props.subtitle
        : 'Knowledge Management Information System';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col bg-muted p-10 text-white lg:flex dark:border-r"
        >
            <div class="absolute inset-0 bg-zinc-900" />
            <Link
                :href="home()"
                class="relative z-20 flex items-start gap-3"
            >
                <AppLogoIcon class="size-8 fill-current text-white" />
                <div class="min-w-0 space-y-1">
                    <p class="text-base font-semibold leading-tight text-white">{{ name }}</p>
                    <p class="text-xs uppercase tracking-wide text-zinc-300">{{ subtitle }}</p>
                </div>
            </Link>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
