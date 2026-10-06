# Product — what changed for you

- Creating a new product now checks the code first: if one already exists, you see that product's name/category/stock status instead of a bare checkmark; if the code belongs to an archived (deleted) product, you're told and can jump straight to it; if nothing matches, you confirm once and the full create form appears — no retyping the code.
- Fixed a bug where saving a new product silently created an empty, invisible "specification" record every time, even if you never opened that tab.
- Fixed a display bug where a product's selected import/export licenses showed as garbled text instead of their proper names, on both the record view and in exports.
- The "Attributes" tags field and the "Extra Specifications" field now explain, in plain language, when to use each one — they were easy to confuse before.
- Products can now be bulk-imported from a CSV/Excel file, the same way export already worked. A duplicate product code is rejected outright, never silently merged into the existing record.
- Export now works the standard way: one click, arrives as a notification with a download link, safe to open in Excel.
- The list now flags products missing a customs (HS) code, with a matching filter to find them all at once.
