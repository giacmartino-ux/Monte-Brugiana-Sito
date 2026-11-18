'use strict';
const http=require('node:http');
const fs=require('node:fs');
const path=require('node:path');
const files={
 '/':'index.html',
 '/index.html':'index.html',
 '/la-fatica.html':'la-fatica.html',
 '/la-comunita.html':'la-comunita.html',
 '/don-marco.html':'don-marco.html',
 '/il-percorso.html':'il-percorso.html',
 '/stile.css':'stile.css',
 '/robots.txt':'robots.txt',
 '/sitemap.xml':'sitemap.xml',
 '/favicon.svg':'favicon.svg',
 '/immagine-ia-tavola.svg':'immagine-ia-tavola.svg',
 '/immagine-ia-camera.svg':'immagine-ia-camera.svg'
};
const security={
 'Strict-Transport-Security':'max-age=31536000',
 'X-Content-Type-Options':'nosniff',
 'Referrer-Policy':'strict-origin-when-cross-origin',
 'X-Frame-Options':'DENY',
 'Permissions-Policy':'camera=(), microphone=(), geolocation=()',
 'Content-Security-Policy':"default-src 'none'; img-src 'self' https://images.unsplash.com https://www.brugiana.it https://www.visittuscany.com https://storage.googleapis.com data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; upgrade-insecure-requests"
};
http.createServer((req,res)=>{
 const pathname=(req.url||'/').split('?')[0];
 if(pathname==='/favicon.ico'){res.writeHead(204,{'Cache-Control':'public, max-age=86400'});return res.end();}
 if(pathname==='/health'){res.writeHead(200,{'Content-Type':'text/plain; charset=utf-8'});return res.end('ok');}
 const file=files[pathname];
 if(!file){res.writeHead(404,{'Content-Type':'text/plain; charset=utf-8',...security});return res.end('Pagina non trovata');}
 const location=path.join(__dirname,file);
 fs.readFile(location,(err,body)=>{
  if(err){res.writeHead(503,{'Content-Type':'text/plain; charset=utf-8',...security});return res.end('Pagina temporaneamente non disponibile');}
  const mime=file.endsWith('.css')?'text/css; charset=utf-8':file.endsWith('.svg')?'image/svg+xml; charset=utf-8':file.endsWith('.txt')?'text/plain; charset=utf-8':file.endsWith('.xml')?'application/xml; charset=utf-8':'text/html; charset=utf-8';
  res.writeHead(200,{'Content-Type':mime,'Cache-Control':'public, max-age=60',...security});
  res.end(body);
 });
}).listen(process.env.PORT||3000,'0.0.0.0');
