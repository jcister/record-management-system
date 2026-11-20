# Product Requirements Document (PRD)

## 1. Product Overview
A secure document rack for institutions to store, organize, review, and distribute files with granular access control, approvals, and auditability.

## 2. Goals & Success
- Reduce time to find or share an institutional file to < 30 seconds from dashboard/search.
- Ensure every external file download is attributable (log with user, IP, UA) and policy-compliant.
- Keep review/approval SLA under 2 business days; surface pending counts on dashboard.
- Zero orphan data: every file is linked to an institution and a folder hierarchy.

## 3. Personas
- **Knowledge Manager**: curates folder structure, assigns permissions, monitors approvals.
- **Contributor**: uploads documents, submits for review, tracks status.
- **Reviewer/Approver**: reviews files, approves/rejects with reasons.
- **Viewer**: browses/downloads allowed documents, bookmarks frequently used items.

## 4. User Stories (Must-Have)
- As a Contributor, I can upload a file into a folder and tag it to an institution.
- As a Reviewer, I can approve/reject a pending file with a reason and see history.
- As a Viewer, I can browse folders, search, and download visible files.
- As any user, I can bookmark files/folders for quick access and see my bookmarks.
- As an Admin, I can manage roles/permissions and invite/approve users.
- As an Auditor, I can export or filter download logs per file/user/date.

## 5. Functional Requirements
- **Folders & Navigation**: nested folders, soft delete, lock flag, breadcrumbs, recent/starred views, trash restore.
- **File Management**: upload (with size/MIME validation), version field, lock/unlock, move between folders, soft delete, expiration date, remarks.
- **Review Workflow**: statuses (pending/approved/rejected), approver/reviewer attribution, rejection reason, approved_at & reviewed_at timestamps.
- **Institutions**: mandatory association for compliance; capture UII, type, region, flexible contact info.
- **Bookmarks**: polymorphic bookmarks for files/folders; unique per user/target.
- **Download Logging**: log downloads with user, timestamp, IP, UA, optional remarks; maintain download count.
- **Access Control**: role/permission model (Spatie) with user-role and direct permission mapping; visibility states (public/restricted/private) respected on fetch/download.
- **Audit & Dashboard**: show counts (pending approvals, recent uploads, most-downloaded, expiring soon), filterable lists.

## 6. Non-Functional Requirements
- **Security**: all endpoints behind auth; role/permission checks; signed/expiring URLs for public/restricted downloads.
- **Performance**: list pagination; eager loading to reduce N+1; file streaming for downloads.
- **Reliability**: soft deletes for recovery; cascading deletes on dependent data; backups for stored files.
- **Usability**: keyboard navigation, accessible components, consistent naming; bookmark and download affordances.

## 7. Roles & Permissions (initial)
- **Admin**: manage users, roles, permissions; full access.
- **Manager**: manage folders/files within scope, approve/reject uploads.
- **Contributor**: upload/update own files, request approval, view allowed items.
- **Viewer**: view/download allowed items, bookmark.

## 8. Analytics & Metrics
- Upload-to-approval time, approval throughput per reviewer.
- Download volume per file/institution/time range.
- Bookmark popularity and search-to-click conversion.
- Error rates (failed uploads, forbidden downloads).

## 9. Constraints & Assumptions
- Files stored via Laravel filesystem; database is transactional SQL.
- Approval is single-step; no multi-approver chain in current scope.
- Visibility rules: public (all), restricted (authenticated with permission), private (uploader + privileged roles).

## 10. Open Questions
- Should versioning create new file records or keep a single row with version bumps?
- Do downloads of rejected/expired files remain allowed for admins?
- Is there a retention policy for download logs and soft-deleted items?
