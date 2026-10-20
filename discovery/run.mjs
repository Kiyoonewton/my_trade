import puppeteer from 'puppeteer-core';
import {spawn} from 'node:child_process';
import {mkdir} from 'node:fs/promises';
import {discover, defaults} from './discover.mjs';
import {collectBatch} from './seasons.mjs';
import {promptForArchive} from './configuration.mjs';
import {runtime} from './runtime.mjs';
import {loadProgress, saveJson, addDiscovered, pendingOldestFirst, retryNetwork} from './progress.mjs';
import {join} from 'node:path';

const config = runtime(process.env.MY_TRADE_LOCAL === '1');
const resume = process.argv.includes('--resume');
const count = Number(process.argv.slice(2).find(arg => arg !== '--resume') ?? 85);
if (!Number.isInteger(count) || count < 1 || count > 1000) {
  console.error('Pass a positive maximum season count (default 85), or --resume.');
  process.exit(1);
}
let manifest, manifestPath;
try {
  await mkdir(config.rawDirectory, {recursive:true});
  const competition = process.env.COMPETITION_ID ?? 'virtual-football';
  const {path, progress} = await loadProgress(config.rawDirectory, competition);
  manifestPath = join(config.rawDirectory, `seasons-${Date.now()}.json`);
  manifest = {competition, discovered_at:new Date().toISOString(), order:'oldest-to-newest',
    discovery_ids:[], season_ids:[], processed:[]};
  if (!resume) {
    const endpoint = await promptForArchive();
    manifest.archive_url = endpoint;
    let browser;
    try {
      browser = await puppeteer.launch(config.launchOptions);
      const page = await browser.newPage();
      const selectors = Object.fromEntries(Object.entries(defaults).map(([key, value]) =>
        [key, process.env[`ARCHIVE_${key.toUpperCase()}_SELECTOR`] ?? value]));
      console.log(`Discovering up to ${count} seasons before API collection...`);
      // The archive lists its own current seasons even when opened from an older URL.
      await discover(page, {endpoint, count, selectors,
        onUnresolved:async row => { (manifest.unresolved_rows ??= []).push(row); },
        onSeason:async id => {
          if (!manifest.discovery_ids.includes(id)) manifest.discovery_ids.push(id);
          manifest.discovery_ids = manifest.discovery_ids.slice(0, count);
          addDiscovered(progress, manifest.discovery_ids);
          await saveJson(path, progress);
          await saveJson(manifestPath, manifest);
        },
      });
      manifest.discovery_complete = !(manifest.unresolved_rows?.length);
    } finally {
      if (browser) await browser.close();
    }
  }
  console.log(`Storage: ${Object.values(progress.seasons).filter(row => row.status === 'complete').length} completed seasons recorded; these will not be collected again.`);
  if (manifest.unresolved_rows?.length) {
    console.warn(`Discovery warning: ${manifest.unresolved_rows.length} rows could not be resolved; see manifest. Collecting saved IDs only.`);
    process.exitCode = 2;
  }
  manifest.season_ids = pendingOldestFirst(progress);
  await saveJson(manifestPath, manifest);
  console.log(`Saved plan: ${manifest.season_ids.length} pending seasons, oldest to newest. Browser is no longer needed.`);
  const result = await collectBatch(manifest.season_ids, async id => {
    const status = await retryNetwork(async () => {
      const job = config.collection(id);
      const status = await new Promise((resolve, reject) => {
        const child = spawn(job.command, job.args, {stdio:'inherit', cwd:job.cwd});
        child.once('error', reject);
        child.once('exit', code => resolve(({0:'complete',2:'network',3:'incomplete'})[code] ?? 'fatal'));
      });
      progress.seasons[id] = {status, updated_at:new Date().toISOString()};
      await saveJson(path, progress);
      return status;
    });
    manifest.processed.push({season_id:id, status});
    await saveJson(manifestPath, manifest);
    return {status};
  });
  manifest.result = result;
  console.log(`Progress: ${path}`);
  if (result.incomplete || result.networkFailures) process.exitCode = 2;
} catch (error) {
  console.error(error.message);
  console.error('Saved pending seasons can be retried without Chrome using: npm run collect -- --resume');
  if (manifest) manifest.error = error.message;
  process.exitCode = 1;
} finally {
  if (manifest && manifestPath) {
    try { await saveJson(manifestPath, manifest); console.log(`Manifest: ${manifestPath}`); }
    catch (error) { console.error(`Cannot save manifest: ${error.message}`); process.exitCode = 1; }
  }
}
