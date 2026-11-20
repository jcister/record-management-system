# Product Requirements Document (PRD)

## RACKS v2.0: Records and Content Knowledge System

### Document Management & Archives

---

## 1. Executive Summary

### What is RACKS?

RACKS (Records And Content Knowledge System) is an intelligent document management and archival system for CHED Regional Office. It organizes, preserves, and provides fast access to institutional records while ensuring compliance with retention policies and ISO standards.

### The Problem We're Solving

- **Finding Documents**: Staff spend 15+ minutes searching for documents; no way to search inside scanned PDFs
- **Manual Classification**: Records officer manually sorts and categorizes hundreds of documents
- **Lost Physical Files**: No system to track where paper documents are stored
- **Compliance Risk**: No automated retention schedules; risk of disposing records too early or keeping them too long
- **No Central Reference**: CMOs, policies, and forms scattered across personal folders and email

### The Solution

An intelligent records management system that:

1. **Searches everything** - including text inside scanned documents
2. **Auto-classifies documents** - using AI to suggest categories
3. **Tracks physical locations** - find paper copies instantly
4. **Automates retention** - archives and flags documents for disposal based on rules
5. **Centralized references** - all CMOs, policies, and forms in one searchable library

---

## 2. Strategic Goals & Success Metrics

### Goals

- **Digitization**: 100% of active records digitized and searchable within 12 months
- **Findability**: Reduce document search time from 15+ minutes to under 30 seconds
- **Compliance**: Achieve ISO 9001 document control standards
- **Accessibility**: All staff can find references (CMOs, policies, forms) without asking colleagues
- **Automation**: 85%+ documents auto-classified correctly; retention rules applied automatically

### Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Document search satisfaction | >90% | User surveys |
| Average search-to-find time | <30 seconds | System analytics |
| Auto-classification accuracy | >85% | ML model metrics |
| Physical document retrieval accuracy | >95% | Audit checks |
| Records uploaded per month | >500 | Database count |
| Retention compliance rate | 100% | Audit reports |
| User adoption rate | >80% staff | Active user count |

---

## 3. User Personas

### Primary Users

#### 1. **Records Officer / Knowledge Manager**

- **Role**: Manages document lifecycle, enforces policies, maintains ISO compliance
- **Needs**: Auto-classification tools, retention rule enforcement, physical location tracking
- **Pain Points**: Manual sorting of documents, tracking retention schedules in spreadsheets
- **Goals**: Zero compliance violations, efficient document retrieval, accurate inventory

#### 2. **Contributors / Staff**

- **Role**: Upload documents, submit reports, search for references
- **Needs**: Easy upload process, quick search, access to CMOs and policies
- **Pain Points**: Repetitive data entry, can't find old documents, unsure which category to use
- **Goals**: Fast uploads, instant access to references, clear instructions

#### 3. **Reviewers / Approvers**

- **Role**: Review uploaded documents for quality and accuracy before publication
- **Needs**: Clear approval queue, document preview, rejection workflow
- **Pain Points**: No centralized review queue, no way to provide feedback
- **Goals**: Fast approvals, ensure document quality, maintain standards

#### 4. **Management / Directors**

- **Role**: Access all documents, oversee compliance, make policy decisions
- **Needs**: System-wide visibility, compliance reports, audit trails
- **Pain Points**: Can't verify compliance status, no visibility into document lifecycle
- **Goals**: Assured compliance, informed decisions, organizational transparency

#### 5. **Auditors / Quality Officers**

- **Role**: Verify ISO compliance, audit access logs, review retention adherence
- **Needs**: Comprehensive reports, download logs, retention status views
- **Pain Points**: Manual compilation of audit evidence
- **Goals**: Easy audit preparation, complete audit trails, compliance verification

---

## 4. Core Features

### 4.1 Document Upload & Organization

#### 4.1.1 Folder Hierarchy

**What**: Nested folder structure for logical organization

**Requirements**:

- Create/edit/delete folders
- Nested folders (unlimited depth)
- Move folders and files between locations
- Folder locking (prevent changes)
- Breadcrumb navigation
- Folder descriptions and metadata
- Permissions per folder (who can view/upload/edit)

**User Story**: *"As a records officer, I can create a folder structure like: CHED ROX → 2025 → Special Orders → January, so documents are organized by year and type"*

#### 4.1.2 File Upload

**What**: Upload documents with metadata capture

**Requirements**:

- Single and bulk upload
- Drag-and-drop interface
- Progress indicators
- File validation (size limits, allowed types)
- **Supported formats**: PDF, DOCX, XLSX, PPTX, JPG, PNG, TIFF
- Metadata fields:
  - Document title
  - Document number (e.g., SO-2025-001)
  - Series/year
  - Category (auto-suggested)
  - Institution
  - Description/remarks
  - Version number
- Save as draft (incomplete uploads)

**User Story**: *"As staff, I can drag-and-drop a scanned Special Order, the system suggests 'Special Order' category, I confirm and add document number SO-2025-015, and it's ready for review"*

#### 4.1.3 Document Categories

**What**: Classification taxonomy for all document types

**Requirements**:

- **Standard Categories**:
  - Special Orders (SO)
  - Personnel Letters (PL)
  - CHED Memorandum Orders (CMO)
  - Policies and Procedures
  - Communications (Memos, Letters)
  - Reports (Financial, Activity, Program)
  - Forms and Templates
  - Contracts and Agreements
  - Meeting Documents (Agendas, Resolutions)
  - Training and Development
  - Correspondence (Internal/External)
- Nested categories (sub-categories)
- Each category has:
  - Name, code, icon
  - Default retention rule
  - Metadata template (required fields)
  - Approval requirement flag
- Admin can create custom categories

