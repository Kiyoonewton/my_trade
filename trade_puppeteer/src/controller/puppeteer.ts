import puppeteer from "puppeteer";
import "dotenv/config";

const apiEndpoint = process.env.SEASON_ENDPOINT;

function sleep(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
export const fetchSeasonId = async ({
  vflId,
  position,
}: {
  vflId: number;
  position: number;
}) => {
  position;
  const browser = await puppeteer.launch({
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
    headless: true,
  });
  const page = await browser.newPage();
  await page.setUserAgent(
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
  );

  await page.setExtraHTTPHeaders({
    "Accept-Language": "en-US,en;q=0.9",
  });

  const bunPath = `#sr-container > div > div > div.container.container-main.contair-full-height-flex-auto > div > div > div > div > div > span > div > div > div > div > div > a:nth-child(${vflId})`;
  const achivePath =
    "#sr-container > div > div > div.menu-wrapper.menu-full-width-bg.menu-mobile-top.menu-mobile-sticky > div.container.no-padding > ul > li:nth-child(6) > a";
  const clickFormCell = `#sr-container > div > div > div.container.container-main.contair-full-height-flex-auto > div > div > div > div > div.panel.margin-bottom > div > div > div:nth-child(1) > table > tbody > tr:nth-child(${
    position ? position : 3
  })`;
  const buttonPath =
    "#sr-container > div > div > div.container.container-main.contair-full-height-flex-auto > div > div > div > div > div.panel.margin-bottom > div > div > div.col-xs-12.text-center.margin-top-medium > button";

  await page.goto(apiEndpoint);

  const bunPathHandle = await page.$(bunPath);
  if (bunPathHandle) {
    await bunPathHandle.click();
  }
  await sleep(2500);

  const achivePathHandle = await page.$(achivePath);
  if (achivePathHandle) {
    await achivePathHandle.click();
  }

  await sleep(2500);

  if (position > 20) {
    const clickIntervals = [30, 60];
    let clicks = 1;

    for (let interval of clickIntervals) {
      if (position > interval) {
        clicks++;
      }
    }

    for (let i = 0; i < clicks; i++) {
      const buttonPathHandle = await page.$(buttonPath);
      if (buttonPathHandle) {
        await buttonPathHandle.click();
        if (i < clicks - 1) {
          await sleep(1000);
        }
      }
    }
  }

  const clickFormCellHandle = await page.$(clickFormCell);
  if (clickFormCellHandle) {
    await clickFormCellHandle.click();
  }
  const fullUrl = page.url();
  const seasonId = fullUrl.substring(fullUrl.lastIndexOf("/") + 1);
  await browser.close();

  return { seasonId, fullUrl: fullUrl + "/standings" };
};
