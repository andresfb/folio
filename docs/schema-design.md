Here is a practical **PostgreSQL schema direction** for a model that keeps the hierarchy relational, content explicit, and metadata flexible.

This is not the only good design, but it is a strong starting point for:

* folders and documents in one tree
* rich per-node metadata
* snapshots
* attached assets
* room for search and vectors later

## Design goals

The schema should support:

* a project contains a navigable tree
* each tree node can be a folder or document
* folders can contain folders and documents
* documents have editable content
* both folders and documents can carry metadata
* nodes can have synopsis, notes, goals, labels, UI state, etc.
* documents can have snapshots
* nodes can have attached assets like images
* the model can evolve without constant painful migrations

## Core idea

Use these main concepts:

* `projects`
* `nodes` for the tree
* `document_contents` for document bodies
* `node_snapshots`
* `assets`
* `node_assets`
* optional JSONB metadata on nodes and assets

That gives you a clean core.

---

# 1. Projects

A project is the top-level container.

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

### Notes

* `settings` can hold project-wide preferences like default view mode, compile/export settings, target word count, custom labels, etc.
* `user_id` can later become ownership or workspace membership logic.

---

# 2. Nodes: the tree backbone

This is the most important table.

Each folder/document in the sidebar tree is a node.

```sql
create table nodes (
    id uuid primary key,
    project_id uuid not null references projects(id) on delete cascade,
    parent_id uuid references nodes(id) on delete cascade,

    kind text not null check (kind in ('folder', 'document')),

    title text not null default '',
    slug text,

    sort_order numeric(20,10) not null default 0,

    metadata jsonb not null default '{}'::jsonb,

    archived_at timestamptz,
    deleted_at timestamptz,

    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),

    constraint nodes_parent_same_project
        check (id <> parent_id)
);
```

## Why this works

* `kind` lets folders and documents live in the same tree
* `parent_id` creates the hierarchy
* `sort_order` controls sibling ordering
* `metadata` holds flexible per-node attributes
* soft-delete/archive fields help future trash/archive features

## Suggested indexes

```sql
create index idx_nodes_project_id on nodes(project_id);
create index idx_nodes_parent_id on nodes(parent_id);
create index idx_nodes_project_parent_order on nodes(project_id, parent_id, sort_order);
create index idx_nodes_kind on nodes(kind);
create index idx_nodes_metadata_gin on nodes using gin (metadata);
```

## What goes in `nodes.metadata`

Good candidates:

* synopsis
* notes preview
* custom fields
* document status
* color/icon
* writing goal summary
* inspector state
* labels cache
* POV character, scene type, etc.

Example:

```json
{
  "synopsis": "Elena arrives at the harbor and discovers the ledger is missing.",
  "status": "draft",
  "label": "blue",
  "goal": {
    "type": "word_count",
    "target": 1200
  },
  "ui": {
    "collapsed": false,
    "icon": "document"
  }
}
```

Do not force everything into JSON forever. Promote fields to columns or tables once they become important and heavily queried.

---

# 3. Document content

Do not put large editable bodies directly in `nodes`.

Use a separate table for document content.

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

## Why separate this table

* folders do not need content rows
* document content can grow large
* easier to snapshot independently
* easier to optimize text and search features later

## Constraint idea

Your application should ensure only `nodes.kind = 'document'` get rows here. PostgreSQL cannot easily enforce that with a simple FK, so this is usually handled in app logic or a trigger.

---

# 4. Snapshots

Snapshots are real entities, not just metadata.

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

## Example `metadata`

```json
{
  "reason": "before major revision",
  "word_count": 1482,
  "author_note": "Cutting the exposition from the first half"
}
```

## Indexes

```sql
create index idx_node_snapshots_node_id on node_snapshots(node_id);
create index idx_node_snapshots_created_at on node_snapshots(created_at);
```

## Why store full body in each snapshot

Because snapshots are historical copies. Keeping the full text makes restore simple and robust.

