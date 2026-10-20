# Calendar Rules — what's new for you

- New "Alerts" sidebar group holds Notification Settings and Calendar Rules; a switcher at the top of each page jumps between them. Both also appear in the top "+" menu.
- A calendar rule says "this date field, shifted by N days, with these filters, alerts these people X days ahead". Matching records show on a month calendar (Dashboard tab) with colored badges, in Jalali or Gregorian.
- Alerts: one notification per rule per day per person, a heads-up before the date, on the day, then overdue (next day, then weekly, at most 3). Nobody gets alerts for a module they cannot view.
- Activity button on the calendar shows the history of any record (soft-deleted ones tagged) limited to rules you may see.
- Landing page has a "Needs attention" tab listing today's due items.
- Each rule chooses how it alerts: in-app, email, or both (same choice as Notification Settings; the email is in the language of the sending process).
- The rules list search also finds a rule by module or channel, and rules appear in the top-bar global search (by name).
- Rules can be previewed, toggled, exported; editing a rule re-syncs its hits in the background (queue workers required).
- Each rule has a sharing choice: private to you, specific people, a role, or everyone. Private rules — their settings and their history — are invisible to everyone else; duplicating a shared rule keeps its sharing.
- The rules list and the record view show how many records currently match each rule, so you can spot an empty rule before opening it.
- A one-click Duplicate action copies an existing rule as a starting point instead of building one from scratch.
- Email alerts can also go to outside addresses (people without an account in the system) — up to 10 per rule, checked for typos and repeats, and shown only to people who may edit the rule.
- Filter conditions on columns with a fixed set of choices (like a status or a transport mode) now offer a picker instead of free typing; rules saved earlier with typed text on those columns keep working unchanged.
- The match count next to each rule now counts only what you can actually see and what still matters — no more inflated numbers from old reminders or modules you can't open.
- A "Next due" column tells you when each rule's next event lands — red if already overdue, amber if this week — so you can scan the list instead of opening the calendar.
- Next to View there's a "See on calendar" action that jumps straight to the dashboard calendar already filtered to that rule.
- A new "What it watches" column (off by default — turn it on via the column toggle) spells out each rule's date field and conditions, so two similar rules are told apart without opening them.
- On the calendar, the colored rule chips under the grid are now click-to-filter buttons — click one to see only that rule, click again to clear.
- The landing page's "Needs attention" tab now shows the true total and says "+N more" when the list is capped, instead of quietly looking complete.
- The "Needs attention" tab now shows one month at a time (the Jalali month when your calendar is Persian) with previous/next arrows and a "Current month" button; the current month also lists every overdue item from earlier months, and the count, chips and pages follow the month you are viewing.
- The "Needs attention" tab has Previous / Upcoming / Unread / All chips next to the month arrows (Unread is on by default) and each item has a Mark read button (plus "Mark all on this page as read"); read items show only under All, dimmed with "Read · date time" and a Mark unread button.
- The selected-day table's grouping controls now stack one per row, read "Module" (localized) instead of the raw "Subject type", and the Farsi picker says «گروه‌بندی» without «براساس».
- The selected-day panel now shows each item as a card (name, rule, status) and pages 5 at a time (switch to 10 or 25), so a busy day no longer renders everything at once.
- Clicking a rule chip under the calendar now also opens the guide section («راهنما و جزئیات این شاخص») and shows that rule's details at the top: date used, alert days, conditions in plain words and the latest edits to the rule. The generic explanation steps aside while a rule is selected. Clearing the filter (the × button, the «Clear filter» chip, or clicking the chip again) closes it. With many rules, the chips load 12 at a time with a «Show more» button.
- Fixed in the calendar audit: a paused rule's hits and filter vanish immediately; the Go-to popover and «Today» no longer show the previous month; under the Persian calendar the month list no longer jumps back to page 1 inside the same month; picking a rule and a module that do not belong together no longer leaves an empty calendar; renaming a record or relinking records through an order's own form now refreshes the calendar within about a minute instead of overnight; very long rule names wrap instead of widening the panel.
- The side table has a «Whole month» button that lists every event of the month (5 per page); the Month/Agenda toggle and the agenda list are gone. Month and year «Go to» now works in one click, the ‹ › arrows sit around the month name, today's tile carries an «Today» tag, and an empty day tells you the date and when the next event is.
- The Activity button now shows module and record side by side, and its history is a timeline with a coloured icon per event (matched, date moved, alert sent, cleared, rule edited), the date on the right.
- Hover (desktop) or tap (phone) any sign — the activity icons, the red warning triangle, the dots, the count badge, the rule badge on a card — to see what it means.
- Items can be marked read: a «Mark as read» icon on every card of the selected-day table, plus «Mark selected as read / unread» in the bulk menu. Read items disappear from the calendar grid (dots, +N, overdue triangle) and from the default «Unread» view of the table; the filter icon in the table toolbar offers «Scope» (One day / Whole month) and «Read state» (Unread / Read / All), and read cards show «Read · date time». Moving an item's date or a new alert about it makes it unread again.
- The bell is separate: marking an alert read in the bell only clears the bell; marking all of an alert's items read on the cards also clears that alert in the bell. Marking cards unread never brings a bell alert back.



# Calendar Rules — QA notes

1. Tests: CalendarRuleResourceTest, CalendarGridWidgetTest, CalendarDayWidgetTest, CalendarActivityActionTest, AlertsHubTest, plus Services/Calendar and Jobs suites.
2. Browser checks still owed by the user: Needs-attention month navigator (Jalali + Gregorian, RTL arrows, Current month button), tile look, jump popover, Whole-month button, RTL toolbar, Activity button, Alerts group + switcher, top "+" entries, rule form, landing tab, bell icons — plus the new wave: legend chips as filter buttons (active chip tint), "Next due" colors, "What it watches" column toggle, "See on calendar" action, and the landing tab's total badge + "+N more". Attention chips: chips sit on the same row as the month arrows (wrap only when narrow), LTR and RTL, fa/fr labels; Previous/Upcoming switch each other off; Unread on by default; All shows read + unread; Mark read hides the row under Unread and updates the badge, module chips and page; Mark all on this page; read item dimmed with the Read line under All and Mark unread restores it; the dashboard calendar tiles and bell badge update at once; bell Mark as seen leaves items unread.
3. Production: run only the three calendar migrations by path; never plain `migrate`, never full `db:seed`.
4. Browser checks owed (read state): filter icon + popover (Scope, Read state) in LTR and RTL, defaults One day / Unread after load, Apply, Reset and chip x; Group-by select and filter icon on ONE row in the narrow panel (no direction select); row icon and bulk mark read/unread; grid tiles update at once; read cards dimmed with the timestamp (fa/fr/en, Jalali); bell badge drops when all items of an alert are read on cards; bell Mark as seen leaves the cards unread.
