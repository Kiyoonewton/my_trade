export function seasonId(url) {
  const decoded = decodeURIComponent(url);
  const match = decoded.match(/(?:season[:/=]|\/)(\d{5,})(?=\/|[?#]|$)/i);
  return match?.[1] ?? null;
}

// Preserve the visible archive order; fewer than the limit is a valid result.
export function topDown(entries, count) {
  return [...new Set(entries.filter(id => /^\d{5,}$/.test(id)))].slice(0, count);
}

export async function collectBatch(ids, collect, log = console.log) {
  let complete = 0, incomplete = 0, networkFailures = 0, streak = 0, stopped = false;
  for (const [index, id] of ids.entries()) {
    log(`[${index + 1}/${ids.length}] Season ${id}`);
    const result = await collect(id);
    const status = result === true ? 'complete' : result === false ? 'incomplete' : result?.status;
    if (status === 'complete') { complete++; streak = 0; }
    else if (status === 'network') {
      networkFailures++;
      log(`NETWORK ${id}: retries exhausted; not counted as an uncrawlable season.`);
    } else if (status === 'incomplete') {
      incomplete++; streak++;
      log(`INCOMPLETE ${id}: ${streak}/3 consecutive uncrawlable seasons.`);
      if (streak >= 3) { stopped = true; log('Stopping: three consecutive uncrawlable seasons.'); break; }
    } else { throw new Error(`Collector failed for ${id}; fix the database/configuration error before retrying.`); }
  }
  log(`Finished: ${complete} complete/skipped; ${incomplete} incomplete; ${networkFailures} network failures.`);
  return {complete, incomplete, networkFailures, stopped};
}