---

# 5. Assets

Assets should be first-class.

```sql
create table assets (
    id uuid primary key,
    project_id uuid not null references projects(id) on delete cascade,
    storage_key text not null,
    original_filename text not null,
    mime_type text not null,
    size_bytes bigint not null,
    checksum text,
    width integer,
    height integer,
    duration_ms integer,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now()
);
```

## Notes

This supports:

* images
* PDFs
* audio
* arbitrary attachments later

`storage_key` might point to:

* local disk path
* S3 object key
* some managed file storage reference

## Indexes

```sql
create index idx_assets_project_id on assets(project_id);
create index idx_assets_metadata_gin on assets using gin (metadata);
```

---

# 6. Linking assets to nodes

One asset may be attached to many nodes, or embedded in one document and referenced elsewhere. So use a join table.

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

## Example roles

* `attachment`
* `cover`
* `inline`
* `reference`

## Example metadata

```json
{
  "caption": "Map of the old harbor",
  "alt_text": "Hand-drawn map with three piers",
  "position": {
    "paragraph": 8
  }
}
```

---

# 7. Optional: node notes as first-class rows

If notes become more than a single blob, give them a table.

```sql
create table node_notes (
    id uuid primary key,
    node_id uuid not null references nodes(id) on delete cascade,
    kind text not null default 'general',
    body text not null default '',
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);
```

This is better than JSON if you want:

* multiple notes per node
* different note types
* separate editing history
* searchability
* timestamps

If you only want one inspector note and one synopsis early on, keep them in `nodes.metadata` first.

---

# 8. Optional: tags/labels as first-class data

If tags are important for filtering across a project, use tables.

```sql
create table tags (
    id uuid primary key,
    project_id uuid not null references projects(id) on delete cascade,
    name text not null,
    color text,
    created_at timestamptz not null default now(),
    unique (project_id, name)
);

create table node_tags (
    node_id uuid not null references nodes(id) on delete cascade,
    tag_id uuid not null references tags(id) on delete cascade,
    primary key (node_id, tag_id)
);
```

If labels are light UI decoration only, they can stay in JSON for a while.

---

# 9. Optional: writing goals as first-class rows

If goals will evolve, track them outside JSON.

```sql
create table node_goals (
    id uuid primary key,
    node_id uuid not null references nodes(id) on delete cascade,
    goal_type text not null check (goal_type in ('word_count', 'character_count', 'deadline')),
    target_value integer,
    target_date date,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);
```

If you only need simple goals for now, JSON is fine.

---

# 10. Hierarchy querying options

The simple `parent_id` model is good to start with.

For hierarchy operations, you have three common choices:

## Option A: adjacency list only

Just keep `parent_id`.

Pros:

* simplest
* easiest to reason about
* enough for many apps

Cons:

* deep tree queries require recursive CTEs

This is a good starting point.

Example recursive query for all descendants:

```sql
with recursive tree as (
    select id, project_id, parent_id, title, kind, sort_order, 0 as depth
    from nodes
    where id = $1

    union all

    select n.id, n.project_id, n.parent_id, n.title, n.kind, n.sort_order, t.depth + 1
    from nodes n
    join tree t on n.parent_id = t.id
)
select *
from tree
order by depth, sort_order;
```

## Option B: materialized path

Store something like a path column:

* `/root/part-1/chapter-3`

Useful for fast subtree reads, but renames/moves become more work.

## Option C: ltree extension

If you want stronger tree operations later, Postgres `ltree` is worth considering.

For now, I would start with adjacency list only.

---

# 11. Search support

For normal text search, you will probably want full-text search on:

* node titles
* synopsis
* notes
* document bodies

A later upgrade path:

```sql
alter table document_contents
add column search_vector tsvector;

create index idx_document_contents_search_vector
on document_contents using gin(search_vector);
```

And maybe a generated or maintained search vector for node title + synopsis.

