import axios from "axios";
import "dotenv/config";

export const fetchCrawler = async ({ seasonId, model_type }: { seasonId: string, model_type: 'VFL' | 'VFE' | 'VFB' }) => {
  console.log('====================================');
  console.log(seasonId);
  console.log('====================================');
  try {
    const mutation = `mutation {
        createSeason(seasonId: ${seasonId}, model_type: ${model_type}) {data}
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