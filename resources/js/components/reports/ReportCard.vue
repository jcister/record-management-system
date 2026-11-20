<script setup lang="ts">
import { BarChart2, type LucideIcon } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

interface Props {
    title: string;
    description: string;
    href: string;
    icon?: LucideIcon;
}

const props = withDefaults(defineProps<Props>(), {
    icon: BarChart2 as unknown as LucideIcon,
});
</script>

<template>
    <Link
        :href="props.href"
        class="group flex flex-col gap-4 rounded-xl border border-border/60 bg-card p-6 shadow-sm transition-all hover:border-primary/40 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        tabindex="0"
        :aria-label="`Open ${props.title} report`"
    >
        <div class="flex items-start justify-between">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary group-hover:bg-primary/20 transition-colors">
                <component :is="props.icon" class="h-5 w-5" />
            </div>
            <span class="text-xs font-medium text-muted-foreground group-hover:text-primary transition-colors">→</span>
        </div>
        <div>
            <h3 class="text-base font-semibold text-foreground">{{ props.title }}</h3>
            <p class="mt-1 text-sm text-muted-foreground leading-relaxed">{{ props.description }}</p>
        </div>
    </Link>
</template>