**User Story**: *"As a records officer, I define that all 'Special Orders' must have a document number, require approval, and have a 10-year retention period before archival"*

---

### 4.2 Intelligent Search & Discovery

#### 4.2.1 Multi-Angle Search

**What**: Comprehensive search across all document attributes and content

**Requirements**:

- **Search Fields**:
  - Document title and description
  - Document number and series
  - OCR text (inside scanned documents)
  - File metadata (author, institution, category)
  - Tags and keywords
  - Date ranges
- **Search Features**:
  - Autocomplete suggestions
  - Fuzzy matching (typo tolerance)
  - Search within folders
  - Saved searches
  - Search history
- **Results Display**:
  - Preview snippets (first 200 characters)
  - Thumbnail previews
  - Highlighted search terms
  - Relevance scoring
  - Sort options (relevance, date, name, downloads)

**User Story**: *"I search 'scholarship eligibility 2024' and get all CMOs, policies, and Special Orders mentioning those terms, even if they were scanned documents"*

#### 4.2.2 Advanced Filters

**What**: Faceted filters to narrow search results

**Requirements**:

- **Filter Options**:
  - Document category (multi-select)
  - Date range (from/to or presets: last 30 days, this year, etc.)
  - Institution (multi-select)
  - Author/Uploader
  - Status (pending, approved, rejected, archived)
  - Retention status (current, archived, for disposal)
  - File type (PDF, Word, Excel, etc.)
  - Has physical copy (yes/no)
- Filter counts (show number of results per filter)
- Clear all filters option
- Save filter combinations as presets

**User Story**: *"I filter to show only 'Approved CMOs from 2023-2024 related to Institution XYZ' and get 12 results instead of scrolling through thousands"*

#### 4.2.3 OCR & Full-Text Search

**What**: Extract and search text from scanned documents

**Requirements**:

- Auto-OCR on upload (PDF images, JPEG, PNG, TIFF)
- OCR processing in background (async job)
- Store OCR text in searchable database field
- Support for English and Filipino text
- Confidence scoring (flag low-quality scans)
- Manual OCR re-processing option
- Search highlighting in original document

**User Story**: *"A scanned memo from 2010 is uploaded. The system reads the text using OCR. When I search 'budget allocation', it finds that old memo even though it's just an image"*

---

### 4.3 Document Classification & Metadata

#### 4.3.1 Auto-Classification

**What**: AI-powered document category suggestion

**Requirements**:

- Machine learning model trained on:
  - Filename patterns (e.g., "SO-2025-001.pdf" → Special Order)
  - OCR-extracted content
  - Metadata provided by user
- Confidence scoring (e.g., "85% confident this is a Special Order")
- Manual override always available
- Learn from corrections (reinforcement learning)
- Bulk classification for multiple documents
- Classification report (accuracy metrics for records officer)

**User Story**: *"I upload 50 scanned documents. The system suggests categories for all of them with confidence scores. I review and correct 5, then approve. The system learns from my corrections"*

#### 4.3.2 Metadata Extraction

**What**: Auto-extract structured data from documents

**Requirements**:

- Extract from filename:
  - Document number (e.g., SO-2025-001)
  - Date patterns (YYYY-MM-DD)
  - Series/year
- Extract from OCR text:
  - Dates mentioned
  - Document numbers
  - Institution names
  - Key terms (meeting, approval, policy, etc.)
- Suggest tags based on content
- Pre-fill upload form with extracted metadata
- User can review and correct before saving

**User Story**: *"I upload 'SO-2025-015_Scholarship_Guidelines.pdf' and the system auto-fills: Document Number = SO-2025-015, Category = Special Order, Series = 2025, Tags = scholarship, guidelines"*

---

### 4.4 Review & Approval Workflow

#### 4.4.1 Document Statuses

**What**: Track document lifecycle states

**Statuses**:

- **Draft**: User saved but not submitted
- **Pending Upload Processing**: OCR in progress
- **Pending Review**: Awaiting reviewer action
- **Approved**: Published and accessible
- **Rejected**: Not approved, with feedback
- **Archived**: Moved to archive based on retention rule
- **For Disposal**: Flagged for deletion approval
- **Disposed**: Permanently deleted

#### 4.4.2 Approval Queue

**What**: Centralized workspace for reviewers

**Requirements**:

- Dashboard showing pending documents count
- Filter by category, institution, uploader, date submitted
- Sort by submission date, priority
- SLA indicators (e.g., "2 days until deadline")
- Bulk actions (approve multiple, assign to another reviewer)
- Preview document before decision
- One-click approve or reject
- Required rejection reason (text field)
- Email notifications to uploader on decision

**User Story**: *"As a reviewer, I see 15 pending documents. I preview each one, approve 12, and reject 3 with clear reasons like 'Missing document number' or 'Wrong category'. Uploaders get email notifications immediately"*

#### 4.4.3 Approval Workflow Settings

**What**: Configure which documents require approval

**Requirements**:

- Set approval requirement per category
- Set approval requirement per folder
- Assign default reviewers per category
- Multi-level approval (optional): Contributor → Reviewer → Final Approver
- Auto-approval after X days (optional, for low-risk docs)
- Approval history (who approved, when, any comments)

**User Story**: *"As a records officer, I configure that all Special Orders require approval, but routine internal memos are auto-approved"*

---

### 4.5 Physical Location Tracking

#### 4.5.1 Location Coding System

**What**: Track where physical documents are stored

**Requirements**:

- **Hierarchical location structure**:
  - Building (e.g., Main Office)
  - Room (e.g., Records Room)
  - Cabinet (e.g., Cabinet A)
  - Shelf (e.g., Shelf 3)
  - Box (e.g., Box 2025-01)
