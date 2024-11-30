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
    const model_type = vflId === 3 ? 'VFL' : vflId === 8 ? 'VFB' : 'VFE';
    const seasonKey = await fetchSeasonId({ vflId, position });
    const crawler = await fetchCrawler({
      seasonId: seasonKey?.seasonId, model_type
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
