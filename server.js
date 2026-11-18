'use strict';
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const staticPages = new Map([
 ['/', 'index.html'], ['/index.html','index.html'],
 ['/la-fatica.html','la-fatica.html'], ['/la-comunita.html','la-comunita.html'],
 ['/don-marco.html','don-marco.html'], ['/il-percorso.html','il-percorso.html'],
 ['/stile.css','stile.css'], ['/favicon.svg','favicon.svg'],
 ['/robots.txt','robots.txt'], ['/sitemap.xml','sitemap.xml'],
]);
const assetNames = new Set(['marco-profilo','marco-intervento','fatica-sera','hero-amici','vita-insieme','camminare','stanza','ascolto']);
const headers = {
  'X-Content-Type-Options':'nosniff',
  'X-Frame-Options':'DENY',
  'Referrer-Policy':'strict-origin-when-cross-origin',
  'Permissions-Policy':'camera=(), microphone=(), geolocation=()',
  'Strict-Transport-Security':'max-age=31536000; includeSubDomains',
  'Content-Security-Policy':"default-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; upgrade-insecure-requests"
};
function serve(req,res){
 const url=(req.url||'/').split('?')[0];
 if(url==='/health'){
  const assetsOK=[...assetNames].every(name=>{
    try{const p=path.join(__dirname,'assets',name+'.jpg'); const st=fs.statSync(p); return st.isFile() && st.size>20000}
    catch{return false}
  });
  const pagesOK=[...staticPages.values()].every(file=>fs.existsSync(path.join(__dirname,file)));
  const ok=assetsOK && pagesOK;
  res.writeHead(ok?200:503,{'Content-Type':'text/plain; charset=utf-8','Cache-Control':'no-store'});
  return res.end(ok?'ok - pages and photographic assets verified':'unhealthy - page or asset unavailable');
}
 if(url==='/favicon.ico'){res.writeHead(204,headers);return res.end()}
 let file=staticPages.get(url);
 if(!file && /^\/assets\/[a-z-]+\.jpg$/.test(url)){
  const name=url.substring(8,url.length-4);
  if(assetNames.has(name))file='assets/'+name+'.jpg';
 }
 if(!file){res.writeHead(404,{'Content-Type':'text/plain; charset=utf-8',...headers});return res.end('Pagina non trovata')}
 const where=path.join(__dirname,file);
 fs.readFile(where,(err,body)=>{
  if(err){res.writeHead(503,{'Content-Type':'text/plain; charset=utf-8',...headers});return res.end('Risorsa momentaneamente non disponibile')}
  const mime=file.endsWith('.jpg')?'image/jpeg':file.endsWith('.svg')?'image/svg+xml; charset=utf-8':file.endsWith('.css')?'text/css; charset=utf-8':file.endsWith('.txt')?'text/plain; charset=utf-8':file.endsWith('.xml')?'application/xml; charset=utf-8':'text/html; charset=utf-8';
  res.writeHead(200,{'Content-Type':mime,'Cache-Control':file.startsWith('assets/')?'public, max-age=86400':'public, max-age=60',...headers});
  if(req.method==='HEAD')return res.end();
  return res.end(body)
 });
}
http.createServer(serve).listen(Number(process.env.PORT||3000),'0.0.0.0');