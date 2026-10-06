# Custom (Customs Clearance) — what changed for you

- The record view has a new "History" tab showing every status change: from, to, who, and when.
- On phones, lists now stack into readable cards instead of forcing you to scroll sideways; a new top-bar toggle (visible on desktop-width screens) switches back to the classic table.
- Exported files are safer to open in Excel — cells that could act as hidden formulas are neutralized before writing.
- Create and edit forms open faster — the Extra Attributes tab now loads its contents only when you open it.
- Attachment uploads got a security hardening that rejects tampered file paths.
- Marking a file as Superseded (or seeing it's Archived) is now a plain dropdown next to each attachment, with a short note explaining what to do. It only shows the extra detail/link once a file's status actually changes.
- Fixed a rare issue where two people creating a customs record at the exact same moment could occasionally get the same reference number, causing one save to fail. Numbers are now generated safely even when this happens — no more errors from this.
- A new topbar toggle lets you switch whether clicking a row in the list opens the record (as before) or jumps straight to editing it.
- Custom records can now be bulk-imported from a CSV/Excel file, the same way export already worked.
- Fixed: the clearance-type label and filter weren't matching real records before — they do now.
- The list now shows a warning when a percentage-type clearance still has money or a guarantee outstanding, plus how many days a clearance has been pending.
- Two date fields that only matter for percentage-type clearances now hide themselves the rest of the time.
- Declaration numbers are now checked for duplicates, like other reference numbers already were.
- A confusing half-translated error message is now fully translated.
- The Create button (including the one shown for linked correspondence) now matches the rest of the app's look.
- Importing a file missing a required value now shows a clear message instead of a technical error.
