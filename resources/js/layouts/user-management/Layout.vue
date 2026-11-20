<script setup lang="ts">
// Sidebar moved to AppSidebar; this layout only renders the content slot.
</script>

<template>


        <div class="flex flex-col px-4 py-6">
            <section class="flex flex-col flex-1">
                <slot />
            </section>
        </div>
</template>
