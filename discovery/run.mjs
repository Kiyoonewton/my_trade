import puppeteer from 'puppeteer-core';
import {spawn} from 'node:child_process';
import {writeFile, mkdir} from 'node:fs/promises';
import {discover, defaults} from './discover.mjs';
import {collectBatch} from './seasons.mjs';
import {promptForArchive} from './configuration.mjs';

const count = Number(process.argv[2] ?? 85);
if (!Number.isInteger(count) || count < 1 || count > 1000) {
  console.error('Pass a positive maximum season count (default 85).');
  process.exit(1);
}
let browser;
try {
  const endpoint = await promptForArchive();
  console.log(`Opening ${endpoint}`);
  browser = await puppeteer.launch({executablePath:process.env.CHROMIUM_PATH ?? '/usr/bin/chromium',
    args:['--no-sandbox','--disable-dev-shm-usage'], headless:true});
  const page = await browser.newPage();
  const selectors = Object.fromEntries(Object.entries(defaults).map(([key, value]) =>
    [key, process.env[`ARCHIVE_${key.toUpperCase()}_SELECTOR`] ?? value]));
  const ids = await discover(page, {endpoint, count, selectors});
  await browser.close(); browser = null;
  await mkdir('/raw', {recursive:true});
  const manifest = `/raw/seasons-${Date.now()}.json`;
  await writeFile(manifest, JSON.stringify({discovered_at:new Date().toISOString(), archive_url:endpoint, order:'top-to-bottom', season_ids:ids}, null, 2));
  console.log(`Discovered ${ids.length} unique seasons, top to bottom. Manifest: ${manifest}`);
  const result = await collectBatch(ids, id => new Promise((resolve, reject) => {
    const child = spawn('php', ['/app/artisan','matches:collect',id,
      `--competition=${process.env.COMPETITION_ID ?? 'virtual-football'}`], {stdio:'inherit'});
    child.once('error',reject);
    child.once('exit',code=>resolve({status:({0:'complete',2:'network',3:'incomplete'})[code] ?? 'fatal'}));
  }));
  if (result.incomplete || result.networkFailures) process.exitCode = 2;
} catch (error) { console.error(error.message); process.exitCode = 1; }
finally { if (browser) await browser.close(); }
