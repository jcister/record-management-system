# Diagrams

## System Context
```mermaid
flowchart LR
    subgraph Client
        U[User Browser]
    end

    subgraph App[Laravel + Inertia/Vue]
        API[HTTP Controllers]
        Auth[Auth + Roles/Permissions]
        Services[Domain Services]
    end

    subgraph Data[Persistence]
        DB[(PostgreSQL/MySQL)]
        FS[(Object Storage / Local FS)]
    end

    U -->|SPA navigation| API
    API --> Auth
    API --> Services
    Services --> DB
    Services --> FS
    API -->|JSON/Inertia| U
```

## File Lifecycle
```mermaid
flowchart TD
    A[Upload File] --> B[Stored with folder + institution]
    B --> C[Status: Pending]
    C -->|Reviewer sets decision| D{Approve?}
    D -->|Yes| E[Status: Approved]
    D -->|No| F[Status: Rejected + Reason]
    E --> G[Visibility enforced on downloads]
    F --> G
    G --> H[Downloads logged for audit]
    H --> I[Download count increments]
```

## Bookmark & Download Interaction
```mermaid
sequenceDiagram
    participant U as User
    participant UI as SPA (Vue)
    participant API as Laravel API
    participant DB as Database

    U->>UI: Click bookmark/download
    UI->>API: POST /bookmarks or GET /files/{id}/download
    API->>DB: Validate auth + permissions
    alt Bookmark
        DB-->>API: Upsert bookmark (unique per user/target)
        API-->>UI: Bookmark state toggled
    else Download
        DB-->>API: Record download_log + bump counter
        API-->>UI: Stream file (respect visibility)
    end
```

## User Journey (Contributor to Approval to Consumption)
```mermaid
journey
    title Contributor upload-to-approval
    section Preparation
        Login and pick target folder: 3:Contributor
        Confirm institution and metadata rules: 2:Contributor
    section Upload
        Upload file with remarks/version: 3:Contributor
        Submit for review (pending status): 3:Contributor
    section Review
        Triage queue of pending files: 2:Reviewer
        Approve or reject with reason: 3:Reviewer
    section Post-Decision
        View status and rejection reason if any: 3:Contributor
        Visibility enforced on listings/downloads: 2:Viewer
        Bookmark or download approved file: 2:All
        Download logged (ip, ua, user) and counter bumped: 2:All
```

## Workflow (Permissioned Download and Logging)
```mermaid
flowchart TD
    A[User requests download] --> B{Authenticated?}
    B -- No --> X[Reject or prompt login]
    B -- Yes --> C{Visibility}
    C -- Public --> D[Allow]
    C -- Restricted --> E{Has required role or permission?}
    E -- No --> X
    E -- Yes --> D
    C -- Private --> F{Owner/Uploader or Admin?}
    F -- No --> X
    F -- Yes --> D
    D --> G[Record download_log (ip, ua, user)]
    G --> H[Increment download count]
    H --> I[Stream file]
```
