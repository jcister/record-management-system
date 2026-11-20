import type { File } from '@/types/schema';

export interface FileMetadata {
    id: string;
    is_record?: boolean;
    file_name: string;
    file_size?: number;
    mime_type?: string;
    record_id: string;
    department_id: string;
    date_created?: string;
    date_released?: string;
    retention_duration?: 'one_year' | 'ten_years' | 'perpetual';
    retention_status?: 'active' | 'archived' | 'ready_for_disposal' | 'disposed';
    physical_location: string;
    author: string;
    folder_id: string | null;
    visibility: 'public' | 'restricted' | 'private';
    status: 'pending' | 'approved' | 'rejected';
    metadata_tag_ids?: number[];
    physical_location_tag_ids?: number[];
}

/**
 * Build file metadata for the update dialog
 */
export function buildFileMetadata(file: File): FileMetadata {
    return {
        id: file.id,
        is_record: file.is_record ?? true,
        file_name: file.file_name,
        file_size: file.file_size,
        mime_type: file.mime_type,
        record_id: file.record_id ?? '',
        department_id: file.department_id ?? '',
        date_created: file.date_created ?? undefined,
        date_released: file.date_released ?? undefined,
        retention_duration: file.retention_duration ?? undefined,
        retention_status: file.retention_status ?? undefined,
        physical_location: file.physical_location ?? '',
        author: file.author ?? '',
        folder_id: file.folder_id ?? null,
        visibility: file.visibility ?? 'public',
        status: file.status ?? 'approved',
        metadata_tag_ids: file.metadata_tag_ids ?? [],
        physical_location_tag_ids: file.physical_location_tag_ids ?? [],
    };
}
