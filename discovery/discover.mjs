import {topDown, seasonId} from './seasons.mjs';

// The supplied URL opens the archive directly; no league tab navigation is needed.
export const defaults = {
  league: '', archive: '', rows: ':is(main, [role="main"]) table tbody tr.cursor-pointer', more: ':is(main, [role="main"]) button',
};

export async function discover(page, {endpoint, count, selectors = defaults, timeout = 60000, onSeason = () => {}, signal}) {
  const waitForArchive = async () => {
    if (selectors.rows === defaults.rows) {
      await page.waitForFunction(() => {
        const headings = [...document.querySelectorAll(':is(main, [role="main"]) th')].map(el => el.textContent.trim());
        return headings.includes('Season') && headings.includes('Champion');
      }, {timeout});
    }
    await page.waitForSelector(selectors.rows, {timeout});
  };
  const openArchive = async () => {
    await page.goto(endpoint, {waitUntil:'domcontentloaded', timeout});
    if (selectors.league) {
      await page.waitForSelector(selectors.league, {timeout}); await page.click(selectors.league);
    }
    if (selectors.archive) {
      await page.waitForSelector(selectors.archive, {timeout}); await page.click(selectors.archive);
    }
    await waitForArchive();
  };
  const rowCount = () => page.$$eval(selectors.rows, rows => rows.length);
  const more = async () => {
    const before = await rowCount();
    const button = await page.$(selectors.more);
    if (!button) return false;
    if (await button.evaluate(el => el.disabled)) return false;
    if (!await button.boundingBox()) return false;
    await button.click();
    try { await page.waitForFunction((selector, n) => document.querySelectorAll(selector).length > n,
      {timeout}, selectors.rows, before); return true; }
    catch { return false; }
  };
  await openArchive();
  // Load enough archive rows (including headers/duplicates) and keep loading if unique IDs fall short.
  const ids = [], inspected = new Set();
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
        await page.$$eval(selectors.rows, (elements, index) => elements[index].click(), row.index);
        try {
          await page.waitForFunction(previous => location.href !== previous, {timeout}, before);
          id = seasonId(page.url());
        } catch { /* Non-season rows are ignored. */ }
        if (page.url() !== before) {
          await page.goBack({waitUntil:'domcontentloaded', timeout});
          await waitForArchive();
          while (await rowCount() < rows.length) {
            if (!await more()) throw new Error('Could not restore loaded archive rows.');
          }
        }
      }
      if (id && !ids.includes(id)) {
        ids.push(id); onSeason(id); console.log(`Discovered season ${id} (${ids.length}/${count})`);
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
