# Registered Order — what changed for you

- Total Amount and Total Quantity on the record's details view now show the real calculated total from the items — they used to always show blank/0.00 regardless of what was entered.
- You can now search by a custom field's name or value — in this list, and in the main spotlight search.
- You can now bulk import and export Registered Orders, one file per direction, with each order's items grouped automatically right under it — the same one-button experience already available for Purchase Requests and Proforma Invoices.
- The import file can also list existing Purchase Request, Proforma Invoice, and Purchase Order numbers to link to the order — matching ones get attached automatically, and an unrecognized number is noted rather than stopping the import.
- The export file uses today's real column labels instead of the older, separate set of export-only labels.
- Empty-list messages on the linked lists (Purchase Requests, Purchase Orders, Payments, etc.) now show properly translated text in every language instead of raw English.
- The date filters on this list are now one inline row instead of two stacked rows — same as every other module.
- The record view has a new "History" tab showing every status change: from, to, who, and when.
- Next to the status field, a collapsed "Approval Pipeline" panel shows every possible status and who (if anyone) needs to approve it — ready for when an admin sets up a staged approval flow for this module, same as Purchase Request already has.
- On phones, lists now stack into readable cards instead of forcing you to scroll sideways; a new top-bar toggle (visible on desktop-width screens) switches back to the classic table.
- Exported files are safer to open in Excel — cells that could act as hidden formulas are neutralized before writing.
- Create and edit forms open faster — the Extra Attributes tab now loads its contents only when you open it.
- Attachment uploads got a security hardening that rejects tampered file paths.
- The "Approval Pipeline" info button no longer shows twice — it's now only on the record page, not the main list.
- Marking a file as Superseded (or seeing it's Archived) is now a plain dropdown next to each attachment, with a short note explaining what to do. It only shows the extra detail/link once a file's status actually changes.
- Fixed a rare issue where two people creating an order at the exact same moment could occasionally get the same order number, causing one save to fail. Numbers are now generated safely even when this happens — no more errors from this.
- A new topbar toggle lets you switch whether clicking a row in the list opens the record (as before) or jumps straight to editing it.
- Importing a file missing a required value now shows a clear message instead of a technical error.
