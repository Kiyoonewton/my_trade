# my_trade

A CLI data pipeline: collect virtual-football 1X2 odds/results into MongoDB and export matches as CSV or JSON. Strategy analysis, betting execution, GraphQL, browser automation and unused web/database services have been removed. Laravel and the existing `win_or_draws` collection are retained.

## Setup

All runtime configuration lives in `engine/`:

```sh
cp engine/.env.example engine/.env
docker compose -f engine/compose.yaml build
docker compose -f engine/compose.yaml up -d mongodb
```

No database ports are published. The default named volume starts a **new empty database**. Existing `db/mongodb` data is not deleted or automatically imported. Back up the old database with `mongodump` before switching, then restore with `mongorestore`, or set `DB_DSN` to a reachable existing MongoDB server. Never run `down -v` unless you intend to remove the new volume.

## Collect

```sh
docker compose -f engine/compose.yaml run --rm app matches:collect SEASON_ID --competition=vfl --matchdays=38 --matches-per-day=10 --sequence=1
```

Provide an explicit source season ID. For a 16-team league use `--matchdays=30 --matches-per-day=8`. Configure the feed prefix through `MATCH_FEED_URL`. The normalizer preserves the existing feed mapping: outcome IDs 1/home, 2/draw, 3/away. Verify this convention against a real response for your provider before collection.

Each response is archived under `raw/`. Matchday counts, duplicate pairings and odds are validated before writing that day. Reruns update matching season/day/home/away records rather than deleting a matchday. A failure can leave earlier days or a partially written day; rerun the command to resume safely. Run one collector at a time. No automated season discovery is included.

Missing settlement flags yield a null result, never an assumed away win. Historical results are collected with their observed odds; `collected_at` is not proof those odds were available before kickoff. Scores, kickoff and actual pre-match capture times remain null until supplied by a verified source.

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
```

The root `.env` was removed from tracking. If it held real credentials, rotate them; removing the current file does not erase Git history. Historical collections are preserved; there are no destructive migrations.
