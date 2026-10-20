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

Enter the current season ID or full archive URL at the prompt. The supplied season goes straight to the API. Local Puppeteer opens a visible Chrome window, scans archive rows and clicks Show more to discover additional IDs. Don't navigate or close the managed window while discovery is running. The browser closes when discovery finishes or collection stops early.

This Mac mode uses the standard Google Chrome application path; set `CHROMIUM_PATH` if Chrome is installed elsewhere. It uses a fresh temporary browser profile with no stealth or fingerprint modifications. If the server denies access, collection of the supplied season still runs and discovery reports the restriction. No claim is made that a visible browser will always be permitted.

The host Node runner invokes `docker compose run --rm -T app matches:collect ...` for each queued season. Do not run a separate collector simultaneously. The 85-season maximum, unique IDs, 240-match repair/skip checks and three-uncrawlable-season stop rule are shared with Docker mode. Manifests stay in the root `raw/` folder.

## Discover and collect 85 seasons

Run the collector from an interactive terminal:

```sh
docker compose -f engine/compose.yaml build
docker compose -f engine/compose.yaml run --rm collector 85
```

Every run prompts: `Current season ID or archive URL:`. Enter the numeric source ID (for example `3147738`) or the full English League archive URL. It constructs `https://st-cdn001.akamaized.net/bet9javirtuals/en/1/season/<ID>/archive`. No `SEASON_ENDPOINT` setting is needed; any old value is ignored. Do not use `-T` for the collector command.

The source season ID is extracted from your input and sent to the API immediately. In parallel, Puppeteer opens the archive directly, scans **top to bottom**, clicks `Show more` as needed, and removes repeated source IDs. `85` is a **maximum**, not a required count: when the archive ends, it processes however many unique seasons were found. Collection processes the supplied current season first, then additional seasons in top-to-bottom order as Puppeteer queues them; duplicate IDs (including the current season) are skipped. The maximum count includes the current season. The source season ID comes from row navigation, not the displayed league number.

The exact archive URL, queued IDs and processed statuses are saved progressively in `raw/seasons-*.json`, including when discovery fails after the current season was processed. The 240-match database skip, repair and deduplication rules are unchanged. Optional `ARCHIVE_*_SELECTOR` overrides remain available, but the defaults target the observed direct archive table and buttons.

The league default is **30 matchdays × 8 matches = 240 unique settled matches per season**. There is no assumption about how many seasons occur in a calendar day.

- Already complete in MongoDB: print `SKIP` and do not fetch it again.
- Incomplete: retain valid matchdays and fetch missing/invalid matchdays.
- API missing, invalid or unsettled: print `INCOMPLETE`, keep stored progress and move on to the next season.
- Exact duplicate fixture rows: collapse to one row and enforce a unique fixture key on subsequent writes.
- A shared collection lock serializes database repairs; unique fixture keys also prevent duplicate inserts.
- Stop after three consecutive uncrawlable/incomplete seasons. A complete/skipped season resets this streak.
- Network failures (connection errors, HTTP 408/429/5xx) retry three times, print `NETWORK`, then continue without increasing or resetting the uncrawlable streak. They are not treated as missing historical data.
- Database/configuration errors stop immediately instead of being misclassified as unavailable seasons.
- At the end: print complete/skipped, incomplete and network totals. Batch exit code 2 means incomplete/network results; setup failures use code 1. The internal single-season command uses exit 0 for complete, 2 for network failure, 3 for incomplete data and 1 for local/configuration failure.

The unique identity is competition + season + matchday + home + away. Legacy untagged rows for the requested season are adopted into the configured competition. Back up your database before the first run: only exact duplicate fixture identities are removed, retaining the newest row. Unexpected extra fixtures are preserved and reported as incomplete rather than deleted. A season is complete only after all 30 matchdays pass validation, not just because its raw count happens to be 240.

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

Optional `--competition=vfl` filters new tagged records. Legacy records without that field are included when no competition filter is specified. Files appear in the root `exports/` folder. Existing destinations are never overwritten. JSON is an array; CSV always includes a header, including when there are no matches. Exports stream through a database cursor and publish a completed temporary file atomically.

Columns: `source, competition_id, season_id, season_sequence, match_id, matchday, kickoff, home_team_id, home_team, away_team_id, away_team, odds_captured_at, home_odds, draw_odds, away_odds, home_score, away_score, status, result`.

Results use H/D/A. Legacy 1/x/2 results and singular `_odd` fields are converted during export. Export match IDs use the MongoDB record ID, because the legacy `match_id` can represent a team pairing rather than a unique fixture. Legacy season order is not inferred from source IDs: supply sequence metadata before chronological strategy testing.

## Checks

```sh
docker compose -f engine/compose.yaml run --rm --entrypoint php app tests/data_pipeline.php
docker compose -f engine/compose.yaml run --rm --entrypoint php app tests/season_collection.php
docker compose -f engine/compose.yaml run --rm -T --no-deps --entrypoint node collector --test /app/discovery/tests/*.test.mjs
```

The root `.env` was removed from tracking. If it held real credentials, rotate them; removing the current file does not erase Git history. Historical collections are preserved; there are no destructive migrations.
