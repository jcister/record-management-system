<script setup lang="ts">
import { Check } from 'lucide-vue-next';

import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const themes = [
    { value: 'light', label: 'Light' },
    { value: 'system', label: 'System Default' },
    { value: 'dark', label: 'Dark' },
] as const;
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <button
            v-for="{ value, label } in themes"
            :key="value"
            type="button"
            @click="updateAppearance(value)"
            :class="[
                'group relative flex flex-col items-center gap-3 rounded-xl border p-4 text-left shadow-sm transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                appearance === value
                    ? 'border-primary bg-primary/5 shadow-primary/20 shadow-lg ring-2 ring-primary dark:bg-primary/10'
                    : 'border-border bg-card hover:scale-[1.02] hover:border-neutral-400 hover:shadow-md dark:hover:border-neutral-500',
            ]"
        >
            <!-- Active checkmark badge -->
            <span
                v-if="appearance === value"
                class="absolute right-2.5 top-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <Check class="h-3 w-3" />
            </span>

            <!-- Light theme preview -->
            <div v-if="value === 'light'" class="w-full overflow-hidden rounded-lg border border-neutral-200 shadow-sm">
                <svg viewBox="0 0 160 100" xmlns="http://www.w3.org/2000/svg" class="w-full">
                    <!-- Background -->
                    <rect width="160" height="100" fill="#f8fafc" />
                    <!-- Sidebar -->
                    <rect width="36" height="100" fill="#f1f5f9" />
                    <!-- Sidebar logo area -->
                    <rect x="8" y="10" width="20" height="6" rx="3" fill="#cbd5e1" />
                    <!-- Sidebar nav items -->
                    <rect x="8" y="24" width="20" height="4" rx="2" fill="#94a3b8" />
                    <rect x="8" y="32" width="16" height="4" rx="2" fill="#cbd5e1" />
                    <rect x="8" y="40" width="18" height="4" rx="2" fill="#cbd5e1" />
                    <rect x="8" y="48" width="14" height="4" rx="2" fill="#cbd5e1" />
                    <!-- Active nav item -->
                    <rect x="4" y="22" width="28" height="8" rx="4" fill="#e2e8f0" />
                    <!-- Header bar -->
                    <rect x="36" width="124" height="18" fill="#ffffff" />
                    <rect x="44" y="6" width="40" height="5" rx="2.5" fill="#e2e8f0" />
                    <circle cx="150" cy="9" r="4" fill="#e2e8f0" />
                    <!-- Content area -->
                    <rect x="44" y="24" width="108" height="8" rx="3" fill="#e2e8f0" />
                    <rect x="44" y="36" width="68" height="5" rx="2.5" fill="#f1f5f9" />
                    <!-- Cards row -->
                    <rect x="44" y="46" width="32" height="22" rx="4" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="82" y="46" width="32" height="22" rx="4" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="120" y="46" width="32" height="22" rx="4" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" />
                    <rect x="50" y="51" width="18" height="3" rx="1.5" fill="#cbd5e1" />
                    <rect x="50" y="57" width="12" height="5" rx="2" fill="#6366f1" />
                    <rect x="88" y="51" width="18" height="3" rx="1.5" fill="#cbd5e1" />
                    <rect x="88" y="57" width="12" height="5" rx="2" fill="#10b981" />
                    <rect x="126" y="51" width="18" height="3" rx="1.5" fill="#cbd5e1" />
                    <rect x="126" y="57" width="12" height="5" rx="2" fill="#f59e0b" />
                    <!-- Bottom row -->
                    <rect x="44" y="74" width="108" height="4" rx="2" fill="#f1f5f9" />
                    <rect x="44" y="82" width="80" height="4" rx="2" fill="#f1f5f9" />
                </svg>
            </div>

            <!-- System Default theme preview (split light/dark) -->
            <div v-else-if="value === 'system'" class="w-full overflow-hidden rounded-lg border border-neutral-300 shadow-sm">
                <svg viewBox="0 0 160 100" xmlns="http://www.w3.org/2000/svg" class="w-full">
                    <!-- Clip paths for split -->
                    <defs>
                        <clipPath id="left-half">
                            <rect width="80" height="100" />
                        </clipPath>
                        <clipPath id="right-half">
                            <rect x="80" width="80" height="100" />
                        </clipPath>
                    </defs>

                    <!-- === LIGHT HALF (left) === -->
                    <g clip-path="url(#left-half)">
                        <rect width="160" height="100" fill="#f8fafc" />
                        <rect width="36" height="100" fill="#f1f5f9" />
                        <rect x="8" y="10" width="20" height="6" rx="3" fill="#cbd5e1" />
                        <rect x="8" y="24" width="20" height="4" rx="2" fill="#94a3b8" />
                        <rect x="8" y="32" width="16" height="4" rx="2" fill="#cbd5e1" />
                        <rect x="4" y="22" width="28" height="8" rx="4" fill="#e2e8f0" />
                        <rect x="36" width="124" height="18" fill="#ffffff" />
                        <rect x="44" y="6" width="40" height="5" rx="2.5" fill="#e2e8f0" />
                        <rect x="44" y="24" width="108" height="8" rx="3" fill="#e2e8f0" />
                        <rect x="44" y="36" width="68" height="5" rx="2.5" fill="#f1f5f9" />
                        <rect x="44" y="46" width="32" height="22" rx="4" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" />
                        <rect x="50" y="51" width="18" height="3" rx="1.5" fill="#cbd5e1" />
                        <rect x="50" y="57" width="12" height="5" rx="2" fill="#6366f1" />
                        <rect x="44" y="74" width="108" height="4" rx="2" fill="#f1f5f9" />
                    </g>

                    <!-- === DARK HALF (right) === -->
                    <g clip-path="url(#right-half)">
                        <rect width="160" height="100" fill="#0f172a" />
                        <rect width="36" height="100" fill="#1e293b" />
                        <rect x="8" y="10" width="20" height="6" rx="3" fill="#334155" />
                        <rect x="8" y="24" width="20" height="4" rx="2" fill="#475569" />
                        <rect x="8" y="32" width="16" height="4" rx="2" fill="#334155" />
                        <rect x="4" y="22" width="28" height="8" rx="4" fill="#334155" />
                        <rect x="36" width="124" height="18" fill="#1e293b" />
                        <rect x="44" y="6" width="40" height="5" rx="2.5" fill="#334155" />
                        <rect x="44" y="24" width="108" height="8" rx="3" fill="#1e293b" />
                        <rect x="44" y="36" width="68" height="5" rx="2.5" fill="#0f172a" />
                        <rect x="44" y="46" width="32" height="22" rx="4" fill="#1e293b" stroke="#334155" stroke-width="1" />
                        <rect x="50" y="51" width="18" height="3" rx="1.5" fill="#334155" />
                        <rect x="50" y="57" width="12" height="5" rx="2" fill="#818cf8" />
                        <rect x="44" y="74" width="108" height="4" rx="2" fill="#1e293b" />
                    </g>

                    <!-- Divider line -->
                    <line x1="80" y1="0" x2="80" y2="100" stroke="#64748b" stroke-width="1" stroke-dasharray="4 3" />
                </svg>
            </div>

            <!-- Dark theme preview -->
            <div v-else-if="value === 'dark'" class="w-full overflow-hidden rounded-lg border border-neutral-700 shadow-sm">
                <svg viewBox="0 0 160 100" xmlns="http://www.w3.org/2000/svg" class="w-full">
                    <!-- Background -->
                    <rect width="160" height="100" fill="#0f172a" />
                    <!-- Sidebar -->
                    <rect width="36" height="100" fill="#1e293b" />
                    <!-- Sidebar logo area -->
                    <rect x="8" y="10" width="20" height="6" rx="3" fill="#334155" />
                    <!-- Sidebar nav items -->
                    <rect x="8" y="24" width="20" height="4" rx="2" fill="#475569" />
                    <rect x="8" y="32" width="16" height="4" rx="2" fill="#334155" />
                    <rect x="8" y="40" width="18" height="4" rx="2" fill="#334155" />
                    <rect x="8" y="48" width="14" height="4" rx="2" fill="#334155" />
                    <!-- Active nav item -->
                    <rect x="4" y="22" width="28" height="8" rx="4" fill="#334155" />
                    <!-- Header bar -->
                    <rect x="36" width="124" height="18" fill="#1e293b" />
                    <rect x="44" y="6" width="40" height="5" rx="2.5" fill="#334155" />
                    <circle cx="150" cy="9" r="4" fill="#334155" />
                    <!-- Content area -->
                    <rect x="44" y="24" width="108" height="8" rx="3" fill="#1e293b" />
                    <rect x="44" y="36" width="68" height="5" rx="2.5" fill="#0f172a" />
                    <!-- Cards row -->
                    <rect x="44" y="46" width="32" height="22" rx="4" fill="#1e293b" stroke="#334155" stroke-width="1" />
                    <rect x="82" y="46" width="32" height="22" rx="4" fill="#1e293b" stroke="#334155" stroke-width="1" />
                    <rect x="120" y="46" width="32" height="22" rx="4" fill="#1e293b" stroke="#334155" stroke-width="1" />
                    <rect x="50" y="51" width="18" height="3" rx="1.5" fill="#334155" />
                    <rect x="50" y="57" width="12" height="5" rx="2" fill="#818cf8" />
                    <rect x="88" y="51" width="18" height="3" rx="1.5" fill="#334155" />
                    <rect x="88" y="57" width="12" height="5" rx="2" fill="#34d399" />
                    <rect x="126" y="51" width="18" height="3" rx="1.5" fill="#334155" />
                    <rect x="126" y="57" width="12" height="5" rx="2" fill="#fbbf24" />
                    <!-- Bottom row -->
                    <rect x="44" y="74" width="108" height="4" rx="2" fill="#1e293b" />
                    <rect x="44" y="82" width="80" height="4" rx="2" fill="#1e293b" />
                </svg>
            </div>

            <!-- Label -->
            <span class="text-sm font-medium text-foreground">{{ label }}</span>
        </button>
    </div>
</template>
