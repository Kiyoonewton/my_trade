import {oldestFirst, seasonId} from './seasons.mjs';

const container = '#sr-container > div > div > div.container.container-main.contair-full-height-flex-auto > div > div > div > div > div.panel.margin-bottom > div > div';
export const defaults = {
  league: '#sr-container > div > div > div.container.container-main.contair-full-height-flex-auto > div > div > div > div > div > span > div > div > div > div > div > a:nth-child(7)',
  archive: '#sr-container > div > div > div.menu-wrapper.menu-full-width-bg.menu-mobile-top.menu-mobile-sticky > div.container.no-padding > ul > li:nth-child(6) > a',
  rows: `${container} > div:nth-child(1) > table > tbody > tr`,
  more: `${container} > div.col-xs-12.text-center.margin-top-medium > button`,
};

export async function discover(page, {endpoint, count, selectors = defaults, timeout = 20000}) {
  const openArchive = async () => {
    await page.goto(endpoint, {waitUntil:'domcontentloaded', timeout});
    if (selectors.league) {
      await page.waitForSelector(selectors.league, {timeout}); await page.click(selectors.league);
    }
    if (selectors.archive) {
      await page.waitForSelector(selectors.archive, {timeout}); await page.click(selectors.archive);
    }
    await page.waitForSelector(selectors.rows, {timeout});
  };
  const rowCount = () => page.$$eval(selectors.rows, rows => rows.length);
  const more = async () => {
    const before = await rowCount();
    const button = await page.$(selectors.more);
    if (!button) return false;
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
    const rows = await page.$$eval(selectors.rows, elements => elements.map((row, index) => ({
      index, link: row.querySelector('a[href]')?.href ?? '',
      dataId: row.getAttribute('data-season-id') ?? '',
      clickable: !!row.querySelector('td') && (!!row.querySelector('a,td') || !!row.onclick),
    })));
    for (const row of rows) {
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
          await openArchive();
          while (await rowCount() < rows.length) {
            if (!await more()) throw new Error('Could not restore loaded archive rows.');
          }
        }
      }
      if (id) ids.push(id);
      if (new Set(ids).size >= count) return oldestFirst(ids, count);
    }
    if (++pages > 30 || !await more()) return oldestFirst(ids, count);
  }
}
