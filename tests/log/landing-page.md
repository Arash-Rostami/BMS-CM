# Landing page & workspace — what changed for you

- Your workspace now shows every module in the system, but only the ones your role may actually open — no more tiles that lead to a "not allowed" screen. Notifications and Entity Attributes stay visible for everyone (they're open to all).
- Newly pin-able modules: Correspondence, Users, Roles, Permissions, Entity Attributes, Targets, and Calendar Rules.
- Pinned records now carry their status chip right on the tile, so you see where each deal stands without opening it (pins saved before this change re-gain their chip next time you pin them).
- The record picker's search rows show the status too, and odd raw values like "medium" now read properly in your language — same for Correspondence's priority and type.
- Recently opened records are offered at the top of the record picker as one-click pin candidates, in their own clearly boxed section.
- The theme/palette you pick now follows you everywhere instantly — change it on the dashboard and the landing page follows (and vice versa, even across separate browser tabs) — no full page refresh needed anymore. The same goes for dark/light mode: flip it anywhere, every open tab follows.
- On phones, the floating corner buttons (language, dark mode, logout, widgets) collapse into a square gear button that opens them as a dock row next to it.
- Pinned tiles now sit on a solid card with a shadow and a clear border instead of blending into the background, and all workspace boxes follow one consistent corner-rounding style based on their size.
- The Search tab's results also show each record's status chip.
- The Features tab was refreshed to describe what actually ships today: the built-in dashboard calendar (month grid, colored rule badges, day panel), rule sharing and outside-email alerts, the permission-shaped workspace, and wording that no longer overstates anything (a few old items promised more than the system does — corrected in all three languages).
- The top ("distinguishing") features section was regrouped from 6 uneven cards into 8 balanced ones and now lays out as two rows of 4 cards — the same 4-column grid as the standard section below it.

# Landing page & workspace — QA notes

1. No module of its own — covered by WorkspaceTest, FeaturesTest, WorkspaceSearchServiceTest, SearchServiceTest, TopbarTest, ConfigTest, plus the JS suite in resources/js (workspace/theme components).
2. Browser checks still owed by the user: permission-shaped module tiles (check with a low- and a high-privilege account), status chips on pinned tiles and Search results, localized urgency, the boxed "Recent" section, tile outline/shadow look, the mobile gear button + dock, and the palette carrying over from dashboard to landing without a refresh, and the Features tab's card heights / 2-rows-of-4 layout.