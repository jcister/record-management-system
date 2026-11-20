<script setup lang="ts">
import { RefreshCw } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';

interface Props {
    pdfUrl: string | null;
    hasGeneratedPreview: boolean;
    isSyncing: boolean;
    errorMessage: string | null;
}

defineProps<Props>();

const emit = defineEmits<{
    refresh: [];
}>();
</script>

<template>
    <div class="flex h-full flex-col bg-gray-600">
        <div class="flex items-center justify-between border-b bg-card px-4 py-2 shrink-0">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium">PDF Preview</span>
            </div>
            <Button variant="outline" size="sm" :disabled="isSyncing" @click="emit('refresh')">
                <RefreshCw class="mr-2 h-4 w-4" :class="isSyncing ? 'animate-spin' : ''" />
                {{ isSyncing ? 'Syncing...' : 'Sync PDF' }}
            </Button>
        </div>

        <div class="flex-1 overflow-auto p-2">
            <div v-if="errorMessage" class="mb-2 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                {{ errorMessage }}
            </div>

            <div v-if="!hasGeneratedPreview" class="flex h-full min-h-55 items-center justify-center rounded-lg border border-dashed border-border bg-card/70 p-6 text-center">
                <div>
                    <p class="text-sm font-medium text-foreground">No generated PDF yet</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Click Sync PDF to generate and display the exact PDF output.
                    </p>
                </div>
            </div>

            <iframe
                v-else-if="pdfUrl"
                :src="pdfUrl"
                class="h-full min-h-180 w-full rounded-md border bg-white"
                title="NAP Form 1 PDF Preview"
            />
        </div>
    </div>
</template>
