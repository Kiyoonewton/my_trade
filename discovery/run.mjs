import puppeteer from 'puppeteer-core';
import {spawn} from 'node:child_process';
import {writeFile, mkdir} from 'node:fs/promises';
import {discover, defaults} from './discover.mjs';
import {collectBatch, seasonId} from './seasons.mjs';
import {promptForArchive} from './configuration.mjs';
import {seasonStream} from './season-stream.mjs';
import {runtime} from './runtime.mjs';
import {join} from 'node:path';

const config = runtime(process.env.MY_TRADE_LOCAL === '1');

const count = Number(process.argv[2] ?? 85);
if (!Number.isInteger(count) || count < 1 || count > 1000) {
  console.error('Pass a positive maximum season count (default 85).');
  process.exit(1);
}
let endpoint, manifest, manifestPath;
try {
  endpoint = await promptForArchive();
  const currentId = seasonId(endpoint);
  await mkdir(config.rawDirectory, {recursive:true});
  manifestPath = join(config.rawDirectory, `seasons-${Date.now()}.json`);
  manifest = {archive_url:endpoint, discovered_at:new Date().toISOString(),
    order:'current-first-then-top-to-bottom', season_ids:[], processed:[]};
  console.log(`Current season ${currentId}: starting API collection immediately.`);
  const ids = seasonStream(currentId, async (add, signal) => {
    let browser;
    const close = () => { if (browser) void browser.close().catch(() => {}); };
    signal.addEventListener('abort', close, {once:true});
    try {
      browser = await puppeteer.launch(config.launchOptions);
      if (signal.aborted) return;
      const page = await browser.newPage();
      const selectors = Object.fromEntries(Object.entries(defaults).map(([key, value]) =>
        [key, process.env[`ARCHIVE_${key.toUpperCase()}_SELECTOR`] ?? value]));
      console.log('Puppeteer: discovering additional seasons in the background.');
      await discover(page, {endpoint, count, selectors, onSeason:add, signal});
    } finally {
      signal.removeEventListener('abort', close);
      if (browser) await browser.close();
    }
  }, {limit:count, onQueued:id => manifest.season_ids.push(id)});
  const result = await collectBatch(ids, async id => {
    const status = await new Promise((resolve, reject) => {
      const job = config.collection(id);
      const child = spawn(job.command, job.args, {stdio:'inherit', cwd:job.cwd});
      child.once('error',reject);
      child.once('exit',code=>resolve(({0:'complete',2:'network',3:'incomplete'})[code] ?? 'fatal'));
    });
    manifest.processed.push({season_id:id,status});
    await writeFile(manifestPath, JSON.stringify(manifest, null, 2));
    return {status};
  });
  manifest.result = result;
  if (result.incomplete || result.networkFailures) process.exitCode = 2;
} catch (error) {
  console.error(error.message);
  if (manifest) manifest.error = error.message;
  process.exitCode = 1;
} finally {
  if (manifest && manifestPath) {
    try { await writeFile(manifestPath, JSON.stringify(manifest, null, 2)); console.log(`Manifest: ${manifestPath}`); }
    catch (error) { console.error(`Cannot save manifest: ${error.message}`); process.exitCode = 1; }
  }
}
