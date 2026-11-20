<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

interface Props {
    open: boolean;
    imageUrl: string;
    imageName: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const handleOpenChange = (value: boolean) => {
    emit('update:open', value);
};
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent class="max-w-[95vw] sm:max-w-[90vw] md:max-w-4xl max-h-[95vh] overflow-auto">
            <DialogHeader>
                <DialogTitle class="wrap-break-word pr-8" :title="props.imageName">
                    {{ props.imageName }}
                </DialogTitle>
                <DialogDescription>
                    Preview of selected image
                </DialogDescription>
            </DialogHeader>
            <div class="flex items-center justify-center overflow-hidden">
                <img
                    :src="props.imageUrl"
                    :alt="props.imageName"
                    class="max-h-[70vh] max-w-full w-auto h-auto rounded-lg object-contain"
                />
            </div>
        </DialogContent>
    </Dialog>
</template>
