import { redisClient } from "./lib/redis.js";
import curl from "./lib/curl.js";
import { execCrawlerLoop } from "./lib/execCrawlerLoop.js";

//process through a iterateCurl
if (process.argv.length === 2) {
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
    await execCrawlerLoop(roundMissedPossible);
  } else {
    console.log("Please wait for new season to be completed");
  }
} else {
  //process from the terminal
  const args = process.argv.slice(2);
  if (Number(args[0]) >= 1) {
    if ((args.length > 1)) {
      curl({ vflId: Number(args[1]), position: 2 + Number(args[0]) });
    } else {
      await execCrawlerLoop(Number(args[0]));
      // const iterations = [3, 7, 8];
      // iterations.map((item) =>
      //   curl({ vflId: item, position: 2 + Number(args[0]) }),
      // );
    }
  } else {
    console.log("Please input >= 1");
  }
}
