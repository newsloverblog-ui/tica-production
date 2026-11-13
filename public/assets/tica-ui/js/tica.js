/* design script 02 */
(function(){
var EV=window.TICA_EVENTS;
/* Tica: categories, currency and fees come from the server (window.TICA_DATA). */
var CATS={},CATSVG={};(TICA_DATA.categories||[]).forEach(function(c){CATS[c.key]=c.name;if(c.svg)CATSVG[c.key]=c.svg});
EV.forEach(function(e){if(!CATS[e.cat])CATS[e.cat]=e.label});
function iconKey(k){var t=(k+' '+(CATS[k]||'')).toLowerCase();
  return /music|concert|show|gig|festival|comedy/.test(t)?'shows':/campus|school|student|youth|universit/.test(t)?'campus':/faith|church|worship|relig|praise|mosque/.test(t)?'faith':/party|night|club|wedding|celebrat/.test(t)?'party':/sport|fitness|run|football|game|marathon/.test(t)?'sport':/art|culture|film|food|fashion|market|design/.test(t)?'arts':'conf'}
var ICON={cal:'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
 clock:'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
 pin:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-7 7-12a7 7 0 0 0-14 0c0 5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>'};
var $=function(i){return document.getElementById(i)};
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function hydrateImages(root){
  if(!root)return;
  var page=root.closest&&root.closest('.page');if(page&&page.hidden)return;
  var images=[];if(root.matches&&root.matches('img[data-src]'))images.push(root);
  if(root.querySelectorAll)images=images.concat([].slice.call(root.querySelectorAll('img[data-src]')));
  images.forEach(function(img){var src=img.dataset.src;if(!src)return;img.src=src;img.removeAttribute('data-src')});
}
var imageRoot=document.getElementById('main');if(imageRoot&&'MutationObserver' in window)new MutationObserver(function(records){records.forEach(function(record){[].forEach.call(record.addedNodes,function(node){if(node.nodeType===1)hydrateImages(node)})})}).observe(imageRoot,{childList:true,subtree:true});
function naira(n){return n===0?'Free':window.TICA_MONEY(n)}
/* Tica: the booking fee percentage set by the admin. */
window.TICA_FEE_PCT=(TICA_DATA.currency&&TICA_DATA.currency.feePct!=null)?+TICA_DATA.currency.feePct:6;
[].forEach.call(document.querySelectorAll('[data-fee-pct]'),function(n){n.textContent=String(window.TICA_FEE_PCT).replace(/\.0+$/,'')+'%'});
window.TICA_MONEY=function(n){var c=window.TICA_CUR||TICA_DATA.currency||{},r=Math.round(n*100)/100,v=r.toLocaleString('en-NG',{minimumFractionDigits:r%1?2:0,maximumFractionDigits:2});return c.position==='right'?v+(c.symbol||''):(c.symbol||'₦')+v};
function meta(e){return e.time[0].toUpperCase()+e.time.slice(1)+', '+e.venue}
function bySlug(s){return EV.filter(function(e){return e.slug===s})[0]}
window.sameCity=function(e){return e.venue===e.city||!!e.city&&e.venue.toLowerCase().indexOf(String(e.city).toLowerCase())>-1};
var saved={};
(function(){var list=(TICA_DATA.account&&TICA_DATA.account.saved)||[];if(!TICA_DATA.user){try{list=JSON.parse(localStorage.getItem('tica_saved')||'[]')}catch(e){list=[]}}list.forEach(function(s){saved[s]=true})})();
function persistSaved(slug,on){
  if(TICA_DATA.user){var e=evAny?evAny(slug):bySlug(slug);if(e)api('/tica-api/wishlist',{event_id:e.id,saved:on}).catch(function(er){flash(er.message)});return}
  try{localStorage.setItem('tica_saved',JSON.stringify(Object.keys(saved).filter(function(k){return saved[k]})))}catch(e){}
}
function dstr(e){var M={Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][new Date(e.y||2026,M[e.mon],+e.day).getDay()]+', '+e.day+' '+e.mon}
var TD={'Regular':'General admission','VIP':'Fast-track entry and the VIP area','Table for six':'A reserved table for six guests','Day pass':'Entry for the whole day','Maker table':'Stall space for one maker','Runner':'Race entry, bib and finisher medal','Free entry':'Free, but you still need a ticket'};
function stockLeft(e){return e.tiers.reduce(function(s,t){return s+t[2]},0)}
function stockTotal(e){return e.tiers.reduce(function(s,t){return s+t[3]},0)}
function stockHtml(e){
  if(e.unlimited||e.hideStock)return '';
  var l=stockLeft(e),t=stockTotal(e),low=l>0&&l<=Math.max(20,t*.15),sold=Math.round((t-l)/t*100);
  return '<div class="bar'+(low?' low':'')+'" aria-hidden="true"><i style="width:'+sold+'%"></i></div><span class="stk-t'+(low?' low':'')+'">'+(l===0?'Sold out':(low?'Only ':'')+l.toLocaleString('en-NG')+(l===1?' ticket':' tickets')+' left')+'</span>';
}
function stockCls(e){var l=stockLeft(e),t=stockTotal(e);return l===0?' out':(l<=Math.max(20,t*.15)?' low':' calm')}
function stockBlock(e){if(e.unlimited||e.hideStock)return '';return '<div class="stk'+stockCls(e)+'" data-stock="'+e.slug+'">'+stockHtml(e)+'</div>'}
function refreshStock(){[].forEach.call(document.querySelectorAll('[data-stock]'),function(n){var e=bySlug(n.dataset.stock);if(!e)return;n.innerHTML=stockHtml(e);if(n.classList.contains('stk')&&n.id!=='spot-stock'){n.classList.remove('calm','low','out');n.classList.add(stockCls(e).trim())}})}
function eventTime(e){
  var M={Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11},t=/(\d+):(\d+)\s*(AM|PM)/.exec(e.time),h=0,mi=0;
  if(t){h=(+t[1]%12)+(t[3]==='PM'?12:0);mi=+t[2]}
  if(e.ts)return e.ts;
  return Date.UTC(e.y||2026,M[e.mon],+e.day,h-1,mi); /* Lagos is UTC+1 */
}
function cdFull(e){
  return '<div class="cdf" role="timer" data-cd="'+eventTime(e)+'" aria-label="Time until '+esc(e.name)+'"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.500"/><path d="M12 7.500V12l3 2"/></svg><span class="cd-l">Starts in</span><span class="cd-v"><b data-u="d">0</b>d <b data-u="h">00</b>h <b data-u="m">00</b>m <b data-u="s">00</b>s</span></div>';
}
function cdMini(e){return '<span class="cdm" data-cdm="'+eventTime(e)+'"></span>'}
function cdTick(){
  var now=Date.now(),p=function(n){return String(n).padStart(2,'0')};
  [].forEach.call(document.querySelectorAll('[data-cd]'),function(n){
    var ms=+n.dataset.cd-now;
    if(ms<=0){n.classList.add('over');n.innerHTML='<p>This event has started</p>';n.removeAttribute('data-cd');return}
    var s=Math.floor(ms/1000),q=function(u){return n.querySelector('[data-u="'+u+'"]')};
    q('d').textContent=Math.floor(s/86400);q('h').textContent=p(Math.floor(s/3600)%24);q('m').textContent=p(Math.floor(s/60)%60);q('s').textContent=p(s%60);
  });
  [].forEach.call(document.querySelectorAll('[data-hold]'),function(n){
    var ms=+n.dataset.hold-now;
    if(ms>0){var s=Math.ceil(ms/1000);n.textContent=Math.floor(s/60)+':'+String(s%60).padStart(2,'0');return}
    var slug=n.dataset.slug;HOLD=null;PENDING=null;[].forEach.call(document.querySelectorAll('dialog[open]'),function(dl){dl.close()});
    $('checkout-view').innerHTML='<div class="expired"><h1>Your ticket hold has ended</h1><p>We held your tickets for 10 minutes so others could still buy. Choose your tickets again to continue.</p><a class="btn btn-hot" href="/event/'+slug+'">Choose tickets again</a></div>';
  });
  [].forEach.call(document.querySelectorAll('[data-cdm]'),function(n){
    var ms=+n.dataset.cdm-now,dd=Math.floor(ms/86400000),hh=Math.floor(ms/3600000);
    n.textContent=ms<=0?'Started':dd>=2?dd+' days to go':dd===1?'Tomorrow':hh>=1?hh+(hh===1?' hour':' hours')+' to go':'Starting soon';
  });
}
setInterval(cdTick,1000);
function card(e){
  return '<article class="card'+(e.sold?' soldout':'')+'"><a class="cardlink" href="/event/'+e.slug+'" tabindex="-1" aria-hidden="true"><div class="flyer">'+e.art+'</div></a>'+
  '<div class="cardbody">'+(e.tag?'<span class="tag">'+e.tag+'</span>':'')+'<p class="cdate">'+dstr(e)+', '+e.time+'</p><h3><a href="/event/'+e.slug+'">'+esc(e.name)+'</a></h3><p class="meta">'+esc(e.venue)+(sameCity(e)?'':', '+esc(e.city))+'</p><p class="by">'+esc(e.by)+' '+(e.v?window.TICA_TICK:'')+'</p>'+
  stockBlock(e)+'<div class="buy">'+(e.sold?'<span class="off">Sold out</span>':'<div class="price">'+(e.tiers.length>1?'From ':'')+e.price+'</div>')+cdMini(e)+'<button class="save" type="button" data-save="'+e.slug+'" aria-pressed="'+(saved[e.slug]?'true':'false')+'" aria-label="Save '+esc(e.name)+'">'+window.TICA_HEART+'</button></div></div></article>';
}
function row(e){
  return '<a class="row'+(e.sold?' soldout':'')+'" href="/event/'+e.slug+'" data-slug="'+e.slug+'"><div><h3>'+esc(e.name)+'</h3><div class="line">'+ICON.cal+'<span>'+dstr(e)+', '+e.time+'</span></div><div class="line">'+ICON.pin+'<span>'+esc(e.venue)+(sameCity(e)?'':', '+esc(e.city))+'</span></div><div class="rowprice">'+(e.sold?'Sold out':e.price)+' '+cdMini(e)+'</div>'+stockBlock(e)+'</div><div class="flyer" aria-hidden="true">'+e.art+'</div></a>';
}

/* static fill */
[].forEach.call(document.querySelectorAll('.catselect'),function(s){
  s.innerHTML='<option value="all">All categories</option>'+Object.keys(CATS).map(function(k){return '<option value="'+k+'">'+esc(CATS[k])+'</option>'}).join('');
});

var CICON={shows:'<path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/>',campus:'<path d="M3 9l9-4 9 4-9 4-9-4z"/><path d="M7 11v5c3 2 7 2 10 0v-5"/>',faith:'<path d="M12 21c-4-3-6-6-6-9 0-3 3-6 6-9 3 3 6 6 6 9 0 3-2 6-6 9z"/>',conf:'<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v5M8 21h8"/>',party:'<path d="M5 3h14l-7 9-7-9z"/><path d="M12 12v8M8 21h8"/>',arts:'<circle cx="12" cy="12" r="9"/><circle cx="8.5" cy="10" r="1"/><circle cx="14" cy="8" r="1"/><circle cx="16" cy="13" r="1"/>',sport:'<circle cx="14" cy="5" r="2"/><path d="M5 20l4-6 3 2 2-5-4-2-3 3M14 11l3 3h3"/>'};
$('home-cats').innerHTML=Object.keys(CATS).map(function(k){return [k,EV.filter(function(e){return e.cat===k}).length]}).sort(function(a,b){return b[1]-a[1]}).slice(0,7).map(function(x){return x[0]}).map(function(k){
  var n=EV.filter(function(e){return e.cat===k}).length;
  return '<a class="catc" href="/discover" data-gocat="'+k+'"><i><svg viewBox="0 0 24 24" aria-hidden="true">'+(CATSVG[k]||CICON[iconKey(k)])+'</svg></i><b>'+esc(CATS[k])+'</b><small>'+n+(n===1?' event':' events')+'</small></a>';
}).join('');
/* spotlight */
var spotList=EV.filter(function(e){return e.featured&&!e.sold}).concat(EV.filter(function(e){return !e.featured&&!e.sold})).slice(0,3),spotI=0;
if(!spotList.length)spotList=EV.slice(0,3);
function spot(){
  var e=spotList[spotI];
  ['spot-flyer','spot-name','spot-meta'].forEach(function(i){var n=$(i);n.classList.remove('swap');void n.offsetWidth;n.classList.add('swap')});
  $('spot-flyer').innerHTML=e.art;$('spot-name').textContent=e.name;
  $('spot-meta').textContent=dstr(e)+', '+e.time+', '+e.venue+(sameCity(e)?'':', '+e.city);
  $('spot-price').textContent=(e.tiers.length>1?'From ':'')+e.price;
  $('spot-link').setAttribute('href','/event/'+e.slug);
  $('spot-cd').innerHTML=cdFull(e);$('spot-stock').dataset.stock=e.slug;$('spot-stock').innerHTML=stockHtml(e);cdTick();
  $('spot-count').textContent=(spotI+1)+' of '+spotList.length;
}
$('spot-prev').addEventListener('click',function(){spotI=(spotI+spotList.length-1)%spotList.length;spot()});
$('spot-next').addEventListener('click',function(){spotI=(spotI+1)%spotList.length;spot()});
if(spotList.length)spot();else document.querySelector('.spot-wrap').hidden=true;
if(spotList.length<2)[].forEach.call(document.querySelectorAll('.spot-nav'),function(n){n.style.display='none'});
/* popular tabs */
(function(){var now=new Date(),MN=['January','February','March','April','May','June','July','August','September','October','November','December'];
  function key(d){var m=d.getMonth()+1;return m===11?'nov':m===12?'dec':'m'+String(m).padStart(2,'0')}
  var tabs=document.querySelectorAll('.tab[data-tab="nov"],.tab[data-tab="dec"]');
  [now,new Date(now.getFullYear(),now.getMonth()+1,1)].forEach(function(d,k){var b=tabs[k];if(!b)return;b.dataset.tab=key(d);b.textContent=MN[d.getMonth()]});
  var chip=document.querySelector('.h-chips [data-hk="h-month"]');if(chip)chip.dataset.hv=key(now);
  var counts={};EV.forEach(function(e){if(e.mode!=='online')counts[e.city]=(counts[e.city]||0)+1});
  var top=Object.keys(counts).sort(function(a,b){return counts[b]-counts[a]})[0],cc=document.querySelector('.h-chips [data-hk="h-where"]');
  if(cc){if(top){cc.dataset.hv=top;cc.textContent='In '+top}else cc.hidden=true}
})();
function tabRender(t){
  var list=EV.filter(function(e){return t==='all'||(t==='free'?e.n===0:e.m===t)}).slice(0,8);
  $('home-wall').innerHTML=list.length?list.map(card).join(''):'<p class="h-empty">No events here yet.</p>';
  [].forEach.call(document.querySelectorAll('.tab'),function(b){b.setAttribute('aria-pressed',b.dataset.tab===t?'true':'false')});
}
[].forEach.call(document.querySelectorAll('.tab'),function(b){b.addEventListener('click',function(){tabRender(b.dataset.tab)})});
tabRender('all');
/* cities shuffle */
(function(){
  var seenCity={},rest=[];EV.forEach(function(e){if(e.mode!=='online'&&e.city&&!seenCity[e.city]){seenCity[e.city]=1;rest.push(e.city)}});
  ['Owerri','Port Harcourt','Abuja','Lagos','Enugu','Uyo','Aba','Calabar','Benin City'].forEach(function(c){if(!seenCity[c]){seenCity[c]=1;rest.push(c)}});
  var busy=function(c){return EV.filter(function(e){return e.city===c}).length};
  var head=rest.filter(busy).sort(function(a,b){return busy(b)-busy(a)}),tail=rest.filter(function(c){return !busy(c)});
  for(var a=tail.length-1;a>0;a--){var b=Math.floor(Math.random()*(a+1)),t=tail[a];tail[a]=tail[b];tail[b]=t}
  var pool=head.concat(tail),at=0,wrap=$('home-cities');
  var label=function(c){var n=EV.filter(function(e){return e.city===c}).length;return n?n+(n===1?' event':' events'):'Events coming soon'};
  wrap.innerHTML=[0,1,2].map(function(){return '<a class="city" href="/discover"><svg viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="48"/><circle cx="50" cy="50" r="34"/><circle cx="50" cy="50" r="20"/></svg><b></b><span></span></a>'}).join('');
  var tiles=[].slice.call(wrap.children);
  function fill(t,c){t.dataset.gocity=c;t.querySelector('b').textContent=c;t.querySelector('span').textContent=label(c);t.setAttribute('aria-label',c+', '+label(c))}
  tiles.forEach(function(t,i){fill(t,pool[i])});
  var reduced=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
  var paused=reduced,hover=false,seen=true;
  function shuffle(){
    at=(at+1)%pool.length;
    tiles.forEach(function(t,i){
      setTimeout(function(){
        t.classList.add('out');
        setTimeout(function(){fill(t,pool[(at+i)%pool.length]);t.classList.remove('out')},300);
      },i*130);
    });
  }
  setInterval(function(){if(!paused&&!hover&&seen&&!document.hidden&&wrap.offsetParent)shuffle()},3800);
  wrap.addEventListener('mouseenter',function(){hover=true});
  wrap.addEventListener('mouseleave',function(){hover=false});
  wrap.addEventListener('focusin',function(){hover=true});
  wrap.addEventListener('focusout',function(){hover=false});
  if('IntersectionObserver' in window)new IntersectionObserver(function(es){seen=es[0].isIntersecting},{threshold:.2}).observe(wrap);
  
  
})();
document.addEventListener('click',function(ev){
  var c=ev.target.closest('[data-gocity]');
  if(c){$('d-q').value='';['d-cat','d-price','d-month'].forEach(function(i){$(i).value='all'});$('d-where').value=c.dataset.gocity;filter()}
});
/* featured coverflow */
(function(){
  var list=EV.filter(function(e){return !e.sold}),i=Math.floor(list.length/2),n=list.length,stage=$('cf-stage');
  if(!n){$('cf').hidden=true;return}
  stage.innerHTML=list.map(function(e,k){
    return '<a class="cfs" href="/event/'+e.slug+'" data-k="'+k+'"><div class="flyer" aria-hidden="true">'+e.art+'</div><div class="cf-cap"><b>'+esc(e.name)+'</b><span>'+dstr(e)+', '+e.time+'</span><span class="cf-v">'+ICON.pin+esc(sameCity(e)?e.city:e.venue+', '+e.city)+'</span>'+cdMini(e)+'</div></a>';
  }).join('');
  var slides=[].slice.call(stage.children);
  function draw(){
    slides.forEach(function(s,k){
      var o=k-i;if(o>n/2)o-=n;if(o<-n/2)o+=n;
      var a=Math.abs(o);
      s.style.setProperty('--o',o);
      s.dataset.pos=a===0?'c':a===1?'s':a===2?'f':'x';
      s.setAttribute('tabindex',a===0?'0':'-1');
      s.setAttribute('aria-hidden',a===0?'false':'true');
    });
    $('cf-live').textContent='Event '+(i+1)+' of '+n+': '+list[i].name;
  }
  function move(d){i=(i+d+n)%n;draw()}
  $('cf-prev').addEventListener('click',function(){move(-1)});
  $('cf-next').addEventListener('click',function(){move(1)});
  slides.forEach(function(s,k){s.addEventListener('click',function(ev){
    if(k!==i){ev.preventDefault();ev.stopPropagation();i=k;draw()}
  })});
  var x=null;
  stage.addEventListener('touchstart',function(ev){x=ev.touches[0].clientX},{passive:true});
  stage.addEventListener('touchend',function(ev){if(x===null)return;var dx=ev.changedTouches[0].clientX-x;x=null;if(Math.abs(dx)>40)move(dx<0?1:-1)});
  $('cf').addEventListener('keydown',function(ev){if(ev.key==='ArrowLeft'){move(-1)}if(ev.key==='ArrowRight'){move(1)}});
  draw();
})();

$('rows').innerHTML=EV.map(row).join('');

/* discover filter */
function filter(){
  var q=$('d-q').value.trim().toLowerCase(),country=$('d-country').value,w=$('d-where').value,c=$('d-cat').value,p=$('d-price').value,m=$('d-month').value,day=$('d-day').value,n=0;
  EV.forEach(function(e){
    var ok=(!q||(e.name+' '+e.label+' '+e.by+' '+e.city).toLowerCase().indexOf(q)!==-1)&&(country==='Online'?e.mode==='online':(e.mode==='online'||!e.country||e.country.toLowerCase()===country.toLowerCase()))&&(w==='all'||e.city===w)&&(c==='all'||e.cat===c||!CATS[c]&&iconKey(e.cat)===c)&&(m==='all'||e.m===m)&&(day==='all'||String(e.day)===day)&&(p==='all'||(p==='free'&&e.n===0)||(p==='low'&&e.n>0&&e.n<5000)||(p==='high'&&e.n>=5000));
    document.querySelector('.row[data-slug="'+e.slug+'"]').hidden=!ok;if(ok)n++;
  });
  var srt=$('d-sort').value,box=$('rows');
  EV.slice().sort(function(a,b){return srt==='low'?a.n-b.n:srt==='high'?b.n-a.n:eventTime(a)-eventTime(b)}).forEach(function(e){box.appendChild(box.querySelector('.row[data-slug="'+e.slug+'"]'))});
  $('count').textContent=n===1?'1 event':n+' events';
  $('empty').hidden=n!==0;
}
['d-q','d-country','d-where','d-cat','d-price','d-date','d-sort'].forEach(function(i){$(i).addEventListener('input',filter)});$('d-submit').addEventListener('click',filter);
$('reset').addEventListener('click',function(){$('d-q').value='';$('d-country').value='Nigeria';$('d-date').value='';['d-where','d-cat','d-price','d-month','d-day'].forEach(function(i){$(i).value='all'});if(window.TICA_SYNC_DATE)window.TICA_SYNC_DATE('d','');filter()});
$('home-search').addEventListener('submit',function(e){
  e.preventDefault();
  $('d-country').value=$('h-country').value;$('d-date').value=$('h-date').value;if(window.TICA_SYNC_SEARCH)window.TICA_SYNC_SEARCH('d',$('h-country').value,$('h-date').value);$('d-where').value=$('h-where').value;$('d-cat').value=$('h-cat').value;$('d-q').value=$('h-q').value;
  $('d-price').value='all';filter();go('discover');
});
document.addEventListener('click',function(ev){
  var s=ev.target.closest('[data-save]');
  if(s){saved[s.dataset.save]=!saved[s.dataset.save];s.setAttribute('aria-pressed',saved[s.dataset.save]?'true':'false');persistSaved(s.dataset.save,!!saved[s.dataset.save]);return}
  var g=ev.target.closest('[data-gocat]');
  if(g){$('d-q').value='';['d-where','d-price','d-month'].forEach(function(i){$(i).value='all'});$('d-cat').value=g.dataset.gocat;filter()}
  var m=ev.target.closest('.menu nav a');
  if(m)m.closest('details').removeAttribute('open');
});

/* event page */
function flash(msg){var s=$('live-msg');s.textContent=msg;s.classList.add('show');clearTimeout(flash.t);flash.t=setTimeout(function(){s.classList.remove('show')},3200)}
function shareEvent(e){openShare(e)}
function shareUrl(e){return location.origin+'/event/'+e.slug}
function openShare(e){
  var url=shareUrl(e),txt=e.name+', '+dstr(e)+'. Get tickets on TICA',m=$('share');
  var enc=encodeURIComponent,U=enc(url),T=enc(txt);
  $('sh-title').textContent=e.name;$('sh-sub').textContent=dstr(e)+', '+(sameCity(e)?e.city:e.venue+', '+e.city);
  $('sh-url').value=url;
  var L={wa:'https://wa.me/?text='+enc(txt+' '+url),x:'https://twitter.com/intent/tweet?text='+T+'&url='+U,fb:'https://www.facebook.com/sharer/sharer.php?u='+U,tg:'https://t.me/share/url?url='+U+'&text='+T,li:'https://www.linkedin.com/sharing/share-offsite/?url='+U,em:'mailto:?subject='+enc(e.name)+'&body='+enc(txt+'\n'+url)};
  [].forEach.call(m.querySelectorAll('[data-net]'),function(a){a.href=L[a.dataset.net]});
  $('sh-more').hidden=!navigator.share;$('sh-more').onclick=function(){navigator.share({title:e.name,text:txt,url:url}).catch(function(){})};
  var q=qrcode(0,'M');q.addData(url);q.make();
  var n=q.getModuleCount(),cv=$('sh-qr'),px=Math.max(4,Math.floor(512/(n+8))),sz=(n+8)*px,c=cv.getContext('2d');
  cv.width=sz;cv.height=sz;c.fillStyle='#fff';c.fillRect(0,0,sz,sz);c.fillStyle='#0B2447';
  for(var r=0;r<n;r++)for(var k=0;k<n;k++)if(q.isDark(r,k))c.fillRect((k+4)*px,(r+4)*px,px,px);
  $('sh-dl').onclick=function(){var W=sz,H=sz+150,o=document.createElement('canvas');o.width=W;o.height=H;var g=o.getContext('2d');g.fillStyle='#fff';g.fillRect(0,0,W,H);g.drawImage(cv,0,0);g.fillStyle='#0B2447';g.textAlign='center';g.font='700 '+Math.round(W/16)+'px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif';g.fillText(e.name.length>30?e.name.slice(0,29)+'…':e.name,W/2,sz+48);g.font='500 '+Math.round(W/24)+'px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,sans-serif';g.fillStyle='#526079';g.fillText(dstr(e)+'  •  Scan for tickets',W/2,sz+96);g.fillStyle='#F97316';g.fillRect(W/2-30,sz+120,60,5);
    var a=document.createElement('a');a.href=o.toDataURL('image/png');a.download=e.slug+'-qr.png';document.body.appendChild(a);a.click();a.remove();flash('QR code downloaded')};
  $('sh-filenote').hidden=/^https?:/.test(location.protocol);
  shTab('links');m.showModal();
}
window.shTab=shTab;function shTab(t){[].forEach.call(document.querySelectorAll('.sh-tabs button'),function(b){b.setAttribute('aria-selected',b.dataset.t===t?'true':'false')});$('sh-p-links').hidden=t!=='links';$('sh-p-qr').hidden=t!=='qr'}
/* Organiser text arrives as clean HTML; keep paragraphs as direct children so .evc>p styles apply. */
function asParas(html){return /^\s*<(p|ul|ol|h3|h4)[\s>]/i.test(html)?html:'<p>'+html+'</p>'}
function tierInfo(e,i){return (e.tierInfo&&e.tierInfo[i])||{}}
function tierPrice(e,i){return Math.max(0,e.tiers[i][1]-(tierInfo(e,i).earlyBird||0))}
function tierMax(e,i){var t=e.tiers[i],inf=tierInfo(e,i),m=inf.unlimited?Infinity:t[2];return inf.maxBuy?Math.min(m,inf.maxBuy):m}
function discountOf(disc,net){if(!disc)return 0;var v=disc.type==='percentage'?net*disc.value/100:disc.value;return Math.round(Math.min(net,Math.max(0,v))*100)/100}
function discountLabel(disc){return disc.type==='percentage'?'Discount ('+disc.value+'%)':'Discount ('+disc.code+')'}
function api(url,body){
  return fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(body||{})})
    .then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok){var m=j.message||'Something went wrong. Please try again.';if(j.errors){var k=Object.keys(j.errors)[0];if(k)m=j.errors[k][0]}var er=new Error(m);er.data=j;er.status=r.status;throw er}return j})});
}
window.TICA_API=api;
/* Tica funnel analytics: page views and checkout steps, only with analytics consent. */
function track(name,props){try{var c=window.TICA_consent&&TICA_consent();if(!c||!c.a)return;fetch('/analytics/events',{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf},body:JSON.stringify({event:name,properties:props||{}})}).catch(function(){})}catch(e){}}
/* Approved organizer provider IDs are loaded only on that organizer's event page
   and only after analytics consent. Identifiers are data, never executable code. */
