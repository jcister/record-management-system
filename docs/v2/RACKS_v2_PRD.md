# Product Requirements Document (PRD)

## RACKS v2.0: Knowledge Management Information System (KMIS)

---

## 1. Executive Summary

### What is RACKS KMIS?

RACKS (Records And Content Knowledge System) is evolving from a document repository into a comprehensive **Knowledge Management Information System** that captures, organizes, and shares both **explicit knowledge** (formal documents) and **tacit knowledge** (experiential insights, wisdom, and learnings) for CHED Regional Office.

### The Problem We're Solving

- **For Staff**: Difficult to find historical documents, policies, and decisions; knowledge loss when personnel transfer
- **For New Employees**: No centralized place to access procedures, references (CMOs, manuals), or learn from past experiences
- **For Management**: Cannot track physical document locations, retention schedules, or ensure ISO compliance
- **For the Organization**: Valuable wisdom from trainings, meetings, and leadership insights are not captured or shared

### The Solution

A dual-system platform that:

1. **Manages documents** with smart search, auto-classification, retention rules, and physical location tracking
2. **Captures knowledge** through structured templates, audio/video uploads, and themed content repositories

---

## 2. Strategic Goals & Success Metrics

### Goals

- **Digitization**: 100% of active records digitized and searchable within 12 months
- **Knowledge Retention**: Capture tacit knowledge from all trainings, meetings, and mentoring sessions
- **Findability**: Reduce document search time from 15+ minutes to under 30 seconds
- **Compliance**: Achieve ISO document control standards and automated retention enforcement
- **Accessibility**: Create reference library accessible to all staff (CMOs, manuals, policies)

### Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Document search satisfaction | >90% | User surveys |
| Average search-to-find time | <30 seconds | System analytics |
| Tacit knowledge entries/month | >20 entries | Database count |
| Physical document retrieval accuracy | >95% | Audit logs |
| Auto-classification accuracy | >85% | ML model metrics |
| User adoption rate | >80% staff | Active user count |

---

## 3. User Personas

### Primary Users

#### 1. **Regional Director (RD)**

- **Needs**: Access to all knowledge; capture and share wisdom; review strategic decisions
- **Pain Points**: Time lost finding documents; knowledge not transferred to successors
- **Goals**: Legacy of captured insights; efficient decision-making support

#### 2. **Records Officer / Knowledge Manager**

- **Needs**: Organize documents by category; enforce retention rules; maintain ISO compliance
- **Pain Points**: Manual classification; tracking physical locations; ensuring compliance
- **Goals**: Automated workflows; zero compliance violations; efficient retrieval

#### 3. **Staff / Contributors**

- **Needs**: Submit travel reports; upload meeting materials; find reference documents quickly
- **Pain Points**: Repetitive form filling; difficulty finding CMOs or policies
- **Goals**: Quick submissions; easy access to references; learn from others' experiences

#### 4. **New Hires**

- **Needs**: Learn organizational knowledge; access training materials; understand procedures
- **Pain Points**: Information overload; don't know what they don't know
- **Goals**: Fast onboarding; confidence in procedures; access to mentor wisdom

#### 5. **Reviewers / Approvers**

- **Needs**: Review submissions; ensure quality and compliance; provide feedback
- **Pain Points**: Queue backlogs; inconsistent submission quality
- **Goals**: Fast, informed approvals; quality control

---

## 4. Core Features

### A. EXPLICIT KNOWLEDGE (Document Management System)

#### 4.1 Enhanced Document Search

**What**: Multi-dimensional search across all document attributes and content

**Requirements**:

- Full-text search within scanned PDFs (OCR integration)
- Search by: document type, date range, institution, author, keywords, tags
- Filter by: retention status, category, year, approval status
- Search results show document preview and metadata
- Advanced filters: "Show only permanent records" or "Documents due for disposal in 2026"

**User Story**: *"As a staff member, I can search 'scholarship policies 2024' and instantly find all relevant CMOs, memos, and decisions"*

#### 4.2 Automatic Document Classification & Archival

**What**: Rules engine that auto-categorizes documents and manages retention lifecycle

**Requirements**:

- **Document Categories**:
  - Special Orders (SO)
  - Personnel Letters (PL)
  - Communications (Memos, Letters)
  - CMOs (CHED Memorandum Orders)
  - Policies and Manuals
  - Reports (Travel, Activity, Financial)
  - Meeting Minutes
  - Coaching & Mentoring Records
