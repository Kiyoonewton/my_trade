import {test} from 'node:test';
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import puppeteer from 'puppeteer-core';
import {discover} from '../discover.mjs';

test('Puppeteer loads archive pages and returns 85 unique IDs top to bottom', {skip:!process.env.CHROMIUM_PATH}, async()=>{
 const html = `<!doctype html><table><tbody id="rows"></tbody></table><button id="more">More</button>
 <script>
 let offset=0;
 function load(){for(let i=offset;i<Math.min(offset+20,90);i++){
 const id=3147603-i; document.querySelector('#rows').insertAdjacentHTML('beforeend',
 '<tr><td><a href="/#/season/'+id+'">Season</a></td></tr>');
 if(i===0)document.querySelector('#rows').insertAdjacentHTML('beforeend','<tr><td><a href="/#/season/'+id+'">Duplicate</a></td></tr>');
 }offset+=20;if(offset>=90)document.querySelector('#more').remove()}
 document.querySelector('#more').onclick=load;load();
 </script>`;
 const server=createServer((req,res)=>{res.setHeader('Content-Type','text/html');res.end(html)});
 await new Promise(r=>server.listen(0,'0.0.0.0',r));
 let browser;
 try {
 browser=await puppeteer.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox'],headless:true});
 const page=await browser.newPage();
 const ids=await discover(page,{endpoint:`http://127.0.0.1:${server.address().port}`,count:85,
 selectors:{league:'',archive:'',rows:'#rows tr',more:'#more'},timeout:1000});
 assert.equal(ids.length,85); assert.equal(new Set(ids).size,85);
 assert.equal(ids[0],'3147603'); assert.equal(ids[84],'3147519');
 const recent = await discover(page,{endpoint:`http://127.0.0.1:${server.address().port}`,count:85,
 selectors:{league:'',archive:'',rows:'#rows tr',more:'#more'},timeout:1000,shouldStop:id=>id==='3147600'});
 assert.deepEqual(recent,['3147603','3147602','3147601']);
 } finally {if(browser)await browser.close();await new Promise(r=>server.close(r));}
});

test('real provider div role=main supports clickable rows and fewer than 85 seasons', {skip:!process.env.CHROMIUM_PATH}, async()=>{
 const html=`<!doctype html><div role="main"><table><thead><tr><th>Season</th><th>Champion</th></tr></thead><tbody>
 ${['3147738','3147737','3147736'].map(id=>`<tr class="cursor-pointer" onclick="location.href='/season/${id}'"><td>League ${id}</td></tr>`).join('')}
 </tbody></table></div>`;
 const server=createServer((req,res)=>{res.setHeader('Content-Type','text/html');res.end(html)});
 await new Promise(r=>server.listen(0,'0.0.0.0',r));let browser;
 try {
  browser=await puppeteer.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox'],headless:true});
  const page=await browser.newPage();
  const ids=await discover(page,{endpoint:`http://127.0.0.1:${server.address().port}/archive`,count:85,timeout:2000});
  assert.deepEqual(ids,['3147738','3147737','3147736']);
 } finally {if(browser)await browser.close();await new Promise(r=>server.close(r));}
});


test('access denial is reported immediately rather than as a selector timeout', {skip:!process.env.CHROMIUM_PATH}, async()=>{
 const server=createServer((req,res)=>{res.writeHead(403,{'Content-Type':'text/html'});res.end('<h1>Access Denied</h1>')});
 await new Promise(r=>server.listen(0,'0.0.0.0',r));let browser;
 try {
  browser=await puppeteer.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox'],headless:true});
  const page=await browser.newPage();
  await assert.rejects(()=>discover(page,{endpoint:`http://127.0.0.1:${server.address().port}/archive`,count:85,timeout:2000}),/Access Denied/);
 } finally {if(browser)await browser.close();await new Promise(r=>server.close(r));}
});

test('same-URL current row and unresolved row do not block older seasons', {skip:!process.env.CHROMIUM_PATH}, async()=>{
 const html=`<!doctype html><table><tbody id="rows">
 <tr onclick="history.pushState({},'',location.href)"><td>Current</td></tr>
 <tr><td>Unavailable row</td></tr>
 <tr><td><a href="/season/10002/archive">Older</a></td></tr>
 </tbody></table>`;
 const server=createServer((req,res)=>{res.setHeader('Content-Type','text/html');res.end(html)});
 await new Promise(r=>server.listen(0,'0.0.0.0',r));let browser;
 try {
  browser=await puppeteer.launch({executablePath:process.env.CHROMIUM_PATH,args:['--no-sandbox'],headless:true});
  const page=await browser.newPage();const unresolved=[];
  const ids=await discover(page,{endpoint:`http://127.0.0.1:${server.address().port}/season/10003/archive`,count:85,timeout:200,
   selectors:{league:'',archive:'',rows:'#rows tr',more:'#more'},initialIds:['10003'],onUnresolved:row=>unresolved.push(row)});
  assert.deepEqual(ids,['10003','10002']);assert.deepEqual(unresolved,[2]);
 } finally {if(browser)await browser.close();await new Promise(r=>server.close(r));}
});
