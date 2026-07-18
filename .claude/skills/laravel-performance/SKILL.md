---
name: laravel-performance
description: Laravel 12 / Filament v5 performance guide. Fills performance gaps this project's own docs do not cover — never duplicates or overrides them.
disable-model-invocation: false
user-invocable: true
---

# Role Definition
Reference guide for performant Laravel 12 + Filament v5 code, scoped strictly to ground this project's own docs do not already cover.

## Precedence (read first)
This guide is supplementary only. On any conflict, these always win, in order:
1. `.claude/skills/code-reviewer/SKILL.md` (the `php-lead` skill)
2. This project's own docs: `app/Filament/filamentPattern.md`, `app/Livewire/livewirePattern.md`, any module-level `*.md`
3. Existing patterns already established elsewhere in the codebase

Anything here that overlaps with those is dead weight and should be deleted from this file, not enforced. Only what genuinely fills a performance gap they do not address stays.

## North Star
Minimality, creativity, performance — not abstraction for its own sake. Prefer the simplest, flattest, most direct code that solves the problem. Do not introduce interfaces, repositories, or wrapper classes beyond what the project's own docs already mandate (Action / Validator / Presenter / Form Object for Livewire; Schemas / Actions for Filament; the Service hierarchy for Search). Where those docs are silent, default to the most direct Eloquent/Laravel call rather than inventing new structure.

## Implementation Standards

### Database & Eloquent Operations — mandatory, no exceptions
* Prevent lazy loading globally during development (`Model::preventLazyLoading()`) to surface N+1 bugs immediately.
* Always explicitly eager-load relationships before iterating over them (`with()`, `load()`, `loadMissing()`), including nested relations via dot notation (`with('posts.comments.author')`).
* Use `Model::automaticallyEagerLoadRelationships()` for relations accessed repeatedly across the codebase.
* Use `withCount()`, `withExists()`, `withSum()`, `withAvg()` instead of loading full relations just to aggregate.
* Select only needed columns with `select()`; avoid `SELECT *` on wide tables.
* Use `chunk()`, `chunkById()`, `lazy()`, or `lazyById()` for large datasets instead of loading entire collections into memory.
* Wrap multi-step writes in database transactions (`DB::transaction()`).
* Add indexes for columns used in `WHERE`, `JOIN`, and `ORDER BY`; avoid `whereRaw` unless the query builder genuinely cannot express it.
* Isolate reusable query logic in Eloquent local scopes rather than duplicating `where()` chains — the one place extra structure earns its keep, since it removes duplication rather than adding ceremony.
* Verify eager-loading additions never bypass existing global scopes or the `scope()` method on `SearchResource` subclasses — performance work must never weaken authorization.

## Caching
* Cache expensive or frequently repeated queries/computations with `Cache::remember()`.
* Run `php artisan optimize` (config:cache, route:cache, view:cache) for production deploys.
* Invalidate caches explicitly on the writes that make them stale.

## Queues & Background Work
* Offload slow operations (emails, exports, image/file processing, webhooks, notifications) to queued jobs.
* Use job batching or chaining for multi-step async workflows instead of sequential synchronous calls.
* Keep queue workers observable; prefer Horizon when on Redis.

## Filament v5 Performance — the main gap this guide fills
`filamentPattern.md` defines folder structure, not performance. Enforce these wherever Filament code is touched:
* Defer loading non-critical UI elements (tabs, badges, relation managers, widgets) instead of loading everything on page mount.
* Lazy-load dashboard widgets; avoid aggressive polling unless data genuinely needs to be near-real-time.
* Eager load relationships used inside table columns/badges via `->modifyQueryUsing(fn ($query) => $query->with([...]))` to kill per-row N+1.
* Avoid expensive closures in `getStateUsing()` evaluated per row; compute it in the query instead.
* Paginate tables by default; never disable pagination on large datasets.
* For very large tables (logs, audit trails), consider database-level partitioning or indexing over application-level workarounds.

## Code-Level Idioms (only what isn't already covered elsewhere)
* Avoid resolving the service container repeatedly inside loops.
* Prefer filtering/aggregating at the query level over loading large datasets and using `Collection::filter()`/`map()` in PHP.
* Use typed properties and return types (PHP 8.2+) — clarity and engine optimization, not ceremony.

## How This Guide Is Used
* Claude reads this guide directly at session start and applies it while writing or reviewing code. The `PostToolUse` reviewer has no file access and cannot read this guide itself — it only independently checks the literal diff for narrower, overlapping red flags (queries in loops, obviously missing eager loads).
* (Scope: Anthropic-native Claude orchestration setup only. Under the Ollama setup where GLM-5.2 is the main orchestrator, the `ollama` skill governs delegation instead and this bullet is inert.) When delegating to glm-5.2:cloud, the delegation prompt instructs it to read this guide and `php-lead`, with the project's own docs always winning on conflict.
* Performance is one pass of the mandatory multi-pass review (see `php-lead` Execution Guideline 6): every diff gets a dedicated performance pass — N+1, missing eager loads, repeated container resolution, expensive CSS — independent of the correctness pass.
