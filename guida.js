"use strict";
(function(){
 const pages=[{"path":"/la-fatica.html","label":"Che cosa mi sta pesando?"},{"path":"/la-comunita.html","label":"Con chi potrei sentirmi a casa?"},{"path":"/don-marco.html","label":"Chi potrebbe accompagnarmi?"},{"path":"/il-percorso.html","label":"Da dove potrei cominciare?"}];
 const current=pages.findIndex(x=>x.path===window.location.pathname);
 if(current<0)return;
 const raw=Number.parseInt(new URLSearchParams(window.location.search).get("inizio")||"",10);
 const origin=raw>=1&&raw<=4?raw-1:current;
 const n=((current-origin+4)%4)+1;
 const marker=document.querySelector(".chapter-bar .start");
 if(marker)marker.textContent="IL TUO PERCORSO · TAPPA "+n+" DI 4";
 const progress=document.querySelector(".chapter-bar .steps");
 if(progress){
  progress.replaceChildren();
  for(let j=0;j<4;j++){
   const idx=(origin+j)%4,el=document.createElement("a");
   el.href=pages[idx].path+"?inizio="+(origin+1);
   el.title="Tappa "+(j+1)+": "+pages[idx].label;
   el.setAttribute("aria-label",el.title);
   if(j+1<n)el.classList.add("completed");
   if(j+1===n)el.setAttribute("aria-current","step");
   progress.append(el);
  }
 }
 const finish=n===4,after=pages[(current+1)%4];
 const destination=finish?"mailto:marcobaleani@gmail.com?subject=Vorrei%20parlarne":after.path+"?inizio="+(origin+1);
 const next=document.querySelector(".next-chapter");
 if(next){
  const hd=next.querySelector("h2"),desc=next.querySelector("p"),cta=next.querySelector("a.btn"),tag=next.querySelector(".eyebrow");
  if(hd)hd.textContent=finish?"Hai incontrato tutte le quattro domande. Vuoi parlarne con don Marco?":"La prossima domanda: «"+after.label+"»";
  if(desc)desc.textContent=finish?"Non devi raccontare tutto in una mail. Basta chiedere un primo colloquio.":"Un passo alla volta, senza dover leggere tutto in un'unica pagina.";
  if(cta){cta.href=destination;cta.textContent=finish?"Scrivi a don Marco ↗":"Vai alla domanda successiva ↗";}
  if(tag)tag.textContent=finish?"IL PRIMO CONTATTO":"TAPPA "+(n+1)+" DI 4";
 }
 const quick=document.querySelector(".guide-quick");
 if(quick){quick.href=destination;quick.textContent=finish?"Parla con don Marco ↗":"Prossima domanda ↗";}
})();
