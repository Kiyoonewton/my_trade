import { fetchSeasonId } from "../controller/puppeteer.js";
export const resolvers = {
    Query: {
        seasonId: async (_, { vflId, position }) => {
            const seasonKey = await fetchSeasonId({ vflId, position });
            return seasonKey?.seasonId;
        },
    },
};
