export interface PendingScanMetadata {
    type: 'request_scan';
    request_id: number;
    request_number?: string | null;
    request_title?: string | null;
    target_file_id: string;
    target_file_record_id?: string | null;
    target_file_name?: string | null;
}

export interface TagSchema {
    id: number;
    name: string;
    type: 'metadata' | 'physical_location';
}

export interface File {
    id: string;
    is_record?: boolean;
    file_name: string;
    file_size: number;
    mime_type: string;
    created_at: string;
    file_path?: string | null;
    record_id?: string;
    author?: string;
    department_id?: string;
    date_created?: string | null;
    date_released?: string | null;
    retention_duration?: 'one_year' | 'ten_years' | 'perpetual' | null;
    retention_expires_at?: string | null;
    retention_status?: 'active' | 'archived' | 'ready_for_disposal' | 'disposed' | null;
    physical_location?: string;
    folder_id?: string | null;
    // folder?: { id: string; name: string } | null;
    visibility?: 'public' | 'restricted' | 'private';
    status?: 'draft' | 'pending' | 'approved' | 'rejected';
    assembly_status?: 'processing' | 'completed' | 'failed' | null;
    ocr_status?: 'pending' | 'processing' | 'completed' | 'failed' | null;
    ocr_text?: string | null;
    is_bookmarked?: boolean;
    bookmark_id?: string;
    latest_version_number?: number;
    current_version_id?: string | null;
    current_version?: FileVersion | null;
    versions?: FileVersion[];
    updated_at?: string;
    deleted_at: string;
    restored_at?: string | null;
    remarks?: string | null;
    pending_scan_metadata?: PendingScanMetadata | null;
    scan_review_notes?: string | null;
    requests?: RequestRecord[];
    folder?: FolderSchema | null;
    metadata_tag_ids?: number[];
    physical_location_tag_ids?: number[];
    tags?: TagSchema[];
}

export interface FileVersion {
    id: string;
    file_id: string;
    version_number: number;
    file_name: string;
    mime_type: string | null;
    file_size: number | null;
    checksum?: string | null;
    version_label?: string | null;
    change_summary?: string | null;
    uploaded_by: string;
    created_at: string;
    ocr_status?: 'pending' | 'processing' | 'completed' | 'failed';
    uploader?: { id: string; name: string } | null;
    thumbnail_url?: string | null;
    thumbnail_urls?: { webp?: string | null; jpeg?: string | null };
    thumbnail_generated_at?: string | null;
}

export interface FolderSchema {
    id: string;
    name: string;
    created_at: string;
    updated_at?: string;
    children_count?: number;
    files_count?: number;
    is_bookmarked?: boolean;
    bookmark_id?: string;
    deleted_at: string;
}


export interface Department {
    id: string;
    name: string;
}

export interface Bookmark {
    id: string;
    bookmarkable_id: string;
    bookmarkable_type: string;
    created_at: string;
    bookmarkable: FolderSchema | File | null;
}

export interface RequestRecord {
    id: number;
    request_id: string;
    department_id: string;
    /** may be either a user id string OR the related user object returned by the API */
    requested_by: string | { id: string; name: string; email: string };
    document_type?: string | null;
    file_number: string | null;
    start_date: string | null;
    end_date: string | null;
    request_title: string;
    purpose: string;
    status: 'Pending' | 'Claimed' | 'Awaiting Update' | 'In Review' | 'Complete' | 'Rejected';
    notes: string | null;
    required_format: 'Retrieve Original Copy' | 'Reproduce Hardcopy' | 'Electronic Copy Only' | null;
    priority: 'standard' | 'urgent';
    requires_scan: boolean;
    scan_review_status?: 'In Review' | 'Approved' | 'Rejected' | null;
    scan_review_notes?: string | null;
    created_at: string;
    updated_at: string;
    requested_by_relation?: { id: string; name: string; email: string };
    department?: Department;
    files?: File[];
    metadata_tags?: TagSchema[];
    metadata_tag_ids?: number[];
}

export interface AuditLog {
    id: number | string;
    user_id?: string | null;
    user_name?: string | null;
    action: string;
    description: string;
    properties: Record<string, unknown> | null;
    auditable_type?: string | null;
    auditable_id?: string | number | null;
    auditable_name?: string | null;
    created_at: string;
    ip_address?: string | null;
    user_agent?: string | null;
    user?: { id: string; name: string; email: string } | null;
    auditable?: { id: number | string; request_title?: string; status?: string; name?: string } | null;
    source?: 'audit' | 'disposal';
}
