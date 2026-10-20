import {test} from 'node:test';
import assert from 'node:assert/strict';
import {mkdtemp, writeFile, readFile, rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {loadProgress, saveJson, addDiscovered, pendingOldestFirst, retryNetwork} from '../progress.mjs';

test('existing manifests import completed seasons and preserve network holes across restarts', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'trade-progress-'));
  try {
    await writeFile(join(dir, 'seasons-100.json'), JSON.stringify({season_ids:['90000','10000','80000'],
      processed:[{season_id:'90000',status:'complete'},{season_id:'10000',status:'network'}]}));
    let {path, progress} = await loadProgress(dir, 'virtual-football');
    assert.deepEqual(pendingOldestFirst(progress), ['80000','10000']);
    addDiscovered(progress, ['70000','90000']);
    assert.deepEqual(pendingOldestFirst(progress), ['80000','10000','70000']);
    progress.seasons['10000'] = {status:'complete'};
    await saveJson(path, progress);
    ({progress} = await loadProgress(dir, 'virtual-football'));
    assert.deepEqual(pendingOldestFirst(progress), ['80000','70000']);
    assert.equal(JSON.parse(await readFile(path)).seasons['90000'].status, 'complete');
    const other = await loadProgress(dir, 'other-league');
    assert.deepEqual(other.progress.order, []);
  } finally {await rm(dir, {recursive:true, force:true});}
});

test('new head stops at completed boundary while pending older seasons remain scheduled', () => {
  const p = {order:['90000','10000','80000'], seasons:{'90000':{status:'complete'},'10000':{status:'network'},'80000':{status:'complete'}}};
  addDiscovered(p, ['70000','60000']);
  assert.deepEqual(pendingOldestFirst(p), ['10000','60000','70000']);
});

test('network retry backs off and stops retrying on success or data failure', async () => {
  const waits = []; let calls = 0;
  const status = await retryNetwork(async () => ++calls < 3 ? 'network' : 'complete',
    {delays:[2,5], sleep:async ms=>waits.push(ms), log:()=>{}});
  assert.equal(status, 'complete'); assert.equal(calls, 3); assert.deepEqual(waits,[2,5]);
  calls = 0;
  assert.equal(await retryNetwork(async()=>{calls++;return 'incomplete'}, {log:()=>{}}), 'incomplete');
  assert.equal(calls,1);
  calls = 0;
  assert.equal(await retryNetwork(async()=>{calls++;return 'network'}, {delays:[0,0],sleep:async()=>{},log:()=>{}}),'network');
  assert.equal(calls,3);
});

test('corrupt checkpoint fails instead of silently fetching completed history again', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'trade-progress-'));
  try {
    await writeFile(join(dir,'collection-progress-virtual-football.json'), '{broken');
    await assert.rejects(()=>loadProgress(dir,'virtual-football'), /Cannot read/);
  } finally {await rm(dir,{recursive:true,force:true});}
});

test('observed archive order corrects an older supplied season previously placed first', () => {
 const p={order:['10001','10003','10002'],seasons:{}};
 addDiscovered(p,['10003','10002','10001']);
 assert.deepEqual(p.order,['10003','10002','10001']);
 assert.deepEqual(pendingOldestFirst(p),['10001','10002','10003']);
});
