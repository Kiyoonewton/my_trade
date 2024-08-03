import { fetchSeasonId } from "./puppeteer.mjs";

export const getSeasonId = async (event) => {
  if (event.httpMethod !== "POST") {
    throw new Error(
      `postMethod only accepts POST method, you tried: ${event.httpMethod} method.`,
    );
  }

  const body = JSON.parse(event.body);

  if (
    Object.keys(body).length > 2 ||
    !Object.keys(body).includes("id") ||
    !Object.keys(body).includes("pos")
  ) {
    return {
      statusCode: 400,
      body: JSON.stringify({
        message: "Invalid input. Only needs an {id: 3 | 7 | 8; pos:int}",
      }),
    };
  }

  const response = {
    statusCode: 200,
    body: JSON.stringify(fetchSeasonId(body.id)),
  };
  return response;
};
