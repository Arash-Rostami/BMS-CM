# Notification Settings («اطلاعیه‌ها») — Backlog (audit + QA, 2026-10-08)

Sources: a read-only code audit and a QA run of about 190 executed checks (temporary tests, all deleted). Existing tests: 40 + 9 pass, but they never go through the real observer path. Nothing here is fixed yet except where marked DECIDED. Calendar work is separate and unaffected.

Legend: **DECIDED** = user decided, ready to implement · **PROPOSED** = my recommendation, needs the user's yes · **NOTE** = behaves per design, may surprise.

## How the module works today (design facts)
- A rule is one `NotificationSetting` row: tables (8 models: PurchaseRequest, ProformaInvoice, RegisteredOrder, PurchaseOrder, Payment, Shipment, Custom, BankProfile), actions (create / update / delete only), optional watched columns + one flat values list, recipients (user ids only), channel (in-app / email / all).
- The resource is open to every logged-in user (no Spatie permissions, by design).
- Notifications are sent synchronously inside the model save; no queue, no cache.

## 0. DECIDED access rule
**Edit, the `is_active` toggle, restore and bulk restore must follow the same rule as delete: only the rule's owner or a listed recipient. No admin special case. No permissions added to the module.**
- Before: a stranger can switch off, re-point or restore someone else's alert (QA: toggle denied? NO, restore allowed, bulk restore allowed, edit open).
- After: all of those are denied on the server for anyone who is neither owner nor recipient (both authorization method families, `authorizeIndividualRecords()` on bulk actions, toggle disabled), with a positive control test.

## A. Privacy / security
1. **HIGH — recipients without module access get the data** (audit 1, QA 3.noView, 6.leak.endToEnd)
   - Before: a user with no permissions can create a rule on payments with themselves as recipient and receive record ids, old/new values (for example a notes change) and a link, by in-app and email. The system never checks that a recipient may view the module.
   - After: at send time, anyone without `{module}.view` is skipped. The screen stays open to everyone.
   - Status: DONE (implemented and reviewed 2026-10-08).
