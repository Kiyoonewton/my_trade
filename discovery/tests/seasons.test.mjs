import {test} from 'node:test';
import assert from 'node:assert/strict';
import {oldestFirst, seasonId, collectBatch} from '../seasons.mjs';
test('85 unique seasons in archive chronology, duplicates ignored', () => {
 const ids = Array.from({length:90}, (_,i)=>String(3147603-i));
 const result = oldestFirst([ids[0],...ids],85);
 assert.equal(result.length,85); assert.equal(new Set(result).size,85);
 assert.equal(result[0],ids[84]); assert.equal(result[84],ids[0]);
});
test('insufficient archive stops before collection',()=>assert.throws(()=>oldestFirst(['3147603'],85),/No collection started/));
test('extract actual source season IDs',()=>{
 assert.equal(seasonId('https://example.org/#/season/3147603/standings'),'3147603');
 assert.equal(seasonId('https://example.org/vf:season:3147603/1'),'3147603');
 assert.equal(seasonId('https://example.org/archive'),null);
});
test('incomplete season never prevents later seasons',async()=>{
 const seen=[];
 const result=await collectBatch(['10001','10002','10003'],async id=>{
 seen.push(id); if(id==='10002')throw Error('API incomplete'); return true;
 },()=>{});
 assert.deepEqual(seen,['10001','10002','10003']);
 assert.deepEqual(result,{complete:2,incomplete:1});
});

test('archive order is respected rather than sorting numeric IDs',()=>assert.deepEqual(oldestFirst(['90000','10000','80000'],3),['80000','10000','90000']));
