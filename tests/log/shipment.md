# Shipment — what changed for you

- The "Commercial Invoice" tab is now called "Commercial Invoice & Packing List."
- The record view has a new "History" tab showing every status change: from, to, who, and when.
- On phones, lists now stack into readable cards instead of forcing you to scroll sideways; a new top-bar toggle (visible on desktop-width screens) switches back to the classic table.
- Exported files are safer to open in Excel — cells that could act as hidden formulas are neutralized before writing.
- Create and edit forms open faster — the Extra Attributes tab now loads its contents only when you open it.
- Attachment uploads got a security hardening that rejects tampered file paths.
- Marking a file as Superseded (or seeing it's Archived) is now a plain dropdown next to each attachment, with a short note explaining what to do. It only shows the extra detail/link once a file's status actually changes.
- Fixed a rare issue where two people creating a shipment at the exact same moment could occasionally get the same shipment number, causing one save to fail. Numbers are now generated safely even when this happens — no more errors from this.
- A new topbar toggle lets you switch whether clicking a row in the list opens the record (as before) or jumps straight to editing it.
- Shipments can now be bulk-imported from a CSV/Excel file, the same way export already worked.
- The list shows at a glance which shipments are overdue or due soon, how many required documents have been received, and — if you turn them on — separate Container/Operation/Doc status columns.
- Typing in a B/L number that's already used by another shipment now shows a heads-up instead of letting it through silently.
- The two table columns that both just said "Progress" now say which one is which.
- Saving a shipment now also quietly saves any unsaved Commercial Invoice changes alongside it — no extra click needed, and it no longer risks being lost or wiping an unrelated custom field.
- Picking a Proforma Invoice to pre-fill the Commercial Invoice no longer leaves the Incoterms field with an invalid, unmatched value.
- Importing a file that's missing a required value (like which Registered Order or Carrier it belongs to) now shows a clear message instead of a technical error.
- The "View Record" button in notifications about a shipment now opens the record's page — it previously led to a page-not-found.
