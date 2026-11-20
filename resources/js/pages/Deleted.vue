<script setup lang="ts">
import axios from '@/lib/axios';
import { Head, router } from '@inertiajs/vue3';
import { FileText, Folder, RotateCcw, Trash2 } from 'lucide-vue-next';
import ViewModeContainer from '@/components/records/ViewModeContainer.vue';

import FilePreview from '@/components/records/FilePreview.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const { confirm: showConfirm } = useConfirm();
const { error, success } = useToast();

interface Folder {
    id: string;
    name: string;
    deleted_at: string;
}

interface File {
    id: string;
    file_name: string;
    file_size: number;
    mime_type: string;
    deleted_at: string;
}

defineProps<{
    folders: Folder[];
    files: File[];
}>();

import { useViewMode } from '@/composables/useViewMode';
const { viewMode } = useViewMode();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Deleted',
        href: '/deleted',
    },
];

const formatDate = (date: string): string => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const restoreFolder = async (folderId: string, event: Event) => {
    event.stopPropagation();

    const confirmed = await showConfirm({
        title: 'Restore Folder',
        description: 'Are you sure you want to restore this folder?',
        confirmText: 'Restore',
        cancelText: 'Cancel'
    });

    if (confirmed !== 'confirm') {
        return;
    }

    try {
        await axios.post(`/api/folders/${folderId}/restore`);
        router.reload({ only: ['folders'] });
        success('Folder restored successfully');
    } catch {
        error('Failed to restore folder', 'Please try again.');
    }
};

const restoreFile = async (fileId: string, event: Event) => {
    event.stopPropagation();

    const confirmed = await showConfirm({
        title: 'Restore File',
        description: 'Are you sure you want to restore this file?',
        confirmText: 'Restore',
        cancelText: 'Cancel'
    });

    if (confirmed !== 'confirm') {
        return;
    }

    try {
        await axios.post(`/api/files/${fileId}/restore`);
        router.reload({ only: ['files'] });
        success('File restored successfully');
    } catch {
        error('Failed to restore file', 'Please try again.');
    }
};

const permanentlyDeleteFolder = async (folderId: string, event: Event) => {
    event.stopPropagation();

    const confirmed = await showConfirm({
        title: 'Permanently Delete Folder',
        description: 'This action cannot be undone! Are you sure you want to permanently delete this folder?',
        confirmText: 'Delete Forever',
        cancelText: 'Cancel'
    });

    if (confirmed !== 'confirm') {
        return;
    }

    try {
        await axios.delete(`/api/folders/${folderId}/force`);
        router.reload({ only: ['folders'] });
        success('Folder permanently deleted');
    } catch {
        error('Failed to permanently delete folder', 'Please try again.');
    }
};

const permanentlyDeleteFile = async (fileId: string, event: Event) => {
    event.stopPropagation();

    const confirmed = await showConfirm({
        title: 'Permanently Delete File',
        description: 'This action cannot be undone! Are you sure you want to permanently delete this file?',
        confirmText: 'Delete Forever',
        cancelText: 'Cancel'
    });

    if (confirmed !== 'confirm') {
        return;
    }

    try {
        await axios.delete(`/api/files/${fileId}/force`);
        router.reload({ only: ['files'] });
        success('File permanently deleted');
    } catch {
        error('Failed to permanently delete file', 'Please try again.');
    }
};
</script>

