import {readFile, readdir, writeFile, rename} from 'node:fs/promises';
import {join} from 'node:path';
import {topDown} from './seasons.mjs';

export async function saveJson(path, value) {
  await writeFile(`${path}.tmp`, JSON.stringify(value, null, 2));
  await rename(`${path}.tmp`, path);
}

export function addDiscovered(progress, ids) {
  // Archive order is authoritative; numeric IDs are not a timeline.
  const fresh = topDown(ids, ids.length);
  const anchor = fresh.find(id => progress.order.includes(id));
  const before = anchor ? progress.order.slice(0, progress.order.indexOf(anchor)) : [];
  progress.order = [...new Set([...before.filter(id => !fresh.includes(id)), ...fresh, ...progress.order])];
  for (const id of fresh) progress.seasons[id] ??= {status:'pending'};
}

export function pendingOldestFirst(progress) {
  return [...progress.order].reverse().filter(id => progress.seasons[id]?.status !== 'complete');
}

export async function loadProgress(directory, competition) {
  const path = join(directory, `collection-progress-${encodeURIComponent(competition)}.json`);
  let progress;
  try {
    progress = JSON.parse(await readFile(path, 'utf8'));
    if (progress.version !== 1 || progress.competition !== competition || !Array.isArray(progress.order)
        || !progress.seasons || !Array.isArray(progress.imported)) throw new Error('Invalid progress file');
  } catch (error) {
    if (error.code !== 'ENOENT') throw new Error(`Cannot read ${path}: ${error.message}`);
    progress = {version:1, competition, order:[], seasons:{}, imported:[]};
  }
  // Old manifests used the default competition and did not record its name.
  const files = (await readdir(directory)).filter(name => /^seasons-\d+(?:-[A-Za-z0-9_-]+)?\.json$/.test(name)).sort();
  for (const name of files) {
    if (progress.imported.includes(name)) continue;
    const manifest = JSON.parse(await readFile(join(directory, name), 'utf8'));
    if ((manifest.competition ?? 'virtual-football') !== competition) continue;
    addDiscovered(progress, manifest.discovery_ids ?? manifest.season_ids ?? []);
    for (const row of manifest.processed ?? []) {
      const id = row.season_id;
      if (!/^\d{5,}$/.test(id)) continue;
      if (!progress.order.includes(id)) progress.order.push(id);
      if (progress.seasons[id]?.status !== 'complete') progress.seasons[id] = {status:row.status};
    }
    progress.imported.push(name);
  }
  await saveJson(path, progress);
  return {path, progress};
}

export async function retryNetwork(collect, {delays = [2000, 5000], sleep = ms => new Promise(r => setTimeout(r, ms)), log = console.log} = {}) {
  for (let attempt = 0; ; attempt++) {
    const status = await collect();
    if (status !== 'network' || attempt >= delays.length) return status;
    log(`Network failure: retrying saved season in ${delays[attempt] / 1000}s (${attempt + 1}/${delays.length}).`);
    await sleep(delays[attempt]);
  }
}
