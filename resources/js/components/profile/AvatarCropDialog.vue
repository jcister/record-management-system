<script setup lang="ts">
import Cropper from 'cropperjs';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import 'cropperjs/dist/cropper.css';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

interface Props {
    open: boolean;
    src: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    cancel: [];
    confirm: [file: File];
}>();

const cropImage = ref<HTMLImageElement | null>(null);
const cropperInstance = ref<Cropper | null>(null);
const cropZoom = ref(0);
const minZoomRatio = ref(0.1);
const maxZoomRatio = ref(2);
const guideDiameterRatio = 0.84;
const minZoomOutFactor = 1;

const openProxy = computed({
    get: () => props.open,
    set: (value: boolean) => {
        emit('update:open', value);
    },
});

const destroyCropper = () => {
    cropperInstance.value?.destroy();
    cropperInstance.value = null;
};

const applyZoom = () => {
    const cropper = cropperInstance.value;
    if (!cropper) {
        return;
    }

    const zoomRatio = minZoomRatio.value + ((maxZoomRatio.value - minZoomRatio.value) * cropZoom.value) / 100;
    cropper.zoomTo(zoomRatio);
};

const syncCropBoxToGuide = () => {
    const cropper = cropperInstance.value;
    if (!cropper) {
        return;
    }

    const containerData = cropper.getContainerData();
    const guideSize = Math.min(containerData.width, containerData.height) * guideDiameterRatio;

    cropper.setCropBoxData({
        width: guideSize,
        height: guideSize,
        left: (containerData.width - guideSize) / 2,
        top: (containerData.height - guideSize) / 2,
    });
};

const syncZoomBounds = () => {
    const cropper = cropperInstance.value;
    if (!cropper) {
        return;
    }

    const imageData = cropper.getImageData();
    const containerData = cropper.getContainerData();
    const guideSize = Math.min(containerData.width, containerData.height) * guideDiameterRatio;
    const fitRatio = Math.max(
        imageData.naturalWidth > 0 ? guideSize / imageData.naturalWidth : 0,
        imageData.naturalHeight > 0 ? guideSize / imageData.naturalHeight : 0,
        0.01,
    );

    minZoomRatio.value = Math.max(fitRatio * minZoomOutFactor, 0.01);
    maxZoomRatio.value = fitRatio * 4;
    cropZoom.value = 0;
    applyZoom();
};

const initializeCropper = async () => {
    if (!props.open || !props.src) {
        destroyCropper();
        return;
    }

    await nextTick();

    if (!cropImage.value) {
        return;
    }

    destroyCropper();

    cropperInstance.value = new Cropper(cropImage.value, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: guideDiameterRatio,
        modal: false,
        background: false,
        guides: false,
        center: false,
        highlight: false,
        movable: true,
        zoomable: true,
        cropBoxResizable: false,
        cropBoxMovable: false,
        responsive: true,
        checkOrientation: false,
        ready: () => {
            syncCropBoxToGuide();
            syncZoomBounds();
        },
    });
};

const handleCancel = () => {
    destroyCropper();
    cropZoom.value = 0;
    emit('cancel');
    emit('update:open', false);
};

const handleConfirm = () => {
    const cropper = cropperInstance.value;

    if (!cropper) {
        return;
    }

    const canvas = cropper.getCroppedCanvas({
        width: 512,
        height: 512,
        imageSmoothingQuality: 'high',
    });

    if (!canvas) {
        return;
    }

    canvas.toBlob(
        (blob) => {
            if (!blob) {
                return;
            }

            emit('confirm', new File([blob], 'avatar.jpg', { type: 'image/jpeg' }));
            destroyCropper();
            cropZoom.value = 0;
            emit('update:open', false);
        },
        'image/jpeg',
        0.9,
    );
};

watch(
    () => [props.open, props.src] as const,
    async ([open, src]) => {
        if (!open || !src) {
            destroyCropper();
            cropZoom.value = 0;
            return;
        }

        await initializeCropper();
    },
);

watch(cropZoom, () => {
    applyZoom();
});

onBeforeUnmount(() => {
    destroyCropper();
});
</script>

<template>
    <Dialog v-model:open="openProxy">
        <DialogContent class="max-w-[min(90vw,28rem)] gap-0 overflow-hidden p-0" :show-close-button="false">
            <DialogHeader class="px-6 pt-6 pb-2">
                <DialogTitle>Crop your photo</DialogTitle>
                <DialogDescription>
                    Drag the image to reposition, use the slider to zoom. The circle shows what will be used as your profile photo.
                </DialogDescription>
            </DialogHeader>

            <div class="avatar-crop-stage relative flex aspect-square min-h-64 w-full max-h-[60vh] items-center justify-center overflow-hidden bg-muted/50">
                <img
                    v-if="src"
                    ref="cropImage"
                    :src="src"
                    alt="Avatar source image"
                    class="block h-full w-full max-w-full"
                >

                <div class="avatar-crop-overlay pointer-events-none absolute inset-0">
                    <div class="avatar-crop-cutout absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full" />
                </div>
                <div class="avatar-crop-ring pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/90" />
            </div>

            <div class="flex items-center gap-4 px-6 py-3">
                <Label class="shrink-0 text-sm" for="avatar-crop-zoom">Zoom</Label>
                <input
                    id="avatar-crop-zoom"
                    v-model.number="cropZoom"
                    type="range"
                    min="0"
                    max="100"
                    class="h-2 flex-1 cursor-pointer appearance-none rounded-full bg-muted accent-primary"
                    aria-label="Zoom crop image"
                >
            </div>

            <DialogFooter class="px-6 pt-2 pb-6">
                <Button type="button" variant="outline" @click="handleCancel">
                    Cancel
                </Button>
                <Button type="button" @click="handleConfirm">
                    Use this photo
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.avatar-crop-stage {
    --avatar-guide-size: 84%;
}

.avatar-crop-cutout {
    width: var(--avatar-guide-size);
    height: var(--avatar-guide-size);
    box-shadow: 0 0 0 9999px rgb(0 0 0 / 0.55);
}

.avatar-crop-ring {
    width: var(--avatar-guide-size);
    height: var(--avatar-guide-size);
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.35);
}

:deep(.cropper-crop-box),
:deep(.cropper-view-box),
:deep(.cropper-face) {
    border: 0 !important;
    outline: 0 !important;
    box-shadow: none !important;

}

:deep(.cropper-dashed),
:deep(.cropper-line),
:deep(.cropper-point) {
    display: none !important;
}
</style>