(function(){var loaded=false,providers=TICA_DATA.trackingProviders||[];if(!providers.length)return;
function script(src){var s=document.createElement('script');s.async=true;s.src=src;s.referrerPolicy='strict-origin-when-cross-origin';document.head.appendChild(s)}
function start(){if(loaded)return;var c=window.TICA_consent&&TICA_consent();if(!c||!c.a)return;loaded=true;providers.forEach(function(p){var id=String(p.id||'');if(!/^[A-Za-z0-9_-]{5,100}$/.test(id))return;
  if(p.provider==='meta'){window.fbq=window.fbq||function(){(window.fbq.callMethod?window.fbq.callMethod:window.fbq.queue).apply(window.fbq,arguments)};window.fbq.queue=window.fbq.queue||[];window.fbq.loaded=true;window.fbq.version='2.0';script('https://connect.facebook.net/en_US/fbevents.js');window.fbq('init',id);window.fbq('track','PageView')}
  if(p.provider==='google'){window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){window.dataLayer.push(arguments)};script('https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(id));window.gtag('js',new Date());window.gtag('config',id,{anonymize_ip:true})}
  if(p.provider==='tiktok'){window.ttq=window.ttq||[];window.ttq.push(['init',id]);window.ttq.push(['track','PageView']);script('https://analytics.tiktok.com/i18n/pixel/events.js?sdkid='+encodeURIComponent(id)+'&lib=ttq')}
})}
document.addEventListener('tica:consent',start);setTimeout(start,0)})();
/* Admin-approved campaign popup. Content is inserted with textContent and the
   URL is server-validated; it appears once per session after marketing consent. */
(function(){var p=TICA_DATA.promotionPopup,shown=false;if(!p)return;function show(){if(shown)return;var c=window.TICA_consent&&TICA_consent();if(!c||!c.m)return;try{if(sessionStorage.getItem('tica_promo_'+p.id))return;sessionStorage.setItem('tica_promo_'+p.id,'1')}catch(e){}shown=true;var d=document.createElement('dialog');d.className='tica-promo-popup';var x=document.createElement('button');x.type='button';x.className='tica-promo-x';x.setAttribute('aria-label','Close promotion');x.textContent='×';x.onclick=function(){d.close()};var body=document.createElement('div');body.className='tica-promo-copy';var tag=document.createElement('small');tag.textContent='Sponsored';var h=document.createElement('h2');h.textContent=p.headline;var copy=document.createElement('p');copy.textContent=p.body;var a=document.createElement('a');a.className='btn btn-hot';a.href=p.url;a.rel='sponsored noopener';a.textContent='View event';body.append(tag,h,copy,a);if(p.image){var img=document.createElement('img');img.src=p.image;img.alt='';d.appendChild(img)}d.append(x,body);document.body.appendChild(d);d.addEventListener('click',function(e){if(e.target===d)d.close()});d.addEventListener('close',function(){d.remove()});d.showModal()}
document.addEventListener('tica:consent',function(){setTimeout(show,1200)});setTimeout(show,2200)})();
function renderEvent(e){
  window.TICA_CUR=e.cur||null;
  var pend=PENDING&&PENDING.slug===e.slug?PENDING:null;
  var qty=pend?pend.qty.map(function(q,i){return Math.min(q,tierMax(e,i))}):e.tiers.map(function(){return 0}),disc=pend?pend.disc:null,when=pend&&pend.date||(e.dates&&e.dates[0]?e.dates[0].value:'');
  var from=e.tiers.length>1?'Tickets from '+naira(Math.min.apply(null,e.tiers.map(function(t,i){return tierPrice(e,i)}))):(e.n?'Tickets '+naira(tierPrice(e,0)):'Free entry');
  var more=EV.filter(function(x){return x!==e&&!x.sold}).slice(0,4);
  var closed=e.regMode!=='corporate'&&(!e.bookable||!e.tiers.length||e.visibility==='private');
  var closedMsg=e.visibility==='private'?'This event is by invitation only. Your invitation email has your link to RSVP.':e.lifecycle==='cancelled'||e.lifecycle==='cancellation_pending'?'This event has been cancelled.':!e.tiers.length&&e.bookable?'Tickets aren\'t on sale yet.':'Ticket sales for this event have closed.';
  var panel=e.regMode==='corporate'?'<div class="tp"><h2>Registration</h2><p class="tp-sold">This event takes registrations instead of ticket sales.</p><a class="btn btn-hot tp-go" href="'+esc(e.regUrl)+'">Register for this event</a></div>':
  closed?'<div class="tp"><h2>Tickets</h2><p class="tp-sold">'+closedMsg+'</p><a class="btn btn-line tp-go" href="/discover">Find another event</a></div>':
  e.sold&&!e.waitlist?'<div class="tp"><h2>Tickets</h2><p class="tp-sold">This event is sold out.</p><a class="btn btn-line tp-go" href="/discover">Find another event</a></div>':
  e.sold?'<div class="tp"><h2>Tickets</h2><p class="tp-sold">This event is sold out.</p><form class="wl" id="wl" novalidate><label for="wl-e">Join the waitlist and we\'ll email you if tickets come back.</label><div><input id="wl-e" type="email" autocomplete="off" placeholder="Your email" value="'+esc(window.TICA_USER&&TICA_USER.email||'')+'"><button class="btn btn-hot" type="submit">Join</button></div><p class="hint" id="wl-m" role="status"></p></form><a class="btn btn-line tp-go" href="/discover">Find another event</a></div>':
  '<div class="tp" id="tp">'+(e.countdown?'<div class="tp-cd">'+cdFull(e)+(e.time.indexOf('confirmed')>-1?'<small>Counting to the date. The start time is still to be confirmed.</small>':'')+'</div>':'')+'<div class="tp-stock">'+stockBlock(e)+'</div>'+
  (e.dates&&e.dates.length>1?'<div class="inp tp-when"><label for="tp-date">Choose a date</label><select id="tp-date">'+e.dates.map(function(d){return '<option value="'+esc(d.value)+'"'+(d.value===when?' selected':'')+'>'+esc(d.label)+'</option>'}).join('')+'</select></div>':'')+
  '<h2>Choose your tickets</h2>'+e.tiers.map(function(t,i){var inf=tierInfo(e,i),p=tierPrice(e,i),low=!inf.unlimited&&t[2]>0&&t[2]<=Math.max(10,t[3]*.15),desc=inf.description||TD[t[0]];
    return '<div class="tp-row"><div><span>'+esc(t[0])+'</span>'+(desc?'<em class="tdesc">'+esc(desc)+'</em>':'')+'<b>'+naira(p)+(inf.earlyBird?'<small class="tfee">Early bird, was '+naira(t[1])+'</small>':'')+(p?(e.absorb?'<small class="tfee">Fees included</small>':'<small class="tfee">+ '+naira(feeFor(p,1))+' fee</small>'):'')+'</b>'+((inf.unlimited||e.hideStock)&&t[2]>0?'':'<small class="tleft'+(low?' low':'')+(t[2]===0?' out':'')+'">'+(t[2]===0?'Sold out':(low?'Only ':'')+t[2].toLocaleString('en-NG')+' left')+'</small>')+'</div>'+(t[2]===0?'':'<div class="stepper"><button type="button" data-d="-1" data-i="'+i+'" aria-label="Remove one '+esc(t[0])+' ticket">−</button><output id="tq'+i+'">'+qty[i]+'</output><button type="button" class="plus" data-d="1" data-i="'+i+'" aria-label="Add one '+esc(t[0])+' ticket">+</button></div>')+'</div>'}).join('')+
  '<div class="tp-lines" id="tp-lines"></div>'+
  (e.tiers.some(function(t){return t[1]>0})?'<form class="tp-code" id="tp-code"><label for="code">Discount code</label><div><input id="code" type="text" placeholder="Enter code" autocomplete="off" value="'+esc(disc?disc.code:'')+'"><button class="btn btn-line" type="submit">Apply</button></div><p class="hint" id="code-msg" role="status">'+(disc?esc(disc.code)+' applied.':'Enter a discount code if you have one.')+'</p></form>':'')+
  '<button class="btn btn-hot tp-go" type="button" id="tp-go" disabled>Continue</button></div>';
  var venueQuery=(e.venue.indexOf('confirmed')>-1?'':e.venue+', ')+(e.venue.indexOf(e.city)>-1?'':e.city+', ')+(e.country||'Nigeria');
  $('event-view').innerHTML='<div class="evb"><div class="evb-bg" aria-hidden="true"><div class="flyer">'+e.art+'</div></div><div class="evb-flyer'+(e.sold?' soldout':'')+'"><div class="flyer" role="img" aria-label="Flyer for '+esc(e.name)+'">'+e.art+'</div></div></div>'+
  '<div class="wrap evpage"><a class="evback" href="/discover" aria-label="Back to Discover events"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>'+
  '<div class="evcols2"><div class="evleft">'+
  '<div class="evtitle"><div>'+(e.tag?'<span class="tag">'+esc(e.tag)+'</span>':'')+'<h1>'+esc(e.name)+'</h1><p>'+(e.regMode==='corporate'?'Registration event':from)+'</p></div><button class="btn btn-line sharebtn" type="button" id="ev-share"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V4M8 8l4-4 4 4M5 13v6a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-6"/></svg>Share</button></div>'+
  (e.desc?'<section class="evc"><h2>About this event</h2>'+asParas(e.desc)+'</section>':'')+
  '<section class="evc"><h2>Date and time</h2><dl class="dt3"><div><dt>Date</dt><dd>'+dstr(e)+(e.dates&&e.dates.length>1?' and '+(e.dates.length-1)+' more':'')+'</dd></div><div><dt>Time</dt><dd>'+e.time[0].toUpperCase()+e.time.slice(1)+'</dd></div><div><dt>City</dt><dd>'+esc(e.city)+'</dd></div></dl></section>'+
  (e.mode==='online'?'<section class="evc"><h2>How to join</h2><div class="ev-online"><i><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/></svg></i><div><b>Online on '+esc(e.platform||'')+'</b><p>Your join link appears on your ticket and in your email 15 minutes before the start. Each ticket works on one device at a time.</p></div></div></section>':'<section class="evc"><h2>Venue</h2><p class="vn">'+esc(e.venue)+(sameCity(e)?'':', '+esc(e.city))+'</p><a class="map" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(e.lat&&e.lng?e.lat+','+e.lng:venueQuery)+'"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 23s8-7.5 8-13a8 8 0 0 0-16 0c0 5.500 8 13 8 13z"/><circle cx="12" cy="10" r="3" fill="#fff"/></svg><span>Open in Google Maps</span></a></section>'+(e.mode==='hybrid'?'<section class="evc"><h2>Watch online</h2><div class="ev-online"><i><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="12" rx="2"/><path d="M8 21h8M12 17v4"/></svg></i><div><b>Online on '+esc(e.platform||'')+'</b><p>Choose an online ticket to watch the live stream. Your join link appears on your ticket and in your email 15 minutes before the start. Each ticket works on one device at a time.</p></div></div></section>':''))+
  '<section class="evc"><h2>Good to know</h2><ul class="gtk">'+(e.age?'<li>'+esc(e.age)+'</li>':'')+'<li>Ticket sales close when the event starts.</li>'+(e.mode==='online'?'<li>Join from your ticket or the link in your email.</li>':'<li>Show the QR code on your e-ticket at the gate.</li>')+'<li>Full refund if the event is cancelled. If it moves, you can keep your ticket or ask for a refund. <a href="/policies/refunds">Refund policy</a></li></ul>'+(e.refund?asParas(e.refund):'')+'</section>'+
  '<section class="evc"><h2>Hosted by</h2><div class="host"><i>'+esc(e.by[0].toUpperCase())+'</i><span>'+esc(e.by)+'</span> '+(e.v?window.TICA_TICK:'')+'</div><p class="hostlinks"><a href="'+esc(e.organizerUrl||'/contact')+'">Contact the organiser</a><a class="rep" href="mailto:reports@tica.ng?subject='+encodeURIComponent('Report: '+e.name)+'">Report this event</a></p></section>'+
  '</div><aside class="evright">'+panel+'</aside></div>'+
  (more.length?'<div class="more"><h2 class="display">More events</h2><div class="grid4">'+more.map(card).join('')+'</div></div>':'')+'</div>'+
  (e.sold||closed||e.regMode==='corporate'?'':'<div class="buybar"><b id="bb-total">'+(e.tiers.length>1?'From ':'')+e.price+'</b><button class="btn btn-hot" type="button" id="bb-go">Choose tickets</button></div>');
  $('ev-share').addEventListener('click',function(){shareEvent(e)});
  track('view_event',{event_id:e.id});
  var wl=$('wl');if(wl)wl.addEventListener('submit',function(x){x.preventDefault();var v=$('wl-e').value.trim(),m=$('wl-m');
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)){m.textContent='Enter a valid email address.';return}
    m.textContent='Adding you to the list...';
    api('/tica-api/waitlist',{event_id:e.id,email:v}).then(function(r){m.textContent=r.message||'You\'re on the list. Check your inbox for confirmation.'},function(er){m.textContent=er.message})});
  if(e.sold||closed||e.regMode==='corporate')return;
  function sum(){return qty.reduce(function(s,q,i){return s+q*tierPrice(e,i)},0)}
  function cnt(){return qty.reduce(function(s,q){return s+q},0)}
  function upd(){
    var t=sum(),n=cnt(),off=discountOf(disc,t),lines='',pc=qty.reduce(function(s,q,i){return s+(tierPrice(e,i)>0?q:0)},0),f=e.absorb?0:feeFor(t-off,pc);
    qty.forEach(function(q,i){var o=$('tq'+i);if(o)o.textContent=q;if(q)lines+='<div class="sumline"><span>'+esc(e.tiers[i][0])+' x '+q+'</span><span>'+naira(q*tierPrice(e,i))+'</span></div>'});
    if(off)lines+='<div class="sumline"><span>'+esc(discountLabel(disc))+'</span><span>-'+naira(off)+'</span></div>';
    if(f)lines+='<div class="sumline"><span>Booking fee</span><span>'+naira(f)+'</span></div>';
    $('tp-lines').innerHTML=n?lines+'<div class="sumline sumtotal"><span>Amount due</span><span>'+naira(t-off+f)+'</span></div>':'';
    $('tp-go').disabled=!n;
    $('bb-total').textContent=n?naira(t-off+f):(e.tiers.length>1?'From ':'')+e.price;
    $('bb-go').textContent=n?'Continue':'Choose tickets';
  }
  var dsel=$('tp-date');if(dsel)dsel.addEventListener('change',function(){when=dsel.value});
  [].forEach.call($('tp').querySelectorAll('.stepper button'),function(b){b.addEventListener('click',function(){
    var i=+b.dataset.i,d=+b.dataset.d,inf=tierInfo(e,i);
    if(d>0&&qty[i]>=tierMax(e,i)){flash(inf.maxBuy&&qty[i]>=inf.maxBuy?'You can buy up to '+inf.maxBuy+' '+e.tiers[i][0]+' tickets':'Only '+e.tiers[i][2]+' '+e.tiers[i][0]+' left');return}
    if(d>0&&cnt()>=10){flash('You can buy up to 10 tickets per order');return}
    qty[i]=Math.max(0,qty[i]+d);upd();var o=$('tq'+i);o.classList.remove('bump');void o.offsetWidth;o.classList.add('bump');
  })});
  var cf=$('tp-code');if(cf)cf.addEventListener('submit',function(ev){
    ev.preventDefault();var v=$('code').value.trim(),msg=$('code-msg');
    if(!v){disc=null;msg.textContent='Enter a code first.';upd();return}
    msg.textContent='Checking '+v.toUpperCase()+'...';
    api('/tica-api/coupon',{event_id:e.id,code:v}).then(function(r){disc={code:r.code,type:r.type,value:+r.value};msg.textContent=r.code+' applied: '+(r.type==='percentage'?r.value+'% off.':naira(+r.value)+' off.');upd()},
      function(){disc=null;msg.textContent='That code isn\'t valid. Check it and try again.';upd()});
  });
  function cont(){track('select_ticket',{event_id:e.id,quantity:cnt()});PENDING={slug:e.slug,qty:qty.slice(),disc:disc,date:when,people:pend&&pend.people||[],submission:checkoutSubmissionId(e.slug,pend&&pend.submission)};go('checkout/'+e.slug)}
  $('tp-go').addEventListener('click',cont);
  $('bb-go').addEventListener('click',function(){if(cnt()){cont()}else{$('tp').scrollIntoView({block:'center'});var pl=$('tp').querySelector('.plus');if(pl)pl.focus({preventScroll:true})}});
  upd();
}

/* checkout: choose, details, pay */
var ORDER=null,PENDING=null;
var FEE_PCT=(TICA_DATA.currency&&TICA_DATA.currency.feePct!=null)?+TICA_DATA.currency.feePct:6,FEE_FLAT=0; /* Tica: booking fee % from admin settings */
function checkoutSubmissionId(slug,candidate){
  var valid=function(v){return /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(v||''))},key='tica_checkout_'+slug;
  if(valid(candidate))return candidate;
  try{var stored=sessionStorage.getItem(key);if(valid(stored))return stored}catch(e){}
  var id;
  if(window.crypto&&typeof window.crypto.randomUUID==='function')id=window.crypto.randomUUID();
  else{var bytes=new Uint8Array(16);if(window.crypto&&window.crypto.getRandomValues)window.crypto.getRandomValues(bytes);else for(var i=0;i<16;i++)bytes[i]=Math.floor(Math.random()*256);bytes[6]=(bytes[6]&15)|64;bytes[8]=(bytes[8]&63)|128;id=Array.prototype.map.call(bytes,function(b,i){return (i===4||i===6||i===8||i===10?'-':'')+b.toString(16).padStart(2,'0')}).join('')}
  try{sessionStorage.setItem(key,id)}catch(e){}
  return id;
}
function clearCheckoutSubmission(slug){try{sessionStorage.removeItem('tica_checkout_'+slug)}catch(e){}}
function feeFor(net,paidCount){return paidCount&&net>0?Math.round(net*FEE_PCT)/100+FEE_FLAT*paidCount:0}
function stepsBar(n){
  return '<ol class="steps">'+['Choose tickets','Your details','Pay','Get your tickets'].map(function(t,i){
    return '<li class="'+(i===n?'on':(i<n?'done':''))+'"'+(i===n?' aria-current="step"':'')+'>'+(i+1)+'. '+t+'</li>'}).join('')+'</ol>';
}
function renderCheckout(e){
  window.TICA_CUR=e.cur||null;
  var pend=PENDING&&PENDING.slug===e.slug?PENDING:null;
  if(pend&&(!HOLD||HOLD.slug!==e.slug||HOLD.end<Date.now()))HOLD={slug:e.slug,end:Date.now()+600000};
  var guestOk=!!(TICA_DATA.currency&&TICA_DATA.currency.guestCheckout);
  var S={login:(!guestOk&&!(window.TICA_USER&&TICA_USER.email))?'id':null,loginId:'',stage:pend?'details':'choose',qty:pend?pend.qty.slice():e.tiers.map(function(){return 0}),people:pend?pend.people.slice():[],open:0,err:{},disc:pend?pend.disc:null,date:pend&&pend.date||(e.dates&&e.dates[0]?e.dates[0].value:''),submission:checkoutSubmissionId(e.slug,pend&&pend.submission)};
  if(pend){S.people=S.people.slice(0,pend.qty.reduce(function(a,b){return a+b},0));S.open=S.people.filter(function(p){return p.done}).length;var _n=pend.qty.reduce(function(a,b){return a+b},0);if(S.open>=_n)S.open=_n-1}
  var view=$('checkout-view');
  /* Tica: free means the chosen tickets cost nothing, not that every ticket type is free. */
  function isFree(){var paidPick=S.qty.some(function(q,i){return q>0&&tierPrice(e,i)>0});return count()>0?!paidPick:e.tiers.every(function(t,i){return tierPrice(e,i)===0})}
  function slots(){var a=[];S.qty.forEach(function(q,i){for(var k=0;k<q;k++)a.push(e.tiers[i][0])});return a}
  function total(){return S.qty.reduce(function(s,q,i){return s+q*tierPrice(e,i)},0)}
  function count(){return S.qty.reduce(function(s,q){return s+q},0)}
  function net(){var t=total();return t-discountOf(S.disc,t)}
  function paid(){return S.qty.reduce(function(s,q,i){return s+(tierPrice(e,i)>0?q:0)},0)}
  function fee(){return e.absorb?0:feeFor(net(),paid())}
  function money(){var t=net()+fee();return naira(Math.round(t*100)/100)}
  function summary(){
    var lines=S.qty.map(function(q,i){return q?'<div class="sumline"><span>'+q+' x '+esc(e.tiers[i][0])+'</span><span>'+naira(q*tierPrice(e,i))+'</span></div>':''}).join('');
    var action='';
    if(S.stage==='choose')action='<button class="btn btn-hot" type="button" id="to-details"'+(count()?'':' disabled')+'>Continue</button>';
    if(S.stage==='pay')action='<button class="btn btn-hot" type="button" id="pay-now">'+(isFree()?(count()===1?'Get my free ticket':'Get my free tickets'):(count()===1?'Buy ticket':'Buy '+count()+' tickets'))+'</button><p class="paylegal">By continuing, you agree to TICA\'s <a href="/policies/tickets">terms</a> and <a href="/policies/refunds">refund policy</a>.</p>'+(isFree()?'':'<p class="secure"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg><span id="gw-secure">Payments are processed securely by '+esc(gwLabel())+'</span></p>');
    return '<aside class="summary">'+(S.stage!=='choose'&&HOLD?'<p class="hold" role="timer"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="8.500"/><path d="M12 7.500V12l3 2"/></svg>Tickets held for <b data-hold="'+HOLD.end+'" data-slug="'+e.slug+'">10:00</b></p>':'')+'<h2>Order summary</h2><p class="sumevent">'+esc(e.name)+'<br><span>'+dstr(e)+', '+esc(meta(e))+'</span></p>'+(lines||'<p class="hint">No tickets chosen yet.</p>')+
    (S.disc&&total()?'<div class="sumline"><span>'+esc(discountLabel(S.disc))+'</span><span>-'+naira(discountOf(S.disc,total()))+'</span></div>':'')+(isFree()?'':'<div class="sumline"><span>Booking fee</span><span>'+(e.absorb?'Included':naira(fee()))+'</span></div>')+'<div class="sumline sumtotal"><span>Total</span><span>'+money()+'</span></div>'+action+
    (S.stage==='details'?'<p class="hint">Complete every ticket form to continue to payment.</p>':'')+'</aside>';
  }
  function chooseHtml(){
    return '<h1 class="display">'+esc(e.name)+'</h1><p class="sub2">'+dstr(e)+', '+esc(meta(e))+'</p><h2>Choose your tickets</h2>'+
    e.tiers.map(function(t,i){if(t[2]===0)return '';return '<div class="tkt"><div><b>'+esc(t[0])+'</b><span>'+naira(tierPrice(e,i))+'</span></div><div class="stepper"><button type="button" data-d="-1" data-i="'+i+'" aria-label="Remove one '+esc(t[0])+' ticket">−</button><output>'+S.qty[i]+'</output><button type="button" data-d="1" data-i="'+i+'" aria-label="Add one '+esc(t[0])+' ticket">+</button></div></div>'}).join('');
  }
  function field(i,k,label,type,ac,hint){
    var id='p'+i+'-'+k,err=S.err[i]&&S.err[i][k],v=(S.people[i]&&S.people[i][k])||'';
    return '<div class="inp'+(err?' bad':'')+'"><label for="'+id+'">'+label+'</label><input id="'+id+'" name="'+k+'" type="'+type+'" autocomplete="'+ac+'" value="'+esc(v)+'"'+(err?' aria-invalid="true" aria-describedby="'+id+'-e"':'')+'>'+(err?'<p class="ferr" id="'+id+'-e">'+err+'</p>':(hint?'<p class="hint">'+hint+'</p>':''))+'</div>';
  }
  function acctHtml(){
    var u=window.TICA_USER;
    if(u&&u.email)return '<div class="acct in"><span class="acct-av" aria-hidden="true">'+esc((u.name||u.email)[0].toUpperCase())+'</span><div><b>Checking out as '+esc(u.name||u.email)+'</b><span>'+esc(u.email)+'. This order will be saved to your account.</span></div><button class="lk-btn" type="button" id="acct-out">Not you?</button></div>';
    var g=!S.login,codeStep=S.login==='code';
    var cards='<div class="acct-seg" role="radiogroup" aria-label="How do you want to check out?">'+
      '<button type="button" role="radio" aria-checked="'+g+'" data-pick="guest"'+(guestOk?'':' disabled aria-disabled="true"')+'><b>Continue as guest</b><small>'+(guestOk?'No account needed':'Log in to buy for this event')+'</small></button>'+
      '<button type="button" role="radio" aria-checked="'+(!g)+'" data-pick="login"><b>Log in</b><small>Faster, and tickets saved</small></button></div>';
    if(g)return '<div class="acct">'+cards+'<p class="acct-note">Your tickets are emailed to you. You can find them anytime under <a href="/tickets">My tickets</a> with your email and order number.</p></div>';
    return '<form class="acct" id="acct-login" novalidate>'+cards+'<div class="acct-otp"><b class="acct-otp-h">Log in with a one-time code</b>'+
      (codeStep?'<p class="acct-note top">We sent a 6-digit code to <b>'+esc(S.loginId)+'</b>. <button class="lk-btn sm" type="button" id="acct-change">Change</button></p><div class="inp"><label for="al-code">Code</label><input id="al-code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6 digits"></div>':
      '<div class="inp"><label for="al-id">Email or phone number</label><input id="al-id" type="text" autocomplete="username" placeholder="name@example.com or 0803 000 0000" value="'+esc(S.loginId||'')+'"></div>')+
      '<p class="ferr" id="al-err" role="alert"></p><div class="acct-row"><button class="btn btn-hot" type="submit">'+(codeStep?'Log in and continue':'Send code')+'</button>'+(codeStep?'<button class="lk-btn" type="button" id="acct-resend">Resend code</button>':'')+'</div>'+
      '<p class="acct-note">No password needed. Your tickets stay held while you log in.'+(codeStep?'':'')+'</p></div></form>';
  }
  function detailsHtml(){
    var sl=slots();
    return '<button class="backlink" type="button" id="back-choose">Back to tickets</button><h1 class="display">Who\'s attending?</h1><p class="sub2">Fill in one form for each ticket. '+(sl.length>1?'The next form opens when you finish this one.':'')+'</p>'+acctHtml()+(S.mode==='login'&&!(window.TICA_USER&&window.TICA_USER.email)?'':'<div class="stackforms">')+
    sl.map(function(tier,i){
      var head='Ticket '+(i+1)+' of '+sl.length+'<span>'+esc(tier)+(i===0?', buyer':'')+'</span>',p=S.people[i];
      if(i===S.open){
        var next=i<sl.length-1?'Continue to ticket '+(i+2):(isFree()?'Review my order':'Continue to payment');
        return '<form class="pform open" data-i="'+i+'" novalidate><h3>'+head+'</h3>'+
        (i===0?'<p class="hint top">We\'ll send all tickets and your receipt to this email.</p>':'')+
        field(i,'name','Full name','text','name')+
        '<div class="two">'+(i===0?field(i,'email','Email address','email','email')+field(i,'phone','Phone number','tel','tel'):field(i,'email','Email address (optional)','email','off','Leave blank to send this ticket to the buyer.')+'<div></div>')+'</div>'+
        (e.question?field(i,'answer',esc(e.question),'text','off'):'')+
        '<button class="btn btn-hot" type="submit">'+next+'</button></form>';
      }
      if(p&&p.done)return '<div class="pform done"><div><h3>'+head+'</h3><p>'+esc(p.name)+(p.email?', '+esc(p.email):', sent to buyer')+'</p></div><button class="editbtn" type="button" data-edit="'+i+'" aria-label="Edit ticket '+(i+1)+'">Edit</button></div>';
      return '<div class="pform later"><h3>'+head+'</h3></div>';
    }).join('')+'</div>';
  }
  function detailsWrap(){return detailsHtml()}
  var KNOWN=["ada.obi@gmail.com","ngozi.o@gmail.com","chidi.eze@yahoo.com","demo@tica.ng"];
  /* Tica: the gateways that can take this event's currency; the buyer picks one when there is a choice. */
  function gateways(){var cur=(e.cur&&e.cur.code)||(TICA_DATA.currency||{}).code||'NGN';return ((TICA_DATA.currency||{}).gateways||[]).filter(function(g){return !g.currencies||g.currencies.indexOf(cur)>-1})}
  function gwKey(){var gs=gateways();if(!gs.length)return null;if(!S.gw||!gs.some(function(g){return g.key===S.gw}))S.gw=gs[0].key;return S.gw}
  function gwLabel(){var k=gwKey(),g=gateways().filter(function(x){return x.key===k})[0];return g?g.label:'Our payment partner'}
  function gwHtml(){var gs=gateways();if(isFree()||gs.length<2)return '';
    return '<fieldset class="gwpick"><legend>How would you like to pay?</legend>'+gs.map(function(g){return '<label><input type="radio" name="gw" value="'+esc(g.key)+'"'+(g.key===gwKey()?' checked':'')+'><span><b>'+esc(g.label)+'</b><small>Card, bank transfer or USSD</small></span></label>'}).join('')+'</fieldset>'}
  function payHtml(){
    return '<button class="backlink" type="button" id="back-details">Back to details</button><h1 class="display">Review your order</h1><div class="review">'+
    S.people.map(function(p,i){return '<div><b>'+esc(p.name)+'</b><span>'+esc(slots()[i])+'</span><span>'+esc(p.email||S.people[0].email)+'</span></div>'}).join('')+'</div>'+
    gwHtml()+
    '<p class="hint" id="pay-msg" role="status"></p>'+
    '<dialog class="paymodal" id="paymodal" aria-labelledby="pm-title"><p class="pm-note">Secure payment</p><h2 id="pm-title">'+esc(gwLabel())+' opens here</h2><p>Pay securely with '+esc(gwLabel())+' using card, bank transfer or USSD.</p><dl><div><dt>Amount</dt><dd>'+money()+'</dd></div><div><dt>Receipt to</dt><dd>'+esc(S.people[0].email)+'</dd></div></dl><div class="pm-wait" role="status"><i></i>Confirming your payment</div><div class="pm-btns"><button class="btn btn-hot" type="button" id="pm-ok">Complete payment</button><button class="btn btn-line" type="button" id="pm-fail">Try another method</button><button class="btn btn-line" type="button" id="pm-cancel">Cancel</button></div></dialog>';
  }
  function prefill(){var u=window.TICA_USER;if(u&&u.email&&!(S.people[0]&&S.people[0].done)){S.people[0]=Object.assign({name:u.name||'',email:u.email,phone:(S.people[0]&&S.people[0].phone)||''},{fromUser:1})}}
  function draw(focusSel){
    var n={choose:0,details:1,pay:2}[S.stage];
    view.innerHTML=stepsBar(n)+'<div class="chgrid st-'+S.stage+'"><div class="panel">'+(S.stage==='choose'?chooseHtml():S.stage==='details'?detailsWrap():payHtml())+'</div>'+summary()+'</div>';
    bind();
    if(focusSel){var f=view.querySelector(focusSel);if(f)f.focus()}
  }
  /* Tica: hand the order to the server, then go to the chosen gateway or the confirmation page. */
  function place(onFail){
    var m=$('pay-msg');if(m){m.className='hint';m.textContent=''}
    api('/tica-api/checkout',{event_id:e.id,event_date:S.date||null,quantities:S.qty,coupon:S.disc?S.disc.code:null,submission_id:S.submission,
      buyer:{name:S.people[0].name,email:S.people[0].email,phone:S.people[0].phone},gateway:isFree()?null:gwKey(),
      attendees:S.people.map(function(p){return {name:p.name,email:p.email||'',answer:p.answer||''}})})
    .then(function(r){clearCheckoutSubmission(e.slug);HOLD=null;PENDING=null;location.href=r.redirect},function(er){
      onFail&&onFail();
      if(er.data&&er.data.login){S.stage='details';S.login='id';draw('#al-id');return}
      var mm=$('pay-msg');if(mm){mm.className='payerr';mm.textContent=er.message}else flash(er.message)});
  }
  function finish(){
    var ref='TICA-'+Date.now().toString().slice(-6);
    HOLD=null;S.qty.forEach(function(q,i){e.tiers[i][2]=Math.max(0,e.tiers[i][2]-q)});if(stockLeft(e)===0)e.sold=1;refreshStock();
    PENDING=null;ORDER={guest:!(window.TICA_USER&&window.TICA_USER.email),known:KNOWN.indexOf((S.people[0].email||'').toLowerCase())>-1,total:money(),slug:e.slug,ref:ref,free:isFree(),buyer:S.people[0].email,tickets:S.people.map(function(p,i){return{name:p.name,email:p.email||S.people[0].email,tier:slots()[i],code:ref+'-'+(i+1)}})};
    go('ticket/'+e.slug);
  }
  function bind(){
    view.querySelectorAll('.stepper button').forEach(function(b){b.addEventListener('click',function(){
      var i=+b.dataset.i,d=+b.dataset.d;if(d>0&&count()>=10)return;if(d>0&&S.qty[i]>=tierMax(e,i)){flash('No more '+e.tiers[i][0]+' tickets available');return}
      S.qty[i]=Math.max(0,S.qty[i]+d);draw('.stepper button[data-i="'+i+'"][data-d="'+d+'"]');
    })});
    var td=$('to-details');if(td)td.addEventListener('click',function(){
      var keep=S.people.slice(0,count());S.people=keep;S.open=0;
      for(var i=0;i<count();i++){if(!(S.people[i]&&S.people[i].done)){S.open=i;break}if(i===count()-1)S.open=i}
      S.stage='details';S.err={};prefill();draw('.pform.open input');window.scrollTo(0,0);
    });
    var ao=$('acct-out');if(ao)ao.addEventListener('click',function(){api('/tica-api/logout').then(function(r){if(r.csrf)TICA_DATA.csrf=r.csrf}).catch(function(){});window.TICA_USER=null;document.body.classList.remove('isuser');if(S.people[0]&&S.people[0].fromUser)S.people[0]=null;S.login=null;draw('.pform.open input')});
    view.querySelectorAll('[data-pick]').forEach(function(b){b.addEventListener('click',function(){if(b.dataset.pick==='guest'){S.login=null;draw('.pform.open input')}else if(!S.login){S.login='id';draw('#al-id')}})});
    var ach=$('acct-change');if(ach)ach.addEventListener('click',function(){S.login='id';draw('#al-id')});
    var ars=$('acct-resend');if(ars)ars.addEventListener('click',function(){api('/tica-api/login-code',{login:S.loginId}).then(function(){$('al-err').className='hint';$('al-err').textContent='New code sent.'},function(er){$('al-err').className='ferr';$('al-err').textContent=er.message})});
    var al=$('acct-login');if(al)al.addEventListener('submit',function(ev){ev.preventDefault();var er=$('al-err');er.className='ferr';
      if(S.login==='id'){var id=$('al-id').value.trim(),isEm=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(id),isPh=id.replace(/\D/g,'').length>=10;
        if(!isEm&&!isPh){er.textContent='Enter the email or phone number on your account.';$('al-id').focus();return}
        var sb=al.querySelector('[type=submit]');sb.disabled=true;
        api('/tica-api/login-code',{login:id}).then(function(){S.loginId=id;S.login='code';draw('#al-code')},function(x){sb.disabled=false;er.textContent=x.message});return}
      var c=$('al-code').value.trim();if(!/^\d{6}$/.test(c)){er.textContent='Enter the 6-digit code we sent you.';$('al-code').focus();return}
      var sb2=al.querySelector('[type=submit]');sb2.disabled=true;
      api('/tica-api/login-code/verify',{code:c}).then(function(r){
        if(r.csrf)TICA_DATA.csrf=r.csrf;var u=r.user,em=u.email,nm=u.name;
        window.TICA_USER=u;document.body.classList.add('isuser');S.login=null;if(S.people[0]&&!S.people[0].done){S.people[0].email=em;if(!S.people[0].name)S.people[0].name=nm}prefill();draw('.pform.open input')},
        function(x){sb2.disabled=false;er.textContent=x.status===422?'That code is wrong or has expired. Check it or ask for a new one.':x.message;$('al-code').focus()})});

    var bc=$('back-choose');if(bc)bc.addEventListener('click',function(){PENDING={slug:e.slug,qty:S.qty.slice(),disc:S.disc,date:S.date,people:S.people.slice(),submission:S.submission};go('event/'+e.slug)});
    var bd=$('back-details');if(bd)bd.addEventListener('click',function(){S.stage='details';S.open=S.people.length-1;draw('.pform.open input')});
    view.querySelectorAll('[data-edit]').forEach(function(b){b.addEventListener('click',function(){S.open=+b.dataset.edit;S.err={};draw('.pform.open input')})});
    view.querySelectorAll('form.pform').forEach(function(f){f.addEventListener('submit',function(ev){
      ev.preventDefault();
      var i=+f.dataset.i,g=function(k){var x=f.elements[k];return x?x.value.trim():''},p={name:g('name'),email:g('email'),phone:g('phone'),answer:g('answer')},er={};
      if(e.question&&!p.answer)er.answer='Answer the question for this ticket.';
      if(p.name.length<2)er.name='Enter this person\'s full name.';
      if((i===0||p.email)&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(p.email))er.email='Enter a valid email address, like name@example.com.';
      if(i===0&&p.phone.replace(/\D/g,'').length<10)er.phone='Enter a phone number with at least 10 digits.';
      S.people[i]=Object.assign(p,{done:!Object.keys(er).length});
      if(Object.keys(er).length){S.err={};S.err[i]=er;draw('.pform.open [aria-invalid=true]');return}
      S.err={};
      var nxt=-1;for(var k=0;k<count();k++){if(!(S.people[k]&&S.people[k].done)){nxt=k;break}}
      if(nxt===-1){S.stage='pay';draw('.backlink');window.scrollTo(0,0)}else{S.open=nxt;draw('.pform.open input')}
    })});
    [].forEach.call(document.querySelectorAll('.gwpick input'),function(r){r.addEventListener('change',function(){S.gw=r.value;var t=$('pm-title');if(t){t.textContent=gwLabel()+' opens here';t.nextElementSibling.textContent='Pay securely with '+gwLabel()+' using card, bank transfer or USSD.'};var sec=$('gw-secure');if(sec)sec.textContent='Payments are processed securely by '+gwLabel()})});
    var pn=$('pay-now');if(pn)pn.addEventListener('click',function(){
      if(isFree()){pn.disabled=true;place(function(){pn.disabled=false});return}
      var d=$('paymodal');d.showModal();
    });
    var ok=$('pm-ok');if(ok)ok.addEventListener('click',function(){$('paymodal').classList.add('busy');place(function(){$('paymodal').classList.remove('busy');$('paymodal').close()})});
    var fl=$('pm-fail');if(fl)fl.addEventListener('click',function(){$('paymodal').close();var m=$('pay-msg');m.className='payerr';m.textContent='Your payment didn\'t go through, and you haven\'t been charged. Your tickets are still held, so you can try again or use another payment method.'});
    var ca=$('pm-cancel');if(ca)ca.addEventListener('click',function(){$('paymodal').close();$('pay-msg').textContent='Payment cancelled. Your details are saved, so you can try again.'});
  }
  draw(S.stage==='details'?'.pform.open input':null);
}

