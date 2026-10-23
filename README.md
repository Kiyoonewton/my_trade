# my_trade

A CLI data pipeline: collect virtual-football 1X2 odds/results into MongoDB and export matches as CSV or JSON. Strategy analysis, betting execution, GraphQL and unused web/database services have been removed; Puppeteer handles season discovery. Laravel and the existing `win_or_draws` collection are retained.

## Setup

All runtime configuration lives in `engine/`:

```sh
cp engine/.env.example engine/.env
docker compose -f engine/compose.yaml build
docker compose -f engine/compose.yaml up -d mongodb
```

No database ports are published. The default named volume starts a **new empty database**. Existing `db/mongodb` data is not deleted or automatically imported. Back up the old database with `mongodump` before switching, then restore with `mongorestore`, or set `DB_DSN` to a reachable existing MongoDB server. Never run `down -v` unless you intend to remove the new volume.

## Visible Chrome on your Mac

Use this mode to watch discovery in local Chrome while the existing API collector and MongoDB stay in Docker:

```sh
docker compose -f engine/compose.yaml build app
docker compose -f engine/compose.yaml up -d --wait mongodb
cd discovery
npm ci
npm run collect -- 85
```

Enter the current season ID or full archive URL at the prompt. Puppeteer opens visible Chrome, scans archive rows and clicks Show more to discover up to 85 unique season IDs. It saves IDs from visible rows before loading more, and scans the requested archive window even across completed seasons. Only new or unfinished seasons enter the API queue, so a later run may collect only 3 or 10 new seasons. Do not navigate or close the managed window during discovery. **Discovery finishes and saves the plan before API collection starts.** The browser then closes; API collection continues independently, oldest to newest according to archive order.

This Mac mode uses the standard Google Chrome application path; set `CHROMIUM_PATH` if Chrome is installed elsewhere. If discovery fails or the server denies access, saved progress remains available. Resume saved pending seasons without opening Chrome or entering a current season:

```sh
npm run collect -- --resume
```

The host Node runner invokes `docker compose run --rm -T app matches:collect ...` for each pending season. Do not run separate collectors simultaneously. Keep `raw/` and the MongoDB volume together: the per-competition `raw/collection-progress-*.json` records completed seasons and pending work. Successful seasons are not requested again by the batch runner. Existing `raw/seasons-*.json` manifests are imported automatically, including their completed seasons, network failures and unprocessed IDs. If you intentionally replace/restore the database, move the progress file and old manifests aside to let MongoDB verify seasons again.

## Discover and collect 85 seasons

Run the collector from an interactive terminal:

```sh
docker compose -f engine/compose.yaml build
docker compose -f engine/compose.yaml run --rm collector 85
```

Every run prompts: `Current season ID or archive URL:`. Enter the numeric source ID (for example `3147738`) or the full selected league archive URL. It constructs `https://st-cdn001.akamaized.net/bet9javirtuals/en/1/season/<ID>/archive`. No `SEASON_ENDPOINT` setting is needed; any old value is ignored. Do not use `-T` for the collector command.

The supplied URL opens the archive. Its table determines the season IDs and their order; an older URL can still display newer seasons. Puppeteer scans **newest to oldest**, clicks Show more as needed until the requested number of unique IDs is found or the archive ends. Completed IDs count toward that discovery limit, but are excluded from API collection. `85` is a maximum, not a required count; discovery can return fewer seasons. API collection reverses archive order to run **oldest to newest**, including any previously pending failures. Numeric source IDs are never used to infer chronology. The source ID comes from row navigation, not the displayed league number.

The plan is saved before collection in `raw/seasons-*.json`. The durable progress file is updated atomically after every season attempt; only successful validation against the selected competition format marks a season complete. Interrupted, network-failed and incomplete seasons remain pending. `--resume` uses this saved queue without Chrome. A stalled Show more request reports unfinished discovery instead of silently claiming the archive has ended. Pagination targets the observed Show more container and verifies row growth. A pagination failure preserves the IDs already discovered for `--resume`. Same-URL season selections are supported. Unresolved rows are reported in the manifest and skipped so other seasons can still be discovered; the run reports a warning rather than claiming full discovery.

Docker-only resume:

