# Changelog

All notable changes to Ratting Manager will be documented in this file.

## [1.0.0] - 2026-11-13 - The Ecosystem Era: Connection Lost

First release. Ratting Manager reads the bounty ticks and ESS payouts in the corporation and character wallet journals SeAT already pulls, pairs the two, and turns them into a page of their own ratting for every member, a Corporation tab, Monthly Report, leaderboard and Ratter Lookup for directors, and Discord or Slack notifications. It needs no tokens or scopes of its own and works on its own; with SeAT Broadcast or SeAT Connector installed, notification roles can be picked by name.

**First release.** A server that ran the plugin before its release updates in place: every migration checks what is already there and does only what is left. If Keep raw ticks has never been saved there, the first night after updating deletes raw ticks older than 60 days, the default; save it as 0 beforehand to keep them.

> Mental model: every 20 minutes of ratting pays one tick, and SeAT holds it twice. The corporation journal has the corporation's cut and names the ratter; the character journal has what the character received. Pairing the two says who ratted, where, when, for how much and what they killed, and the cut alone is enough to work out the income of a member who never added a wallet token.

### 🎉 Ratting data

- Bounty ticks and ESS payouts are imported from both wallet journals, for the corporations picked in Settings. The corporation journal names the ratter on every tick it taxes, so members with no wallet token are seen too.
- A tick seen in both journals is paired: the character journal gives what the character received, the corporation journal the corporation's cut.
- A tick only the corporation journal has gets its income worked out exactly from the cut, at the corporation's tax rate for that day. The rate for each day is read back from that day's paired ticks, falling back to the nearest day that had some and then to the rate SeAT holds. ESS payouts are paired and worked out the same way.
- **ISK ratted** is what the rats paid before tax: what the pilots received plus the corporation's cut. EVE takes no corporation tax on a payout under 100,000 ISK, or at a 0% corporation, so those count as they were received.
- Each tick's kill list is kept and counted once per fleet payout, however many fleet members shared it.
- NPCs are placed by faction, size and rank from SeAT's SDE, by group name, or by mass where a group says nothing of size. Officers and commanders come from their SDE groups and clone soldiers from their names; a rat with Commander or Officer only in its own name is a regular rat. Dreadnoughts, carriers, supercarriers and titans are capitals, and a hauler with Carrier in its name is not. The overseers of Angel Cartel DED complexes, whose names and groups say nothing of whose they are, are placed one by one.
- **Special spawns**: officers, commanders, clone soldiers and capital rats are listed as they are killed, once per spawn, along with any NPC types an admin adds to watch.
- Ticks are grouped into sessions, and time is counted from the 20 minutes each tick stands for, once per player however many alts were ratting.
- A character's ticks count for the corporation they were in at the time. A taxed row in a character's own journal names the corporation that took the tax, which settles it however far behind SeAT's corporation history is, and a tick after someone has left is never credited to the corporation they left.
- A row that reaches SeAT late, days after it paid, is picked up by the next half-hourly import, which reaches back for it and rebuilds that day. The nightly 31-day read goes over the same ground as a backstop.
- A wallet token that comes back brings up to 30 days of journal with it, and the next import reaches back for those days and pairs them, so nothing is lost. While a director's token is gone, each taxed bounty row in a member's own journal gives the corporation's cut, to the ISK.
- Names and corporations for ratters SeAT does not know come from the journal rows first, then a background lookup: ESI, with EVEWho and zKillboard behind it, the same lookup Mining Manager uses. Pages never wait on it; a character nobody has named yet shows as *Character info in progress*, and the page refreshes itself once the lookup has them.

### 👤 My Ratting