- **Location Code Format**: `CAB-A/SH-3/BOX-2025-01`
- Manage locations (add/edit/deactivate)
- Track capacity per location (max items, current count)
- Assign location manager (responsible person)
- Link documents to physical locations
- Search documents by physical location
- Generate location inventory reports

**User Story**: *"I store a physical copy of SO-2025-015 in Cabinet A, Shelf 3, Box 2025-01. I link it in the system. Later, when someone searches for it, they see: 'Physical copy available at CAB-A/SH-3/BOX-2025-01'"*

#### 4.5.2 Document Physical States

**What**: Track relationship between digital and physical copies

**States**:

- **Digital Only**: No physical copy exists
- **Digital + Physical**: Both exist, physical location recorded
- **Physical Only**: Not yet digitized (pending scan)
- **Physical Destroyed**: Was physical, now only digital remains

**Requirements**:

- Display state on document view page
- Filter documents by state
- "Show Physical Location" button (if applicable)
- QR code for physical boxes (scan to see all contents)
- Track when physical copy was last verified

**User Story**: *"I search for an old policy. The system shows it's digitized with a physical copy at CAB-B/SH-1/BOX-2019-05. I need the original for verification, so I go directly to that location"*

---

### 4.6 Retention Management & Archival

#### 4.6.1 Retention Rules

**What**: Define lifecycle policies for document categories

**Requirements**:

- **Rule Components**:
  - Rule name and description
  - Retention period (years)
  - Disposition action: Archive, Dispose, Transfer, Review
  - Permanent flag (never dispose)
  - Notification period (e.g., alert 90 days before action)
  - Requires disposal approval (yes/no)
  - Applies to which categories
- **Standard Rules** (examples):
  - CMOs: Permanent (never dispose)
  - Special Orders: 10 years → archive
  - Personnel Letters: 5 years → archive
  - Routine Memos: 3 years → dispose (with approval)
  - Financial Records: 7 years → archive
- Admin creates and manages rules
- Link rules to categories as defaults
- Override rule per individual document (if needed)

**User Story**: *"As a records officer, I create a rule: 'Travel Reports: Retain 5 years, then archive. Notify 90 days before.' All travel reports automatically follow this rule"*

#### 4.6.2 Automated Retention Processing

**What**: System automatically applies retention rules

**Requirements**:

- **Daily scheduled job** checks all documents
- Calculate action date: `document_date + retention_period`
- If action date reached:
  - Archive action: Change status to "Archived", move to archive folder
  - Dispose action: Change status to "For Disposal", notify admin
  - Review action: Flag for manual review
- Send email notifications before actions (e.g., 90 days notice)
- Generate monthly retention reports
- Audit log of all automated actions

**User Story**: *"A travel report from 2020 automatically changes to 'Archived' status in 2025 (5 years later). The records officer gets a monthly report showing all documents archived that month"*

#### 4.6.3 Disposal Workflow

**What**: Controlled process for deleting documents

**Requirements**:

- Documents flagged "For Disposal" enter approval queue
- Disposal approval dashboard:
  - List all documents pending disposal
  - Show retention rule applied
  - Document preview
  - Approve or Deny with reason
- Approved disposals:
  - Soft delete (30-day recovery window)
  - Hard delete after 30 days (permanent)
  - Log disposal with approver, date, reason
- Denied disposals:
  - Extend retention period (specify new date)
  - Change to "Archived" status
- Disposal audit report (who, what, when)

**User Story**: *"The system flags 25 routine memos from 2022 for disposal (3-year rule). The records officer reviews the list, approves 20, and extends retention for 5 that are still relevant. Approved documents are deleted after 30-day grace period"*

---

### 4.7 Reference Library

#### 4.7.1 Centralized References

**What**: Dedicated area for frequently accessed documents

**Requirements**:

- **Library Collections**:
  - CHED Memorandum Orders (CMOs) - searchable by number, topic, year
  - Policies and Procedures Manual
  - Institutional Forms (downloadable templates)
  - Legal References (RA, IRR, Executive Orders)
  - Training Materials
  - Organizational Charts
  - Contact Directories
- Separate navigation section (not mixed with regular documents)
- Enhanced metadata for references:
  - CMO number, date issued, topic
  - Form version number, effective date
  - Policy approval date, review cycle
- Quick access widgets on homepage
- "Most Downloaded" references
- "Recently Added" references

**User Story**: *"A new employee needs to find all CMOs about scholarship programs. They go to Reference Library → CMOs → Filter by 'scholarship' topic and find 8 relevant CMOs instantly"*

#### 4.7.2 Forms Management

**What**: Downloadable form templates

**Requirements**:

- Upload blank form templates (Word, Excel, PDF)
- Forms organized by purpose:
  - Personnel forms
  - Financial forms
  - Activity/Program forms
  - Administrative forms
- Metadata per form:
  - Form number/code
  - Version number
  - Effective date
  - Obsolete date (if replaced)
  - Instructions link
- Track form downloads
- Notify users when forms are updated
- Show "Latest Version" badge

**User Story**: *"I need a leave application form. I go to Reference Library → Forms → Personnel and download the latest version (v2.3, updated Jan 2025)"*

---

### 4.8 Bookmarks & Favorites

#### 4.8.1 User Bookmarks

**What**: Save frequently accessed documents and folders

**Requirements**:

- Bookmark button on documents and folders
- "My Bookmarks" section in user dashboard
- Organize bookmarks:
  - Add personal notes to bookmarks
  - Create bookmark collections/groups
  - Reorder bookmarks
- Remove bookmarks
- Share bookmark collections with team
- Notification if bookmarked document is updated or moved

**User Story**: *"I bookmark 5 CMOs I reference weekly, the scholarship policy folder, and 3 form templates. They're all in my dashboard for instant access"*

---

