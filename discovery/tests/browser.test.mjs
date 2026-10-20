import {test} from 'node:test';
import assert from 'node:assert/strict';
import {createServer} from 'node:http';
import puppeteer from 'puppeteer-core';
import {discover} from '../discover.mjs';

test('Puppeteer loads archive pages and returns 85 unique IDs oldest first', {skip:!process.env.CHROMIUM_PATH}, async()=>{
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
 assert.equal(ids[0],'3147519'); assert.equal(ids[84],'3147603');
 } finally {if(browser)await browser.close();await new Promise(r=>server.close(r));}
});
