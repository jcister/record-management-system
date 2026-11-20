<script setup lang="ts">
import { Maximize2, Minimize2, RefreshCw } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';

import { Button } from '@/components/ui/button';

interface Props {
    pdfUrl: string | null;
    hasGeneratedPreview: boolean;
    isSyncing: boolean;
    errorMessage: string | null;
    title?: string;
    iframeTitle?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'PDF Preview',
    iframeTitle: 'PDF Preview',
});

const emit = defineEmits<{
    refresh: [];
}>();

const paneRef = ref<HTMLElement | null>(null);
const isFullscreen = ref(false);

const toggleFullscreen = () => {
    if (!paneRef.value) return;

    if (!document.fullscreenElement) {
        paneRef.value.requestFullscreen()
            .then(() => {
                isFullscreen.value = true;
            })
            .catch((err) => {
                console.error('Error enabling fullscreen:', err);
            });
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
};

const handleFullscreenChange = () => {
    isFullscreen.value = document.fullscreenElement === paneRef.value;
};

onMounted(() => {
    if (typeof document !== 'undefined') {
        document.addEventListener('fullscreenchange', handleFullscreenChange);
    }
});

onUnmounted(() => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('fullscreenchange', handleFullscreenChange);
    }
});
</script>

<template>
    <div ref="paneRef" class="flex h-full flex-col bg-gray-600 w-full">
        <div class="flex items-center justify-between border-b bg-card px-4 py-2 shrink-0 select-none">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-card-foreground">{{ props.title }}</span>
                <span v-if="isFullscreen" class="text-xs text-muted-foreground bg-muted px-1.5 py-0.5 rounded">Full Screen Mode</span>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" :disabled="props.isSyncing" @click="emit('refresh')">
                    <RefreshCw class="mr-2 h-4 w-4" :class="props.isSyncing ? 'animate-spin' : ''" />
                    {{ props.isSyncing ? 'Syncing...' : 'Sync PDF' }}
                </Button>
                <Button variant="outline" size="icon" class="h-8 w-8" :disabled="!props.hasGeneratedPreview" @click="toggleFullscreen" :title="isFullscreen ? 'Exit Full Screen' : 'View Full Screen'">
                    <Minimize2 v-if="isFullscreen" class="h-4 w-4" />
                    <Maximize2 v-else class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <div class="flex-1 overflow-auto p-2" :class="isFullscreen ? 'h-[calc(100vh-3rem)]' : ''">
            <div
                v-if="props.errorMessage"
                class="mb-2 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive"
            >
                {{ props.errorMessage }}
            </div>

            <div
                v-if="!props.hasGeneratedPreview"
                class="flex h-full min-h-55 items-center justify-center rounded-lg border border-dashed border-border bg-card/70 p-6 text-center"
            >
                <div>
                    <p class="text-sm font-medium text-foreground">No generated PDF yet</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Click Sync PDF to generate and display the exact PDF output.
                    </p>
                </div>
            </div>

            <iframe
                v-else-if="props.pdfUrl"
                :src="props.pdfUrl"
                class="h-full min-h-180 w-full rounded-md border bg-white"
                :title="props.iframeTitle"
            />
        </div>
    </div>
</template>