- **Retention Rules**:
  - Permanent: Never dispose (policies, CMOs, important SOs)
  - Temporary: Auto-archive after X years (travel reports after 5 years, routine memos after 3 years)
  - For Disposal: Flag documents reaching disposal age
- **Auto-Classification**: ML-based suggestion of category based on filename, content, and metadata
- **Manual Override**: Users can correct classifications; system learns from corrections

**User Story**: *"As a records officer, the system automatically marks travel reports older than 5 years as 'archived' and flags routine memos for disposal after 3 years"*

#### 4.3 Physical Location Tracking

**What**: Link digital documents to their physical storage locations

**Requirements**:

- **Location Coding System**: Cabinet → Shelf → Box → Folder code (e.g., "CAB-A/SH-3/BOX-2025-01")
- **Document States**:
  - Digital Only
  - Digital + Physical (with location)
  - Physical Only (digitization pending)
- **Search Integration**: "Show me the physical location" button on document view
- **Location History**: Track movements of physical documents
- **Inventory Reports**: List all documents in a specific cabinet/box

**User Story**: *"As staff, when I find a document digitally, I can see it's physically located at CAB-A/SH-3/BOX-2024-12 if I need the original"*

#### 4.4 Document Control & ISO Compliance

**What**: Features supporting ISO 9001 document control standards

**Requirements**:

- **Document Ownership**: Each document has designated owner/custodian
- **Version Control**: Track document versions with change log
- **Approval Workflows**: Defined approval paths for controlled documents
- **Document Links**: Link related documents (e.g., policy → implementation memo → revision)
- **Obsolete Marking**: Mark superseded documents as obsolete with replacement link
- **Audit Trail**: Complete history of all document actions

**User Story**: *"As a quality officer, I can generate an ISO-compliant report showing all controlled documents, their owners, versions, and approval status"*

#### 4.5 Reference Library

**What**: Dedicated repository for frequently accessed references

**Requirements**:

- **Collections**:
  - All CMOs (CHED Memorandum Orders) - searchable by number, topic, year
  - Policies and Procedures Manual
  - Forms Library (downloadable templates)
  - External References (RA, IRR, etc.)
  - Training Materials Archive
- **Quick Access**: Pinned on homepage; separate navigation section
- **Tagging**: Tag documents by topic (scholarship, faculty, compliance, etc.)
- **Favorites**: Users can favorite frequently used references

**User Story**: *"As a new hire, I can access the Reference Library to find all CMOs about scholarship programs without asking colleagues"*

---

### B. TACIT KNOWLEDGE (Knowledge Sharing System)

#### 4.6 Travel Reports with Narrative

**What**: Template-based travel reports that capture learnings, not just logistics

**Requirements**:

- **Digital Form Fields**:
  - Basic Info: Name, destination, dates, purpose
  - Activities Attended: List of sessions/meetings
  - **Key Learnings**: Rich text narrative field
  - **Insights & Recommendations**: What should the office do based on this trip?
  - **Resources Collected**: Upload presentations, handouts, photos
  - **Tags/Themes**: Categorize by topic (e.g., "scholarship policies", "quality assurance")
- **Workflow**: Submit → Supervisor Reviews → Published to knowledge base
- **Search**: Other staff can search travel reports by theme, destination, or keyword
- **Less Paper**: Direct digital submission; no scan-print-scan cycle

**User Story**: *"After attending a training on accreditation, I fill out the travel report template, add my insights about new CHED standards, upload the presentation slides, and tag it 'accreditation' so colleagues can learn from it"*

#### 4.7 Meeting Minutes & Recordings

**What**: Capture meeting outcomes and discussions with audio/video support

**Requirements**:

- **Meeting Entry Template**:
  - Meeting title, date, attendees
  - Agenda items
  - **Decisions Made**: Structured list of decisions with owners
  - **Action Items**: Who will do what by when
  - **Discussion Notes**: Key points discussed
  - **Audio/Video Upload**: Attach meeting recordings
  - **Transcription**: Auto-transcribe audio using AI (speech-to-text)
- **Tagging by Theme**: "Scholarship Issues", "Budget Planning", "Policy Review"
- **Searchable**: Find decisions by keyword, attendee, or theme
- **Linkable**: Reference minutes when implementing decisions

**User Story**: *"After our meeting about scholarship issues, I log the key decisions, upload the audio recording, and the system auto-generates a transcript. Later, when a similar issue arises, staff can search 'scholarship eligibility decision 2025' and find our discussion and the RD's wisdom"*

#### 4.8 Coaching & Mentoring Documentation

**What**: Capture one-on-one or group mentoring sessions

**Requirements**:

- **Mentoring Entry Template**:
  - Mentor and Mentee(s)
  - Topic/Question
  - **Mentor's Guidance**: Rich text field for wisdom/advice
  - **Audio Recording**: Option to record conversation (with consent)
  - **Resources Shared**: Links or uploaded files
  - **Follow-up Notes**: Did the advice work? What happened?
- **Visibility Control**: Private (mentor-mentee only) or Shared (anonymized for all)
- **Wisdom Archive**: Create repository of leadership insights

**User Story**: *"As RD, when Bem asks me about handling a scholarship appeal, I share my experience. Bem documents the conversation and my advice. The system saves this as 'RD's Wisdom on Scholarship Appeals' so future staff can learn even after I transfer"*

#### 4.9 "Food for Thought" Themed Content

**What**: Curated collections of insights organized by theme

**Requirements**:

- **Theme Creation**: Admin creates themes (e.g., "Respect", "Leadership", "Customer Service")
- **Content Submission**: Staff can submit:
  - Quotes or reflections
  - Short articles or stories
  - Video clips
  - Discussion prompts
- **Display Options**: Featured on homepage; browseable by theme; random "thought of the day"
- **Engagement**: Staff can comment or share reactions

**User Story**: *"Each week, the office focuses on a value. Staff submit 'Food for Thought' entries about 'Integrity', including stories, quotes, and reflections. These are compiled and displayed in the office lounge and on the system homepage"*

#### 4.10 Presentation & Training Materials Repository

**What**: Centralized storage for all training presentations and materials

**Requirements**:

- **Upload After Training**: Staff who attend external trainings upload slides, handouts, certificates
- **Metadata**: Training title, provider, date, topics covered, relevant staff
- **Searchable**: Find materials by topic, date, or provider
- **Integration with Travel Reports**: Link training materials to travel report narratives
- **Collections**: Group materials by training program or theme

**User Story**: *"After attending a CHED ROX training on new policies, I upload the presenter's slides and handouts. My colleagues can access these materials without attending, staying informed about policy changes"*

---

## 5. Technical Requirements

### 5.1 AI & Machine Learning Features

- **Document Classification**: ML model trained to suggest document category based on:
  - Filename patterns
  - OCR-extracted text
  - Metadata
  - User corrections (reinforcement learning)
- **Thematic Analysis**: NLP to extract themes/topics from narrative text (travel reports, meeting notes)
- **Audio Transcription**: Integrate speech-to-text API (e.g., OpenAI Whisper, Google Speech-to-Text)
- **Smart Search**: Semantic search that understands context, not just keywords
- **Content Recommendations**: "Related documents" and "You might also need" suggestions

### 5.2 Media Handling

- **File Types Supported**:
  - Documents: PDF, DOCX, XLSX, PPTX
  - Images: JPG, PNG, TIFF (scanned documents)
  - Audio: MP3, M4A, WAV
  - Video: MP4, MOV, AVI
- **OCR Processing**: Auto-OCR PDF and images on upload for searchability
- **Media Streaming**: Audio/video playback in-browser
- **Transcription Storage**: Save transcripts as searchable text linked to media files

### 5.3 Enhanced Search Engine

- **Full-Text Search**: ElasticSearch or PostgreSQL full-text search
- **Faceted Filters**: Category, date range, institution, author, status, retention
- **Highlighting**: Highlight search terms in results
- **Search Analytics**: Track popular searches to improve taxonomy

### 5.4 Retention Rules Engine

- **Rules Configuration UI**: Admin defines retention rules per document category:
  - Retention period (years)
  - Disposition action (archive, dispose, transfer)
  - Notification triggers (e.g., 90 days before disposal)
- **Automated Processing**: Scheduled job checks document ages and applies rules
- **Approval for Disposal**: Documents flagged for disposal require approval before deletion
- **Audit Log**: Record all retention actions

### 5.5 Templates & Forms

- **Template Builder**: Admin creates digital forms with field types:
  - Text, rich text, date, dropdown, file upload, tags
- **Pre-built Templates**:
  - Travel Report
  - Meeting Minutes
  - Coaching Session
  - Food for Thought Entry
- **Form Workflows**: Submit → Review → Publish/Reject
- **PDF Generation**: Generate printable PDF from submitted forms

### 5.6 Integration Points

- **Active Directory / LDAP**: SSO for user authentication
- **Email Notifications**: Alerts for approvals, expirations, new knowledge entries
- **Calendar Integration**: Link meetings to meeting minutes
- **External APIs**: OCR service, transcription service, ML model hosting

---

## 6. User Interface Requirements

