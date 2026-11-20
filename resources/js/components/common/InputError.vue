<script setup lang="ts">
defineProps<{
    id?: string;
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p :id="id" class="text-sm text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
