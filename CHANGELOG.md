# Changelog

All notable changes to Ratting Manager will be documented in this file.

## [1.0.0] (in preparation)

**First release.** There is nothing to update from: installing creates every table and adds the schedules.

### Ratting data

- Bounty ticks and ESS payouts are imported from both wallet journals SeAT pulls, for the corporations picked in Settings. The corporation journal names the ratter on every tick it taxes, so members with no wallet token are seen too.
- A tick seen in both journals is paired: the character journal gives what the character received, the corporation journal the corporation's cut.
- A tick only the corporation journal has gets its income worked out exactly from the cut, at the corporation's tax rate for that day. The rate for each day is read back from that day's paired ticks, falling back to the nearest day that had some and then to the rate SeAT holds.
- ESS payouts are paired and worked out the same way.
- Each tick's kill list is kept and counted once per fleet payout.
- NPCs are placed by faction, size and rank from SeAT's SDE, by group name, or by mass where a group says nothing of size. Officers and commanders come from their SDE groups and clone soldiers from their names; a rat with Commander or Officer only in its own name is a regular rat. Dreadnoughts, carriers, supercarriers and titans are capitals, and a hauler with Carrier in its name is not. The overseers of Angel Cartel DED complexes, whose names and groups say nothing of whose they are, are placed one by one.
- Ticks are grouped into sessions, and time is counted from the 20 minutes each tick stands for, once per player however many alts were ratting.
- A character's ticks count for the corporation they were in at the time. A taxed row in a character's own journal names the corporation that took the tax, which settles it however far behind SeAT's corporation history is, and a tick after someone has left is never credited to the corporation they left.
- A row that reaches SeAT late, days after it paid, is picked up by the next half-hourly import, which reaches back for it and rebuilds that day's summaries. The nightly 31-day read goes over the same ground as a backstop.
- A wallet token that comes back brings up to 30 days of journal with it, and the next import reaches back for those days and pairs them, so nothing is lost. While a director's token is gone, each taxed bounty row in a member's own journal gives the corporation's cut, to the ISK. The Corporation tab, Data Integrity and an alert say who has lost a token and the last day it can come back and lose nothing.
- A journal row the database will not take is left out and logged, and the rows around it still come in. A run that stops part way leaves a note, so the next one to finish builds what it left. Diagnostics says when either has happened.
- Names and corporations for ratters SeAT does not know come from the journal rows first, then a background lookup: ESI, with EVEWho and zKillboard behind it, the same lookup Mining Manager uses. Pages never wait on it; a character nobody has named yet shows as *Character info in progress*, and the page refreshes itself once the lookup has them.

### Pages

