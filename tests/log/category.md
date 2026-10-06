# Category — what changed for you

- Categories can now be bulk-imported from a CSV/Excel file, the same way export already worked. A duplicate category (matched by its generated URL slug) is rejected outright, never silently merged into an existing record.
- A category's hierarchy level is now always calculated automatically from its parent — no more free-typing a level number that could drift from reality.
- Fixed a real data bug: moving a category to a different parent could leave its own sub-categories silently pointing at the old, stale hierarchy — this is now fixed, and moving a category correctly updates every category beneath it.
- You're now stopped from setting a category as its own (grand-)child's parent, which would have corrupted the hierarchy.
- Deleting a category that still has sub-categories or products attached is now blocked with a clear warning.
- Picking a parent category now suggests the resulting hierarchy level as a hint, without forcing it.
- Export now works the standard way: one click, arrives as a notification with a download link, safe to open in Excel.
