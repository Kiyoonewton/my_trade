import {readFileSync, existsSync} from 'node:fs';

const local = new URL('../trade_crawler/config/competitions.json', import.meta.url);
const path = existsSync(local) ? local : new URL('../config/competitions.json', import.meta.url);
export const leagues = JSON.parse(readFileSync(path, 'utf8'));

export function collectionOptions(args, env = process.env) {
  let count = 85, resume = false, newestFirst = false, competition = env.COMPETITION_ID ?? 'virtual-football';
  let counted = false;
  for (let i = 0; i < args.length; i++) {
    const arg = args[i];
    if (arg === '--resume') resume = true;
    else if (arg === '--newest-first') newestFirst = true;
    else if (arg === '--competition') {
      competition = args[++i];
      if (!competition || competition.startsWith('--')) throw new Error('--competition needs a league ID.');
    } else if (arg.startsWith('--competition=')) competition = arg.slice(14);
    else if (/^\d+$/.test(arg) && !counted) { count = Number(arg); counted = true; }
    else throw new Error(`Unknown argument: ${arg}`);
  }
  if (!Number.isInteger(count) || count < 1 || count > 1000) throw new Error('Pass a season count from 1 to 1000.');
  if (!Object.hasOwn(leagues, competition)) throw new Error(`Unknown competition. Choose: ${Object.keys(leagues).join(', ')}`);
  return {count, resume, newestFirst, competition, profile:leagues[competition]};
}