```sh
docker compose -f engine/compose.yaml run --rm collector --resume
```

The league default is **30 matchdays × 8 matches = 240 unique settled matches per season**. There is no assumption about how many seasons occur in a calendar day.

- Already complete in MongoDB: print `SKIP` and do not fetch it again.
- Incomplete: retain valid matchdays and fetch missing/invalid matchdays.
- API missing, invalid or unsettled: print `INCOMPLETE`, keep stored progress and move on to the next season.
- Exact duplicate fixture rows: collapse to one row and enforce a unique fixture key on subsequent writes.
- A shared collection lock serializes database repairs; unique fixture keys also prevent duplicate inserts.
- Stop after three consecutive uncrawlable/incomplete seasons. A complete/skipped season resets this streak.
- Network failures (connection errors, HTTP 408/429/5xx) receive the API client retries, then up to two additional season attempts after 2s and 5s. Already saved valid matchdays are reused. Exhausted failures remain pending, print `NETWORK`, then continue without increasing or resetting the uncrawlable streak. They are not treated as missing historical data.
- Database/configuration errors stop immediately instead of being misclassified as unavailable seasons.
- At the end: print complete/skipped, incomplete and network totals. Batch exit code 2 means incomplete/network results; setup failures use code 1. The internal single-season command uses exit 0 for complete, 2 for network failure, 3 for incomplete data and 1 for local/configuration failure.

The unique identity is competition + season + matchday + home + away. Legacy untagged rows are adopted only into the English `virtual-football` competition; German and Euro collection never adopt them. Back up your database before the first run: only exact duplicate fixture identities are removed, retaining the newest row. Unexpected extra fixtures are preserved and reported as incomplete rather than deleted. A season is complete only after all 30 matchdays pass validation, not just because its raw count happens to be 240.

To retry one known season:

```sh
docker compose -f engine/compose.yaml run --rm app matches:collect 3147603
```

Optional `--matchdays` and `--matches-per-matchday` support other league formats. The misleading `--matches-per-day` option was removed. Collection archives responses in `raw/`, and uses the verified feed outcome mapping 1/home, 2/draw, 3/away. Missing settlement flags never imply an away win. Historical odds collected after settlement do not establish prices available before kickoff. Missing scores/times remain null.

## Export from the database

```sh
docker compose -f engine/compose.yaml run --rm app matches:export /exports/matches.csv
docker compose -f engine/compose.yaml run --rm app matches:export /exports/season.json --format=json --season=SEASON_ID
```

Optional `--competition=virtual-bundesliga` or `--competition=virtual-euro` isolates that competition. `--competition=virtual-football` also includes legacy untagged English records. Without a filter all competitions are exported, ordered by competition and season. Legacy rows are labelled `virtual-football` in the exported file; this does not rewrite MongoDB. Files appear in the root `exports/` folder. Existing destinations are never overwritten. JSON is an array; CSV always includes a header, including when there are no matches. Exports stream through a database cursor and publish a completed temporary file atomically.

Columns: `source, competition_id, season_id, season_sequence, match_id, provider_match_id, provider_tournament_id, collected_at, matchday, kickoff, home_team_id, home_team, away_team_id, away_team, odds_captured_at, home_odds, draw_odds, away_odds, home_score, away_score, status, result`.

Results use H/D/A. Legacy 1/x/2 results and singular `_odd` fields are converted during export. Export match IDs use the MongoDB record ID, because the legacy `match_id` can represent a team pairing rather than a unique fixture. Legacy season order is not inferred from source IDs: supply sequence metadata before chronological strategy testing.

## Checks

```sh
docker compose -f engine/compose.yaml run --rm --entrypoint php app tests/data_pipeline.php
docker compose -f engine/compose.yaml run --rm --entrypoint php app tests/season_collection.php
docker compose -f engine/compose.yaml run --rm -T --no-deps --entrypoint node collector --test /app/discovery/tests/*.test.mjs
```

The root `.env` was removed from tracking. If it held real credentials, rotate them; removing the current file does not erase Git history. Historical collections are preserved; there are no destructive migrations.


## English, Bundesliga and Euro collection

