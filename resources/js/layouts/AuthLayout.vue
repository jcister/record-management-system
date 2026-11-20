<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        size?: 'sm' | 'lg';
    }>(),
    {
        size: 'sm',
    }
);
</script>

<template>
    <AuthLayout :title="props.title" :description="props.description" :size="props.size">
        <slot />
    </AuthLayout>
</template>
