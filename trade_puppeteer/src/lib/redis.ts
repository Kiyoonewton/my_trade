import { createClient } from 'redis';

// Create a Redis client
export const redisClient = createClient({
  url: 'redis://127.0.0.1:6379',  // Default Redis port
});

// Connect to Redis
// redisClient.connect();


// redisClient.connect()
//   .then(() => {
//     console.log('Connected to Redis successfully!');
//   })
//   .catch((err) => {
//     console.error('Failed to connect to Redis:', err);
//   });

redisClient.on('connect', () => {
  console.log('Connected to Redis');
});

redisClient.on('error', (err) => {
  console.error('Redis error:', err);
});