- Every member's own page: income, ISK per hour, hours, rats, active days, rank by hours and the corporation tax their ratting paid, across all their characters or one of them. Charts of income and tax by month, income by day, systems, factions and sizes, a weekday by hour map in EVE time, each session's bounty ISK per hour against the median, income by character, recent sessions, special spawns and personal records over the last 12 months (best tick, session, day and month, longest session). Every chart says what it shows in a line under it.
- **Insights**: favourite and best-paying system, most active and best-paying day of the week, most active and best-paying three hours of the day, ratting streaks, typical and longest session, rats per hour, favourite target and the ESS share of income. Hours by day of the week and by hour of the day, ISK per hour by system, every system ratted with its hours, share, income, ISK per hour, rats and days, and the NPCs killed most.
- **Month by character**: what each character earned in a month (bounties, ESS, corporation tax, hours, ISK per hour, ticks and rats) with the month's total across all of them, any of the last 12 months a pick away. Below it, each character's income month by month.

### 🏢 Directors

- **Corporation tab**, a second tab on the Dashboard, in sections for an overview, players, when, where, kills and month by month: ISK ratted, totals and the corporation's cut, the median ISK per hour, coverage, the leaderboard, income by month and day, who earns the income, when the corporation rats, active ratters per month, ratters coming and going (new, back after a gap, still ratting, stopped), efficiency by system, what the corporation kills, how many systems, constellations and regions the period covers, untracked ratters, players who have gone quiet, and special spawns.
- **Insights** on the Corporation tab: the same as on My Ratting for the whole corporation, plus the most players ratting at once, players per active day and income per player, and for each system how many players ratted there and who ratted there most.
- **Month by month** on the Corporation tab, for the last 12 months: what the players earned and what landed in the corporation's wallet, the tax rate that makes, players, characters, hours, ISK per hour and income per player, with a chart of earned against taxed and line charts of the rest, the month still running dashed.
- **Monthly Report**: one calendar month for the corporation's books, this month or any of the 11 before it. ISK ratted with the change on the month before, the corporation's cut, what the pilots received, how many systems, constellations and regions it covers, the top systems, and everyone who ratted for it that month, biggest first, with a CSV download.
- **Ratter Lookup**: every player who ratted for the corporation picked in the last 90 days, searchable, each opening onto their ratting for it as they see it, with their rank by hours and where each of their characters' figures come from.
- **Wallet tokens lost** on the Corporation tab: who has lost a token, since when, and the last day it can come back and lose nothing.

### 🏆 Leaderboard

- Members see their corporation ranked by hours ratted, with active days and their own row picked out, and nobody's ISK.
- Directors see every column: ISK ratted, income, hours, ISK per hour, ISK per hour against the median player, rats, active days, the change on the period before, and coverage, and can download it as a CSV.

### 🚪 Who has left, and who is not on SeAT

- A player none of whose characters is in the corporations shown any more is tagged as an ex-member, with the day they left, and on hover the time in EVE time and their last tick, on the Monthly Report, the leaderboard, the Corporation tab, Gone quiet and the Ratter Lookup. Their ratting still counts for the corporation they earned it in, so a past month still lists them, and a tick from before they left still counts when it lands after they have gone. A player's page also says what landed in the corporation's wallet from them.
- A player who is still here but has characters that left is tagged with how many, and on hover who and when, wherever the ex-member tag shows. What those characters ratted before they left still counts for the corporation. On My Ratting and a player's page, each of them is tagged with the day it left.
- Gone quiet keeps someone who stopped because they left, or whose characters that ratted all left, tagged until the end of the month they left, since what they ratted that month still counts, then drops them.
- Everyone who rats is watched for leaving, token or no token: SeAT's member tracking and affiliations first, then ESI, EVEWho and zKillboard for whoever SeAT cannot vouch for, with the newest answer winning. EVEWho and zKillboard only overrule SeAT once SeAT's answer has stood for a week.
- `ratting-manager:track-departures` reads the time they left from EVE's corporation history in the background: SeAT's copy, then ESI and EVEWho. With no history to go on, the tag says when they were gone by.
- **Not authed with SeAT**: a badge on a player with no live token, on the same pages and in the CSVs, saying whether they were never added or have lost every token. They still count, from the corporation journal, and are listed under Untracked ratters so directors can ask them for a token.