/* tickets */
function qrCells(seed){
  var cells='',n=21;
  function fin(r,c,r0,c0){var y=r-r0,x=c-c0;if(y<0||x<0||y>6||x>6)return null;return y===0||x===0||y===6||x===6||(y>1&&y<5&&x>1&&x<5)}
  for(var r=0;r<n;r++)for(var c=0;c<n;c++){
    var on=fin(r,c,0,0);if(on===null)on=fin(r,c,0,14);if(on===null)on=fin(r,c,14,0);
    if(on===null){if(r===7&&(c<8||c>12)||c===7&&(r<8||r>12)||r===13&&c<8||c===13&&r<8)on=false;else{var v=Math.sin(r*12.9898+c*78.233+seed*37.7)*43758.5453;on=v-Math.floor(v)>.5}}
    cells+='<i style="background:'+(on?'#0B2447':'#fff')+'"></i>';
  }
  return cells;
}
/* Tica: a scannable QR for issued tickets, drawn in the design's .qr grid. */
function qrHtml(t,i){
  if(!t.qr)return '<div class="qr" aria-hidden="true">'+qrCells(i+1)+'</div>';
  var q=qrcode(0,'M');q.addData(t.qr);q.make();var n=q.getModuleCount(),cells='';
  for(var r=0;r<n;r++)for(var c=0;c<n;c++)cells+='<i style="background:'+(q.isDark(r,c)?'#0B2447':'#fff')+'"></i>';
  return '<div class="qr" role="img" aria-label="Ticket QR code" style="grid-template-columns:repeat('+n+',1fr)">'+cells+'</div>';
}
var TSTAT={checked_in:'Checked in',transferred:'Transferred',revoked:'Cancelled',refunded:'Refunded',transfer_pending:'Transfer pending'};
function ticketCard(e,t,i){
  return '<article class="eticket"><span class="samplebadge">Admit one</span><h2>'+esc(e.name)+'</h2><p>'+dstr(e)+', '+esc(meta(e))+'</p><div class="qr" aria-hidden="true">'+qrCells(i+1)+'</div><dl class="tmeta"><div><dt>Name</dt><dd>'+esc(t.name)+'</dd></div><div><dt>Ticket</dt><dd>'+esc(t.tier)+'</dd></div><div><dt>Code</dt><dd>'+esc(t.code)+'</dd></div></dl></article>';
}
/* order confirmation */
var ICO={qr:'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><path d="M14 14h2v2h-2zM18 18h2v2h-2zM14 19h1M19 14h1"/></svg>',
 dl:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M8 11l4 4 4-4M5 19h14"/></svg>',
 cal:'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>',
 sh:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V4M8 8l4-4 4 4M5 13v6a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-6"/></svg>'};
function addToCalendar(e){
  var M={Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11},p=function(n){return String(n).padStart(2,'0')},y=e.y||2026,m=M[e.mon],d=+e.day;
  var t=/(\d+):(\d+)\s*(AM|PM)/.exec(e.time),when;
  if(t){var h=(+t[1]%12)+(t[3]==='PM'?12:0);var end=new Date(y,m,d,h+3,+t[2]);when='DTSTART;TZID=Africa/Lagos:'+y+p(m+1)+p(d)+'T'+p(h)+t[2]+'00\r\nDTEND;TZID=Africa/Lagos:'+end.getFullYear()+p(end.getMonth()+1)+p(end.getDate())+'T'+p(end.getHours())+p(end.getMinutes())+'00'}
  else{when='DTSTART;VALUE=DATE:'+y+p(m+1)+p(d)}
  var ics=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//TICA//EN','BEGIN:VEVENT','UID:'+e.slug+'@tica','SUMMARY:'+e.name,'LOCATION:'+e.venue+(sameCity(e)?'':', '+e.city),when,'END:VEVENT','END:VCALENDAR'].join('\r\n');
  var a=document.createElement('a');a.href=URL.createObjectURL(new Blob([ics],{type:'text/calendar'}));a.download=e.slug+'.ics';document.body.appendChild(a);a.click();a.remove();
  flash('Calendar file downloaded');
}
function tixHtml(e,t,i,o){
  var num=String(i+1).padStart(2,'0'),openIt=i===0,sent=t.email!==o.buyer;
  return '<article class="tix"><header><div class="tix-t"><b>'+num+' Ticket</b><span>'+esc(t.tier)+', '+esc(t.name)+'</span></div><span class="status'+(sent||t.status&&t.status!=='issued'?' sent':'')+'">'+(t.status&&t.status!=='issued'?esc(TSTAT[t.status]||t.status):sent?'Sent to '+esc(t.email):'Active')+'</span>'+
  '<div class="tix-a"><button type="button" class="ic" data-toggle="pass'+i+'" aria-expanded="'+openIt+'" aria-controls="pass'+i+'" aria-label="'+(openIt?'Hide':'Show')+' QR code for ticket '+(i+1)+'">'+ICO.qr+'</button><button type="button" class="ic" data-print aria-label="Download ticket '+(i+1)+'">'+ICO.dl+'</button></div></header>'+
  '<div class="pass" id="pass'+i+'"'+(openIt?'':' hidden')+'>'+(e.mode==='online'?'<div class="tix-join"><b>Join on '+esc(e.platform)+'</b><p>'+(o.reveal===0?'Your link is ready below. ':'Your link opens here '+(o.reveal>=60?'1 hour':(o.reveal||15)+' minutes')+' before the start, on '+dstr(e)+' at '+esc(e.time)+'. ')+'It\'s also emailed to you.</p>'+(o.join?'<a class="btn btn-hot" href="'+esc(o.join)+'" target="_blank" rel="noopener">Join event</a>':'<a class="btn btn-hot" aria-disabled="true" role="link">Join event</a>')+'<small>'+esc(t.code)+'</small></div>':'<div class="pass-qr">'+qrHtml(t,i)+'<small>'+esc(t.code)+'</small></div>')+
  '<div class="pass-info"><span class="samplebadge">Admit one</span><h3>'+esc(e.name)+'</h3><dl><div><dt>Name</dt><dd>'+esc(t.name)+'</dd></div><div><dt>Ticket</dt><dd>'+esc(t.tier)+'</dd></div><div><dt>Date</dt><dd>'+dstr(e)+'</dd></div><div><dt>Time</dt><dd>'+esc(e.time[0].toUpperCase()+e.time.slice(1))+'</dd></div><div class="wide"><dt>Venue</dt><dd>'+esc(e.venue)+(sameCity(e)?'':', '+esc(e.city))+'</dd></div></dl></div></div></article>';
}
function renderTicket(e){
  window.TICA_CUR=e.cur||null;
  if(TICA_DATA.order&&!ORDER)ORDER=Object.assign({slug:e.slug},TICA_DATA.order);
  var real=ORDER&&ORDER.slug===e.slug;
  var o=real?ORDER:{ref:'TICA-000000',buyer:'you@example.com',tickets:[{name:'Your name',email:'you@example.com',tier:e.tiers[0][0],code:'TICA-000000-1'},{name:'Your guest',email:'guest@example.com',tier:e.tiers[0][0],code:'TICA-000000-2'}]};
  var first=o.tickets[0].name.split(' ')[0];

  $('ticket-view').innerHTML=stepsBar(3)+'<div class="thanks">'+(real?'<div class="tickok" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>':'')+
  '<h1>'+(real?'Thank you, '+esc(first)+'.<br>You\'re attending <b>'+esc(e.name)+'</b>.':'This is how an order confirmation looks.')+'</h1>'+
  '<p>'+(real?'Your confirmation and '+(o.tickets.length===1?'ticket have':'tickets have')+' been sent to <a href="mailto:'+esc(o.buyer)+'">'+esc(o.buyer)+'</a>.'+(o.guest&&o.known?' We\'ve also added this order to your TICA account.':''):'Buy a ticket from any event page to see it with your own details.')+'</p>'+
  (real?'<dl class="thx-meta"><div><dt>Order</dt><dd>#'+esc(o.ref)+'</dd></div><div><dt>'+(o.free?'Price':'Total paid')+'</dt><dd>'+(o.free?'Free':esc(o.total))+'</dd></div><div><dt>Tickets</dt><dd>'+o.tickets.length+'</dd></div></dl><p class="hint">A confirmation email has been sent.</p>':'')+(real&&o.guest&&!o.known?'<div class="savecard" id="savecard"><div><b>Save these tickets to an account</b><span>Find every ticket in one place and check out faster next time. We\'ll send a link to '+esc(o.buyer)+' to finish, no password needed now.</span></div><button class="btn btn-line" type="button" id="save-acct">Save to an account</button></div>':'')+'</div>'+
  '<div class="ordercard"><div class="ocevent"><div class="oc-flyer"><div class="flyer" aria-hidden="true">'+e.art+'</div></div><div class="oc-info"><span class="cat">'+esc(CATS[e.cat]||e.label)+'</span><h2>'+esc(e.name)+'</h2><p class="oc-v">'+ICON.pin+esc(e.venue)+(sameCity(e)?'':', '+esc(e.city))+'</p><dl><div><dt>Date</dt><dd>'+dstr(e)+'</dd></div><div><dt>Time</dt><dd>'+esc(e.time[0].toUpperCase()+e.time.slice(1))+'</dd></div></dl></div></div>'+
  '<div class="tixlist">'+o.tickets.map(function(t,i){return tixHtml(e,t,i,o)}).join('')+'</div></div>'+
  '<div class="oc-btns"><button class="btn btn-hot" type="button" data-print>'+ICO.dl+'Download tickets (PDF)</button><button class="btn btn-line" type="button" id="oc-cal">'+ICO.cal+'Add to calendar</button><button class="btn btn-line" type="button" id="oc-share">'+ICO.sh+'Share event</button></div>'+
  '<p class="oc-help">Need help with your order? <a href="/contact">Contact support</a></p>';
  $('oc-cal').addEventListener('click',function(){addToCalendar(e)});
  var sa=$('save-acct');if(sa)sa.addEventListener('click',function(){$('savecard').innerHTML='<div><b>Check your email</b><span>We sent a link to '+esc(o.buyer)+'. Open it to finish creating your account.</span></div>';$('savecard').classList.add('ok')});
  $('oc-share').addEventListener('click',function(){shareEvent(e)});
  [].forEach.call($('ticket-view').querySelectorAll('[data-print]'),function(b){b.addEventListener('click',function(){window.print()})});
  [].forEach.call($('ticket-view').querySelectorAll('[data-toggle]'),function(b){b.addEventListener('click',function(){
    var p=$(b.dataset.toggle),show=p.hidden;p.hidden=!show;b.setAttribute('aria-expanded',show?'true':'false');
    b.setAttribute('aria-label',b.getAttribute('aria-label').replace(show?'Show':'Hide',show?'Hide':'Show'));
  })});
}

/* create preview */
var MON=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
function prev(){
  var name=$('c-name').value.trim(),d=$('c-date').value,t=$('c-time').value,v=$('c-venue').value.trim(),c=$('c-city').value.trim(),p=parseInt($('c-price').value,10);
  $('p-name').textContent=name||'Your event name';
  if(d){var a=d.split('-');$('p-day').textContent=+a[2];$('p-mon').textContent=MON[+a[1]-1]}else{$('p-day').textContent='--';$('p-mon').textContent='Date'}
  var bits=[t,v,c].filter(Boolean);$('p-meta').textContent=bits.join(', ')||'Time and venue';
  /* price shows only once the organiser has typed one */
  var pp=$('p-price');pp.hidden=isNaN(p);pp.textContent=isNaN(p)?'':p>0?naira(p):'Free';
  var art=$('p-art');if(art)art.textContent=name||'Your flyer here';
}
['c-name','c-date','c-time','c-venue','c-city','c-price'].forEach(function(i){$(i).addEventListener('input',prev)});
$('c-flyer').addEventListener('change',function(){
  var f=this.files[0];if(!f)return;
  $('p-flyer').innerHTML='';var img=new Image();img.alt='Your flyer';img.src=URL.createObjectURL(f);$('p-flyer').appendChild(img);
});

/* router */
/* v18: dates, my tickets, dashboard */
var HOLD=null;
function renderDash(){if(window.TICA_renderDash)TICA_renderDash()}
document.addEventListener('click',function(ev){
  var b=ev.target.closest('.db-side [data-go]');
  if(b){[].forEach.call(document.querySelectorAll('.db-side [data-go]'),function(x){x.classList.toggle('on',x===b)});var t=$(b.dataset.go);if(t)t.scrollIntoView({behavior:'smooth',block:'start'})}
  
});
$('mt-form').addEventListener('submit',function(ev){
  ev.preventDefault();
  var em=$('mt-email').value.trim(),rf=$('mt-ref').value.trim().toUpperCase().replace(/^#/,''),ok=true,msg=$('mt-msg');
  function er(id,m){var e=$(id+'-e'),i=$(id);e.textContent=m||'';e.hidden=!m;if(m){i.setAttribute('aria-invalid','true');ok=false}else i.removeAttribute('aria-invalid')}
  er('mt-email',/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)?'':'Enter the email address you used to buy.');
  er('mt-ref',/^TICA-\d{6,}$/.test(rf)?'':'Enter your order number, like TICA-123456.');
  msg.hidden=true;
  if(!ok){var f=document.querySelector('#mt-form [aria-invalid=true]');if(f)f.focus();return}
  var sb=$('mt-form').querySelector('[type=submit]');sb.disabled=true;
  api('/tica-api/order-lookup',{email:em,ref:rf}).then(function(r){location.href=r.redirect},function(x){sb.disabled=false;msg.hidden=false;msg.textContent=x.message});
});

/* v19: attendee account and gate check-in */
var acTab='up';
var ACC=TICA_DATA.account||{orders:[],events:[],saved:[]};
function evAny(s){return bySlug(s)||(ACC.events||[]).filter(function(e){return e.slug===s})[0]}
var acPanel='overview';
function acOrders(){return (ACC.orders||[]).map(function(o){return Object.assign({},o,{e:evAny(o.slug)})}).filter(function(o){return o.e})}
function acMoney(o){var v=Math.round(o.total*100)/100;return (o.symbol||'')+v.toLocaleString('en-NG',{minimumFractionDigits:v%1?2:0,maximumFractionDigits:2})}
function acIcon(d){return '<svg viewBox="0 0 24 24" aria-hidden="true">'+d+'</svg>'}
function acShow(p,focus){acPanel=['overview','tickets','orders','saved','profile','help'].indexOf(p)>-1?p:'overview';
  [].forEach.call(document.querySelectorAll('.acd-p'),function(x){x.hidden=x.dataset.p!==acPanel});
  [].forEach.call(document.querySelectorAll('.acd-nav button'),function(b){var on=b.dataset.acp===acPanel;b.classList.toggle('on',on);if(on)b.setAttribute('aria-current','page');else b.removeAttribute('aria-current')});
  try{history.replaceState(null,'',location.pathname+(acPanel==='overview'?'':'#'+acPanel))}catch(e){}
  if(focus){var h=document.querySelector('.acd-p[data-p="'+acPanel+'"] h1');if(h){h.setAttribute('tabindex','-1');h.focus({preventScroll:true})}window.scrollTo({top:0,behavior:'instant'})}
}
function acTicketRow(o,past){var e=o.e;
  if(past)return '<li class="ac-o past"><div class="ac-f flyer">'+e.art+'</div><div class="ac-i"><span class="ac-when">'+dstr(e)+'</span><h3>'+esc(e.name)+'</h3><p>'+esc(sameCity(e)?e.venue:e.venue+', '+e.city)+'</p><p class="ac-n">'+o.n+(o.n===1?' ticket':' tickets')+(o.attended?', attended':'')+'</p></div><div class="ac-a"><a class="btn btn-line" href="/discover">Find similar events</a></div></li>';
  return '<li class="ac-o"><a class="ac-f flyer" href="/event/'+e.slug+'" tabindex="-1" aria-hidden="true">'+e.art+'</a><div class="ac-i"><span class="ac-when">'+dstr(e)+', '+esc(e.time)+'</span><h3>'+esc(e.name)+'</h3><p>'+esc(sameCity(e)?e.venue:e.venue+', '+e.city)+'</p><p class="ac-n">'+o.n+(o.n===1?' ticket':' tickets')+' <span>Order #'+esc(o.ref)+'</span>'+cdMini(e)+'</p></div><div class="ac-a"><a class="btn btn-hot" href="'+esc(o.url)+'">View tickets</a><button class="btn btn-line" type="button" data-addcal="'+e.slug+'">Add to calendar</button></div></li>';
}
function renderAccount(){
  var u=window.TICA_USER||{},pf=ACC.profile||{},links=ACC.links||{},now=Date.now(),first=(u.name||pf.fname||'').split(' ')[0];
  var all=acOrders(),up=all.filter(function(o){return eventTime(o.e)>=now}).sort(function(a,b){return eventTime(a.e)-eventTime(b.e)}),past=all.filter(function(o){return eventTime(o.e)<now}).sort(function(a,b){return eventTime(b.e)-eventTime(a.e)});
  var savedList=Object.keys(saved).filter(function(k){return saved[k]}).map(evAny).filter(Boolean);
  var h=new Date().getHours();
  $('acd-name').textContent=u.name||'Your account';$('acd-since').textContent=pf.since?'Member since '+pf.since:(u.email||'');
  $('acd-av').textContent=((u.name||'T').split(/\s+/).slice(0,2).map(function(w){return w.charAt(0)}).join('')||'T').toUpperCase();
  $('ac-hi').textContent=(h<12?'Good morning':h<17?'Good afternoon':'Good evening')+(first?', '+first:'');
  $('acd-today').textContent=up.length?'You have '+up.reduce(function(a,o){return a+o.n},0)+(up.reduce(function(a,o){return a+o.n},0)===1?' ticket':' tickets')+' for '+up.length+(up.length===1?' upcoming event.':' upcoming events.'):'No upcoming tickets yet. Find something good to go to.';
  /* next event */
  var nx=up[0],box=$('acd-next');
  if(nx){var e=nx.e;box.hidden=false;box.innerHTML='<a class="acd-next-f flyer" href="/event/'+e.slug+'" tabindex="-1" aria-hidden="true">'+e.art+'</a><div class="acd-next-b"><p class="acd-k">Your next event</p><h2>'+esc(e.name)+'</h2><p class="acd-next-m">'+acIcon('<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>')+dstr(e)+', '+esc(e.time)+'</p><p class="acd-next-m">'+acIcon('<path d="M12 22s7-6.5 7-12a7 7 0 0 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>')+esc(sameCity(e)?e.venue:e.venue+', '+e.city)+'</p><p class="acd-next-c">'+nx.n+(nx.n===1?' ticket':' tickets')+cdMini(e)+'</p><div class="acd-next-a"><a class="btn acd-primary" href="'+esc(nx.url)+'">Show my ticket</a><button class="btn btn-line" type="button" data-addcal="'+e.slug+'">Add to calendar</button>'+(e.mode==='online'?'':'<a class="btn btn-line" href="https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(e.venue+', '+e.city)+'" target="_blank" rel="noopener">Directions</a>')+'</div></div>'}
  else{box.innerHTML='<div class="acd-next-empty"><p class="acd-k">Nothing booked yet</p><h2>Find your next night out</h2><p>Concerts, campus nights, conferences and more. Your tickets will show up here.</p><a class="btn acd-primary" href="/discover">Discover events</a></div>'}
  /* stats */
  var ticketsUp=up.reduce(function(a,o){return a+o.n},0),attended=past.filter(function(o){return o.attended}).length;
  $('acd-stats').innerHTML=[['Upcoming tickets',ticketsUp,'<path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3zM14 8v2M14 13v1M14 16v1"/>','tickets'],['Events attended',attended,'<path d="M5 12.5l4.5 4.5L19 7.5"/>','tickets'],['Saved events',savedList.length,'<path d="M12 20.5s-8-4.9-8-11A4.5 4.5 0 0 1 12 6.7a4.5 4.5 0 0 1 8 2.8c0 6.1-8 11-8 11z"/>','saved'],['Orders',(ACC.orders||[]).length,'<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>','orders']].map(function(x){return '<button type="button" class="acd-stat" data-acp="'+x[3]+'"><i class="acd-ic">'+acIcon(x[2])+'</i><span>'+x[0]+'</span><b>'+x[1].toLocaleString('en-NG')+'</b></button>'}).join('');
  /* coming up */
  $('acd-up').innerHTML=up.length?up.slice(0,4).map(function(o){var e=o.e;return '<li><span class="acd-date"><b>'+esc(e.day)+'</b>'+esc(e.mon)+'</span><span class="acd-mini-b"><a href="'+esc(o.url)+'">'+esc(e.name)+'</a><small>'+esc(e.time)+' · '+esc(e.venue)+'</small></span><em>'+o.n+(o.n===1?' ticket':' tickets')+'</em></li>'}).join(''):'<li class="acd-none">Tickets you buy appear here, ready to show at the gate.</li>';
  /* events you may like: same categories or city as your orders, not already booked */
  var mine={},cats={},cities={};all.concat(savedList.map(function(e){return {e:e}})).forEach(function(o){mine[o.e.slug]=1;if(o.e.cat)cats[o.e.cat]=1;if(o.e.city)cities[o.e.city]=1});
  var recs=(window.TICA_EVENTS||[]).filter(function(e){return !e.sold&&!mine[e.slug]&&eventTime(e)>=now&&e.visibility!=='unlisted'}).map(function(e){return {e:e,s:(cats[e.cat]?2:0)+(cities[e.city]?1:0)+(e.featured?.5:0)}}).sort(function(a,b){return b.s-a.s||eventTime(a.e)-eventTime(b.e)}).slice(0,4).map(function(x){return x.e});
  $('acd-recs').innerHTML=recs.length?recs.map(card).join(''):'<p class="acd-none">New events appear here as they go on sale.</p>';
  /* my tickets */
  [].forEach.call(document.querySelectorAll('.ac-tabs button'),function(b){b.setAttribute('aria-pressed',b.dataset.tab===acTab?'true':'false')});
  var list=acTab==='past'?past:up;
  $('ac-list').innerHTML=list.length?'<ul class="ac-orders">'+list.map(function(o){return acTicketRow(o,acTab==='past')}).join('')+'</ul>':
    (acTab==='past'?'<div class="ac-empty"><h2>No past events yet</h2><p>Events you\'ve been to appear here.</p><a class="btn btn-hot" href="/discover">Discover events</a></div>':'<div class="ac-empty"><h2>No upcoming tickets</h2><p>Tickets you buy appear here, ready to show at the gate.</p><a class="btn btn-hot" href="/discover">Discover events</a></div>');
  /* orders */
  var ST={paid:['Paid','paid'],free:['Free','free'],refunded:['Refunded','ref']};
  $('acd-orders').innerHTML=all.length?'<ul class="acd-orders">'+all.map(function(o){var e=o.e,st=ST[o.status]||ST.paid;return '<li><div class="acd-o-ev"><span class="acd-date"><b>'+esc(e.day)+'</b>'+esc(e.mon)+'</span><div><b>'+esc(e.name)+'</b><small>Order #'+esc(o.ref)+(o.placed?' · placed '+esc(o.placed):'')+' · '+o.n+(o.n===1?' ticket':' tickets')+'</small></div></div><div class="acd-o-amt"><b>'+(o.status==='free'?'Free':acMoney(o))+'</b>'+(o.status==='free'?'':'<em class="acd-st '+st[1]+'">'+st[0]+'</em>')+'</div><div class="acd-o-act"><a class="btn btn-line" href="'+esc(o.url)+'">View tickets</a>'+(o.pdf?'<a class="btn btn-line" href="'+esc(o.pdf)+'">PDF</a>':'')+(o.invoice?'<a class="btn btn-line" href="'+esc(o.invoice)+'">Receipt</a>':'')+(o.resend&&o.status!=='refunded'?'<button class="btn btn-line" type="button" data-resend="'+esc(o.resend)+'">Email again</button>':'')+'</div></li>'}).join('')+'</ul>':
    '<div class="ac-empty"><h2>No orders yet</h2><p>When you buy tickets, your receipts and ticket PDFs appear here.</p><a class="btn btn-hot" href="/discover">Discover events</a></div>';
  /* saved */
  $('acd-saved').innerHTML=savedList.length?'<div class="grid4">'+savedList.map(card).join('')+'</div>':'<div class="ac-empty"><h2>No saved events yet</h2><p>Tap the heart on any event to keep it here.</p><a class="btn btn-hot" href="/discover">Discover events</a></div>';
  /* profile */
  var F=$('acd-profile');if(F&&!F.dataset.ready){F.dataset.ready='1';['fname','lname','email','phone','city','country','username'].forEach(function(k){var i=F.querySelector('[name='+k+']');if(i)i.value=pf[k]||''})}
  var ms=$('acd-mfa-state');if(ms){ms.textContent=pf.mfa?'On':'Off';ms.classList.toggle('on',!!pf.mfa)}if(links.security)$('acd-security').href=links.security;
  if(links.data)$('acd-data').href=links.data;
  if(links.support){var sp=$('acd-support');sp.href=links.support;sp.querySelector('b').textContent='Message support';sp.querySelector('small').textContent='Open a support ticket with the TICA team'}
  acShow(acPanel);
  cdTick();
}
(function(){var hash=(location.hash||'').slice(1);if(hash)acPanel=hash})();
window.addEventListener('hashchange',function(){if(document.querySelector('.page[data-page=account]:not([hidden])'))acShow((location.hash||'#overview').slice(1),true)});
function acSubmit(form,url,msgId,okText){
  var m=$(msgId);m.className='acd-msg';m.textContent='Saving…';
  var fd=new FormData(form);
  return fetch(url,{method:'POST',body:fd,credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest'}}).then(function(r){
    if(r.status===422)return r.json().then(function(j){var errs=j.errors||{};m.className='acd-msg bad';m.textContent=Object.keys(errs).map(function(k){return errs[k][0]}).join(' ')||j.message||'Check the details and try again.';return false});
    if(r.status===419||r.status===401){m.className='acd-msg bad';m.textContent='Your session ended. Log in again, then try once more.';return false}
    if(!r.ok){m.className='acd-msg bad';m.textContent='Something went wrong. Please try again.';return false}
    /* A successful save returns to this page; any other landing page means it was stopped (for example an unverified email). */
    if(r.redirected&&new URL(r.url).pathname!==location.pathname){m.className='acd-msg bad';m.textContent='We couldn\'t save this. Confirm your email address first, then try again.';return false}
    m.className='acd-msg ok';m.textContent=okText;return true;
  },function(){m.className='acd-msg bad';m.textContent='No connection. Check the internet and try again.';return false});
}
document.addEventListener('submit',function(ev){
  var f=ev.target;
  if(f.id==='acd-profile'){ev.preventDefault();var L=(ACC.links||{});acSubmit(f,L.profile,'pf-msg','Saved. Your receipts and tickets use these details.').then(function(ok){if(!ok)return;var pf=ACC.profile||(ACC.profile={});['fname','lname','email','phone','city','country','username'].forEach(function(k){pf[k]=f.querySelector('[name='+k+']').value.trim()});window.TICA_USER=Object.assign(window.TICA_USER||{},{name:(pf.fname+' '+pf.lname).trim(),email:pf.email});f.querySelector('[name=current_password]').value='';$('pf-cur-wrap').hidden=true;$('acd-name').textContent=window.TICA_USER.name})}
  if(f.id==='acd-pass'){ev.preventDefault();if(f.new_password.value!==f.new_password_confirmation.value){var m=$('pw-msg');m.className='acd-msg bad';m.textContent='The two new passwords don\'t match.';return}acSubmit(f,(ACC.links||{}).password,'pw-msg','Password changed.').then(function(ok){if(ok)f.reset()})}
});
document.addEventListener('input',function(ev){if(ev.target.id==='pf-email'){var pf=ACC.profile||{};$('pf-cur-wrap').hidden=ev.target.value.trim().toLowerCase()===(pf.email||'').toLowerCase()}});
document.addEventListener('click',function(ev){
  var t=ev.target.closest('.ac-tabs button');if(t){acTab=t.dataset.tab;renderAccount();return}
  var p=ev.target.closest('[data-acp]');if(p&&p.closest('.page[data-page=account]')){acShow(p.dataset.acp,true);return}
  var c=ev.target.closest('[data-addcal]');if(c)addToCalendar(evAny(c.dataset.addcal));
  var r=ev.target.closest('[data-resend]');if(r){r.disabled=true;r.textContent='Sending…';
    fetch(r.dataset.resend,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest'}}).then(function(res){r.textContent=res.ok?'Sent':'Try again';r.disabled=!res.ok?false:true;flash(res.ok?'Your tickets are on their way to '+((ACC.profile||{}).email||'your email')+'.':'We couldn\'t send them just now. Please try again.')},function(){r.disabled=false;r.textContent='Try again';flash('No connection. Please try again.')})}
});

/* Tica gate check-in: real scans through Tica's scanner, from this browser as an enrolled gate device. */
var CK={total:0,in:0,log:[]},CKE=null,CKCAM=null,CKLAST={};
var CKS={get:function(k){try{return localStorage.getItem(k)||''}catch(e){return ''}},set:function(k,v){try{localStorage.setItem(k,v)}catch(e){}},del:function(k){try{localStorage.removeItem(k)}catch(e){}}};
function ckTime(){var d=new Date();return d.toLocaleTimeString('en-NG',{hour:'numeric',minute:'2-digit'})}
function ckHeaders(){return {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest','X-Scanner-Device':CKS.get('tica_scanner_device_id'),'X-Scanner-Secret':CKS.get('tica_scanner_device_secret')}}
function ckFetch(url,opts){opts=opts||{};opts.credentials='same-origin';opts.headers=ckHeaders();return fetch(url,opts).then(function(r){return r.json().catch(function(){return {}}).then(function(j){j.__status=r.status;return j})})}
function ckEvents(){
  if(TICA_DATA.dashboard)return TICA_DATA.dashboard.events.filter(function(r){return r.state==='up'||r.state==='past'&&Date.now()-r.ts<2*864e5}).sort(function(a,b){return a.ts-b.ts}).map(function(r){return {id:r.id,label:r.name+', '+['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][new Date(r.ts).getDay()]+' '+r.day+' '+r.mon}});
  return (TICA_DATA.gate&&TICA_DATA.gate.events)||[];
}
/* The device credential is shared with Tica's own scanner page on this browser. */
function ckDevice(){
  if(CKS.get('tica_scanner_device_id')&&CKS.get('tica_scanner_device_secret'))return Promise.resolve(true);
  if(!window.TICA_ORG)return Promise.reject(new Error('This device isn\'t set up for scanning. Ask the event owner to open check-in on it once to set it up.'));
  return api('/organizer/scanner/devices',{name:'TICA gate, '+(navigator.platform||'browser')}).then(function(r){
    CKS.set('tica_scanner_device_id',r.device.public_id);CKS.set('tica_scanner_device_secret',r.secret);CKS.set('tica_scanner_device_name',r.device.name);return true});
}
function ckDraw(){
  $('ck-in').textContent=CK.in.toLocaleString('en-NG');$('ck-total').textContent=CK.total.toLocaleString('en-NG');
  $('ck-bar').style.width=(CK.total?Math.round(CK.in/CK.total*100):0)+'%';$('ck-left').textContent=Math.max(0,CK.total-CK.in).toLocaleString('en-NG')+' still to arrive';
  $('ck-log').innerHTML=CK.log.length?CK.log.slice(0,6).map(function(l){return '<li class="'+l.k+'"><i aria-hidden="true"></i><div><b>'+esc(l.name)+'</b><span>'+esc(l.sub)+'</span></div><time>'+l.t+'</time></li>'}).join(''):'<li class="none">No scans yet at this gate.</li>';
}
function ckShow(k,title,lines){
  var ic={ok:'<path d="M5 12.5l4.5 4.5L19 7.5"/>',used:'<path d="M12 7v6M12 16.5v.5"/>',bad:'<path d="M7 7l10 10M17 7L7 17"/>'}[k];
  var r=$('ck-res');r.className='ck-res '+k;
  r.innerHTML='<div class="ck-ic" aria-hidden="true"><svg viewBox="0 0 24 24">'+ic+'</svg></div><div><h2>'+title+'</h2>'+lines.map(function(l){return '<p>'+esc(l)+'</p>'}).join('')+'</div>';
  r.classList.remove('flash');void r.offsetWidth;r.classList.add('flash');
  if(navigator.vibrate)try{navigator.vibrate(k==='ok'?60:[80,60,80])}catch(e){}
}
function ckMetrics(){
  if(!CKE)return;
  ckFetch('/scanner/events/'+CKE+'/metrics').then(function(m){if(m.__status>=400)return;CK.total=m.total||0;CK.in=m.scanned||0;ckDraw()});
}
function ckCheck(payload,source,label){
  if(!CKE){ckShow('bad','Choose an event',['Pick the event you\'re checking in for first.']);return}
  ckFetch('/scanner/check-qrcode',{method:'POST',body:JSON.stringify({booking_id:payload,source:source||'scanner'})}).then(function(r){ckResult(r,label)},function(){ckShow('bad','No connection',['Check the internet connection, then scan again.'])});
}
/* Shared by QR scans, typed codes and guests found by search. Returns 'ok', 'used' or 'bad'. */
function ckResult(r,label){
  var who=label||r.booking_id||'Ticket';
  if(r.__status===401&&r.code&&/scanner_device/.test(r.code)){CKS.del('tica_scanner_device_id');CKS.del('tica_scanner_device_secret');ckShow('bad','This device needs setting up',[r.message||'Reload the page to set it up again.']);return 'bad'}
  var k;
  if(r.alert_type==='success'){k='ok';ckShow('ok','Valid ticket',[label||'Ticket checked in',r.message||'']);CK.log.unshift({k:'ok',name:label||'Ticket checked in',sub:r.message||'',t:ckTime()});ckMetrics()}
  else if(/already/i.test(r.message||'')){k='used';ckShow('used','Already checked in',[label||'This ticket',(r.message||'')+'. Don\'t let this ticket in twice.']);CK.log.unshift({k:'used',name:label||'Scanned again',sub:r.message||'',t:ckTime()})}
  else{k='bad';ckShow('bad','Ticket not valid',[r.message||'This ticket can\'t be used for this event.','Check the code, or send the guest to the help desk.']);CK.log.unshift({k:'bad',name:'Not valid',sub:r.message||String(who),t:ckTime()})}
  ckDraw();
  return k;
}
function renderCheckin(){
  var sel=$('ck-event'),evs=ckEvents();
  sel.innerHTML=evs.length?evs.map(function(e){return '<option value="'+e.id+'">'+esc(e.label)+'</option>'}).join(''):'<option value="">No events to check in</option>';
  CKE=evs.length?(+CKS.get('tica_ck_event')&&evs.some(function(e){return e.id===+CKS.get('tica_ck_event')})?+CKS.get('tica_ck_event'):evs[0].id):null;if(CKE)sel.value=String(CKE);
  ckSync();ckDraw();
  /* Owners manage gate staff and devices from the same place they scan */
  var su=$('ck-setup');if(su)su.hidden=!(window.TICA_ORG&&!TICA_DATA.gate&&((TICA_DATA.dashboard||{}).role||'owner')==='owner');
  ckDevice().then(ckMetrics,function(er){ckShow('bad','This device isn\'t set up',[er.message]);$('ck-cam-go').disabled=true});
}
function ckSync(){var s=document.querySelector('.ck-sync');if(s)s.innerHTML='<i aria-hidden="true"'+(navigator.onLine?'':' style="background:#F04438;box-shadow:0 0 0 4px rgba(240,68,56,.18)"')+'></i>'+(navigator.onLine?'Online. Results are live.':'Offline. Reconnect to keep checking tickets.')}
addEventListener('online',ckSync);addEventListener('offline',ckSync);
/* camera, using the QR reader Tica's scanner already ships */
(function(){
  var sim=document.querySelector('.ck-sim');if(sim)sim.innerHTML='<p>Scan the QR code on each ticket with this device\'s camera.</p><div><button class="btn btn-hot" type="button" id="ck-cam-go">Start scanning</button><button class="btn btn-ghost2" type="button" id="ck-cam-stop" hidden>Stop camera</button></div>';
  var cam=document.querySelector('.ck-cam');if(cam)cam.insertAdjacentHTML('afterbegin','<div id="ck-reader" class="ck-reader" aria-hidden="true"></div>');
})();
function ckLoadReader(){
  if(window.Html5Qrcode)return Promise.resolve();
  return new Promise(function(ok,no){var s=document.createElement('script');s.src='/assets/pwa_scanner/html5-qrcode.min.js';s.onload=ok;s.onerror=function(){no(new Error('The camera reader didn\'t load.'))};document.head.appendChild(s)});
}
function ckStart(){
  var go=$('ck-cam-go');go.disabled=true;go.textContent='Starting camera...';
  ckLoadReader().then(function(){
    CKCAM=new Html5Qrcode('ck-reader',{verbose:false});
    return CKCAM.start({facingMode:'environment'},{fps:10,qrbox:function(w,h){var s=Math.floor(Math.min(w,h)*.6);return {width:s,height:s}}},function(text){
      var now=Date.now();if(CKLAST[text]&&now-CKLAST[text]<4000)return;CKLAST[text]=now;ckCheck(text,'scanner');
    },function(){});
  }).then(function(){document.querySelector('.ck-cam').classList.add('live');go.hidden=true;$('ck-cam-stop').hidden=false;go.disabled=false;go.textContent='Start scanning'},
  function(er){go.disabled=false;go.textContent='Start scanning';ckShow('bad','Camera unavailable',[er&&er.message?er.message:'Allow camera access in your browser settings, then try again.'])});
}
function ckStop(){if(CKCAM){CKCAM.stop().catch(function(){});CKCAM=null}document.querySelector('.ck-cam').classList.remove('live');$('ck-cam-go').hidden=false;$('ck-cam-stop').hidden=true}
document.addEventListener('click',function(ev){if(ev.target.id==='ck-cam-go')ckStart();if(ev.target.id==='ck-cam-stop')ckStop()});
window.addEventListener('tica:navigate',function(){if(CKCAM&&!document.querySelector('.page[data-page=checkin]:not([hidden])'))ckStop()});
/* Can't scan? Find the guest by name, email or booking ID for this event, then check them in. */
(function(){var f=$('ck-find');if(!f)return;var out=$('ck-found'),FOUND=[];
  function stat(t){out.innerHTML='<li class="ck-found-msg">'+esc(t)+'</li>'}
  f.addEventListener('submit',function(ev){ev.preventDefault();var q=$('ck-q').value.trim();
    if(q.length<3){stat('Type at least 3 letters of a name, an email or a booking ID.');return}
    if(!CKE){stat('Pick the event you\'re checking in for first.');return}
    stat('Searching…');
    ckFetch('/scanner/ticket-lookup',{method:'POST',body:JSON.stringify({query:q,event_id:CKE})}).then(function(r){
      if(r.__status>=400){if(r.__status===401)ckResult(r);stat(r.message||'Search failed. Try again.');return}
      FOUND=r.tickets||[];
      if(!FOUND.length){stat('No ticket for "'+q+'" at this event. Check the spelling, or try their email.');return}
      out.innerHTML=FOUND.map(function(t,i){var st=t.status==='issued'?'':t.status==='checked_in'?'Checked in':String(t.status||'').replace(/_/g,' ');
        return '<li><div><b>'+esc(t.holder_name||'Guest')+'</b><span>'+esc(t.holder_email||'')+'</span><small>'+esc((t.variation_name?t.variation_name+' · ':'')+'Booking '+t.booking_id+' · ticket '+t.sequence+' of '+t.quantity)+'</small></div>'+
          (st?'<em class="ck-found-st'+(t.status==='checked_in'?' in':'')+'">'+esc(st.charAt(0).toUpperCase()+st.slice(1))+'</em>':'<button class="btn btn-hot" type="button" data-found="'+i+'">Check in</button>')+'</li>'}).join('');
    },function(){stat('No connection. Check the internet, then search again.')});
  });
  out.addEventListener('click',function(ev){var b=ev.target.closest('[data-found]');if(!b)return;var t=FOUND[+b.dataset.found];if(!t)return;
    b.disabled=true;b.textContent='Checking in…';
    ckFetch('/scanner/manual-check-in',{method:'POST',body:JSON.stringify({ticket_unit_id:t.id})}).then(function(r){
      var k=ckResult(r,t.holder_name||'Guest');var em=document.createElement('em');em.className='ck-found-st'+(k==='bad'?' bad':' in');em.textContent=k==='ok'?'Checked in':k==='used'?'Already in':'Not valid';b.replaceWith(em);
    },function(){b.disabled=false;b.textContent='Try again';ckShow('bad','No connection',['Check the internet connection, then try again.'])});
  });
})();
$('ck-manual').addEventListener('submit',function(ev){ev.preventDefault();var v=$('ck-code').value.trim().toUpperCase();if(!v)return;$('ck-code').value='';
  if(!CKE){ckShow('bad','Choose an event',['Pick the event you\'re checking in for first.']);return}
  api('/tica-api/checkin/code',{event_id:CKE,code:v}).then(function(r){ckCheck(r.payload,'manual',r.name+', '+r.tier)},function(er){ckShow('bad','Ticket not found',[er.message,'Check the code, or send the guest to the help desk.']);CK.log.unshift({k:'bad',name:'Unknown code',sub:v,t:ckTime()});ckDraw()})});
$('ck-event').addEventListener('change',function(){CKE=+this.value||null;CKS.set('tica_ck_event',String(CKE||''));CK={total:0,in:0,log:[]};$('ck-res').className='ck-res';$('ck-res').innerHTML='<p class="ck-idle">Scan a ticket to see the result here.</p>';ckDraw();ckMetrics()});
setInterval(function(){if(document.querySelector('.page[data-page=checkin]:not([hidden])')&&!document.hidden)ckMetrics()},20000);

var TITLES={home:'Home',discover:'Discover events',how:'How TICA works',pricing:'Pricing',about:'About us',contact:'Contact',create:'Create an event',policies:'Terms and policies',login:'Log in',account:'Your tickets',checkin:'Gate check-in',tickets:'My tickets',dashboard:'Dashboard',signup:'Create your account',forgot:'Reset your password',join:'Join',missing:'Page not found'};
/* Tica: the design's pages live at real paths. Same-origin links to a design
   page are routed here; anything else is a normal page load. */
var SPA=['','discover','how','pricing','about','contact','join','policies','tickets','account','login','signup','forgot','create','dashboard','checkin','event','checkout','ticket'];
function pathOf(u){return u.pathname.replace(/^\/+|\/+$/g,'')}
function isSpa(path){var p=path.split('/')[0];return SPA.indexOf(p)!==-1&&(p!=='event'&&p!=='checkout'&&p!=='ticket'||!!bySlug(path.split('/').slice(1).join('/')))}
var cur=(window.TICA_DATA&&typeof TICA_DATA.path==='string')?TICA_DATA.path:pathOf(location);
function go(path){path=String(path).replace(/^\/+/,'');if(!isSpa(path.split('?')[0])){location.href='/'+path;return}cur=path;try{history.pushState(null,'','/'+path)}catch(err){}route()}
document.addEventListener('click',function(ev){
  if(ev.defaultPrevented||ev.button!==0||ev.metaKey||ev.ctrlKey||ev.shiftKey||ev.altKey)return;
  var a=ev.target.closest('a[href]');if(!a||a.target&&a.target!=='_self'||a.hasAttribute('download'))return;
  var u;try{u=new URL(a.getAttribute('href'),location.href)}catch(err){return}
  if(u.origin!==location.origin||u.search||(u.hash&&pathOf(u)===pathOf(location)))return;
  var path=pathOf(u);if(!isSpa(path))return;
  ev.preventDefault();go(path);
});
var RV='.h-sec>*,.org-in>*,.fcards>*,.stepcards>*,.who>*,.faq>*,.joins>*,.duo,.aboutimg,.rows>*,.more>*,.evc,.partners .wrap,.policy>*,.nz';
var rvIO=('IntersectionObserver' in window&&!(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches))?new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');rvIO.unobserve(x.target)}})},{rootMargin:'0px 0px -6% 0px',threshold:.05}):null;
function reveal(root){
  if(!rvIO)return;
  var seen=new Map();
  [].forEach.call(root.querySelectorAll(RV),function(el){
    if(el.classList.contains('rv'))return;
    var c=(seen.get(el.parentNode)||0);seen.set(el.parentNode,c+1);
    el.style.setProperty('--d',Math.min(c,5)*60+'ms');el.classList.add('rv');rvIO.observe(el);
  });
}
window.TICA_go=go;
function route(){
  var c0=cur.split('/')[0];
  if(c0==='tickets'&&window.TICA_USER)cur='account';
  if(c0==='account'&&!window.TICA_USER){cur='login';[].forEach.call(document.querySelectorAll('.au'),function(a){a.dataset.role='att'});[].forEach.call(document.querySelectorAll('.au-seg button'),function(b){b.setAttribute('aria-pressed',b.dataset.role==='att'?'true':'false')});try{history.replaceState(null,'','/login')}catch(err){}}
  if((c0==='create'||c0==='dashboard'||c0==='checkin')&&!window.TICA_ORG&&!(c0==='create'&&window.TICA_ADMIN)&&!(c0==='checkin'&&TICA_DATA.gate)){
    cur='signup';
    [].forEach.call(document.querySelectorAll('.au'),function(a){a.dataset.role='org'});
    [].forEach.call(document.querySelectorAll('.au-seg button'),function(b){b.setAttribute('aria-pressed',b.dataset.role==='org'?'true':'false')});
    try{history.replaceState(null,'','/signup')}catch(err){}
  }
  var parts=cur.split('/'),p=parts[0]||'home',e=parts[1]?bySlug(parts.slice(1).join('/')):null;
  window.TICA_CUR=null;
  if(['event','checkout','ticket'].indexOf(p)!==-1){
    if(!e)p='missing';
    else if(p==='event')renderEvent(e);
    else if(p==='checkout'){if(e.sold){go('event/'+e.slug);return}renderCheckout(e)}
    else renderTicket(e);
  }else if(window.TICA_DATA&&TICA_DATA.content&&cur===TICA_DATA.path&&document.querySelector('.page[data-page=content]')){p='content'}else if(!TITLES[p])p='missing';
  [].forEach.call(document.querySelectorAll('.page'),function(s){s.hidden=s.dataset.page!==p});
  document.body.classList.toggle('isorg',!!window.TICA_ORG);document.body.classList.toggle('orgshell',!!document.getElementById('os-side')&&['dashboard','create','checkin'].indexOf(p)>-1);document.body.classList.toggle('t-app',document.body.classList.contains('orgshell'));document.body.classList.toggle('dashmode',p==='dashboard');document.body.classList.toggle('appmode',['dashboard','create','account','checkin'].indexOf(p)>-1);document.body.classList.toggle('gatemode',p==='checkin');document.body.classList.toggle('isuser',!!window.TICA_USER);document.body.classList.remove('dashnav-open');var workspaceMenu=document.getElementById('workspace-menu');if(workspaceMenu){var workspaceOpen=window.matchMedia('(min-width:861px)').matches&&!document.body.classList.contains('dashnav-collapsed');workspaceMenu.setAttribute('aria-expanded',workspaceOpen?'true':'false');workspaceMenu.setAttribute('aria-label',workspaceOpen?'Close dashboard menu':'Open dashboard menu')}if(p==='account')renderAccount();if(p==='checkin')renderCheckin();if(p==='dashboard')renderDash();
  var workspaceTitle=document.getElementById('workspace-title'),workspaceRole=document.getElementById('workspace-role'),workspaceAccount=document.getElementById('workspace-account');
  if(workspaceTitle)workspaceTitle.textContent=p==='dashboard'?'Organiser dashboard':p==='account'?'Your account':p==='checkin'?'Gate check-in':p==='create'?'Create an event':'TICA workspace';
  if(workspaceRole)workspaceRole.textContent=p==='create'&&window.TICA_ADMIN?'Admin event workspace':(p==='dashboard'||p==='checkin'||p==='create')?'Organiser workspace':p==='account'?'Customer account':'TICA workspace';
  if(workspaceAccount){var customerWorkspace=p==='account',workspaceName=window.TICA_ADMIN?(TICA_DATA.adminBuilder.adminName||'TICA admin'):customerWorkspace&&window.TICA_USER?window.TICA_USER.name:window.TICA_ORG&&window.TICA_DATA.dashboard?window.TICA_DATA.dashboard.org.name:null;workspaceAccount.textContent=(workspaceName||'T').trim().charAt(0).toUpperCase();workspaceAccount.href=window.TICA_ADMIN?(TICA_DATA.adminBuilder.backUrl||'/admin/dashboard'):customerWorkspace?'/account/profile':'/dashboard/settings'}
  if(p==='dashboard'&&!route.ran){var workspaceMore=document.getElementById('od-more');if(workspaceMore)workspaceMore.removeAttribute('open')}
  document.body.classList.toggle('authmode',p==='login'||p==='signup'||p==='forgot');
  [].forEach.call(document.querySelectorAll('.pagebar a'),function(a){var h=a.getAttribute('href').slice(1).split('/')[0]||'home';if(h===p)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current')});
  [].forEach.call(document.querySelectorAll('[data-nav]'),function(a){if(a.dataset.nav===p)a.setAttribute('aria-current','page');else a.removeAttribute('aria-current')});
  document.title=(e&&['event','checkout','ticket'].indexOf(p)!==-1?e.name:p==='content'?TICA_DATA.content.title:TITLES[p])+' | TICA';
  window.scrollTo({top:0,behavior:'instant'});
  var pg=document.querySelector('.page[data-page="'+p+'"]');pg.classList.remove('enter');void pg.offsetWidth;pg.classList.add('enter');
  reveal(pg);cdTick();refreshStock();
  var main=$('main');if(parts[0]!==undefined&&route.ran)main.focus({preventScroll:true});route.ran=true;
  if(p==='discover')filter();
  hydrateImages(pg);
  var first=$('tica-first-page');if(first)first.remove();
  try{window.dispatchEvent(new Event('tica:navigate'))}catch(err){}
}
function fromUrl(){cur=pathOf(location);route()}
window.addEventListener('scroll',function(){document.querySelector('.topbar').classList.toggle('scrolled',window.scrollY>8)},{passive:true});
document.addEventListener('click',function(ev){var m=document.querySelector('.menu[open]');if(m&&!m.contains(ev.target))m.removeAttribute('open')});
document.addEventListener('click',function(ev){var b=ev.target.closest('#workspace-menu');if(b){var mobile=window.matchMedia('(max-width:860px)').matches,open;if(mobile){open=!document.body.classList.contains('dashnav-open');document.body.classList.toggle('dashnav-open',open)}else{document.body.classList.remove('dashnav-open');document.body.classList.toggle('dashnav-collapsed');open=!document.body.classList.contains('dashnav-collapsed')}b.setAttribute('aria-expanded',open?'true':'false');b.setAttribute('aria-label',open?'Close dashboard menu':'Open dashboard menu');return}if(document.body.classList.contains('dashnav-open')&&(!ev.target.closest('.db-side,.acd-side')||ev.target.closest('.db-side button,.db-side a,.acd-side button,.acd-side a'))){document.body.classList.remove('dashnav-open');var mb=document.getElementById('workspace-menu');if(mb)mb.setAttribute('aria-expanded','false')}});
document.addEventListener('keydown',function(ev){if(ev.key==='Escape'){var m=document.querySelector('.menu[open]');if(m){m.removeAttribute('open');m.querySelector('summary').focus()}}});
document.addEventListener('keydown',function(ev){if(ev.key==='Escape'&&document.body.classList.contains('dashnav-open')){document.body.classList.remove('dashnav-open');var b=document.getElementById('workspace-menu');if(b){b.setAttribute('aria-expanded','false');b.focus()}}});
window.addEventListener('popstate',fromUrl);
prev();filter();route();
/* Tica: log out of an organiser or attendee account. */
document.addEventListener('click',function(ev){var b=ev.target.closest('[data-logout]');if(!b)return;b.disabled=true;
  var url=b.dataset.logout==='organizer'?'/organizer/logout':'/customer/logout';
  fetch(url,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':TICA_DATA.csrf},redirect:'manual'}).finally(function(){location.href='/'})});
/* Tica: show the server's one-off message (e.g. after verifying an email). */
(function(){var f=TICA_DATA.flash||{},m=f.error||f.success||f.message;if(m)setTimeout(function(){flash(m)},400)})();
})();
/* design script 03 */
(function(){
  var seg=document.querySelectorAll('.x-seg button');
  [].forEach.call(seg,function(b){b.addEventListener('click',function(){
    [].forEach.call(seg,function(o){o.setAttribute('aria-pressed',o===b?'true':'false')});
    [].forEach.call(document.querySelectorAll('.x-line'),function(l){l.hidden=l.dataset.jp!==b.dataset.j});
  })});
  var $=function(i){return document.getElementById(i)},N=function(n){return window.TICA_MONEY(n)};
  function calc(){
    var p=+$('c1').value,q=+$('c2').value,fee=Math.round(p*window.TICA_FEE_PCT)/100;
    $('c1o').textContent=N(p);$('c2o').textContent=q.toLocaleString('en-NG');
    var s=p*q;$('c-fee').textContent=N(fee);$('c-buy').textContent=N(p+fee);$('c-get').textContent=N(s);
  }
  $('c1').addEventListener('input',calc);$('c2').addEventListener('input',calc);
  $('calc').addEventListener('submit',function(e){e.preventDefault()});
  calc();
})();
/* design script 04 */
(function(){
  var all=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
  all('.au-seg button').forEach(function(b){b.addEventListener('click',function(){
    var role=b.dataset.role;
    all('.au').forEach(function(p){p.dataset.role=role});
    all('.au-seg button').forEach(function(o){o.setAttribute('aria-pressed',o.dataset.role===role?'true':'false')});
  })});
  all('.au-eye').forEach(function(b){b.addEventListener('click',function(){
    var i=b.parentNode.querySelector('input'),show=i.type==='password';
    i.type=show?'text':'password';b.textContent=show?'Hide':'Show';
    b.setAttribute('aria-pressed',show?'true':'false');b.setAttribute('aria-label',show?'Hide password':'Show password');
  })});
  var sp=document.getElementById('s-pass');
  sp.addEventListener('input',function(){
    var v=sp.value,score=0;
    if(v.length>=8)score++;
    if(/[A-Z]/.test(v)&&/[a-z]/.test(v)&&/\d/.test(v))score++;
    if(v.length>=12||/[^A-Za-z0-9]/.test(v))score++;
    if(v.length<8&&score>0)score=1;
    var m=sp.closest('.au-f').querySelector('.au-meter');m.dataset.s=v?score:0;
    document.getElementById('pw-hint').textContent=!v||v.length<8?'Use at least 8 characters.':['Use at least 8 characters.','Okay. Add numbers and capitals to make it stronger.','Good password.','Strong password.'][score];
  });
  function err(f,id,msg){
    var i=f.querySelector('#'+id),e=f.querySelector('#'+id+'-e');
    e.textContent=msg||'';e.hidden=!msg;
    if(msg){i.setAttribute('aria-invalid','true');i.setAttribute('aria-describedby',id+'-e')}else{i.removeAttribute('aria-invalid');i.removeAttribute('aria-describedby')}
    return !msg;
  }
  var EM=/^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  function done(f,msg){
    var d=f.querySelector('.au-done');d.textContent=msg;d.hidden=false;
    all('.au-pw input',f).forEach(function(i){i.value='';i.type='password'});all('.au-eye',f).forEach(function(b){b.textContent='Show';b.setAttribute('aria-pressed','false');b.setAttribute('aria-label','Show password')});
    var m=f.querySelector('.au-meter');if(m)m.dataset.s=0;
  }
  all('[data-auth]').forEach(function(f){
    f.addEventListener('input',function(ev){f.querySelector('.au-done').hidden=true;var t=ev.target;if(t.id&&t.getAttribute('aria-invalid'))err(f,t.id,'')});
    f.addEventListener('submit',function(ev){
      ev.preventDefault();
      var k=f.dataset.auth,v=function(id){return f.querySelector('#'+id).value.trim()},ok=true;
      var chk=function(id,msg){if(!err(f,id,msg))ok=false};
      if(k==='login'){chk('l-email',EM.test(v('l-email'))?'':'Enter a valid email address, like name@example.com.');chk('l-pass',f.querySelector('#l-pass').value?'':'Enter your password.')}
      if(k==='forgot'){chk('f-email',EM.test(v('f-email'))?'':'Enter a valid email address, like name@example.com.')}
      if(k==='signup'){
        var org=f.closest('.au').dataset.role==='org';
        chk('s-org',org&&v('s-org').length<2?'Enter your organisation or brand name.':'');
        chk('s-name',v('s-name').length<2?'Enter your full name.':'');
        chk('s-email',EM.test(v('s-email'))?'':'Enter a valid email address, like name@example.com.');
        chk('s-phone',v('s-phone').replace(/\D/g,'').length<10?'Enter a phone number with at least 10 digits.':'');
        chk('s-pass',f.querySelector('#s-pass').value.length<8?'Use at least 8 characters.':'');
      }
      if(!ok){var first=f.querySelector('[aria-invalid=true]');if(first)first.focus();return}
      /* Tica: real accounts. Field errors from the server land in the matching au-err slot. */
      var au=f.closest('.au'),role=au.dataset.role,go=f.querySelector('.au-go');
      var body=k==='login'?{role:role,email:v('l-email'),password:f.querySelector('#l-pass').value}:
        k==='forgot'?{role:role,email:v('f-email')}:
        {role:role,organisation:v('s-org'),name:v('s-name'),email:v('s-email'),phone:v('s-phone'),password:f.querySelector('#s-pass').value};
      var cap=f.querySelector('[name="g-recaptcha-response"]');if(cap)body['g-recaptcha-response']=cap.value;
      var FIELD={login:{email:'l-email',password:'l-pass'},signup:{organisation:'s-org',name:'s-name',email:'s-email',phone:'s-phone',password:'s-pass'},forgot:{email:'f-email'}}[k];
      go.disabled=true;
      window.TICA_API('/tica-api/'+k,body).then(function(r){
        if(r.redirect){location.href=r.redirect;return}
        go.disabled=false;done(f,r.message||'Done.');
      },function(er){
        go.disabled=false;var placed=false,errs=er.data&&er.data.errors||{};
        Object.keys(errs).forEach(function(key){var id=FIELD[key];if(id&&f.querySelector('#'+id)){err(f,id,errs[key][0]);if(!placed){f.querySelector('#'+id).focus();placed=true}}});
        if(!placed){var d=f.querySelector('.au-done');d.textContent=er.message;d.hidden=false}
      });
    });
  });
  all('[data-demo]').forEach(function(b){b.addEventListener('click',function(){
    if(b.closest('.au').dataset.role==='org'){done(b.closest('form'),'Google sign-in is for attendees. Organisers log in with email and password.');return}
    location.href='/customer/auth/google';
  })});
  /* Tica: a team invite turns the sign-up page into "join this organiser's team". */
  var inv=window.TICA_DATA&&TICA_DATA.teamInvite,sf=document.querySelector('[data-auth=signup]');
  if(inv&&sf){
    all('.au').forEach(function(p){p.dataset.role='org'});
    var hide=function(n){if(n)n.style.setProperty('display','none','important')};
    ['.au-seg','.au-g','.au-or'].forEach(function(c){hide(sf.querySelector(c))});
    [sf.querySelector('#s-org'),sf.querySelector('#s-phone')].forEach(function(i){if(i)hide(i.closest('.au-f'))});
    var h=sf.querySelector('h1'),sub=sf.querySelector('.au-sub'),go=sf.querySelector('.au-go'),em=sf.querySelector('#s-email');
    if(inv.expired){h.textContent='This invite has expired';sub.textContent='Ask the organiser to send you a new one.';[].forEach.call(sf.querySelectorAll('.au-f,.au-go,.au-legal'),hide)}
    else{
      h.textContent='Join '+inv.org+' on TICA';sub.textContent='You\'ve been invited as '+inv.role+'. Choose a password to accept.';
      em.value=inv.email;em.readOnly=true;go.innerHTML='Accept invite';
      sf.addEventListener('submit',function(ev){
        ev.preventDefault();ev.stopImmediatePropagation();
        var nm=sf.querySelector('#s-name').value.trim(),pw=sf.querySelector('#s-pass').value,ok=true;
        if(!err(sf,'s-name',nm.length<2?'Enter your full name.':''))ok=false;
        if(!err(sf,'s-pass',pw.length<8?'Use at least 8 characters.':''))ok=false;
        if(!ok)return;go.disabled=true;
        window.TICA_API('/tica-api/team/accept',{token:inv.token,name:nm,password:pw}).then(function(r){done(sf,r.message);setTimeout(function(){location.href=r.redirect},1600)},function(x){go.disabled=false;var e2=x.data&&x.data.errors||{};if(e2.password)err(sf,'s-pass',e2.password[0]);else if(e2.name)err(sf,'s-name',e2.name[0]);else done(sf,x.message)});
      },true);
    }
  }
  /* Tica: open the organiser side when the URL asks for it (e.g. /signup?role=org). */
  var wantRole=new URLSearchParams(location.search).get('role');
  if(wantRole==='org'||wantRole==='att'){all('.au').forEach(function(p){p.dataset.role=wantRole});all('.au-seg button').forEach(function(o){o.setAttribute('aria-pressed',o.dataset.role===wantRole?'true':'false')})}
})();
/* design script 05 */
(function(){var q=document.getElementById('c-qty'),o=document.getElementById('p-left');function u(){var n=parseInt(q.value,10);o.textContent=n>0?n.toLocaleString('en-NG')+' tickets left':''}q.addEventListener('input',u);u()})();
/* design script 06 */
(function(){var m=document.querySelector('.menu');function s(){document.documentElement.style.setProperty('--menu-top',Math.round(document.querySelector('.topbar').getBoundingClientRect().bottom)+'px')}m.addEventListener('toggle',s);window.addEventListener('scroll',function(){if(m.open)s()},{passive:true});window.addEventListener('resize',function(){if(m.open)s()})})();
/* design script 07 */
(function(){
var fab=document.getElementById('fab'),fk=document.getElementById('fab-k'),fv=document.getElementById('fab-v'),fb=document.getElementById('fab-b'),tgt=null,mode='';
function page(){var p=document.querySelector('.page:not([hidden])');return p?p.dataset.page:''}
function pick(){
  var p=page();mode='';
  if(p==='event'){var g=document.getElementById('tp-go');if(!g)return null;mode='event';return g}
  if(p==='checkout'){var t=document.querySelector('#checkout-view .pform.open button[type=submit]')||document.getElementById('pay-now')||document.getElementById('to-details');if(t)mode='checkout';return t}
  return null;
}
function inView(el){var r=el.getBoundingClientRect();return r.bottom>60&&r.top<innerHeight-10}
function upd(){
  tgt=pick();var small=innerWidth<=900,show=false;
  if(tgt&&small&&tgt.offsetParent&&!document.querySelector('dialog[open]')&&!inView(tgt)){
    show=true;
    if(mode==='event'){
      var tot=document.querySelector('#tp-lines .sumtotal span:last-child');
      fk.textContent=tot?'Amount due':'Tickets';fv.textContent=tot?tot.textContent:(document.getElementById('bb-total')||{}).textContent||'';
      fb.textContent=tgt.disabled?'Choose tickets':'Continue';fb.disabled=false;
    }else{
      var s=document.querySelector('#checkout-view .sumtotal span:last-child');
      fk.textContent='Total';fv.textContent=s?s.textContent:'';
      fb.textContent=tgt.textContent.trim();fb.disabled=!!tgt.disabled;
    }
  }
  fab.classList.toggle('on',show);fab.setAttribute('aria-hidden',show?'false':'true');fb.tabIndex=show?0:-1;
  document.body.classList.toggle('fab-pad',!!tgt&&small);
}
fb.addEventListener('click',function(){
  if(!tgt)return;
  if(mode==='event'&&tgt.disabled){var tp=document.getElementById('tp');tp.scrollIntoView({behavior:'smooth',block:'center'});var pl=tp.querySelector('.plus');if(pl)setTimeout(function(){pl.focus({preventScroll:true})},400);return}
  if(mode==='checkout'&&tgt.type==='submit'){var f=tgt.closest('form');var bad=f&&[].filter.call(f.querySelectorAll('input'),function(i){return !i.value.trim()&&!/optional/i.test((f.querySelector('label[for="'+i.id+'"]')||{}).textContent||'')})[0];if(bad){bad.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(function(){bad.focus({preventScroll:true})},350)}}
  tgt.click();setTimeout(upd,60);
});
var q=0;function sch(){if(!q){q=1;requestAnimationFrame(function(){q=0;upd()})}}
addEventListener('scroll',sch,{passive:true});addEventListener('resize',sch);
document.addEventListener('click',function(){setTimeout(upd,80)});document.addEventListener('input',sch);
addEventListener('tica:navigate',function(){setTimeout(upd,120)});addEventListener('popstate',function(){setTimeout(upd,120)});
new MutationObserver(sch).observe(document.querySelector('main'),{childList:true,subtree:true,attributes:true,attributeFilter:['hidden','disabled']});
upd();
})();
/* design script 08 */
(function(){function k(){[].forEach.call(document.querySelectorAll('.steps'),function(s){var a=s.querySelector('.on');if(a&&s.scrollWidth>s.clientWidth)s.scrollLeft=a.offsetLeft-s.offsetLeft-16})}new MutationObserver(function(){requestAnimationFrame(k)}).observe(document.querySelector('main'),{childList:true,subtree:true})})();
/* design script 09 */
(function(){
var m=document.querySelector('.menu');
m.addEventListener('toggle',function(){document.documentElement.classList.toggle('menu-open',m.open&&innerWidth<=1040)});
addEventListener('tica:navigate',function(){m.removeAttribute('open');document.documentElement.classList.remove('menu-open')});
[].forEach.call(document.querySelectorAll('.h-chips button'),function(b){b.addEventListener('click',function(){
  ['h-where','h-cat','h-month'].forEach(function(i){document.getElementById(i).value='all'});document.getElementById('h-q').value='';
  document.getElementById(b.dataset.hk).value=b.dataset.hv;
  var f=document.getElementById('home-search');if(f.requestSubmit)f.requestSubmit();else f.dispatchEvent(new Event('submit',{cancelable:true}));
})});
var s=document.querySelector('.spot'),x=null,y=null;
s.addEventListener('touchstart',function(e){x=e.touches[0].clientX;y=e.touches[0].clientY},{passive:true});
s.addEventListener('touchend',function(e){if(x===null)return;var dx=e.changedTouches[0].clientX-x,dy=e.changedTouches[0].clientY-y;x=null;if(Math.abs(dx)>45&&Math.abs(dx)>Math.abs(dy))document.getElementById(dx<0?'spot-next':'spot-prev').click()});
})();
/* design script 10 */
(function(){
var $=function(i){return document.getElementById(i)},more=$('f-more'),bd=$('f-bd'),btn=$('f-btn'),last=null;
var SEL=[['d-where','City'],['d-cat','Category'],['d-price','Price'],['d-month','Date']];
function ui(){
  var n=0,chips='';
  SEL.forEach(function(s){var el=$(s[0]);if(el.value!=='all'){n++;chips+='<button type="button" data-clr="'+s[0]+'" aria-label="Remove filter: '+el.options[el.selectedIndex].text+'">'+el.options[el.selectedIndex].text+'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>'}});
  $('f-n').textContent=n;$('f-n').hidden=!n;btn.setAttribute('aria-label','Filters'+(n?', '+n+' active':''));
  $('f-active').innerHTML=chips;
  var c=($('count').textContent||'').trim();$('f-apply').textContent=c&&c!=='0 events'?'Show '+c:'No matching events';
}
var home=btn.parentNode,bdHome=bd.parentNode,bdNext=bd.nextSibling;
function open(){last=document.activeElement;document.body.appendChild(bd);document.body.appendChild(more);void more.offsetWidth;bd.hidden=false;requestAnimationFrame(function(){bd.classList.add('on');more.classList.add('open')});document.documentElement.classList.add('menu-open');setTimeout(function(){$('f-x').focus()},320)}
function close(){more.classList.remove('open');bd.classList.remove('on');document.documentElement.classList.remove('menu-open');setTimeout(function(){bd.hidden=true;if(!more.classList.contains('open')){home.insertBefore(more,btn.nextSibling);bdHome.insertBefore(bd,bdNext)}},320);if(last)last.focus()}
btn.addEventListener('click',open);$('f-x').addEventListener('click',close);$('f-apply').addEventListener('click',close);bd.addEventListener('click',close);
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&more.classList.contains('open'))close()});
$('f-clear').addEventListener('click',function(){$('reset').click();$('d-sort').value='soon';$('d-sort').dispatchEvent(new Event('input',{bubbles:true}))});
$('f-active').addEventListener('click',function(e){var b=e.target.closest('[data-clr]');if(!b)return;var s=$(b.dataset.clr);s.value='all';s.dispatchEvent(new Event('input',{bubbles:true}))});
new MutationObserver(ui).observe($('count'),{childList:true,characterData:true,subtree:true});
SEL.forEach(function(s){$(s[0]).addEventListener('input',ui);$(s[0]).addEventListener('change',ui)});
addEventListener('tica:navigate',function(){if(more.classList.contains('open'))close()});
document.addEventListener('click',function(e){if(e.target.closest('.nav-ic')){setTimeout(function(){var q=$('d-q');q.focus();q.scrollIntoView({block:'center'})},380)}});
ui();
})();
/* design script 11 */
(function(){function p(){var s=innerWidth<=480;var e=document.getElementById('h-q');if(e)e.placeholder=s?'Search events':'Search events or artists';var d=document.getElementById('d-q');if(d)d.placeholder=s?'Search events':'Artist, event or organiser'}p();addEventListener('resize',p)})();
/* design script 12 */
(function(){
var s=[].slice.call(document.querySelectorAll('.h-slide'));if(s.length<2)return;
var i=0,red=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
s.forEach(function(x,k){if(k){var im=x.querySelector('img');im.loading='eager'}});
setInterval(function(){if(red||document.hidden)return;var hero=document.querySelector('[data-page=home]');if(!hero||hero.hidden)return;s[i].classList.remove('on');i=(i+1)%s.length;s[i].classList.add('on')},6500);
})();
/* design script 14 */
(function(){
var m=document.getElementById('share');
document.getElementById('sh-x').addEventListener('click',function(){m.close()});
m.addEventListener('click',function(e){if(e.target===m)m.close()});
[].forEach.call(document.querySelectorAll('.sh-tabs button'),function(b){b.addEventListener('click',function(){shTab(b.dataset.t)})});
document.getElementById('sh-cp').addEventListener('click',function(){var i=document.getElementById('sh-url'),b=this;function ok(){b.textContent='Copied';setTimeout(function(){b.textContent='Copy'},1800)}
 if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(i.value).then(ok,function(){i.select();try{document.execCommand('copy');ok()}catch(e){}});else{i.select();try{document.execCommand('copy');ok()}catch(e){}}});
addEventListener('tica:navigate',function(){if(m.open)m.close()});
})();
/* design script 15 */
(function(){
if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;
function run(el){var t=el.textContent.trim(),m=t.match(/^(₦?)([\d,.]+)([A-Za-z]*)(.*)$/);if(!m||el.dataset.cu)return;el.dataset.cu=1;
 var pre=m[1],num=parseFloat(m[2].replace(/,/g,'')),suf=m[3]+m[4],dec=(m[2].split('.')[1]||'').length,comma=m[2].indexOf(',')>-1,t0=null;
 function f(n){var s=n.toFixed(dec);if(comma)s=Number(s).toLocaleString('en-NG',{minimumFractionDigits:dec,maximumFractionDigits:dec});return pre+s+suf}
 function step(ts){if(!t0)t0=ts;var p=Math.min(1,(ts-t0)/1100),e=1-Math.pow(1-p,3);el.textContent=f(num*e);if(p<1)requestAnimationFrame(step);else el.textContent=t}
 requestAnimationFrame(step)}
var io=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){run(x.target);io.unobserve(x.target)}})},{threshold:.6});
function scan(){[].forEach.call(document.querySelectorAll('.page:not([hidden]) .bn-num b,.page:not([hidden]) .db-kpis b,.page:not([hidden]) .db-big,.page:not([hidden]) .ck-stat b>span:first-child'),function(el){if(!el.dataset.cu)io.observe(el)})}
addEventListener('tica:navigate',function(){setTimeout(scan,350)});document.addEventListener('click',function(){setTimeout(scan,400)});scan();
})();
/* design script 16 */
(function(){
var s=document.querySelector('.spot');if(!s)return;
var bar=document.createElement('i');bar.className='spot-prog';bar.setAttribute('aria-hidden','true');s.appendChild(bar);
var red=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;if(red)return;
var hold=false,seen=true,left=6000,last=Date.now();
function restart(){left=6000;last=Date.now();bar.classList.remove('run');void bar.offsetWidth;bar.classList.add('run')}
s.addEventListener('mouseenter',function(){hold=true});s.addEventListener('mouseleave',function(){hold=false;last=Date.now()});
s.addEventListener('focusin',function(){hold=true});s.addEventListener('focusout',function(){hold=false;last=Date.now()});
s.addEventListener('touchstart',function(){hold=true},{passive:true});s.addEventListener('touchend',function(){setTimeout(function(){hold=false;last=Date.now()},1500)});
if('IntersectionObserver' in window)new IntersectionObserver(function(e){seen=e[0].isIntersecting},{threshold:.4}).observe(s);
['spot-prev','spot-next'].forEach(function(id){document.getElementById(id).addEventListener('click',function(e){if(e.isTrusted)restart()})});
setInterval(function(){var now=Date.now(),home=document.querySelector('[data-page=home]');
 if(hold||!seen||document.hidden||!home||home.hidden){last=now;return}
 left-=now-last;last=now;
 if(left<=0){document.getElementById('spot-next').click();restart()}},200);
restart();
})();
/* design script 17 */
(function(){
var $=function(i){return document.getElementById(i)},KEY='tica_consent_v1',ban=$('ck-banner'),dlg=$('ck-dlg');
function load(){var c;try{c=JSON.parse(localStorage.getItem(KEY)||'null')}catch(e){c=window.__ck||null}if(c&&c.at&&Date.now()-Date.parse(c.at)>365*864e5)return null;return c}
function save(c){c.v=1;c.at=new Date().toISOString();window.__ck=c;try{localStorage.setItem(KEY,JSON.stringify(c))}catch(e){}ban.hidden=true;if(dlg.open)dlg.close();startNB();document.dispatchEvent(new CustomEvent('tica:consent',{detail:c}))}
function openDlg(){var c=load()||{p:false,a:false,m:false};$('ck-p').checked=!!c.p;$('ck-a').checked=!!c.a;$('ck-m').checked=!!c.m;dlg.showModal()}
document.addEventListener('click',function(e){
  var b=e.target.closest('[data-ck]');
  if(b){var k=b.dataset.ck;
    if(k==='accept')save({p:true,a:true,m:true});
    if(k==='reject')save({p:false,a:false,m:false});
    if(k==='manage')openDlg();
    if(k==='save')save({p:$('ck-p').checked,a:$('ck-a').checked,m:$('ck-m').checked});
    if(k==='close')dlg.close();
    return}
  if(e.target.closest('[data-cookie-open]')){e.preventDefault();openDlg()}
});
dlg.addEventListener('click',function(e){if(e.target===dlg)dlg.close()});

/* nearby suggestions */
var stockLeft=function(e){return e.tiers.reduce(function(s,t){return s+t[2]},0)},stockTotal=function(e){return e.tiers.reduce(function(s,t){return s+t[3]},0)};
var dstr=function(e){var M={Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][new Date(e.y||2026,M[e.mon],+e.day).getDay()]+', '+e.day+' '+e.mon};
var nb=$('nb'),started=false,stopped=false,shown=0,MAX=5,timer=null,hideT=null,over=false;
try{stopped=sessionStorage.getItem('tica_nb_off')==='1'}catch(e){}
var OK=['home','discover','how','pricing','about','contact'];
function page(){var p=document.querySelector('.page:not([hidden])');return p?p.dataset.page:''}
function city(){return window.TICA_CITY||'Owerri'} /* live site: city comes from the server's IP lookup */
function pool(){
  var c=load()||{},near=c.p,list=window.TICA_EVENTS.filter(function(e){return !e.sold});
  var here=near?list.filter(function(e){return e.city===city()}):[];
  var src=(here.length?here:list).slice();
  return src.map(function(e){
    var l=stockLeft(e),low=l>0&&l<=Math.max(20,stockTotal(e)*.15);
    var k=near&&e.city===city()?'Happening in '+city():(low?'Selling fast':'Trending this week');
    if(low&&near&&e.city===city())k='Selling fast in '+city();
    return {e:e,k:k,s:dstr(e)+(low?', only '+l+' left':', '+(sameCity(e)?e.city:e.venue))};
  });
}
var order=[],idx=0;
function canShow(){return !stopped&&!over&&!document.hidden&&OK.indexOf(page())>-1&&ban.hidden&&!document.querySelector('dialog[open]')&&!document.documentElement.classList.contains('menu-open')}
function show(){
  if(!canShow()){schedule(8000);return}
  if(shown>=MAX)return;
  if(!order.length||idx>=order.length){order=pool();idx=0}
  if(!order.length)return;
  var it=order[idx++];
  $('nb-th').innerHTML=it.e.art;$('nb-k').textContent=it.k;$('nb-t').textContent=it.e.name;$('nb-s').textContent=it.s;$('nb-link').href='/event/'+it.e.slug;
  nb.hidden=false;void nb.offsetWidth;nb.classList.add('on');shown++;
  clearTimeout(hideT);hideT=setTimeout(hide,6000);
}
function hide(){if(over){hideT=setTimeout(hide,2000);return}nb.classList.remove('on');setTimeout(function(){if(!nb.classList.contains('on'))nb.hidden=true},450);schedule(22000)}
function schedule(ms){clearTimeout(timer);if(!stopped&&shown<MAX)timer=setTimeout(show,ms)}
function startNB(){if(started||stopped)return;started=true;schedule(5000)}
nb.addEventListener('mouseenter',function(){over=true});nb.addEventListener('mouseleave',function(){over=false});
$('nb-x').addEventListener('click',function(){stopped=true;try{sessionStorage.setItem('tica_nb_off','1')}catch(e){}clearTimeout(timer);clearTimeout(hideT);nb.classList.remove('on');setTimeout(function(){nb.hidden=true},400)});
$('nb-link').addEventListener('click',function(){nb.classList.remove('on');nb.hidden=true;schedule(30000)});
addEventListener('tica:navigate',function(){if(OK.indexOf(page())<0){nb.classList.remove('on');nb.hidden=true}});

if(load())startNB();else setTimeout(function(){ban.hidden=false},900);
window.TICA_consent=load;
})();
/* design script 18 */
/* v38: legal hub navigation */
(function(){
  var page=document.querySelector('.page[data-page="policies"]');if(!page)return;
  var links=[].slice.call(page.querySelectorAll('[data-pol]'));
  function hdr(){var t=document.querySelector('.topbar');if(t)page.style.setProperty('--hdr',t.offsetHeight+'px')}hdr();window.addEventListener('resize',hdr);
  function mark(id){links.forEach(function(a){if(a.dataset.pol===id){a.setAttribute('aria-current','true');var ul=a.closest('ul');if(ul&&ul.scrollWidth>ul.clientWidth)ul.scrollTo({left:a.offsetLeft-16,behavior:'smooth'})}else a.removeAttribute('aria-current')})}
  function jump(id,smooth){var el=document.getElementById('pol-'+id);if(!el)return;el.scrollIntoView({behavior:smooth?'smooth':'instant',block:'start'});mark(id)}
  function fromHash(){var m=location.pathname.match(/^\/policies\/([a-z]+)/);if(m){setTimeout(function(){jump(m[1],false)},0);setTimeout(function(){var el=document.getElementById('pol-'+m[1]);if(el&&Math.abs(el.getBoundingClientRect().top)>window.innerHeight*.5)jump(m[1],false)},450)}else mark('summary')}
  window.addEventListener('tica:navigate',fromHash);fromHash();
  page.addEventListener('click',function(e){var a=e.target.closest('[data-pol]');if(!a)return;e.preventDefault();e.stopPropagation();try{history.replaceState(null,'','/policies/'+a.dataset.pol)}catch(err){}jump(a.dataset.pol,!matchMedia('(prefers-reduced-motion: reduce)').matches)},true);
  if('IntersectionObserver' in window){var io=new IntersectionObserver(function(es){es.forEach(function(en){if(en.isIntersecting)mark(en.target.id.slice(4))})},{rootMargin:'-30% 0px -60% 0px'});[].forEach.call(page.querySelectorAll('.lg-sec'),function(s){io.observe(s)})}
})();
/* design script 19 */
/* v39: organiser workspace (Tica: live organiser data and actions) */
(function(){
var D=window.TICA_DATA&&TICA_DATA.dashboard;
if(!D){window.OD_bank=function(){return null};window.OD_after=function(){};return}
var $=function(i){return document.getElementById(i)};
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function N(n){return window.TICA_MONEY(n)}
/* an amount in a given currency (null = base currency) */
function Nc(n,c){var o=window.TICA_CUR;window.TICA_CUR=c||null;var s=N(n);window.TICA_CUR=o;return s}
var api=window.TICA_API;
function get(url){return fetch(url,{credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json().then(function(j){if(!r.ok){var e=new Error(j.message||'Something went wrong.');e.data=j;throw e}return j})})}
function errText(er){var e=er.data&&er.data.errors,k=e&&Object.keys(e)[0];return k?e[k][0]:er.message}
var ROLE=D.role||'owner',PERMS=D.permissions||['*'],PERM=function(k){return PERMS.indexOf('*')>-1||PERMS.indexOf(k)>-1},CAN={events:PERM('events.manage'),promote:PERM('promote.manage'),payouts:PERM('payouts.view'),withdraw:PERM('withdrawals.request'),refund:PERM('refunds.manage'),team:PERM('team.manage'),settings:PERM('settings.manage'),orders:PERM('orders.view'),checkin:PERM('checkin.run')};
var toastEl=document.createElement('div');toastEl.className='od-toast';toastEl.setAttribute('role','status');document.body.appendChild(toastEl);
function toast(m){toastEl.textContent=m;toastEl.classList.add('on');clearTimeout(toast.t);toast.t=setTimeout(function(){toastEl.classList.remove('on')},3200)}
var ICO={ok:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',warn:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg>',x:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>'};
var XBTN='<button class="sh-x" type="button" data-close aria-label="Close">'+ICO.x+'</button>';
var MO=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
function ds(r){return ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][new Date(r.ts).getDay()]+', '+r.day+' '+r.mon}
function evById(id){return D.events.filter(function(r){return r.id===+id})[0]}
var OD={ef:'up'};
window.OD_bank=function(){var a=D.payouts.account;return a&&a.verified?{name:a.name,bank:a.bank,num:a.last4}:null};
function reload(msg){if(msg)try{sessionStorage.setItem('tica_od_toast',msg)}catch(e){}location.reload()}
try{var pend=sessionStorage.getItem('tica_od_toast');if(pend){sessionStorage.removeItem('tica_od_toast');setTimeout(function(){toast(pend)},300)}}catch(e){}

/* organiser card and role-limited menu */
function drawAvatar(){[].forEach.call(document.querySelectorAll('.od-av'),function(el){el.textContent=D.org.initials;if(D.org.avatar){var im=new Image();im.alt='';im.onerror=function(){im.remove()};im.src=D.org.avatar;el.appendChild(im)}})}drawAvatar();

if(!CAN.events)[].forEach.call(document.querySelectorAll('.page[data-page=dashboard] a[href="/create"]'),function(a){a.hidden=true});
[].forEach.call(document.querySelectorAll('.od-tool'),function(a){var ar=a.dataset.area;if(ar==='events'&&!CAN.events||ar==='money'&&!CAN.payouts||ar==='owner'&&!PERM('team.manage'))a.hidden=true});
if(!CAN.withdraw&&$('od-wd-btn'))$('od-wd-btn').hidden=true;
/* More tools dropdown: remembers open or closed, and on phones floats below the menu bar */
(function(){var d=document.getElementById('od-more');if(!d)return;var sum=d.querySelector('summary'),list=d.querySelector('.od-more-l');
  if(!d.querySelector('.od-tool:not([hidden])')){d.hidden=true;return}
  var narrow=function(){return matchMedia('(max-width:860px)').matches};
  try{if(!narrow()&&localStorage.getItem('tica.moreTools')==='open')d.open=true}catch(e){}
  function place(){if(d.open&&narrow()){var r=sum.getBoundingClientRect();list.style.top=Math.round(r.bottom+8)+'px'}else list.style.top=''}
  d.addEventListener('toggle',function(){place();if(!narrow()){try{localStorage.setItem('tica.moreTools',d.open?'open':'closed')}catch(e){}}});
  addEventListener('resize',place);addEventListener('scroll',function(){if(d.open&&narrow())place()},{passive:true});
  document.addEventListener('click',function(e){if(d.open&&narrow()&&!d.contains(e.target))d.open=false});
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&d.open&&narrow()){d.open=false;sum.focus()}});
  list.addEventListener('click',function(e){if(e.target.closest('a')&&narrow())d.open=false});
})();

/* One organiser menu: rendered once by the server (components/shell/organizer). */
function osActive(key,title){[].forEach.call(document.querySelectorAll('#os-side .os-link[href]'),function(l){var on=l.dataset.panel===key||(!l.dataset.panel&&key==='checkin'&&l.getAttribute('href')==='/checkin');l.classList.toggle('is-active',on);if(on)l.setAttribute('aria-current','page');else l.removeAttribute('aria-current')});var t=document.getElementById('os-title');if(t&&title)t.textContent=title}
document.addEventListener('click',function(ev){var l=ev.target.closest('#os-side [data-panel]');if(!l||ev.metaKey||ev.ctrlKey||ev.shiftKey)return;ev.preventDefault();var p=l.dataset.panel;
  if(document.querySelector('.page[data-page=dashboard]:not([hidden])'))show(p,true);else window.TICA_go(p==='overview'?'dashboard':'dashboard/'+p)});
window.addEventListener('tica:navigate',function(){var pg=document.querySelector('.page:not([hidden])'),k=pg&&pg.dataset.page;if(k==='create')osActive('events',document.querySelector('[data-page=create] h1')?document.querySelector('[data-page=create] h1').textContent:'Create an event');else if(k==='checkin')osActive('checkin','Gate check-in')});

/* panels */
var PANELS=['overview','events','orders','payouts','promote','team','settings'];
function allowed(p){return !((p==='events'&&!PERM('events.view'))||(p==='promote'&&!CAN.promote)||(p==='orders'&&!CAN.orders)||(p==='payouts'&&!CAN.payouts)||(p==='team'&&!CAN.team)||(p==='settings'&&!CAN.settings))}
function show(p,push){
  if(PANELS.indexOf(p)<0||!allowed(p))p='overview';
  [].forEach.call(document.querySelectorAll('.od-p'),function(x){x.hidden=x.dataset.p!==p});
  osActive(p,{overview:'Overview',events:'Events',orders:'Orders and attendees',payouts:'Payouts',promote:'Promote',team:'Team',settings:'Settings'}[p]);
  if(push){try{history.replaceState(null,'','/dashboard'+(p==='overview'?'':'/'+p))}catch(e){}window.scrollTo({top:0,behavior:'instant'})}
  var DRAW={events:drawEvents,orders:drawAtt,payouts:drawPay,promote:drawPromote,team:drawTeam,overview:drawOverview,settings:drawSettings};
  DRAW[p]&&DRAW[p]();
  var wt=document.getElementById('workspace-title');if(wt)wt.textContent={overview:'Overview',events:'Events',orders:'Orders and attendees',payouts:'Payouts',promote:'Promote',team:'Team',settings:'Settings'}[p]||'Organiser dashboard';
}
function fromHash(){var m=location.pathname.match(/^\/dashboard(?:\/([a-z]+))?/);if(m)show(m[1]||'overview',false)}
window.addEventListener('tica:navigate',function(){setTimeout(fromHash,0)});
window.OD_after=function(){setTimeout(fromHash,0)};

/* overview */
function drawChart(range){
  var rows=D.chart[range]||[],vals=rows.map(function(r){return r[1]}),max=Math.max.apply(null,vals.concat([1])),total=vals.reduce(function(a,b){return a+b},0);
  $('db-chart').innerHTML=total?'<div class="bars" role="img" aria-label="Tickets sold, '+esc($('od-range').selectedOptions[0].text.toLowerCase())+': '+total+' in total">'+rows.map(function(r,i){return '<div class="b"><i style="height:'+Math.round(r[1]/max*100)+'%"><em>'+r[1]+'</em></i><span>'+(rows.length>16&&i%3||rows.length<=16&&i%2?'':esc(r[0]))+'</span></div>'}).join('')+'</div>':'<div class="od-empty-state compact"><i aria-hidden="true">'+ICO.warn+'</i><h3>No ticket sales in this period</h3><p>Share an event link to bring people to your ticket page.</p><button class="btn btn-line" type="button" data-panel="promote">Share an event</button></div>';
}
$('od-range').innerHTML='<option value="14">Last 14 days</option><option value="30">Last 30 days</option><option value="all">All time</option>';
$('od-range').addEventListener('change',function(){drawOverviewKpis(this.value);drawChart(this.value)});
function drawOverview(){
  var s=D.setup,doneN=[s.identity,s.published,s.payout].filter(Boolean).length,setup=$('od-setup');
  setup.hidden=doneN===3||!CAN.payouts;
  if(!setup.hidden){
    var next=!s.identity?['Verify your identity so you can be paid.','Verify identity',D.setup.kycUrl]:!s.payout?['Add the bank account your sales should go to.','Add bank account',null]:['Publish your first event.','Create an event','/create'];
    var step=function(done,label){return '<li class="'+(done?'is-done':'')+'"><span class="ov-tick" aria-hidden="true">'+(done?ICO.ok:'')+'</span>'+label+(done?'<span class="t-sr"> (done)</span>':'')+'</li>'};
    setup.innerHTML='<div class="ov-setup__text"><h2 id="od-setup-h">Finish setting up to get paid</h2><p>'+doneN+' of 3 done. '+next[0]+'</p></div><ol class="ov-steps">'+step(s.identity,'Verify your identity')+step(s.published,'Publish your first event')+step(s.payout,'Add your payout account')+'</ol>'+
      (next[2]?'<a class="t-btn t-btn--primary" href="'+esc(next[2])+'">'+next[1]+'</a>':'<button class="t-btn t-btn--primary" type="button" data-panel="payouts">'+next[1]+'</button>');
  }
  if($('od-paydot'))$('od-paydot').hidden=s.payout||!CAN.payouts;
  var e=D.next;
  if(e){
    var days=Math.max(0,Math.round((e.ts-Date.now())/864e5)),pct=e.cap?Math.min(100,Math.round(e.sold/e.cap*100)):0;
    $('od-next').innerHTML='<div class="t-card__head"><h2 class="t-card__title">Next event</h2><span class="t-badge t-badge--blue">'+(days===0?'Today':days===1?'Tomorrow':'In '+days+' days')+'</span></div>'+
      '<div class="ov-next__flyer flyer" aria-hidden="true">'+e.art+'</div>'+
      '<h3 class="ov-next__name">'+esc(e.name)+'</h3><p class="t-muted">'+ds(e)+', '+esc(e.time)+'</p>'+
      '<div class="ov-cap"><span><b>'+e.sold.toLocaleString('en-NG')+'</b>'+(e.cap?' of '+e.cap.toLocaleString('en-NG'):'')+' sold</span>'+(e.cap?'<span class="t-muted">'+pct+'%</span>':'')+'</div>'+(e.cap?'<div class="ov-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'+pct+'" aria-label="Tickets sold"><i style="width:'+pct+'%"></i></div>':'')+
      (e.tiers.length>1?'<details class="ov-tiers"><summary>By ticket type</summary><ul>'+e.tiers.map(function(t){return '<li><span>'+esc(t.name)+'</span><span>'+t.sold+(t.total!=null?' / '+t.total:'')+'</span></li>'}).join('')+'</ul></details>':'')+
      '<div class="ov-next__act">'+(CAN.events?'<button class="t-btn t-btn--secondary" type="button" data-manage="'+e.id+'">Manage</button>':'')+(CAN.checkin?'<a class="t-btn t-btn--ghost" href="/checkin">Check in guests</a>':'')+'</div>';
  }else $('od-next').innerHTML='<div class="t-empty"><span class="t-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg></span><h3>No upcoming events</h3><p>Your next event will show here with its ticket sales.</p>'+(CAN.events?'<a class="t-btn t-btn--primary" href="/create">Create an event</a>':'')+'</div>';
}
function drawOverviewKpis(range){
  var k=D.kpisByRange&&D.kpisByRange[range]||D.kpis,per=$('od-range').selectedOptions[0]?$('od-range').selectedOptions[0].text.toLowerCase():'';
  $('db-kpis').innerHTML=[['Revenue',N(k.gross),'orders','Gross ticket sales, '+per],['Tickets sold',k.sold.toLocaleString('en-NG'),'events','Across all your events, '+per],['Page views',k.views.toLocaleString('en-NG'),'promote','Visits to your event pages, '+per]].map(function(x){
    return '<button class="t-stat ov-stat" type="button" data-panel="'+x[2]+'" title="'+esc(x[3])+'"><span class="t-stat__label">'+x[0]+'</span><span class="t-stat__value">'+esc(x[1])+'</span><span class="t-stat__note">'+esc(x[3])+'</span></button>'}).join('');
}
function renderDash(){
  if($('db-org'))$('db-org').textContent=D.org.name;
  $('db-hi').textContent='Welcome back, '+D.org.name.split(' ')[0];
  var nw=window.TICA_NEW;$('db-flag').hidden=!nw;if(nw)$('db-flag').textContent=nw.name+(nw.status==='draft'?' is saved as a draft.':' is now live.');
  drawOverviewKpis($('od-range').value);
  drawChart($('od-range').value);
  var ord=D.orders.slice(0,5);
  $('db-ord').innerHTML=ord.length?'<table class="t-table t-table--cards ov-ord"><thead><tr><th scope="col">Buyer</th><th scope="col">Tickets</th><th scope="col" class="t-num">Amount</th><th scope="col">When</th></tr></thead><tbody>'+ord.map(function(o){return '<tr><td data-label="Buyer"><span class="ov-buyer"><span class="t-avatar" style="--av:28px" aria-hidden="true">'+esc((o.name||'?')[0].toUpperCase())+'</span>'+esc(o.name)+'</span></td><td data-label="Tickets">'+esc(o.tickets)+'</td><td data-label="Amount" class="t-num">'+(o.amount?Nc(o.amount,o.cur):'Free')+'</td><td data-label="When" class="t-muted">'+esc(o.ago)+'</td></tr>'}).join('')+'</tbody></table>'
    :'<div class="t-empty"><span class="t-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3z"/></svg></span><h3>No orders yet</h3><p>Orders appear here as soon as someone buys a ticket.</p>'+(CAN.promote?'<button class="t-btn t-btn--secondary" type="button" data-panel="promote">Share your event</button>':'')+'</div>';
  drawOverview();fromHash();
}
window.TICA_renderDash=renderDash;

/* events */
function evRows(){var q=($('ev-q')&&$('ev-q').value||'').trim().toLowerCase();return D.events.filter(function(r){return r.state===OD.ef&&(!q||(r.name+' '+(r.city||'')+' '+(r.venue||'')).toLowerCase().indexOf(q)>-1)}).sort(function(a,b){return OD.ef==='past'?b.ts-a.ts:a.ts-b.ts})}
OD.evPage=0;var EV_PER=10;
function evStatus(r){var cls=r.status==='On sale'?'green':r.status==='Sales paused'||r.status==='Cancelling'?'orange':r.status==='Cancelled'?'red':r.state==='draft'?'':'blue';return '<span class="t-badge'+(cls?' t-badge--'+cls:'')+'">'+esc(r.status)+'</span>'}
function drawEvents(){
  [].forEach.call(document.querySelectorAll('[data-ef]'),function(b){b.setAttribute('aria-selected',b.dataset.ef===OD.ef?'true':'false')});
  ['up','draft','past'].forEach(function(k){var c=document.querySelector('[data-efc='+k+']');if(c)c.textContent=D.events.filter(function(r){return r.state===k}).length});
  var create=document.querySelector('.od-events-create');if(create)create.hidden=!CAN.events;
  var all=evRows(),pages=Math.max(1,Math.ceil(all.length/EV_PER));OD.evPage=Math.min(OD.evPage,pages-1);
  var rows=all.slice(OD.evPage*EV_PER,OD.evPage*EV_PER+EV_PER),q=$('ev-q')&&$('ev-q').value.trim();
  if(!rows.length){
    var empty={up:['No upcoming events','Create your next event and it shows here with its sales.'],draft:['No drafts','Events you save without publishing wait here.'],past:['No past events yet','Finished events move here, with their final sales.']}[OD.ef];
    $('od-evlist').innerHTML='<div class="t-card"><div class="t-empty"><span class="t-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg></span><h3>'+(q?'No events match "'+esc(q)+'"':empty[0])+'</h3><p>'+(q?'Try another name or city.':empty[1])+'</p>'+(!q&&CAN.events&&OD.ef!=='past'?'<a class="t-btn t-btn--primary" href="/create">Create an event</a>':'')+'</div></div>';return}
  $('od-evlist').innerHTML='<div class="t-table-wrap"><table class="t-table t-table--cards ev-table"><thead><tr><th scope="col">Event</th><th scope="col">Date</th><th scope="col">Sold</th><th scope="col" class="t-num">Revenue</th><th scope="col">Status</th><th scope="col"><span class="t-sr">Actions</span></th></tr></thead><tbody>'+rows.map(function(r){
    var pct=r.cap?Math.min(100,Math.round(r.sold/r.cap*100)):0,menu=[];
    if(r.state==='draft'){if(CAN.events)menu.push('<a href="'+esc(r.editUrl)+'">Continue editing</a>')}
    else{if(CAN.events&&r.state!=='past')menu.push('<button type="button" data-manage="'+r.id+'">Manage sales and settings</button>');if(CAN.events&&r.editUrl)menu.push('<a href="'+esc(r.editUrl)+'">Edit details</a>');menu.push('<a href="/event/'+esc(r.slug)+'" target="_blank" rel="noopener">View event page</a>');if(CAN.orders)menu.push('<button type="button" data-ev-orders="'+r.id+'">See attendees</button>');if(r.registrationUrl)menu.push('<a href="'+esc(r.registrationUrl)+'">Registrations</a>');if(r.state==='past'&&CAN.payouts)menu.push('<button type="button" data-panel="payouts">Get paid</button>')}
    var tags=[r.mode!=='In person'?r.mode:'',r.payer==='me'?'Fees included':'',r.regMode==='corporate'?'Registration':''].filter(Boolean);
    return '<tr><td data-label=""><div class="ev-cell"><span class="ev-thumb flyer" aria-hidden="true">'+r.art+'</span><span><span class="ev-name">'+esc(r.name)+'</span>'+(tags.length?'<span class="ev-tags">'+tags.map(function(t){return '<span class="t-badge">'+esc(t)+'</span>'}).join('')+'</span>':'')+'</span></div></td>'+
      '<td data-label="Date">'+esc(ds(r))+'<div class="t-muted">'+esc(r.time)+'</div></td>'+
      '<td data-label="Sold" class="ev-sold">'+(r.state==='draft'?'<span class="t-muted">Not on sale</span>':'<span><b>'+r.sold.toLocaleString('en-NG')+'</b>'+(r.cap?' of '+r.cap.toLocaleString('en-NG'):'')+'</span>'+(r.cap?'<div class="ov-bar"><i style="width:'+pct+'%"></i></div>':''))+'</td>'+
      '<td data-label="Revenue" class="t-num">'+(r.state==='draft'?'<span class="t-muted">—</span>':Nc(r.revenue,r.cur))+'</td>'+
      '<td data-label="Status">'+evStatus(r)+'</td>'+
      '<td class="ev-actions">'+(menu.length?'<details class="t-menu"><summary class="t-icon-btn" aria-label="Actions for '+esc(r.name)+'"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg></summary><div class="t-menu__list">'+menu.join('')+'</div></details>':'')+'</td></tr>'}).join('')+'</tbody></table></div>'+
    (pages>1?'<div class="t-pager"><span>'+(OD.evPage*EV_PER+1)+'–'+(OD.evPage*EV_PER+rows.length)+' of '+all.length+'</span><span class="t-pager__btns"><button class="t-btn t-btn--secondary t-btn--sm" type="button" data-evpage="-1"'+(OD.evPage===0?' disabled':'')+'>Previous</button><button class="t-btn t-btn--secondary t-btn--sm" type="button" data-evpage="1"'+(OD.evPage>=pages-1?' disabled':'')+'>Next</button></span></div>':'');
}
document.addEventListener('input',function(e){if(e.target.id==='ev-q'){OD.evPage=0;drawEvents()}});
document.addEventListener('click',function(e){var p=e.target.closest('[data-evpage]');if(p){OD.evPage+=+p.dataset.evpage;drawEvents();return}
  var o=e.target.closest('[data-ev-orders]');if(o){var f=$('od-fe');show('orders',true);if(f){f.value=o.dataset.evOrders;f.dispatchEvent(new Event('change',{bubbles:true}))}return}
  var m=document.querySelector('.t-menu[open]');if(m&&!m.contains(e.target))m.removeAttribute('open')});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){var m=document.querySelector('.t-menu[open]');if(m){m.removeAttribute('open');m.querySelector('summary').focus()}}});

