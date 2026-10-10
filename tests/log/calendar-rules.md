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
- The calendar's agenda list items (and the whole calendar on phones) are now clickable and open the record; a deleted record stays plain text.
- The landing page's "Needs attention" tab now shows the true total and says "+N more" when the list is capped, instead of quietly looking complete.

# Calendar Rules — QA notes

1. Tests: CalendarRuleResourceTest, CalendarGridWidgetTest, CalendarDayWidgetTest, CalendarActivityActionTest, AlertsHubTest, plus Services/Calendar and Jobs suites.
2. Browser checks still owed by the user: tile look, jump popover, month/agenda toggle, mobile agenda, RTL toolbar, Activity button, Alerts group + switcher, top "+" entries, rule form, landing tab, bell icons — plus the new wave: legend chips as filter buttons (active chip tint), agenda items open their record, "Next due" colors, "What it watches" column toggle, "See on calendar" action, and the landing tab's total badge + "+N more".
3. Production: run only the three calendar migrations by path; never plain `migrate`, never full `db:seed`.
