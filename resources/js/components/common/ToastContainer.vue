<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

const { toasts, remove } = useToast();
</script>

<template>
    <div class="fixed bottom-4 right-4 z-50 flex w-[min(24rem,calc(100vw-1rem))] flex-col gap-2">
        <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2">
            <Alert
                v-for="toast in toasts"
                :key="toast.id"
                :variant="toast.variant"
                :class="[
                    'shadow-lg toast-item',
                    toast.fadingOut ? 'toast-fading-out' : ''
                ]"
            >
                <AlertTitle class="pr-8 wrap-break-word">{{ toast.title }}</AlertTitle>
                <AlertDescription v-if="toast.description" class="pr-8 wrap-break-word">
                    {{ toast.description }}
                </AlertDescription>
                <Button
                    variant="ghost"
                    size="icon"
                    class="absolute top-2 right-2 h-6 w-6"
                    @click="remove(toast.id)"
                >
                    <X class="h-4 w-4" />
                </Button>
            </Alert>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-item {
    transition: opacity 500ms ease-in-out, transform 500ms ease-in-out;
}

.toast-fading-out {
    opacity: 0;
    transform: translateX(100%);
}

.toast-enter-active {
    transition: all 0.3s ease-out;
}

.toast-leave-active {
    transition: all 2s ease-in;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