### 4.9 Download & Access Logging

#### 4.9.1 Download Tracking

**What**: Comprehensive audit trail of all document access

**Requirements**:

- Log every download:
  - User who downloaded
  - Timestamp
  - IP address
  - User agent (browser/device)
  - Download method (web, API)
- Increment download counter on file
- View download history per document
- View download history per user
- Export logs for audit (CSV, Excel)

**User Story**: *"As an auditor, I can see that Document SO-2025-015 was downloaded 23 times, by 12 different users, with full details of when and from where"*

#### 4.9.2 Access Reports

**What**: Analytics and reporting on document usage

**Requirements**:

- **Reports available**:
  - Most downloaded documents (this month, this year, all time)
  - Documents never downloaded (consider for disposal)
  - Downloads per user (activity tracking)
  - Downloads per institution
  - Downloads per category
  - Access by time (peak hours/days)
- Filter reports by date range
- Export reports
- Schedule automated monthly reports

**User Story**: *"At year-end, the director requests a report on most-accessed documents. I generate 'Top 50 Downloads 2025' showing CMO-2025-008 was downloaded 450 times, indicating high relevance"*

---

### 4.10 Document Visibility & Access Control

#### 4.10.1 Visibility Levels

**What**: Control who can see and download documents

**Levels**:

- **Public**: Anyone with system access can view and download
- **Restricted**: Only users with specific permissions or roles
- **Private**: Only uploader, approver, and admins
- **Institution-Specific**: Only users from the same institution

**Requirements**:

- Set visibility on upload or edit
- Default visibility per category (configurable)
- Override visibility per document
- Visibility shown on document card/list
- Search respects visibility (users only see allowed docs)
- Download button disabled if no permission

**User Story**: *"Personnel letters are set to 'Private' visibility. Only HR staff and admins can search for and download them. Other staff won't even see them in search results"*

#### 4.10.2 Role-Based Access Control

**What**: Granular permissions using Spatie roles/permissions

**Roles**:

- **Super Admin**: Full system access
- **Records Officer**: Manage categories, retention rules, approve documents
- **Reviewer**: Review and approve pending documents
- **Contributor**: Upload documents, edit own documents
- **Viewer**: View and download allowed documents
- **Auditor**: Read-only access to all documents + logs

**Permissions** (examples):

- `documents.upload`
- `documents.approve`
- `documents.delete`
- `documents.view_all`
- `categories.manage`
- `retention.manage`
- `locations.manage`
- `logs.export`

**User Story**: *"Jane is assigned 'Reviewer' role. She can approve pending documents but cannot delete documents or change retention rules. Only the Records Officer can do that"*

---

### 4.11 Document Actions

#### 4.11.1 File Operations

**What**: Actions users can perform on documents

**Actions Available**:

- **View**: Open document in browser viewer (PDF, images) or download to open
- **Download**: Save file to local device
- **Edit Metadata**: Update title, description, category, tags (if permitted)
- **Move**: Change folder location
- **Version Update**: Upload new version (keeps history)
- **Lock/Unlock**: Prevent editing
- **Delete**: Soft delete (trash) or permanent delete (after retention)
- **Restore**: Recover from trash
- **Share**: Generate shareable link (with expiration)
- **Bookmark**: Save to favorites

**Permissions**: Each action respects user role and document status

**User Story**: *"I need to move 10 Special Orders to a different folder. I select them, click 'Move', choose the destination folder, and they're moved. The system logs this action for audit"*

#### 4.11.2 Bulk Operations

**What**: Perform actions on multiple documents at once

**Requirements**:

- Select multiple documents (checkboxes)
- "Select All" on current page or folder
- Bulk actions:
  - Move to folder
  - Change category
  - Add tags
  - Change visibility
  - Delete
  - Approve (for reviewers)
  - Export metadata (CSV)
- Progress indicator for bulk operations
- Error handling (show which succeeded/failed)

**User Story**: *"I select 50 documents, change their category from 'Uncategorized' to 'Communications', and the system updates all 50 in seconds"*

---

### 4.12 Document Versioning

#### 4.12.1 Version Control

**What**: Track changes to documents over time

**Requirements**:

- Version field (e.g., v1.0, v2.1)
- Upload new version option
- Version history list:
  - Version number
  - Upload date
  - Uploaded by
  - Change notes
- Download previous versions
- Compare versions (show differences)
- Revert to previous version option
- Set a version as "Current Official Version"

**User Story**: *"The Scholarship Policy is updated. I upload v2.0, add change notes 'Updated eligibility criteria'. Users see the latest version by default but can access v1.0 from history if needed"*

---

### 4.13 Expiration & Review Dates

#### 4.13.1 Document Expiration

**What**: Set dates when documents become invalid

**Requirements**:

- Optional expiration date field
- Automated actions on expiration:
  - Change visibility to "Expired"
  - Notify document owner
  - Flag for review or disposal
  - Show "EXPIRED" badge on document
- Dashboard widget: "Expiring Soon" (30, 60, 90 days)
- Filter expired documents

**User Story**: *"A temporary policy is valid until Dec 31, 2025. I set expiration date. On Jan 1, 2026, it's automatically flagged as expired and hidden from search (but not deleted, for record-keeping)"*

#### 4.13.2 Review Cycles

**What**: Periodic review reminders for documents

**Requirements**:

- Review cycle field (e.g., "Every 2 years")
- Next review date calculated automatically
- Notify document owner/reviewer before review date
- Review workflow:
  - Reviewer confirms document is still valid
  - Or: Upload updated version
  - Or: Mark as obsolete
- Track review history

**User Story**: *"The Office Manual has a 2-year review cycle. The system reminds the records officer every 2 years to review and update it"*

---

## 5. Technical Requirements

