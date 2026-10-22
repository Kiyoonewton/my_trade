import {createInterface} from 'node:readline/promises';
import {seasonId} from './seasons.mjs';

export function archiveUrl(value) {
  const input = value.trim();
  let id;
  if (/^\d{5,}$/.test(input)) id = input;
  else {
    const url = new URL(input);
    if (url.protocol !== 'https:' || url.hostname !== 'st-cdn001.akamaized.net'
        || !url.pathname.startsWith('/bet9javirtuals/en/1/season/')) {
      throw new Error('Enter a numeric source season ID or the Bet9ja virtual football archive URL.');
    }
    id = seasonId(input);
  }
  if (!id) throw new Error('The current season ID is missing or invalid.');
  return `https://st-cdn001.akamaized.net/bet9javirtuals/en/1/season/${id}/archive`;
}

export async function promptForArchive(input = process.stdin, output = process.stdout) {
  if (!input.isTTY) throw new Error('Run collector in an interactive terminal (do not use -T): it prompts for the current season.');
  const readline = createInterface({input, output});
  try {
    while (true) {
      const value = await readline.question('Current season ID or archive URL: ');
      try { return archiveUrl(value); }
      catch (error) { output.write(`${error.message}\n`); }
    }
  } finally { readline.close(); }
}
