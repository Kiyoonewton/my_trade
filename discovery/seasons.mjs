export function seasonId(url) {
  const decoded = decodeURIComponent(url);
  const match = decoded.match(/(?:season[:/=]|\/)(\d{5,})(?=\/|[?#]|$)/i);
  return match?.[1] ?? null;
}

// The archive is displayed newest first. Pick the newest N unique entries, then reverse.
export function oldestFirst(entries, count) {
  const unique = [...new Set(entries.filter(id => /^\d{5,}$/.test(id)))];
  if (unique.length < count) throw new Error(`Only ${unique.length} unique seasons discovered; requested ${count}. No collection started.`);
  return unique.slice(0, count).reverse();
}

export async function collectBatch(ids, collect, log = console.log) {
  let complete = 0, incomplete = 0;
  for (const [index, id] of ids.entries()) {
    log(`[${index + 1}/${ids.length}] Season ${id}`);
    try {
      if (await collect(id)) complete++;
      else { incomplete++; log(`INCOMPLETE ${id}: moving to next season.`); }
    } catch (error) {
      incomplete++; log(`INCOMPLETE ${id}: ${error.message}; moving to next season.`);
    }
  }
  log(`Finished: ${complete} complete/skipped; ${incomplete} incomplete.`);
  return {complete, incomplete};
}
