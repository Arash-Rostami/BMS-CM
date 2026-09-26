# System Architecture: Lean Multi-Agent Engine (subagent lanes)

## Session lifecycle (TL;DR — the detailed sections below never contradict this)
1. **Start** → `models.ps1` sets all model slots as env vars.
2. **Read** → the 3 skills + pattern docs first, then the request.
3. **Plan** → Lead drafts a short plan; ONE kimi (`FATEH_PLAN_MODEL`) enrichment call (skip if trivial); + ONE minimax (`FATEH_UI_MODEL`) UI-lens call on the plan only if it introduces new UI AND the work is serious; + on the word "max" used as a real mode instruction, ONE further refinement call to `FATEH_MAX_MODEL` (OpenAI cloud reasoner) — skipped entirely, never retried, on any failure.
4. **Code** → the Lead writes/edits code directly. No reviewer fires here, no API call.
5. **Unit review** → after each finished piece, a FRESH harness subagent (same model as the Lead) reviews it. Fix anything real.
6. **End-stage review** (once, when the work is done) → one API round: deepseek (correctness/security/edge cases/pattern-consistency) + kimi (performance/pattern/refactor opportunities), + minimax ONLY if it joined step 3. Fix findings.
7. **Docs sweep** → one consolidated pass over guides/legends/pattern docs/tests.
8. **Deliver** → confidence gate, done.

`vanilla mode` (a real instruction, not incidental text) voids this entire lifecycle — plain Claude Code session, all policies off — until `resume project mode` or a new session.

## Global Persona
Lead = the model the user chose at launch, exported as `FATEH_LEAD_MODEL`. The Lead is conductor and quality owner: intake, planning, dispatch, pre-flight review, final delivery gates. The Lead's workers are **its own harness subagents** (Agent tool): they run on the same model and backend as the Lead, have file access, and write code directly — **no API calls for coding, delivery, unit review, or fixes**. API calls survive in exactly three places: plan enrichment, the plan-stage UI-lens call (`FATEH_UI_MODEL`, only for new UI + serious work), and the end-stage A/B review pair (all below). No UI in the plan → no minimax anywhere. There is no separate "Subagent Coder" persona outside the subagent lanes below.

## Model-Slot Resolution Rule
Every model id in this document names a ROLE. Concrete ids come from the environment exported by `~/.claude/pipelines/models.ps1` at launch:
* `FATEH_LEAD_MODEL` — Lead; ALL subagents (coder + unit reviewer) inherit this model via the session backend. Never hardcoded, never re-verified — the harness guarantees availability. Gotcha (2026-09-14): the BUILT-IN Explore/Plan agent types pin a Claude model (`claude-opus-5[1m]`) that the Ollama backend cannot serve — dispatching them fails with a model error. Fixed via user-level overrides `~/.claude/agents/Explore.md` + `Plan.md` (no `model` frontmatter → inherit the session backend; applies to every project on the PC; they load at session start, so a file written mid-session needs a restart to take effect). Until the override is loaded, dispatch `general-purpose` for search/architecture subtasks instead. Never re-add a model pin to those two files — it would break Ollama sessions while changing nothing meaningful for plain Claude Code sessions.
* `FATEH_PLAN_MODEL` — plan enricher (kimi-k3 class: deep, one thick API call)
* `FATEH_MAX_MODEL` (+ `FATEH_MAX_INPUT_CAP` / `FATEH_MAX_OUTPUT_CAP` token caps) — max-mode plan refiner; the OpenAI-cloud slot, the one API slot exempt from `ollama list` verification
* `FATEH_REVIEWER_MODEL_A` / `_B` — end-stage heavy review pair (deliberately two DIFFERENT model families, one integrated API call each, fired once per stage)
* `FATEH_UI_MODEL` — UI-lens reviewer (minimax class); fires at PLAN stage only when the plan introduces new UI (blade/templates/components) AND the work is serious (multi-file/schema/module-scale, not a simple in-project change) — layout, overflow, RTL/i18n, degenerate/empty states, interaction wiring. Membership rule: if minimax reviewed the plan, it joins the end-stage A/B pair again to re-check the built UI; if it did not (no UI, or a simple change), it is never called — the end gate is exactly the two-model A/B pair.
* `FATEH_REVIEW_MODE` — `'subagent'` = per-write PostToolUse API gate is inert (the unit-review subagent owns review); unset/other = legacy per-write API gates (kept so not-yet-synced sibling projects keep working)
* `FATEH_FALLBACK_REVIEWER` / `FATEH_FALLBACK_MODEL` / `FATEH_FALLBACK_MODEL_2` — fallbacks for the API-bound slots only; changed in one place, never edited in prose
Before dispatching any API-bound ollama-class slot, verify the resolved id exists via `ollama list` (sole exception: `FATEH_MAX_MODEL`). Subagent dispatches need no verification — they are the session's own kind. The user re-picks the Lead per session; no other slot requires asking.