<template>
    <Head title="Deleted Items" />

    <AppLayout :breadcrumbs="breadcrumbs" :showLayoutToggle="true">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Deleted Items</h1>
                <p class="text-muted-foreground mt-2">
                    Restore or permanently delete items
                </p>
            </div>

            <!-- Deleted Folders Section -->
            <div v-if="folders.length > 0">
                <h2 class="text-lg font-semibold mb-3">Deleted Folders</h2>
                <ViewModeContainer>
                    <template #tiles>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <Card
                                v-for="folder in folders"
                                :key="folder.id"
                                class="transition-all hover:shadow-lg group relative"
                            >
                                <div class="absolute top-2 right-2 flex gap-1 z-10">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity bg-background"
                                        @click="(e: Event) => restoreFolder(folder.id, e)"
                                    >
                                        <RotateCcw class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity"
                                        @click="(e: Event) => permanentlyDeleteFolder(folder.id, e)"
                                    >
                                        <Trash2 class="h-4 w-4 fill-none stroke-current text-destructive" />
                                    </Button>
                                </div>
                                <CardContent class="p-6">
                                    <div class="flex flex-col items-center text-center space-y-4">
                                        <div class="relative opacity-50">
                                            <Folder class="h-20 w-20 text-blue-500" />
                                        </div>
                                        <div class="w-full">
                                            <h3 class="font-semibold text-lg truncate">{{ folder.name }}</h3>
                                            <p class="text-xs text-muted-foreground mt-1">
                                                Deleted {{ formatDate(folder.deleted_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>\
                    </template>

                    <template #list>
                        <div class="w-full">
                                <div class="mb-2 flex i tems-center border-b border-sidebar-border/60 pb-2 text-sm text-muted-foreground">
                                <div class="w-full sm:w-1/2 font-semibold">File name</div>
                                <div class="hidden sm:block sm:w-1/6 font-semibold">Date</div>
                                <div class="hidden sm:block sm:w-1/6 font-semibold">Size</div>
                                <div class="w-full sm:w-1/6 font-semibold text-right">Actions</div>
                            </div>

                            <div>
                                <div v-for="folder in folders" :key="folder.id" class="flex flex-col sm:flex-row items-start sm:items-center py-3 border-b last:border-b-0 hover:bg-accent/5">
                                    <div class="w-full sm:w-1/2 flex items-center gap-3">
                                        <Folder class="h-5 w-5 text-blue-500" />
                                        <div class="truncate">{{ folder.name }}</div>
                                    </div>
                                    <div class="w-full sm:w-1/6 text-xs text-muted-foreground mt-1 sm:mt-0">{{ formatDate(folder.deleted_at) }}</div>
                                    <div class="w-full sm:w-1/6 text-xs text-muted-foreground mt-1 sm:mt-0">-</div>
                                    <div class="w-full sm:w-1/6 text-right mt-2 sm:mt-0">
                                        <Button variant="outline" size="icon" class="mr-2" @click="(e: Event) => restoreFolder(folder.id, e)">
                                            <RotateCcw class="h-4 w-4" />
                                        </Button>
                                        <Button variant="destructive" size="icon" @click="(e: Event) => permanentlyDeleteFolder(folder.id, e)">
                                            <Trash2 class="h-4 w-4 fill-none stroke-current text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </ViewModeContainer>
            </div>

            <!-- Deleted Files Section -->
            <div v-if="files.length > 0">
                <h2 class="text-lg font-semibold mb-3">Deleted Files</h2>

                <ViewModeContainer>
                    <template #tiles>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <Card
                                v-for="file in files"
                                :key="file.id"
                                class="transition-all hover:shadow-lg group relative"
                            >
                                <div class="absolute top-2 right-2 flex gap-1 z-10">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity bg-background"
                                        @click="(e: Event) => restoreFile(file.id, e)"
                                    >
                                        <RotateCcw class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        class="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity"
                                        @click="(e: Event) => permanentlyDeleteFile(file.id, e)"
                                    >
                                        <Trash2 class="h-4 w-4 fill-none stroke-current text-destructive" />
                                    </Button>
                                </div>
                                <CardContent class="p-6">
                                    <div class="flex flex-col items-center text-center space-y-4">
                                        <div class="relative opacity-50">
                                            <FileText class="h-20 w-20 text-green-500" />
                                        </div>
                                        <div class="w-full">
                                            <h3 class="font-semibold text-base truncate" :title="file.file_name">
                                                {{ file.file_name }}
                                            </h3>
                                            <p class="text-xs text-muted-foreground mt-1">
                                                {{ formatFileSize(file.file_size) }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                Deleted {{ formatDate(file.deleted_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </template>

                    <template #list>
                        <div>
                            <div class="mb-2 flex items-center border-b border-sidebar-border/60 pb-2 text-sm text-muted-foreground">
                                <div class="w-full sm:w-1/2 font-semibold">File name</div>
                                <div class="hidden sm:block sm:w-1/6 font-semibold">Date</div>
                                <div class="hidden sm:block sm:w-1/6 font-semibold">Size</div>
                                <div class="w-full sm:w-1/6 font-semibold text-right">Actions</div>
                            </div>

                            <div>
                                <div v-for="file in files" :key="file.id" class="flex flex-col sm:flex-row items-start sm:items-center py-3 border-b last:border-b-0 hover:bg-accent/5">
                                    <div class="w-full sm:w-1/2 flex items-center gap-3">
                                        <FileText class="h-5 w-5 text-green-500" />
                                        <div class="truncate" :title="file.file_name">{{ file.file_name }}</div>
                                    </div>
                                    <div class="w-full sm:w-1/6 text-xs text-muted-foreground mt-1 sm:mt-0">{{ formatDate(file.deleted_at) }}</div>
                                    <div class="w-full sm:w-1/6 text-xs text-muted-foreground mt-1 sm:mt-0">{{ formatFileSize(file.file_size) }}</div>
                                    <div class="w-full sm:w-1/6 text-right mt-2 sm:mt-0">
                                        <Button variant="outline" size="icon" class="mr-2" @click="(e: Event) => restoreFile(file.id, e)">
                                            <RotateCcw class="h-4 w-4" />
                                        </Button>
                                        <Button variant="outline" class="text-red-500" size="icon" @click="(e: Event) => permanentlyDeleteFile(file.id, e)">
                                            <Trash2 class="h-4 w-4 " />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </ViewModeContainer>
            </div>

            <!-- Empty State -->
            <div v-if="folders.length === 0 && files.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
                <Trash2 class="h-24 w-24 fill-none stroke-current text-muted-foreground/50 mb-4" />
                <h2 class="text-xl font-semibold mb-2">No deleted items</h2>
                <p class="text-muted-foreground max-w-md">
                    Items you delete will appear here and can be restored or permanently deleted.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
