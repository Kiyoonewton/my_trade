import { fetchSeasonId } from "./controller/puppeteer.js";
import { fetchCrawler } from "./functions/fetch-crawler.js";
import redis from "redis";
import "dotenv/config";

const redisClient = redis.createClient({
  url: `redis://${process.env.REDIS_HOST}:${process.env.REDIS_PORT}`,
});

redisClient.on('connect', () => {
    console.log('Connected to Redis');
});

redisClient.on('error', (err) => {
    console.error('Redis error:', err);
});

redisClient.connect();

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
