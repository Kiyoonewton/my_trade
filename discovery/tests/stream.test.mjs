import {test} from 'node:test';
import assert from 'node:assert/strict';
import {seasonStream} from '../season-stream.mjs';
import {collectBatch} from '../seasons.mjs';

test('current season reaches API before discovery finishes, without duplicates',async()=>{
 let release;
 const discovered = new Promise(resolve=>release=resolve);
 const processed=[];
 const stream=seasonStream('3147738',async add=>{
  await discovered;add('3147738');add('3147737');add('3147737');add('3147736');
 });
 const result=await collectBatch(stream,async id=>{
  processed.push(id);
  if(id==='3147738') {assert.deepEqual(processed,['3147738']);release();}
  return true;
 },()=>{});
 assert.deepEqual(processed,['3147738','3147737','3147736']);assert.equal(result.complete,3);
});

test('maximum count includes supplied season',async()=>{
 const result=[];
 for await(const id of seasonStream('3147738',async add=>{
  for(const id of ['3147738','3147737','3147736','3147735'])add(id);
 },{limit:3}))result.push(id);
 assert.deepEqual(result,['3147738','3147737','3147736']);
});

test('three incomplete seasons cancel background discovery',async()=>{
 let cancelled=false,calls=0;
 const stream=seasonStream('3147738',async(add,signal)=>{
  add('3147737');add('3147736');add('3147735');
  await new Promise(resolve=>signal.addEventListener('abort',()=>{cancelled=true;resolve()},{once:true}));
 });
 const result=await collectBatch(stream,async()=>{calls++;return false},()=>{});
 assert.equal(result.stopped,true);assert.equal(calls,3);assert.equal(cancelled,true);
});

test('archive failure is reported after supplied season has been processed',async()=>{
 const seen=[];
 await assert.rejects(()=>collectBatch(seasonStream('3147738',async()=>{throw Error('archive timeout')}),
 async id=>{seen.push(id);return true},()=>{}),/archive timeout/);
 assert.deepEqual(seen,['3147738']);
});
