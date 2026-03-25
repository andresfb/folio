# Product Requirements Document (PRD)

## Distraction-Free Writing System (TUI + API Backend)

---

# 1. Overview

## 1.1 Purpose

This system is a distraction-free writing application inspired by tools like Scrivener. It provides a structured, hierarchical workspace for long-form writing, combining:

* A **Go-based TUI client** for writing and navigation
* A **Laravel API backend** for data storage, retrieval, and processing
* A **PostgreSQL database** for persistence (with future support for vector search)

The system emphasizes:

* Fast navigation
* Flexible organization
* Incremental loading for performance
* Extensibility for future features (AI, web/desktop clients, offline support)

---

## 1.2 Goals

### Primary Goals

* Enable users to organize writing into hierarchical structures (folders + documents)
* Provide fast access to large projects (hundreds/thousands of nodes)
* Support rich metadata per document/node
* Enable snapshots (versioning)
* Support attachments (images/files)

### Secondary Goals

* Enable semantic search (future, via vectors)
* Provide foundation for AI-assisted writing
* Support eventual offline/local caching

---

## 1.3 Non-Goals (Phase 1)

* Real-time collaboration
* Full offline-first sync engine
* Public API stability guarantees
* Multi-user shared editing

---

# 2. System Architecture

## 2.1 Components

### TUI Client (Go)

* Handles UI, editing, navigation
* Communicates via REST API
* Maintains in-memory cache (Phase 1)
* Optional SQLite cache (Phase 2)

### API Backend (Laravel)

* Business logic
* Data access (PostgreSQL)
* Metadata handling
* Future: embeddings + AI workflows

### Database (PostgreSQL)

* Relational core
* JSONB for flexible metadata
* Future: vector support (pgvector)

---

## 2.2 Data Loading Strategy

### On App Startup

* Load **project + thin tree only**
* Do NOT load document bodies or heavy metadata

### On Node Selection

* Load:

  * document content (if applicable)
  * full metadata

### On Panel Access (lazy)

* Load snapshots
* Load assets
* Load notes

---

# 3. Core Domain Model

## 3.1 Concepts

### Project

Top-level container for all content

### Node

Represents:

* folder OR
* document

Forms a tree structure

### Document Content

Editable text for document nodes

### Snapshot

Historical copy of document content

### Asset

Files attached to nodes (images, PDFs, etc.)

---

# 4. Database Schema (Phase 1)

## 4.1 Projects

```sql
create table projects (
    id uuid primary key,
    user_id uuid not null,
    title text not null,
    description text,
    settings jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);
```

---

## 4.2 Nodes (Tree Structure)

```sql
create table nodes (
    id uuid primary key,
    project_id uuid not null references projects(id) on delete cascade,
    parent_id uuid references nodes(id) on delete cascade,

    kind text not null check (kind in ('folder', 'document')),

    title text not null default '',
    sort_order numeric(20,10) not null default 0,

    metadata jsonb not null default '{}'::jsonb,

    archived_at timestamptz,
    deleted_at timestamptz,

    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),

    check (id <> parent_id)
);
```

### Indexes

```sql
create index idx_nodes_project_parent_order
on nodes(project_id, parent_id, sort_order);

create index idx_nodes_metadata_gin
on nodes using gin(metadata);
```

---

## 4.3 Document Content

```sql
create table document_contents (
    node_id uuid primary key references nodes(id) on delete cascade,
    body text not null default '',
    format text not null default 'markdown',
    word_count integer not null default 0,
    character_count integer not null default 0,
    excerpt text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);
```

---

## 4.4 Snapshots

```sql
create table node_snapshots (
    id uuid primary key,
    node_id uuid not null references nodes(id) on delete cascade,
    name text not null,
    body text not null,
    format text not null default 'markdown',
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now()
);
```

---

## 4.5 Assets

```sql
create table assets (
    id uuid primary key,
    project_id uuid not null references projects(id) on delete cascade,
    storage_key text not null,
    original_filename text not null,
    mime_type text not null,
    size_bytes bigint not null,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now()
);
```

---

## 4.6 Node-Asset Relationship

```sql
create table node_assets (
    id uuid primary key,
    node_id uuid not null references nodes(id) on delete cascade,
    asset_id uuid not null references assets(id) on delete cascade,
    role text not null default 'attachment',
    sort_order numeric(20,10) not null default 0,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now(),
    unique (node_id, asset_id, role)
);
```

