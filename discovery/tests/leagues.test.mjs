import {test} from 'node:test';
import assert from 'node:assert/strict';
import {collectionOptions, leagues} from '../leagues.mjs';
import {runtime} from '../runtime.mjs';
import {mkdtemp, writeFile, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {loadProgress} from '../progress.mjs';

test('default remains English; CLI league takes precedence over environment', () => {
  assert.equal(collectionOptions([], {}).competition, 'virtual-football');
  assert.equal(collectionOptions(['85', '--competition=virtual-euro'], {COMPETITION_ID:'virtual-football'}).competition, 'virtual-euro');
  assert.deepEqual(collectionOptions(['--resume', '--competition', 'virtual-bundesliga'], {}).profile, leagues['virtual-bundesliga']);
  assert.equal(collectionOptions(['--resume'], {COMPETITION_ID:'virtual-euro'}).competition, 'virtual-euro');
  for (const args of [['--competition'], ['--competition=unknown'], ['0'], ['85','90'], ['--invalid']]) {
    assert.throws(() => collectionOptions(args, {}));
  }
});

test('selected league reaches Docker and direct PHP collection', () => {
  for (const local of [true, false]) {
    const job = runtime(local, {COMPETITION_ID:'virtual-bundesliga'}).collection('3148613');
    assert.equal(job.args.at(-1), '--competition=virtual-bundesliga');
  }
});

test('new league-labelled manifests and checkpoints remain isolated', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'trade-leagues-'));
  try {
    await writeFile(join(directory, 'seasons-100.json'), JSON.stringify({season_ids:['11111'], processed:[{season_id:'11111',status:'complete'}]}));
    for (const [league, id] of [['virtual-bundesliga','22222'],['virtual-euro','33333']]) {
      await writeFile(join(directory, `seasons-101-${league}.json`), JSON.stringify({competition:league, season_ids:[id], processed:[{season_id:id,status:'complete'}]}));
    }
    const english = await loadProgress(directory, 'virtual-football');
    const german = await loadProgress(directory, 'virtual-bundesliga');
    const euro = await loadProgress(directory, 'virtual-euro');
    assert.deepEqual(english.progress.order, ['11111']);
    assert.deepEqual(german.progress.order, ['22222']);
    assert.deepEqual(euro.progress.order, ['33333']);
    assert.equal(euro.progress.seasons['33333'].status, 'complete');
  } finally { await rm(directory, {recursive:true,force:true}); }
});