/* event control dialog */
var curEv=null;
function seg(name,val,opts){return '<div class="od-seg" role="radiogroup">'+opts.map(function(o){return '<label><input type="radio" name="'+name+'" value="'+o[0]+'"'+(val===o[0]?' checked':'')+'><span>'+o[1]+'</span></label>'}).join('')+'</div>'}
function sw(id,on,label,sub){return '<li><span><b>'+label+'</b>'+(sub?'<small>'+sub+'</small>':'')+'</span><input class="ck-sw" type="checkbox" id="'+id+'"'+(on?' checked':'')+' aria-label="'+esc(label)+'"></li>'}
function feeEx(r,payer){var t=r.tiers.filter(function(x){return x.price>0})[0];if(!t)return 'Free tickets have no fee.';var p=t.price,f=Math.round(p*window.TICA_FEE_PCT)/100;return payer==='buyer'?'A '+N(p)+' '+esc(t.name)+' ticket: the buyer pays '+N(p+f)+', you get '+N(p)+'.':'A '+N(p)+' '+esc(t.name)+' ticket: the buyer pays '+N(p)+', you get '+N(p-f)+'.'}
var VISN={public:'Shown on Discover and in search.',unlisted:'Hidden from Discover. Anyone with the link can buy.',private:'Only people you invite by email can come.'};
function openEv(id){
  var r=evById(id),online=r.mode!=='In person';curEv=r.id;window.TICA_CUR=r.cur||null;
  $('od-ev-body').innerHTML='<div class="sh-head"><div><h2 id="od-ev-h">'+esc(r.name)+'</h2><p>'+ds(r)+', '+esc(r.time)+'. '+esc(r.mode)+'</p></div>'+XBTN+'</div>'+
  '<form class="od-form od-evf" id="od-evf" novalidate>'+
  '<section><h3>Ticket sales</h3><ul class="od-sw">'+sw('ev-on',r.status==='On sale','Selling tickets','Pause to stop new orders. Existing tickets stay valid.')+'</ul>'+
  '<div class="od-tt" role="table" aria-label="Ticket types"><div class="od-ttr th" role="row"><span role="columnheader">Ticket</span><span role="columnheader">Price</span><span role="columnheader">Sold</span><span role="columnheader">Available</span></div>'+r.tiers.map(function(t,i){return '<div class="od-ttr" role="row"><span role="cell"><b>'+esc(t.name)+'</b></span><span role="cell">'+(t.price?N(t.price):'Free')+'</span><span role="cell">'+t.sold+'</span><span role="cell">'+(t.total==null?'<input type="text" value="Unlimited" disabled aria-label="'+esc(t.name)+' has no limit">':'<input type="number" min="'+t.sold+'" value="'+t.total+'" aria-label="Total '+esc(t.name)+' tickets" data-tq="'+i+'">')+'</span></div>'}).join('')+'</div>'+
  (r.mode==='Online'&&r.tiers.length?'':'<div class="od-newt" id="ev-newt" hidden><div class="od-row3"><div class="inp"><label for="ev-nt-n">Ticket name</label><input id="ev-nt-n" type="text" placeholder="VIP"></div><div class="inp"><label for="ev-nt-p">Price</label><input id="ev-nt-p" type="number" min="0" step="500" value="0"></div><div class="inp"><label for="ev-nt-q">Quantity</label><input id="ev-nt-q" type="number" min="1" placeholder="Unlimited"></div></div></div><button class="od-add" type="button" id="ev-addt">+ Add a ticket type</button>')+'</section>'+
  '<section><h3>Who pays the '+esc(String(window.TICA_FEE_PCT).replace(/\.0+$/,''))+'% booking fee?</h3>'+seg('ev-payer',r.payer,[['buyer','Buyers pay it'],['me','I\'ll include it']])+'<p class="od-ex" id="ev-feeex">'+feeEx(r,r.payer)+'</p><p class="od-fine">Applies to new orders. People who already bought aren\'t affected.</p></section>'+
  (online?'<section><h3>Online access</h3><div class="od-row2"><div class="inp"><label for="ev-pl">Platform</label><select id="ev-pl">'+['Zoom','Google Meet','YouTube Live','Instagram Live','Other link'].map(function(p){return '<option'+(p===r.platform?' selected':'')+'>'+p+'</option>'}).join('')+'</select></div><div class="inp"><label for="ev-rv">Show the link</label><select id="ev-rv"><option value="15">15 minutes before</option><option value="30">30 minutes before</option><option value="60">1 hour before</option><option value="0">Right after purchase</option></select></div></div><div class="inp"><label for="ev-ln">Join link</label><input id="ev-ln" type="url" value="" placeholder="Leave blank to keep the current link"></div><p class="od-fine">Only ticket holders see the link, on their ticket and by email. Each ticket opens it on one device at a time.</p></section>':'')+
  '<section><h3>Who can find it</h3>'+seg('ev-vis',r.visibility,[['public','Public'],['unlisted','Link only'],['private','Private']])+'<p class="od-fine" id="ev-visn">'+VISN[r.visibility]+'</p></section>'+
  '<section><h3>Event page</h3><ul class="od-sw">'+sw('ev-left',r.showRemaining,'Show tickets left','')+sw('ev-wait',r.waitlist,'Waitlist when sold out','')+sw('ev-tr',r.transfers,'Allow ticket transfers','Buyers can pass a ticket to a friend before the event.')+'</ul>'+
  '<div class="inp"><label for="ev-rf">Refunds</label><select id="ev-rf"><option value="standard">TICA standard: refunds only if cancelled or changed</option><option value="7">Refunds up to 7 days before the event</option><option value="48">Refunds up to 48 hours before the event</option></select></div></section>'+
  '<div class="od-dlgb"><button class="btn btn-hot" type="submit">Save changes</button><a class="btn btn-line" href="'+esc(r.editUrl)+'">Full event editor</a><a class="btn btn-line" href="/event/'+r.slug+'">View page</a></div><p class="hint" id="ev-m" role="status"></p>'+
  '<section class="od-danger"><h3>Postpone or cancel</h3><p>Buyers are told by email. If you cancel, all '+r.sold+' buyers get a full refund, booking fee included, from your sales.</p><div id="ev-postf" hidden><div class="od-row2"><div class="inp"><label for="ev-nd">New date</label><input id="ev-nd" type="date"></div><div class="inp"><label for="ev-ntm">Start time</label><input id="ev-ntm" type="time"></div></div></div><div class="od-dlgb"><button class="btn btn-line" type="button" id="ev-post">Change the date</button><button class="btn od-red" type="button" id="ev-cancel">Cancel event</button></div><p class="hint" id="ev-dz" role="status"></p></section>'+
  '</form>';
  $('ev-rf').value=['standard','7','48'].indexOf(r.refund)>-1?r.refund:'standard';if($('ev-rv'))$('ev-rv').value=String(r.reveal);
  $('od-ev-dlg').showModal();
}
$('od-ev-dlg').addEventListener('close',function(){window.TICA_CUR=null});
document.addEventListener('change',function(ev){
  var t=ev.target;if(!curEv||!t.closest('#od-evf'))return;var r=evById(curEv);
  if(t.name==='ev-payer')$('ev-feeex').textContent=feeEx(r,t.value);
  if(t.name==='ev-vis')$('ev-visn').textContent=VISN[t.value];
});
document.addEventListener('submit',function(ev){
  if(ev.target.id!=='od-evf')return;ev.preventDefault();var f=ev.target,r=evById(curEv),btn=f.querySelector('[type=submit]');
  var totals={};[].forEach.call(f.querySelectorAll('[data-tq]'),function(i){var t=r.tiers[+i.dataset.tq];totals[i.dataset.tq]={ticket_id:t.id,variation:t.variation,total:+i.value}});
  var body={selling:$('ev-on').checked,payer:f.querySelector('[name=ev-payer]:checked').value,visibility:f.querySelector('[name=ev-vis]:checked').value,show_remaining:$('ev-left').checked,waitlist:$('ev-wait').checked,transfers:$('ev-tr').checked,refund:$('ev-rf').value,totals:Object.keys(totals).map(function(k){return totals[k]})};
  if($('ev-ln')){body.join_url=$('ev-ln').value.trim();body.reveal=+$('ev-rv').value}
  var nt=$('ev-newt');if(nt&&!nt.hidden&&$('ev-nt-n').value.trim())body.new_ticket={name:$('ev-nt-n').value.trim(),price:+$('ev-nt-p').value||0,quantity:+$('ev-nt-q').value||null};
  btn.disabled=true;$('ev-m').textContent='Saving...';
  api('/tica-api/organizer/events/'+r.id,body).then(function(){reload('Saved. Changes are live on your event page.')},function(er){btn.disabled=false;$('ev-m').textContent=errText(er)});
});

