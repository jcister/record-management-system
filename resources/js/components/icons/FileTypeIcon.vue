<script setup lang="ts">
import { computed, type Component } from 'vue';
import { Image, FileText, FileVideo } from 'lucide-vue-next';

import { detectFileCategory, type FileCategory } from '@/utils/fileTypes';

const props = defineProps<{
    mimeType?: string | null;
    filename?: string | null;
}>();

interface IconMeta {
    component: Component;
    color: string;
}

// Simplified mapping per request:
// - images => Image
// - videos => FilePlay
// - all other types => FileText
const categoryMeta: Record<FileCategory, IconMeta> = {
    image: { component: Image, color: 'text-indigo-500' },
    video: { component: FileVideo, color: 'text-orange-500' },        // Video - Orange
    audio: { component: FileText, color: 'text-yellow-500' },     // Music - Yellow
    pdf: { component: FileText, color: 'text-red-500' },          // PDF - Red
    document: { component: FileText, color: 'text-red-500' },     // Document - Red
    spreadsheet: { component: FileText, color: 'text-emerald-500' }, // Data/Spreadsheets - Green
    presentation: { component: FileText, color: 'text-blue-500' }, // Presentation - Blue
    text: { component: FileText, color: 'text-blue-500' },        // Web (text/html) - Blue
    archive: { component: FileText, color: 'text-gray-500' },     // System Compressed - Grey
    unknown: { component: FileText, color: 'text-muted-foreground' },
};

const iconMeta = computed(() => {
    const category = detectFileCategory(props.mimeType, props.filename);
    return categoryMeta[category] ?? categoryMeta.unknown;
});
</script>

<template>
    <component :is="iconMeta.component" :class="['transition-colors duration-200', iconMeta.color]" />
</template>
