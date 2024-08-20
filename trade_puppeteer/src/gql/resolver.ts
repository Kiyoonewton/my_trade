import { fetchSeasonId } from "../controller/puppeteer.js";
import { fetchCrawler } from "../functions/fetch-crawler.js";

export const resolvers = {
  Query: {
    seasonId: async (
      _: void,
      { vflId, position }: { vflId: number; position?: number },
    ) => {
      const seasonKey = await fetchSeasonId({ vflId, position });
      await fetchCrawler({ seasonId: seasonKey?.seasonId, vflId });

      return seasonKey?.seasonId;
    },
  },
};
