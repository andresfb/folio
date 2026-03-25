## Product Requirements Document (PRD) — Phase 1

**Working name:** Folio (TUI Writing App)
**Platform:** Local-first Go TUI + SQLite

---

## 1. Objective

Build a distraction-free writing application focused on:

* Simple project-based writing
* Fast navigation between documents
* Reliable versioning via snapshots

The system must be:

* Fully local (no backend)
* Fast to load and operate
* Minimal in features and UI complexity

---

## 2. Goals

### Primary goals

* Enable users to write and organize documents within projects
* Provide a clear, low-friction way to save and restore snapshots
* Maintain fast performance even with large projects

### Secondary goals

* Keep architecture simple and extensible
* Enable future addition of sync or richer structure without refactor

---

## 3. Non-Goals (Phase 1)

* No cloud sync
* No collaboration
* No attachments or media
* No tagging or metadata system
* No rich text (plain text only)
* No plugins or extensibility system
* No advanced search (basic text search optional)

---

## 4. Core Concepts

### Project

A container for documents.

### Document

A unit of writing (scene, chapter, note). Editable.

### Snapshot

A full copy of a document at a point in time. Used for versioning and recovery.

---

## 5. User Experience

### Layout (TUI)

Three primary areas:

1. **Project / Document List (Sidebar)**

   * List of documents
   * Ordered manually
   * Highlighted active document

2. **Editor Pane (Main)**

   * Plain text editing
   * Full focus area

3. **Snapshot Panel (Optional toggle)**

   * List of snapshots for active document
   * Actions: create, restore

---

## 6. Core Features

### 6.1 Project Management

* Create project
* Open project
* List projects (optional for v1; can open via file path)

---

### 6.2 Document Management

* Create document
* Rename document
* Delete document
* Reorder documents (move up/down)
* Select document

---

### 6.3 Editing

* Edit document content in main pane
* Autosave behavior:

  * Debounced save (e.g., every 2–5 seconds)
  * Save on document switch
  * Save on app exit

---

### 6.4 Snapshots (Critical Feature)

* Create snapshot (manual trigger)
* Name snapshot (optional)
* List snapshots per document
* Restore snapshot → replaces document content
* Snapshots are immutable

---

### 6.5 Persistence

* All data stored in local SQLite database
* No external dependencies required

---

## 7. Functional Requirements

### Documents

* Must belong to a project
* Must have:

  * title
  * content
  * sort order

### Snapshots

* Must belong to a document
* Must store full content (no diffs)
* Must be restorable at any time

---

## 8. Data Model

### SQLite Schema

```sql
CREATE TABLE projects (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE documents (
    id TEXT PRIMARY KEY,
    project_id TEXT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    content TEXT NOT NULL DEFAULT '',
    sort_index INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE INDEX idx_documents_project_sort
ON documents(project_id, sort_index);

CREATE TABLE snapshots (
    id TEXT PRIMARY KEY,
    document_id TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    name TEXT,
    content TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE INDEX idx_snapshots_document_created
ON snapshots(document_id, created_at DESC);
```

---

## 9. Application Architecture

### Overview

Single binary application:

* TUI (presentation)
* Services (business logic)
* SQLite (storage)

---

### Suggested Package Structure

```text
internal/
  app/        # app lifecycle, wiring
  tui/        # UI components (views, keybindings)
  db/         # SQLite connection + migrations
  model/      # structs (Project, Document, Snapshot)
  service/    # business logic
```

---

### Core Services

#### ProjectService

* CreateProject(title)
* ListProjects()
* OpenProject(id)

#### DocumentService

* ListDocuments(projectID)
* CreateDocument(projectID, title)
* UpdateDocument(id, content)
* RenameDocument(id, title)
* DeleteDocument(id)
* ReorderDocument(id, newIndex)

#### SnapshotService

* CreateSnapshot(documentID, name)
* ListSnapshots(documentID)
* RestoreSnapshot(snapshotID)

---

## 10. State Management

### Active State

* current project
* current document
* in-memory document content

### Editing Model

* Load document into memory on selection
* Persist changes via:

  * debounce timer
  * explicit triggers (switch, exit)

---

## 11. Performance Considerations

### Project Loading

* Load:

  * project metadata
  * document list (id + title + sort_index only)
* Do NOT load document content until selected

### Snapshots

* Load snapshots only when snapshot panel is opened or document selected

---

## 12. Key Design Decisions

### 1. Full Snapshots (Not Diffs)

* Simpler implementation
* Faster restore
* Acceptable storage cost for text

### 2. Flat Document List (Phase 1)

* Avoid tree complexity early
* Can extend later with `parent_document_id`

### 3. Local-Only Architecture

* Eliminates API complexity
* Faster iteration
* Easier debugging

---

## 13. Future Extensions (Not in Scope)

* Document hierarchy (folders/tree)
* Full-text search (SQLite FTS)
* Export formats (Markdown, PDF)
* Sync engine (local-first + remote)
* Multi-device support
* Rich text / formatting

---

## 14. MVP Definition

Phase 1 is complete when:

* User can create a project
* User can create, edit, and reorder documents
* Autosave works reliably
* User can create and restore snapshots
* App remains responsive with ~100–300 documents

---

## 15. Risks

### Over-engineering

Mitigation: strict adherence to minimal schema and feature set

### Data loss

Mitigation:

* autosave
* SQLite WAL mode
* snapshots as explicit recovery points

### UI complexity creep

Mitigation:

* limit panes
* prioritize keyboard-driven flows

---

## 16. Success Criteria

* App launches instantly
* Navigation between documents is immediate
* Snapshot restore is reliable and predictable
* No cognitive overhead while writing

---

If needed next, the following can be defined:

* TUI layout and keybinding spec
* Go interfaces for services and repositories
* Migration strategy and DB initialization flow
