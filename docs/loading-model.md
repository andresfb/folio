Do **not** load the full project with all document bodies at startup.

For this kind of app, the usual approach is:

* load the **project shell**
* load the **tree structure**
* load only **lightweight metadata needed for navigation**
* lazy-load heavy things like document bodies, snapshots, full notes, assets, and rich inspector data

That is the safest default.

## The main principle

Treat the project as having different data layers:

### Layer 1: navigation data

Needed to render the binder/tree immediately:

* project id, title
* node id
* parent id
* kind
* title
* sort order
* maybe a few tiny flags like status/icon/has-children/has-snapshot

This should be cheap.

### Layer 2: sidebar-visible metadata

Only what is needed for the current UI view:

* synopsis preview if you show it in corkboard/outliner
* label color
* status
* target progress summary
* updated_at

Still relatively light.

### Layer 3: heavy node data

Load only on demand:

* full document body
* full notes
* snapshots
* attached assets
* comments/history
* semantic search context/chunks

This should not be part of initial open.

---

# Best default strategy

## On app open

Fetch:

* project record
* full node tree, but only in a **thin form**
* perhaps counts/flags, not heavy content

For most projects, sending a few hundred nodes with light metadata is not a big problem. A few hundred rows of:

* uuid
* parent
* title
* type
* sort order
* small JSON snippet

is very different from sending hundreds of full documents.

That is usually completely manageable.

## On node select

Fetch:

* full node detail
* document body if it is a document
* richer metadata
* notes/synopsis if not already included
* assets/snapshots only if that panel is opened

This keeps the startup fast.

---

# What I would not do first

I would **not** start with:

* downloading the full project including all content on every open
* a mandatory local SQLite sync engine before you even know you need one

Those add complexity quickly.

A local cache can be good, but a full offline-first sync system is a much bigger decision.

---

# A good API shape

You want different endpoints for different weights of data.

## 1. Project tree endpoint

Returns only binder/outliner-safe fields.

Example:
`GET /projects/{id}/tree`

Response shape:

* project info
* flat node list or nested tree
* each node includes only lightweight fields

Example node payload:

```json
{
  "id": "node-123",
  "parent_id": "node-100",
  "kind": "document",
  "title": "Arrival",
  "sort_order": 12,
  "status": "draft",
  "label": "blue",
  "has_children": false,
  "has_document": true,
  "has_notes": true,
  "has_snapshots": true,
  "updated_at": "2026-03-24T12:00:00Z"
}
```

Not included:

* body
* full notes
* snapshots list
* assets list

## 2. Node detail endpoint

Returns richer metadata for one node.

`GET /nodes/{id}`

Could include:

* title
* metadata
* synopsis
* inspector info
* goal
* counts
* recent snapshot count
* asset count

## 3. Document content endpoint

`GET /nodes/{id}/content`

Returns:

* body
* format
* word count
* revision info

## 4. Separate endpoints for optional panels

* `GET /nodes/{id}/snapshots`
* `GET /nodes/{id}/assets`
* `GET /nodes/{id}/notes`

That way the UI only asks for what the user actually opens.

---

# Should you send the full tree at startup?

Usually yes, but only the **thin tree**.

For a Scrivener-like app, the user expects immediate access to the binder. That means the tree itself should usually be loaded up front.

Even a large novel project with:

* 300–1000 nodes
* small titles
* small flags

is generally fine to fetch in one request.

Where it becomes expensive is when each node also includes:

* document body
* synopsis blobs
* notes blobs
* snapshot arrays
* asset arrays
* large metadata JSON

So the tree is not the real problem. **Payload bloat per node** is the problem.

---

# Flat list vs nested tree

For the API, I would return the tree as a **flat list**, not deeply nested JSON.

Example:

```json
{
  "project": { "id": "p1", "title": "Glass Harbor" },
  "nodes": [
    { "id": "1", "parent_id": null, "kind": "folder", "title": "Part I", "sort_order": 1 },
    { "id": "2", "parent_id": "1", "kind": "folder", "title": "Chapter 1", "sort_order": 1 },
    { "id": "3", "parent_id": "2", "kind": "document", "title": "Arrival", "sort_order": 1 }
  ]
}
```

Why:

* smaller and simpler
* easier for the TUI to rebuild into a tree
* easier to patch/update incrementally
* easier to cache locally

---

# Metadata strategy for startup

Do not think in terms of “all metadata” vs “no metadata.”
Think in terms of **view-specific metadata**.

## Include at startup only what the initial screen needs

For a binder-like sidebar, maybe:

* title
* type
* label
* icon/status
* child count
* document word count summary
* updated_at

## Load richer metadata later

For inspector view:

* synopsis
* long notes
* custom metadata
* writing goals details
* snapshot info
* references/assets

This is the right compromise.

---

# Caching locally

Yes, local caching is a good idea.

But there are levels to it.

## Level 1: simple cache

The TUI stores:

* the last loaded tree
* recently opened document bodies
* etags/version numbers/updated_at hashes

