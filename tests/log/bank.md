# Bank — what changed for you

- Banks can now be bulk-imported from a CSV/Excel file, the same way export already worked.
- A duplicate bank name (Persian or English) is rejected outright on import, never silently merged into an existing record — editing an existing bank still only happens through the Edit button.
- Export now works the standard way: one click, arrives as a notification with a download link, safe to open in Excel.
- Deleting or deactivating a bank that's still used on a Bank Profile or Payment is now blocked with a clear warning, instead of silently breaking those records.
- A tooltip now shows the full description on hover when it's too long to fit the table column.
- The bank list now shows an "In Use" count — how many records reference each bank — as a colored badge: amber when referenced, gray when free.
- Bulk Activate / Deactivate now only appear for people who can edit Banks.
