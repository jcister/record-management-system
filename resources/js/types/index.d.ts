import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';
import type { PendingScanMetadata } from './schema';

export interface Auth {
    user: User;
    can?: Record<string, boolean> & {
        organizeContent: boolean;
        deleteContent: boolean;
        reviewContent: boolean;
        manageUsers: boolean;
        manageDisposal: boolean;
        manageRequests: boolean;
        manageReports: boolean;
        manageAgencyInformation: boolean;
        viewAuditTrail: boolean;
    };
}

export interface BreadcrumbItem {
    title: string;
    description?: string;
    href: string;
}

export interface SelectedItem {
    id: string;
    type: 'folder' | 'file';
    name: string;
    isBookmarked?: boolean;
    bookmarkId?: string | null;
    currentFolderId?: string | null;
    hasFile?: boolean;
    status?: 'draft' | 'pending' | 'approved' | 'rejected';
    pendingScanMetadata?: PendingScanMetadata | null;
}

export type NavItemKind = 'menuitem' | 'delimiter';

export interface NavItem {
    kind?: NavItemKind;
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    permission?: string;
    children?: NavItem[];
    hidePrimary?: boolean;
    defaultOpen?: boolean;
}

export interface NotificationData {
    type:
        | 'new_request'
        | 'record_updated'
        | 'request_status_updated'
        | 'request_will_return_copy_changed'
        | 'scan_submitted_for_review'
        | 'scan_approved'
        | 'scan_rejected'
        | 'record_disposal_approaching'
        | 'retention_duration_reached'
        | 'disposal_batch_schedule_reminder';
    message: string;
    link: string;
    request_id?: string;
    request_title?: string;
    requested_by?: string;
    old_status?: string;
    new_status?: string;
    file_id?: string;
    record_id?: string;
    file_name?: string;
    actor?: string;
    approver?: string;
    rejector?: string;
    uploader?: string;
    reason?: string;
    days_until_expiry?: number;
    days_until_scheduled?: number;
    urgency?: 'warning' | 'critical' | 'today' | 'upcoming';
    batch_id?: string;
    reference?: string;
    file_count?: number;
    will_return_copy?: boolean;
}

export interface AppNotification {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
}

export type NotificationSseEvent =
    | {
          event: 'notification_created';
          notification: AppNotification;
          unread_count: number;
          ts: number;
      }
    | {
          event: 'notification_read';
          notification_id: string;
          read_at: string;
          unread_count: number;
          ts: number;
      }
    | {
          event: 'notifications_all_read';
          notification_ids: string[];
          read_at: string;
          unread_count: number;
          ts: number;
      };

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    unreadNotificationsCount: number;
    [key: string]: unknown;
};

export interface User {
    id: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    department?: {
        id: string;
        name: string
    } | null;
    unit?: {
        id: string;
        name: string;
    } | null;
    contact_number?: string | null;
    created_at: string;
    updated_at: string;
    roles?: Array<{
        id: string;
        name: string;
        slug: string;
    }>;
    [key: string]: unknown; // This allows for additional properties...
}

export type BreadcrumbItemType = BreadcrumbItem;
export type SelectedItemType = SelectedItem;

// Global route helper function
declare global {
    function route(name: string, params?: Record<string, unknown>): string;
}