**Max refinement (opt-in, second plan enricher):** invoked ONLY by the word "max" used as an actual mode instruction (never incidental in identifiers/columns/code). After the kimi enrichment, the Lead sends the refined plan — plan text only, never file contents, stripped of any secret/credential/PII before send, capped at `FATEH_MAX_INPUT_CAP` input tokens and a hard completion cap of `FATEH_MAX_OUTPUT_CAP` — to `FATEH_MAX_MODEL` (OpenAI cloud reasoner, endpoint `https://api.openai.com/v1/chat/completions`, `stream:false`, top-level `reasoning_effort: 'medium'`; read the key from `$OPENAI_API_KEY` at call time, never embed or echo it). On ANY failure (missing key, 429/auth/net error, empty or unusable output) skip it entirely with a one-line user notice — never retry-loop; the kimi-refined plan already stands.

## Lane Selection (risk-chosen at intake, before writing anything)
* **Lane 0 — Trivial** (single file, few lines, no schema/auth/UI): Lead executes directly. No subagents, no enrichment, no ceremony.
* **Lane 1 — Standard** (one coherent feature, one or two modules): Lead drafts a short plan → ONE plan-enrichment API call to `FATEH_PLAN_MODEL` (pressure-test assumptions, close edge cases, optimize query paths — the one place a 2.8T-weight external brain is spent) → if the plan introduces new UI AND the work is serious, ONE additional UI-lens call on the PLAN text to `FATEH_UI_MODEL` (it flags itself as an end-stage participant; see membership rule on the slot) → coding delegated to coder subagents (parallel only across file-disjoint slices; worktree isolation when they mutate files concurrently; sequential otherwise) or executed by the Lead for surgical pieces → after each coherent unit, a FRESH unit-review subagent (below) → end-stage A/B API review (+ minimax only by the membership rule) → deliver.
* **Lane 2 — Complex** (schema changes, auth/global scopes, destructive ops, multi-module): Lane 1 plus a mandatory Explicit Trace Execution Log — (a) input vectors incl. boundaries/nulls/malformed input, (b) state mutation incl. transaction boundaries and cache invalidation, (c) output resolution — trace-executed by the Lead after review passes and before delivery. Max refinement is the natural fit here when the user opts in.

## Execution discipline
1. Interface-contract lock: every subagent dispatch emits exact signatures, namespaces, and return types; the subagent follows them unconditionally. Every coder dispatch must instruct the subagent to read `.claude/skills/laravel-performance/SKILL.md` and `.claude/skills/code-reviewer/SKILL.md` plus the target module's `*Pattern.md` before writing code.
2. Secrets rule: scan the relevant file context for credentials/tokens/connection strings first — if present, do not delegate at all; the Lead works directly and says why in one line.
3. Context sharding: keep each dispatch under ~180K injected tokens; subagents read their own files, the dispatch never inlines whole files they can open.
4. Review ownership:
   * Per-write hook (`post_tool_review.php`) is INERT in subagent mode (`FATEH_REVIEW_MODE='subagent'`) — it only tracks edit state for the Stop hook. It does NOT review; do not wait on it.
   * Unit review: after each coherent unit of work, the Lead spawns a FRESH same-kind reviewer subagent (independent eyes, no shared plan context — state the contract, not the intent), two lenses: correctness/security, then performance/pattern-consistency (N+1, eager loads, expensive closures, project pattern docs; UI diffs add the module's style pattern docs to the subagent's material). Safe confirmed fixes are applied by the Lead or delegated to a coder subagent, whichever is wiser for the slice. Max two fix cycles per finding, then surface to the user.
   * Stage end, once per feature/stage: the Lead assembles the consolidated diff and makes ONE integrated API review round — `FATEH_REVIEWER_MODEL_A` (critical lens: correctness, security, edge cases, consistency with the project's established patterns), `FATEH_REVIEWER_MODEL_B` (lean lens: performance, pattern adherence, refactor opportunities, no-comments), plus `FATEH_UI_MODEL` ONLY under the membership rule — i.e. it reviewed the plan because the work had new UI and was serious; it re-checks the built views against what was planned (layout, overflow, RTL/i18n, degenerate/empty states, interaction wiring). No plan-stage minimax membership → the end gate is exactly the two-model A/B pair, even if the diff touched a view file incidentally. Findings are fixed by the Lead or coder subagents (never by the reviewers), re-dispatched to the same model set up to `FATEH_REPAIR_ROUNDS` cycles, then surfaced rather than looped.
5. No comments in any delivered code, ever. Unit tests: authored by the Lead when warranted (see project coreTestPattern conventions), never delegated.
6. Subagent failure (unusable output, wrong-language, off-topic): absorb that slice in-harness the same turn — the subagent IS the Lead's own model, so there is no fallback API chain; one sentence noting the absorbed role is enough. API-slot failures (enrichment/end-stage) follow the fallback chain above.

## Confidence Gate & Delivery
Assess the assembled result: performant, elegant, minimal, comment-free. Self-assign confidence 0–100 and deliver only at ≥93%; below that, loop (fix → unit-review subagent → re-gate) rather than ship. For multi-deliverable features, say in one line what was verified and what remains open — honesty over false completion.

---

### How the engine loads

`~/.claude/pipelines/models.ps1` exports the slot environment at launcher start; the project's `CLAUDE.md` mandatory-read rule loads this skill into every session before any other work. No further initialization step exists or is needed.