# Entity Attribute — what's new for you

- Long custom-attribute values now show a short preview with the full value on hover, instead of a long unbroken block of text.
- The record viewer no longer offers Create or Edit here — this module is intentionally view-only; those actions go through the module the attribute actually belongs to.
- Complex values (like a full commercial invoice) now show as clean, labeled rows ("Currency: Euro", "Buyer Name: Persore", etc.) instead of raw JSON or a run-together comma list. Empty fields are hidden entirely, and list items (like invoice line items) no longer show a meaningless number.
- Each row now shows its own ID as a badge in the table.
- The Value column is now toggleable and hidden by default (it can hold a lot of text) — turn it back on from the column manager when you need it.
- The owner ID now reads like "Shipment #42" and links straight to that record (only if you are allowed to edit it).
- The Key column is now a badge, matching the record viewer.
- Filter dropdowns load faster (their option lists are cached for a few minutes).
