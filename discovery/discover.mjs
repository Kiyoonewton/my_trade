import {topDown, seasonId} from './seasons.mjs';

// The supplied URL opens the archive directly; no league tab navigation is needed.
export const defaults = {
  league: '', archive: '', rows: ':is(main, [role="main"]) table tbody tr.cursor-pointer', more: ':is(main, [role="main"]) .col-xs-12.text-center.margin-top-medium > button',
};

async function clickStable(page, handle, timeout) {
  // The archive uses scrolling layouts. Override smooth scrolling before calculating click coordinates.
  await handle.evaluate(el => el.scrollIntoView({behavior:'instant', block:'center', inline:'nearest'}));
  await page.waitForFunction(el => {
    const box = el.getBoundingClientRect();
    const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
    return !!hit && (hit === el || el.contains(hit));
  }, {timeout}, handle);
  await handle.click();
}

export async function discover(page, {endpoint, count, selectors = defaults, timeout = 60000, onSeason = () => {}, shouldStop = () => false, initialIds = [], onUnresolved = () => {}, signal}) {
  const waitForArchive = async () => {
    if (selectors.rows === defaults.rows) {
      await page.waitForFunction(() => {
        const headings = [...document.querySelectorAll(':is(main, [role="main"]) th')].map(el => el.textContent.trim());
        return document.body.innerText.includes('Access Denied')
          || (headings.includes('Season') && headings.includes('Champion'));
      }, {timeout});
      if (await page.evaluate(() => document.body.innerText.includes('Access Denied'))) {
        throw new Error('Archive server returned Access Denied.');
      }
    }
    await page.waitForSelector(selectors.rows, {timeout});
  };
  const openArchive = async () => {
    console.log('Archive: opening page...');
    const response = await page.goto(endpoint, {waitUntil:'domcontentloaded', timeout});
    if (response?.status() === 403) throw new Error('Archive server returned Access Denied (HTTP 403).');
    if (selectors.league) {
      await page.waitForSelector(selectors.league, {timeout}); await page.click(selectors.league);
    }
    if (selectors.archive) {
      await page.waitForSelector(selectors.archive, {timeout}); await page.click(selectors.archive);
    }
    await waitForArchive();
    await page.waitForNetworkIdle({idleTime:150, timeout});
  };
  const rowCount = () => page.$$eval(selectors.rows, rows => rows.length);
  const more = async () => {
    const before = await rowCount();
    const candidates = await page.$$(selectors.more);
    let button;
    for (const candidate of candidates) {
      const suitable = await candidate.evaluate((el, custom) => custom
        || /^(?:show|load)?\s*more$/i.test(el.textContent.trim()), selectors.more !== defaults.more);
      if (suitable && await candidate.boundingBox()) { button = candidate; break; }
    }
    if (!button) return false;
    await page.waitForFunction(el => !el.disabled, {timeout}, button);
    console.log(`Archive: Show more from ${before} rows...`);
    const failures = [];
    const failed = request => failures.push(`${request.failure()?.errorText}: ${request.url()}`);
    const response = response => { if (response.status() >= 400) failures.push(`HTTP ${response.status()}: ${response.url()}`); };
    page.on('requestfailed', failed); page.on('response', response);
    try {
      await clickStable(page, button, timeout);
      await page.waitForFunction((selector, n) => document.querySelectorAll(selector).length > n,
        {timeout}, selectors.rows, before);
      console.log(`Archive: now ${await rowCount()} rows.`);
      return true;
    } catch (error) {
      const label = await button.evaluate(el => el.textContent.trim()).catch(() => 'removed');
      throw new Error(`Show more did not load additional rows (${before} rows, button: ${label}; cause: ${error.message}; URL: ${page.url()}; network: ${failures.slice(-3).join(' | ') || 'no failed requests observed'}). Discovered IDs are saved; use --resume to collect them.`);
    } finally {
      page.off('requestfailed', failed); page.off('response', response);
    }
  };
  await openArchive();
  console.log(`Archive: found ${await rowCount()} season rows.`);
  // Load enough archive rows (including headers/duplicates) and keep loading if unique IDs fall short.
  const ids = topDown(initialIds, count), inspected = new Set();
  let pages = 0;
  while (true) {
    if (signal?.aborted) return topDown(ids, count);
    const rows = await page.$$eval(selectors.rows, elements => elements.map((row, index) => ({
      index, link: row.querySelector('a[href]')?.href ?? '',
      dataId: row.getAttribute('data-season-id') ?? '',
      clickable: !!row.querySelector('td') && (!!row.querySelector('a,td') || !!row.onclick),
    })));
    for (const row of rows) {
      if (signal?.aborted) return topDown(ids, count);
      if (inspected.has(row.index)) continue;
      inspected.add(row.index);
      let id = /^\d{5,}$/.test(row.dataId) ? row.dataId : seasonId(row.link);
      if (!id && row.clickable) {
        const before = page.url();
        const handle = (await page.$$(selectors.rows))[row.index];
        const cell = await handle.$('td');
        // Subscribe before clicking; URL changes alone do not mean the SPA has finished rendering.
        const navigation = page.waitForNavigation({waitUntil:'networkidle2', timeout:Math.min(timeout, 15000)})
          .catch(error => { if (error.name !== 'TimeoutError') throw error; });
        await clickStable(page, cell ?? handle, timeout);
        await navigation;
        if (page.url() !== before) id = seasonId(page.url());
        if (!id) {
          console.warn(`Archive row ${row.index + 1} did not expose a season ID; continuing with other rows.`);
          await onUnresolved(row.index + 1);
        }
        if (page.url() !== before) {
          await page.goBack({waitUntil:'networkidle2', timeout});
          await page.waitForFunction(url => location.href === url, {timeout}, before);
          await waitForArchive();
          await page.waitForNetworkIdle({idleTime:350, timeout});
          while (await rowCount() < rows.length) {
            if (!await more()) throw new Error('Could not restore loaded archive rows.');
          }
        }
      }
      if (id && !ids.includes(id)) {
        if (shouldStop(id)) { console.log(`Archive: reached completed season ${id}; stopping discovery.`); return topDown(ids, count); }
        ids.push(id); await onSeason(id); console.log(`Discovered season ${id} (${ids.length}/${count})`);
      }
      if (new Set(ids).size >= count) return topDown(ids, count);
    }
    if (++pages > 30) throw new Error('Archive pagination exceeded safety limit.');
    if (!await more()) {
      if (!ids.length) throw new Error('No season IDs found in the archive.');
      return topDown(ids, count);
    }
  }
}
