<script setup lang="ts">
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Badge } from '@/components/ui/badge';
import type { AuditLog } from '@/types/schema';

const props = defineProps<{
    open: boolean;
    log: AuditLog | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const actionLabels: Record<string, string> = {
    file_created: 'Created file',
    file_updated: 'Updated file',
    file_deleted: 'Moved to trash',
    file_restored: 'Restored file',
    file_force_deleted: 'Deleted permanently',
    file_approved: 'Approved file',
    file_rejected: 'Rejected file',
    file_locked: 'Locked file',
    file_unlocked: 'Unlocked file',
    file_visibility_changed: 'Visibility changed',
    file_downloaded: 'Downloaded file',
    file_viewed: 'Viewed file',
    file_version_restored: 'Restored version',
    file_version_created: 'New version',
    file_ocr_completed: 'OCR completed',
    file_ocr_failed: 'OCR failed',
    chunked_upload_assembled: 'Chunked upload assembled',
    chunked_upload_failed: 'Chunked upload failed',
    folder_created: 'Created folder',
    folder_updated: 'Updated folder',
    folder_moved: 'Moved folder',
    folder_deleted: 'Archived folder',
    folder_restored: 'Restored folder',
    folder_force_deleted: 'Deleted folder permanently',
    request_created: 'Created request',
    request_status_changed: 'Changed request status',
    request_file_attached: 'Attached request file',
    request_file_detached: 'Detached request file',
    request_scan_uploaded: 'Uploaded request scan',
    role_created: 'Created role',
    role_updated: 'Updated role',
    role_deleted: 'Deleted role',
    permission_assigned: 'Assigned permission',
    permission_revoked: 'Revoked permission',
    user_created: 'Created user',
    user_deleted: 'Deleted user',
    user_updated: 'Updated user',
    profile_updated: 'Updated profile',
    password_updated: 'Updated password',
    login: 'Signed in',
    login_success: 'Signed in',
    logout: 'Signed out',
    login_failed: 'Failed sign-in',
    password_reset: 'Password reset',
    email_verified: 'Email verified',
    disposal_scheduled: 'Scheduled disposal',
    disposal_completed: 'Completed disposal',
    disposal_inspected: 'Inspected disposal',
    disposal_approved: 'Approved disposal',
    disposal_rejected: 'Rejected disposal',
    disposal_unarchive: 'Unarchived from disposal',
};

const subjectLabels: Record<string, string> = {
    'App\\Models\\File': 'File',
    'App\\Models\\Folder': 'Folder',
    'App\\Models\\Requests': 'Request',
    'App\\Models\\User': 'User',
    'App\\Models\\Role': 'Role',
    'App\\Models\\Permission': 'Permission',
    'App\\Models\\Institution': 'Institution',
    'App\\Models\\Department': 'Department',
    'App\\Models\\DisposalBatch': 'Disposal Batch',
    'App\\Models\\DisposalBatchFile': 'Disposal File',
};

const formatAction = (action: string): string =>
    actionLabels[action] ?? action.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

const formatSubject = (auditableType?: string | null): string => {
    if (!auditableType) return '—';
    return subjectLabels[auditableType] ?? auditableType.split('\\').pop() ?? auditableType;
};

const formatTimestamp = (value?: string | null): string => {
    if (!value) return '—';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return '—';
    return parsed.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
};

const formatProperties = (log: AuditLog): string => {
    if (!log.properties) return '—';

    const p = log.properties as Record<string, unknown>;

    if (p.old_status && p.new_status) {
        return `${p.old_status} → ${p.new_status}`;
    }

    if (p.old_visibility && p.new_visibility) {
        return `${p.old_visibility} → ${p.new_visibility}`;
    }

    if (p.changes && typeof p.changes === 'object') {
        const entries = Object.entries(p.changes as Record<string, { from?: unknown; to?: unknown }>);
        if (entries.length === 0) return '—';
        return entries
            .map(([field, change]) => `${field}: ${change.from ?? '—'} → ${change.to ?? '—'}`)
            .join('\n');
    }

    if (p.file_name && typeof p.file_name === 'string') return p.file_name;
    if (p.request_title && typeof p.request_title === 'string') return p.request_title;

    if (log.action === 'file_ocr_completed' && typeof p.text_length === 'number') {
        return `Extracted ${p.text_length.toLocaleString()} characters`;
    }

    if (log.action === 'file_ocr_failed') {
        const err = typeof p.error === 'string' ? p.error : null;
        return err ?? 'Processing failed';
    }

    // Fallback: render key=value pairs for known scalar fields; skip guard/meta noise
    const readable = Object.entries(p)
        .filter(([, v]) => v !== null && typeof v !== 'object')
        .map(([k, v]) => `${k}: ${v}`)
        .join('\n');

    return readable || '—';
};

const handleOpenChange = (value: boolean): void => {
    emit('update:open', value);
};
</script>

<template>
    <Sheet :open="open" @update:open="handleOpenChange">
        <SheetContent
            side="right"
            class="flex w-full flex-col gap-0 overflow-y-auto sm:max-w-md"
        >
            <SheetHeader class="border-b px-6 py-5">
                <SheetTitle class="text-base font-semibold">Audit Entry Details</SheetTitle>
            </SheetHeader>

            <div v-if="log" class="flex flex-col gap-0 divide-y divide-border">
                <!-- Timestamp -->
                <div class="flex flex-col gap-1 px-6 py-4">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Timestamp</span>
                    <span class="text-sm">{{ formatTimestamp(log.created_at) }}</span>
                </div>

                <!-- Action -->
                <div class="flex flex-col gap-1.5 px-6 py-4">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Action</span>
                    <div class="flex items-center gap-2 flex-wrap">
                        <Badge variant="secondary" class="text-xs">{{ formatAction(log.action) }}</Badge>
                       
                    </div>
                </div>

                <!-- User -->
                <div class="flex flex-col gap-1 px-6 py-4">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">User</span>
                    <span class="text-sm">{{ log.user_name ?? log.user?.name ?? '—' }}</span>
                </div>

                <!-- Subject -->
                <div class="flex flex-col gap-1 px-6 py-4">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Subject</span>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-medium">{{ log.auditable_name ?? '—' }}</span>
                        <Badge
                            v-if="log.auditable_type"
                            variant="outline"
                            class="text-xs"
                        >
                            {{ formatSubject(log.auditable_type) }}
                        </Badge>
                    </div>
                </div>

                <!-- Description -->
                <div class="flex flex-col gap-1 px-6 py-4">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Description</span>
                    <p class="text-sm leading-relaxed">{{ log.description || '—' }}</p>
                </div>

                <!-- Details / Properties -->
                <div class="flex flex-col gap-1.5 px-6 py-4">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Details</span>
                    <div
                        v-if="formatProperties(log) !== '—'"
                        class="rounded-md bg-muted/50 px-3 py-2.5 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words"
                    >
                        {{ formatProperties(log) }}
                    </div>
                    <span v-else class="text-sm text-muted-foreground">—</span>
                </div>

                <!-- IP Address -->
                <div class="flex flex-col gap-1 px-6 py-4">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">IP Address</span>
                    <span class="text-sm">{{ log.ip_address ?? '—' }}</span>
                </div>

                <!-- User Agent -->
                <div class="flex flex-col gap-1 px-6 py-4">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wide">User Agent</span>
                    <span class="break-all text-xs text-muted-foreground leading-relaxed">{{ log.user_agent ?? '—' }}</span>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