/* attendees */
var ATT=[],attT=null,attCursor=null,attLoading=false,attRequest=0;
function drawAtt(){
  var fe=$('od-fe');if(fe.options.length<2)D.events.forEach(function(r){if(r.state!=='draft')fe.add(new Option(r.name,r.id))});
  $('od-fs').innerHTML='<option value="">Any status</option><option value="paid">Paid</option><option value="checked_in">Checked in</option><option value="refunded">Refunded</option><option value="guest">Guest</option>'.replace('value="'+($('od-fs').value||'~')+'"','value="'+$('od-fs').value+'" selected');
  attRequest++;attLoading=false;attCursor=null;ATT=[];clearTimeout(attT);attT=setTimeout(function(){loadAtt(false)},180);
}
function loadAtt(append){
  if(attLoading||append&&!attCursor)return;
  var seq=++attRequest,url='/tica-api/organizer/attendees?'+attQuery()+(append?'&cursor='+encodeURIComponent(attCursor):'');
  attLoading=true;$('od-att').setAttribute('aria-busy','true');$('od-att-more').disabled=true;$('od-att-msg').textContent=append?'Loading more attendees…':'';
  if(!append)$('od-att').innerHTML='<div class="t-table-wrap or-skel">'+[1,2,3,4,5].map(function(){return '<span class="t-skel" style="height:44px;margin:12px 16px"></span>'}).join('')+'</div>';
  get(url).then(function(r){if(seq!==attRequest)return;ATT=append?ATT.concat(r.rows):r.rows;attCursor=r.nextCursor||null;paintAtt(r)},function(er){if(seq===attRequest){$('od-att-msg').textContent=er.message;$('od-att-more').hidden=true}}).then(function(){if(seq===attRequest){attLoading=false;$('od-att').removeAttribute('aria-busy');$('od-att-more').disabled=false}});
}
function attQuery(){return 'q='+encodeURIComponent($('od-q').value.trim())+'&event='+$('od-fe').value+'&status='+$('od-fs').value+'&from='+($('od-from').value||'')+'&to='+($('od-to').value||'')}
function attStatus(s){var c=s==='Paid'?'green':s==='Checked in'?'blue':s==='Refunded'||s==='Refund requested'?'orange':'';return '<span class="t-badge'+(c?' t-badge--'+c:'')+'">'+esc(s)+'</span>'}
function paintAtt(r){
  var rows=ATT,filtered=!!($('od-q').value.trim()||$('od-fe').value||$('od-fs').value||$('od-from').value||$('od-to').value);
  $('od-clear').hidden=!filtered;
  if(!rows.length){$('od-att').innerHTML='<div class="t-card"><div class="t-empty"><span class="t-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/></svg></span><h3>'+(filtered?'No one matches these filters':'No attendees yet')+'</h3><p>'+(filtered?'Try a different name, event or date.':'People who buy tickets or get added as guests appear here.')+'</p>'+(filtered?'<button class="t-btn t-btn--secondary" type="button" id="od-clear2">Clear filters</button>':(CAN.promote?'<button class="t-btn t-btn--secondary" type="button" data-panel="promote">Share your event</button>':''))+'</div></div>';$('od-att-more').hidden=true;$('od-att-msg').textContent='';return}
  $('od-att').innerHTML='<div class="t-table-wrap"><table class="t-table t-table--cards or-table"><thead><tr><th scope="col">Attendee</th><th scope="col">Ticket</th><th scope="col">Order</th><th scope="col" class="t-num">Amount</th><th scope="col">Status</th><th scope="col"><span class="t-sr">Actions</span></th></tr></thead><tbody>'+rows.map(function(a,i){
    var menu=[];if(a.bookingId){menu.push('<a href="/organizer/event-booking/details/'+encodeURIComponent(a.bookingId)+'">Order details</a>');if(CAN.events||PERM('orders.manage'))menu.push('<button type="button" data-resend="'+i+'">Resend tickets</button>')}if(a.refundable&&CAN.refund)menu.push('<button type="button" class="is-danger" data-refund="'+i+'">Refund order</button>');
    return '<tr><td data-label=""><span class="ov-buyer"><span class="t-avatar" style="--av:32px" aria-hidden="true">'+esc((a.name||'?')[0].toUpperCase())+'</span><span><span class="or-name">'+esc(a.name)+'</span><span class="t-muted or-sub">'+esc(a.email)+'</span></span></span></td>'+
      '<td data-label="Ticket">'+esc(a.tier)+'<div class="t-muted or-sub">'+esc(a.event)+'</div></td>'+
      '<td data-label="Order">#'+esc(a.ref)+'</td>'+
      '<td data-label="Amount" class="t-num">'+(a.amount?Nc(a.amount,a.cur):'Free')+'</td>'+
      '<td data-label="Status">'+attStatus(a.status==='Paid'&&!a.orderTotal?'Free':a.status)+'</td>'+
      '<td class="ev-actions">'+(menu.length?'<details class="t-menu"><summary class="t-icon-btn" aria-label="Actions for '+esc(a.name)+'"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg></summary><div class="t-menu__list">'+menu.join('')+'</div></details>':'')+'</td></tr>'}).join('')+'</tbody></table></div>';
  $('od-att-more').hidden=!(r&&r.more);$('od-att-msg').textContent=r&&r.more?'Showing '+rows.length+'. Load more, or export the full list.':rows.length+' attendee'+(rows.length===1?'':'s');
}
document.addEventListener('click',function(e){if(e.target.id==='od-clear'||e.target.id==='od-clear2'){['od-q','od-from','od-to'].forEach(function(id){$(id).value=''});$('od-fe').value='';$('od-fs').value='';drawAtt()}});
['od-from','od-to'].forEach(function(id){$(id).addEventListener('change',drawAtt)});
['od-q','od-fe','od-fs'].forEach(function(id){$(id).addEventListener('input',drawAtt)});
var refundI=null;
document.addEventListener('click',function(ev){
  var t=ev.target;
  var r=t.closest('[data-resend]');if(r){var a=ATT[+r.dataset.resend];r.disabled=true;api('/tica-api/organizer/orders/'+a.bookingId+'/resend').then(function(){r.disabled=false;toast('Tickets sent again to '+a.buyerEmail+'.')},function(er){r.disabled=false;toast(errText(er))});return}
  var f=t.closest('[data-refund]');if(f){var a2=ATT[+f.dataset.refund];refundI=+f.dataset.refund;
    $('od-wd-body').innerHTML='<div class="sh-head"><div><h2 id="od-wd-h">Refund '+esc(a2.name)+'?</h2><p>Order #'+esc(a2.ref)+', '+esc(a2.tier)+' for '+esc(a2.event)+'.</p></div>'+XBTN+'</div><div class="od-form">'+seg('rf-amt','full',[['full','Full, '+Nc(a2.orderTotal,a2.cur)],['ticket','Ticket price only, '+Nc(a2.orderTickets,a2.cur)]])+'<div class="inp"><label for="rf-why">Reason</label><input id="rf-why" type="text" maxlength="300" value="Requested by the organiser"></div><p class="od-fine">TICA reviews the request, then the money goes back to the buyer\'s card or account within 7 to 10 working days and comes out of this event\'s sales. The whole order is refunded and its tickets stop working.</p><div class="od-dlgb"><button class="btn od-red" type="button" id="rf-go">Refund order</button><button class="btn btn-line" type="button" data-close>Keep order</button></div><p class="hint" id="rf-m" role="status"></p></div>';
    $('od-wd').showModal();return}
  if(t.id==='rf-go'){var a3=ATT[refundI];t.disabled=true;api('/tica-api/organizer/orders/'+a3.bookingId+'/refund',{amount:document.querySelector('[name=rf-amt]:checked').value,reason:$('rf-why').value}).then(function(){$('od-wd').close();loadAtt();toast('Refund requested. '+a3.name+' will get an email once it\'s sent.')},function(er){t.disabled=false;$('rf-m').textContent=errText(er)});return}
  var m=t.closest('[data-manage]');if(m){openEv(m.dataset.manage);return}
  var p=t.closest('.page[data-page=dashboard] [data-panel], .od-dlg [data-panel]');if(p){var dl0=p.closest('dialog');if(dl0)dl0.close();show(p.dataset.panel,true);return}
  var ef=t.closest('[data-ef]');if(ef){OD.ef=ef.dataset.ef;OD.evPage=0;drawEvents();return}
  var d=t.closest('[data-dlg]');if(d){if(d.dataset.dlg==='od-msg'||d.dataset.dlg==='od-comp')fillDlg(d.dataset.dlg);$(d.dataset.dlg).showModal();return}
  if(t.closest('[data-close]')){var dl=t.closest('dialog');if(dl)dl.close();return}
  if(t.tagName==='DIALOG'&&t.classList.contains('od-dlg')){t.close();return}
  if(t.id==='od-export'){location.href='/tica-api/organizer/attendees.csv?'+attQuery();toast('Attendee list downloading.');return}
  if(t.id==='od-att-more'){loadAtt(true);return}
  if(t.id==='ev-addt'){var nt=$('ev-newt');nt.hidden=false;t.hidden=true;$('ev-nt-n').focus();return}
  if(t.id==='ev-post'){var pf=$('ev-postf');if(pf.hidden){pf.hidden=false;t.textContent='Save new date';$('ev-dz').textContent='Tickets stay valid. Buyers are emailed the new date and can ask for a refund.';return}
    if(!$('ev-nd').value||!$('ev-ntm').value){$('ev-dz').textContent='Choose the new date and start time.';return}
    t.disabled=true;api('/tica-api/organizer/events/'+curEv+'/date',{date:$('ev-nd').value,time:$('ev-ntm').value}).then(function(){reload('New date saved. Buyers are being told.')},function(er){t.disabled=false;$('ev-dz').textContent=errText(er)});return}
  if(t.id==='ev-cancel'){if(!t.dataset.sure){t.dataset.sure='1';t.textContent='Yes, cancel and refund everyone';$('ev-dz').textContent='This can\'t be undone. Press again to confirm.';return}
    t.disabled=true;api('/tica-api/organizer/events/'+curEv+'/cancel',{reason:'Cancelled by the organiser'}).then(function(){reload('Cancellation requested. TICA will refund your buyers and tell them by email.')},function(er){t.disabled=false;$('ev-dz').textContent=errText(er)});return}
  var cp=t.closest('[data-copy]');if(cp){var l=D.links.filter(function(x){return x.id===+cp.dataset.copy})[0],ev2=l.eventId?evById(l.eventId):(D.next||D.events[0]),u=location.origin+(ev2?'/event/'+ev2.slug:'/discover')+'?ref='+l.code;try{navigator.clipboard.writeText(u).then(function(){toast('Link copied.')},function(){toast(u)})}catch(e){toast(u)}return}
  if(t.id==='od-wd-btn'||t.closest('#od-wd-btn')){if(CAN.withdraw)openWd();return}
  if(t.id==='wd-go'){sendWd();return}
  if(t.id==='bk-change'){OD.changing=true;drawPay();return}
});
function fillDlg(id){
  var opts=D.events.filter(function(r){return r.state==='up'});
  if(id==='od-msg'){$('od-ma').innerHTML=opts.map(function(r){return '<option value="'+r.id+':all">Everyone with a ticket to '+esc(r.name)+' ('+r.sold+')</option><option value="'+r.id+':unscanned">'+esc(r.name)+', people who haven\'t checked in yet</option>'}).join('')||'<option value="">No upcoming events</option>';$('od-mt').value='';$('od-mt').placeholder='Hi! Doors open at 9:30 PM. Bring your QR code and a valid ID. See you tonight.'}
  if(id==='od-comp'){$('od-gv').innerHTML=opts.filter(function(r){return r.regMode!=='corporate'}).map(function(r){return '<option value="'+r.id+'">'+esc(r.name)+'</option>'}).join('');tiersFor()}
}
function tiersFor(){var r=evById($('od-gv').value);$('od-gt').innerHTML=r?r.tiers.map(function(t,i){return '<option value="'+i+'">'+esc(t.name)+'</option>'}).join(''):''}
$('od-gv').addEventListener('change',tiersFor);
(function(){var c=document.querySelector('#od-msg-f .od-chk');if(c)c.innerHTML='<input type="checkbox" checked disabled> Also send as SMS <small>(SMS isn\'t set up on TICA yet, so this goes by email only)</small>'})();
$('od-msg-f').addEventListener('submit',function(e){e.preventDefault();var v=$('od-ma').value.split(':'),txt=$('od-mt').value.trim(),b=e.target.querySelector('[type=submit]');if(!v[0]){$('od-msg-m').textContent='You have no upcoming events to message.';return}if(txt.length<5){$('od-msg-m').textContent='Write your message first.';return}
  b.disabled=true;api('/tica-api/organizer/message',{event_id:+v[0],audience:v[1],message:txt}).then(function(r){b.disabled=false;$('od-msg').close();toast(r.message)},function(er){b.disabled=false;$('od-msg-m').textContent=errText(er)})});
