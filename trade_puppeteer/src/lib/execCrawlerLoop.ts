import curl from "./curl.js";
import { redisClient } from "./redis.js";

export async function execCrawlerLoop(totalRoundMissed: number, type: number) {
  // const iterations = [3, 7, 8];
  // const type = [type];
  const totalOperations = totalRoundMissed; // Total number of operations
  let completedOperations = 0;

  try {
    for (let index = totalRoundMissed; index >= 3; index--) {
      // for (const item of iterations) {
      await curl({ vflId: type, position: index });

      completedOperations++;

      console.log(
        `On -> ${index}, Progress .... ${Math.round(
          (completedOperations / totalOperations) * 100
        )}%`
      );
      // }
    }

    const now = new Date();
    const dateString = now.toISOString();
    console.log("Done");

    await redisClient.set("timeStamp", dateString);
  } catch (error) {
    console.error("Error during operations:", error);
  } finally {
    redisClient.quit();
  }
}