Use one MongoDB collection with separate `competition_id` values. The existing
English value remains `virtual-football`; no reset, migration or reimport is needed.
Run collectors sequentially because repairs share a database lock.

The provider samples supplied for Bundesliga season `3148613` and Euro season
`3148615` returned eight fixtures on days 1 and 30 and none on day 31. All three
configured formats are currently 30 × 8 = 240. These are virtual competitions,
not the real-world Bundesliga/European tournament schedules. Profiles live in
`trade_crawler/config/competitions.json` and are shared by Node and PHP.

| Competition ID | Feed tournament ID |
| --- | --- |
| virtual-football | vf:tournament:31867 |
| virtual-bundesliga | vf:tournament:34616 |
| virtual-euro | vf:tournament:14149 |

After updating the code, rebuild the images once:

```sh
docker compose -f engine/compose.yaml build
cd discovery
npm ci
npm run collect -- 85 --competition=virtual-football
npm run collect -- 85 --competition=virtual-bundesliga
npm run collect -- 85 --competition=virtual-euro
```

Each command prompts for that competition's current archive URL or season ID.
The supplied German and Euro examples were:

- `https://st-cdn001.akamaized.net/bet9javirtuals/en/1/season/3148613/archive`
- `https://st-cdn001.akamaized.net/bet9javirtuals/en/1/season/3148615/archive`

URLs have the same path structure, so they do not identify the league by themselves.
Select the correct competition explicitly. Every fetched event must match its
configured tournament ID; a mismatch stops collection before API rows are written.
Normal completion/deduplication checks still happen without refetching stored data.
Discovery retains the existing archive UI selectors; each league's live browser
pagination should be verified when first collecting it.

Resume only a particular league's saved work:

```sh
npm run collect -- --resume --competition=virtual-bundesliga
```

Docker-only equivalent (from repository root):

```sh
docker compose -f engine/compose.yaml run --rm collector 85 --competition=virtual-euro
```

Progress is stored in `raw/collection-progress-<competition>.json`; new manifests
and raw response filenames also include the competition. Old English manifests
remain readable and are not imported into the other leagues. `COMPETITION_ID`
continues to work as an environment default; the CLI option takes precedence.

Exports from the repository root (choose unused output filenames):

```sh
docker compose -f engine/compose.yaml run --rm app matches:export /exports/english.json --format=json --competition=virtual-football
docker compose -f engine/compose.yaml run --rm app matches:export /exports/bundesliga.json --format=json --competition=virtual-bundesliga
docker compose -f engine/compose.yaml run --rm app matches:export /exports/euro.json --format=json --competition=virtual-euro
docker compose -f engine/compose.yaml run --rm app matches:export /exports/all-leagues.json --format=json
```

Use separate league exports with existing single-league analysis scripts. Those
locally created scripts are not tracked in this repository and have not been
rewritten: do not feed an all-league file into them until they partition by
`competition_id` before computing standings or historical features.

New exports retain `collected_at` and provider match/tournament IDs. Existing
collection already writes `collected_at` on new records. This is fetch time,
not kickoff; the response-level `doc[].timestamp` is not treated as event time.
Old records without timestamps stay unknown. Stored raw JSON responses remain
available for future backfilling if genuine event-time metadata is identified.
Custom PHP competition IDs require explicit `--matchdays` and
`--matches-per-matchday` rather than silently inheriting the English format.


### Older seasons with empty odds

An archive row can remain available after its feed no longer supplies 1X2
outcomes. These records cannot be imported with fabricated odds. If collecting
oldest first stops at three unavailable seasons before reaching newer history,
resume the same saved league queue newest first:

```sh
cd discovery
npm run collect -- --resume --competition=virtual-euro --newest-first
```

`--newest-first` also works with discovery and Docker collection. It reverses the
pending queue using saved archive order, never numeric IDs, and leaves checkpoints
and existing matches intact. Completed seasons remain excluded. The existing
three-consecutive-incomplete stop and network handling stay unchanged. Earlier
failed seasons remain retryable. Without this option collection remains oldest
first. For the reported Euro examples, a live check found empty 1X2 outcome lists
in seasons 3146383, 3146415 and 3146442, while 3148615 and 3148793 supplied odds.
This is a feed-availability observation, not a permanent expiry classification.