$('od-comp-f').addEventListener('submit',function(e){e.preventDefault();var n=$('od-gn').value.trim(),m=$('od-ge').value.trim(),b=e.target.querySelector('[type=submit]');if(!n||m.indexOf('@')<1){$('od-comp-m').textContent='Add the guest\'s name and email.';return}
  var r=evById($('od-gv').value),t=r&&r.tiers[+$('od-gt').value];if(!t){$('od-comp-m').textContent='Choose an event and ticket.';return}
  b.disabled=true;api('/tica-api/organizer/guests',{event_id:r.id,ticket_id:t.id,variation:t.variation,name:n,email:m}).then(function(){b.disabled=false;$('od-comp').close();$('od-comp-f').reset();$('od-comp-m').textContent='';loadAtt();toast('Free ticket sent to '+m+'.')},function(er){b.disabled=false;$('od-comp-m').textContent=errText(er)})});

/* payouts */
var P=D.payouts;
function drawPay(){
  $('od-avail').textContent=N(P.available);$('od-pend').textContent=N(P.pending);$('od-paid').textContent=N(P.paid);
  /* balances in other currencies are kept apart and paid out by TICA */
  var bal=$('od-bal');[].forEach.call(bal.querySelectorAll('.od-b.cur'),function(n){n.remove()});
  (P.currencies||[]).forEach(function(c){bal.insertAdjacentHTML('beforeend','<div class="t-card t-stat od-b cur"><span class="t-stat__label">'+esc(c.code)+' balance</span><b class="t-stat__value">'+esc(Nc(c.amount,c.cur))+'</b><span class="t-stat__note">Paid out by TICA support. Email hello@tica.ng to withdraw.</span></div>')});
  [].forEach.call(document.querySelectorAll('[name=od-sched]'),function(r){r.checked=r.value===P.schedule});
  var v=$('od-bank-view'),a=P.account;
  if(P.kyc!=='verified'){
    var st=P.staged;
    v.innerHTML=st?'<div class="od-acct"><i aria-hidden="true">'+esc(st.bank.replace(/[^A-Z]/g,'').slice(0,3)||st.bank.slice(0,2))+'</i><div><b>'+esc(st.name)+'</b><span>'+esc(st.bank)+', •••• '+esc(st.last4)+'</span></div>'+(st.status==='name_mismatch'?'<em class="od-badb">Name mismatch</em>':'<em class="od-waitb">Waiting</em>')+'</div><p class="od-fine">'+(st.status==='name_mismatch'?'This account\'s name doesn\'t match your approved name. Add an account in your name.':P.kyc==='pending'?'We\'re checking your identity. This account switches on as soon as you\'re approved.':'Finish your identity check and this account switches on when you\'re approved.')+'</p><a class="btn btn-line" href="'+esc(D.setup.kycUrl)+'">Open identity and bank</a>'
      :'<p class="od-fine">'+(P.kyc==='pending'?'We\'re checking your identity. Add your bank account now and it switches on when you\'re approved.':'Verify your identity and add your bank account in one step. Payouts only go to an account in your name.')+'</p><a class="btn btn-hot" href="'+esc(D.setup.kycUrl)+'">'+(P.kyc==='pending'?'Add bank account':'Verify identity and add bank')+'</a>';
  }else if(P.review&&!OD.changing&&!(a&&a.verified)){
    v.innerHTML='<div class="od-acct"><i aria-hidden="true">'+esc(P.review.bank.slice(0,2))+'</i><div><b>'+esc(P.review.name)+'</b><span>'+esc(P.review.bank)+', •••• '+esc(P.review.last4)+'</span></div>'+(P.review.status==='rejected'?'<em class="od-badb">Not approved</em>':'<em class="od-waitb">In review</em>')+'</div><p class="od-fine">'+(P.review.status==='rejected'?esc(P.review.reason||'TICA couldn\'t approve this account.')+' Add an account in your verified name.':'The name at the bank doesn\'t match your verified name, so TICA is checking it. This usually takes one working day.')+'</p><button class="od-lk" type="button" id="bk-change">Use a different account</button>';
  }else if(a&&a.verified&&!OD.changing){
    v.innerHTML='<div class="od-acct"><i aria-hidden="true">'+esc(a.bank.replace(/[^A-Z]/g,'').slice(0,3)||a.bank.slice(0,2))+'</i><div><b>'+esc(a.name)+'</b><span>'+esc(a.bank)+', •••• '+esc(a.last4)+'</span></div><em class="od-okb">'+ICO.ok+'Verified</em></div>'+(a.held?'<p class="od-note">'+ICO.warn+'New account. Payouts start 24 hours after it was added.</p>':'')+(P.review&&P.review.status==='needs_review'?'<p class="od-note">'+ICO.warn+'Your new account ('+esc(P.review.bank)+' •••• '+esc(P.review.last4)+') is with TICA for review. Payouts keep going here until it\'s approved.</p>':P.review&&P.review.status==='rejected'?'<p class="od-note">'+ICO.warn+'TICA didn\'t approve '+esc(P.review.bank)+' •••• '+esc(P.review.last4)+'. '+esc(P.review.reason)+'</p>':'')+'<button class="od-lk" type="button" id="bk-change">Change account</button>';
  }else{
    v.innerHTML='<form class="od-form" id="bk-f" novalidate><p class="od-fine">Payouts can only go to an account in your name, '+esc(D.org.owner)+', or your registered business name.</p>'+
    '<div class="inp"><label for="bk-b">Bank</label><select id="bk-b"><option value="">Loading banks...</option></select></div>'+
    '<div class="inp"><label for="bk-n">Account number</label><input id="bk-n" type="text" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="10 digits"></div>'+
    '<div class="od-res" id="bk-r" aria-live="polite"></div>'+
    '<div id="bk-otp" hidden><div class="inp"><label for="bk-pw">Your TICA password</label><input id="bk-pw" type="password" autocomplete="current-password"></div>'+(D.profile&&D.profile.totp?'<div class="inp"><label for="bk-o">Code from your authenticator app</label><input id="bk-o" type="text" inputmode="numeric" maxlength="11" autocomplete="one-time-code"></div>':'')+'<p class="od-fine">This keeps anyone else from changing where your money goes.</p></div>'+
    '<button class="btn btn-hot" type="submit" id="bk-save" disabled>Save account</button><p class="hint" id="bk-m" role="status"></p></form>';
    (OD.banks=OD.banks||get('/tica-api/organizer/banks').catch(function(er){OD.banks=null;throw er})).then(function(r){$('bk-b').innerHTML='<option value="">Choose your bank</option>'+r.banks.map(function(b){return '<option value="'+esc(b.code)+'">'+esc(b.name)+'</option>'}).join('');if(r.stale)$('bk-m').textContent='The bank list is a saved copy because the bank service is slow. Your bank should still be here.'},function(er){$('bk-b').innerHTML='<option value="">Banks unavailable</option>';$('bk-m').textContent=er.message});
    $('bk-n').addEventListener('input',resolve);$('bk-b').addEventListener('change',resolve);
    $('bk-f').addEventListener('submit',saveBank);
  }
  $('od-hist').innerHTML=P.history.length?'<div class="t-table-wrap"><table class="t-table t-table--cards"><thead><tr><th scope="col">Date</th><th scope="col">Reference</th><th scope="col" class="t-num">Amount</th><th scope="col">To</th><th scope="col">Status</th></tr></thead><tbody>'+P.history.map(function(h){var c=h.status==='Paid'?'green':/fail|reject|revers/i.test(h.status)?'red':'orange';return '<tr><td data-label="Date">'+esc(h.date)+'</td><td data-label="Reference">'+esc(String(h.ref).slice(0,13))+'</td><td data-label="Amount" class="t-num">'+N(h.amount)+'</td><td data-label="To">'+esc(h.to)+'</td><td data-label="Status"><span class="t-badge t-badge--'+c+'">'+esc(h.status)+'</span></td></tr>'}).join('')+'</tbody></table></div>':'<div class="t-empty"><span class="t-empty__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 10l9-6 9 6M5 10v9h14v-9M9 19v-5h6v5"/></svg></span><h3>No payouts yet</h3><p>'+(P.available>0?N(P.available)+' is ready to withdraw'+(P.account&&P.account.verified?'.':' once your bank account is added.'):'Money from ticket sales appears here once it is sent to your bank.')+'</p></div>';
  drawOverview();
}
var resT=null,resolved=null;
function resolve(){
  var n=$('bk-n');n.value=n.value.replace(/\D/g,'').slice(0,10);var b=$('bk-b'),r=$('bk-r');resolved=null;$('bk-save').disabled=true;$('bk-otp').hidden=true;clearTimeout(resT);
  if(n.value.length<10||!b.value){r.innerHTML=n.value.length&&n.value.length<10?'<p class="od-fine">'+(10-n.value.length)+' more digit'+(n.value.length===9?'':'s')+'</p>':'';return}
  r.innerHTML='<p class="od-chk2"><i class="od-spin" aria-hidden="true"></i>Checking with '+esc(b.selectedOptions[0].text)+'…</p>';
  resT=setTimeout(function(){
    api('/tica-api/organizer/bank/resolve',{bank_code:b.value,account_number:n.value}).then(function(x){
      resolved={bank_code:b.value,account_number:n.value,review:!x.matches};
      r.innerHTML=x.matches?'<div class="od-name"><span>Account name</span><b>'+esc(x.name)+'</b><p>'+ICO.ok+'Matches your verified name</p></div>'
        :'<div class="od-name bad"><span>Account name</span><b>'+esc(x.name)+'</b><p>'+ICO.warn+'This doesn\'t match your verified name. If it\'s your business account, send it to TICA to review. We\'ll keep paying your current account until it\'s approved.</p></div>';
      $('bk-save').textContent=x.matches?'Save account':'Send for review';
      $('bk-otp').hidden=false;$('bk-save').disabled=false;$('bk-pw').focus();
    },function(er){r.innerHTML='<p class="od-bad">'+ICO.warn+esc(errText(er))+(er.data&&er.data.retry?' <button type="button" class="od-lk" id="bk-retry">Try again</button>':'')+'</p>'});
  },500);
}
function saveBank(e){e.preventDefault();if(!resolved)return;var pw=$('bk-pw').value,o=$('bk-o');$('bk-m').textContent='';
  if(!pw){$('bk-m').textContent='Enter your password to confirm.';$('bk-pw').focus();return}
  if(o&&!o.value.trim()){$('bk-m').textContent='Enter the code from your authenticator app.';o.focus();return}
  $('bk-save').disabled=true;api('/tica-api/organizer/bank',{bank_code:resolved.bank_code,account_number:resolved.account_number,password:pw,otp:o?o.value.trim():null}).then(function(x){reload(x.status==='needs_review'?'Sent to TICA for review. We\'ll email you within one working day.':'Bank account saved and verified.')},function(er){$('bk-save').disabled=false;$('bk-m').textContent=errText(er)})}
document.addEventListener('click',function(e){if(e.target.id==='bk-retry')resolve()});
[].forEach.call(document.querySelectorAll('[name=od-sched]'),function(r){r.addEventListener('change',function(){api('/tica-api/organizer/payout-schedule',{schedule:r.value}).then(function(){P.schedule=r.value;toast(r.value==='auto'?'We\'ll send your sales to your bank automatically.':'Your money waits until you press Withdraw.')},function(er){toast(errText(er));drawPay()})})});
function openWd(){
  var a=P.account,m=P.method,min=m?Math.max(m.min,1):1,max=m&&m.max?Math.min(m.max,P.available):P.available;
  $('od-wd-body').innerHTML='<div class="sh-head"><div><h2 id="od-wd-h">Withdraw to your bank</h2><p>Available: '+N(P.available)+'</p></div>'+XBTN+'</div>'+
  (!a||!a.verified?'<p class="od-fine">Add a payout account first, so we know where to send your money.</p><div class="od-dlgb"><button class="btn btn-hot" type="button" data-panel="payouts">Add bank account</button></div>':
  !m?'<p class="od-fine">Withdrawals aren\'t open right now. Contact TICA support.</p>':
  P.available<min?'<p class="od-fine">Nothing to withdraw right now. You can withdraw once you have at least '+N(min)+'.</p>':
  '<div class="od-form"><div class="inp"><label for="wd-a">Amount</label><input id="wd-a" type="number" min="'+min+'" max="'+max+'" value="'+max+'"></div>'+
  '<dl class="od-wdl"><div><dt>To</dt><dd>'+esc(a.name)+'<br>'+esc(a.bank)+', •••• '+esc(a.last4)+'</dd></div><div><dt>Transfer fee</dt><dd id="wd-fee">'+(m.fixed||m.percent?'':'Free')+'</dd></div><div><dt>Arrives</dt><dd>'+(a.held?'After your new account\'s 24-hour safety hold':'Next business day')+'</dd></div></dl>'+
  '<div class="od-dlgb"><button class="btn btn-hot" type="button" id="wd-go">Withdraw '+N(max)+'</button></div><p class="hint" id="wd-m" role="status"></p></div>');
  var inp=$('wd-a');if(inp){var fee=function(){var v=+inp.value||0,f=Math.round((m.fixed+v*m.percent/100)*100)/100;$('wd-fee').textContent=f?N(f):'Free';$('wd-go').textContent='Withdraw '+N(v)};inp.addEventListener('input',fee);fee()}
  $('od-wd').showModal();
}
function sendWd(){
  var a=+$('wd-a').value||0,m=P.method,min=Math.max(m.min,1),max=m.max?Math.min(m.max,P.available):P.available;if(a<min||a>max){$('wd-m').textContent='Enter between '+N(min)+' and '+N(max)+'.';return}
  $('wd-go').disabled=true;
  api('/tica-api/organizer/withdraw',{amount:a}).then(function(r){
    var b=P.account;
    $('od-wd-body').innerHTML='<div class="od-sent"><i>'+ICO.ok+'</i><h2 id="od-wd-h">'+N(a)+' is on its way</h2><p>It goes to '+esc(b.bank)+' •••• '+esc(b.last4)+' in the next payout run. We\'ll email you when it\'s sent.</p><p class="od-fine">Reference '+esc(r.reference)+'</p><button class="btn btn-line" type="button" data-close>Done</button></div>';
    $('od-wd').addEventListener('close',function(){reload()},{once:true});
  },function(er){$('wd-go').disabled=false;$('wd-m').textContent=errText(er)});
}

