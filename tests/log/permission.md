# Permission — what's new for you

- Searching permissions by name now actually works from the main search box (it was silently ignored before), and now matches what you see on screen (e.g. typing "Purchase Request") as well as the raw technical name — not just an exact prefix match. The separate per-row search was dropped, consistent with every other module.
- Permission names must now follow the `module.action` shape (e.g. `purchase_request.view`) — garbage names are rejected with a clear message.
- Permissions can now be exported, same as other Master Data modules — pick rows, export, and a download link arrives as a notification.
- The Roles and Users columns now show colored icon badges instead of bare numbers — gray when empty, colored when something is linked.
- Deleting a permission (single or bulk) now asks for confirmation and says how many roles and users will lose that access.
- The record viewer now lists the users who hold a permission directly, not just the roles.
- New "Not granted to anyone" filter finds permissions no role or user has.
