export interface DepartmentOption {
    id: string | number;
    name: string;
}

export interface UserOption {
    id: string;
    name: string;
}

export interface FileUploadFormData {
    author: string;
    department_id: string;
    date_created: string;
    date_released: string;
    record_id: string;
    file_name: string;
    mime_type: string;
    file_size: number;
    folder_id: string | null;
    visibility: 'public' | 'restricted' | 'private';
    status: 'pending' | 'approved' | 'rejected';
    metadata_tag_ids: number[];
    physical_location_tag_ids: number[];
    retention_duration: '' | RetentionDuration;
    retention_status: RetentionStatus;
}

export interface DateValidationPayload {
    isValid: boolean;
    message?: string;
}

export interface Tag {
    id: number;
    name: string;
}

export type ComboboxListItem = { value: string };

export type UploadDialogMode = 'record' | 'fileOnly';

export type RetentionDuration = 'one_year' | 'ten_years' | 'perpetual';

export type RetentionStatus = 'active' | 'archived' | 'ready_for_disposal' | 'disposed';
