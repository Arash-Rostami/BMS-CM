# Bank Profile — what changed for you

- Search was completely broken — typing anything in the list search box did nothing. Fixed; search now actually filters the list.
- Custom (extra) attributes are now searchable too, and search now works correctly inside the Registered Order's linked Bank Profiles list.
- Sorting by the linked Product/Category column now works correctly, whether the row is linked to a Product or a Category.
- The table now shows Requested Amount and Requested Currency directly, so you don't have to open every record just to see the amount.
- Overdue payment and commitment dates are now flagged in red with a warning icon, instead of looking like any other date.
- An over-drawn commitment (documents received exceed the requested amount) now shows in red instead of the same green as a healthy balance.
- You can now bulk import and export Bank Profiles, one file per direction — same one-button experience as the other modules.
- Export includes the full set of figures (all computed totals, rates, dates, and audit fields), not just the raw fields the import file accepts.
- The date filters (payment due date, creation date) are now one inline row instead of two stacked rows — same as every other module.
- Empty-list messages (e.g. a linked list with nothing in it) now show properly translated text in every language instead of raw English.
- Creating a Bank Profile from inside a Registered Order now auto-fills its Registered Order field instead of asking you to pick it again; creating one on its own still lets you pick it.
- The record view has a new "History" tab showing every status change: from, to, who, and when.
- Next to the status field, a collapsed "Approval Pipeline" panel shows every possible status and who (if anyone) needs to approve it — ready for when an admin sets up a staged approval flow for this module, same as Purchase Request already has.
