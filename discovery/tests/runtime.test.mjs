import {test} from 'node:test';
import assert from 'node:assert/strict';
import {runtime} from '../runtime.mjs';

test('local visible Chrome sends API jobs to Docker app service',()=>{
 const r=runtime(true,{});
 assert.equal(r.launchOptions.headless,false);
 assert.equal(r.launchOptions.defaultViewport,null);
 assert.equal(r.launchOptions.executablePath,'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome');
 assert.deepEqual(r.launchOptions.args,[]);
 const job=r.collection('3147770');
 assert.equal(job.command,'docker');
 assert.deepEqual(job.args.slice(-6),['--rm','-T','app','matches:collect','3147770','--competition=virtual-football']);
 assert.equal(job.args.includes('collector'),false);
 assert.match(r.rawDirectory,/\/raw$/);
});
test('Docker mode retains headless Chromium and direct PHP jobs',()=>{
 const r=runtime(false,{});
 assert.equal(r.launchOptions.headless,true);
 assert.equal(r.launchOptions.executablePath,'/usr/bin/chromium');
 assert.equal(r.rawDirectory,'/raw');
 assert.deepEqual(r.collection('3147770'),{command:'php',args:['/app/artisan','matches:collect','3147770','--competition=virtual-football'],cwd:'/app'});
});
test('explicit browser path and competition are respected',()=>{
 const r=runtime(true,{CHROMIUM_PATH:'/custom/chrome',COMPETITION_ID:'league'});
 assert.equal(r.launchOptions.executablePath,'/custom/chrome');
 assert.equal(r.collection('3147770').args.at(-1),'--competition=league');
});
