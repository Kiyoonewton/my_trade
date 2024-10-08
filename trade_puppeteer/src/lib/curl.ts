import { fetchSeasonId } from "../controller/puppeteer.js";
import { fetchCrawler } from "../controller/fetch-crawler.js";

export default async function curl({
  vflId,
  position,
}: {
  vflId: number;
  position: number;
}) {
  try {
    const seasonKey = await fetchSeasonId({ vflId, position });
    const crawler = await fetchCrawler({
      seasonId: seasonKey?.seasonId,
    });
    if (crawler) {
      console.log(crawler);
    } else {
      console.log("Failed --->", position);
    }
  } catch (error) {
    console.log("error", error);
  }
}
