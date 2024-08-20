import axios from "axios";
import "dotenv/config";

export const fetchCrawler = async ({
  seasonId,
  vflId,
}: {
  seasonId: string;
  vflId: number;
}) => {
  try {
    const mutation = `mutation {
        createSeason(seasonId: ${seasonId}, vflId: ${vflId}) {
            season_id
            }
        }`;

    const response = await axios.post(
      process.env.CRAWLER_URL,
      {
        query: mutation,
      },
      {
        headers: {
          "Content-Type": "application/json",
        },
      },
    );

    return response?.data?.data?.createSeason;
  } catch (error) {
    console.error("Error:", error);
  }
};