### 6.1 Dashboard

**For All Users**:

- Quick search bar (prominent, top center)
- Recent documents viewed
- Bookmarked items
- "Food for Thought" of the day

**For Contributors**:

- My pending submissions
- Forms to fill (travel reports due, etc.)

**For Reviewers**:

- Pending approvals queue (count badge)
- SLA warnings (items nearing 2-day threshold)

**For Knowledge Managers**:

- System stats: Total documents, growth trends, popular searches
- Documents nearing disposal
- Classification accuracy reports

### 6.2 Search Interface

- **Search Bar**: Autocomplete suggestions as you type
- **Filters Panel**: Collapsible sidebar with facets
- **Results View**:
  - List or grid view toggle
  - Preview pane (show document thumbnail and first 200 characters)
  - Metadata tags (category, date, author)
  - Actions: Download, Bookmark, View Details, See Physical Location

### 6.3 Document View Page

- **Document Viewer**: In-browser PDF/image viewer; media player for audio/video
- **Metadata Panel**: Category, institution, dates, version, retention status, physical location
- **Actions**: Download, Edit (if permitted), Move, Delete, Bookmark
- **Related**: Show related documents and references
- **Audit Trail**: View history of changes

### 6.4 Knowledge Entry Forms

- **Intuitive Layout**: Progressive disclosure (show fields as needed)
- **Rich Text Editor**: Formatting, bullets, links, images for narrative fields
- **File Upload**: Drag-and-drop; multiple files; progress indicators
- **Tag Selector**: Type-ahead tag input with suggestions
- **Save Draft**: Allow saving incomplete forms
- **Preview**: Show how entry will appear before submitting

### 6.5 Reference Library Page

- **Browse by Category**: CMOs, Policies, Forms, Manuals
- **Featured Section**: Most downloaded, recently added
- **Search Within Library**: Scoped search
- **Download Counts**: Show popularity

---

## 7. Roles & Permissions

| Role | Permissions |
|------|-------------|
| **Super Admin** | Full system access; manage users, roles, retention rules, templates |
| **Regional Director** | Access all knowledge; approve/reject any entry; contribute wisdom |
| **Records Officer / Knowledge Manager** | Manage folders, document categories, retention rules; assign physical locations; review submissions |
| **Reviewer / Approver** | Review and approve/reject pending documents and knowledge entries |
| **Contributor / Staff** | Upload documents; submit travel reports, meeting minutes, coaching notes; bookmark; download allowed items |
| **Viewer / New Hire** | Browse and download approved documents; access reference library; view tacit knowledge (read-only) |
| **Auditor** | View all logs; export reports; read-only access to all content |

### Permission Matrix

| Action | Admin | RD | Records Officer | Reviewer | Contributor | Viewer |
|--------|-------|----|----|----------|-------------|--------|
| Upload document | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Approve/reject submission | ✓ | ✓ | ✓ | ✓ | ✗ | ✗ |
| Set retention rules | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ |
| Assign physical location | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ |
| Submit travel report | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| Log coaching session | ✓ | ✓ | ✓ | ✓ | ✓ | ✗ |
| View approved content | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Download media | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Export logs | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ |
| Manage users | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |

Summarized permissions
| Permissions (Grouped Actions) | Admin | Records Officer | RD | Reviewer | Contributor | Viewer |
|------------------------------|:-----:|:---------------:|:--:|:--------:|:-----------:|:------:|
| User Management              | ✓     | ✗               | ✗  | ✗        | ✗           | ✗      |
| Retention & Logs             | ✓     | ✓               | ✗  | ✗        | ✗           | ✗      |
| Approve/Reject Content       | ✓     | ✓               | ✓  | ✓        | ✗           | ✗      |
| Upload & Submit Reports      | ✓     | ✓               | ✓  | ✓        | ✓           | ✗      |
| View & Download              | ✓     | ✓               | ✓  | ✓        | ✓           | ✓      |



| Action Group               | Included Actions                                                                 |
|---------------------------|-----------------------------------------------------------------------------------|
| User Management           | Manage users                                                                      |
| Retention & Logs          | Set retention rules, Assign physical location, Export logs                        |
| Approve/Reject Content    | Approve/reject submission                                                         |
| Upload & Submit Reports   | Upload document, Submit travel report, Log coaching session                        |
| View & Download           | View approved content, Download media                                             |

---

## 8. Non-Functional Requirements

### 8.1 Performance

- Search results in <2 seconds for 100k+ documents
- Document upload processing (OCR, classification) completes within 60 seconds
- Page load time <3 seconds
- Support 100 concurrent users