/* promote */
function drawPromote(){
  $('od-cv').innerHTML='<option value="p:10">10% off</option><option value="p:20">20% off</option><option value="p:50">50% off</option><option value="f:1000">'+esc(N(1000))+' off</option>';
  $('od-codes').innerHTML=D.codes.length?D.codes.map(function(c){var lab=c.label.indexOf('fixed:')===0?N(+c.label.slice(6))+' off':c.label;return '<li><code>'+esc(c.code)+'</code><span><b>'+esc(lab)+'</b><small>'+esc(c.scope)+'</small></span><span class="od-use"><small>'+c.used+(c.max?' of '+c.max:'')+' used</small><i class="bar"><i style="width:'+(c.max?Math.round(c.used/c.max*100):0)+'%"></i></i></span><em class="st'+(c.active?' new':'')+'">'+(c.active?'Active':'Used up')+'</em></li>'}).join(''):'<li class="od-empty">No discount codes yet.</li>';
  $('od-links').innerHTML=D.links.length?D.links.map(function(l){return '<li><span><b>'+esc(l.name)+'</b><small>'+l.clicks.toLocaleString('en-NG')+' clicks, '+l.sales+' sales</small></span><button class="od-lk" type="button" data-copy="'+l.id+'">Copy link</button></li>'}).join(''):'<li class="od-empty">No tracking links yet.</li>';
  var plans=D.promotionPlans||[],hasPlans=plans.length>0;
  $('od-promo-plan').innerHTML=plans.map(function(p){return '<option value="'+p.id+'">'+esc(p.name)+' · '+esc(Nc(p.price,{code:p.currency,symbol:p.currency,position:'left'}))+' / '+p.days+' days</option>'}).join('')||'<option value="">No promotion plans available</option>';
  $('od-promo-event').innerHTML=D.events.filter(function(r){return r.state==='up'}).map(function(r){return '<option value="'+r.id+'">'+esc(r.name)+'</option>'}).join('')||'<option value="">No published upcoming event</option>';
  if(!$('od-promo-start').value)$('od-promo-start').value=new Date().toISOString().slice(0,10);
  $('od-promo-balance').textContent=N(D.kpis.available||0);$('od-promo-f').hidden=!hasPlans;$('od-promo-empty').hidden=hasPlans;
  $('od-campaigns').innerHTML=(D.promotions||[]).length?(D.promotions||[]).map(function(c){return '<article class="od-campaign"><span><b>'+esc(c.event)+'</b><small>'+esc(c.plan)+' · '+esc(c.starts||'Date pending')+'</small></span><span><em class="st">'+esc(c.status.replace(/_/g,' '))+'</em><small>'+esc(c.currency)+' '+Number(c.price).toLocaleString('en-NG')+'</small></span><div>'+(c.canPay?'<button class="btn btn-hot" type="button" data-payprom="'+c.id+'">Pay from balance</button>':'')+(c.canCancel?'<button class="od-lk red" type="button" data-cancelprom="'+c.id+'">Cancel</button>':'')+'</div></article>'}).join(''):'<p class="od-empty">No promotion requests yet.</p>';
  $('od-pixels').innerHTML=(D.trackingPixels||[]).length?(D.trackingPixels||[]).map(function(p){return '<li><span><b>'+esc(p.label)+'</b><small>'+esc(p.provider.toUpperCase())+' · '+esc(p.identifier)+'</small></span><em class="st">'+esc(p.status.replace(/_/g,' '))+'</em></li>'}).join(''):'<li class="od-empty">No tracking IDs submitted.</li>';
}
$('od-code-f').addEventListener('submit',function(e){e.preventDefault();var c=$('od-cn').value.trim().toUpperCase().replace(/\s+/g,''),v=$('od-cv').value.split(':');if(!/^[A-Z0-9]{3,30}$/.test(c)){$('od-code-m').textContent='Codes need 3 to 30 letters or numbers.';return}
  api('/tica-api/organizer/codes',{code:c,type:v[0]==='p'?'percentage':'fixed',value:+v[1],max_uses:+$('od-cl').value||null}).then(function(){reload(c+' is ready to share.')},function(er){$('od-code-m').textContent=errText(er)})});
$('od-link-f').addEventListener('submit',function(e){e.preventDefault();var n=$('od-ln').value.trim();if(!n)return;api('/tica-api/organizer/links',{name:n}).then(function(){reload('Link created. Copy it to share.')},function(er){toast(errText(er))})});
$('od-promo-f').addEventListener('submit',function(e){e.preventDefault();var b=e.target.querySelector('[type=submit]'),fd=new FormData(),plan=$('od-promo-plan').value,eventId=$('od-promo-event').value;if(!plan||!eventId){$('od-promo-m').textContent='Choose a plan and a published upcoming event.';return}
  fd.append('plan_id',plan);fd.append('event_id',eventId);fd.append('starts_at',$('od-promo-start').value);fd.append('headline',$('od-promo-headline').value.trim());fd.append('body',$('od-promo-body').value.trim());fd.append('destination_url',$('od-promo-url').value.trim());var creative=$('od-promo-creative').files&&$('od-promo-creative').files[0];if(creative)fd.append('creative',creative);b.disabled=true;
  fetch('/tica-api/organizer/promotions',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest'},body:fd}).then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok){var er=new Error(j.message||'Could not create promotion.');er.data=j;throw er}return j})}).then(function(r){reload(r.message)},function(er){b.disabled=false;$('od-promo-m').textContent=errText(er)})});
$('od-pixel-f').addEventListener('submit',function(e){e.preventDefault();var b=e.target.querySelector('[type=submit]'),id=$('od-pixel-id').value.trim(),label=$('od-pixel-label').value.trim();if(!/^[A-Za-z0-9_-]{5,100}$/.test(id)||label.length<2){$('od-pixel-m').textContent='Add a valid provider ID and label.';return}b.disabled=true;api('/tica-api/organizer/tracking-pixels',{provider:$('od-pixel-provider').value,pixel_identifier:id,label:label}).then(function(r){reload(r.message)},function(er){b.disabled=false;$('od-pixel-m').textContent=errText(er)})});
document.addEventListener('click',function(e){var pay=e.target.closest('[data-payprom]'),cancel=e.target.closest('[data-cancelprom]');if(pay){var password=window.prompt('Confirm this payment with your organizer password.');if(!password)return;pay.disabled=true;api('/tica-api/organizer/promotions/'+pay.dataset.payprom+'/pay',{current_password:password}).then(function(r){reload(r.message)},function(er){pay.disabled=false;toast(errText(er))});return}if(cancel){if(!cancel.dataset.sure){cancel.dataset.sure='1';cancel.textContent='Confirm cancel';return}cancel.disabled=true;api('/tica-api/organizer/promotions/'+cancel.dataset.cancelprom+'/cancel').then(function(r){reload(r.message)},function(er){cancel.disabled=false;toast(errText(er))})}});

/* team */
function drawRoles(){var box=$('od-roles'),R=D.roleSummaries||{};if(!box)return;box.innerHTML=[['owner','Owner'],['manager','Manager'],['finance','Finance'],['gate','Gate staff']].map(function(r){var x=R[r[0]]||{can:[],cannot:[]};return '<div class="od-role-card"><h3>'+r[1]+'</h3><ul class="od-can">'+x.can.map(function(l){return '<li>'+esc(l)+'</li>'}).join('')+'</ul>'+(x.cannot.length?'<ul class="od-cannot">'+x.cannot.map(function(l){return '<li>'+esc(l)+'</li>'}).join('')+'</ul>':'')+'</div>'}).join('')}drawRoles();
function drawTeam(){
  var T=D.team||[];
  $('od-team').innerHTML='<div class="t-table-wrap"><table class="t-table t-table--cards or-table"><thead><tr><th scope="col">Member</th><th scope="col">Role</th><th scope="col">Access</th><th scope="col"><span class="t-sr">Actions</span></th></tr></thead><tbody>'+T.map(function(m){
    var edit=m.id&&CAN.team&&(m.roleKey==='manager'||m.roleKey==='finance');
    return '<tr><td data-label=""><span class="ov-buyer"><span class="t-avatar" style="--av:32px" aria-hidden="true">'+esc((m.name||'?')[0].toUpperCase())+'</span><span><span class="or-name">'+esc(m.name)+'</span><span class="t-muted or-sub">'+esc(m.email)+'</span></span></span></td>'+
      '<td data-label="Role">'+(edit?'<select class="t-select tm-role" data-rolefor="'+m.id+'" aria-label="Role for '+esc(m.name)+'"><option value="manager"'+(m.roleKey==='manager'?' selected':'')+'>Manager</option><option value="finance"'+(m.roleKey==='finance'?' selected':'')+'>Finance</option></select>':'<span class="t-badge'+(m.roleKey==='owner'||!m.id?' t-badge--blue':'')+'">'+esc(m.role)+'</span>')+'</td>'+
      '<td data-label="Access">'+esc(m.scope)+'</td>'+
      '<td class="ev-actions">'+(m.id&&CAN.team?'<button class="t-btn t-btn--ghost t-btn--sm tm-remove" type="button" data-unteam="'+m.id+'">Remove</button>':'')+'</td></tr>'}).join('')+'</tbody></table></div>'+
    (T.length<=1?'<p class="t-help tm-solo">It\'s just you for now. Invite gate staff to scan tickets, or a manager to help run events.</p>':'');
  if($('od-inv-open'))$('od-inv-open').hidden=!CAN.team;
}
document.addEventListener('change',function(e){var s=e.target.closest('[data-rolefor]');if(!s)return;s.disabled=true;api('/tica-api/organizer/team/'+s.dataset.rolefor+'/role',{role:s.value}).then(function(){reload('Role updated. It applies from their next click.')},function(er){s.disabled=false;toast(errText(er))})});
document.addEventListener('click',function(e){var b=e.target.closest('[data-unteam]');if(!b)return;if(!b.dataset.sure){b.dataset.sure='1';b.textContent='Remove access?';return}api('/tica-api/organizer/team/'+b.dataset.unteam+'/remove').then(function(){reload('Access removed.')},function(er){toast(errText(er))})});
$('od-ir').innerHTML='<option value="gate">Gate staff</option><option value="manager">Manager</option><option value="finance">Finance</option>';
var IR_HELP={gate:'Can only scan tickets at the gate.',manager:'Runs events and orders. Cannot see payouts or change the bank account.',finance:'Sees money and exports. Cannot edit events.'};
function irHelp(){$('od-ir-help').textContent=IR_HELP[$('od-ir').value]||''}$('od-ir').addEventListener('change',irHelp);irHelp();
/* settings tabs */
function stab(k,focus){[].forEach.call(document.querySelectorAll('[data-stab]'),function(t){var on=t.dataset.stab===k;t.classList.toggle('is-on',on);t.setAttribute('aria-selected',on?'true':'false');t.tabIndex=on?0:-1;if(on&&focus)t.focus()});[].forEach.call(document.querySelectorAll('[data-spane]'),function(x){x.hidden=x.dataset.spane!==k})}
document.addEventListener('click',function(e){var t=e.target.closest('[data-stab]');if(t)stab(t.dataset.stab)});
$('od-stabs').addEventListener('keydown',function(e){if(e.key!=='ArrowRight'&&e.key!=='ArrowLeft')return;var ts=[].slice.call(this.querySelectorAll('[data-stab]')),i=ts.indexOf(document.activeElement);if(i<0)return;e.preventDefault();stab(ts[(i+(e.key==='ArrowRight'?1:ts.length-1))%ts.length].dataset.stab,true)});
if(location.hash==='#password')stab('security');
$('od-inv').addEventListener('submit',function(e){e.preventDefault();var m=$('od-ie').value.trim();if(m.indexOf('@')<1||m.indexOf('.')<0){$('od-inv-m').textContent='Enter a valid email address.';return}
  api('/tica-api/organizer/team',{email:m,role:$('od-ir').value}).then(function(){$('od-inv-dlg').close();reload('Invite sent to '+m+'.')},function(er){$('od-inv-m').textContent=errText(er)})});

/* settings */
function drawSettings(){
  var p=D.profile;$('od-pn').value=p.name;$('od-pa').value=p.about;$('od-pe').value=p.email||'';$('od-pu').value=p.username||'';
  $('od-paddr').value=p.address||'';$('od-pcity').value=p.city||'';$('od-pstate').value=p.state||'';$('od-pcountry').value=p.country||'';$('od-pzip').value=p.zipCode||'';
  $('od-pi').value=p.instagram||'';$('od-pw').value=p.phone;
  $('od-pt').value=p.tiktok||'';$('od-pweb').value=p.website||'';$('od-pfb').value=p.facebook||'';$('od-px').value=p.twitter||'';$('od-pli').value=p.linkedin||'';$('od-pyt').value=p.youtube||'';
  $('od-photo-ini').textContent=D.org.initials;$('od-pcp-row').hidden=true;var img=$('od-photo-preview');img.onerror=function(){img.hidden=true};if(p.photoUrl){img.src=p.photoUrl;img.hidden=false}else{img.removeAttribute('src');img.hidden=true}
  var state=$('od-mfa-state'),sec=$('od-security-link'),on=p.totp||p.mfa;if(state){state.textContent=p.totp?'App code on':p.mfa?'Email code on':'Off';state.classList.toggle('on',!!on)}if(sec&&p.securityUrl)sec.href=p.securityUrl;
  draw2fa();
  // Each switch names its own setting in data-pref, so adding one can't shift the others.
  [].forEach.call(document.querySelectorAll('[data-p=settings] [data-pref]'),function(s){s.checked=!!D.prefs[s.dataset.pref]});
}
document.addEventListener('change',function(e){var s=e.target.closest('[data-pref]');if(!s)return;var body={};body[s.dataset.pref]=s.checked;api('/tica-api/organizer/prefs',body).then(function(){D.prefs[s.dataset.pref]=s.checked;toast('Saved.')},function(er){s.checked=!s.checked;toast(errText(er))})});
$('od-photo').addEventListener('change',function(){var f=this.files&&this.files[0],img=$('od-photo-preview');fieldErr('photo','');if(!f)return;
  if(['image/jpeg','image/png','image/webp','image/gif'].indexOf(f.type)<0){this.value='';fieldErr('photo','Choose a JPG, PNG, WebP or GIF image.');return}
  if(f.size>3*1024*1024){this.value='';fieldErr('photo','That photo is over 3 MB. Choose a smaller one.');return}
  $('od-photo-name').textContent=f.name;var u=URL.createObjectURL(f);img.src=u;img.hidden=false;img.onload=function(){URL.revokeObjectURL(u)}});
var PROF_FIELDS={name:'od-pn',about:'od-pa',email:'od-pe',username:'od-pu',current_password:'od-pcp',country:'od-pcountry',city:'od-pcity',state:'od-pstate',zip_code:'od-pzip',address:'od-paddr',instagram:'od-pi',phone:'od-pw',tiktok:'od-pt',website:'od-pweb',facebook:'od-pfb',twitter:'od-px',linkedin:'od-pli',youtube:'od-pyt',photo:'od-photo',file:'od-photo'};
function fieldErr(name,msg){var el=$(PROF_FIELDS[name]||name);if(!el)return false;var box=el.closest('.inp')||el.closest('.od-profile-photo')||el.parentNode,old=box.querySelector('.od-err');if(old)old.remove();
  if(name==='photo'||name==='file'){el=$('od-photo')}
  if(!msg){el.removeAttribute('aria-invalid');el.removeAttribute('aria-describedby');return true}
  var m=document.createElement('small');m.className='od-err';m.id=el.id+'-err';m.textContent=msg;box.appendChild(m);el.setAttribute('aria-invalid','true');el.setAttribute('aria-describedby',m.id);return true}
function clearProfErrs(){Object.keys(PROF_FIELDS).forEach(function(k){fieldErr(k,'')})}
$('od-prof').addEventListener('input',function(e){var id=e.target.id;Object.keys(PROF_FIELDS).forEach(function(k){if(PROF_FIELDS[k]===id)fieldErr(k,'')});if(id==='od-pe')$('od-pcp-row').hidden=e.target.value.trim().toLowerCase()===(D.profile.email||'').toLowerCase()});
$('od-prof').addEventListener('submit',function(e){e.preventDefault();var b=e.target.querySelector('[type=submit]'),fd=new FormData();clearProfErrs();$('od-prof-m').textContent='';b.disabled=true;
  [['name','od-pn'],['about','od-pa'],['email','od-pe'],['username','od-pu'],['current_password','od-pcp'],['country','od-pcountry'],['city','od-pcity'],['state','od-pstate'],['zip_code','od-pzip'],['address','od-paddr'],['instagram','od-pi'],['phone','od-pw'],['tiktok','od-pt'],['website','od-pweb'],['facebook','od-pfb'],['twitter','od-px'],['linkedin','od-pli'],['youtube','od-pyt']].forEach(function(x){fd.append(x[0],$(x[1]).value.trim())});
  var photo=$('od-photo').files&&$('od-photo').files[0];if(photo)fd.append('photo',photo);
  fetch('/tica-api/organizer/profile',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest'},body:fd}).then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok){var er=new Error(j.message||'Could not save profile.');er.data=j;throw er}return j})}).then(function(r){b.disabled=false;$('od-prof-m').textContent='';if(r.redirect){location.href=r.redirect;return}reload('Profile saved.')},function(er){b.disabled=false;var errs=er.data&&er.data.errors,first=null;if(errs){Object.keys(errs).forEach(function(k){if(fieldErr(k,errs[k][0])&&!first)first=$(PROF_FIELDS[k]||k)})}if(first){first.focus();toast('Check the highlighted fields.')}else{$('od-prof-m').textContent=errText(er);toast(errText(er))}})});
$('od-pass').addEventListener('submit',function(e){e.preventDefault();var b=e.target.querySelector('[type=submit]'),n=$('od-pass-new').value,c=$('od-pass-confirm').value;$('od-pass-m').textContent='';
  if(n!==c){$('od-pass-m').textContent='Password confirmation does not match.';return}b.disabled=true;
  api('/tica-api/organizer/password',{current_password:$('od-pass-current').value,new_password:n,new_password_confirmation:c}).then(function(){b.disabled=false;e.target.reset();toast('Password updated.')},function(er){b.disabled=false;$('od-pass-m').textContent=errText(er)})});

/* two-factor (authenticator app) */
function draw2fa(){var b=$('od-2fa'),p=D.profile;if(!b)return;
  b.innerHTML=p.totp?'<div class="od-2fa-row"><span class="od-2fa-ic on" aria-hidden="true">'+ICO.ok+'</span><div><b>On since '+esc(p.totpSince||'')+'</b><small>'+p.totpCodesLeft+' of 8 recovery codes left'+(p.totpCodesLeft<3?'. Make new ones soon.':'')+'</small></div></div><div class="od-2fa-act"><button class="btn btn-line" type="button" data-2fa="codes">New recovery codes</button><button class="btn btn-line od-red-l" type="button" data-2fa="off">Turn off</button></div>'
    :'<div class="od-2fa-row"><span class="od-2fa-ic" aria-hidden="true">'+ICO.warn+'</span><div><b>Off</b><small>Anyone with your password can sign in.</small></div></div><div class="od-2fa-act"><button class="btn btn-hot" type="button" data-2fa="on">Turn on</button></div>'}
function tfaOpen(html){$('od-2fa-body').innerHTML=html;var d=$('od-2fa-dlg');if(!d.open)d.showModal();var f=d.querySelector('input');if(f)f.focus()}
function tfaErr(id,er){var m=$(id);if(m)m.textContent=errText(er)}
function tfaCodes(title,codes,note){return '<div class="sh-head"><div><h2 id="od-2fa-h">'+title+'</h2><p>'+note+'</p></div>'+XBTN+'</div><ol class="od-rc">'+codes.map(function(c){return '<li><code>'+esc(c)+'</code></li>'}).join('')+'</ol><div class="od-dlgb"><button class="btn btn-line" type="button" id="tfa-copy">Copy codes</button><button class="btn btn-line" type="button" id="tfa-dl">Download</button><button class="btn btn-hot" type="button" id="tfa-done">I\'ve saved them</button></div><p class="hint" id="tfa-m" role="status"></p>'}
var TFA_CODES=[];
document.addEventListener('click',function(e){var t=e.target,a=t.closest('[data-2fa]');
  if(a){var k=a.dataset['2fa'];
    if(k==='on')tfaOpen('<div class="sh-head"><div><h2 id="od-2fa-h">Turn on two-factor</h2><p>Step 1 of 3. Confirm it\'s you.</p></div>'+XBTN+'</div><form class="od-form" id="tfa-f1" novalidate><div class="inp"><label for="tfa-pw">Your password</label><input id="tfa-pw" type="password" autocomplete="current-password"></div><div class="od-dlgb"><button class="btn btn-hot" type="submit">Continue</button><button class="btn btn-line" type="button" data-close>Cancel</button></div><p class="hint" id="tfa-m" role="status"></p></form>');
    else tfaOpen('<div class="sh-head"><div><h2 id="od-2fa-h">'+(k==='off'?'Turn off two-factor?':'Make new recovery codes')+'</h2><p>'+(k==='off'?'You\'ll sign in with just your password. Your bank account changes will only need your password too.':'Your old recovery codes stop working straight away.')+'</p></div>'+XBTN+'</div><form class="od-form" id="tfa-f-'+k+'" novalidate><div class="inp"><label for="tfa-pw">Your password</label><input id="tfa-pw" type="password" autocomplete="current-password"></div><div class="inp"><label for="tfa-otp">Code from your app, or a recovery code</label><input id="tfa-otp" type="text" inputmode="numeric" maxlength="11" autocomplete="one-time-code"></div><div class="od-dlgb"><button class="btn '+(k==='off'?'od-red':'btn-hot')+'" type="submit">'+(k==='off'?'Turn off':'Make new codes')+'</button><button class="btn btn-line" type="button" data-close>Cancel</button></div><p class="hint" id="tfa-m" role="status"></p></form>');
    return}
  if(t.id==='tfa-copy'){navigator.clipboard&&navigator.clipboard.writeText(TFA_CODES.join('\n')).then(function(){$('tfa-m').textContent='Copied.'});return}
  if(t.id==='tfa-dl'){var u=URL.createObjectURL(new Blob(['TICA recovery codes\nEach code works once.\n\n'+TFA_CODES.join('\n')+'\n'],{type:'text/plain'})),l=document.createElement('a');l.href=u;l.download='tica-recovery-codes.txt';l.click();setTimeout(function(){URL.revokeObjectURL(u)},1000);return}
  if(t.id==='tfa-done'){$('od-2fa-dlg').close();reload('Two-factor settings saved.');return}
});
document.addEventListener('submit',function(e){var f=e.target;if(!/^tfa-f/.test(f.id))return;e.preventDefault();var btn=f.querySelector('[type=submit]');btn.disabled=true;$('tfa-m').textContent='';
  var done=function(){btn.disabled=false};
  if(f.id==='tfa-f1'){api('/tica-api/organizer/two-factor/start',{password:$('tfa-pw').value}).then(function(r){
      tfaOpen('<div class="sh-head"><div><h2 id="od-2fa-h">Scan this with your app</h2><p>Step 2 of 3. Open Google Authenticator, Authy or 1Password, add an account, and scan the code.</p></div>'+XBTN+'</div><div class="od-qr">'+r.qr+'</div><p class="od-fine">Can\'t scan it? Enter this key instead:</p><p class="od-key"><code>'+esc(r.secret)+'</code></p><form class="od-form" id="tfa-f2" novalidate><div class="inp"><label for="tfa-code">Step 3 of 3. Enter the 6-digit code your app shows</label><input id="tfa-code" type="text" inputmode="numeric" maxlength="7" autocomplete="one-time-code"></div><div class="od-dlgb"><button class="btn btn-hot" type="submit">Turn on</button><button class="btn btn-line" type="button" data-close>Cancel</button></div><p class="hint" id="tfa-m" role="status"></p></form>')},function(er){done();tfaErr('tfa-m',er)});return}
  if(f.id==='tfa-f2'){api('/tica-api/organizer/two-factor/confirm',{code:$('tfa-code').value}).then(function(r){TFA_CODES=r.recovery_codes;tfaOpen(tfaCodes('Two-factor is on','Save these recovery codes somewhere safe, away from your phone. Each one signs you in once if you lose your phone. We won\'t show them again.',''))},function(er){done();tfaErr('tfa-m',er)});return}
  var k=f.id.slice(6),url=k==='off'?'/tica-api/organizer/two-factor/disable':'/tica-api/organizer/two-factor/recovery-codes';
  api(url,{password:$('tfa-pw').value,otp:$('tfa-otp').value}).then(function(r){if(k==='off'){$('od-2fa-dlg').close();reload('Two-factor is off.');return}TFA_CODES=r.recovery_codes;tfaOpen(tfaCodes('New recovery codes','Your old codes no longer work. Save these somewhere safe. We won\'t show them again.',''))},function(er){done();tfaErr('tfa-m',er)});
});
/* create page uses the organiser's saved defaults */
(function(){var pr=D.prefs,f=document.getElementById('create-form');if(!f)return;var me=f.querySelector('[name=c-payer][value=me]'),bu=f.querySelector('[name=c-payer][value=buyer]');if(me&&bu){me.checked=!pr.buyers_pay_fee;bu.checked=!!pr.buyers_pay_fee}var l=$('c-left');if(l)l.checked=!!pr.show_remaining;var w=$('c-wait');if(w)w.checked=!!pr.waitlist;var t=$('c-trans');if(t)t.checked=!!pr.transfers})();
/* platform rules from the admin: hide features that are switched off */
(function(){var P=(TICA_DATA.currency||{}).platform,f=document.getElementById('create-form');if(!P||!f)return;
  function drop(el){if(!el)return;var l=el.closest('label,li')||el;l.hidden=true;l.style.display='none';if(el.checked){el.checked=false}}
  if(!P.online)drop(f.querySelector('[name=c-mode][value=Online]'));if(!P.hybrid)drop(f.querySelector('[name=c-mode][value=Hybrid]'));
  if(!P.registration)drop(f.querySelector('[name=c-entry][value=corporate]'));
  var vis=document.getElementById('c-vis');if(vis)[].slice.call(vis.options).forEach(function(o){if((o.value==='unlisted'&&!P.unlisted)||(o.value==='private'&&!P.private))o.remove()});
  if(!P.waitlist)drop(document.getElementById('c-wait'));if(!P.transfers)drop(document.getElementById('c-trans'));if(!P.question)drop(document.getElementById('c-askq'));
  var me=f.querySelector('[name=c-payer][value=me]'),bu=f.querySelector('[name=c-payer][value=buyer]');
  if(!P.organizer_pays){drop(me);if(bu)bu.checked=true}
  ['c-mode','c-entry'].forEach(function(n){if(!f.querySelector('[name='+n+']:checked')){var first=f.querySelector('[name='+n+']');if(first)first.checked=true}});
})();
if(document.querySelector('.page[data-page=dashboard]:not([hidden])'))renderDash();else fromHash();
})();
/* design script 20 */
/* v39: create event form (Tica: saves real events with every ticket and event option) */
(function(){
var $=function(i){return document.getElementById(i)},N=function(n){return window.TICA_MONEY(n)};
var f=$('create-form');if(!f)return;
var CD=TICA_DATA.currency||{},OPTS=CD.options||[],CUR=CD.symbol||'₦';
var ADMIN=TICA_DATA.adminBuilder||null,EDIT=TICA_DATA.builderEdit||null;
var requestedType=(new URLSearchParams(location.search).get('type')||'').toLowerCase();
if(requestedType==='online'||requestedType==='venue'){
  var requestedMode=f.querySelector('[name=c-mode][value="'+(requestedType==='online'?'Online':'In person')+'"]');
  if(requestedMode)requestedMode.checked=true;
}
if(ADMIN){
  var adminPanel=$('ce-admin'),owner=$('c-owner');adminPanel.hidden=false;document.body.classList.add('admin-builder');
  owner.innerHTML='<option value="">TICA · platform-managed event</option>'+(ADMIN.organizers||[]).map(function(o){return '<option value="'+o.id+'">'+esc(o.name)+(o.email?' · '+esc(o.email):'')+'</option>'}).join('');
  var workspaceBack=document.querySelector('.workspace-site');if(workspaceBack)workspaceBack.href=ADMIN.backUrl||'/admin/dashboard';
  var bank=$('ce-bank');if(bank)bank.innerHTML='<span><b>Payout ownership</b><small>Assign an organizer for organizer payouts. TICA-managed events remain under platform finance.</small></span><a class="btn btn-line" href="'+esc(ADMIN.backUrl||'/admin/dashboard')+'">All events</a>';
}
function curOf(code){return OPTS.filter(function(o){return o.code===code})[0]||{code:code,symbol:code,paid:false}}
/* country picks the currency; the organiser can still change it */
(function(){
  var cs=$('c-country'),cc=$('c-cur'),map=CD.countries||{},names=Object.keys(map);
  var first=names.filter(function(n){return map[n]===CD.code})[0]||'Nigeria';
  names.sort(function(a,b){return a===first?-1:b===first?1:a.localeCompare(b)});
  cs.innerHTML=names.map(function(n){return '<option>'+esc(n)+'</option>'}).join('')+'<option value="Other">Another country</option>';
  cc.innerHTML=OPTS.map(function(o){return '<option value="'+o.code+'">'+esc(o.code+' ('+o.symbol+'), '+o.name)+'</option>'}).join('');
  cs.value=first;cc.value=map[first]||CD.code;
  cs.addEventListener('change',function(){if(map[cs.value])cc.value=map[cs.value];cc.dispatchEvent(new Event('change',{bubbles:true}))});
})();
function curCode(){return $('c-cur').value||CD.code}
function setCur(){var o=curOf(curCode());CUR=o.symbol;window.TICA_CUR=o.code===CD.code?null:{code:o.code,symbol:o.symbol,position:'left'};
  [].forEach.call(f.querySelectorAll('label[for^="c-price"],label[for^="c-teb"]'),function(l){l.textContent=(/teb/.test(l.htmlFor)?'Early bird price':'Price')+' ('+CUR+')'});
  var n=$('c-cur-note'),paid=o.paid;n.textContent=paid?'Buyers pay in '+o.code+'. It\'s set from the country, and you can change it.':o.code+' can\'t take card payments yet, so tickets must be free. Choose another currency to sell paid tickets.';
  n.className='hint'+(paid?'':' ce-warn');}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function mode(){return (f.querySelector('[name=c-mode]:checked')||{}).value||'In person'}
function payer(){return (f.querySelector('[name=c-payer]:checked')||{}).value||'buyer'}
function entry(){return (f.querySelector('[name=c-entry]:checked')||{}).value||'ticketing'}

/* categories from Tica */
(function(){var sel=$('c-cat'),cats=TICA_DATA.categories||[];if(!cats.length)return;sel.innerHTML=cats.map(function(c){return '<option value="'+c.id+'">'+esc(c.name)+'</option>'}).join('')})();


/* each ticket type gets its own advanced options under the row */
function tierOptions(n){
  var k=n>1?n:'';
  return '<div class="ce-x"><ul class="od-sw"><li><span><b>More options</b><small>Description, limit per order and early bird price</small></span><input class="ck-sw ce-xsw" type="checkbox" aria-label="More options for this ticket"></li></ul>'+
  '<div class="ce-xo" hidden><div class="inp"><label for="c-tdesc'+k+'">What\'s included</label><input id="c-tdesc'+k+'" class="ce-tdesc" type="text" maxlength="300" placeholder="Fast-track entry and the VIP area"></div>'+
  '<div class="two"><div class="inp"><label for="c-tmax'+k+'">Limit per order</label><input id="c-tmax'+k+'" class="ce-tmax" type="number" min="1" step="1" placeholder="No limit"></div><div class="inp"><label for="c-tunl'+k+'">Quantity</label><select id="c-tunl'+k+'" class="ce-tunl"><option value="limited">Limited, as above</option><option value="unlimited">Unlimited</option></select></div></div>'+
  '<div class="two"><div class="inp"><label for="c-teb'+k+'">Early bird price ('+esc(CUR)+')</label><input id="c-teb'+k+'" class="ce-teb" type="number" min="0" step="500" placeholder="Optional"></div><div class="inp"><label for="c-tebe'+k+'">Early bird ends</label><input id="c-tebe'+k+'" class="ce-tebe" type="datetime-local"></div></div></div></div>';
}
f.querySelector('.ce-tier').insertAdjacentHTML('beforeend',tierOptions(1));
$('ce-tiers').addEventListener('change',function(e){var sw=e.target.closest('.ce-xsw');if(sw)sw.closest('.ce-x').querySelector('.ce-xo').hidden=!sw.checked});

function upd(){
  setCur();
  var m=mode(),p=Math.max(0,parseFloat($('c-price').value)||0),fee=Math.round(p*window.TICA_FEE_PCT)/100,reg=entry()==='corporate';
  f.querySelector('.ce-in').hidden=m==='Online';f.querySelector('.ce-on').hidden=m==='In person';
  f.querySelector('.ce-sell').hidden=reg;f.querySelector('.ce-reg').hidden=!reg;
  var tiers=f.querySelectorAll('#ce-tiers .ce-tier');$('ce-add').hidden=m==='Online'&&tiers.length>=1;
  $('ce-ex-b').textContent=p?'Buyer pays '+N(p+fee)+'. You get '+N(p)+'.':'Free tickets have no fee.';
  $('ce-ex-m').textContent=p?'Buyer pays '+N(p)+'. You get '+N(p-fee)+'.':'Free tickets have no fee.';
  var priced=$('c-price').value.trim()!=='',pf=$('p-fee');pf.hidden=!reg&&!priced;
  pf.textContent=reg?'Registration, approved by you':!priced?'':!p?'Free, no booking fee':payer()==='buyer'?'+ '+N(fee)+' booking fee at checkout':'Booking fee included';
  var tag=$('p-mode');tag.hidden=m==='In person';tag.textContent=m==='Hybrid'?'In person and online':'Online';
  if(m==='Online'){var t=$('c-time').value;$('p-meta').textContent=[t,'Online on '+$('c-plat').value].filter(Boolean).join(', ')}
  f.querySelector('.ce-qx').hidden=!$('c-askq').checked;
  var pr=$('p-price');pr.hidden=!reg&&!priced;pr.textContent=reg?'Registration':!priced?'':p>0?N(p):'Free';
  var b=TICA_DATA.organizer&&TICA_DATA.organizer.payout,bx=$('ce-bank');
  if(b&&b.verified){bx.classList.add('ok');bx.innerHTML='<span><b>Payout account</b><small>'+esc(b.name)+', '+esc(b.bank)+' •••• '+esc(String(b.num).slice(-4))+'. Verified</small></span><a class="btn btn-line" href="/dashboard/payouts">Change</a>'}
}
f.addEventListener('input',upd);f.addEventListener('change',upd);

var n=1;
$('ce-add').addEventListener('click',function(){
  n++;var d=document.createElement('div');d.className='ce-tier';
  d.innerHTML='<div class="inp"><label for="c-tier'+n+'">Ticket name</label><input id="c-tier'+n+'" type="text" placeholder="VIP"></div><div class="inp"><label for="c-price'+n+'">Price ('+esc(CUR)+')</label><input id="c-price'+n+'" type="number" min="0" step="500" value="15000"></div><div class="inp"><label for="c-qty'+n+'">Quantity</label><input id="c-qty'+n+'" type="number" min="1" value="50"></div><button class="ce-rm" type="button" aria-label="Remove this ticket type">×</button>'+tierOptions(n);
  $('ce-tiers').appendChild(d);d.querySelector('input').focus();upd();
});
$('ce-tiers').addEventListener('click',function(e){var r=e.target.closest('.ce-rm');if(r){r.closest('.ce-tier').remove();upd()}});

/* several dates */
var dn=0;
function dateRow(){
  dn++;var d=document.createElement('div');d.className='ce-tier ce-date';
  d.innerHTML='<div class="inp"><label for="c-md'+dn+'">Date</label><input id="c-md'+dn+'" class="ce-md" type="date"></div><div class="inp"><label for="c-mt'+dn+'">Starts</label><input id="c-mt'+dn+'" class="ce-mt" type="time"></div><div class="inp"><label for="c-me'+dn+'">Ends</label><input id="c-me'+dn+'" class="ce-me" type="time"></div><button class="ce-rm" type="button" aria-label="Remove this date">×</button>';
  $('ce-dates').appendChild(d);return d;
}
$('c-multi').addEventListener('change',function(){
  var on=this.checked;f.querySelector('.ce-one').hidden=on;$('ce-dates').hidden=!on;$('ce-add-date').hidden=!on;
  if(on&&!$('ce-dates').children.length){var r=dateRow();r.querySelector('.ce-md').value=$('c-date').value;r.querySelector('.ce-mt').value=$('c-time').value;r.querySelector('.ce-me').value=$('c-etime').value}
});
$('ce-add-date').addEventListener('click',function(){dateRow().querySelector('input').focus()});
$('ce-dates').addEventListener('click',function(e){var r=e.target.closest('.ce-rm');if(r&&$('ce-dates').children.length>1)r.closest('.ce-tier').remove()});
$('ce-dates').addEventListener('input',function(){var r=$('ce-dates').querySelector('.ce-tier');if(!r)return;$('c-date').value=r.querySelector('.ce-md').value;$('c-time').value=r.querySelector('.ce-mt').value;$('c-date').dispatchEvent(new Event('input',{bubbles:true}))});

/* save */
function payload(draft){
  var fd=new FormData(),v=function(id){return $(id).value.trim()},m=mode();
  fd.append('draft',draft?'1':'0');fd.append('name',v('c-name'));fd.append('category_id',$('c-cat').value);fd.append('age_restriction',$('c-age').value);
  fd.append('description',$('c-desc').value);fd.append('mode',m);fd.append('entry',entry());
  if($('c-multi').checked){fd.append('multiple','1');[].forEach.call($('ce-dates').children,function(r,i){var d=r.querySelector('.ce-md').value;fd.append('dates['+i+'][start_date]',d);fd.append('dates['+i+'][start_time]',r.querySelector('.ce-mt').value);if(r.querySelector('.ce-me').value){fd.append('dates['+i+'][end_date]',d);fd.append('dates['+i+'][end_time]',r.querySelector('.ce-me').value)}})}
  else{fd.append('multiple','0');['start_date:c-date','start_time:c-time','end_date:c-edate','end_time:c-etime'].forEach(function(x){var k=x.split(':');if(v(k[1]))fd.append(k[0],v(k[1]))})}
  fd.append('currency',curCode());fd.append('country',$('c-country').value);
  if(m!=='Online'){fd.append('address',v('c-venue'));fd.append('city',v('c-city'));fd.append('state',v('c-state'))}
  if(m!=='In person'){fd.append('online_join_url',v('c-link'));fd.append('join_reveal_minutes',$('c-rev').value)}
  if(entry()==='corporate'){fd.append('registration_form_name',v('c-reg-name'));fd.append('registration_intro',$('c-reg-intro').value);fd.append('registration_approval',$('c-reg-mode').value)}
  else [].forEach.call(f.querySelectorAll('#ce-tiers .ce-tier'),function(t,i){
    var ins=t.querySelectorAll(':scope>.inp input'),x=t.querySelector('.ce-x'),adv=x&&x.querySelector('.ce-xsw').checked,unl=adv&&x.querySelector('.ce-tunl').value==='unlimited';
    if(t.dataset.tid)fd.append('tiers['+i+'][id]',t.dataset.tid);fd.append('tiers['+i+'][name]',ins[0].value.trim());fd.append('tiers['+i+'][price]',ins[1].value||'0');if(!unl&&ins[2].value)fd.append('tiers['+i+'][quantity]',ins[2].value);
    if(adv){var g=function(c){return x.querySelector(c).value.trim()};if(g('.ce-tdesc'))fd.append('tiers['+i+'][description]',g('.ce-tdesc'));if(g('.ce-tmax'))fd.append('tiers['+i+'][max_per_order]',g('.ce-tmax'));if(g('.ce-teb')){fd.append('tiers['+i+'][early_price]',g('.ce-teb'));fd.append('tiers['+i+'][early_ends]',g('.ce-tebe'))}}
  });
  fd.append('fee_payer',payer()==='me'?'organizer':'buyer');fd.append('visibility',$('c-vis').value);fd.append('refund_policy',$('c-ref').value);
  fd.append('show_remaining',$('c-left').checked?'1':'0');fd.append('waitlist_enabled',$('c-wait').checked?'1':'0');fd.append('allow_transfers',$('c-trans').checked?'1':'0');fd.append('countdown',$('c-cd').checked?'1':'0');
  fd.append('ask_question',$('c-askq').checked?'1':'0');fd.append('attendee_question',v('c-question'));
  fd.append('meta_description',v('c-seo'));fd.append('meta_keywords',v('c-keys'));
  if($('c-flyer').files[0])fd.append('flyer',$('c-flyer').files[0]);
  if(ADMIN){fd.append('organizer_id',$('c-owner').value);fd.append('is_featured',$('c-featured').checked?'1':'0');if(EDIT)fd.append('status',$('c-status').value)}
  return fd;
}
/* step by step */
var STAGES=['ce-basics','ce-where','ce-tix','ce-fee','ce-more','ce-review'],at=0,reached=0;
var STEPNAME=['Basics','Where','Tickets','Fees and payout','Settings','Review'];
function stageOf(id){var el=$(id);if(!el)return 0;var fs=el.closest('fieldset');var i=fs?STAGES.indexOf(fs.id):0;return i<0?0:i}
function stage(i,focus){
  at=Math.max(0,Math.min(STAGES.length-1,i));reached=Math.max(reached,at);
  STAGES.forEach(function(id,k){$(id).hidden=k!==at});
  [].forEach.call(f.querySelectorAll('.ce-steps a'),function(a,k){if(k===at)a.setAttribute('aria-current','step');else a.removeAttribute('aria-current');a.classList.toggle('done',k<at||k<=reached&&k!==at)});
  $('ce-progress').textContent='Step '+(at+1)+' of '+STAGES.length+': '+STEPNAME[at];
  var last=at===STAGES.length-1;$('ce-back').hidden=at===0;$('ce-next').hidden=last;$('ce-draft').hidden=!last;$('ce-publish').hidden=!last;
  if(last)summary();
  $('c-msg').textContent='';
  var top=f.querySelector('.ce-steps');if(top&&top.getBoundingClientRect().top<0)top.scrollIntoView({behavior:'smooth',block:'start'});
  if(focus){var x=$(STAGES[at]).querySelector('input:not([type=radio]):not([type=checkbox]),select,textarea');if(x)x.focus({preventScroll:true})}
}
function need(id,msg){var el=$(id);if(el&&!String(el.value).trim()){$('c-msg').textContent=msg;el.focus();return false}return true}
function check(i){
  var m=mode();
  if(i===0){if($('c-name').value.trim().length<3){$('c-msg').textContent='Give your event a name first.';$('c-name').focus();return false}
    if($('c-multi').checked){var rows=$('ce-dates').children;if(!rows.length||[].some.call(rows,function(r){return !r.querySelector('.ce-md').value||!r.querySelector('.ce-mt').value})){$('c-msg').textContent='Add a date and start time for each day.';return false}return true}
    return need('c-date','Choose the date your event starts.')&&need('c-time','Choose the time your event starts.')}
  if(i===1){if(m!=='Online'&&!(need('c-venue','Enter the venue.')&&need('c-city','Enter the city.')))return false;if(m==='Online'&&!need('c-link','Add the link people use to join.'))return false;return true}
  if(i===2){if(entry()==='corporate'){if(ADMIN&&!$('c-owner').value){$('c-msg').textContent='Assign an organizer before using registration approvals.';$('c-owner').focus();return false}return true}var tiers=f.querySelectorAll('#ce-tiers .ce-tier'),paid=false,ok=true;
    [].forEach.call(tiers,function(t){var ins=t.querySelectorAll(':scope>.inp input');if(!ins[0].value.trim()&&ok){ok=false;$('c-msg').textContent='Give every ticket type a name.';ins[0].focus()}else if(ins[1].value.trim()===''&&ok){ok=false;$('c-msg').textContent='Enter a price for every ticket. Use 0 for free tickets.';ins[1].focus()}if(parseFloat(ins[1].value)>0)paid=true});
    if(!ok)return false;if(paid&&!curOf(curCode()).paid){$('c-msg').textContent=curCode()+' can\'t take card payments yet. Make the tickets free or change the currency in step 2.';return false}return true}
  if(i===4){if($('c-askq').checked&&!need('c-question','Write the question you want to ask.'))return false;return true}
  return true;
}
function summary(){
  var m=mode(),o=curOf(curCode()),tiers=[].map.call(f.querySelectorAll('#ce-tiers .ce-tier'),function(t){var ins=t.querySelectorAll(':scope>.inp input');return esc(ins[0].value.trim()||'Ticket')+', '+(parseFloat(ins[1].value)>0?esc(N(+ins[1].value)):'Free')+(ins[2]&&ins[2].value?', '+esc(ins[2].value)+' available':'')});
  var when=$('c-multi').checked?[].map.call($('ce-dates').children,function(r){return r.querySelector('.ce-md').value+' '+r.querySelector('.ce-mt').value}).join('; '):($('c-date').value+' '+$('c-time').value);
  var rows=[['Event',esc($('c-name').value.trim())],['When',esc(when)],['Where',m==='Online'?'Online, '+esc($('c-plat').value):esc([$('c-venue').value,$('c-city').value,$('c-country').value].filter(Boolean).join(', '))+(m==='Hybrid'?' and online':'')],['Currency',esc(o.code+' ('+o.symbol+')')],['Entry',entry()==='corporate'?'Registration, '+($('c-reg-mode').value==='manual'?'you approve each person':'everyone approved'):tiers.join('<br>')],['Booking fee',payer()==='buyer'?'Buyers pay it':'Included in your prices'],['Who can find it',esc($('c-vis').selectedOptions[0].text)],['Flyer',$('c-flyer').files[0]?esc($('c-flyer').files[0].name):'<span class="ce-warn">Add a flyer in step 5 before you publish</span>']];
  if(ADMIN)rows.splice(1,0,['Owner',$('c-owner').value?esc($('c-owner').selectedOptions[0].text):'TICA · platform-managed'],['Featured',$('c-featured').checked?'Yes':'No']);
  $('ce-sum').innerHTML=rows.map(function(r){return '<div><dt>'+r[0]+'</dt><dd>'+r[1]+'</dd></div>'}).join('');
}
$('ce-next').addEventListener('click',function(){if(check(at))stage(at+1,true)});
$('ce-back').addEventListener('click',function(){stage(at-1,true)});
f.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target.tagName==='INPUT'&&at<STAGES.length-1){e.preventDefault();if(check(at))stage(at+1,true)}});
var FIELDS={organizer_id:'c-owner',is_featured:'c-featured',currency:'c-cur',country:'c-country',name:'c-name',category_id:'c-cat',start_date:'c-date',start_time:'c-time',end_date:'c-edate',end_time:'c-etime',address:'c-venue',city:'c-city',online_join_url:'c-link',flyer:'c-flyer',attendee_question:'c-question',dates:'c-multi',tiers:'ce-add'};
function save(draft){
  var msg=$('c-msg'),btns=f.querySelectorAll('.ce-go .btn');msg.textContent=draft?'Saving your draft...':'Publishing your event...';
  [].forEach.call(btns,function(b){b.disabled=true});
  var req={method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest'},body:payload(draft)};
  (EDIT?fetch(EDIT.endpoint,req):fetch(ADMIN?ADMIN.endpoint:'/tica-api/events',req))
  .then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok){var e=new Error(j.message||'We couldn\'t save your event. Try again.');e.data=j;throw e}return j})})
  .then(function(r){
    window.TICA_NEW={name:r.name,status:r.status};
    msg.textContent=EDIT?'Your changes to '+r.name+' are saved.':draft?r.name+' is saved as a draft. Find it under Events, Drafts.':r.name+' is now live.';
    setTimeout(function(){location.href=r.redirect_url||r.registration_url||(ADMIN?ADMIN.backUrl:'/dashboard')},900);
  },function(er){
    [].forEach.call(btns,function(b){b.disabled=false});
    var errs=er.data&&er.data.errors||{},k=Object.keys(errs)[0],text=k?errs[k][0]:er.message;msg.textContent=text;
    var id=k&&(FIELDS[k]||FIELDS[k.split('.')[0]]);if(id)stage(stageOf(id));else if(k&&/^tiers/.test(k))stage(2);if(k&&/^tiers\.\d+\./.test(k)){var t=f.querySelectorAll('#ce-tiers .ce-tier')[+k.split('.')[1]];if(t){t.scrollIntoView({behavior:'smooth',block:'center'});var x=t.querySelector('input');if(x)x.focus({preventScroll:true})}return}
    if(id&&$(id)){$(id).scrollIntoView({behavior:'smooth',block:'center'});$(id).focus({preventScroll:true})}
  });
}
f.addEventListener('submit',function(e){e.preventDefault();if(at<STAGES.length-1){if(check(at))stage(at+1,true);return}for(var k=0;k<STAGES.length-1;k++){if(!check(k)){stage(k,false);check(k);return}}if(!$('c-flyer').files[0]&&!(EDIT&&EDIT.event.flyer)){stage(4,false);$('c-msg').textContent='Add a flyer before you publish.';$('c-flyer').focus();return}save(false)});
$('ce-draft').addEventListener('click',function(){if($('c-name').value.trim().length<3){$('c-msg').textContent='Give your event a name first.';$('c-name').focus();return}save(true)});
f.querySelector('.ce-steps').addEventListener('click',function(e){var a=e.target.closest('[data-ce]');if(!a)return;e.preventDefault();e.stopPropagation();var to=STAGES.indexOf(a.dataset.ce);
  if(to<=at||to<=reached){stage(to,true);return}for(var k=at;k<to;k++){if(!check(k)){stage(k,false);return}}stage(to,true)},true);
