<script setup lang="ts">
import { Check, Pipette } from 'lucide-vue-next';

import { useIconColor } from '@/composables/useIconColor';

const { iconColor, activePresetKey, updateIconColor, iconColorOptions } = useIconColor();
</script>

<template>
    <div class="flex flex-wrap gap-3">
        <!-- Preset color swatches -->
        <button
            v-for="option in iconColorOptions"
            :key="option.key"
            type="button"
            @click="updateIconColor(option.value)"
            :title="option.label"
            :class="[
                'relative flex h-10 w-10 items-center justify-center rounded-full border-2 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                activePresetKey === option.key
                    ? 'border-primary shadow-md ring-2 ring-primary ring-offset-2'
                    : 'border-border hover:border-neutral-400 hover:shadow-sm dark:hover:border-neutral-500',
            ]"
        >
            <!-- Default swatch -->
            <span
                v-if="option.key === 'default'"
                class="flex h-6 w-6 items-center justify-center rounded-full bg-foreground/10"
            >
                <span class="h-3 w-3 rounded-full bg-foreground/60" />
            </span>

            <!-- Colored preset swatch -->
            <span
                v-else
                class="h-6 w-6 rounded-full"
                :style="{ backgroundColor: option.swatch }"
            />

            <!-- Active checkmark -->
            <span
                v-if="activePresetKey === option.key"
                class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-primary-foreground shadow"
            >
                <Check class="h-2.5 w-2.5" />
            </span>
        </button>

        <!-- Custom color swatch (opens native color picker) -->
        <label
            title="Custom color"
            :class="[
                'relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border-2 transition-all duration-200 focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                activePresetKey === 'custom'
                    ? 'border-primary shadow-md ring-2 ring-primary ring-offset-2'
                    : 'border-border hover:border-neutral-400 hover:shadow-sm dark:hover:border-neutral-500',
            ]"
        >
            <input
                type="color"
                :value="activePresetKey === 'custom' ? iconColor : '#3b82f6'"
                @input="updateIconColor(($event.target as HTMLInputElement).value)"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                aria-label="Pick a custom color"
            />

            <!-- Show chosen custom color fill or a rainbow gradient -->
            <span
                v-if="activePresetKey === 'custom'"
                class="h-6 w-6 rounded-full border border-white/20 shadow-sm"
                :style="{ backgroundColor: iconColor }"
            />
            <span
                v-else
                class="flex h-6 w-6 items-center justify-center rounded-full"
                style="background: conic-gradient(red, yellow, lime, cyan, blue, magenta, red)"
            >
                <Pipette class="h-3 w-3 text-white drop-shadow" />
            </span>

            <!-- Active checkmark -->
            <span
                v-if="activePresetKey === 'custom'"
                class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-primary-foreground shadow"
            >
                <Check class="h-2.5 w-2.5" />
            </span>
        </label>
    </div>

    <!-- Label for selected color -->
    <p class="mt-3 text-sm text-muted-foreground">
        <span class="font-medium text-foreground">
            <template v-if="activePresetKey === 'custom'">
                Custom
                <span class="ml-1 font-mono text-xs text-muted-foreground">{{ iconColor }}</span>
            </template>
            <template v-else>
                {{ iconColorOptions.find((o) => o.key === activePresetKey)?.label ?? 'Default' }}
            </template>
        </span>
        — applies to all sidebar navigation icons.
    </p>
</template>
