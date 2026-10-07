# Notification Settings — what's new for you

- Table names in the list, the detail view, and the "Tables" filter now show a readable name (e.g. "Bank Profile") instead of the raw database table name (e.g. `bank_profiles`).
- Sorting the list by the recipient names no longer crashes the page.
- Creating a notification rule now requires you to actually pick a channel, at least one table to watch, and at least one action — submitting a blank form no longer silently creates a useless rule.
- Everyone can still freely view, create, and edit notification settings — no permission required, same as before.
- Deleting is restricted to your own records — you can only delete a notification setting you created or one that lists you as a recipient. Deleting several at once only removes the ones you're allowed to touch; the rest are left alone.
- A bulk export is now available, matching the other modules — select rows, export, and a download link arrives as a notification.

# Notification Settings — full change index (build of 2026-10-07)

1. **Localization** — `ModelInspector::getAvailableModels()` now resolves each monitorable table's label through `PermissionLabeler::getEntityLabel()` (the same resolver already used for Permission/Role module labels) instead of a raw class-name guess; `NotificationSetting::getLocalizedTables()`/`getLocalizedActions()` wire this into the table column, infolist entry, the table groupings, the Tables filter, and the new exporter.
2. **Sort crash** — `showRecipients()`'s `recipient.*.name` column was `->sortable()` against a model accessor, not a real column; removed, since there's no clean column-level equivalent for a JSON array of recipient ids.
3. **Validation** — `notification_type`, `settings.tables`, and `settings.actions` are now required, with translated messages in all three locales.
4. **Ownership-scoped delete (final shape)** — a same-day first attempt added full role-based permission gating (`notification_setting.*`) on top of an ownership check; reverted on explicit instruction — view/create/edit/restore stay open to any authenticated user, no permission required. Delete alone is restricted, standalone, to the record's creator or a listed recipient — independent of role.
6. **Export** — new `NotificationSettingExporter` + queued `ExportNotificationSettings` job + bulk export action, matching every other master-data module.
7. **Bonus fix** — the factory that generates test/sample notification settings listed `sms` as a possible channel, which isn't a real option; corrected to the three real channels (in-app, email, both).
8. **Recipients are now required** — a new rule defaults to you as the recipient, so a rule can no longer be saved that silently notifies nobody.
9. **Faster list and form** — recipient names load in one lookup instead of one per row, and the user picker no longer loads every column of every user.
10. **"My Notifications" filter** — shows only the rules you created or receive; it is the last filter in the list.
