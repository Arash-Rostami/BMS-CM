# OpenAI API Models — Price & Power Log

Verified live against this account's key (`sk-proj…`, 2026-07-10) via `/v1/models` + chat-completion pongs.
Prices = USD per 1M tokens, **Standard tier** (Batch = 50% off; Flex = 50% off; Priority ≈ 2×; cached input ≈ 10% of input).
"Chat" = works on `/v1/chat/completions` (what `ollama/SKILL.md` uses). `reasoning_effort` is a top-level param (low/medium/high/xhigh), NOT `reasoning:{effort}`. Use `max_completion_tokens`, not `max_tokens`.

## Frontier reasoning (chat ✓ verified)

| Model | In | Out | Ctx | Cutoff | Effort | Power note |
|---|---|---|---|---|---|---|
| **gpt-5.5** | $5 | $30 | 1.05M | Dec 2025 | med/high/xhigh | Newest flagship chat. Verified pong low+high. |
| **gpt-5.4** | $2.50 | $15 | 400K | Aug 2025 | low/med/high/xhigh | Current-gen balanced flagship. Verified pong low. |
| gpt-5.4-mini | $0.75 | $4.50 | 400K | Aug 2025 | low/med/high | Frontier-mini, cheap. |
| gpt-5.4-nano | $0.20 | $1.25 | 400K | Aug 2025 | low/med | Cheapest frontier. |
| o3 | $2 | $8 | 200K | older | low/med/high | Dedicated reasoning, cheaper out than 5.4. Verified. |
| o4-mini | $1.10 | $4.40 | 200K | older | low/med/high | Cheap reasoning. Verified. |

## Pro / Responses-only (NOT chat — would need `/v1/responses`)

| Model | In | Out | Note |
|---|---|---|---|
| gpt-5.5-pro | $30 | $180 | Strongest, but `not a chat model` (404). Responses API only. |
| gpt-5.4-pro | $30 | $180 | Same. |
| gpt-5.2-pro | $21 | $168 | Same. |
| gpt-5-pro | $15 | $120 | Same. |
| o1-pro | $150 | $600 | Responses API only, no streaming. |

## Earlier GPT-5 chat

| Model | In | Out | Ctx | Cutoff | Note |
|---|---|---|---|---|---|
| gpt-5.2 | $1.75 | $14 | 400K | — | |
| gpt-5.1 / gpt-5 | $1.25 | $10 | 400K | — | |
| gpt-5-mini | $0.25 | $2 | — | — | |
| gpt-5-nano | $0.05 | $0.40 | — | — | Cheapest of all. |
| o1 | $15 | $60 | 200K | Oct 2023 | Old, pricey, hidden reasoning tokens. |
| o3-mini | $1.10 | $4.40 | 200K | — | |

## Codex family (coding-specialized — chat ✓, but not a plan-refiner)

| Model | In | Out | Ctx | Note |
|---|---|---|---|---|
| gpt-5.3-codex | $1.75 | $14 | 400K | Most capable agentic coder (Feb 2026). Deprecates → 5.2-codex. |
| gpt-5.2-codex | $1.75 | $14 | 400K | Deprecated → 5.3-codex. |
| gpt-5.1-codex | $1.25 | $10 | 400K | Cheapest codex. low/med/high/xhigh. |
| gpt-5.1-codex-max / -mini | — | — | — | Max/min variants; price unconfirmed, near codex tier. |

## Legacy general (non-reasoning)

| Model | In | Out | Note |
|---|---|---|---|
| gpt-4.1 | $2 | $8 | 1M ctx, "smartest non-reasoning." |
| gpt-4.1-mini | $0.40 | $1.60 | |
| gpt-4o | $2.50 | $10 | 128K, multimodal. |
| gpt-4o-mini | $0.15 | $0.60 | |

## gpt-5.6 family — in docs, NOT on this account

Listed by third-party trackers but **`model_not_found` on this key** (verified 2026-07-10): `gpt-5.6-sol` ($5/$30), `gpt-5.6-terra` ($2.50/$15), `gpt-5.6-luna` ($1/$6). Not rolled out to this account/region yet — do not reference in `ollama/SKILL.md` until reachable.

## Hidden-token caveat
Reasoning models (o1/o3/o4-mini, gpt-5.x w/ effort) emit invisible reasoning tokens billed at the output rate. Budget ~2–5× visible output for high-effort calls.