- **My Ratting** for every member: income, ISK per hour, hours, rats, active days, rank by hours and the corporation tax their ratting paid, with charts of income and tax by month, income by day, systems, factions and sizes, a weekday by hour map, each session's ISK per hour against the median, income by character, recent sessions, special spawns and personal records. Every chart says what it shows in a line under it.
- **Insights** on My Ratting: favourite and best-paying system, most active and best-paying day, most active and best-paying time of day, ratting streaks, sessions, rats per hour, favourite target and the ESS share of income. Hours by day of the week and by hour of the day, ISK per hour by system, every system ratted with its hours, share, income, ISK per hour and rats, and the NPCs killed most.
- **Month by character** on My Ratting: what each character earned in a month, bounties, ESS, corporation tax, hours, ISK per hour, ticks and rats, with the month's total across all of them, any of the last 12 months a pick away. Below it, each character's income month by month.
- **Corporation tab** for directors: totals, the corporation's cut, median ISK per hour, coverage, the leaderboard, income by month and day, who earns the income, when the corporation rats, active ratters per month, ratters coming and going, what the corporation kills, untracked ratters, players who have gone quiet, and special spawns.
- **Insights** on the Corporation tab: the same as on My Ratting for the whole corporation, plus the most players ratting at once, players per active day and income per player. Hours by day of the week, players ratting by hour, ISK per hour by system, every system with how many players ratted there and who ratted there most, and the NPCs killed most.
- **Month by month** on the Corporation tab: what the players earned and what landed in the corporation's wallet each month, the tax rate that makes, players, characters, hours, ISK per hour and income per player, with a chart of earned against taxed.
- **Monthly Report** for directors: one calendar month for the corporation's books, with ISK ratted, the corporation's cut, what the pilots received, how many systems, constellations and regions it covers, the top systems, and everyone who ratted for it that month, biggest first, with a CSV download.
- **ISK ratted**, what the rats paid before tax: what the pilots received plus the corporation's cut. It is on the Monthly Report, the Corporation tab, Month by month, the directors' leaderboard and its CSV, worked out when a page is read, so it needs nothing rebuilt and stands after raw ticks are dropped.
- **Leaderboard**: rank and hours for members, every column for directors, who can download it as a CSV. ISK is written as plain numbers a spreadsheet can add up, and text a spreadsheet would run as a formula is kept as text.
- A player none of whose characters is in the corporations shown any more is tagged as an ex-member, with the day they left, and on hover the time in EVE time and their last tick, on the Monthly Report, the leaderboard, the Corporation tab, Gone quiet and the Ratter Lookup. Their ratting still counts for the corporation they earned it in, so a past month still lists them, and a tick from before they left still counts when it lands after they have gone. A player's page also says what landed in the corporation's wallet from them. `ratting-manager:track-departures` reads the time from EVE's corporation history in the background: SeAT's copy, then ESI and EVEWho. With no history to go on, the tag says when they were gone by.
- Everyone who rats is watched for leaving, token or no token: SeAT's member tracking and affiliations first, then ESI, EVEWho and zKillboard for whoever SeAT cannot vouch for, with the newest answer winning.
- **Not authed with SeAT**: a badge on a player with no live token, on the same pages and in the CSVs, saying whether they were never added or have lost every token. They still count, from the corporation journal.
- Directors read the tracked corporations their characters are in now, and admins all of them, checked on the server for every page and download. **Close the pages to people who have left** under Settings can also close the member and director pages to anyone with no character in one. It is off by default.
- Picks in the period, month, character and corporation lists apply as they are made. A custom range keeps its button, since it takes two dates.
- The Corporation tab is split into sections for an overview, players, when, where, kills and month by month, and says how many systems, constellations and regions the period covers.
- Every period picker offers this month, last month, the last 30 or 90 days, the last 12 months, any of the 11 calendar months before this one, or a custom range of days within the last year. The change on the period before is left out when that period reaches back further than the imported ratting.
- Long lists on the dashboards (sessions, systems, special spawns, untracked ratters, players gone quiet and the Corporation tab's leaderboard) show their first 10 rows, with **View all** opening the whole list on its own page, searchable and sortable.
- **Ratter Lookup** for directors: every player who ratted in the last 90 days, each opening onto their ratting as they see it, with their rank by hours.
- The Ratter Lookup, ratter pages, Leaderboard and full lists sit in the same dark body as the dashboard, under a tab naming the page.
- **Settings**, in sections: corporations to track, picked by searching SeAT's corporations and confirming after a look at each one's ticker, alliance, tax rate, members, characters on SeAT and journal; session gap, the Gone quiet lists, the members' leaderboard size and extra NPC types to watch; Webhooks; Notifications; and the Routing Map.
- **Help & Documentation**, in the menu for every Ratting Manager permission, with the version installed against the latest release and a search across every section; and **Diagnostics** with Health Checks, Master Test, Settings Health, Data Integrity, Tick Trace and Notification Testing. Unplaced NPCs leaves out the Crimson Harvest event's rats and the ones the game marks with a diamond, and Data Integrity says how much each table takes up.

### Notifications

- Posts to Discord webhooks, and to Slack incoming webhooks as plain text. A webhook is for every tracked corporation or for one, and only gets that corporation's posts.
- Each notification type has a switch and a default role, and is sent to the webhooks ticked for it, each with a role of its own if wanted. The webhook's own role comes first, then the default; *No ping* mentions nobody. Only the role or user named is ever pinged. Roles can be picked by name from SeAT Broadcast or SeAT Connector when either is installed. Nothing is bound on install.
- What a webhook carries is ticked when adding it, and its edit button opens it with its notifications filled in, to change any of them. The URL is stored encrypted with SeAT's `APP_KEY` and never sent back to the page; left empty when editing, it stays as it is. If the key changes, Settings asks for it again and nothing is sent until it has it.
- **Special spawn**: an NPC of the chosen rank or above (clone soldier, commander, officer), a capital rat or an NPC on the watch list, one post per spawn however many fleet members shared it.
- **Ratting ship lost**: a tracked pilot lost a ship in the system they had a tick in shortly before, with a zKillboard link. Tractor units and other deployables are not ships and are not posted. The final blow is named from SeAT, or looked up from ESI, EVEWho or zKillboard when SeAT does not know the pilot, as most hunters are.
- **Outsiders in home space**: ESI's hourly NPC kill count for each system of the home space, less what the tracked corporations killed there, once the journals for that hour are in. The home space is the regions, constellations and systems listed on the alert. With none listed it sends nothing even when switched on, and Settings, the Routing Map and Diagnostics say so.
- **Data stalled**: a corporation's wallet journal has stopped coming in, with the last day a token can come back and lose nothing, or the import has stopped running, and again when it is back.
- **Wallet token lost**: a ratter's token is lost or revoked, or their journal has stopped coming in, with the last day it can come back and lose nothing, and again a week before that day.
- **Untracked ratter** and **Ratter gone quiet**: one post per corporation listing the characters or players new to the list.
- **Director Report**, weekly and monthly: what the players earned, what landed in the corporation's wallet and the tax rate, the change on the period before, players, hours, median ISK per hour, rats and top systems, with players ranked by income (tax paid beside it), hours, ISK per hour and corporation tax paid.
- **Rankings**, weekly and monthly: players ranked by hours only, with no ISK, for members' channels.
- Players are ranked as a main with all their alts.
- Each alert remembers what it has sent and never sends the same thing twice; a post every bound webhook refused is tried again on the next run. Every send is recorded, and Notification Testing shows each webhook's delivery health and sends a sample of any notification to any webhook.
- The **Routing Map** shows where every notification goes and who it mentions, as it will be sent.

### Commands and schedule

- `ratting-manager:initialize` for the first import, of the last 365 days SeAT holds since no page shows more, with a progress bar for each step, `ratting-manager:process` for the scheduled one, `ratting-manager:trace-tick` to follow one journal row, and a command for each step of the import.
- `process` runs twice an hour over the last two days, further back when SeAT has just added older rows, and once a day over the last 31 as a backstop.
- `resolve-characters --requested` runs every minute for the characters pages asked for, and the full lookup every 10 minutes.
- `ratting-manager:track-departures` runs hourly, looking up each move once.
- `ratting-manager:notify` checks the alerts twice an hour, five minutes after the import; `ratting-manager:poll-system-kills` keeps ESI's kill counts hourly; `ratting-manager:send-reports` sends the reports that are due daily at 11:20 EVE.
- `ratting-manager:drop-old-ticks` runs nightly and keeps every table to its time. See Speed and size.

### ESI

- Pages never call anything. The plugin reads what SeAT already pulls: wallet journals, killmails, affiliations, corporation histories and member tracking.
- It calls ESI itself for four things, all public and with no token: where a ratter SeAT's member tracking does not list is now, once a day and one request per 1000 characters; a name nobody has on file; a corporation history SeAT's copy cannot answer, once per move; and the hourly kill counts, only while the Outsiders alert has a home space.
- It stops calling ESI while the error budget it shares with SeAT is low, and EVEWho and zKillboard answer instead.

### Speed and size

- Every figure is read from the plugin's own tables, indexed for the way the pages and the import read them, checked against a copy 20 times the size of a live install.
- SeAT's own tables are only ever read, and those reads go straight to the rows they need: the import starts at its window's first journal row, and what is new since the last run is found by row number, so a server with years of journal costs no more than a new one. Charts of income, systems and kills come from day by day totals, and whole months within a period from month by month totals; only time and player counts read the ticks, once per page, as a stream.
- Those ticks are read from one compact row per character per day, holding each tick's second, system, ISK, rats and kill group with its kill list. Alts and fleets are still counted once when a page is read, so the same counting runs over the same values and an alt linked later still corrects its player's time.
- The import keeps those rows and the monthly totals up to date along with the daily ones, and fills them from what is on file the first time it runs. Until then the pages read the ticks and daily totals, and show the same figures.
- Figures are kept until the next import changes them, and the ones the Corporation tab opens with are worked out straight after each import. A year of a 200-character corporation opens in about two seconds the first time and at once after that.
- Each import rebuilds the sessions and daily totals of only the characters and days it wrote ticks or payouts for. Journal rows that reach SeAT late are read by the next import, and rebuild just the days they belong to, and a run that brings in nothing keeps the cached figures.
- Sessions, ratting time and rats are counted as the ticks stream past instead of being held in memory, and new ticks are written in batches.
- My Ratting and the rest of the Corporation tab are cached with the other figures, and charts are drawn as they come into view.
- Alert records that can no longer change what is sent, closed ones and one-off events such as an outsider hour or a ship lost, are cleared once they are 90 days old.
- The webhook delivery log has an index on its time, for clearing old entries and for the 24 hour counts in Diagnostics.
- **Keep raw ticks** in Settings can delete ticks, kill rows, ESS payouts and daily kill totals older than a number of days, 45 at the least, once the summaries hold their days. It's off until set. Pages and reports keep every figure, charts of what was killed reading the character days' kill lists for those days, and those days can no longer be rebuilt, traced or imported again. `ratting-manager:drop-old-ticks --dry-run` says what would go, and `ratting-manager:restore-ticks` brings up to a year back from SeAT's journals, showing what would change and asking first.
- No page shows more than a year, so nothing is kept for longer: daily summaries, character days, sessions, special spawns and everything else tied to a day go after 365 days, whatever Keep raw ticks says. The monthly summaries stay 25 months, so the last 12 can be compared with the 12 before them. Personal records cover the last 12 months.

### Tests

- Unit tests for the sums and rules, and checks on the source itself: Blade directives closed the way Blade reads them, language keys that exist, migrations named as they should be, nothing writing to SeAT's tables. They need no SeAT and no database, and run on every push on PHP 8.1 to 8.4.