### 8.2 Security

- Role-based access control (RBAC) enforced on all endpoints
- Encrypted file storage
- Audit logging for all document access and modifications
- Secure media streaming (no direct file URLs)
- Data retention compliance (GDPR, Philippine Data Privacy Act)

### 8.3 Scalability

- Support 500k documents and 10k knowledge entries
- Horizontal scaling for search indexing
- Cloud storage option for large media files

### 8.4 Usability

- Mobile-responsive design
- Accessibility: WCAG 2.1 AA compliance
- Multi-language support (English, Filipino)
- Keyboard navigation and screen reader support
- Onboarding tutorial for new users

### 8.5 Reliability

- 99.5% uptime SLA
- Automated backups (daily full, hourly incremental)
- Disaster recovery plan: RTO 4 hours, RPO 1 hour
- Soft deletes with 30-day recovery window

---

## 9. Implementation Phases

### Phase 1: Enhanced Document Management (Months 1-3)

- Enhanced search with filters
- OCR integration
- Document categorization UI
- Physical location tracking
- Basic retention rules

### Phase 2: Knowledge Capture Forms (Months 4-6)

- Travel report template
- Meeting minutes template
- Coaching & mentoring template
- Template builder for admins
- Form submission workflow

### Phase 3: AI & Automation (Months 7-9)

- Auto-classification ML model
- Audio transcription integration
- Thematic analysis
- Automated retention processing
- Smart search recommendations

### Phase 4: Knowledge Sharing Features (Months 10-12)

- Food for Thought module
- Presentation repository
- Enhanced reference library
- User engagement features (comments, reactions)
- Mobile app or PWA

---

## 10. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| OCR accuracy <70% for old scanned docs | High | Manual review queue; improve over time with better scans |
| Staff resist documenting tacit knowledge | High | Leadership mandate; make forms easy; show value through demos |
| Audio transcription costs | Medium | Use open-source Whisper model; optimize usage |
| ML model misclassifies documents | Medium | Manual override always available; learn from corrections |
| Physical location data entry burden | Medium | Start with new documents; phase in historical backlog |
| Storage costs for media files | Medium | Retention policy for audio/video; compress files; use cloud storage tiers |

---

## 11. Success Criteria & KPIs

### Adoption Metrics

- 80% of staff have logged in and used search within first month
- 50% of staff have contributed at least one knowledge entry within 3 months
- 20+ tacit knowledge entries per month by month 6

### Efficiency Metrics

- Average search time reduced from 15 min to <30 sec
- Travel report submission time reduced from 60 min to <15 min
- Approval turnaround <2 business days

### Quality Metrics

- Auto-classification accuracy >85%
- User search satisfaction >90% (quarterly survey)
- Physical location retrieval accuracy >95%

### Usage Metrics

- 500+ documents digitized in first 6 months
- 1000+ searches per month
- 50+ knowledge entries per quarter

---

## 12. Open Questions & Decisions Needed

1. **Transcription Service**: Use paid API (Google, Azure) or open-source Whisper? Budget implications?
2. **Physical Location Migration**: Retroactive coding of existing documents or only new documents?
3. **Retention Rules Authority**: Who approves retention schedules and disposal actions?
4. **Knowledge Visibility**: Should tacit knowledge (coaching, wisdom) be fully public internally or have privacy controls?
5. **Mobile Priority**: Is mobile app required or is responsive web sufficient?
6. **Integration with Existing Systems**: Does CHED have existing HR or activity tracking systems to integrate with?
7. **Wisdom Anonymization**: Should mentoring/coaching entries be anonymized for broader sharing?
8. **Disposal Approval**: Single approver or committee for document disposal decisions?

---

## 13. Appendices

### A. Glossary

- **CMO**: CHED Memorandum Order
- **Explicit Knowledge**: Formal, documented knowledge (policies, procedures, reports)
- **Tacit Knowledge**: Experiential knowledge (insights, wisdom, learnings)
- **OCR**: Optical Character Recognition (convert images to searchable text)
- **Retention**: How long to keep a document before disposal
- **ISO**: International Organization for Standardization (quality management standards)

### B. Related Documents

- CHED Records Management Manual
- ISO 9001 Document Control Procedures
- Philippine Data Privacy Act Compliance Guide
- CHED ROX Digitization Roadmap 2025-2027

---

**Document Control**

- **Version**: 2.0
- **Date**: January 2026
- **Author**: Development Team
- **Approver**: CHED RD & Records Officer
- **Next Review**: March 2026
