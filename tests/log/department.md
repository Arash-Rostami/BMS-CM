# Department — what's new for you

- A new 【#】 Master Data module: Departments — one list for both departments and cost centers (they share the same table), each row with its own active/inactive toggle.
- Turning a department off hides it from the Department and Cost-center dropdowns in every form (Purchase Requests, Users) without deleting its history.
- The toggle is edit-protected: users who can only view can't flip it, neither via the row toggle nor the bulk activate/deactivate buttons.
- Full parity with your other audited modules: search, filters (status, creator, updater, trashed), soft-delete + restore, creator/updater stamps, and one bulk export that arrives as a notification with a download link.
- Bulk import works the same as the other modules: download the blank template or filled example, upload it back, and rows are created — code left blank gets an automatic DEPT- number, re-uploading an existing code updates that row instead of duplicating it.
- The filled example now opens with its Farsi column readable in Excel (a UTF-8 BOM was missing).
- Nav icon is a grid (squares) — deliberately different from the Company and Bank building icons; entry also added to the landing-page workspace module list.
- Permission & Role screens show the module with proper localized names in all three languages (EN/FA/FR), including the previously missing "Restore" action label.

# Department — full change index (build of 2026-09-26)

1. **Schema** — `departments` migration: `is_active` (default true), `user_id`, `updated_by_id`, timestamps, soft deletes; dropped DB unique on `code` (soft-delete collision) → plain `idx_departments_code`; follow-up migration added indexes on `user_id` / `updated_by_id` / `is_active` (parity with `banks`).
2. **Model** — `Department`: audit kit (`General\Relationships` unaliased + own `Relationships as ExclusiveRelationships`, `UserStamps`, `SoftDeletes`, `HasScope`, `Localization`), `SCANNABLE_IDENTIFIER = 'code'`; `PurchaseRequest::department()/costCenter()` and `User::department()` now `->where('is_active', 1)` so inactive rows vanish from all form dropdowns.
3. **Resource** — new master module `DepartmentResource` + `ManageDepartments` page (Bank recipe): search by code, active/creator/updater/trashed filters, soft-delete + restore, creator/updater columns, global search (name/english_name/code), nav sort 8 with User/Role/Permission/NotificationSetting shifted to 9/10/11/12.
4. **Import/export** — flat twin of Bank Profile: 4-column importer (`code` optional + auto-`DEPT-` via `CodeGenerator` import-only map entry, `name` mandatory, `english_name`/`description` optional; re-upload matches by code and updates in place); 10-column exporter (BOM, formula-escape, Jalali dates) + queued `ExportDepartments` job with signed download-link notification.
5. **Permissions** — `department.{view,create,edit,delete,restore}` seeded and granted to `admin_junior`; removed 20 wrongly-seeded child-model permissions (`attachment.*`, `proforma_invoice_item.*`, `specification.*`, `correspondence_recipient.*`) from DB and from the seeder — RelationManager children inherit their parent's permission.
6. **Gating** — activate/deactivate bulk actions `->authorize(canEditAny())` and `is_active` ToggleColumn `->disabled()` without `department.edit` (new `canEditAny()` on `HasResourcePermissions`); Bank/other masters not yet gated.
7. **Icon** — `squares-2x2` grid, distinct from Company (`building-office-2`) and Bank (`building-library`); also in the landing-page workspace module list.
8. **Localization** — department strings ×3 locales; fa import/export = `وارد کردن دپارتمان‌ها` / `خروجی گرفتن از دپارتمان‌ها` (majority convention of the audited modules, not BankProfile's صدور); fr = `Importer/Exporter les départements` + fixed `helper_is_active` sentence; added the app-wide missing `general.actions.restore` key in en/fa/fr.
9. **Example CSV** — UTF-8 BOM prepended so Excel renders the Farsi column (same as PR/RO examples).
10. **Cleanup** — stray test department row #108 + its empty test PR-1618 force-deleted; today's orphan export folders removed.
11. **Tests** — 31 across the mirrored Resource/Model/Job layers (real dev-MySQL, transaction rollback), incl. activation-gating denial, import auto-code, re-upload update-in-place, column-count pins (4 import / 10 export), filled-example end-to-end, export job notification.
12. **Docs** — one consolidated sweep: `filamentPattern.md` (master list 12, Department notes), `modelsPattern.md` (trait group counts), `servicesPattern.md` (CodeGenerator 10th map entry), `importsPattern.md` (Department flat-consumer section), `jobsPattern.md` (ExportDepartments), `localizationPattern.md` (word-choice parity + actions.restore), CLAUDE.md (Master list, child-permission rule), `tests/qa-checklist.html` (Department module entry).- On phones, lists now stack into readable cards instead of forcing you to scroll sideways; a new top-bar toggle (visible on desktop-width screens) switches back to the classic table.
- Exported files are safer to open in Excel — cells that could act as hidden formulas are neutralized before writing.

# Department — 2026-10-07 follow-up

- Tried making the bulk Activate/Deactivate buttons smarter (only show when relevant) — didn't work reliably in the browser, reverted. Both buttons always show, same as before.
- Bulk Activate / Deactivate now only appear for people who can edit Departments.
