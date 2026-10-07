# User — what's new for you

- Fixed the broken Department filter on the Users list.
- Phone numbers are now checked for a valid format when creating or editing a user.
- New users default to Active status instead of starting blank.
- The user form is now split into two tabs: the basics (name, phone, email, status, password, password confirmation) up front, everything else (company, department, position, role, photo) in a second tab.
- Password and Password Confirmation now sit side by side on the same row (Status moved next to Email instead of sitting between them).
- The record viewer no longer shows a Delete button for Users — deleting a user was never meant to be possible from here.
- Added bulk Activate / Deactivate for Users, matching the other Master Data modules. Both buttons always show (not conditional on what you've selected) — a smarter version was tried and didn't work reliably, so it was simplified back.
- Bulk Deactivate now asks for confirmation and never deactivates you or top-tier admins (it tells you how many were skipped).
- Last Login is shown by default, highlighted when empty or older than 30 days, with the exact date on hover; new "No login in 30 days" filter.
- Export now matches every other module (same CSV shape, Jalali dates, download notification) instead of the old generic exporter.
- Inactive users' rows are now dimmed in the list (full brightness on hover).
