# Currency — what changed for you

- Currencies can now be bulk-imported from a CSV/Excel file, the same way export already worked.
- A duplicate currency name (Persian or English) is rejected outright on import, never silently merged into an existing record — editing an existing currency still only happens through the Edit button.
- Deleting or deactivating a currency that's still used on a Bank Profile, Payment, Proforma Invoice, Purchase Order, or Registered Order is now blocked with a clear warning, instead of silently breaking those records.
- The currency list now shows an "In Use" count — how many records reference each currency — as a colored badge: amber when referenced, gray when free. It can be filtered to only in-use or only unused currencies.
- Export now works the standard way: one click, arrives as a notification with a download link, safe to open in Excel.
- Bulk Activate / Deactivate now only appear for people who can edit Currencies.