### 5.1 AI & Machine Learning

#### 5.1.1 OCR Integration

**Technology Options**:

- **Open Source**: Tesseract OCR
- **Cloud**: Google Vision API, Azure Computer Vision, AWS Textract

**Requirements**:

- Process PDF images and scanned documents (JPEG, PNG, TIFF)
- Extract text with confidence scores
- Language support: English, Filipino
- Handle poor-quality scans (preprocessing: deskew, denoise)
- Async processing (background jobs)
- Store OCR text in database (full-text searchable)
- Flag low-confidence results for manual review

#### 5.1.2 Document Classification Model

**Approach**: Supervised machine learning

**Training Data**:

- Historical documents with known categories
- Filename patterns
- OCR text samples

**Features**:

- Filename tokens
- Document length
- Key term frequency (TF-IDF)
- Metadata patterns

**Model**: Random Forest or Neural Network (TensorFlow/PyTorch)

**Output**: Category prediction with confidence score

**Continuous Learning**: Retrain model monthly with new labeled data

#### 5.1.3 Metadata Extraction

**Techniques**:

- **Regex patterns**: Extract document numbers, dates, codes
- **Named Entity Recognition (NER)**: Extract names, organizations, locations
- **Keyword extraction**: Identify key topics for tagging

**Libraries**: spaCy, NLTK, custom regex

### 5.2 Search Engine

#### 5.2.1 Full-Text Search

**Technology**: ElasticSearch or PostgreSQL Full-Text Search

**Requirements**:

- Index document metadata and OCR text
- Support for:
  - Exact phrase matching ("scholarship eligibility")
  - Partial matching (eligib* → eligibility, eligible)
  - Fuzzy matching (schlarship → scholarship)
  - Boolean operators (scholarship AND guidelines)
  - Proximity search (words within X distance)
- Ranking by relevance (TF-IDF, BM25)
- Highlighting search terms in results
- Search suggestions (autocomplete)

#### 5.2.2 Faceted Search

**Requirements**:

- Dynamic facets based on result set
- Count of results per facet value
- Multi-select filters (additive, not restrictive)
- Real-time filter updates (AJAX)

### 5.3 File Storage

#### 5.3.1 Storage Backend

**Options**:

- **Cloud**: AWS S3, Azure Blob Storage, Google Cloud Storage
- **Local**: Server filesystem (for on-premise deployment)

**Requirements**:

- Scalable storage
- File versioning support
- Encryption at rest
- Access control (signed URLs)
- CDN integration for fast delivery

#### 5.3.2 File Processing Pipeline

**Workflow**:

1. User uploads file
2. Store in temporary location
3. Validate file (size, type, virus scan)
4. Move to permanent storage
5. Generate thumbnail (for images/PDFs)
6. Queue OCR job (if applicable)
7. Queue classification job
8. Update database with results

### 5.4 Background Jobs & Scheduling

#### 5.4.1 Job Queue

**Technology**: Laravel Queues (Redis or database driver)

**Jobs**:

- `ProcessDocumentOCR`: Extract text from scanned docs
- `ClassifyDocument`: Run ML classification
- `GenerateThumbnail`: Create preview images
- `ProcessRetentionRules`: Check and apply retention actions
- `SendNotifications`: Email alerts
- `ExportReport`: Generate large reports

**Requirements**:

