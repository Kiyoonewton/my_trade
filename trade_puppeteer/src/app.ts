import { fetchSeasonId } from "./controller/puppeteer.js";
import { fetchCrawler } from "./controller/fetch-crawler.js";

export default async function curl({
  vflId,
  position,
}: {
  vflId: number;
  position: number;
}) {
  const seasonKey = await fetchSeasonId({ vflId, position });
  console.log(seasonKey);

  await fetchCrawler({ seasonId: seasonKey?.seasonId, vflId });
}

//process through a cron

//process from the terminal
const args = process.argv.slice(2);

curl({ vflId: Number(args[0]), position: Number(args[1]) });
