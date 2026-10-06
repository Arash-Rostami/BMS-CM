# Payment — what changed for you

- The record view has a new "History" tab showing every status change: from, to, who, and when.
- Next to the status field, a collapsed "Approval Pipeline" panel shows every possible status and who (if anyone) needs to approve it — ready for when an admin sets up a staged approval flow for this module, same as Purchase Request already has.
- On phones, lists now stack into readable cards instead of forcing you to scroll sideways; a new top-bar toggle (visible on desktop-width screens) switches back to the classic table.
- Exported files are safer to open in Excel — cells that could act as hidden formulas are neutralized before writing.
- Create and edit forms open faster — the Extra Attributes tab now loads its contents only when you open it.
- Attachment uploads got a security hardening that rejects tampered file paths.
- The "Approval Pipeline" info button no longer shows twice — it's now only on the record page, not the main list.
- Marking a file as Superseded (or seeing it's Archived) is now a plain dropdown next to each attachment, with a short note explaining what to do. It only shows the extra detail/link once a file's status actually changes.
- Fixed a rare issue where two people creating a record at the exact same moment could occasionally get the same reference number, causing one save to fail. Numbers are now generated safely even when this happens — no more errors from this.
- A new topbar toggle lets you switch whether clicking a row in the list opens the record (as before) or jumps straight to editing it.
- Payments can now be bulk-imported and exported as CSV files, same as other modules — including payments linked to either a Purchase Order or a Registered Order.
- Searching by Payment Number now also matches values stored in a payment's custom fields, same as other modules.
- Picking a Purchase Order or Registered Order on a new payment now auto-fills the Payor, Payee, Bank, and Currency from that order, if those fields are still empty.
- Creating a payment now warns you if a payment to the same payee for the same order was already made in the last 24 hours — you can still proceed, it's just a heads-up.
- The list now shows whether a payment's entered total matches the system-calculated total, at a glance, without opening the record.
- Typing an IBAN now warns you if it differs from the last IBAN used for that payee — a quick check against entering the wrong bank details.
- Importing a file missing a required value now shows a clear message instead of a technical error.
- The "View Record" button in notifications about a payment now opens the record's page — it previously led to a page-not-found.
