import axios from "axios";
import "dotenv/config";

export const fetchCrawler = async ({ seasonId }: { seasonId: string }) => {
  console.log('====================================');
  console.log(seasonId);
  console.log('====================================');
  try {
    const mutation = `mutation {
        createSeason(seasonId: ${seasonId}) {data}
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
      }
    );

    return response?.data?.data?.createSeason;
  } catch (error) {
    console.error("Retry---->:", seasonId);
  }
};