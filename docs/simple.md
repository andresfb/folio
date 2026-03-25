Yes. For this project, a local-first TUI with SQLite is a strong choice.

For a distraction-free writing tool, PostgreSQL plus an API can be premature complexity unless one of these is already a requirement: multi-device sync, collaboration, web access, or remote backups. If none of those matter yet, the simpler architecture is likely the better one.

A good phase-1 shape is:

* TUI application
* SQLite database on disk
* Local file storage only if needed later
* Core entities:

  * `projects`
  * `documents`
  * `snapshots`

That gives you a working product with a clean mental model and leaves room to grow later.

## Why this is a good fit

SQLite fits this kind of app well because:

* the data is local, private, and fast
* schema migrations are simple
* no server deployment is needed
* the TUI can own all business logic directly
* snapshots become easy to implement atomically

This also matches your product goal. “Distraction free” usually benefits from a small internal model too. Fewer concepts means less UI clutter and less code fighting you while you build.

## Recommended model

Keep the tree simple in the UI, but even with a simplified product, I would still recommend one small structural choice:

* a project
* many documents inside the project
* snapshots attached to documents

You do not need notes, metadata, tags, attachments, or arbitrary nested folders right now.

If you want the absolute minimum, each document can just have an order index so the project reads as a linear binder/list.

## Suggested schema

This is enough to start:

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
  position INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE snapshots (
  id TEXT PRIMARY KEY,
  document_id TEXT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  title TEXT,
  content TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE INDEX idx_documents_project_id ON documents(project_id);
CREATE INDEX idx_documents_project_position ON documents(project_id, position);
CREATE INDEX idx_snapshots_document_id ON snapshots(document_id, created_at DESC);
```

## What each table means

`projects`

* Usually just one row per writing project
* Holds project title and timestamps

`documents`

* The active working pieces of writing
* `position` controls ordering in the sidebar/binder
* `content` stores the current editable text

`snapshots`

* Immutable copies of document content at a point in time
* Can be manual or automatic
* This is the main versioning mechanism

## Minimal feature set for phase 1

A solid first version could support only this:

* create/open project
* list documents
* create/rename/reorder/delete document
* edit document content
* create snapshot
* browse document snapshots
* restore snapshot into current document
* autosave current document
* basic search across document titles and content

That is already a real product.

## Important design decision: how snapshots should work

Make snapshots immutable. Do not edit them in place.

When restoring a snapshot:

* either replace current document content directly
* or safer: first create a snapshot of the current content, then restore the old one

That second option is better. It prevents “restore regret.”

A good restore flow is:

1. current document is snapshotted automatically
2. selected historical snapshot content is copied into `documents.content`
3. document `updated_at` changes

This keeps history safe without needing a complicated diff/version system.

## Practical app architecture

For the TUI, keep it very boring:

* `app` layer for commands and UI flows
* `db` layer for SQLite access
* `models` for project/document/snapshot structs
* `editor` integration for text editing behavior
* `services` for higher-level actions like snapshot creation, restore, reorder

Example shape:

```text
/src
  /app
  /db
  /models
  /services
  /ui
```

Or, if you want even simpler:

```text
/src
  main
  db
  store
  ui
```

The key is to keep SQL and UI separate early.

## Suggested behavior around saving

Use these rules:

* save document content automatically on edit debounce, blur, or document switch
* create snapshots manually from a command
* optionally create automatic snapshots:

  * before restore
  * before delete
  * every N minutes only if content changed

Do not create a snapshot on every keystroke. That will create noise.

## What to avoid for now

Do not add these yet:

* remote sync
* multi-user support
* arbitrary metadata
* notes on documents
* file attachments
* rich text
* plugin system
* nested folders unless you clearly need them

All of those can come later, and each one expands the product surface a lot.

## One thing I would still keep an eye on

Even if you do not want “tree complexity,” you may eventually want at least lightweight grouping. If that happens, add it later as a small extension instead of designing for it now.

The easiest upgrade path is:

* later add a nullable `parent_id` to `documents`
* or add a `sections` table

But I would not start there.

## Recommendation

Start with:

* one local SQLite DB per project, or one app DB containing many projects
* project -> ordered documents
* document -> immutable snapshots

That is enough structure to build the TUI and validate the workflow without overengineering the system.

A good product principle here is:

**current document state lives in `documents`; history lives in `snapshots`.**

That will take you far.

I can turn this into a compact phase-1 PRD and a migration-ready SQLite schema next.
