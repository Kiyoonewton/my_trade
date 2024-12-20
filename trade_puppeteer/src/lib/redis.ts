import { createClient } from 'redis';

// Create a Redis client
export const redisClient = createClient({
  url: 'redis://redisdb:6379',
});

redisClient.on('connect', () => {
  console.log('Connected to Redis');
});

redisClient.on('error', (err) => {
  console.error('Redis error:', err);
});