### 🧭 Pickers and lists

- Every period picker offers this month, last month, the last 30 or 90 days, the last 12 months, any of the 11 calendar months before this one, or a custom range of days within the last year. The change on the period before is left out when that period reaches back further than the imported ratting.
- Every figure, chart and ranking on a page is for the corporation picked on it, so corporations are only added together when someone picks **All corporations**, and no page opens on that. Every corporation picker opens on the viewer's own corporation, the one their main character is in, and only shows when there is more than one to choose from. Links to a player's page or a full list keep the pick.
- Picks apply as they are made. A custom range has a Show button, since it takes two dates.
- Long lists on the dashboards (sessions, systems, special spawns, untracked ratters, players gone quiet and the Corporation tab's leaderboard) show their first 10 rows, with **View all** opening the whole list on its own page for the same period and corporation, searchable and sortable. Special spawns can be narrowed by NPC, faction, rank, size, player, character and system, sessions by character and system, and untracked ratters by corporation and whether they are on SeAT, with each dropdown offering only what is in the list.

### ⚙️ Settings and Help

- **Settings**, for admins: the corporations to track, picked by searching SeAT's corporations and confirming after a look at each one's ticker, alliance, tax rate, members, characters on SeAT and journal; the session gap, the Gone quiet lists, the members' leaderboard size, Keep raw ticks, who can open the pages and extra NPC types to watch; Webhooks; Notifications; and the Routing Map.
- **Help & Documentation**, in the menu for every Ratting Manager permission, with the version installed against the latest release and a search across every section.
- **Plugins & Ecosystem** in Help: every plugin Ratting Manager works with, crucial and optional, and whether this server has it. Key Features gives each feature that needs another plugin a box of its own, marked red while a crucial plugin is missing and yellow while an optional one is. Ratting Manager does not connect to Manager Core yet, so its box says Coming soon; a later release brings it into the ecosystem.

### 🔔 Notifications & routing

- Posts to Discord webhooks, and to Slack incoming webhooks as plain text. A webhook is for every tracked corporation or for one. One for a corporation gets that corporation's posts, and the ones about no corporation in particular, such as the import stopping. Nothing is sent until an admin adds a webhook, and nothing is bound on install.
- Each notification has a switch and a default role, and goes to the webhooks ticked for it, each with a role of its own if wanted. The webhook's own role comes first, then the default; *No ping* mentions nobody. Roles can be picked by name from SeAT Broadcast or SeAT Connector when either is installed.
- What a webhook carries is ticked when adding it, and its edit button changes any of it later. Its Test button sends it a test post.
- **Special spawn**: an NPC of the chosen rank or above (clone soldier, commander, officer), a capital rat or an NPC on the watch list, one post per spawn however many fleet members shared it.
- **Ratting ship lost**: a tracked pilot lost a ship in the system they had a tick in shortly before, with a zKillboard link. Tractor units and other deployables are not ships and are not posted. The final blow is named from SeAT, or looked up from ESI, EVEWho or zKillboard when SeAT does not know the pilot, as most hunters are.
- **Capsule lost**: the same for a capsule, with the ship lost in that system in the half hour before it, so pods can go to a channel of their own or to none.
- **Outsiders in home space**: ESI's hourly NPC kill count for each system of the home space, less what the tracked corporations killed there, once the journals for that hour are in. The home space is the regions, constellations and systems listed on the alert. With none listed it sends nothing even when switched on, and Settings, the Routing Map and Diagnostics say so.
- **Data stalled**: a corporation's wallet journal has stopped coming in, with the last day a token can come back and lose nothing, or the import has stopped running, and again when it is back.
- **Wallet token lost**: a ratter's token is lost or revoked, or their journal has stopped coming in, with the last day it can come back and lose nothing, and again a week before that day.
- **Untracked ratter** and **Ratter gone quiet**: one post per corporation listing the characters or players new to the list. Nobody who has left is posted, and a character that left while the player stayed is named on the player's line. Someone who comes back is posted as usual.
- **Director Report**, weekly on Monday and monthly on the 1st: what the players earned, what landed in the corporation's wallet and the tax rate, the change on the period before, players, hours, median ISK per hour, rats and top systems, with players ranked by income (tax paid beside it), hours, ISK per hour and corporation tax paid.
- **Rankings**, on the same days: players ranked by hours only, with no ISK, for members' channels.
- Players are ranked as a main with all their alts. A report goes once per period; one that could not be sent, or had no webhook yet, goes on the next day's run.
- Each alert remembers what it has sent and never sends the same thing twice; a post every bound webhook refused is tried again on the next run.
- The **Routing Map** shows where every notification goes and who it mentions, as it will be sent.

### 🌐 ESI

- Pages never call anything. The plugin reads what SeAT already pulls: wallet journals, killmails, affiliations, corporation histories and member tracking.
- It calls ESI itself for four things, all public and with no token: where a ratter SeAT's member tracking does not list is now, once a day and one request per 1000 characters; a name nobody has on file; a corporation history SeAT's copy cannot answer, once per move; and the hourly kill counts, only while the Outsiders alert has a home space.
- It stops calling ESI while the error budget it shares with SeAT is low, and EVEWho and zKillboard answer instead.

### 🗃️ What is kept

- **Keep raw ticks**, under Settings: ticks, kill rows, ESS payouts and daily kill totals are deleted nightly once they are 60 days old and the summaries hold their days. 45 days is the least, and 0 keeps them for the 365 days the pages can show. Pages and reports keep every figure, charts of what was killed reading the character days' kill lists, but those days can no longer be rebuilt, traced or imported again the usual way. `ratting-manager:drop-old-ticks --dry-run` says what would go, and `ratting-manager:restore-ticks` brings back up to a year from SeAT's journals, showing what would change and asking first.
- No page shows more than a year, so nothing is kept for longer: daily summaries, character days, sessions, special spawns, tax rates and departures go after 365 days, whatever Keep raw ticks says. Monthly summaries stay 25 months, so the last 12 can be compared with the 12 before them.
- Alert records that can no longer change what is sent are cleared after 90 days, and the webhook delivery log and ESI's hourly kill counts after 30.

### ⚡ Speed

- Every figure is read from the plugin's own tables, indexed for the way the pages and the import read them. SeAT's tables are read straight at the rows needed: the import starts at its window's first journal row and finds what is new since the last run by row number, so a server with years of journal costs no more than a new one.
- Charts of income, systems and kills come from day by day totals, and whole months within a period from month by month totals. Time and player counts read one compact row per character per day, holding each tick's second, system, ISK, rats and kill list, so alts and fleets are counted once when a page is read and an alt linked later corrects its player's time.
- Each import rebuilds the sessions and summaries of only the characters and days it wrote, and a run that brings in nothing keeps the cached figures.
- Figures are cached until the next import changes them, so a page shows the same figures between imports however often it is opened. The ones the Corporation tab, the Leaderboard and the Ratter Lookup open with are worked out straight after each import, and charts are drawn as they come into view.

### 🩺 Diagnostic dashboard

- For SeAT's server admins only, at `/ratting-manager/diagnostic`, with an **Open Diagnostics** button in Settings and its own section in Help. The plugin's Admin permission is not enough. Six tabs, each with an intro saying what it is for and when to use it: **Health Checks**, **Master Test** (the data from the journals to the figures, including how close worked-out income comes to what characters really received), **Settings Health**, **Data Integrity** (with how much each table takes up), **Tick Trace**, which follows one journal row through everything the plugin does with it, and **Notification Testing**, which sends a sample of any notification to any webhook and shows each webhook's delivery health.
- Nothing on it changes the plugin's data; the only thing it sends is the sample posts. NPCs the plugin could not place are listed, leaving out the Crimson Harvest event's rats and the ones the game marks with a diamond.

### 🛡️ Security + correctness

- Directors read the tracked corporations their characters are in now, and admins all of them, checked on the server for every page and download, so a link cannot reach another corporation's figures. **Close the pages to people who have left**, off by default, can also close the member and director pages to anyone with no character in a tracked corporation now.
- SeAT's own tables are only ever read, never written.
- Webhook URLs are stored encrypted with SeAT's `APP_KEY` and never sent back to the page; left empty when editing, a URL stays as it is. If the key changes, Settings and Diagnostics ask for it again and nothing is sent until it has it.
- Only the role or user named in a role field is ever pinged; anything else typed there is ignored.
- CSVs are UTF-8 with a byte order mark and ISK as plain numbers, and text a spreadsheet would run as a formula is kept as text.
- The commands that import, rebuild or drop ratting data share one lock, and the alerts, reports, lookups and departures each have their own. A run that finds its lock taken stops instead of working on the same rows.
- A journal row the database will not take is left out and logged, and the rows around it still come in. A run that stops part way leaves a note, so the next one to finish builds what it left. Diagnostics says when either has happened.

### 🗄️ Schema

All tables carry the `ratting_manager_` prefix and are created by the bundled migrations, which run when SeAT's containers start.

| Group | Tables |
|---|---|
| Ratting data | `ticks`, `tick_kills`, `ess_payouts` |
| Summaries | `sessions`, `daily_summaries`, `daily_kills`, `character_days`, `monthly_summaries` |
| NPCs and spawns | `npc_types`, `special_spawns` |
| Corporations and characters | `corp_tax_rates`, `character_affiliations`, `departures` |
| Notifications | `webhooks`, `notification_types`, `type_webhook`, `webhook_deliveries`, `alert_state`, `system_kills` |
| Configuration | `settings` |

### ⏱️ Scheduled jobs + commands

Added by the schedule seeder. Changes made to them in SeAT are kept on update. Times are EVE time.

| Command | When | Purpose |
|---|---|---|
| `ratting-manager:process` | :10 and :40 | Reads the last two days of both journals, and further back when SeAT has just added older rows, pairs them, works out corporation-only rows and builds what changed |
| `ratting-manager:process --days=31` | Daily 04:25 | The same over the last 31 days, as a backstop |
| `ratting-manager:resolve-characters --requested` | Every minute | Looks up the characters pages asked for |
| `ratting-manager:resolve-characters` | Every 10 minutes | Looks up every ratter of the last year SeAT's member tracking does not list, once a day |
| `ratting-manager:track-departures` | Hourly :37 | Finds when ratters who have moved left, once per move |
| `ratting-manager:notify` | :15 and :45 | Checks every alert, five minutes after the import, and sends what is new |
| `ratting-manager:poll-system-kills` | Hourly :07 | Keeps ESI's hourly NPC kill counts for the home space |
| `ratting-manager:send-reports` | Daily 11:20 | Sends the Director Reports and Rankings that are due |
| `ratting-manager:drop-old-ticks` | Daily 04:55 | Keeps every table to its time |

Plus these, run by hand:

- **`ratting-manager:initialize`**: the first import, of the last 365 days SeAT holds, since no page shows more, with a progress bar for each step. Without it the schedule brings in the last 31 days within a day. Once raw ticks have been dropped, it starts at the oldest day still kept, and before that day fills only the days a corporation tracked late, or tracked again after a break, has no figures for, from what SeAT still holds, for that corporation alone (`--corporation=` for one). `restore-ticks` brings the dropped ticks back for every corporation.
- **`ratting-manager:restore-ticks --from=YYYY-MM-DD`**: imports dropped days again from SeAT's journals, up to a year back. It shows what would change first, asks for a typed confirmation, and stops without changing anything if a day would lose ticks or income.
- **`ratting-manager:trace-tick {journal_id}`**: what the plugin did with one journal row, the same as the Tick Trace tab.
- **`ratting-manager:update-daily-summaries`** and **`ratting-manager:build-sessions`**: rebuild what a Data Integrity warning names.
- **`ratting-manager:import-corp-journal`**, **`ratting-manager:import-character-journal`**, **`ratting-manager:update-tax-rates`**, **`ratting-manager:snapshot-tax-rates`** and **`ratting-manager:sync-npc-types`**: the steps of `process` on their own.

### 🔧 Install

Needs SeAT 5.x on PHP 8.1 or later.

**SeAT Docker**: add `mattfalahe/ratting-manager` to `SEAT_PLUGINS` in the `.env` beside your `docker-compose.yml`, then restart the stack. The containers install the plugin, run its migrations and add its schedules as they start.

```bash
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml down
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d
```

**Without Docker**:

```bash
composer require mattfalahe/ratting-manager
php artisan vendor:publish --tag=seat --force
php artisan migrate
php artisan db:seed --class=RattingManager\\Database\\Seeders\\ScheduleSeeder
php artisan view:clear
```

After install:

1. **Ratting Manager > Settings**: add the corporations to track.
2. Run the first import: `docker exec -it seat-docker-front-1 php artisan ratting-manager:initialize`.
3. Give out the permissions under SeAT's **Access Management**: `ratting-manager.member` for everyone who rats, `ratting-manager.director` for the people who should see everyone's ISK, and `ratting-manager.admin` for whoever runs the plugin. Each includes the ones below it, and `ratting-manager.view` opens Help alone.
4. **Settings > Webhooks** and **Notifications** (optional): add a webhook and tick what it carries.

> Reminder: the plugin reads what SeAT already pulls, so SeAT needs a director token with `esi-wallet.read_corporation_wallets.v1`, on a character with the Accountant or Junior Accountant role, for each tracked corporation. Members' own wallet tokens give exact figures, and member tracking (`esi-corporations.track_members.v1`) means the plugin asks ESI about none of the members it lists.

### 📊 Honest limitations

- **Small or untaxed ticks need the member's own token.** EVE takes no corporation tax on a payout under 100,000 ISK, or at a 0% corporation, so the corporation journal has no row for it, and a member with no wallet token is not seen for those ticks.
- **ESI serves 30 days of journal.** A token that comes back later than that cannot bring older days back from anywhere. Ticks the corporation taxed stay worked out, which is the same sum at that day's rate; ticks only the lost journal had stay unseen.
- **Ratting ship lost and Capsule lost need the killmail in SeAT.** A loss SeAT holds no killmail for is not posted.
- **Outsiders in home space is a count, not a name.** ESI says how many NPCs died in a system in an hour, not who killed them, so the alert posts the difference from what the tracked corporations killed, a few hours after the hour.
- **One year on the pages.** No page shows more than the last 12 months, and nothing but the monthly totals is kept for longer.
- **Dropped raw rows stay dropped.** Days older than Keep raw ticks keep every figure, but come back only with `ratting-manager:restore-ticks`, for the last 365 days, or from a database backup.
- **Public sources can be days behind.** For a ratter SeAT holds no token for and member tracking does not list, when they left is only as good as ESI, EVEWho and zKillboard say.
- **Not real time.** A tick reaches the pages one to two hours after it pays out: ESI caches the wallet journal for an hour, SeAT pulls it about hourly, and the import runs every half hour.

### 🧪 Tests

- Unit tests for the sums and rules, and checks on the source itself: Blade directives closed the way Blade reads them, language keys that exist, scripts and stylesheets the pages link that ship with the plugin, migrations named as they should be, and nothing writing to SeAT's tables. They need no SeAT and no database, and run on every push on PHP 8.1 to 8.4.

### 🙏 Credits

Thanks to Bahu90, whose fork of a very early dev version gave me some insights, and to Claw [UK Corp], who tested the dev version, helped me improve the plugin and gave some valid insights.