2. **HIGH — the value picker lists real values from any column** (audit 2)
   - Before: anyone can browse real column values (including sensitive ones) of tables they cannot access.
   - After: pickers offer only modules the user can view, and skip sensitive columns.
   - Status: DONE (implemented and reviewed 2026-10-08). (open-to-everyone vs. pickers: user's call).
3. **MED — the update message lists every changed column, including system ones** (QA 4.db.columnsLeak)
   - Before: body/email shows unwatched fields and system ones ("Updated By Id: (empty) -> 31769").
   - After: only the watched columns; `updated_at` / `updated_by_id` never shown.
   - Status: DONE (implemented and reviewed 2026-10-08).

## B. Reliability
4. **HIGH — a mail outage breaks the business save** (QA 5.mailDown, audit 4)
   - Before: when SMTP is down, creating a request throws after the row is saved (user sees an error), and later recipients get nothing.
   - After: sends are queued after commit, and one failing recipient or rule never stops the others or the save.
   - Status: DONE (implemented and reviewed 2026-10-08).
5. **MED — the notification observer and the calendar observer can silence each other** (QA 8.calBoomCreate, 8.evalBoom)
   - Before: an exception in the calendar observer on create kills the notification, and an exception in the evaluator on update skips the calendar touch.
   - After: each side is isolated (caught and reported) so neither blocks the other.
   - Status: DONE (implemented and reviewed 2026-10-08). (Normal operation: no duplicates and no interference, QA 8.create / 8.update pass.)
6. **MED — a restore sends an "update" notification** (QA 1.*.restore, 2.restore.asUpdate)
   - Before: restoring a record notifies "updated: Deleted At" on all 8 models; there is no restore action.
   - After: either a real "restore" action, or restores are silent.
   - Status: DONE (implemented and reviewed 2026-10-08). (user's call: add the action or stay silent).
7. **MED — inactive users still get notified** (QA 3.inactive)
   - Before: a user with status inactive receives alerts.
   - After: inactive users are skipped.
   - Status: DONE (implemented and reviewed 2026-10-08).
8. **LOW — links** (QA, audit 11): a delete or force-delete notification links to an edit page of a record that no longer exists; mail links get a double slash when `APP_URL` ends with `/`.
   - After: delete links go to the list; the URL is joined safely.

## C. Condition logic ("column reaches value")
9. **MED — some conditions can never match** (QA 2.num, 2.date, 2.datetime, 2.fk.string, audit 3)
   - Before: values are compared strictly against raw model values: decimal, date and datetime columns never fire, and an id stored as the string "5" does not match 5.
   - After: both sides are normalized by column type before comparing.
   - Status: DONE (implemented and reviewed 2026-10-08). (Browser value types not testable here.)
10. **MED — it is not change-sensitive and values are global** — **APPROVED 2026-10-08 (user asked for it after a plain-words explanation); passed to the coder with per-column values, backward compatible with old flat lists** (QA 2.text.unrelatedAlreadyEquals, 2.multi.cross)
    - Before: a record already at the value re-fires when any other watched column changes, and a value meant for column A can satisfy column B.
    - After: fires only when a watched column changes INTO a listed value; values are stored per column.
    - Status: DONE (per-column values; legacy flat lists removed, fail closed).
11. **LOW** — `tables` sent as a string saves an inert rule with no error; `is_active` stored as `1`, `"1"` or `"true"` counts as off (only imports/manual edits); a dead `orWhereJsonLength` branch; stale columns stay after changing tables.

## D. Language
12. **MED — all notification text is English** (QA 4.localeFa, audit 5)
    - Before: fa and fr users get English; the `User` model has no preferred locale.
    - After: translated text per recipient (needs a recipient locale source — a small decision: user setting or the app locale).
    - Status: DONE (implemented and reviewed 2026-10-08).

## E. Performance
13. **MED — cost on every save** (QA 9, audit 8)
    - Before: one uncached query per save of any of the 8 models, plus one recipient query per matching rule (52 queries at 50 rules; 102 with real delivery); a filesystem scan of `app/Models` runs on every request; the optional "Columns" column is very slow when toggled on.
    - After: active rules cached and refreshed on change, recipients loaded once per save, the model list built once.
    - Status: DONE (implemented and reviewed 2026-10-08).
14. **LOW** — the navigation badge stays stale after creating, deleting or restoring a setting (QA 7.badge): invalidate it on those events.

## F. Feature gaps versus the stated intent (design backlog, not bugs)
- Only 8 models (Company, User and others are not selectable); only create / update / delete (no restore, no force-delete action).
- Conditions: equality only, no operators, no AND/OR, no empty / not-empty; create and delete conditions work in the engine but the form cannot set them.
- Recipients: specific users only — no roles, no "anyone", no external emails.
- Status: IDEAS — each needs its own product decision.

## G. Behaviours that match the design but may surprise
- Users are notified of their own actions. A user in two rules gets two notifications (duplicates inside one rule collapse). Channel "all" = two notifications per recipient per event; a 50-record bulk save sends 100.
- `touch()` and query-builder `update()`, `delete()`, `insert()` raise no notification (known gap). Imports are silenced by `NotificationDispatcher::suspended()`.
- Soft delete and force delete are both the "delete" action.
- Empty/null cannot be picked as a value.

## Verified working
All 8 models fire exactly once on create, update and delete for the intended recipient and not on others; unlinked models fire nothing; channels (in-app, email, all) deliver for real; suspension flags reset safely; settings changes take effect on the next save (no stale cache); admin-form validation rejects bad tables, actions, columns, values, users and types; no interference with the calendar observers in normal operation.

## Suggested order
1. Section 0 (decided access rule) — small, one pass.
2. A1 + B7 (who may receive) — small, closes the leak.
3. B4 + B5 (reliability) — queue after commit, isolate each side.
4. C9 + C10 (conditions) — needs a yes because rule behaviour changes.
5. D12 (language), E13/E14 (performance), A2/A3, B6, B8, C11.
6. F items only if wanted.
