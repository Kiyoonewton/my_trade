import { redisClient } from "./lib/redis.js";
import { execCrawlerLoop } from "./lib/execCrawlerLoop.js";


//process through a iterateCurl
if (process.argv.length === 2 || process.argv.length === 3) {
  const type = process.argv[2].toLowerCase() == 'vfl' ? 3 : process.argv[2].toLowerCase() == 'vfb' ? 8 : 7;
  await redisClient.connect();
  const value = await redisClient.get("timeStamp");
  const timestamp = new Date(value);
  const minusTimestampFromNewDate = Number(new Date()) - Number(timestamp);
  const anHourAnd52SecsInMs = 6720000;
  const totalRoundMissed =
    Math.floor(minusTimestampFromNewDate / anHourAnd52SecsInMs) + 1; // plus 1 for the 13 or 14 matches diff of VFEL
  const roundMissedPossible = totalRoundMissed > 85 ? 85 : totalRoundMissed;
  console.log("Total round(s) missed:", roundMissedPossible);
  if (totalRoundMissed >= 1) {
    await execCrawlerLoop(roundMissedPossible, type);
  } else {
    console.log("Please wait for new season to be completed");
  }
} else {
  const args = process.argv.slice(2);
  const type = args[0].toLowerCase() == 'vfl' ? 3 : args[0].toLowerCase() == 'vfb' ? 8 : 7;
  if (Number(args[1]) >= 3) {
    execCrawlerLoop(Number(args[1]), type);
  } else {
    console.log("Please let 4th value >= 3");
  }
}