---

# 5. Metadata Strategy

## 5.1 Stored in JSONB (`nodes.metadata`)

Examples:

* synopsis
* status
* label/color
* writing goals
* UI state
* custom fields

### Example

```json
{
  "synopsis": "Arrival at harbor",
  "status": "draft",
  "label": "blue",
  "goal": { "type": "word_count", "target": 1200 }
}
```

## 5.2 Promotion Rule

Move to relational tables if:

* heavily queried
* shared across nodes
* lifecycle complexity increases

---

# 6. API Design

## 6.1 Principles

* Separate **lightweight vs heavy payloads**
* Use **lazy loading**
* Avoid nested deep trees (use flat lists)
* Versioning via timestamps/revisions

---

## 6.2 Project Tree Endpoint

### GET `/projects/{id}/tree`

### Purpose

Load binder/navigation tree

### Response

```json
{
  "project": {
    "id": "p1",
    "title": "Glass Harbor",
    "tree_revision": 12
  },
  "nodes": [
    {
      "id": "1",
      "parent_id": null,
      "kind": "folder",
      "title": "Part I",
      "sort_order": 1,
      "updated_at": "2026-03-24T12:00:00Z",
      "flags": {
        "has_children": true,
        "has_document": false,
        "has_notes": false,
        "has_snapshots": false
      },
      "display": {
        "label": "blue",
        "status": "draft"
      }
    }
  ]
}
```

### Notes

* Flat list
* Minimal metadata only
* No document bodies

---

## 6.3 Node Detail Endpoint

### GET `/nodes/{id}`

### Purpose

Load inspector-level data

### Response

```json
{
  "id": "node-123",
  "title": "Arrival",
  "kind": "document",
  "metadata": {
    "synopsis": "Elena arrives at harbor",
    "status": "draft",
    "goal": { "target": 1200 }
  },
  "stats": {
    "word_count": 980,
    "snapshot_count": 3,
    "asset_count": 2
  },
  "updated_at": "2026-03-24T12:00:00Z"
}
```

---

## 6.4 Document Content Endpoint

### GET `/nodes/{id}/content`

### Response

```json
{
  "node_id": "node-123",
  "revision": 5,
  "body": "Full document text...",
  "format": "markdown",
  "word_count": 980,
  "character_count": 5200,
  "updated_at": "2026-03-24T12:00:00Z"
}
```

---

## 6.5 Snapshots Endpoint

### GET `/nodes/{id}/snapshots`

```json
[
  {
    "id": "snap-1",
    "name": "Before revision",
    "created_at": "2026-03-20T10:00:00Z"
  }
]
```

---

## 6.6 Assets Endpoint

### GET `/nodes/{id}/assets`

```json
[
  {
    "id": "asset-1",
    "filename": "map.png",
    "mime_type": "image/png"
  }
]
```

---

# 7. Data Loading Flow

## 7.1 App Startup

1. Load cached tree (if available)
2. Render UI immediately
3. Fetch `/projects/{id}/tree`
4. Replace/update tree

---

## 7.2 Node Selection

1. Show cached content (if available)
2. Fetch:

   * `/nodes/{id}`
   * `/nodes/{id}/content`
3. Update UI

---

## 7.3 Optional Panels

* Snapshots → `/snapshots`
* Assets → `/assets`

---

# 8. Caching Strategy

## Phase 1

* In-memory cache only
* Store:

  * tree
  * open documents

## Phase 2

* Add SQLite cache
* Store:

  * project tree
  * recent document contents
  * metadata snapshots

## Phase 3 (Future)

* Offline-first sync engine
* Conflict resolution

---

# 9. Performance Considerations

## Key Decisions

* Thin tree payload
* Lazy loading heavy data
* Flat node list (not nested JSON)
* Separate endpoints per data type

## Expected Scale

* 100–1000 nodes per project
* Acceptable startup payload if lightweight

---

# 10. Future Extensions

## Vector Search

* semantic search across notes/documents
* related content suggestions

## AI Features

* summarize project sections
* contextual writing assistance

## Sync

* incremental updates via revision tokens

---

# 11. Summary

This design:

* Uses **PostgreSQL as a hybrid relational + JSON system**
* Keeps **tree structure relational**
* Stores **flexible metadata in JSONB**
* Separates **heavy content from navigation data**
* Uses **lazy loading for performance**
* Enables **future AI and vector features**
* Supports **incremental evolution without major rewrites**

---