That gives you exact-ish textual search. Later you can add vectors for semantic search.

---

# 12. Vector support later

When you are ready, add chunked semantic storage rather than embedding whole documents only.

Example:

```sql
create table document_chunks (
    id uuid primary key,
    node_id uuid not null references nodes(id) on delete cascade,
    chunk_index integer not null,
    content text not null,
    token_count integer,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now(),
    unique (node_id, chunk_index)
);
```

Then an embedding table, or an embedding column on chunks if using `pgvector`.

That stays compatible with this schema.

---

# 13. Recommended boundaries: what goes where

## In `nodes`

Put:

* identity
* hierarchy
* kind
* title
* ordering
* light flexible metadata

## In `document_contents`

Put:

* current editable body
* body stats
* excerpt/cache

## In `node_snapshots`

Put:

* historical copies of content

## In `assets` / `node_assets`

Put:

* file records and attachments

## In JSONB

Put:

* unstable product metadata
* optional node-specific fields
* display settings
* lightweight custom fields

## In separate tables later

Promote:

* high-query metadata
* repeated collections
* histories
* many-to-many labels/tags
* multi-note systems
* writing sessions

---

# 14. Example shape of a project

A project might look like this conceptually:

* Project: “The Glass Harbor”

  * Folder: “Part I”

    * Folder: “Chapter 1”

      * Document: “Arrival”
      * Document: “The Missing Ledger”
  * Folder: “Research”

    * Document: “Harbor Timeline”
    * Document: “Ship Terminology”
  * Folder: “Characters”

    * Document: “Elena”
    * Document: “Marek”

All of those are rows in `nodes`.
Only documents have rows in `document_contents`.
Some documents have rows in `node_snapshots`.
Any node can have attached assets through `node_assets`.

---

# 15. Minimal v1 schema

If you want the leanest version that still scales well, start with just:

* `projects`
* `nodes`
* `document_contents`
* `node_snapshots`
* `assets`
* `node_assets`

That is enough to build a lot.

Keep synopsis and notes in `nodes.metadata` at first.
Add first-class tables later only if those features get richer.

---

# 16. My recommended v1 DDL

This is the version I would actually start from.

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

create index idx_nodes_project_parent_order
on nodes(project_id, parent_id, sort_order);

create index idx_nodes_metadata_gin
on nodes using gin(metadata);

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

create table node_snapshots (
    id uuid primary key,
    node_id uuid not null references nodes(id) on delete cascade,
    name text not null,
    body text not null,
    format text not null default 'markdown',
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now()
);

create index idx_node_snapshots_node_created
on node_snapshots(node_id, created_at desc);

create table assets (
    id uuid primary key,
    project_id uuid not null references projects(id) on delete cascade,
    storage_key text not null,
    original_filename text not null,
    mime_type text not null,
    size_bytes bigint not null,
    checksum text,
    width integer,
    height integer,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now()
);

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

# 17. Laravel mapping

This schema maps cleanly to Laravel models:

* `Project`
* `Node`
* `DocumentContent`
* `NodeSnapshot`
* `Asset`
* `NodeAsset`

Likely relationships:

* `Project hasMany Node`
* `Node belongsTo Project`
* `Node belongsTo Node parent`
* `Node hasMany Node children`
* `Node hasOne DocumentContent`
* `Node hasMany NodeSnapshot`
* `Node belongsToMany Asset` through `node_assets` if you want that style
* JSON casts on `metadata` and `settings`

That is straightforward in Eloquent.

---

# 18. Recommendation

Start with:

* relational tree
* separate document body table
* snapshots as rows
* assets as rows
* `jsonb` for evolving metadata

Do not begin with a giant “whole project as JSON” design. You would likely regret it once you need querying, search, snapshots, and asset relationships.

The next useful step is probably to design the **API resource shapes** for:

* project tree listing
* node detail
* document content
* snapshot list
* asset attachment payloads
