<script setup lang="ts">
import { computed } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Badge } from '@/components/ui/badge';
import {
    FileText,
    User,
    Building2,
    FolderOpen,
    Tag as TagIcon,
    Calendar,
    Clock,
    Shield,
} from 'lucide-vue-next';

interface Tag {
    id: number;
    name: string;
    type: string;
}

interface RecordItem {
    id: string;
    record_id: string;
    file_name: string;
    author: string | null;
    department: string | null;
    folder: string | null;
    tags: Tag[];
    status: string;
    retention_status: string;
    date_created: string | null;
    retention_expires_at: string | null;
}

const props = defineProps<{
    record: RecordItem | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const statusVariant = computed(() => {
    const statusMap: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        approved: 'default',
        pending: 'secondary',
        rejected: 'destructive',
    };
    return statusMap[props.record?.status?.toLowerCase() ?? ''] ?? 'outline';
});

const formattedRetentionStatus = computed(() => {
    const raw = props.record?.retention_status?.toLowerCase() ?? '';

    if (raw === 'active' || raw === 'archived') return 'ACTIVE';
    if (raw === 'ready_for_disposal') return 'READY TO BE DISPOSED';
    if (raw === 'disposed') return 'DISPOSED';

    if (raw) return raw.replace(/_/g, ' ').toUpperCase();

    return 'N/A';
});
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent class="w-full sm:max-w-105 overflow-y-auto p-0">
            <div v-if="record" class="flex flex-col h-full">
                <!-- Header -->
                <SheetHeader class="px-4 sm:px-6 py-3 sm:py-4 border-b">
                    <SheetTitle class="flex flex-wrap gap-2 text-sm sm:text-base">
                        <div class="text-xs sm:text-sm">{{ record.record_id }}</div>
                        <Badge :variant="statusVariant" class="text-[10px] sm:text-xs">{{ record.status }}</Badge>
                    </SheetTitle>
                </SheetHeader>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-4 sm:py-6">
                    <!-- Title Section -->
                    <div class="mb-3">
                        <h2 class="text-base sm:text-lg lg:text-xl font-normal mb-1 wrap-break-word">{{ record.file_name }}</h2>
                    </div>

                    <h1 class="border-t mt-3 pt-3 text-sm font-medium">Record Details</h1>

                    <!-- Info Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-4 sm:mb-6 mt-3">
                        <div class="flex items-start gap-2 sm:gap-3">
                            <FileText class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Record ID</div>
                                <div class="text-xs sm:text-sm leading-tight wrap-break-word">{{ record.record_id }}</div>
                            </div>
                        </div>

                        <div v-if="record.author" class="flex items-start gap-2 sm:gap-3">
                            <User class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Author</div>
                                <div class="text-xs sm:text-sm wrap-break-word">{{ record.author }}</div>
                            </div>
                        </div>

                        <div v-if="record.department" class="flex items-start gap-2 sm:gap-3">
                            <Building2 class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Division</div>
                                <div class="text-xs sm:text-sm wrap-break-word">{{ record.department }}</div>
                            </div>
                        </div>

                        <div v-if="record.folder" class="flex items-start gap-2 sm:gap-3">
                            <FolderOpen class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Folder</div>
                                <div class="text-xs sm:text-sm wrap-break-word">{{ record.folder }}</div>
                            </div>
                        </div>

                        <div class="flex items-start gap-2 sm:gap-3">
                            <TagIcon class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Tags</div>
                                <div v-if="record.tags.length" class="flex flex-wrap gap-1 sm:gap-1.5">
                                    <Badge v-for="tag in record.tags" :key="tag.id" variant="outline"
                                        class="text-[10px] sm:text-[11px] border-muted-foreground/40">
                                        {{ tag.name }}
                                    </Badge>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">No tags</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-2 sm:gap-3">
                            <Shield class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Retention Status</div>
                                <Badge
                                    :class="[
                                        'text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5 border capitalize',
                                        record.retention_status?.toLowerCase() === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-300 dark:border-green-800' :
                                        record.retention_status?.toLowerCase() === 'archived' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-300 dark:border-green-800' :
                                        record.retention_status?.toLowerCase() === 'ready_for_disposal' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-300 dark:border-amber-800' :
                                        record.retention_status?.toLowerCase() === 'disposed' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-800' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400 border-gray-300 dark:border-gray-800'
                                    ]">
                                    {{ formattedRetentionStatus }}
                                </Badge>
                            </div>
                        </div>

                        <div v-if="record.date_created" class="flex items-start gap-2 sm:gap-3">
                            <Calendar class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Created</div>
                                <div class="text-xs sm:text-sm wrap-break-word">{{ record.date_created }}</div>
                            </div>
                        </div>

                        <div v-if="record.retention_expires_at" class="flex items-start gap-2 sm:gap-3">
                            <Clock class="h-3.5 w-3.5 sm:h-4 sm:w-4 mt-0.5 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-bold mb-0.5">Expires</div>
                                <div class="text-xs sm:text-sm wrap-break-word">{{ record.retention_expires_at }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
