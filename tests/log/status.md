# Status (Master Data) — what changed for you

- Editing a status now has a second tab, "Approval Workflow," where you can mark a status as requiring approval, set its position in the approval order, and pick exactly which people are allowed to grant it — no separate trip to Roles/Permissions needed.
- The list and record view now show at a glance whether a status is ordered and/or requires approval.
- On phones, lists now stack into readable cards instead of forcing you to scroll sideways; a new top-bar toggle (visible on desktop-width screens) switches back to the classic table.
- Exported files are safer to open in Excel — cells that could act as hidden formulas are neutralized before writing.
- Export now goes through the same queued, notification-with-download-link flow every other module uses (previously a different, older-style export).
- A warning now appears if an approval stage's order has a gap or a duplicate, so the approval chain can't silently truncate without anyone noticing.
- A warning now appears if a status requires approval but no one currently holds the permission to approve it — previously that status would be permanently stuck with zero visible signal.
- A warning now appears if a newly typed custom status type/name is a near-duplicate (likely a typo) of an existing one.
