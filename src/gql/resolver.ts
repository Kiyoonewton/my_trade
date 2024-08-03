import { fetchSeasonId } from "../controller/puppeteer.js";

export const resolvers = {
    Query: {
      seasonId: async (
        _: void,
        { vflId, position }: { vflId: number; position?: number },
      ) => {
        const seasonKey = await fetchSeasonId({ vflId, position });
        return seasonKey?.seasonId;
      },
    },
  };
