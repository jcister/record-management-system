<script setup lang="ts">
import Heading from '@/components/layout/Heading.vue';
// No per-page settings nav: settings are available via user menu
</script>

<template>
    <div class="px-4 py-6">
        

        <div class="flex flex-col">
            <div class="flex-1 w-full">
                <section class="w-full space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