window.addEventListener('tica:navigate',function(){if(/^\/create/.test(location.pathname))setTimeout(function(){upd();stage(at)},0)});
/* edit mode: fill the builder with the saved event */
function fillEdit(){
  if(!EDIT)return;var E=EDIT.event,set=function(id,v){var el=$(id);if(el&&v!=null)el.value=v};
  var h=f.querySelector('h1.display');if(h)h.textContent='Edit event';
  var ld=f.querySelector('p.lead');if(ld)ld.textContent='Change anything below and save. Tickets that have sold keep their price.';
  var ap=$('ce-admin');if(ap&&ADMIN){var at=$('ce-admin-title');if(at)at.textContent='Owner, status and featured';[].forEach.call(f.querySelectorAll('.ce-admin-edit'),function(x){x.hidden=false});$('c-status').value=E.status;
    var del=$('ce-delete');if(del)del.addEventListener('click',function(){if(!window.confirm('Delete "'+E.name+'"? This removes the event, its tickets and its page, and can\'t be undone.'))return;del.disabled=true;
      var fd=new FormData();fd.append('_token',TICA_DATA.csrf);fetch(EDIT.deleteUrl,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':TICA_DATA.csrf},body:fd}).then(function(){location.href=ADMIN.backUrl},function(){del.disabled=false;$('c-msg').textContent='We couldn\'t delete the event. Try again.'})});}
  if(ADMIN){set('c-owner',E.organizer_id||'');$('c-featured').checked=!!E.is_featured}
  set('c-name',E.name);if(E.category_id)set('c-cat',E.category_id);set('c-age',E.age_restriction);set('c-desc',E.description);
  var mr=f.querySelector('[name=c-mode][value="'+E.mode+'"]');if(mr)mr.checked=true;
  var er=f.querySelector('[name=c-entry][value="'+E.entry+'"]');if(er)er.checked=true;
  [].forEach.call(f.querySelectorAll('[name=c-entry]'),function(x){x.disabled=true});
  if(E.country){var cs=$('c-country');if(![].some.call(cs.options,function(o){return o.value===E.country}))cs.insertAdjacentHTML('beforeend','<option>'+esc(E.country)+'</option>');cs.value=E.country}
  if(E.currency)set('c-cur',E.currency);
  set('c-venue',E.address);set('c-city',E.city);set('c-state',E.state);set('c-link',E.online_join_url);set('c-rev',E.join_reveal_minutes);
  if(E.multiple){$('c-multi').checked=true;$('c-multi').dispatchEvent(new Event('change'));$('ce-dates').innerHTML='';(E.dates||[]).forEach(function(d){var r=dateRow();r.querySelector('.ce-md').value=d.date;r.querySelector('.ce-mt').value=d.start;r.querySelector('.ce-me').value=d.end})}
  else{set('c-date',E.start_date);set('c-time',E.start_time);set('c-edate',E.end_date);set('c-etime',E.end_time)}
  (E.tiers||[]).forEach(function(t,i){
    if(i>0)$('ce-add').click();
    var rows=f.querySelectorAll('#ce-tiers .ce-tier'),row=rows[rows.length-1],ins=row.querySelectorAll(':scope>.inp input');
    row.dataset.tid=t.id;ins[0].value=t.name;ins[1].value=t.price;ins[2].value=t.quantity==null?'':t.quantity;
    var x=row.querySelector('.ce-x');
    if(x&&(t.description||t.max_per_order||t.early_price!=null||t.quantity==null)){var sw=x.querySelector('.ce-xsw');sw.checked=true;x.querySelector('.ce-xo').hidden=false;
      x.querySelector('.ce-tdesc').value=t.description||'';x.querySelector('.ce-tmax').value=t.max_per_order||'';x.querySelector('.ce-tunl').value=t.quantity==null?'unlimited':'limited';
      if(t.early_price!=null){x.querySelector('.ce-teb').value=t.early_price;x.querySelector('.ce-tebe').value=t.early_ends||''}}
    if(t.sold>0||t.locked){ins[1].readOnly=true;var rm=row.querySelector('.ce-rm');if(rm)rm.remove();
      row.insertAdjacentHTML('beforeend','<p class="ce-sold">'+(t.locked?'This ticket has options set in the old editor, so it stays as it is here.':t.sold+' sold, so the price stays the same and it can\'t be removed. The quantity can\'t go below '+t.sold+'.')+'</p>');
      if(t.locked)[].forEach.call(row.querySelectorAll('input,select'),function(el){el.disabled=true})}
  });
  var pay=f.querySelector('[name=c-payer][value='+(E.fee_payer==='organizer'?'me':'buyer')+']');if(pay)pay.checked=true;
  set('c-vis',E.visibility);set('c-ref',E.refund_policy);
  $('c-left').checked=!!E.show_remaining;$('c-wait').checked=!!E.waitlist_enabled;$('c-trans').checked=!!E.allow_transfers;$('c-cd').checked=!!E.countdown;
  $('c-askq').checked=!!E.attendee_question;set('c-question',E.attendee_question);set('c-seo',E.meta_description);set('c-keys',E.meta_keywords);
  if(E.flyer){$('p-flyer').innerHTML='';var img=new Image();img.alt='Your flyer';img.src=E.flyer;$('p-flyer').appendChild(img);
    var fh=$('c-flyer').closest('.inp');if(fh)fh.insertAdjacentHTML('afterend','<p class="hint">Your current flyer stays unless you choose a new one.</p>')}
  if(E.locks&&E.locks.dates){['c-date','c-time','c-edate','c-etime','c-multi'].forEach(function(id){$(id).disabled=true});[].forEach.call($('ce-dates').querySelectorAll('input,button'),function(el){el.disabled=true});$('ce-add-date').disabled=true;
    $('c-multi').closest('ul').insertAdjacentHTML('beforebegin','<p class="ce-lock">Tickets have sold, so dates are locked here. Use Change date on the event to move it. Attendees are told automatically.</p>')}
  if(E.locks&&E.locks.currency){$('c-cur').disabled=true;$('c-cur').closest('.inp').insertAdjacentHTML('beforeend','<p class="ce-lock">Tickets have sold in '+esc(E.currency)+', so the currency can\'t change.</p>')}
  ['c-name','c-date','c-time','c-venue','c-city','c-price'].forEach(function(i){$(i).dispatchEvent(new Event('input',{bubbles:true}))});
  var pub=$('ce-publish'),dr=$('ce-draft');
  if(ADMIN||E.status==='live'){pub.textContent='Save changes';dr.style.display='none'}
  else pub.textContent='Publish event';
  if(!ADMIN&&EDIT.backUrl){var wb=document.querySelector('.workspace-site');if(wb)wb.href=EDIT.backUrl}
}
fillEdit();
upd();stage(0);
})();
/* design script 21 */
(() => {
  if (document.querySelector('.home-progress')) return;

  const progress = document.createElement('div');
  progress.className = 'home-progress';
  progress.setAttribute('aria-hidden', 'true');
  document.body.appendChild(progress);

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  let ticking = false;

  const isHome = () => !document.querySelector('.page[data-page="home"]')?.hidden;
  const update = () => {
    ticking = false;
    const home = isHome();
    document.body.classList.toggle('home-route', home);
    document.body.classList.toggle('home-scrolled', home && window.scrollY > 18);
    if (!home) return;

    const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    progress.style.transform = `scaleX(${Math.min(1, window.scrollY / max)})`;

    if (!reduced.matches && window.innerWidth > 700) {
      const amount = Math.min(window.scrollY, 520);
      const homePage = document.querySelector('.page[data-page="home"]');
      homePage?.style.setProperty('--hero-y', `${amount * 0.12}px`);
      homePage?.style.setProperty('--hero-copy-y', `${amount * 0.045}px`);
      homePage?.style.setProperty('--hero-scale', String(1 + amount * 0.00008));
    }
  };

  const requestUpdate = () => {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(update);
    }
  };

  window.addEventListener('scroll', requestUpdate, { passive: true });
  window.addEventListener('resize', requestUpdate, { passive: true });
  window.addEventListener('tica:navigate', () => requestAnimationFrame(update));

  if (!reduced.matches && finePointer.matches) {
    const spot = document.querySelector('.page[data-page="home"] .spot');
    spot?.addEventListener('pointermove', (event) => {
      const rect = spot.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width - .5;
      const y = (event.clientY - rect.top) / rect.height - .5;
      spot.style.transform = `perspective(1200px) rotateX(${-y * 1.4}deg) rotateY(${x * 1.8}deg) translateY(-2px)`;
    }, { passive: true });
    spot?.addEventListener('pointerleave', () => { spot.style.transform = ''; }, { passive: true });
  }

  const citiesByCountry = {
    Nigeria: ['Owerri', 'Port Harcourt', 'Abuja', 'Lagos', 'Enugu', 'Uyo', 'Aba', 'Calabar', 'Benin City'],
    Ghana: ['Accra', 'Kumasi', 'Takoradi'],
    Kenya: ['Nairobi', 'Mombasa', 'Kisumu'],
    'South Africa': ['Johannesburg', 'Cape Town', 'Durban', 'Pretoria'],
    'United Kingdom': ['London', 'Manchester', 'Birmingham'],
    'United States': ['New York', 'Atlanta', 'Houston', 'Los Angeles'],
    Online: ['Online']
  };
  // Tica: add the countries and cities that have live events.
  (window.TICA_EVENTS || []).forEach((event) => {
    if (event.mode === 'online' || !event.country) return;
    const country = Object.keys(citiesByCountry).find((name) => name.toLowerCase() === event.country.toLowerCase()) || event.country;
    citiesByCountry[country] = citiesByCountry[country] || [];
    if (event.city && !citiesByCountry[country].includes(event.city)) citiesByCountry[country].unshift(event.city);
    document.querySelectorAll('select[data-country]').forEach((select) => {
      if (![...select.options].some((option) => option.value === country)) select.insertBefore(new Option(country, country), select.querySelector('option[value="Online"]'));
    });
  });
  const smartSelects = new Map();
  const datePickers = new Map();

  const setOptions = (select, options, disabled = false) => {
    if (!select) return;
    const selected = select.value;
    select.replaceChildren(...options.map(({ value, label }) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = label;
      return option;
    }));
    select.disabled = disabled;
    if ([...select.options].some((option) => option.value === selected)) select.value = selected;
    smartSelects.get(select)?.refresh();
  };

  const populateCities = (prefix, country) => {
    const cities = citiesByCountry[country] || [];
    const options = country === 'Online'
      ? [{ value: 'Online', label: 'Online events' }]
      : [{ value: 'all', label: 'All cities' }, ...cities.map((city) => ({ value: city, label: city }))];
    setOptions(document.getElementById(`${prefix}-where`), options);
  };

  const syncDate = (prefix, dateValue) => {
    const month = document.getElementById(`${prefix}-month`);
    const day = document.getElementById(`${prefix}-day`);
    if (!month || !day) return;
    if (!dateValue) {
      month.value = 'all';
      day.value = 'all';
      return;
    }
    const [, monthNumber, dayNumber] = dateValue.split('-');
    month.value = monthNumber === '11' ? 'nov' : monthNumber === '12' ? 'dec' : `m${monthNumber}`;
    day.value = String(Number(dayNumber));
  };

  const closeSmartSelects = (except) => {
    smartSelects.forEach((control) => {
      if (control !== except) control.close();
    });
  };

  const enhanceSelect = (select) => {
    if (!select || smartSelects.has(select)) return;
    const shell = document.createElement('div');
    shell.className = 'smart-select';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'smart-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const value = document.createElement('span');
    const chevron = document.createElement('span');
    chevron.className = 'smart-chevron';
    chevron.setAttribute('aria-hidden', 'true');
    const menu = document.createElement('div');
    menu.className = 'smart-menu';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    trigger.append(value, chevron);
    select.before(shell);
    shell.append(select, trigger);
    document.body.appendChild(menu);
    select.classList.add('smart-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');
    const label = document.querySelector(`label[for="${select.id}"]`)?.textContent?.trim();
    if (label) trigger.setAttribute('aria-label', label);

    const close = () => {
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      shell.classList.remove('is-open');
    };
    const refresh = () => {
      value.textContent = select.selectedOptions[0]?.textContent || 'Select';
      trigger.disabled = select.disabled;
      menu.replaceChildren(...[...select.options].map((option) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'smart-option';
        item.textContent = option.textContent;
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', String(option.selected));
        item.addEventListener('click', () => {
          select.value = option.value;
          refresh();
          close();
          select.dispatchEvent(new Event('input', { bubbles: true }));
          select.dispatchEvent(new Event('change', { bubbles: true }));
          trigger.focus();
        });
        return item;
      }));
    };
    const control = { refresh, close };
    smartSelects.set(select, control);
    trigger.addEventListener('click', () => {
      const opening = menu.hidden;
      closeSmartSelects(control);
      menu.hidden = !opening;
      trigger.setAttribute('aria-expanded', String(opening));
      shell.classList.toggle('is-open', opening);
      if (opening) {
        const rect = trigger.getBoundingClientRect();
        const width = Math.max(210, rect.width);
        menu.style.width = `${width}px`;
        menu.style.left = `${Math.min(window.innerWidth - width - 12, Math.max(12, rect.left))}px`;
        const menuHeight = Math.min(menu.scrollHeight, 258);
        const roomBelow = window.innerHeight - rect.bottom - 12;
        menu.style.top = roomBelow >= menuHeight
          ? `${rect.bottom + 8}px`
          : `${Math.max(12, rect.top - menuHeight - 8)}px`;
        menu.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
      }
    });
    trigger.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
      if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && menu.hidden) {
        event.preventDefault();
        trigger.click();
      }
    });
    select.addEventListener('change', refresh);
    refresh();
  };

  const enhanceDatePicker = (input) => {
    if (!input || datePickers.has(input)) return;
    input.classList.add('calendar-native');
    input.tabIndex = -1;
    input.setAttribute('aria-hidden', 'true');
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'calendar-trigger';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-expanded', 'false');
    input.before(trigger);
    const panel = document.createElement('div');
    panel.className = 'calendar-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Choose a date');
    panel.hidden = true;
    panel.innerHTML = '<div class="calendar-head"><button type="button" class="calendar-prev" aria-label="Previous month">‹</button><strong></strong><button type="button" class="calendar-next" aria-label="Next month">›</button></div><div class="calendar-week" aria-hidden="true"><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span></div><div class="calendar-days"></div>';
    document.body.appendChild(panel);
    const title = panel.querySelector('.calendar-head strong');
    const days = panel.querySelector('.calendar-days');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const initial = input.value ? new Date(`${input.value}T00:00:00`) : today;
    let viewYear = initial.getFullYear();
    let viewMonth = initial.getMonth();

    const formatValue = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const refreshTrigger = () => {
      trigger.textContent = input.value
        ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${input.value}T00:00:00`))
        : 'Pick a date';
    };
    const render = () => {
      title.textContent = new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' }).format(new Date(viewYear, viewMonth, 1));
      const firstDay = new Date(viewYear, viewMonth, 1).getDay();
      const count = new Date(viewYear, viewMonth + 1, 0).getDate();
      const cells = Array.from({ length: firstDay }, () => {
        const blank = document.createElement('span');
        blank.className = 'calendar-blank';
        return blank;
      });
      for (let day = 1; day <= count; day += 1) {
        const date = new Date(viewYear, viewMonth, day);
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = String(day);
        button.setAttribute('aria-label', new Intl.DateTimeFormat('en', { dateStyle: 'full' }).format(date));
        const value = formatValue(date);
        if (value === input.value) button.classList.add('is-selected');
        if (value === formatValue(today)) button.classList.add('is-today');
        if (date < today) {
          button.disabled = true;
          button.classList.add('is-past');
        }
        button.addEventListener('click', () => {
          input.value = value;
          refreshTrigger();
          render();
          close();
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
          trigger.focus();
        });
        cells.push(button);
      }
      days.replaceChildren(...cells);
    };
    const close = () => {
      panel.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      trigger.classList.remove('is-open');
    };
    const control = { close };
    datePickers.set(input, control);
    trigger.addEventListener('click', () => {
      const opening = panel.hidden;
      closeSmartSelects();
      datePickers.forEach((picker) => { if (picker !== control) picker.close(); });
      panel.hidden = !opening;
      trigger.setAttribute('aria-expanded', String(opening));
      trigger.classList.toggle('is-open', opening);
      if (opening) {
        render();
        const rect = trigger.getBoundingClientRect();
        const width = Math.min(330, window.innerWidth - 24);
        panel.style.width = `${width}px`;
        panel.style.left = `${Math.min(window.innerWidth - width - 12, Math.max(12, rect.left))}px`;
        const height = panel.offsetHeight;
        panel.style.top = window.innerHeight - rect.bottom - 12 >= height
          ? `${rect.bottom + 8}px`
          : `${Math.max(12, rect.top - height - 8)}px`;
      }
    });
    panel.querySelector('.calendar-prev').addEventListener('click', () => {
      viewMonth -= 1;
      if (viewMonth < 0) { viewMonth = 11; viewYear -= 1; }
      render();
    });
    panel.querySelector('.calendar-next').addEventListener('click', () => {
      viewMonth += 1;
      if (viewMonth > 11) { viewMonth = 0; viewYear += 1; }
      render();
    });
    input.addEventListener('change', refreshTrigger);
    refreshTrigger();
  };

  ['h', 'd'].forEach((prefix) => {
    const country = document.getElementById(`${prefix}-country`);
    const date = document.getElementById(`${prefix}-date`);
    populateCities(prefix, country?.value || 'Nigeria');
    syncDate(prefix, date?.value || '');
    country?.addEventListener('change', () => populateCities(prefix, country.value));
    date?.addEventListener('input', () => syncDate(prefix, date.value));
  });
  document.querySelectorAll('.search.ms select:not([hidden]), .filters select').forEach(enhanceSelect);
  document.querySelectorAll('#h-date, #d-date').forEach(enhanceDatePicker);
  document.addEventListener('pointerdown', (event) => {
    if (!event.target.closest('.smart-select') && !event.target.closest('.smart-menu')) closeSmartSelects();
    if (!event.target.closest('.calendar-trigger') && !event.target.closest('.calendar-panel')) datePickers.forEach((picker) => picker.close());
  });
  window.addEventListener('scroll', () => { closeSmartSelects(); datePickers.forEach((picker) => picker.close()); }, { passive: true });
  window.addEventListener('resize', () => { closeSmartSelects(); datePickers.forEach((picker) => picker.close()); }, { passive: true });
  window.TICA_SYNC_DATE = syncDate;
  window.TICA_SYNC_SEARCH = (prefix, country, date) => {
    populateCities(prefix, country);
    syncDate(prefix, date);
  };

  update();
})();
