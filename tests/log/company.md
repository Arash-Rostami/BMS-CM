# Company — what changed for you

- Companies can now be bulk-imported from a CSV/Excel file, the same way export already worked.
- A duplicate company name (English) is rejected outright on import, never silently merged into an existing record — editing an existing company still only happens through the Edit button.
- Deleting or deactivating a company that's still used on a Proforma Invoice, Purchase Order, Registered Order, Payment, Bank Profile, or Shipment is now blocked with a clear warning, instead of silently breaking those records.
- Companies with no type assigned (Seller, Buyer, Supplier, etc.) are now flagged, since an unclassified company is invisible to every seller/buyer picker elsewhere in the app.
- The create/edit form is now split into General and Classification tabs, with company types shown full-width instead of collapsed.
- The company list now shows an "In Use" count — how many records reference each company — as a colored badge: amber when referenced, gray when free.
- A tooltip now shows the full description on hover when it's too long to fit the table column.
- Export now works the standard way: one click, arrives as a notification with a download link, safe to open in Excel.
- Bulk Activate / Deactivate now only appear for people who can edit Companies.
