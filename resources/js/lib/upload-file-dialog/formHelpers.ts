import type { FileUploadFormData } from './types';

export const buildEmptyUploadFormData = (folderId?: string | null): FileUploadFormData => ({
    author: '',
    department_id: '',
    date_created: '',
    date_released: '',
    record_id: '',
    file_name: '',
    mime_type: '',
    file_size: 0,
    folder_id: folderId ?? null,
    visibility: 'public',
    status: 'approved',
    metadata_tag_ids: [],
    physical_location_tag_ids: [],
    retention_duration: '',
    retention_status: 'active',
});