- Async execution (don't block user)
- Retry on failure (with exponential backoff)
- Job status tracking (show progress to user)
- Priority queues (urgent jobs first)

#### 5.4.2 Scheduled Tasks

**Technology**: Laravel Task Scheduler (Cron)

**Daily Tasks**:

- Process retention rules (check for archive/disposal dates)
- Send expiration warnings
- Generate daily activity reports

**Weekly Tasks**:

- Retrain classification model (if new data available)
- Backup verification

**Monthly Tasks**:

- Generate compliance reports
- Cleanup soft-deleted documents (after 30 days)

### 5.5 Database Design

#### 5.5.1 Database Technology

**Recommended**: PostgreSQL 15+

**Reasons**:

- JSONB support (flexible metadata storage)
- Full-text search capabilities
- Advanced indexing (GIN, GiST)
- Robust and reliable

#### 5.5.2 Key Indexes

```sql
-- Search performance
CREATE INDEX idx_files_ocr_text_fulltext ON files USING GIN(to_tsvector('english', ocr_text));
CREATE INDEX idx_files_title_fulltext ON files USING GIN(to_tsvector('english', title));

-- Filtering
CREATE INDEX idx_files_category_id ON files(category_id);
CREATE INDEX idx_files_status ON files(status);
CREATE INDEX idx_files_retention_status ON files(retention_status);
CREATE INDEX idx_files_created_at ON files(created_at);

-- Retention automation
CREATE INDEX idx_files_auto_archive_date ON files(auto_archive_date) WHERE auto_archive_date IS NOT NULL;
CREATE INDEX idx_files_disposal_date ON files(disposal_date) WHERE disposal_date IS NOT NULL;

-- Physical location
CREATE INDEX idx_files_physical_location_id ON files(physical_location_id);

-- Audit
CREATE INDEX idx_download_logs_file_id ON file_download_logs(file_id);
CREATE INDEX idx_download_logs_downloaded_at ON file_download_logs(downloaded_at);
```

### 5.6 API Design

#### 5.6.1 RESTful Endpoints

**Authentication**: Laravel Sanctum (SPA token-based)

**Key Endpoints**:

```
Documents
  GET    /api/documents              - List documents (with filters)
  POST   /api/documents              - Upload new document
  GET    /api/documents/{id}         - Get document details
  PATCH  /api/documents/{id}         - Update document metadata
  DELETE /api/documents/{id}         - Delete document
  GET    /api/documents/{id}/download - Download file

Categories
  GET    /api/categories             - List categories
  POST   /api/categories             - Create category

Folders
  GET    /api/folders                - List folders
  POST   /api/folders                - Create folder
  GET    /api/folders/{id}           - Get folder contents

Search
  GET    /api/search                 - Search documents
  GET    /api/search/suggestions     - Autocomplete

Retention
  GET    /api/retention/rules        - List retention rules
  POST   /api/retention/rules        - Create rule
  GET    /api/retention/pending      - Documents pending action

Physical Locations
  GET    /api/locations              - List locations
  POST   /api/locations              - Create location

Reports
  GET    /api/reports/downloads      - Download statistics
  GET    /api/reports/compliance     - Retention compliance
```

### 5.7 Integration Points

#### 5.7.1 Email Notifications

**Events**:

- Document approved/rejected
- Pending approval (for reviewers)
- Retention action applied
- Document expiring soon
- Review date approaching

**Technology**: Laravel Mail + Mailgun/SendGrid/SMTP

#### 5.7.2 External Authentication (Optional)

**Options**:

- LDAP/Active Directory integration
- SSO (SAML, OAuth)

---

## 6. User Interface Requirements

### 6.1 Dashboard

**Widgets**:

- Quick search bar (prominent, centered)
- My recent documents (last 10 viewed)
- My bookmarks
- Pending approvals (if reviewer)
- Documents expiring soon
- Recently uploaded (last 20)
- Most downloaded this month

### 6.2 Search Interface

**Layout**:

- Search bar with autocomplete at top
- Filters panel on left sidebar (collapsible)
- Results in main area (list or grid view toggle)
- Preview pane on right (optional, collapsible)

**Result Card**:

- Document icon (based on file type)
- Title (clickable)
- Metadata: Category, Date, Author, Institution
- Status badge (Pending, Approved, Archived)
- Physical location (if exists)
- Actions: Download, Bookmark, View, More

### 6.3 Document View Page

**Sections**:

- Document viewer (embedded PDF/image viewer or download prompt)
- Metadata panel (right sidebar):
  - Title, description
  - Category, document number, series
  - Institution, author, dates
  - Version, status, visibility
  - Physical location (with map/diagram)
  - Retention status and dates
  - Tags
- Actions toolbar:
  - Download, Print, Share, Bookmark, Edit, Delete
- Related documents (below viewer)
- Version history (collapsible)
- Activity log (collapsible)

### 6.4 Upload Interface

**Steps**:

1. Select files (drag-drop or browse)
2. Choose folder
3. Fill metadata form (with auto-suggestions)
4. Review and submit
5. Show upload progress
6. Confirmation with next steps (OCR processing, pending review)

**Features**:

- Save as draft option
- Bulk upload (select multiple files)
- Template metadata (use previous upload as template)

### 6.5 Approval Queue (Reviewer Dashboard)

**Layout**:

- Pending count badge in navigation
- List of pending documents with filters
- Preview pane (click to preview)
- Approve/Reject buttons
- Bulk approve (select multiple)

**Workflow**:

1. Click document to preview
2. Review metadata and content
3. Click "Approve" or "Reject"
4. If reject, provide reason (required text field)
5. Confirmation message
6. Next document auto-loads

### 6.6 Reference Library Page

**Layout**:

- Browse by collection (tabs or cards)
- Search within library
- Featured section (most used)
- Recently added section

**Collections**:

- CMOs (filterable by year, topic)
- Policies
- Forms
- Legal References
- Training Materials

### 6.7 Records Officer Admin Panel

**Sections**:

- Document Categories Management
  - List, create, edit, delete categories
  - Set retention rules per category
- Retention Rules Management
  - List, create, edit, disable rules
  - View documents affected by each rule
- Physical Locations Management
  - List, create, edit, deactivate locations
  - View inventory per location
  - Print location QR codes
- Disposal Queue
  - List documents for disposal
  - Approve or extend retention
- Reports
  - Compliance reports
  - Retention status reports
  - Classification accuracy

---

## 7. Roles & Permissions

### 7.1 Role Definitions

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| **Super Admin** | Full system control | All permissions |
| **Records Officer** | Manages records lifecycle | Manage categories, retention rules, locations; approve documents; access all documents |
| **Reviewer** | Reviews and approves documents | View pending queue; approve/reject documents; view all documents |
| **Contributor** | Uploads and manages own documents | Upload documents; edit own documents; view approved documents |
| **Viewer** | Read-only access | View and download approved documents; bookmark |
| **Auditor** | Compliance and audit access | Read-only access to all documents; export logs; view reports |

### 7.2 Permission Matrix

| Action | Super Admin | Records Officer | Reviewer | Contributor | Viewer | Auditor |
|--------|-------------|-----------------|----------|-------------|--------|---------|
| Upload document | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Edit own document | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Edit any document | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Approve/reject document | ✓ | ✓ | ✓ | ✗ | ✗ | ✗ |
| Delete document | ✓ | ✓ | ✗ | Own only | ✗ | ✗ |
| View approved documents | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| View pending documents | ✓ | ✓ | ✓ | Own only | ✗ | ✓ |
| Download document | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Manage categories | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Manage retention rules | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Manage physical locations | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Approve disposal | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| View all logs | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ |
| Export reports | ✓ | ✓ | ✗ | ✗ | ✗ | ✓ |
| Manage users | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |

---

## 8. Non-Functional Requirements

### 8.1 Performance

- Search results in <2 seconds for 100,000+ documents
- Document upload with OCR processing completes within 60 seconds
- Page load time <3 seconds
- Support 100 concurrent users
- Background jobs don't impact user experience

### 8.2 Security

- **Authentication**: Secure login with password hashing (bcrypt)
- **Authorization**: Role-based access control (RBAC) enforced on all endpoints
- **Data Encryption**:
  - Files encrypted at rest
  - TLS/SSL for data in transit
- **Audit Logging**: All document access, modifications, and deletions logged
- **File Validation**:
  - MIME type verification
  - Virus scanning on upload
  - File size limits
- **Secure Downloads**: Signed URLs with expiration
- **Data Privacy**: Compliance with Philippine Data Privacy Act
- **Backup**: Encrypted backups stored securely

### 8.3 Scalability

- Support 500,000 documents
- Horizontal scaling (add more app servers)
- Cloud storage for files (unlimited capacity)
- Database read replicas for search-heavy operations
- CDN for fast file delivery

### 8.4 Usability

- **Responsive Design**: Works on desktop, tablet, mobile
- **Accessibility**: WCAG 2.1 AA compliance
  - Keyboard navigation
  - Screen reader support
  - Color contrast ratios
  - Alt text for images
- **Multi-Language**: English and Filipino
- **Intuitive UI**: Minimal training needed
- **Help Documentation**: Contextual help tooltips and user guide

### 8.5 Reliability

- **Uptime**: 99.5% SLA
- **Backups**:
  - Database: Daily full backup, hourly incremental
  - Files: Daily backup to separate storage
- **Disaster Recovery**:
  - RTO (Recovery Time Objective): 4 hours
  - RPO (Recovery Point Objective): 1 hour
- **Error Handling**: Graceful degradation, user-friendly error messages
- **Soft Deletes**: 30-day recovery window for deleted documents

### 8.6 Maintainability

- **Code Quality**: Follow Laravel best practices, PSR standards
- **Documentation**: Inline code comments, API documentation, user manual
- **Testing**: Unit tests, integration tests, 80%+ code coverage
- **Version Control**: Git with branching strategy (main, develop, feature branches)
- **Monitoring**: Application performance monitoring (APM), error tracking

---

## 9. Implementation Roadmap

### Phase 1: Core Document Management (Months 1-3)

**Deliverables**:

- User authentication and roles
- Folder structure and navigation
- Document upload with metadata
- Basic search (metadata only)
- Download with logging
- Bookmarks
- Approval workflow (pending → approved/rejected)

**Milestone**: Staff can upload, organize, and find documents

---

### Phase 2: Enhanced Search & Classification (Months 4-6)

**Deliverables**:

- OCR integration (extract text from scanned docs)
- Full-text search (search within OCR text)
- Document categories and classification
- Auto-classification (ML model)
- Advanced filters (category, date, institution, etc.)
- Reference library

**Milestone**: Staff can search within scanned documents; system suggests categories

---

### Phase 3: Retention & Physical Location (Months 7-9)

**Deliverables**:

- Retention rules management
- Automated retention processing (archive, disposal)
- Disposal approval workflow
- Physical location tracking
- Location coding system
- Expiration dates and review cycles
- Compliance reports

**Milestone**: Records officer can automate lifecycle management; track physical documents

---

### Phase 4: Analytics & Refinement (Months 10-12)

**Deliverables**:

- Download analytics and reports
- Most downloaded documents
- Classification accuracy reports
- Admin dashboard with KPIs
- Bulk operations (bulk upload, bulk edit, bulk move)
- Document versioning
- Mobile-responsive improvements
- User training and onboarding materials

**Milestone**: Full system with analytics, reporting, and optimization

---

## 10. Success Criteria & KPIs

### Adoption Metrics

- **Target**: 80% of staff actively use the system within 3 months of launch
- **Measure**: Unique users logging in and performing actions monthly

### Efficiency Metrics

- **Search Time**: Average time from search to finding document <30 seconds
- **Upload Time**: Average time to upload and submit document <5 minutes
- **Approval Turnaround**: Average time from submission to approval <2 business days

### Quality Metrics

- **Auto-Classification Accuracy**: >85% correct category suggestions
- **OCR Accuracy**: >90% text extraction accuracy (for good-quality scans)
- **User Satisfaction**: >90% satisfaction score (quarterly survey)
- **Physical Location Accuracy**: >95% accuracy when retrieving physical documents

### Usage Metrics

- **Documents Digitized**: >500 documents per month
- **Searches**: >1,000 searches per month
- **Downloads**: >2,000 downloads per month
- **Reference Library Access**: >500 CMO/policy views per month

### Compliance Metrics

- **Retention Compliance**: 100% of documents assigned retention rules
- **Zero Overdue Reviews**: No documents past review date without action
- **Disposal Approval**: 100% of disposals have approval record
- **Audit Trail**: 100% of actions logged

---

## 11. Risks & Mitigations

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| **OCR accuracy <70% for old scanned docs** | High | Medium | Manual review queue; improve scans; use advanced OCR API |
| **Staff resistance to system adoption** | High | Medium | Leadership mandate; training; show quick wins; make system easy |
| **Physical location data entry burden** | Medium | High | Start with new documents; phase in historical backlog; use QR codes |
| **ML classification model low accuracy** | Medium | Medium | Start with 10-15 categories; manual override; continuous learning |
| **Storage costs exceed budget** | Medium | Low | Use retention rules to archive/delete old docs; compress files; tiered storage |
| **OCR processing causes performance issues** | Medium | Medium | Async background jobs; queue management; process during off-hours |
| **Data loss due to system failure** | High | Low | Regular backups; disaster recovery plan; test restores |
| **Unauthorized access to sensitive docs** | High | Low | Strong authentication; role-based access; audit logs; security testing |
| **Users delete important documents accidentally** | Medium | Medium | Soft deletes with 30-day recovery; permission restrictions on delete |

---

## 12. Open Questions & Decisions Needed

### Technical Decisions

1. **OCR Service**: Use open-source Tesseract or paid API (Google Vision, Azure)?
   - **Trade-off**: Cost vs. accuracy
   - **Recommendation**: Start with Tesseract; upgrade to API if accuracy insufficient

2. **Search Engine**: PostgreSQL full-text or ElasticSearch?
   - **Trade-off**: Simplicity vs. advanced features
   - **Recommendation**: Start with PostgreSQL; migrate to ElasticSearch if needed

3. **File Storage**: Cloud (S3) or local filesystem?
   - **Trade-off**: Cost vs. scalability
   - **Recommendation**: Cloud for production; local for development/testing

4. **ML Classification**: Custom model or pre-trained service?
   - **Trade-off**: Customization vs. ease of use
   - **Recommendation**: Custom model trained on CHED documents

### Policy Decisions

1. **Retention Schedules**: Who defines and approves retention rules for each category?
   - **Stakeholder**: Records Officer + Legal/Compliance Team

2. **Disposal Authority**: Who has final approval for document disposal?
   - **Stakeholder**: Regional Director or Designated Officer

3. **Physical Location Migration**: Retroactively code existing documents or only new documents?
   - **Recommendation**: New documents mandatory; historical documents phased in

4. **Classification Override**: Can contributors override auto-classification without review?
   - **Recommendation**: Yes, but log overrides; review for model improvement

5. **Visibility Defaults**: What is the default visibility for uploaded documents?
   - **Recommendation**: "Restricted" (authenticated users only) for safety

6. **Version Control**: Create new file record per version or single record with version history?
    - **Recommendation**: Single record with version history (easier to track)

---

## 13. Assumptions & Constraints

### Assumptions

- Staff have basic computer literacy
- Internet connectivity available in office
- CHED has existing document scanning equipment
- Historical documents are organized enough to begin digitization
- Leadership supports digitization initiative

### Constraints

- **Budget**: Limited budget for AI services (prefer open-source)
- **Timeline**: 12-month implementation window
- **Resources**: Small development team (2-4 developers + interns)
- **Infrastructure**: Existing server infrastructure or cloud budget
- **Compliance**: Must comply with ISO 9001, Data Privacy Act

---

## 14. Dependencies

### External Dependencies

- OCR service availability (Tesseract or cloud API)
- Email service for notifications
- Cloud storage service (if using S3/Azure)
- SSL certificate for secure connections

### Internal Dependencies

- Leadership approval and support
- Records Officer availability for requirements clarification
- Staff availability for user acceptance testing
- IT infrastructure readiness (servers, network)
- Training materials preparation

---

## 15. Glossary

| Term | Definition |
|------|------------|
| **CMO** | CHED Memorandum Order - official policy documents |
| **OCR** | Optical Character Recognition - technology to extract text from images |
| **Retention** | How long to keep a document before disposal |
| **Disposition** | What to do with a document after retention period (archive, dispose, transfer) |
| **ISO 9001** | International quality management standard |
| **Soft Delete** | Mark as deleted but don't permanently remove (allows recovery) |
| **Hard Delete** | Permanently remove from system |
| **RBAC** | Role-Based Access Control - permissions based on user roles |
| **SLA** | Service Level Agreement - guaranteed performance targets |
| **TF-IDF** | Term Frequency-Inverse Document Frequency - relevance ranking algorithm |

---

## Appendices

### A. Sample Document Categories

1. Special Orders (SO)
2. Personnel Letters (PL)
3. CHED Memorandum Orders (CMO)
4. Policies and Procedures
5. Communications - Internal Memos
6. Communications - External Letters
7. Financial Reports
8. Activity Reports
9. Program Reports
10. Meeting Documents - Agendas
11. Meeting Documents - Minutes
12. Meeting Documents - Resolutions
13. Contracts and Agreements
14. Forms and Templates
15. Training and Development Materials
16. Legal References (RA, IRR, EO)
17. Organizational Charts and Directories
18. Correspondence - Incoming
19. Correspondence - Outgoing

### B. Sample Retention Rules

| Category | Retention Period | Disposition |
|----------|------------------|-------------|
| CMOs | Permanent | Archive only, never dispose |
| Policies | Permanent | Archive only, never dispose |
| Special Orders | 10 years | Archive |
| Personnel Letters | 5 years | Archive |
| Financial Reports | 7 years | Archive |
| Meeting Minutes | 7 years | Archive |
| Routine Memos | 3 years | Dispose (with approval) |
| Correspondence | 2 years | Dispose (with approval) |
| Forms (obsolete versions) | 1 year | Dispose |

### C. Example Physical Location Codes

- `CAB-A/SH-1/BOX-2025-01` - Cabinet A, Shelf 1, Box 2025-01
- `CAB-A/SH-1/BOX-2025-02` - Cabinet A, Shelf 1, Box 2025-02
- `CAB-B/SH-3/BOX-2024-12` - Cabinet B, Shelf 3, Box 2024-12
- `SAFE/SH-1/FOLDER-CONTRACTS` - Safe, Shelf 1, Contracts Folder
- `OFFSITE/ARCHIVE/BOX-2019-05` - Offsite Archive, Box 2019-05

### D. Related Standards and References

- ISO 15489: Records Management Standard
- ISO 9001: Quality Management Systems
- Philippine Data Privacy Act of 2012 (RA 10173)
- CHED Records Management Manual
- National Archives Guidelines on Retention and Disposal

---

**Document Control**

- **Version**: 2.0 (Records Management Only)
- **Date**: January 20, 2026
- **Author**: Development Team
- **Scope**: RACKS Document Management System (Explicit Knowledge)
- **Excludes**: Tacit Knowledge features (travel reports, coaching, meeting minutes as knowledge entries)
- **Next Review**: March 2026
