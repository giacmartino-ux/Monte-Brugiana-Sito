'use strict';
const http=require('node:http');
const fs=require('node:fs');
const path=require('node:path');
const html=fs.readFileSync(path.join(__dirname,'index.html'));
http.createServer((req,res)=>{
  const p=(req.url||'/').split('?')[0];
  if(p==='/'||p==='/index.html'){
    res.writeHead(200,{'Content-Type':'text/html; charset=utf-8','Cache-Control':'public, max-age=60','X-Content-Type-Options':'nosniff','Referrer-Policy':'strict-origin-when-cross-origin'});
    return res.end(html);
  }
  if(p==='/health'){res.writeHead(200,{'Content-Type':'text/plain'});return res.end('ok');}
  res.writeHead(404,{'Content-Type':'text/plain; charset=utf-8'});res.end('Pagina non trovata');
}).listen(process.env.PORT||3000,'0.0.0.0');
