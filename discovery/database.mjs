import {spawn} from 'node:child_process';

export function synchronizeCompleted(progress, snapshot) {
  if (snapshot?.competition !== progress.competition || !Array.isArray(snapshot.season_ids)
      || snapshot.season_ids.some(id => typeof id !== 'string' || !/^\d{5,}$/.test(id))) {
    throw new Error('Invalid completed-season database snapshot.');
  }
  const complete = new Set(snapshot.season_ids);
  for (const [id, state] of Object.entries(progress.seasons)) {
    if (state.status === 'complete' && !complete.has(id)) progress.seasons[id] = {...state, status:'pending'};
  }
  for (const id of complete) progress.seasons[id] = {...progress.seasons[id], status:'complete'};
  return complete;
}

export async function completedSnapshot(job) {
  const output = await new Promise((resolve, reject) => {
    const child = spawn(job.command, job.args, {cwd:job.cwd, stdio:['ignore','pipe','inherit']});
    let text = '';
    child.stdout.setEncoding('utf8');
    child.stdout.on('data', chunk => { text += chunk; });
    child.once('error', reject);
    child.once('exit', code => code === 0 ? resolve(text)
      : reject(new Error('Could not check completed seasons in MongoDB. Rebuild the app image and check database connectivity.')));
  });
  try { return JSON.parse(output); }
  catch { throw new Error('Database completion command did not return valid JSON.'); }
}
