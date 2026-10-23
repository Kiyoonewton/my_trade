import {test} from 'node:test';
import assert from 'node:assert/strict';
import {synchronizeCompleted, completedSnapshot} from '../database.mjs';
import {pendingSeasons} from '../progress.mjs';
import {runtime} from '../runtime.mjs';

test('MongoDB completion snapshot is authoritative without changing archive order', () => {
  const progress = {competition:'virtual-euro', order:['30000','20000','10000'], seasons:{
    '30000':{status:'pending'}, '20000':{status:'complete'}, '10000':{status:'network'},
  }};
  const completed = synchronizeCompleted(progress, {competition:'virtual-euro',season_ids:['10000','40000']});
  assert.equal(completed.has('10000'), true);
  assert.equal(completed.has('20000'), false);
  assert.equal(progress.seasons['20000'].status, 'pending');
  assert.equal(progress.seasons['10000'].status, 'complete');
  assert.equal(completed.has('40000'), true);
  assert.deepEqual(pendingSeasons(progress), ['20000','30000']);
  assert.deepEqual(progress.order, ['30000','20000','10000']);
});

test('a fresh progress file can stop on complete database history', () => {
  const progress = {competition:'virtual-euro',order:[],seasons:{}};
  const completed = synchronizeCompleted(progress, {competition:'virtual-euro',season_ids:['10000']});
  assert.equal(completed.has('10000'), true);
  assert.deepEqual(pendingSeasons(progress), []);
  assert.throws(() => synchronizeCompleted(progress, {competition:'virtual-football',season_ids:['10000']}));
  assert.throws(() => synchronizeCompleted(progress, {competition:'virtual-euro',season_ids:[10000]}));
});

test('completion command reaches the selected league in Docker and PHP', () => {
  for (const local of [true,false]) {
    const job = runtime(local,{COMPETITION_ID:'virtual-euro'}).completedSeasons();
    assert.deepEqual(job.args.slice(-2), ['matches:completed','--competition=virtual-euro']);
  }
});

test('database command errors and invalid JSON fail closed', async () => {
  const job = script => ({command:process.execPath,args:['-e',script],cwd:process.cwd()});
  assert.deepEqual(await completedSnapshot(job('console.log(JSON.stringify({competition:"virtual-euro",season_ids:[]}))')),
    {competition:'virtual-euro',season_ids:[]});
  await assert.rejects(() => completedSnapshot(job('process.exit(1)')), /Could not check/);
  await assert.rejects(() => completedSnapshot(job('console.log("not JSON")')), /valid JSON/);
});
