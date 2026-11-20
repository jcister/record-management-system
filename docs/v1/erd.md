# Data Model ERD

The diagram reflects the current Laravel models and migrations (files, folders, institutions, bookmarks, download logs, users, and Spatie permission tables).

```mermaid
erDiagram
    USER ||--o{ FOLDER : "creates"
    FOLDER ||--o| FOLDER : "parent"
    FOLDER ||--o{ FILE : "contains"
    USER ||--o{ FILE : "uploads"
    USER ||--o{ FILE : "approves"
    USER ||--o{ FILE : "reviews"
    INSTITUTION ||--o{ FILE : "owns"
    FILE ||--o{ FILE_DOWNLOAD_LOG : "downloaded"
    USER ||--o{ FILE_DOWNLOAD_LOG : "downloads"
    USER ||--o{ BOOKMARK : "saves"
    BOOKMARK }o--|| FILE : "targets"
    BOOKMARK }o--|| FOLDER : "targets"

    USER ||--o{ MODEL_HAS_ROLES : "assigned"
    ROLE ||--o{ MODEL_HAS_ROLES : "maps"
    ROLE ||--o{ ROLE_HAS_PERMISSIONS : "grants"
    PERMISSION ||--o{ ROLE_HAS_PERMISSIONS : "linked"
    USER ||--o{ MODEL_HAS_PERMISSIONS : "direct"
    PERMISSION ||--o{ MODEL_HAS_PERMISSIONS : "assigned"

    USER {
        uuid id PK
        string first_name
        string last_name
        string email
        string password
        timestamp approved_at
    }

    FOLDER {
        uuid id PK
        string name
        uuid parent_id FK
        bool is_basic
        bool is_locked
        uuid created_by FK
    }

    FILE {
        uuid id PK
        uuid folder_id FK
        uuid uploaded_by FK
        uuid institution_id FK
        string document_number
        string series
        text remarks
        string file_path
        string original_filename
        string mime_type
        bigint file_size
        timestamp locked_at
        string version
        enum visibility
        enum status
        uuid approved_by FK
        uuid reviewed_by FK
        timestamp reviewed_at
        text rejection_reason
        ulong download_count
        timestamp expiration_date
    }

    INSTITUTION {
        uuid id PK
        string uii
        string name
        string type
        string region
        json contact_info
    }

    FILE_DOWNLOAD_LOG {
        uuid id PK
        uuid file_id FK
        uuid downloaded_by FK
        timestamp downloaded_at
        ip ip_address
        string user_agent
        text remarks
    }

    BOOKMARK {
        uuid id PK
        uuid user_id FK
        uuid bookmarkable_id
        string bookmarkable_type
    }

    ROLE {
        uuid id PK
        string name
        string guard_name
    }

    PERMISSION {
        uuid id PK
        string name
        string guard_name
    }

    MODEL_HAS_ROLES {
        uuid role_id FK
        uuid model_id FK
        string model_type
    }

    MODEL_HAS_PERMISSIONS {
        uuid permission_id FK
        uuid model_id FK
        string model_type
    }

    ROLE_HAS_PERMISSIONS {
        uuid permission_id FK
        uuid role_id FK
    }
```

**Notes**
- Bookmarks are polymorphic; current bookmarkable targets are `files` and `folders`.
- Soft deletes exist on `files`, `folders`, and `bookmarks`; cascades are set on folders→files, files→download logs, and bookmarks unique per user/target.
- Visibility/state fields (`visibility`, `status`, `is_locked`, `locked_at`, `approved_at`) are modeled for review/approval workflows.