This can be:

* files on disk
* bolt db / bbolt
* badger
* SQLite

This is already useful and much simpler than full sync.

Benefits:

* faster reopen
* fewer repeated API calls
* better perceived performance
* can show the last known tree immediately while refreshing in background-like flow within the same session logic

For your app, this is probably enough at first.

## Level 2: local SQLite as cache

Also good.

You can keep:

* projects
* nodes
* selected metadata
* recent document contents
* maybe snapshots summaries

Then:

* on startup, render from local cache
* request server updates
* refresh changed nodes

This is a solid design if you want a snappy native-app feel.

## Level 3: full offline-first sync engine

This is much more complex.

You now need:

* dirty state tracking
* conflict resolution
* tombstones/deletes
* change feeds or sync tokens
* merge strategy
* retry semantics
* attachment sync rules

I would avoid building this until you really want offline editing across sessions.

---

# My recommendation for your stage

Use this progression:

## Phase 1

* no local DB required
* fetch thin project tree on open
* lazy-load document bodies and heavy metadata on selection
* keep an in-memory cache for opened nodes during the session

This is enough to build the product.

## Phase 2

* add a simple disk cache, likely SQLite
* cache tree + recently opened document contents
* use `updated_at` or revision ids to invalidate stale entries

This gives faster startup and smoother UX.

## Phase 3

* only later decide whether you want true offline sync

That is the point where local SQLite becomes not just cache, but part of your sync architecture.

---

# A very practical loading model

Here is a clean pattern.

## App startup

1. load cached tree if available
2. render binder immediately
3. call API for fresh tree
4. patch local state with changes
5. keep document pane empty until selection or reopen last-open document

## When user selects a document

1. show cached body if available
2. fetch fresh body from API
3. replace if changed
4. cache it locally

## When user opens inspector/snapshots/assets

fetch those panels separately

This feels fast without requiring a full sync engine.

---

# How to detect freshness

You need lightweight versioning.

Good options:

* `updated_at`
* `content_updated_at`
* integer `revision`
* per-node content hash
* project tree version

A very useful pattern is:

## On each node

* `updated_at` for metadata/title/etc.
* `content_updated_at` for document body changes

Then the TUI can avoid refetching unchanged data.

## On project

* `tree_updated_at` or `tree_revision`

Then the TUI can ask:

* has the tree changed since last sync?

If not, it can reuse the local tree cache.

---

# API patterns that help a lot

## 1. Separate tree revision from content revision

The tree changes when:

* node added
* moved
* renamed
* deleted
* reordered

The tree does **not** need to change when only document body changes.

That means the binder can remain stable while document content updates separately.

## 2. Include summary flags in tree payload

For example:

* `has_notes`
* `has_snapshots`
* `asset_count`
* `document_word_count`

This gives the UI enough awareness without loading heavy data.

## 3. Support incremental sync later

You can later add:

* `GET /projects/{id}/tree?since_revision=42`

or

* `GET /projects/{id}/changes?since=...`

You do not need this on day one, but it is a good direction.

---

# Should you use SQLite locally?

Yes, **as a cache**, very possibly.

No, **not necessarily as a full sync database** at first.

SQLite is a good option because:

* simple
* stable
* queryable
* good for structured cached tree/node/content records
* easy to bundle with a desktop/TUI app

For a Go TUI, SQLite is perfectly reasonable if you want persistence beyond a session.

But you can start simpler with:

* in-memory cache only
* maybe JSON files for a first pass

Then move to SQLite once the data model stabilizes.

---

# Suggested startup payload design

A project tree payload could include only:

* project id/title
* tree revision
* nodes:

  * id
  * parent_id
  * kind
  * title
  * sort_order
  * small display metadata only
  * lightweight counters/flags
  * updated_at

That will usually be very manageable even for large projects.

A document content payload should be separate and include:

* node_id
* content revision
* body
* word_count
* character_count
* maybe excerpt/format

A node detail payload should separately include:

* full metadata
* synopsis
* notes
* goals
* asset count
* snapshot count

---

# What I would do in your case

Given your current direction, I would do this:

## Initial architecture

* Laravel API returns a thin tree for the whole project
* Go TUI loads that tree at startup
* document body and rich metadata are lazy-loaded on selection
* opened documents stay cached in memory

## Next improvement

* add local SQLite cache for:

  * projects
  * thin node tree
  * recent document bodies
  * revision timestamps

## Avoid for now

* full offline-first sync
* syncing every metadata subtype independently
* loading full project contents at startup

---

# Bottom line

The best default is:

* **load the whole project tree, but only as lightweight structure**
* **lazy-load document contents and heavy metadata**
* **cache locally, first in memory, later in SQLite if needed**
* **do not build full sync until you actually need offline-first behavior**

That gives you:

* fast startup
* manageable payloads
* simple API design
* room to evolve toward local caching or offline support later

The next useful thing to design is probably the exact shape of:

1. the thin tree payload,
2. the node detail payload, and
3. the document content payload.
