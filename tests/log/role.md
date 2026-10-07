# Role — what's new for you

- Fixed a crash: grouping the Role list, saving an edit, or closing the record viewer could throw a "Class not found" error. Gone now.
- Roles can now be exported, same as other Master Data modules.
- A role can no longer be saved with zero permissions attached — pick at least one module (or "Select All") first.
- The Users and Permissions columns now show colored icon badges instead of bare numbers — gray when empty, colored when the role has users or permissions.
- Deleting a role (single or bulk) now asks for confirmation and says how many users will lose access.
- New Duplicate action copies a role's grade and permissions into a new role named with `_copy` (users are not copied); a taken name shows a clear message instead of an error.
- The record viewer now groups permissions by module (e.g. "Payment: View, Create") with an "N of M permissions" summary instead of one long pile of tags.
