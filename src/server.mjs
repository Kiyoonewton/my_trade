const {express} = require('express')

// export const getSeasonId = async (event) => {
//   if (event.httpMethod !== "POST") {
//     throw new Error(
//       `postMethod only accepts POST method, you tried: ${event.httpMethod} method.`,
//     );
//   }

//   const body = JSON.parse(event.body);

// //   if (Object.keys(body).length > 1 || !Object.keys(body).includes("id")) {
// //     return {
// //       statusCode: 400,
// //       body: JSON.stringify({
// //         message: "Invalid input. Only needs an {id: 3 | 7 | 8}",
// //       }),
// //     };
// //   }

//   const response = {
//     statusCode: 200,
//     body: JSON.stringify(body),
//   };
//   return response;
// };

const express = require('express')
const app = express()

app.get('/', function (req, res) {
    res.send('Hello World')
  })