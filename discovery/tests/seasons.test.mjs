import {test} from 'node:test';
import assert from 'node:assert/strict';
import {topDown, seasonId, collectBatch} from '../seasons.mjs';
test('85 unique seasons in top-to-bottom order, duplicates ignored', () => {
 const ids = Array.from({length:90}, (_,i)=>String(3147603-i));
 const result = topDown([ids[0],...ids],85);
 assert.equal(result.length,85); assert.equal(new Set(result).size,85);
 assert.equal(result[0],ids[0]); assert.equal(result[84],ids[84]);
});
test('fewer than 85 is valid when archive ends',()=>assert.deepEqual(topDown(['3147603','3147603'],85),['3147603']));
test('extract actual source season IDs',()=>{
 assert.equal(seasonId('https://example.org/#/season/3147603/standings'),'3147603');
 assert.equal(seasonId('https://example.org/vf:season:3147603/1'),'3147603');
 assert.equal(seasonId('https://example.org/archive'),null);
});
test('incomplete season never prevents later seasons',async()=>{
 const seen=[];
 const result=await collectBatch(['10001','10002','10003'],async id=>{
 seen.push(id); if(id==='10002')return false; return true;
 },()=>{});
 assert.deepEqual(seen,['10001','10002','10003']);
 assert.deepEqual(result,{complete:2,incomplete:1,networkFailures:0,stopped:false});
});

test('archive order is respected rather than sorting numeric IDs',()=>assert.deepEqual(topDown(['90000','10000','80000'],3),['90000','10000','80000']));

import {archiveUrl} from '../configuration.mjs';
test('current source ID constructs the direct archive URL',()=>{
 assert.equal(archiveUrl('3147738'),'https://st-cdn001.akamaized.net/bet9javirtuals/en/1/season/3147738/archive');
 assert.equal(archiveUrl('https://st-cdn001.akamaized.net/bet9javirtuals/en/1/season/3147738/archive'),archiveUrl('3147738'));
 assert.throws(()=>archiveUrl('YOUR_ACTUAL_SEASON_ID'));
 assert.throws(()=>archiveUrl('https://example.org/season/3147738/archive'));
});

import {PassThrough} from 'node:stream';
import {promptForArchive} from '../configuration.mjs';
test('each invocation asks for the current season',async()=>{
 for (const id of ['3147738','3147739']) {
  const input=new PassThrough(); input.isTTY=true;
  const output=new PassThrough(); let text=''; output.on('data',chunk=>text+=chunk);
  setImmediate(()=>input.write(`${id}\n`));
  assert.equal(await promptForArchive(input,output),archiveUrl(id));
  assert.match(text,/Current season ID or archive URL/);
  input.destroy(); output.destroy();
 }
});

test('three uncrawlable seasons stop the batch before the fourth',async()=>{
 const seen=[];const r=await collectBatch(['10001','10002','10003','10004'],async id=>{seen.push(id);return false},()=>{});
 assert.equal(seen.length,3);assert.equal(r.stopped,true);
});
test('network failures do not count; success resets the failure streak',async()=>{
 const statuses=['incomplete','network','incomplete','complete','incomplete','incomplete','network','incomplete','complete'];
 let calls=0;const r=await collectBatch(statuses.map((_,i)=>String(10000+i)),async()=>({status:statuses[calls++]}),()=>{});
 assert.equal(calls,8);assert.equal(r.networkFailures,2);assert.equal(r.complete,1);assert.equal(r.stopped,true);
});
test('database/configuration failure stops instead of pretending the season is uncrawlable',async()=>{
 await assert.rejects(()=>collectBatch(['10001'],async()=>({status:'fatal'}),()=>{}),/database\/configuration/);
});
