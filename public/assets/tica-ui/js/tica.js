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
/* Tica: the booking fee is the admin percentage plus a fixed amount per paid ticket. */
window.TICA_FEE_PCT=(TICA_DATA.currency&&TICA_DATA.currency.feePct!=null)?+TICA_DATA.currency.feePct:6;
window.TICA_FEE_FLAT=(TICA_DATA.currency&&TICA_DATA.currency.feeFlat!=null)?+TICA_DATA.currency.feeFlat:100;
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
var TD={'Regular':'General admission','VIP':'Fast-track entry and the VIP area','VVIP':'Premium access and the best available section','Early Bird':'Limited early-release admission','Student':'Discounted admission with valid student ID','Complimentary':'Free invited admission','Table for six':'A reserved table for six guests','Day pass':'Entry for the whole day','Maker table':'Stall space for one maker','Runner':'Race entry, bib and finisher medal','Free entry':'Free, but you still need a ticket'};
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
    var countryOk=e.mode==='online'||!e.country||e.country.toLowerCase()===country.toLowerCase();
    var ok=(!q||(e.name+' '+e.label+' '+e.by+' '+e.city).toLowerCase().indexOf(q)!==-1)&&countryOk&&(w==='all'||e.city===w)&&(c==='all'||e.cat===c||!CATS[c]&&iconKey(e.cat)===c)&&(m==='all'||e.m===m)&&(day==='all'||String(e.day)===day)&&(p==='all'||(p==='free'&&e.n===0)||(p==='low'&&e.n>0&&e.n<5000)||(p==='high'&&e.n>=5000));
    document.querySelector('.row[data-slug="'+e.slug+'"]').hidden=!ok;if(ok)n++;
  });
  var srt=$('d-sort').value,box=$('rows');
  EV.slice().sort(function(a,b){return srt==='low'?a.n-b.n:srt==='high'?b.n-a.n:eventTime(a)-eventTime(b)}).forEach(function(e){box.appendChild(box.querySelector('.row[data-slug="'+e.slug+'"]'))});
  $('count').textContent=n===1?'1 event':n+' events';
  $('empty').hidden=n!==0;
}
['d-q','d-country','d-where','d-cat','d-price','d-date','d-sort'].forEach(function(i){$(i).addEventListener('input',filter)});$('d-submit').addEventListener('click',filter);
$('reset').addEventListener('click',function(){$('d-q').value='';$('d-country').value='Nigeria';$('d-date').value='';['d-where','d-cat','d-price','d-month','d-day'].forEach(function(i){$(i).value='all'});if(window.TICA_SYNC_DATE)window.TICA_SYNC_DATE('d','');['d-country','d-where','d-cat','d-price'].forEach(function(i){$(i).dispatchEvent(new Event('change',{bubbles:true}))});filter()});
$('home-search').addEventListener('submit',function(e){
  e.preventDefault();
  $('d-country').value=$('h-country').value;$('d-date').value=$('h-date').value;if(window.TICA_SYNC_SEARCH)window.TICA_SYNC_SEARCH('d',$('h-country').value,$('h-date').value);$('d-where').value=$('h-where').value;$('d-cat').value=$('h-cat').value;$('d-q').value=$('h-q').value;['d-country','d-where','d-cat'].forEach(function(i){$(i).dispatchEvent(new Event('change',{bubbles:true}))});
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
function eventProgramme(e){var p=e.programme||{},people=p.people||[],agenda=p.agenda||[];if(!people.length&&!agenda.length)return '';var artists=p.type==='artist',initials=function(name){return String(name||'Guest').trim().split(/\s+/).slice(0,2).map(function(x){return x.charAt(0)}).join('').toUpperCase()||'G'},peopleHtml=people.length?'<section class="evc ev-programme"><div class="ev-section-head"><div><span class="ev-kicker">'+(artists?'On stage':'Meet the people')+'</span><h2>'+(artists?'Line-up':'Featured speakers')+'</h2></div><span class="ev-count">'+people.length+' '+(artists?(people.length===1?'artist':'artists'):(people.length===1?'speaker':'speakers'))+'</span></div><div class="ev-people">'+people.map(function(person){var visual=person.image?'<img loading="lazy" decoding="async" src="'+esc(person.image)+'" alt="Portrait of '+esc(person.name)+'">':'<span aria-hidden="true">'+esc(initials(person.name))+'</span>';return '<article class="ev-person"><div class="ev-person-photo">'+visual+'</div><div class="ev-person-copy"><h3>'+esc(person.name)+'</h3>'+(person.role?'<p class="ev-person-role">'+esc(person.role)+'</p>':'')+(person.topic?'<p class="ev-person-topic">'+esc(person.topic)+'</p>':'')+(person.bio?'<p class="ev-person-bio">'+esc(person.bio)+'</p>':'')+'</div></article>'}).join('')+'</div></section>':'';
  var agendaHtml=agenda.length?'<section class="evc ev-agenda"><div class="ev-section-head"><div><span class="ev-kicker">Plan your day</span><h2>Agenda</h2></div></div><ol class="ev-agenda-list">'+agenda.map(function(item,i){var meta=[item.location,item.person].filter(Boolean).map(esc).join(' · '),time=item.time?esc(item.time+(item.endTime?' – '+item.endTime:'')):'Time to be confirmed';return '<li><div class="ev-agenda-rail"><span>'+(i+1)+'</span></div><article><div class="ev-agenda-top"><div><time>'+time+'</time>'+(item.date?'<small>'+esc(item.date)+'</small>':'')+'</div><h3>'+esc(item.title)+'</h3></div>'+(meta?'<p class="ev-agenda-meta">'+meta+'</p>':'')+(item.description?'<p>'+esc(item.description)+'</p>':'')+'</article></li>'}).join('')+'</ol></section>':'';return peopleHtml+agendaHtml}
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
  '<h2>Choose your tickets</h2>'+e.tiers.map(function(t,i){var inf=tierInfo(e,i),p=tierPrice(e,i),low=!inf.unlimited&&t[2]>0&&t[2]<=Math.max(10,t[3]*.15),desc=inf.description||(inf.seatingMode==='reserved'?'Choose your seat from the live seating map.':TD[t[0]]);
    return '<div class="tp-row"><div><span>'+esc(t[0])+'</span>'+(desc?'<em class="tdesc">'+esc(desc)+'</em>':'')+'<b>'+naira(p)+(inf.earlyBird?'<small class="tfee">Early bird, was '+naira(t[1])+'</small>':'')+'</b>'+((inf.unlimited||e.hideStock)&&t[2]>0?'':'<small class="tleft'+(low?' low':'')+(t[2]===0?' out':'')+'">'+(t[2]===0?'Sold out':(low?'Only ':'')+t[2].toLocaleString('en-NG')+' left')+'</small>')+'</div>'+(t[2]===0?'':'<div class="stepper"><button type="button" data-d="-1" data-i="'+i+'" aria-label="Remove one '+esc(t[0])+' ticket">−</button><output id="tq'+i+'">'+qty[i]+'</output><button type="button" class="plus" data-d="1" data-i="'+i+'" aria-label="Add one '+esc(t[0])+' ticket">+</button></div>')+'</div>'}).join('')+
  '<div class="tp-lines" id="tp-lines"></div>'+
  (e.tiers.some(function(t){return t[1]>0})?'<form class="tp-code" id="tp-code"><label for="code">Discount code</label><div><input id="code" type="text" placeholder="Enter code" autocomplete="off" value="'+esc(disc?disc.code:'')+'"><button class="btn btn-line" type="submit">Apply</button></div><p class="hint" id="code-msg" role="status">'+(disc?esc(disc.code)+' applied.':'Enter a discount code if you have one.')+'</p></form>':'')+
  '<button class="btn btn-hot tp-go" type="button" id="tp-go" disabled>Continue</button></div>';
  var venueQuery=(e.venue.indexOf('confirmed')>-1?'':e.venue+', ')+(e.venue.indexOf(e.city)>-1?'':e.city+', ')+(e.country||'Nigeria');
  $('event-view').innerHTML='<div class="evb"><div class="evb-bg" aria-hidden="true"><div class="flyer">'+e.art+'</div></div><div class="evb-flyer'+(e.sold?' soldout':'')+'"><div class="flyer" role="img" aria-label="Flyer for '+esc(e.name)+'">'+e.art+'</div></div></div>'+
  '<div class="wrap evpage"><a class="evback" href="/discover" aria-label="Back to Discover events"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></a>'+
  '<div class="evcols2"><div class="evleft">'+
  '<div class="evtitle"><div>'+(e.tag?'<span class="tag">'+esc(e.tag)+'</span>':'')+'<h1>'+esc(e.name)+'</h1><p>'+(e.regMode==='corporate'?'Registration event':from)+'</p></div><button class="btn btn-line sharebtn" type="button" id="ev-share"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V4M8 8l4-4 4 4M5 13v6a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-6"/></svg>Share</button></div>'+
  (e.desc?'<section class="evc"><h2>About this event</h2>'+asParas(e.desc)+'</section>':'')+
  eventProgramme(e)+
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
var FEE_PCT=window.TICA_FEE_PCT,FEE_FLAT=window.TICA_FEE_FLAT;
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
function stepsBar(n,withForm){
  return '<ol class="steps">'+(withForm?['Choose tickets','Your details','Registration form','Pay','Get your tickets']:['Choose tickets','Your details','Pay','Get your tickets']).map(function(t,i){
    return '<li class="'+(i===n?'on':(i<n?'done':''))+'"'+(i===n?' aria-current="step"':'')+'>'+(i+1)+'. '+t+'</li>'}).join('')+'</ol>';
}
function renderCheckout(e){
  window.TICA_CUR=e.cur||null;
  var pend=PENDING&&PENDING.slug===e.slug?PENDING:null;
  if(pend&&(!HOLD||HOLD.slug!==e.slug||HOLD.end<Date.now()))HOLD={slug:e.slug,end:Date.now()+600000};
  var guestOk=!!(TICA_DATA.currency&&TICA_DATA.currency.guestCheckout);
  var S={login:(!guestOk&&!(window.TICA_USER&&TICA_USER.email))?'id':null,loginId:'',stage:pend?'details':'choose',qty:pend?pend.qty.slice():e.tiers.map(function(){return 0}),people:pend?pend.people.slice():[],open:0,err:{},disc:pend?pend.disc:null,date:pend&&pend.date||(e.dates&&e.dates[0]?e.dates[0].value:''),submission:checkoutSubmissionId(e.slug,pend&&pend.submission),seats:[],seatData:null,seatLoading:false,seatError:''};
  if(pend){S.people=S.people.slice(0,pend.qty.reduce(function(a,b){return a+b},0));S.open=S.people.filter(function(p){return p.done}).length;var _n=pend.qty.reduce(function(a,b){return a+b},0);if(S.open>=_n)S.open=_n-1}
  var view=$('checkout-view');
  /* Tica: free means the chosen tickets cost nothing, not that every ticket type is free. */
  function seatById(id){return (S.seatData||[]).filter(function(s){return +s.id===+id})[0]}
  function selectedSeatsFor(i){var tid=String(tierInfo(i).ticketId);return S.seats.map(seatById).filter(function(s){return s&&String(s.ticket_id)===tid})}
  function lineTotal(i){var q=S.qty[i]||0;if(tierInfo(i).seatingMode!=='reserved')return q*tierPrice(e,i);var picked=selectedSeatsFor(i);return picked.length===q?picked.reduce(function(sum,s){return sum+Math.max(0,+s.price||0)},0):q*tierPrice(e,i)}
  function isFree(){return count()>0?total()<=0:e.tiers.every(function(t,i){return tierPrice(e,i)===0})}
  function slots(){var a=[];S.qty.forEach(function(q,i){for(var k=0;k<q;k++)a.push(e.tiers[i][0])});return a}
  function total(){return S.qty.reduce(function(s,q,i){return s+lineTotal(i)},0)}
  function count(){return S.qty.reduce(function(s,q){return s+q},0)}
  function tierInfo(i){return (e.tierInfo&&e.tierInfo[i])||{}}
  function reservedCount(){return S.qty.reduce(function(s,q,i){return s+(tierInfo(i).seatingMode==='reserved'?q:0)},0)}
  /* A quantity carried from the event page must still pass through authoritative seat selection. */
  if(S.stage==='details'&&reservedCount()>0)S.stage='choose';
  function reservedTicketIds(){var ids={};S.qty.forEach(function(q,i){if(q>0&&tierInfo(i).seatingMode==='reserved')ids[String(tierInfo(i).ticketId)]=q});return ids}
  function net(){var t=total();return t-discountOf(S.disc,t)}
  function paid(){return S.qty.reduce(function(sum,q,i){if(tierInfo(i).seatingMode!=='reserved')return sum+(tierPrice(e,i)>0?q:0);var picked=selectedSeatsFor(i);return sum+(picked.length===q?picked.filter(function(s){return +s.price>0}).length:(tierPrice(e,i)>0?q:0))},0)}
  function fee(){return e.absorb?0:feeFor(net(),paid())}
  function money(){var t=net()+fee();return naira(Math.round(t*100)/100)}
  function summary(){
    var lines=S.qty.map(function(q,i){return q?'<div class="sumline"><span>'+q+' x '+esc(e.tiers[i][0])+'</span><span>'+naira(lineTotal(i))+'</span></div>':''}).join('');
    var action='';
    if(S.stage==='choose')action='<button class="btn btn-hot" type="button" id="to-details"'+(count()?'':' disabled')+'>Continue</button>';
    return '<aside class="summary">'+(S.stage!=='choose'&&HOLD?'<p class="hold" role="timer"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="8.500"/><path d="M12 7.500V12l3 2"/></svg>Tickets held for <b data-hold="'+HOLD.end+'" data-slug="'+e.slug+'">10:00</b></p>':'')+'<h2>Order summary</h2><p class="sumevent">'+esc(e.name)+'<br><span>'+dstr(e)+', '+esc(meta(e))+'</span></p>'+(lines||'<p class="hint">No tickets chosen yet.</p>')+
    (S.disc&&total()?'<div class="sumline"><span>'+esc(discountLabel(S.disc))+'</span><span>-'+naira(discountOf(S.disc,total()))+'</span></div>':'')+(isFree()?'':'<div class="sumline"><span>Booking fee</span><span>'+(e.absorb?'Included':naira(fee()))+'</span></div>')+'<div class="sumline sumtotal"><span>Total</span><span>'+money()+'</span></div>'+action+
    (S.stage==='details'?'<p class="hint">'+(RF&&anyQs()?'Complete every ticket form to continue to the registration form.':'Complete every ticket form to continue to payment.')+'</p>':'')+(S.stage==='form'?'<p class="hint">Fill the form for every person to continue to payment.</p>':'')+'</aside>';
  }
  function chooseHtml(){
    return '<h1 class="display">'+esc(e.name)+'</h1><p class="sub2">'+dstr(e)+', '+esc(meta(e))+'</p><h2>Choose your tickets</h2>'+
    e.tiers.map(function(t,i){if(t[2]===0)return '';return '<div class="tkt"><div><b>'+esc(t[0])+'</b><span>'+naira(tierPrice(e,i))+(tierInfo(i).seatingMode==='reserved'?' · Choose seats':'')+'</span></div><div class="stepper"><button type="button" data-d="-1" data-i="'+i+'" aria-label="Remove one '+esc(t[0])+' ticket">−</button><output>'+S.qty[i]+'</output><button type="button" data-d="1" data-i="'+i+'" aria-label="Add one '+esc(t[0])+' ticket">+</button></div></div>'}).join('')+seatHtml();
  }
  function seatHtml(){var needed=reservedCount();if(!needed)return '';if(S.seatLoading)return '<section class="buyer-seats"><h3>Choose your seats</h3><p class="hint">Loading the live seating map…</p></section>';if(S.seatError)return '<section class="buyer-seats"><h3>Choose your seats</h3><p class="payerr">'+esc(S.seatError)+'</p><button class="btn btn-line" type="button" id="seat-retry">Try again</button></section>';if(!S.seatData)return '<section class="buyer-seats"><h3>Choose your seats</h3><p class="hint">Loading the live seating map…</p></section>';
    var wanted=reservedTicketIds(),groups={};S.seatData.forEach(function(s){if(!wanted[String(s.ticket_id)])return;var key=s.section||'Seating';(groups[key]||(groups[key]=[])).push(s)});var selected={};S.seats.forEach(function(id){selected[id]=1});
    return '<section class="buyer-seats"><div class="buyer-seat-head"><div><h3>Choose your seats</h3><p>'+S.seats.length+' of '+needed+' selected</p></div><div class="buyer-seat-legend"><span><i></i>Available</span><span><i class="selected"></i>Selected</span><span><i class="reserved"></i>Reserved or sold</span></div></div>'+Object.keys(groups).map(function(name){var rows={};groups[name].forEach(function(s){(rows[s.row||'Area']||(rows[s.row||'Area']=[])).push(s)});return '<div class="buyer-section"><h4>'+esc(name)+'</h4>'+Object.keys(rows).map(function(row){return '<div class="buyer-row"><b>'+esc(row)+'</b><div>'+rows[row].map(function(s){var on=selected[s.id],off=s.status!=='available'&&!on,price=+s.price||0,label=(s.section||'')+' row '+(s.row||'')+' seat '+s.label+', '+naira(price)+', '+(on?'selected':s.status);return '<button type="button" class="buyer-seat'+(on?' selected':'')+'" data-seat="'+s.id+'" data-ticket="'+s.ticket_id+'"'+(off?' disabled':'')+' aria-pressed="'+(on?'true':'false')+'" aria-label="'+esc(label)+'"><b>'+esc(s.label)+'</b><small>'+naira(price)+'</small></button>'}).join('')+'</div></div>'}).join('')+'</div>'}).join('')+'</section>';
  }
  function loadSeats(){if(S.seatLoading||S.seatData||!reservedCount())return;S.seatLoading=true;fetch('/tica-api/events/'+e.id+'/seats',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(r){return r.json().then(function(j){if(!r.ok)throw new Error(j.message||'The seating map is unavailable.');return j})}).then(function(j){S.seatLoading=false;S.seatData=j.seats||[];draw()},function(er){S.seatLoading=false;S.seatError=er.message||'The seating map is unavailable.';draw()})}
  function field(i,k,label,type,ac,hint){
    var id='p'+i+'-'+k,err=S.err[i]&&S.err[i][k],v=(S.people[i]&&S.people[i][k])||'';
    return '<div class="inp'+(err?' bad':'')+'"><label for="'+id+'">'+label+'</label><input id="'+id+'" name="'+k+'" type="'+type+'" autocomplete="'+ac+'" value="'+esc(v)+'"'+(err?' aria-invalid="true" aria-describedby="'+id+'-e"':'')+'>'+(err?'<p class="ferr" id="'+id+'-e">'+err+'</p>':(hint?'<p class="hint">'+hint+'</p>':''))+'</div>';
  }
  /* The event's registration form, asked once for every ticket. */
  var RF=e.form&&e.form.questions&&e.form.questions.length?e.form:null;
  function slotTicket(i){var ids=[];S.qty.forEach(function(q,t){for(var k=0;k<q;k++)ids.push(tierInfo(t).ticketId)});return ids[i]}
  function qsFor(i){if(!RF)return [];var tid=slotTicket(i);return RF.questions.filter(function(q){return !q.ticketId||String(q.ticketId)===String(tid)})}
  function qVal(f,q){var els=f.querySelectorAll('[name="q:'+q.key+'"]');if(!els.length)return null;var first=els[0];
    if(q.type==='consent'||q.type==='checkbox')return !!first.checked;
    if(q.type==='multi_select')return [].filter.call(els,function(x){return x.checked}).map(function(x){return x.value});
    if(q.type==='radio'){var on=[].filter.call(els,function(x){return x.checked})[0];return on?on.value:''}
    return first.value.trim()}
  function qEmpty(v){return v===null||v===''||v===false||(Array.isArray(v)&&!v.length)}
  function qShown(q,answers){var c=q.condition;if(!c||!c.key)return true;var a=answers[c.key];if(c.op==='not_empty')return !qEmpty(a);
    var have=(Array.isArray(a)?a:[a]).map(function(x){return x===true?'1':x===false?'0':String(x==null?'':x)}),want=(Array.isArray(c.value)?c.value:[c.value]).map(function(x){return x===true?'1':x===false?'0':String(x==null?'':x)});
    var hit=have.some(function(x){return want.indexOf(x)>-1});return c.op==='not_equals'?!hit:hit}
  function hasQs(i){return qsFor(i).some(function(q){return q.type!=='terms'})}
  function anyQs(){for(var k=0;k<count();k++)if(hasQs(k))return true;return false}
  function lastQ(){for(var k=count()-1;k>=0;k--)if(hasQs(k))return k;return -1}
  /* A person's form counts as done only for the ticket type it was filled for. */
  function fDone(i){var p=S.people[i];if(!hasQs(i))return true;return !!(p&&p.fdone&&String(p.fticket)===String(slotTicket(i)))}
  function qAnswers(f,i){var out={};qsFor(i).forEach(function(q){if(q.type!=='terms')out[q.key]=qVal(f,q)});return out}
  function qSync(f){var i=+f.dataset.i,ans=qAnswers(f,i);qsFor(i).forEach(function(q){var w=f.querySelector('[data-qkey="'+q.key+'"]');if(w)w.hidden=!qShown(q,ans)})}
  function qHtml(i,q){
    var id='p'+i+'-q-'+q.key,err=S.err[i]&&S.err[i]['q:'+q.key],saved=(S.people[i]&&S.people[i].answers)||{},v=saved[q.key],req=q.required?' <span class="qreq" aria-hidden="true">*</span>':'',
      help=q.help?'<p class="hint">'+esc(q.help)+'</p>':'',bad=err?'<p class="ferr" id="'+id+'-e">'+esc(err)+'</p>':'',inv=err?' aria-invalid="true" aria-describedby="'+id+'-e"':'',
      name=' name="q:'+esc(q.key)+'"',open='<div class="inp qf'+(err?' bad':'')+'" data-qkey="'+esc(q.key)+'">',ans=saved;
    if(q.type==='terms')return '<div class="qterms" data-qkey="'+esc(q.key)+'"><b>'+esc(q.label)+'</b>'+(q.help?'<p>'+esc(q.help)+'</p>':'')+'</div>';
    if(q.type==='consent'||q.type==='checkbox')return open+(q.type==='checkbox'?'<span class="qlabel">'+esc(q.label)+req+'</span>':'')+'<label class="qcheck"><input type="checkbox" id="'+id+'"'+name+' value="1"'+(v===true?' checked':'')+inv+'><span>'+(q.type==='consent'?esc(q.label)+req:'Yes')+'</span></label>'+help+bad+'</div>';
    if(q.type==='radio'||q.type==='multi_select'){var pick=Array.isArray(v)?v:[v];return '<fieldset class="inp qf qgroup'+(err?' bad':'')+'" data-qkey="'+esc(q.key)+'"'+(err?' aria-describedby="'+id+'-e"':'')+'><legend>'+esc(q.label)+req+'</legend>'+q.options.map(function(o,k){return '<label class="qcheck"><input type="'+(q.type==='radio'?'radio':'checkbox')+'" id="'+id+'-'+k+'"'+name+' value="'+esc(o)+'"'+(pick.indexOf(o)>-1?' checked':'')+(err&&k===0?' aria-invalid="true"':'')+'><span>'+esc(o)+'</span></label>'}).join('')+help+bad+'</fieldset>'}
    var label='<label for="'+id+'">'+esc(q.label)+req+'</label>',ph=q.placeholder?' placeholder="'+esc(q.placeholder)+'"':'',mx=q.max?' maxlength="'+q.max+'"':'',val=v==null?'':String(v);
    if(q.type==='select')return open+label+'<select id="'+id+'"'+name+inv+'><option value="">Choose an option</option>'+q.options.map(function(o){return '<option value="'+esc(o)+'"'+(o===val?' selected':'')+'>'+esc(o)+'</option>'}).join('')+'</select>'+help+bad+'</div>';
    if(q.type==='textarea')return open+label+'<textarea id="'+id+'"'+name+' rows="3"'+ph+mx+inv+'>'+esc(val)+'</textarea>'+help+bad+'</div>';
    var type={email:'email',phone:'tel',number:'number',date:'date'}[q.type]||'text';
    return open+label+'<input id="'+id+'"'+name+' type="'+type+'" value="'+esc(val)+'"'+ph+mx+inv+(type==='tel'?' autocomplete="tel"':'')+'>'+help+bad+'</div>';
  }
  function consentHtml(){if(!RF)return '';var err=S.err.consent;return '<div class="qconsent'+(err?' bad':'')+'"><label class="qcheck"><input type="checkbox" name="rf-consent" value="1"'+(S.consent?' checked':'')+(err?' aria-invalid="true" aria-describedby="rf-consent-e"':'')+'><span>I agree that TICA and the organiser can use these details to run this event.</span></label>'+(RF.privacy?'<details><summary>Read the privacy notice</summary><p>'+esc(RF.privacy)+'</p></details>':'')+(err?'<p class="ferr" id="rf-consent-e">'+esc(err)+'</p>':'')+'</div>'}
  function nextForm(){for(var k=0;k<count();k++)if(!fDone(k))return k;return -1}
  function toForm(){S.stage='form';S.err={};var k=nextForm();S.fopen=k>-1?k:lastQ();track('registration_form',{event_id:e.id});draw('.rform input, .rform select, .rform textarea');window.scrollTo(0,0)}
  function formHtml(){
    var sl=slots(),n=sl.length;
    var askN=sl.filter(function(t,i){return hasQs(i)}).length;
    return '<button class="backlink" type="button" id="back-details">Back to your details</button><h1 class="display">Registration form</h1><p class="sub2">'+(askN>1?'The organiser asks a few questions for each person. The next person opens when you finish.':'The organiser asks a few questions before you pay.')+'</p>'+(RF.intro?'<p class="rf-intro">'+esc(RF.intro)+'</p>':'')+'<div class="stackforms">'+
    sl.map(function(tier,i){
      var p=S.people[i]||{},who=esc(p.name||('Ticket '+(i+1))),head='Person '+(i+1)+' of '+n+'<span>'+who+', '+esc(tier)+'</span>';
      if(!hasQs(i))return '';
      if(i===S.fopen){
        var last=i===lastQ(),nq=-1;for(var k=i+1;k<n;k++)if(hasQs(k)){nq=k;break}
        var next=!last?'Continue to person '+(nq+1):(isFree()?(n===1?'Get my free ticket':'Get my free tickets'):'Continue to secure payment');
        return '<form class="pform rform open" data-i="'+i+'" novalidate><h3>'+head+'</h3>'+qsFor(i).map(function(q){return qHtml(i,q)}).join('')+(last?consentHtml():'')+
        '<button class="btn btn-hot" type="submit">'+next+'</button>'+
        (last?'<p class="paylegal">By continuing, you agree to TICA\'s <a href="/policies/tickets">terms</a> and <a href="/policies/refunds">refund policy</a>.</p><p class="hint checkout-status" id="pay-msg" role="status"></p>':'')+'</form>';
      }
      if(fDone(i))return '<div class="pform done"><div><h3>'+head+'</h3><p>Form complete</p></div><button class="editbtn" type="button" data-fedit="'+i+'" aria-label="Edit the form for person '+(i+1)+'">Edit</button></div>';
      return '<div class="pform later"><h3>'+head+'</h3></div>';
    }).join('')+'</div>';
  }
  function bindForm(){
    var bd=$('back-details');if(bd)bd.addEventListener('click',function(){S.stage='details';S.err={};S.open=count()-1;draw('.pform.open input')});
    view.querySelectorAll('[data-fedit]').forEach(function(b){b.addEventListener('click',function(){S.fopen=+b.dataset.fedit;S.err={};draw('.rform input, .rform select, .rform textarea')})});
    view.querySelectorAll('form.rform').forEach(function(f){
      qSync(f);f.addEventListener('change',function(){qSync(f)});f.addEventListener('input',function(){qSync(f)});
      f.addEventListener('submit',function(ev){
        ev.preventDefault();
        var i=+f.dataset.i,all=qAnswers(f,i),kept={},er={};
        qsFor(i).forEach(function(q){if(q.type==='terms'||!qShown(q,all))return;var v=all[q.key];kept[q.key]=v;
          if(q.required&&qEmpty(v))er['q:'+q.key]=q.type==='consent'?'Tick this box to continue.':'Answer this question.';
          else if(!qEmpty(v)&&q.type==='email'&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))er['q:'+q.key]='Enter a valid email address.'});
        var cb=f.elements['rf-consent'];if(cb){S.consent=cb.checked;if(!cb.checked)er.consent='Agree to this to continue.'}
        S.people[i]=Object.assign(S.people[i]||{},{answers:kept,fdone:!Object.keys(er).length,fticket:slotTicket(i)});
        if(Object.keys(er).length){S.err={};S.err[i]=er;if(er.consent)S.err.consent=er.consent;draw('.rform [aria-invalid=true]');return}
        S.err={};
        var nxt=nextForm();
        if(nxt>-1){S.fopen=nxt;draw('.rform input, .rform select, .rform textarea');var box=view.querySelector('.rform');if(box)box.scrollIntoView({block:'start'});return}
        var submit=f.querySelector('[type=submit]'),label=submit.textContent;
        submit.disabled=true;submit.textContent=isFree()?'Issuing your tickets…':'Opening '+gwLabel()+'…';
        var status=$('pay-msg');if(status){status.className='hint checkout-status';status.textContent=isFree()?'Creating your tickets…':'Connecting securely to '+gwLabel()+'…'}
        place(function(){submit.disabled=false;submit.textContent=label});
      });
    });
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
        var next=i<sl.length-1?'Continue to ticket '+(i+2):RF&&anyQs()?'Continue to registration form':(isFree()?(sl.length===1?'Get my free ticket':'Get my free tickets'):'Continue to secure payment');
        return '<form class="pform open" data-i="'+i+'" novalidate><h3>'+head+'</h3>'+
        (i===0?'<p class="hint top">We\'ll send all tickets and your receipt to this email.</p>':'')+
        field(i,'name','Full name','text','name')+
        '<div class="two">'+(i===0?field(i,'email','Email address','email','email')+field(i,'phone','Phone number','tel','tel'):field(i,'email','Email address (optional)','email','off','Leave blank to send this ticket to the buyer.')+'<div></div>')+'</div>'+
        (e.question?field(i,'answer',esc(e.question),'text','off'):'')+
        '<button class="btn btn-hot" type="submit">'+next+'</button>'+
        (i===sl.length-1&&!(RF&&anyQs())?'<p class="paylegal">By continuing, you agree to TICA\'s <a href="/policies/tickets">terms</a> and <a href="/policies/refunds">refund policy</a>.</p><p class="hint checkout-status" id="pay-msg" role="status"></p>':'')+'</form>';
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
  function prefill(){var u=window.TICA_USER;if(u&&u.email&&!(S.people[0]&&S.people[0].done)){S.people[0]=Object.assign({name:u.name||'',email:u.email,phone:(S.people[0]&&S.people[0].phone)||''},{fromUser:1})}}
  function draw(focusSel){
    var n={choose:0,details:1,form:2}[S.stage];
    view.innerHTML=stepsBar(n,!!(RF&&anyQs()))+'<div class="chgrid st-'+S.stage+'"><div class="panel">'+(S.stage==='choose'?chooseHtml():S.stage==='form'?formHtml():detailsWrap())+'</div>'+summary()+'</div>';
    bind();
    if(S.stage==='choose')loadSeats();
    if(focusSel){var f=view.querySelector(focusSel);if(f)f.focus()}
  }
  /* Tica: hand the order to the server, then go to the chosen gateway or the confirmation page. */
  function place(onFail){
    if(S.placing)return;
    S.placing=true;
    var m=$('pay-msg');if(m){m.className='hint';m.textContent=''}
    api('/tica-api/checkout',{event_id:e.id,event_date:S.date||null,quantities:S.qty,coupon:S.disc?S.disc.code:null,submission_id:S.submission,seat_ids:S.seats,
      buyer:{name:S.people[0].name,email:S.people[0].email,phone:S.people[0].phone},gateway:isFree()?null:gwKey(),
      consent:RF&&anyQs()?!!S.consent:null,
      attendees:S.people.map(function(p){return {name:p.name,email:p.email||'',answer:p.answer||'',answers:p.answers||{}}})})
    .then(function(r){clearCheckoutSubmission(e.slug);HOLD=null;PENDING=null;location.href=r.redirect},function(er){
      S.placing=false;
      onFail&&onFail();
      if(er.data&&er.data.login){S.stage='details';S.login='id';draw('#al-id');return}
      if(er.data&&er.data.field==='consent'&&RF){S.stage='form';S.fopen=count()-1;S.people[S.fopen].fdone=false;S.err={consent:er.message};draw('.rform [aria-invalid=true]');return}
      if(er.data&&er.data.ticket!=null&&S.people[er.data.ticket]){var t=er.data.ticket,inForm=RF&&/answers/.test(er.data.field||'');if(inForm){S.stage='form';S.people[t].fdone=false;S.fopen=t}else{S.stage='details';S.people[t].done=false;S.open=t}S.err={};draw(inForm?'.rform input':'.pform.open input');var pm=$('pay-msg'),box=view.querySelector('.pform.open');if(box){var note=document.createElement('p');note.className='payerr';note.setAttribute('role','alert');note.textContent=er.message;box.insertBefore(note,box.querySelector('[type=submit]'))}return}
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
      S.qty[i]=Math.max(0,S.qty[i]+d);var limits=reservedTicketIds(),counts={};S.seats=S.seats.filter(function(id){var seat=(S.seatData||[]).filter(function(s){return s.id===id})[0];if(!seat)return false;var k=String(seat.ticket_id);counts[k]=(counts[k]||0)+1;return counts[k]<=Number(limits[k]||0)});draw('.stepper button[data-i="'+i+'"][data-d="'+d+'"]');
    })});
    view.querySelectorAll('.buyer-seat').forEach(function(b){b.addEventListener('click',function(){var id=+b.dataset.seat,ticket=String(b.dataset.ticket),limit=Number(reservedTicketIds()[ticket]||0),has=S.seats.indexOf(id),selectedFor=S.seats.filter(function(x){var seat=(S.seatData||[]).filter(function(s){return s.id===x})[0];return seat&&String(seat.ticket_id)===ticket}).length;if(has>-1)S.seats.splice(has,1);else if(selectedFor<limit)S.seats.push(id);else{flash('You have already chosen enough seats for this ticket type.');return}draw('.buyer-seat[data-seat="'+id+'"]')})});
    var retry=$('seat-retry');if(retry)retry.addEventListener('click',function(){S.seatError='';S.seatData=null;loadSeats();draw()});
    var td=$('to-details');if(td)td.addEventListener('click',function(){
      if(S.seats.length!==reservedCount()){flash('Choose '+reservedCount()+' seats before continuing.');return}
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
    view.querySelectorAll('[data-edit]').forEach(function(b){b.addEventListener('click',function(){S.open=+b.dataset.edit;S.err={};draw('.pform.open input')})});
    bindForm();
    view.querySelectorAll('form.pform:not(.rform)').forEach(function(f){f.addEventListener('submit',function(ev){
      ev.preventDefault();
      var i=+f.dataset.i,g=function(k){var x=f.elements[k];return x?x.value.trim():''},p={name:g('name'),email:g('email'),phone:g('phone'),answer:g('answer')},er={};
      if(e.question&&!p.answer)er.answer='Answer the question for this ticket.';

      if(p.name.length<2)er.name='Enter this person\'s full name.';
      if((i===0||p.email)&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(p.email))er.email='Enter a valid email address, like name@example.com.';
      if(i===0&&p.phone.replace(/\D/g,'').length<10)er.phone='Enter a phone number with at least 10 digits.';
      var prev=S.people[i]||{};S.people[i]=Object.assign(p,{done:!Object.keys(er).length,answers:prev.answers,fdone:prev.fdone});
      if(Object.keys(er).length){S.err={};S.err[i]=er;draw('.pform.open [aria-invalid=true]');return}
      S.err={};
      var nxt=-1;for(var k=0;k<count();k++){if(!(S.people[k]&&S.people[k].done)){nxt=k;break}}
      if(nxt===-1&&RF&&anyQs()){toForm();return}
      if(nxt===-1){
        var submit=f.querySelector('[type=submit]'),label=submit.textContent;
        submit.disabled=true;submit.textContent=isFree()?'Issuing your tickets…':'Opening '+gwLabel()+'…';
        var status=$('pay-msg');if(status){status.className='hint checkout-status';status.textContent=isFree()?'Creating your tickets…':'Connecting securely to '+gwLabel()+'…'}
        place(function(){submit.disabled=false;submit.textContent=label});
      }else{S.open=nxt;draw('.pform.open input')}
    })});
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
  var num=String(i+1).padStart(2,'0'),openIt=i===0,sent=t.email!==o.buyer,d=t.design||{},show=function(k){return d['show_'+k]!==false&&d['show_'+k]!==0&&d['show_'+k]!=='0'},accent=/^#[0-9a-f]{6}$/i.test(d.accent||'')?d.accent:'#245fd3',seat=[show('section')?t.section:null,show('row')&&t.row?'Row '+t.row:null,show('seat')&&t.seat?'Seat '+t.seat:null].filter(Boolean).join(' · ');
  return '<article class="tix" style="--ticket-accent:'+accent+'"><header><div class="tix-t"><b>'+num+' Ticket</b><span>'+(show('category')?esc(t.tier):'Admission')+(show('attendee')?', '+esc(t.name):'')+'</span></div>'+(show('status')?'<span class="status'+(sent||t.status&&t.status!=='issued'?' sent':'')+'">'+(t.status&&t.status!=='issued'?esc(TSTAT[t.status]||t.status):sent?'Sent to '+esc(t.email):'Active')+'</span>':'')+
  '<div class="tix-a"><button type="button" class="ic" data-toggle="pass'+i+'" aria-expanded="'+openIt+'" aria-controls="pass'+i+'" aria-label="'+(openIt?'Hide':'Show')+' QR code for ticket '+(i+1)+'">'+ICO.qr+'</button><button type="button" class="ic" data-print aria-label="Download ticket '+(i+1)+'">'+ICO.dl+'</button></div></header>'+
  '<div class="pass" id="pass'+i+'"'+(openIt?'':' hidden')+'>'+(e.mode==='online'?'<div class="tix-join"><b>Join on '+esc(e.platform)+'</b><p>'+(o.reveal===0?'Your link is ready below. ':'Your link opens here '+(o.reveal>=60?'1 hour':(o.reveal||15)+' minutes')+' before the start, on '+dstr(e)+' at '+esc(e.time)+'. ')+'It\'s also emailed to you.</p>'+(o.join?'<a class="btn btn-hot" href="'+esc(o.join)+'" target="_blank" rel="noopener">Join event</a>':'<a class="btn btn-hot" aria-disabled="true" role="link">Join event</a>')+(show('number')?'<small>'+esc(t.code)+'</small>':'')+'</div>':'<div class="pass-qr">'+qrHtml(t,i)+(show('number')?'<small>'+esc(t.code)+'</small>':'')+'</div>')+
  '<div class="pass-info">'+(show('logo')&&e.image?'<img class="pass-info__logo" src="'+esc(e.image)+'" alt="">':'')+'<span class="samplebadge">Admit one</span><h3>'+esc(e.name)+'</h3><dl>'+(show('attendee')?'<div><dt>Name</dt><dd>'+esc(t.name)+'</dd></div>':'')+(show('category')?'<div><dt>Ticket</dt><dd>'+esc(t.tier)+'</dd></div>':'')+(show('date')?'<div><dt>Date</dt><dd>'+dstr(e)+'</dd></div><div><dt>Time</dt><dd>'+esc(e.time[0].toUpperCase()+e.time.slice(1))+'</dd></div>':'')+(show('venue')?'<div class="wide"><dt>Venue</dt><dd>'+esc(e.venue)+(sameCity(e)?'':', '+esc(e.city))+'</dd></div>':'')+(seat?'<div class="wide"><dt>Seat</dt><dd>'+esc(seat)+'</dd></div>':'')+'</dl></div></div></article>';
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
  var feedback=$('c-msg'),message=function(text,state){if(!feedback)return;feedback.textContent=text||'';feedback.className='ce-feedback'+(state?' is-'+state:'')};
  if(!/^image\/(jpeg|png|webp|gif)$/.test(f.type)){this.value='';message('Use a JPG, PNG, WebP or GIF flyer.','error');return}
  if(f.size>25*1024*1024){this.value='';message('Use a flyer smaller than 25 MB.','error');return}
  if(f.size>2*1024*1024)message('Large flyer selected. TICA will optimize it before upload.','working');
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
function acInitials(name){return ((name||'T').trim().split(/\s+/).slice(0,2).map(function(w){return w.charAt(0)}).join('')||'T').toUpperCase()}
function acAvatar(el,name,photo){
  if(!el)return;
  el.textContent=acInitials(name);el.classList.remove('has-photo');
  if(!photo)return;
  var image=new Image();image.alt='';image.decoding='async';
  image.onerror=function(){el.classList.remove('has-photo')};
  image.onload=function(){el.replaceChildren(image);el.classList.add('has-photo')};
  image.src=photo;
}
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
  $('acd-name').textContent=u.name||'Your account';
  acAvatar($('acd-av'),u.name,pf.photo||u.photo);acAvatar($('pf-photo-preview'),u.name,pf.photo||u.photo);
  $('ac-hi').textContent=(h<12?'Good morning':h<17?'Good afternoon':'Good evening')+(first?', '+first:'');
  $('acd-today').textContent='Your ticket activity and upcoming events, all in one place.';
  var upg=ACC.upgrade,ub=$('acd-upgrade'),un=$('acd-upgrade-note');
  if(ub){ub.hidden=!(upg&&upg.state==='available');if(upg&&upg.url)ub.href=upg.url}
  if(un){un.hidden=!(upg&&upg.state==='pending');un.textContent=upg&&upg.state==='pending'?upg.label:''}
  /* next event */
  var nx=up[0],box=$('acd-next');
  if(nx){var e=nx.e;box.hidden=false;box.innerHTML='<a class="acd-next-f flyer" href="/event/'+e.slug+'" tabindex="-1" aria-hidden="true">'+e.art+'</a><div class="acd-next-b"><p class="acd-k">Your next event</p><h2>'+esc(e.name)+'</h2><p class="acd-next-m">'+acIcon('<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>')+dstr(e)+', '+esc(e.time)+'</p><p class="acd-next-m">'+acIcon('<path d="M12 22s7-6.5 7-12a7 7 0 0 0-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>')+esc(sameCity(e)?e.venue:e.venue+', '+e.city)+'</p><p class="acd-next-c">'+nx.n+(nx.n===1?' ticket':' tickets')+cdMini(e)+'</p><div class="acd-next-a"><a class="btn acd-primary" href="'+esc(nx.url)+'">Show my ticket</a><button class="btn btn-line" type="button" data-addcal="'+e.slug+'">Add to calendar</button>'+(e.mode==='online'?'':'<a class="btn btn-line" href="https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(e.venue+', '+e.city)+'" target="_blank" rel="noopener">Directions</a>')+'</div></div>'}
  else{box.innerHTML='<div class="acd-next-empty"><div><h2>No upcoming tickets</h2><p>Your next ticket will appear here after checkout.</p></div></div>'}
  /* stats */
  var ticketsUp=up.reduce(function(a,o){return a+o.n},0),attended=past.filter(function(o){return o.attended}).length;
  var paidOrders=(ACC.orders||[]).filter(function(o){return o.status!=='refunded'}),spent=paidOrders.reduce(function(a,o){return a+(Number(o.total)||0)},0),spentSymbol=(paidOrders[0]&&paidOrders[0].symbol)||'₦';
  var statData=[['Tickets ready',ticketsUp,'<path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3zM14 8v2M14 13v1M14 16v1"/>','tickets'],['Events attended',attended,'<path d="M5 12.5l4.5 4.5L19 7.5"/>','tickets'],['Orders',(ACC.orders||[]).length,'<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>','orders'],['Total spent',spent,'<path d="M5 7h14M7 4h10M8 7v13M16 7v13M5 11h14"/>','orders']];
  $('acd-stats').innerHTML=statData.map(function(x,i){var value=i===3?spentSymbol+(Math.round(x[1]*100)/100).toLocaleString('en-NG',{minimumFractionDigits:0,maximumFractionDigits:2}):x[1].toLocaleString('en-NG');return '<button type="button" class="acd-stat" data-acp="'+x[3]+'"><i class="acd-ic">'+acIcon(x[2])+'</i><span>'+x[0]+'</span><b>'+value+'</b></button>'}).join('');
  /* later events: the first upcoming event is already shown above */
  var later=up.slice(1,5),laterCard=$('acd-up-card');
  laterCard.hidden=!later.length;
  $('acd-up').innerHTML=later.map(function(o){var e=o.e;return '<li><span class="acd-date"><b>'+esc(e.day)+'</b>'+esc(e.mon)+'</span><span class="acd-mini-b"><a href="'+esc(o.url)+'">'+esc(e.name)+'</a><small>'+esc(e.time)+' · '+esc(e.venue)+'</small></span><em>'+o.n+(o.n===1?' ticket':' tickets')+'</em></li>'}).join('');
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
  var F=$('acd-profile');if(F&&!F.dataset.ready){F.dataset.ready='1';['fname','lname','email','phone','city','country','username'].forEach(function(k){var i=F.querySelector('[name='+k+']');if(i)i.value=pf[k]||''});var photoInput=$('pf-photo');if(photoInput)photoInput.addEventListener('change',function(){var file=this.files&&this.files[0],label=$('pf-photo-name');if(!file){label.textContent='No photo selected';acAvatar($('pf-photo-preview'),u.name,pf.photo||u.photo);return}label.textContent=file.name;if(F._photoPreview)URL.revokeObjectURL(F._photoPreview);F._photoPreview=URL.createObjectURL(file);acAvatar($('pf-photo-preview'),u.name,F._photoPreview)})}
  if(links.data)$('acd-data').href=links.data;
  var organizerEmail=$('acd-organizer-email'),organizerContact=ACC.organizerEmail;
  if(organizerEmail&&organizerContact&&organizerContact.email){
    organizerEmail.hidden=false;
    organizerEmail.href='mailto:'+organizerContact.email+'?subject='+encodeURIComponent(organizerContact.subject||'Event question');
    organizerEmail.querySelector('small').textContent='Ask about '+(organizerContact.event||'your event');
  }else if(organizerEmail){organizerEmail.hidden=true;organizerEmail.removeAttribute('href')}
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
    var saved=function(data){form._lastResponse=data||{};m.className='acd-msg ok';m.textContent=(data&&data.message)||okText;return true};
    if((r.headers.get('content-type')||'').indexOf('application/json')>-1)return r.json().then(saved,function(){return saved({})});
    return saved({});
  },function(){m.className='acd-msg bad';m.textContent='No connection. Check the internet and try again.';return false});
}
document.addEventListener('submit',function(ev){
  var f=ev.target;
  if(f.id==='acd-profile'){ev.preventDefault();var L=(ACC.links||{});acSubmit(f,L.profile,'pf-msg','Saved. Your receipts and tickets use these details.').then(function(ok){if(!ok)return;var pf=ACC.profile||(ACC.profile={});['fname','lname','email','phone','city','country','username'].forEach(function(k){pf[k]=f.querySelector('[name='+k+']').value.trim()});var response=f._lastResponse||{};if(response.profile&&response.profile.photo)pf.photo=response.profile.photo;window.TICA_USER=Object.assign(window.TICA_USER||{},{name:(pf.fname+' '+pf.lname).trim(),email:pf.email,photo:pf.photo});f.querySelector('[name=current_password]').value='';$('pf-cur-wrap').hidden=true;$('acd-name').textContent=window.TICA_USER.name;acAvatar($('acd-av'),window.TICA_USER.name,pf.photo);acAvatar($('pf-photo-preview'),window.TICA_USER.name,pf.photo);acAvatar($('workspace-account'),window.TICA_USER.name,pf.photo);var photo=f.querySelector('[name=photo]');if(photo)photo.value='';$('pf-photo-name').textContent='No photo selected'})}
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
        return '<li><div><b>'+esc(t.holder_name||'Guest')+'</b><span>'+esc(t.holder_email||'')+'</span><small>'+esc((t.variation_name?t.variation_name+' · ':'')+(t.ticket_number?t.ticket_number+' · ':'Booking '+t.booking_id+' · ticket '+t.sequence+' of '+t.quantity)+(t.seat?' · '+[t.section,t.row?'Row '+t.row:null,'Seat '+t.seat].filter(Boolean).join(' · '):''))+'</small></div>'+
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
  var workspaceTitle=document.getElementById('workspace-title'),workspaceRole=document.getElementById('workspace-role'),workspaceAccount=document.getElementById('workspace-account'),workspaceBadge=document.querySelector('.workspace-badge');
  if(workspaceBadge)workspaceBadge.hidden=p==='account';
  if(workspaceRole)workspaceRole.hidden=p==='account';
  if(workspaceTitle)workspaceTitle.textContent=p==='dashboard'?'Organiser dashboard':p==='account'?'Your account':p==='checkin'?'Gate check-in':p==='create'?'Create an event':'TICA workspace';
  if(workspaceRole)workspaceRole.textContent=p==='create'&&window.TICA_ADMIN?'Admin event workspace':(p==='dashboard'||p==='checkin'||p==='create')?'Organiser workspace':'TICA workspace';
  if(workspaceAccount){var customerWorkspace=p==='account',workspaceName=window.TICA_ADMIN?(TICA_DATA.adminBuilder.adminName||'TICA admin'):customerWorkspace&&window.TICA_USER?window.TICA_USER.name:window.TICA_ORG&&window.TICA_DATA.dashboard?window.TICA_DATA.dashboard.org.name:null,workspaceAvatar=customerWorkspace&&window.TICA_USER?window.TICA_USER.photo:window.TICA_ORG&&window.TICA_DATA.dashboard?window.TICA_DATA.dashboard.org.avatar:null;acAvatar(workspaceAccount,workspaceName,workspaceAvatar);workspaceAccount.href=window.TICA_ADMIN?(TICA_DATA.adminBuilder.backUrl||'/admin/dashboard'):customerWorkspace?'/account/profile':'/dashboard/settings'}
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
window.addEventListener('scroll',function(){var topbar=document.querySelector('.topbar');if(topbar)topbar.classList.toggle('scrolled',window.scrollY>8)},{passive:true});
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
    var p=+$('c1').value,q=+$('c2').value,fee=Math.round((p*window.TICA_FEE_PCT/100+window.TICA_FEE_FLAT)*100)/100;
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
        if(!placed){var d=f.querySelector('.au-done');d.textContent=er.message;d.hidden=false;auSwitch(d,er.data&&er.data.switch_role)}
        else if(errs.email&&/log in as an? (organi[sz]er|customer)/i.test(errs.email[0])){auSwitch(f.querySelector('#s-email-e')||f.querySelector('.au-done'),/organi[sz]er\./i.test(errs.email[0])?'org':'att')}
      });
    });
  });
  /* One email, one account type: offer the right log-in in place. */
  function auSwitch(box,role){
    if(!box||(role!=='org'&&role!=='att'))return;
    var b=document.createElement('button');b.type='button';b.className='au-switch';
    b.textContent=role==='org'?'Log in as an organiser':'Log in as a customer';
    b.addEventListener('click',function(){
      if(location.pathname.replace(/\/$/,'')!=='/login'){location.href='/login?role='+role;return}
      var t=document.querySelector('.au-seg button[data-role="'+role+'"]');if(t)t.click();
    });
    box.appendChild(document.createTextNode(' '));box.appendChild(b);box.hidden=false;
  }
  (function(){
    var q=(location.search.match(/[?&](?:role|as)=(org|att)\b/)||[])[1];
    if(q){var t=document.querySelector('.au-seg button[data-role="'+q+'"]');if(t)t.click()}
    var fl=TICA_DATA.flash||{};
    if(fl.error&&document.querySelector('.au')){var d=document.querySelector('[data-auth=login] .au-done');if(d){d.textContent=fl.error;d.hidden=false;auSwitch(d,fl.switch_role)}}
  })();
  /* "Continue with Google" for attendees and organisers; shown only when Admin > Integrations has it on. */
  all('[data-demo]').forEach(function(b){
    if(!TICA_DATA.googleSignIn){b.hidden=true;b.style.display='none';var or=b.parentNode&&b.parentNode.querySelector('.au-or');if(or){or.hidden=true;or.style.display='none'}return}
    b.addEventListener('click',function(){location.href='/auth/google/start?as='+(b.closest('.au').dataset.role==='org'?'org':'att')});
  });
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
(function(){var m=document.querySelector('.menu'),topbar=document.querySelector('.topbar');if(!m||!topbar)return;function s(){document.documentElement.style.setProperty('--menu-top',Math.round(topbar.getBoundingClientRect().bottom)+'px')}m.addEventListener('toggle',s);window.addEventListener('scroll',function(){if(m.open)s()},{passive:true});window.addEventListener('resize',function(){if(m.open)s()})})();
/* design script 07 */
(function(){
var fab=document.getElementById('fab'),fk=document.getElementById('fab-k'),fv=document.getElementById('fab-v'),fb=document.getElementById('fab-b'),tgt=null,mode='';
if(!fab||!fk||!fv||!fb)return;
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
if(!m)return;
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
if(window.TICA_ADMIN)return;
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
$('f-active').addEventListener('click',function(e){var b=e.target.closest('[data-clr]');if(!b)return;var s=$(b.dataset.clr);s.value='all';s.dispatchEvent(new Event('input',{bubbles:true}));s.dispatchEvent(new Event('change',{bubbles:true}))});
new MutationObserver(ui).observe($('count'),{childList:true,characterData:true,subtree:true});
SEL.forEach(function(s){$(s[0]).addEventListener('input',ui);$(s[0]).addEventListener('change',ui)});
addEventListener('tica:navigate',function(){if(more.classList.contains('open'))close()});
document.addEventListener('click',function(e){if(e.target.closest('.nav-ic')){setTimeout(function(){var q=$('d-q');q.focus();q.scrollIntoView({block:'center'})},380)}});
ui();
})();
/* design script 11 */
(function(){if(window.TICA_ADMIN)return;function p(){var s=innerWidth<=480;var e=document.getElementById('h-q');if(e)e.placeholder=s?'Search events':'Search events or artists';var d=document.getElementById('d-q');if(d)d.placeholder=s?'Search events':'Artist, event or organiser'}p();addEventListener('resize',p)})();
/* design script 12 */
(function(){
if(window.TICA_ADMIN)return;
var s=[].slice.call(document.querySelectorAll('.h-slide'));if(s.length<2)return;
var i=0,red=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
s.forEach(function(x,k){if(k){var im=x.querySelector('img');im.loading='eager'}});
setInterval(function(){if(red||document.hidden)return;var hero=document.querySelector('[data-page=home]');if(!hero||hero.hidden)return;s[i].classList.remove('on');i=(i+1)%s.length;s[i].classList.add('on')},6500);
})();
/* design script 14 */
(function(){
if(window.TICA_ADMIN)return;
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
if(window.TICA_ADMIN)return;
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
if(window.TICA_ADMIN)return;
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
if(window.TICA_ADMIN)return;
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
  if(window.TICA_ADMIN)return;
  var page=document.querySelector('.page[data-page="policies"]');if(!page)return;
  var links=[].slice.call(page.querySelectorAll('[data-pol]'));
  var title=page.querySelector('[data-policy-title]');
  var defaultCopy={title:title?title.textContent:'Terms and policies'};
  function hdr(){var t=document.querySelector('.topbar');if(t)page.style.setProperty('--hdr',t.offsetHeight+'px')}hdr();window.addEventListener('resize',hdr);
  function mark(id){links.forEach(function(a){if(a.dataset.pol===id){a.setAttribute('aria-current','true');var ul=a.closest('ul');if(ul&&ul.scrollWidth>ul.clientWidth)ul.scrollTo({left:a.offsetLeft-16,behavior:'smooth'})}else a.removeAttribute('aria-current')})}
  function view(id){var privacy=id==='privacy';page.dataset.policyView=privacy?'privacy':'all';if(title)title.textContent=privacy?'Privacy, simply explained':defaultCopy.title}
  function jump(id,smooth){var el=document.getElementById('pol-'+id);if(!el)return;view(id);if(id==='privacy')page.scrollIntoView({behavior:smooth?'smooth':'instant',block:'start'});else el.scrollIntoView({behavior:smooth?'smooth':'instant',block:'start'});mark(id)}
  function fromHash(){var m=location.pathname.match(/^\/policies\/([a-z]+)/);if(m){view(m[1]);setTimeout(function(){jump(m[1],false)},0);setTimeout(function(){var el=document.getElementById('pol-'+m[1]);if(el&&m[1]!=='privacy'&&Math.abs(el.getBoundingClientRect().top)>window.innerHeight*.5)jump(m[1],false)},450)}else{view('all');mark('summary')}}
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
var ICO={ok:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>',warn:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg>',x:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',cal:'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/></svg>',qr:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16v3a2 2 0 0 0 0 3v3H4v-3a2 2 0 0 0 0-3z"/></svg>'};
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
var PANELS=['overview','events','tickets','orders','payouts','promote','team','settings'];
function allowed(p){return !((p==='events'&&!PERM('events.view'))||(p==='tickets'&&!CAN.orders)||(p==='promote'&&!CAN.promote)||(p==='orders'&&!CAN.orders)||(p==='payouts'&&!CAN.payouts)||(p==='team'&&!CAN.team)||(p==='settings'&&!CAN.settings))}
function show(p,push){
  if(PANELS.indexOf(p)<0||!allowed(p))p='overview';
  [].forEach.call(document.querySelectorAll('.od-p'),function(x){x.hidden=x.dataset.p!==p});
  osActive(p,{overview:'Overview',events:'Events',tickets:'Ticket management',orders:'Orders and attendees',payouts:'Payouts',promote:'Promote',team:'Team',settings:'Settings'}[p]);
  if(push){try{history.replaceState(null,'','/dashboard'+(p==='overview'?'':'/'+p))}catch(e){}window.scrollTo({top:0,behavior:'instant'})}
  var DRAW={events:drawEvents,tickets:drawTicketing,orders:drawAtt,payouts:drawPay,promote:drawPromote,team:drawTeam,overview:drawOverview,settings:drawSettings};
  DRAW[p]&&DRAW[p]();
  var wt=document.getElementById('workspace-title');if(wt)wt.textContent={overview:'Overview',events:'Events',tickets:'Ticket management',orders:'Orders and attendees',payouts:'Payouts',promote:'Promote',team:'Team',settings:'Settings'}[p]||'Organiser dashboard';
}
function fromHash(){var m=location.pathname.match(/^\/dashboard(?:\/([a-z]+))?/);if(m)show(m[1]||'overview',false)}
window.addEventListener('tica:navigate',function(){setTimeout(fromHash,0)});
window.OD_after=function(){setTimeout(fromHash,0)};

/* overview: headline numbers, sales chart, money, events, orders, attention, ticket types, audience, channels */
var OV={range:'30',metric:'revenue'};
var ovResize;window.addEventListener('resize',function(){clearTimeout(ovResize);ovResize=setTimeout(function(){var c=$('db-chart');if(c&&c.offsetParent)drawChart()},150)});
var RANGES=[['14','14 days'],['30','30 days'],['all','All time']];
function nf(n){return Number(n||0).toLocaleString('en-NG')}
function compact(n){n=Number(n||0);var a=Math.abs(n);return a>=1e9?(n/1e9).toFixed(1).replace(/\.0$/,'')+'B':a>=1e6?(n/1e6).toFixed(1).replace(/\.0$/,'')+'M':a>=1e3?(n/1e3).toFixed(1).replace(/\.0$/,'')+'k':String(Math.round(n))}
function money(n){return N(n)}
function moneyShort(n){var s=N(0).replace(/[\d.,\s]+/g,'');return s+compact(n)}
function perLabel(){return OV.range==='all'?'all time':'the last '+OV.range+' days'}
function chartRows(){return (D.chart&&D.chart[OV.range])||[]}
function drawOverviewKpis(range){
  if(range)OV.range=range;
  var k=D.kpisByRange&&D.kpisByRange[OV.range]||D.kpis||{};
  var cards=[
    ['Revenue',money(k.gross),CAN.orders?'orders':'','money'],
    ['Tickets',nf(k.sold),'tickets','tickets'],
    ['Orders',nf(k.orders),CAN.orders?'orders':'','orders'],
    ['Audience',nf(k.views),CAN.promote?'promote':'','audience']
  ];
  $('db-kpis').innerHTML=cards.map(function(c){var tag=c[2]?'button type="button" data-panel="'+c[2]+'" aria-label="Open '+c[0]+'"':'div';
    return '<'+tag+' class="ov-summary__item ov-summary__item--'+c[3]+'"><span class="ov-summary__label">'+c[0]+'</span><span class="ov-summary__value">'+esc(c[1])+'</span></'+(c[2]?'button':'div')+'>'}).join('');
}
function drawChart(range){
  if(range)OV.range=range;
  var rows=chartRows(),rev=OV.metric==='revenue',vals=rows.map(function(r){return rev?(r[2]||0):r[1]}),total=vals.reduce(function(a,b){return a+b},0),el=$('db-chart');
  $('od-chart-sub').textContent=(OV.range==='all'?'Monthly ':'Daily ')+(rev?'revenue':'tickets sold')+', '+perLabel()+'. Hover for each '+(OV.range==='all'?'month':'day')+'.';
  [].forEach.call(document.querySelectorAll('.ov-toggle [data-metric]'),function(b){b.setAttribute('aria-checked',String(b.dataset.metric===OV.metric))});
  $('od-chart-table').innerHTML='<table class="t-table"><thead><tr><th scope="col">'+(OV.range==='all'?'Month':'Day')+'</th><th scope="col" class="t-num">Tickets</th><th scope="col" class="t-num">Revenue</th></tr></thead><tbody>'+rows.map(function(r){return '<tr><td>'+esc(r[0])+'</td><td class="t-num">'+nf(r[1])+'</td><td class="t-num">'+money(r[2]||0)+'</td></tr>'}).join('')+'</tbody></table>';
  el.classList.toggle('is-empty',!total);
  if(!total){el.innerHTML='<div class="ov-empty ov-empty--chart"><span class="ov-empty__icon" aria-hidden="true">'+ICO.warn+'</span><div><h3>No '+(rev?'revenue':'ticket sales')+' in '+perLabel()+'</h3><p>Your chart will build as tickets are issued.</p></div>'+(CAN.promote?'<button class="ov-link" type="button" data-panel="promote">Share an event</button>':'')+'</div>';return}
  el.innerHTML='';var W=Math.max(300,Math.round(el.clientWidth||720)),room=Math.round(el.clientHeight||0),H=W<520?220:Math.min(420,Math.max(260,room-8)),pl=W<520?40:48,pr=12,pt=12,pb=28,iw=W-pl-pr,ih=H-pt-pb,max=Math.max.apply(null,vals);
  var raw=(max||1)/4,mag=Math.pow(10,Math.floor(Math.log10(raw))),nice=[1,2,2.5,5,10].map(function(m){return m*mag}).filter(function(v){return v>=raw})[0]||raw,top=nice*4;
  var x=function(i){return pl+(rows.length>1?i*iw/(rows.length-1):iw/2)},y=function(v){return pt+ih-(v/top)*ih};
  var grid='',ticks='';for(var g=0;g<=4;g++){var gv=nice*g,gy=y(gv);grid+='<line x1="'+pl+'" x2="'+(W-pr)+'" y1="'+gy+'" y2="'+gy+'"/>';ticks+='<text x="'+(pl-8)+'" y="'+(gy+4)+'" text-anchor="end">'+(rev?moneyShort(gv):compact(gv))+'</text>'}
  var every=Math.ceil(rows.length/(W<520?4:7)),xl=rows.map(function(r,i){var last=rows.length-1;return (i!==last&&(i%every||last-i<every*.7))?'':'<text x="'+x(i)+'" y="'+(H-8)+'" text-anchor="'+(i===0?'start':i===rows.length-1?'end':'middle')+'">'+esc(r[0])+'</text>'}).join('');
  var line=vals.map(function(v,i){return (i?'L':'M')+x(i).toFixed(1)+' '+y(v).toFixed(1)}).join(' '),area=line+' L'+x(vals.length-1).toFixed(1)+' '+y(0)+' L'+x(0).toFixed(1)+' '+y(0)+' Z';
  el.innerHTML='<div class="ov-plot"><svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+esc((rev?'Revenue':'Tickets sold')+' over '+perLabel()+': '+(rev?money(total):nf(total))+' in total')+'"><defs><linearGradient id="ovfill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#2E4FD8" stop-opacity=".18"/><stop offset="1" stop-color="#2E4FD8" stop-opacity="0"/></linearGradient></defs><g class="ov-plot__grid">'+grid+'</g><g class="ov-plot__y">'+ticks+'</g><g class="ov-plot__x">'+xl+'</g><path class="ov-plot__area" d="'+area+'"/><path class="ov-plot__line" d="'+line+'"/><line class="ov-plot__cross" y1="'+pt+'" y2="'+(pt+ih)+'" x1="0" x2="0" hidden/><circle class="ov-plot__dot" r="5" hidden/></svg><div class="ov-tip" hidden></div></div>';
  var svg=el.querySelector('svg'),tip=el.querySelector('.ov-tip'),cross=svg.querySelector('.ov-plot__cross'),dot=svg.querySelector('.ov-plot__dot');
  function at(clientX){var r=svg.getBoundingClientRect(),px=(clientX-r.left)/r.width*W,i=Math.round((px-pl)/(rows.length>1?iw/(rows.length-1):1));return Math.max(0,Math.min(rows.length-1,i))}
  function hover(i){var cx=x(i),cy=y(vals[i]);cross.setAttribute('x1',cx);cross.setAttribute('x2',cx);cross.hidden=false;dot.setAttribute('cx',cx);dot.setAttribute('cy',cy);dot.hidden=false;
    tip.innerHTML='<b>'+esc(rows[i][0])+'</b><span>'+money(rows[i][2]||0)+'</span><span>'+nf(rows[i][1])+' '+(rows[i][1]===1?'ticket':'tickets')+'</span>';tip.hidden=false;
    var pr2=svg.getBoundingClientRect(),left=cx/W*pr2.width;tip.style.left=Math.min(Math.max(left,70),pr2.width-70)+'px';tip.style.top=(cy/H*pr2.height)+'px'}
  svg.addEventListener('pointermove',function(e){hover(at(e.clientX))});
  svg.addEventListener('pointerleave',function(){cross.hidden=true;dot.hidden=true;tip.hidden=true});
}
function drawRangeControls(){
  $('od-range').innerHTML=RANGES.map(function(r){return '<option value="'+r[0]+'">'+(r[0]==='all'?'All time':'Last '+r[1])+'</option>'}).join('');$('od-range').value=OV.range;
  $('od-range-seg').innerHTML=RANGES.map(function(r){return '<button type="button" role="radio" aria-checked="'+(r[0]===OV.range)+'" data-range="'+r[0]+'">'+r[1]+'</button>'}).join('');
}
function setRange(v){OV.range=v;drawRangeControls();drawOverviewKpis(v);drawChart(v)}
$('od-range').addEventListener('change',function(){setRange(this.value)});
document.addEventListener('click',function(e){var b=e.target.closest('#od-range-seg [data-range]');if(b){setRange(b.dataset.range);return}var m=e.target.closest('.ov-toggle [data-metric]');if(m){OV.metric=m.dataset.metric;drawChart()}});
function drawOverviewMoney(){
  var p=D.payouts||{},acc=p.account,box=$('od-money');if(!CAN.payouts){box.hidden=true;return}
  var status=!acc?null:acc.held?['warn','Payouts paused','Your new bank account is in its 24-hour safety hold.']:!acc.verified?['warn','Account not verified','Finish verifying your bank account to get paid.']:['ok',esc(acc.bank)+' ••'+esc(acc.last4),p.schedule==='auto'?'Paid out automatically when sales are released.':'Withdraw whenever you like.'];
  box.innerHTML='<div class="ov-card__head"><div><h2 class="ov-card__title" id="od-money-h">Payouts</h2><p class="ov-card__sub">Your available and processed balance.</p></div><button class="ov-link" type="button" data-panel="payouts">Open</button></div>'+
    '<div class="ov-money__lead"><p class="ov-money__label">Available now</p><p class="ov-money__value">'+money(p.available||0)+'</p></div>'+
    '<dl class="ov-money__list"><div><dt>Processing</dt><dd>'+money(p.pending||0)+'</dd></div><div><dt>Paid out</dt><dd>'+money(p.paid||0)+'</dd></div>'+((p.currencies||[]).filter(function(c){return c.code!==((window.TICA_DATA.currency||{}).code)}).map(function(c){return '<div><dt>'+esc(c.code)+' balance</dt><dd>'+Nc(c.amount,c.cur)+'</dd></div>'}).join(''))+'</dl>'+
    (status?'<p class="ov-status ov-status--'+status[0]+'"><span class="ov-status__icon" aria-hidden="true">'+(status[0]==='ok'?ICO.ok:ICO.warn)+'</span><span><b>'+status[1]+'</b>'+status[2]+'</span></p>':'')+
    '<button class="t-btn t-btn--secondary t-btn--block" type="button" data-panel="payouts">'+(acc&&acc.verified?'Withdraw':'Set up payouts')+'</button>';
}
function drawOverviewEvents(){
  var ev=(D.events||[]).filter(function(e){return e.state!=='draft'}),up=ev.filter(function(e){return e.state==='up'}).sort(function(a,b){return a.ts-b.ts}),past=ev.filter(function(e){return e.state==='past'}).sort(function(a,b){return b.ts-a.ts});
  var list=up.concat(past),box=$('od-top');
  if(D.next&&!list.some(function(e){return String(e.id)===String(D.next.id)}))list.unshift(D.next);
  list=list.slice(0,4);
  if(!list.length){box.innerHTML='<div class="ov-empty ov-empty--compact"><span class="ov-empty__icon" aria-hidden="true">'+ICO.cal+'</span><div><h3>No published events yet</h3><p>Publish an event to see its ticket pace here.</p></div>'+(CAN.events?'<a class="ov-link" href="/create">Create an event</a>':'')+'</div>';return}
  var tone={'On sale':'green','Sales paused':'orange','Ended':'','Cancelled':'red','Cancelling':'red'};
  box.innerHTML='<table class="ov-table"><thead><tr><th scope="col">Event</th><th scope="col">Status</th><th scope="col">Sold</th><th scope="col" class="t-num">Revenue</th><th scope="col"><span class="t-sr">Actions</span></th></tr></thead><tbody>'+list.map(function(e){
    var pct=e.cap?Math.min(100,Math.round(e.sold/e.cap*100)):null;
    return '<tr><td data-label="Event"><span class="ov-ev"><span class="ov-ev__date"><b>'+e.day+'</b>'+esc(e.mon)+'</span><span><span class="ov-ev__name">'+esc(e.name)+'</span><span class="ov-ev__meta">'+esc([e.time,e.mode==='Online'?'Online':e.city].filter(Boolean).join(' · '))+'</span></span></span></td>'+
      '<td data-label="Status"><span class="ov-pill ov-pill--'+(tone[e.status]||'grey')+'">'+esc(e.status)+'</span></td>'+
      '<td data-label="Sold"><span class="ov-sold"><span>'+nf(e.sold)+(e.cap?' / '+nf(e.cap):'')+'</span>'+(pct!=null?'<span class="ov-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'+pct+'" aria-label="'+pct+'% sold"><i style="width:'+pct+'%"></i></span>':'<span class="ov-sold__open">No limit</span>')+'</span></td>'+
      '<td data-label="Revenue" class="t-num">'+(e.revenue?Nc(e.revenue,e.cur):'—')+'</td>'+
      '<td class="ov-table__act">'+(CAN.events?'<button class="ov-link" type="button" data-manage="'+e.id+'">Manage</button>':'')+'</td></tr>'}).join('')+'</tbody></table>'+(list.length<3&&CAN.events?'<div class="ov-card__foot"><a class="ov-foot-cta" href="/create"><svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M12 5v14M5 12h14\"/></svg>Create another event</a></div>':'');
}
function drawOverviewNext(){
  var e=D.next,box=$('od-next');
  if(!e){box.innerHTML='<div class="ov-card__head"><h2 class="ov-card__title">Next event</h2></div><div class="ov-empty"><span class="ov-empty__icon" aria-hidden="true">'+ICO.cal+'</span><h3>Nothing scheduled</h3><p>Your next event shows here with a countdown and its sales.</p>'+(CAN.events?'<a class="t-btn t-btn--secondary" href="/create">Create an event</a>':'')+'</div>';return}
  var ms=e.ts-Date.now(),days=Math.max(0,Math.floor(ms/864e5)),hrs=Math.max(0,Math.floor(ms%864e5/36e5)),pct=e.cap?Math.min(100,Math.round(e.sold/e.cap*100)):null;
  box.innerHTML='<div class="ov-card__head"><div><h2 class="ov-card__title">Next event</h2><p class="ov-card__sub">Your nearest live operation.</p></div><span class="ov-pill ov-pill--blue">'+(days===0?(hrs?'In '+hrs+' h':'Today'):days===1?'Tomorrow':'In '+days+' days')+'</span></div>'+
    '<div class="ov-next__brief"><div class="ov-next__flyer flyer" aria-hidden="true">'+e.art+'</div><div class="ov-next__copy"><h3 class="ov-next__name">'+esc(e.name)+'</h3><p class="ov-next__meta">'+ds(e)+' · '+esc(e.time)+(e.venue?' · '+esc(e.venue):'')+'</p><p class="ov-next__pace"><b>'+nf(e.sold)+'</b> '+(e.cap?'of '+nf(e.cap):'')+' tickets sold</p></div></div>'+
    (pct!=null?'<div class="ov-bar ov-bar--lg" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'+pct+'" aria-label="'+pct+'% of tickets sold"><i style="width:'+pct+'%"></i></div><p class="ov-next__pct">'+pct+'% of capacity</p>':'')+
    '<div class="ov-next__act">'+(CAN.events?'<button class="t-btn t-btn--secondary" type="button" data-manage="'+e.id+'">Manage</button>':'')+(CAN.checkin?'<a class="t-btn t-btn--ghost" href="/checkin">Check in guests</a>':'')+'</div>';
}
function drawOverviewOrders(){
  var ord=(D.orders||[]).slice(0,6);
  $('od-order-count').textContent=ord.length?'The latest '+ord.length+' completed '+(ord.length===1?'order':'orders')+'.':'Completed orders appear here.';
  $('db-ord').innerHTML=ord.length?'<ul class="ov-orders__list">'+ord.map(function(o){return '<li><span class="ov-av" aria-hidden="true">'+esc((o.name||'?').trim().charAt(0).toUpperCase()||'?')+'</span><span class="ov-orders__who"><b>'+esc(o.name||'Guest')+'</b><span>'+esc(o.tickets)+'</span></span><span class="ov-orders__amt"><b>'+(o.amount?Nc(o.amount,o.cur):'Free')+'</b><span>'+esc(o.ago)+'</span></span></li>'}).join('')+'</ul>'+(ord.length<4&&CAN.promote?'<div class="ov-card__foot"><p class="ov-foot-note"><span>Share your event link on WhatsApp and Instagram, or make a tracking link to see which channel sells. <button class="ov-link" type="button" data-panel="promote">Promote</button></span></p></div>':'')
    :'<div class="ov-empty"><span class="ov-empty__icon" aria-hidden="true">'+ICO.qr+'</span><h3>No orders yet</h3><p>Orders appear here as soon as someone gets a ticket.</p>'+(CAN.promote?'<button class="t-btn t-btn--secondary" type="button" data-panel="promote">Share your event</button>':'')+'</div>';
}
function drawOverviewChannels(){
  var links=(D.links||[]).slice().sort(function(a,b){return (b.sales||0)-(a.sales||0)||(b.clicks||0)-(a.clicks||0)}),ps=D.promotionStats||{},box=$('od-channels');
  if(!CAN.promote){box.hidden=true;return}
  var top=links.slice(0,8),hasRev=links.some(function(l){return l.revenue}),promo='';
  box.innerHTML='<div class="ov-card__head"><div><h2 class="ov-card__title" id="od-channels-h">Where sales come from</h2><p class="ov-card__sub">Each tracking link you share, and what it brought in.</p></div><button class="t-btn t-btn--secondary t-btn--sm" type="button" data-panel="promote">New tracking link</button></div>'+
    (top.length?'<div class="ov-chan-wrap"><table class="ov-chan-table"><thead><tr><th scope="col">Link</th><th scope="col">Channel</th><th scope="col" class="t-num">Clicks</th><th scope="col" class="t-num">Tickets</th><th scope="col" class="t-num">Conversion</th>'+(hasRev?'<th scope="col" class="t-num">Revenue</th>':'')+'</tr></thead><tbody>'+top.map(function(l){var conv=l.clicks?Math.round((l.conversions||l.sales||0)/l.clicks*1000)/10:0;
      return '<tr><td data-label="Link"><b>'+esc(l.name)+'</b><span>'+esc(l.code)+'</span></td><td data-label="Channel">'+esc(l.channel||l.influencer||'—')+'</td><td data-label="Clicks" class="t-num">'+nf(l.clicks)+'</td><td data-label="Tickets" class="t-num">'+nf(l.sales)+'</td><td data-label="Conversion" class="t-num">'+conv+'%</td>'+(hasRev?'<td data-label="Revenue" class="t-num">'+(l.revenue?money(l.revenue):'—')+'</td>':'')+'</tr>'}).join('')+'</tbody></table></div>'+(links.length>top.length?'<p class="ov-chan__more"><button class="ov-link" type="button" data-panel="promote">See all '+links.length+' links</button></p>':'')
      :'<div class="ov-empty"><span class="ov-empty__icon" aria-hidden="true">'+ICO.qr+'</span><h3>No tracking links yet</h3><p>Make a link for each channel or promoter to see which one sells.</p><button class="t-btn t-btn--secondary" type="button" data-panel="promote">Create a tracking link</button></div>')+promo;
}
function drawOverviewSetup(){
  var s=D.setup,doneN=[s.identity,s.published,s.payout].filter(Boolean).length,setup=$('od-setup');
  setup.hidden=doneN===3||!CAN.payouts;
  if(setup.hidden)return;
  var next=!s.identity?['Verify your identity so you can be paid.','Verify identity',D.setup.kycUrl]:!s.payout?['Add the bank account your sales should go to.','Add bank account',null]:['Publish your first event.','Create an event','/create'];
  var step=function(done,label){return '<li class="'+(done?'is-done':'')+'"><span class="ov-tick" aria-hidden="true">'+(done?ICO.ok:'')+'</span><span class="t-sr">'+label+(done?' complete':' pending')+'</span></li>'};
  setup.innerHTML='<div class="ov-setup__count"><b>'+doneN+'/3</b><span>ready</span></div><div class="ov-setup__text"><h2 id="od-setup-h">Complete your payout setup</h2><p>'+next[0]+'</p></div><ol class="ov-steps" aria-label="Setup progress">'+step(s.identity,'Identity')+step(s.published,'First event')+step(s.payout,'Payout account')+'</ol>'+
    (next[2]?'<a class="t-btn t-btn--primary" href="'+esc(next[2])+'">'+next[1]+'</a>':'<button class="t-btn t-btn--primary" type="button" data-panel="payouts">'+next[1]+'</button>');
}
function drawOverview(){
  if($('od-overview-create'))$('od-overview-create').hidden=!CAN.events;
  drawOverviewSetup();drawOverviewMoney();drawOverviewEvents();drawOverviewNext();drawOverviewOrders();drawOverviewChannels();
  if($('od-paydot'))$('od-paydot').hidden=D.setup.payout||!CAN.payouts;
}
function renderDash(){
  if($('db-org'))$('db-org').textContent=D.org.name;
  var h=new Date().getHours();$('db-hi').textContent=(h<12?'Good morning':h<17?'Good afternoon':'Good evening')+', '+D.org.name.split(' ')[0];
  if($('od-today'))$('od-today').textContent=new Date().toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
  if($('od-refreshed')&&D.generatedAt){var mins=Math.max(0,Math.floor((Date.now()-new Date(D.generatedAt).getTime())/60000));$('od-refreshed').textContent=mins<1?'Updated just now.':mins===1?'Updated a minute ago.':'Updated '+mins+' minutes ago.'}
  var nw=window.TICA_NEW;$('db-flag').hidden=!nw;if(nw)$('db-flag').textContent=nw.name+(nw.status==='draft'?' is saved as a draft.':' is now live.');
  drawRangeControls();drawOverviewKpis(OV.range);drawChart(OV.range);
  drawOverview();fromHash();
  /* The chart fills its card, so draw it again once the cards beside it are in place. */
  requestAnimationFrame(function(){var c=$('db-chart');if(c&&c.offsetParent)drawChart()});
}
window.TICA_renderDash=renderDash;

/* events */
function evRows(){var q=($('ev-q')&&$('ev-q').value||'').trim().toLowerCase();return D.events.filter(function(r){return r.state===OD.ef&&(!q||(r.name+' '+(r.city||'')+' '+(r.venue||'')).toLowerCase().indexOf(q)>-1)}).sort(function(a,b){return OD.ef==='past'?b.ts-a.ts:a.ts-b.ts})}
OD.evPage=0;var EV_PER=10;
function evStatus(r){var cls=r.status==='On sale'?'green':r.status==='Sales paused'||r.status==='Cancelling'?'orange':r.status==='Cancelled'?'red':r.state==='draft'?'':'blue';return '<span class="t-badge'+(cls?' t-badge--'+cls:'')+'">'+esc(r.status)+'</span>'}
var floatingRowMenu=null;
function restoreRowMenu(details){
  if(!details||!details._ticaMenuList)return;
  var list=details._ticaMenuList,marker=details._ticaMenuMarker;
  if(marker&&marker.parentNode)marker.parentNode.insertBefore(list,marker);
  if(marker&&marker.parentNode)marker.parentNode.removeChild(marker);
  list.classList.remove('is-floating');
  list.removeAttribute('style');
  details._ticaMenuList=null;details._ticaMenuMarker=null;
  if(floatingRowMenu===details)floatingRowMenu=null;
}
function closeRowMenu(){
  if(!floatingRowMenu)return;
  var details=floatingRowMenu;
  details.removeAttribute('open');
  restoreRowMenu(details);
}
function floatRowMenu(details){
  if(floatingRowMenu&&floatingRowMenu!==details)closeRowMenu();
  var list=details.querySelector('.t-menu__list'),trigger=details.querySelector('summary');
  if(!list||!trigger)return;
  var marker=document.createComment('row-menu');
  list.parentNode.insertBefore(marker,list);
  details._ticaMenuList=list;details._ticaMenuMarker=marker;floatingRowMenu=details;
  document.body.appendChild(list);list.classList.add('is-floating');
  list.style.visibility='hidden';list.style.left='12px';list.style.top='12px';list.style.maxHeight=Math.max(120,window.innerHeight-24)+'px';
  var rect=trigger.getBoundingClientRect(),width=list.offsetWidth,height=Math.min(list.scrollHeight,window.innerHeight-24),gap=6,pad=12;
  var left=Math.max(pad,Math.min(rect.right-width,window.innerWidth-width-pad));
  var below=rect.bottom+gap,top=below+height<=window.innerHeight-pad?below:Math.max(pad,rect.top-height-gap);
  list.style.left=Math.round(left)+'px';list.style.top=Math.round(top)+'px';list.style.visibility='visible';
  requestAnimationFrame(function(){var first=list.querySelector('a,button');if(first&&details.open)first.focus()});
}
function bindRowMenus(root){
  [].forEach.call(root.querySelectorAll('.t-menu'),function(details){details.addEventListener('toggle',function(){if(details.open)floatRowMenu(details);else restoreRowMenu(details)})});
}
function drawEvents(){
  closeRowMenu();
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
  bindRowMenus($('od-evlist'));
}
document.addEventListener('input',function(e){if(e.target.id==='ev-q'){OD.evPage=0;drawEvents()}});
document.addEventListener('click',function(e){var p=e.target.closest('[data-evpage]');if(p){OD.evPage+=+p.dataset.evpage;drawEvents();return}
  var o=e.target.closest('[data-ev-orders]');if(o){var f=$('od-fe');show('orders',true);if(f){f.value=o.dataset.evOrders;f.dispatchEvent(new Event('change',{bubbles:true}))}return}
  var m=document.querySelector('.t-menu[open]'),list=m&&m._ticaMenuList;if(m&&!m.contains(e.target)&&!(list&&list.contains(e.target)))m.removeAttribute('open');else if(m&&list&&list.contains(e.target)&&e.target.closest('a,button'))m.removeAttribute('open')});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){var m=document.querySelector('.t-menu[open]');if(m){m.removeAttribute('open');m.querySelector('summary').focus()}}});
document.addEventListener('scroll',function(e){if(floatingRowMenu&&e.target!==floatingRowMenu._ticaMenuList)closeRowMenu()},true);
window.addEventListener('resize',closeRowMenu);

/* ticket inventory and individual assignments */
var TK=[],tkCursor=null,tkLoading=false,tkRequest=0,tkTimer=null;
function ticketCategoryRows(){
  var q=($('tk-cat-q').value||'').trim().toLowerCase(),event=$('tk-cat-event').value;
  return (D.ticketing&&D.ticketing.categories||[]).filter(function(t){return (!event||String(t.eventId)===event)&&(!q||(t.event+' '+t.name+' '+t.prefix).toLowerCase().indexOf(q)>-1)});
}
function drawTicketing(){
  var info=D.ticketing||{summary:{},categories:[]},s=info.summary||{},cats=info.categories||[];
  $('tk-stats').innerHTML=[['Tickets sold',s.sold||0,'Issued and valid'],['Available',s.available||0,'Across limited categories'],['Numbered',s.numbered||0,'Permanent ticket numbers'],['Reserved seats',s.seated||0,'Assigned seat records'],['Ticket revenue',N(s.revenue||0),'Completed and free orders']].map(function(x){return '<article class="t-card t-stat"><span class="t-stat__label">'+x[0]+'</span><b class="t-stat__value">'+esc(String(x[1]))+'</b><span class="t-stat__note">'+x[2]+'</span></article>'}).join('');
  var eventNames={};cats.forEach(function(t){eventNames[t.eventId]=t.event});
  var eventOptions=Object.keys(eventNames).map(function(id){return '<option value="'+id+'">'+esc(eventNames[id])+'</option>'}).join('');
  if($('tk-cat-event').options.length<2)$('tk-cat-event').insertAdjacentHTML('beforeend',eventOptions);
  if($('tk-event').options.length<2)$('tk-event').insertAdjacentHTML('beforeend',eventOptions);
  var currentCategory=$('tk-category').value;$('tk-category').innerHTML='<option value="">All ticket types</option>'+cats.filter(function(t){return !$('tk-event').value||String(t.eventId)===$('tk-event').value}).map(function(t){return '<option value="'+t.id+'">'+esc(t.name+' · '+t.event)+'</option>'}).join('');$('tk-category').value=currentCategory;
  paintTicketCategories();
  tkRequest++;tkLoading=false;tkCursor=null;TK=[];clearTimeout(tkTimer);tkTimer=setTimeout(function(){loadTickets(false)},160);
}
function paintTicketCategories(){
  var rows=ticketCategoryRows();
  if(!rows.length){$('tk-cat-table').innerHTML='<div class="t-empty"><h3>No ticket types match</h3><p>Change the event or search term.</p></div>';return}
  $('tk-cat-table').innerHTML='<div class="t-table-wrap"><table class="t-table t-table--cards tk-table"><thead><tr><th scope="col">Ticket type</th><th scope="col">Inventory</th><th scope="col">Sold</th><th scope="col">Available</th><th scope="col" class="t-num">Revenue</th><th scope="col">Configuration</th></tr></thead><tbody>'+rows.map(function(t){return '<tr><td data-label=""><b>'+esc(t.name)+'</b><div class="t-muted or-sub">'+esc(t.event)+'</div></td><td data-label="Inventory">'+(t.inventory==null?'—':Number(t.inventory).toLocaleString('en-NG'))+'</td><td data-label="Sold">'+Number(t.sold||0).toLocaleString('en-NG')+'</td><td data-label="Available">'+(t.available==null?'—':Number(t.available).toLocaleString('en-NG'))+'</td><td data-label="Revenue" class="t-num">'+N(t.revenue||0)+'</td><td data-label="Configuration"><span class="t-badge'+(t.status==='on_sale'?' t-badge--green':' t-badge--orange')+'">'+esc(t.status==='on_sale'?'On sale':'Paused')+'</span><div class="t-muted or-sub">'+esc(t.seatingMode==='reserved'?'Reserved seating':'General admission')+(t.prefix?' · '+esc(t.prefix):'')+'</div></td></tr>'}).join('')+'</tbody></table></div>';
}
function ticketQuery(){return 'q='+encodeURIComponent($('tk-q').value.trim())+'&event='+$('tk-event').value+'&ticket='+$('tk-category').value+'&status='+$('tk-status').value}
function loadTickets(append){
  if(tkLoading||append&&!tkCursor)return;var seq=++tkRequest,url='/tica-api/organizer/attendees?'+ticketQuery()+(append?'&cursor='+encodeURIComponent(tkCursor):'');tkLoading=true;$('tk-units').setAttribute('aria-busy','true');$('tk-more').disabled=true;
  if(!append)$('tk-units').innerHTML='<div class="t-table-wrap or-skel">'+[1,2,3,4].map(function(){return '<span class="t-skel" style="height:52px;margin:12px 16px"></span>'}).join('')+'</div>';
  get(url).then(function(r){if(seq!==tkRequest)return;TK=append?TK.concat(r.rows):r.rows;tkCursor=r.nextCursor||null;paintTickets(r)},function(er){if(seq===tkRequest){$('tk-msg').textContent=er.message;$('tk-more').hidden=true}}).then(function(){if(seq===tkRequest){tkLoading=false;$('tk-units').removeAttribute('aria-busy');$('tk-more').disabled=false}});
}
function paintTickets(r){
  if(!TK.length){$('tk-units').innerHTML='<div class="t-empty"><h3>No assigned tickets match</h3><p>Ticket assignments appear after a verified payment or complimentary issue.</p></div>';$('tk-more').hidden=true;$('tk-msg').textContent='';return}
  $('tk-units').innerHTML='<div class="t-table-wrap"><table class="t-table t-table--cards tk-table"><thead><tr><th scope="col">Ticket number</th><th scope="col">Attendee</th><th scope="col">Ticket type</th><th scope="col">Seat</th><th scope="col">Status</th><th scope="col"><span class="t-sr">Manage</span></th></tr></thead><tbody>'+TK.map(function(a){var seat=[a.section,a.row,a.seat].filter(Boolean).join(' · ');return '<tr><td data-label="Ticket number"><b class="tk-number">'+esc(a.ticketNumber||a.ref)+'</b><div class="t-muted or-sub">'+esc(a.ref)+'</div></td><td data-label="Attendee"><b>'+esc(a.name)+'</b><div class="t-muted or-sub">'+esc(a.email)+'</div></td><td data-label="Ticket type">'+esc(a.tier)+'<div class="t-muted or-sub">'+esc(a.event)+'</div></td><td data-label="Seat">'+(seat?esc(seat):'<span class="t-muted">General admission</span>')+'</td><td data-label="Status">'+attStatus(a.status)+'</td><td class="ev-actions"><a class="t-btn t-btn--ghost t-btn--sm" href="'+esc(a.manageUrl)+'">Manage</a></td></tr>'}).join('')+'</tbody></table></div>';
  $('tk-more').hidden=!(r&&r.more);$('tk-msg').textContent=r&&r.more?'Showing '+TK.length+'. Load more, or export the full list.':TK.length+' assigned ticket'+(TK.length===1?'':'s');
}
['tk-cat-q','tk-cat-event'].forEach(function(id){$(id).addEventListener('input',paintTicketCategories)});
['tk-q','tk-event','tk-category','tk-status'].forEach(function(id){$(id).addEventListener('input',function(){if(id==='tk-event')drawTicketing();else{tkRequest++;tkLoading=false;tkCursor=null;TK=[];clearTimeout(tkTimer);tkTimer=setTimeout(function(){loadTickets(false)},160)}})});

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
  $('od-att').innerHTML='<div class="t-table-wrap"><table class="t-table t-table--cards or-table"><colgroup><col class="or-col-attendee"><col class="or-col-ticket"><col class="or-col-order"><col class="or-col-amount"><col class="or-col-status"><col class="or-col-actions"></colgroup><thead><tr><th scope="col">Attendee</th><th scope="col">Ticket</th><th scope="col">Order</th><th scope="col" class="t-num">Amount</th><th scope="col">Status</th><th scope="col"><span class="t-sr">Actions</span></th></tr></thead><tbody>'+rows.map(function(a,i){
    var menu=[];if(a.bookingId){menu.push('<a href="/organizer/event-booking/details/'+encodeURIComponent(a.bookingId)+'">Order details</a>');if(CAN.events||PERM('orders.manage'))menu.push('<button type="button" data-resend="'+i+'">Resend tickets</button>')}if(a.refundable&&CAN.refund)menu.push('<button type="button" class="is-danger" data-refund="'+i+'">Refund order</button>');
    return '<tr><td data-label=""><span class="ov-buyer"><span class="t-avatar" style="--av:32px" aria-hidden="true">'+esc((a.name||'?')[0].toUpperCase())+'</span><span><span class="or-name">'+esc(a.name)+'</span><span class="t-muted or-sub">'+esc(a.email)+'</span></span></span></td>'+
      '<td data-label="Ticket">'+esc(a.tier)+'<div class="t-muted or-sub">'+esc(a.event)+'</div></td>'+
      '<td data-label="Order">#'+esc(a.ref)+'</td>'+
      '<td data-label="Amount" class="t-num or-amount">'+(a.amount?Nc(a.amount,a.cur):'Free')+'</td>'+
      '<td data-label="Status">'+attStatus(a.status==='Paid'&&!a.orderTotal?'Free':a.status)+'</td>'+
      '<td class="ev-actions">'+(menu.length?'<details class="t-menu"><summary class="t-icon-btn" aria-label="Actions for '+esc(a.name)+'"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg></summary><div class="t-menu__list">'+menu.join('')+'</div></details>':'')+'</td></tr>'}).join('')+'</tbody></table></div>';
  $('od-att-more').hidden=!(r&&r.more);$('od-att-msg').textContent=r&&r.more?'Showing '+rows.length+'. Load more, or export the full list.':'';
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
  if(t.id==='tk-export'){location.href='/tica-api/organizer/attendees.csv?'+ticketQuery();toast('Ticket assignment list downloading.');return}
  if(t.id==='tk-more'){loadTickets(true);return}
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
var guestSeatRequest=0;
function tiersFor(){var r=evById($('od-gv').value),available=r?r.tiers.map(function(t,i){return {tier:t,index:i}}):[];$('od-gt').innerHTML=available.length?available.map(function(x){return '<option value="'+x.index+'">'+esc(x.tier.name)+(x.tier.seatingMode==='reserved'?' · Reserved':'')+'</option>'}).join(''):'<option value="">No ticket types</option>';loadGuestSeats()}
function loadGuestSeats(){var r=evById($('od-gv').value),t=r&&r.tiers[+$('od-gt').value],wrap=$('od-gs-wrap'),sel=$('od-gs'),request=++guestSeatRequest;if(!t||t.seatingMode!=='reserved'){wrap.hidden=true;sel.required=false;sel.innerHTML='<option value="">Choose an available seat</option>';return}wrap.hidden=false;sel.required=true;sel.innerHTML='<option value="">Loading available seats…</option>';fetch('/tica-api/events/'+r.id+'/seats',{credentials:'same-origin',headers:{Accept:'application/json'}}).then(function(res){return res.json().then(function(data){if(!res.ok)throw new Error(data.message||'Seats are unavailable.');return data})}).then(function(data){if(request!==guestSeatRequest)return;var seats=(data.seats||[]).filter(function(s){return String(s.ticket_id)===String(t.id)&&s.status==='available'});sel.innerHTML='<option value="">Choose an available seat</option>'+seats.map(function(s){return '<option value="'+s.id+'">'+esc([s.section,s.row?'Row '+s.row:null,'Seat '+s.label].filter(Boolean).join(' · '))+'</option>'}).join('');if(!seats.length)$('od-comp-m').textContent='No seats are currently available for this ticket type.'},function(er){if(request!==guestSeatRequest)return;sel.innerHTML='<option value="">Seats unavailable</option>';$('od-comp-m').textContent=er.message||'Seats are unavailable.'})}
$('od-gv').addEventListener('change',tiersFor);
$('od-gt').addEventListener('change',loadGuestSeats);
(function(){var c=document.querySelector('#od-msg-f .od-chk');if(c)c.innerHTML='<input type="checkbox" checked disabled> Also send as SMS <small>(SMS isn\'t set up on TICA yet, so this goes by email only)</small>'})();
$('od-msg-f').addEventListener('submit',function(e){e.preventDefault();var v=$('od-ma').value.split(':'),txt=$('od-mt').value.trim(),b=e.target.querySelector('[type=submit]');if(!v[0]){$('od-msg-m').textContent='You have no upcoming events to message.';return}if(txt.length<5){$('od-msg-m').textContent='Write your message first.';return}
  b.disabled=true;api('/tica-api/organizer/message',{event_id:+v[0],audience:v[1],message:txt}).then(function(r){b.disabled=false;$('od-msg').close();toast(r.message)},function(er){b.disabled=false;$('od-msg-m').textContent=errText(er)})});
$('od-comp-f').addEventListener('submit',function(e){e.preventDefault();var n=$('od-gn').value.trim(),m=$('od-ge').value.trim(),b=e.target.querySelector('[type=submit]');if(!n||m.indexOf('@')<1){$('od-comp-m').textContent='Add the guest\'s name and email.';return}
  var r=evById($('od-gv').value),t=r&&r.tiers[+$('od-gt').value],seat=t&&t.seatingMode==='reserved'?+$('od-gs').value:null;if(!t){$('od-comp-m').textContent='Choose an event and ticket.';return}if(t.seatingMode==='reserved'&&!seat){$('od-comp-m').textContent='Choose an available seat.';$('od-gs').focus();return}
  b.disabled=true;api('/tica-api/organizer/guests',{event_id:r.id,ticket_id:t.id,variation:t.variation,name:n,email:m,seat_id:seat}).then(function(){b.disabled=false;$('od-comp').close();$('od-comp-f').reset();$('od-comp-m').textContent='';loadAtt();toast('Free ticket sent to '+m+'.')},function(er){b.disabled=false;$('od-comp-m').textContent=errText(er);if(t.seatingMode==='reserved')loadGuestSeats()})});

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

/* promote: one page with tool cards, one campaigns list and side panels for every flow */
function prUrl(){var id=+$('pr-ev').value,e=evById(id);return location.origin+(e?'/event/'+e.slug:'/discover')}
function prEvent(){return +($('pr-ev')&&$('pr-ev').value)||0}
function drawShare(){var sel=$('pr-ev');if(!sel)return;var evs=D.events.filter(function(r){return r.state==='up'||r.state==='live'});if(!evs.length)evs=D.events;var keep=sel.value;
  sel.innerHTML=evs.map(function(r){return '<option value="'+r.id+'">'+esc(r.name)+'</option>'}).join('')||'<option value="">No events yet</option>';if(keep)sel.value=keep;
  var u=prUrl(),e=evById(+sel.value),txt=encodeURIComponent((e?e.name+' — get your tickets on TICA: ':'Find events on TICA: ')+u);$('pr-url').value=u;$('pr-copy').disabled=!evs.length;
  $('pr-social').innerHTML=[['WhatsApp','https://wa.me/?text='+txt],['X','https://x.com/intent/tweet?text='+txt],['Facebook','https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(u)],['LinkedIn','https://www.linkedin.com/sharing/share-offsite/?url='+encodeURIComponent(u)],['Email','mailto:?subject='+encodeURIComponent(e?e.name:'TICA')+'&body='+txt]].map(function(x){return '<a class="t-btn t-btn--secondary t-btn--sm" href="'+esc(x[1])+'" target="_blank" rel="noopener noreferrer">'+x[0]+'</a>'}).join('')}
document.addEventListener('change',function(e){if(e.target.id==='pr-ev'){drawShare();drawCampaigns()}});
document.addEventListener('click',function(e){if(e.target.id!=='pr-copy')return;var u=$('pr-url').value;try{navigator.clipboard.writeText(u).then(function(){toast('Link copied.')},function(){$('pr-url').select();toast('Press Ctrl+C to copy.')})}catch(er){$('pr-url').select()}});
var PR={code:null,link:null,payCampaign:null,filter:'all',menu:null,plan:null,fstep:1,created:null};
var PV_ICO={code:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12V4h8l9.5 9.5-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/></svg>',link:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/></svg>',feat:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z"/></svg>',dots:'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/></svg>'};
var PV_STATUS={pending_payment:['Waiting for payment','amber'],pending_review:['In review','amber'],change_requested:['Changes requested','red'],approved:['Scheduled','blue'],scheduled:['Scheduled','blue'],active:['Live','green'],paused:['Paused','grey'],completed:['Ended','grey'],rejected:['Not approved','red'],cancelled:['Cancelled','grey'],cancellation_requested:['Cancelling','amber'],refunded:['Refunded','grey']};
function prEvOpts(sel){return '<option value="">All events</option>'+D.events.map(function(r){return '<option value="'+r.id+'"'+(+sel===r.id?' selected':'')+'>'+esc(r.name)+'</option>'}).join('')}
function prLinkUrl(l){var ev2=l.eventId?evById(l.eventId):(D.next||D.events[0]);return location.origin+(ev2?'/event/'+ev2.slug:'/discover')+'?ref='+l.code}
function prCodeLabel(c){return c.type==='fixed'?N(c.value)+' off':(+c.value).toLocaleString('en-NG')+'% off'}
function prMoney(v,cur){return Nc(v,{code:cur,symbol:cur,position:'left'})}
function prAct(kind,id,paused){return (kind==='link'?'<button type="button" role="menuitem" data-copy="'+id+'">Copy link</button><button type="button" role="menuitem" data-prqr="'+id+'">Show QR code</button>':'')+'<button type="button" role="menuitem" data-predit="'+kind+':'+id+'">Edit</button><button type="button" role="menuitem" data-prpause="'+kind+':'+id+'" data-active="'+(paused?'0':'1')+'">'+(paused?'Resume':'Pause')+'</button><button type="button" role="menuitem" class="is-danger" data-prdel="'+kind+':'+id+'">Delete</button>'}
function prRows(){var ev=prEvent(),rows=[],match=function(id){return !ev||!id||+id===ev};
  (D.promotions||[]).forEach(function(c){if(!match(c.eventId))return;var st=PV_STATUS[c.status]||[c.status.replace(/_/g,' '),'grey'],dates=c.starts?c.starts+(c.ends?' – '+c.ends:''):'Dates pending';
    var result=c.status==='active'||c.status==='completed'?nf(c.clicks)+' clicks · '+nf(c.tickets)+' tickets':dates;
    var acts=(c.canPay?'<button type="button" role="menuitem" data-checkoutprom="'+c.id+'">Pay with Paystack</button><button type="button" role="menuitem" data-payprom="'+c.id+'">Use TICA balance</button>':'')+(c.canCancel?'<button type="button" role="menuitem" class="is-danger" data-cancelprom="'+c.id+'">Cancel</button>':'');
    rows.push({kind:'feat',key:'p'+c.id,name:c.plan||'Featured placement',sub:(ev?'':c.event+' · ')+prMoney(c.price,c.currency),type:'Featured placement',result:result,status:st[0],tone:st[1],note:c.status==='change_requested'||c.status==='rejected'?c.adminNote:'',acts:acts,cta:c.canPay?'<button class="t-btn t-btn--sm pv-pay" type="button" data-checkoutprom="'+c.id+'">Pay '+esc(prMoney(c.price,c.currency))+'</button>':''})});
  (D.codes||[]).forEach(function(c){if(!match(c.eventId))return;var status=c.status||(c.active?'Active':'Paused');
    rows.push({kind:'code',key:'c'+c.id,name:c.code,sub:prCodeLabel(c)+(c.ends?' · ends '+c.ends:''),type:'Promo code',result:nf(c.used)+(c.max?' / '+nf(c.max):'')+' uses · '+N(c.revenue)+' sales',status:status,tone:status==='Active'?'green':'grey',acts:prAct('code',c.id,c.paused)})});
  (D.links||[]).forEach(function(l){if(!match(l.eventId))return;
    rows.push({kind:'link',key:'l'+l.id,name:l.name,sub:[l.channel,l.influencer].filter(Boolean).join(' · ')||'?ref='+l.code,type:'Tracking link',result:nf(l.clicks)+' clicks · '+nf(l.sales)+' tickets',status:l.active?'Active':'Paused',tone:l.active?'green':'grey',acts:prAct('link',l.id,!l.active)})});
  return rows}
function drawCampaigns(){var box=$('od-campaigns');if(!box)return;var all=prRows(),rows=all.filter(function(r){return PR.filter==='all'||r.kind===PR.filter});
  [].forEach.call(document.querySelectorAll('[data-prfilter]'),function(b){b.setAttribute('aria-selected',String(b.dataset.prfilter===PR.filter))});
  if(!rows.length){box.innerHTML='<div class="pv-empty"><b>'+(all.length?'Nothing here yet':'No promotions yet')+'</b><span>'+(PR.filter==='code'?'Create a promo code to reward early buyers.':PR.filter==='link'?'Make a link for each place you post.':PR.filter==='feat'?'Get featured on the TICA home page or Discover.':'Pick a card above to start your first promotion.')+'</span></div>';return}
  box.innerHTML='<div class="pv-row pv-row--head" role="row"><span role="columnheader">Campaign</span><span role="columnheader">Type</span><span role="columnheader" class="pv-res">Results</span><span role="columnheader">Status</span><span role="columnheader"><span class="t-sr">Actions</span></span></div>'+rows.map(function(r,i){var open=PR.menu===r.key;
    return '<div class="pv-row'+(PR.fresh===r.key?' is-new':'')+'" role="row" style="--i:'+i+'"><span role="cell" class="pv-name"><i class="pv-ico pv-tone-'+r.kind+'">'+PV_ICO[r.kind]+'</i><span><b>'+esc(r.name)+'</b><small>'+esc(r.sub)+'</small>'+(r.note?'<small class="pv-row__note">'+esc(r.note)+'</small>':'')+'</span></span><span role="cell" class="pv-type">'+r.type+'</span><span role="cell" class="pv-res">'+esc(r.result)+'</span><span role="cell"><span class="pv-pill pv-pill--'+r.tone+'"><i></i>'+esc(r.status)+'</span></span><span role="cell" class="pv-act">'+(r.cta||'')+(r.acts?'<button class="pv-dots" type="button" data-prmenu="'+r.key+'" aria-haspopup="menu" aria-expanded="'+open+'" aria-label="Actions for '+esc(r.name)+'">'+PV_ICO.dots+'</button>'+(open?'<div class="pv-menu" role="menu">'+r.acts+'</div>':''):'')+'</span></div>'}).join('')}
function prCount(el,to,fmt){if(!el)return;var reduce=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;if(reduce||!to){el.textContent=fmt(to);return}var t0=performance.now();(function tick(n){var k=Math.min(1,(n-t0)/900),e=1-Math.pow(1-k,3);el.textContent=fmt(to*e);if(k<1)requestAnimationFrame(tick)})(t0)}
function drawPromote(){
  drawShare();
  var codes=D.codes||[],links=D.links||[],promos=D.promotions||[],ps=D.promotionStats||{},sum=function(a,k){return a.reduce(function(s,x){return s+(+x[k]||0)},0)};
  var tickets=sum(codes,'tickets')+sum(links,'sales')+(+ps.tickets||0),uses=sum(codes,'used'),revenue=sum(codes,'revenue')+sum(links,'revenue')+sum(promos,'revenue');
  $('od-promo-kpis').innerHTML=[['code','Ticket sales','pv-s1','From codes, links and placements'],['link','Code uses','pv-s2',codes.filter(function(c){return c.active}).length+' active '+(codes.filter(function(c){return c.active}).length===1?'code':'codes')],['feat','Revenue from promotions','pv-s3','Ticket sales they brought in']].map(function(x){return '<article class="pv-stat"><i class="pv-ico pv-tone-'+x[0]+'">'+PV_ICO[x[0]]+'</i><div><span>'+x[1]+'</span><b id="'+x[2]+'">0</b><small>'+esc(x[3])+'</small></div></article>'}).join('');
  prCount($('pv-s1'),tickets,function(v){return nf(Math.round(v))});prCount($('pv-s2'),uses,function(v){return nf(Math.round(v))});prCount($('pv-s3'),revenue,function(v){return N(Math.round(v))});
  var plans=D.promotionPlans||[];if(plans.length){var low=plans.reduce(function(a,p){return p.price<a.price?p:a},plans[0]);$('pv-feat-from').textContent='Get your event featured across TICA, from '+prMoney(low.price,low.currency)}
  drawCampaigns();
  $('od-pixels').innerHTML=(D.trackingPixels||[]).length?(D.trackingPixels||[]).map(function(p){return '<li class="pr-row pr-row--plain"><span class="pr-row__text"><b>'+esc(p.label)+'</b><small>'+esc(p.provider.toUpperCase())+' · '+esc(p.identifier)+'</small></span><span class="pr-pill pr-pill--grey">'+esc(p.status.replace(/_/g,' '))+'</span></li>'}).join(''):'';
}
function prOpenCode(c){PR.code=c||null;var f=$('od-code-f');f.reset();$('od-code-m').textContent='';
  $('pr-code-h').textContent=c?'Edit '+c.code:'Create a promo code';$('pr-code-p').textContent=c?(c.used?'Used '+c.used+' times, so the code itself can\'t be renamed.':'Change anything, then save.'):'Buyers type this at checkout.';
  $('od-code-event').innerHTML=prEvOpts(c?c.eventId:prEvent());
  if(c){$('od-cn').value=c.code;$('od-cn').disabled=c.used>0;$('od-ct').value=c.type||'percentage';$('od-cv').value=c.value;$('od-cl').value=c.max||'';$('od-code-min').value=c.minimum||'';$('od-code-end').value=c.endsIso||''}else{$('od-cn').disabled=false;$('od-cv').value=10}
  prTypeLabel();prPreview();$('od-code-go').textContent=c?'Save changes':'Create code';$('pr-code-dlg').showModal();if(!c)$('od-cn').focus()}
function prTypeLabel(){var f=$('od-ct').value==='fixed';$('od-cv-l').textContent=f?'Amount off ('+N(0).replace(/[\d.,\s]/g,'')+')':'Percentage';$('od-cv').max=f?100000000:100}
function prPreview(){var p=$('od-code-preview');if(!p)return;var c=($('od-cn').value||'').trim().toUpperCase().replace(/\s+/g,''),v=+$('od-cv').value||0,f=$('od-ct').value==='fixed';p.innerHTML=c&&v?'Buyers who enter <b>'+esc(c)+'</b> get <b>'+esc(f?N(v)+' off':v+'% off')+'</b>.':'Type a code and a discount to see what buyers get.'}
function prOpenLink(l){PR.link=l||null;var f=$('od-link-f');f.reset();$('od-link-m').textContent='';
  $('pr-link-h').textContent=l?'Edit tracking link':'Create a tracking link';$('pr-link-p').textContent=l?'The link address stays the same, so shared copies keep working.':'Share it in one place to see what it sells.';
  $('od-link-event').innerHTML=prEvOpts(l?l.eventId:prEvent());
  if(l){$('od-ln').value=l.name;$('od-link-channel').value=l.channel||'';$('od-link-influencer').value=l.influencer||''}
  prChips();$('od-link-go').textContent=l?'Save changes':'Create link';$('pr-link-dlg').showModal();$('od-ln').focus()}
function prChips(){var v=($('od-link-channel').value||'').toLowerCase();[].forEach.call(document.querySelectorAll('[data-chan]'),function(b){b.setAttribute('aria-pressed',String(b.dataset.chan.toLowerCase()===v))})}
function prQr(l){var url=prLinkUrl(l),q=qrcode(0,'M');q.addData(url);q.make();var n=q.getModuleCount(),cv=$('pr-qr-c'),px=Math.floor(320/(n+8)),sz=(n+8)*px,c=cv.getContext('2d');cv.width=sz;cv.height=sz;c.fillStyle='#fff';c.fillRect(0,0,sz,sz);c.fillStyle='#0B2447';for(var r=0;r<n;r++)for(var k=0;k<n;k++)if(q.isDark(r,k))c.fillRect((k+4)*px,(r+4)*px,px,px);
  $('pr-qr-h').textContent=l.name;$('pr-qr-p').textContent=url;$('pr-qr-dl').onclick=function(){var a=document.createElement('a');a.href=cv.toDataURL('image/png');a.download=(l.name||'tracking-link').replace(/[^a-z0-9]+/gi,'-').toLowerCase()+'-qr.png';document.body.appendChild(a);a.click();a.remove()};$('pr-qr-dlg').showModal()}
document.addEventListener('change',function(e){if(e.target.id==='od-ct'){prTypeLabel();prPreview()}});
document.addEventListener('input',function(e){if(e.target.id==='od-cn'||e.target.id==='od-cv')prPreview();if(e.target.id==='od-link-channel')prChips()});
/* featured placement: three steps, then Paystack */
function prPlans(){return D.promotionPlans||[]}
function prPlan(){return prPlans().filter(function(p){return String(p.id)===String(PR.plan)})[0]}
function prAddDays(iso,n){var d=new Date(iso+'T00:00:00');d.setDate(d.getDate()+n);return d}
function prDay(d){return d.toLocaleDateString('en-GB',{day:'numeric',month:'short'})}
function prRun(){var p=prPlan(),s=$('od-promo-start').value;if(!p||!s)return '';return prDay(new Date(s+'T00:00:00'))+' – '+prDay(prAddDays(s,p.days-1))}
function prFeatStep(n){PR.fstep=n;var labels=['','Where','Which event and when','Check and pay'];
  [].forEach.call(document.querySelectorAll('#od-promo-f [data-fstep]'),function(el){var on=+el.dataset.fstep===n;el.hidden=!on;if(on){el.classList.remove('pv-in');void el.offsetWidth;el.classList.add('pv-in')}});
  $('pr-feat-steps').hidden=n>3;$('pr-feat-step').textContent='Step '+n+' of 3 · '+(labels[n]||'');[].forEach.call(document.querySelectorAll('#pr-feat-steps i'),function(b,i){b.classList.toggle('on',i<n)});
  $('pr-feat-h').textContent=n>3?'Request sent':'Get featured on TICA';
  $('pr-f-back').hidden=n===1||n>3;$('pr-f-next').hidden=n>=3;$('pr-f-pay').hidden=n!==3;$('pr-f-balance').hidden=true;$('pr-f-done').hidden=n<4;$('od-promo-m').textContent='';
  if(n===2){$('od-package-summary').textContent=prPlan()?'Runs for '+prPlan().days+' days: '+prRun()+'.':''}
  if(n===3){var p=prPlan(),e=evById(+$('od-promo-event').value);
    $('pr-feat-card').innerHTML='<span>'+esc(p.name)+'</span><b>'+esc(e?e.name:'Your event')+'</b><small>'+esc(e?[e.date,e.city].filter(Boolean).join(' · '):'')+'</small>';
    $('pr-feat-sum').innerHTML='<div><dt>Placement</dt><dd>'+esc(p.name)+'</dd></div><div><dt>Dates</dt><dd>'+esc(prRun())+' · '+p.days+' days</dd></div><div class="pv-sum__total"><dt>Total</dt><dd>'+esc(prMoney(p.price,p.currency))+'</dd></div>';
    $('pr-feat-note').textContent=p.approvalRequired?'We check every placement before it goes live. If we can’t approve it, your payment is refunded.':'It starts on the date you chose once payment is confirmed.';
    $('pr-f-pay').textContent=p.price>0?'Pay '+prMoney(p.price,p.currency):'Send for review'}}
function prOpenFeatured(){var plans=prPlans(),f=$('od-promo-f');f.reset();PR.created=null;
  $('od-package-grid').innerHTML=plans.map(function(p,i){return '<button type="button" class="pv-plan" role="radio" aria-checked="false" data-plan="'+p.id+'"><span class="pv-radio" aria-hidden="true"></span><span class="pv-plan__txt"><b>'+esc(p.name)+'</b><small>'+esc(p.description||'Shown across TICA.')+'</small>'+(p.availableSlots===0?'<small class="pv-full">Fully booked right now</small>':'')+'</span><span class="pv-plan__price"><b>'+esc(prMoney(p.price,p.currency))+'</b><small>'+p.days+' days</small></span></button>'}).join('');
  $('od-promo-empty').hidden=plans.length>0;$('od-package-grid').hidden=!plans.length;
  var ups=D.events.filter(function(r){return r.state==='up'});
  $('od-promo-event').innerHTML=ups.map(function(r){return '<option value="'+r.id+'">'+esc(r.name)+'</option>'}).join('')||'<option value="">No published upcoming event</option>';
  if(ups.some(function(r){return r.id===prEvent()}))$('od-promo-event').value=String(prEvent());
  $('od-promo-start').value=new Date().toISOString().slice(0,10);$('od-promo-start').min=$('od-promo-start').value;
  PR.plan=plans.length?(plans.filter(function(p){return p.availableSlots!==0})[0]||plans[0]).id:null;prPickPlan(PR.plan);
  prFeatStep(1);$('pr-f-next').disabled=!plans.length;$('pr-feat-dlg').showModal()}
function prPickPlan(id){PR.plan=id;[].forEach.call(document.querySelectorAll('[data-plan]'),function(b){b.setAttribute('aria-checked',String(b.dataset.plan===String(id)))})}
document.addEventListener('change',function(e){if(e.target.id==='od-promo-start'&&PR.fstep===2)$('od-package-summary').textContent=prPlan()?'Runs for '+prPlan().days+' days: '+prRun()+'.':''});
$('pr-f-next').addEventListener('click',function(){var m=$('od-promo-m');
  if(PR.fstep===1){if(!prPlan()){m.textContent='Choose a placement.';return}prFeatStep(2);return}
  if(PR.fstep===2){if(!$('od-promo-event').value){m.textContent='Publish an upcoming event before promoting it.';return}if(!$('od-promo-start').value){m.textContent='Choose a start date.';$('od-promo-start').focus();return}prFeatStep(3)}});
$('pr-f-back').addEventListener('click',function(){prFeatStep(Math.max(1,PR.fstep-1))});
$('pr-f-done').addEventListener('click',function(){reload()});
$('pr-f-balance').addEventListener('click',function(){if(!PR.created)return;$('pr-feat-dlg').close();PR.payCampaign=PR.created;$('od-promo-pay-f').reset();$('od-promo-pay-m').textContent='';$('pr-pay-dlg').showModal();$('od-promo-password').focus()});
function promoCreative(file){
  var limit=3*1024*1024;if(!file||file.size<=limit)return Promise.resolve(file);
  if(file.type==='image/gif')return Promise.reject(new Error('GIF images must be 3 MB or smaller.'));
  if(['image/jpeg','image/png','image/webp'].indexOf(file.type)<0)return Promise.reject(new Error('Choose a JPG, PNG, WebP or GIF image.'));
  if(file.size>12*1024*1024)return Promise.reject(new Error('Choose an image smaller than 12 MB.'));
  return new Promise(function(resolve,reject){
    var img=new Image(),url=URL.createObjectURL(file);
    function done(){URL.revokeObjectURL(url)}
    img.onerror=function(){done();reject(new Error('This image could not be prepared. Choose another image.'))};
    img.onload=function(){
      done();var max=1800,scale=Math.min(1,max/Math.max(img.naturalWidth,img.naturalHeight)),canvas=document.createElement('canvas');
      canvas.width=Math.max(1,Math.round(img.naturalWidth*scale));canvas.height=Math.max(1,Math.round(img.naturalHeight*scale));
      var context=canvas.getContext('2d');if(!context){reject(new Error('This image could not be prepared. Choose another image.'));return}
      context.drawImage(img,0,0,canvas.width,canvas.height);
      canvas.toBlob(function(blob){
        if(!blob||blob.size>limit){reject(new Error('This image is still larger than 3 MB after optimization. Choose a smaller image.'));return}
        var name=(file.name||'campaign-image').replace(/\.[^.]+$/,'')+'.webp';resolve(new File([blob],name,{type:'image/webp',lastModified:Date.now()}));
      },'image/webp',.82);
    };
    img.src=url;
  });
}
function prGoPaystack(id,b){b.disabled=true;b.innerHTML='<span class="pv-spin" aria-hidden="true"></span>Opening Paystack…';return api('/tica-api/organizer/promotions/'+id+'/checkout').then(function(r){if(!r.authorization_url)throw new Error('Payment provider did not return a checkout link.');location.assign(r.authorization_url)})}
$('od-promo-f').addEventListener('submit',function(e){e.preventDefault();if(PR.fstep!==3)return;var b=$('pr-f-pay'),label=b.textContent,p=prPlan(),m=$('od-promo-m');if(!p)return;
  var fail=function(er){b.disabled=false;b.textContent=label;m.textContent=errText(er);if(PR.created&&p.price>0&&D.kpis&&(D.kpis.available||0)>=p.price)$('pr-f-balance').hidden=false};
  if(PR.created){prGoPaystack(PR.created,b).catch(fail);return}
  var creative=$('od-promo-creative').files&&$('od-promo-creative').files[0];b.disabled=true;b.innerHTML='<span class="pv-spin" aria-hidden="true"></span>'+(creative&&creative.size>3*1024*1024?'Optimizing image…':'Reserving your dates…');m.textContent='';
  promoCreative(creative).then(function(upload){var fd=new FormData();fd.append('plan_id',p.id);fd.append('event_id',$('od-promo-event').value);fd.append('starts_at',$('od-promo-start').value);fd.append('headline',$('od-promo-headline').value.trim());fd.append('body',$('od-promo-body').value.trim());fd.append('destination_url',$('od-promo-url').value.trim());if(upload)fd.append('creative',upload);
    return fetch('/tica-api/organizer/promotions',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest'},body:fd}).then(function(r){return r.json().catch(function(){return {}}).then(function(j){if(!r.ok){var er=new Error(j.message||('Could not create promotion (HTTP '+r.status+').'));er.data=j;throw er}return j})});
  }).then(function(r){PR.created=r.id||null;
    if((r.price||p.price)>0&&PR.created)return prGoPaystack(PR.created,b);
    $('pr-feat-done-p').textContent=p.name+', '+prRun()+'.';prFeatStep(4);
  }).catch(fail)});
$('od-promo-pay-f').addEventListener('submit',function(e){e.preventDefault();var b=e.target.querySelector('[type=submit]'),m=$('od-promo-pay-m'),password=$('od-promo-password').value;if(!password){m.textContent='Enter your current password.';return}b.disabled=true;m.textContent='';api('/tica-api/organizer/promotions/'+PR.payCampaign+'/pay',{current_password:password}).then(function(r){$('pr-pay-dlg').close();reload(r.message)},function(er){b.disabled=false;m.textContent=errText(er)})});
document.addEventListener('click',function(e){var t=e.target;
  var nw=t.closest('[data-prnew]');if(nw){var k=nw.dataset.prnew,open=document.querySelector('dialog.pr-drawer[open]');if(open)open.close();if(k==='code')prOpenCode();else if(k==='link')prOpenLink();else if(k==='featured')prOpenFeatured();else $('pr-choose-dlg').showModal();return}
  var pl=t.closest('[data-plan]');if(pl){prPickPlan(pl.dataset.plan);return}
  var ch=t.closest('[data-chan]');if(ch){$('od-link-channel').value=ch.dataset.chan;if(!$('od-ln').value.trim())$('od-ln').value=ch.dataset.chan;prChips();return}
  var fl=t.closest('[data-prfilter]');if(fl){PR.filter=fl.dataset.prfilter;PR.menu=null;drawCampaigns();return}
  var mb=t.closest('[data-prmenu]');if(mb){PR.menu=PR.menu===mb.dataset.prmenu?null:mb.dataset.prmenu;drawCampaigns();var fm=document.querySelector('.pv-menu [role=menuitem]');if(fm&&PR.menu)fm.focus();return}
  if(PR.menu&&!t.closest('.pv-menu')){PR.menu=null;drawCampaigns()}
  var ed=t.closest('[data-predit]');if(ed){var p=ed.dataset.predit.split(':'),id=+p[1];PR.menu=null;if(p[0]==='code')prOpenCode((D.codes||[]).filter(function(c){return c.id===id})[0]);else prOpenLink((D.links||[]).filter(function(l){return l.id===id})[0]);return}
  var qb=t.closest('[data-prqr]');if(qb){prQr((D.links||[]).filter(function(l){return l.id===+qb.dataset.prqr})[0]);return}
  var pb=t.closest('[data-prpause]');if(pb){var q=pb.dataset.prpause.split(':');pb.disabled=true;api('/tica-api/organizer/'+(q[0]==='code'?'codes':'links')+'/'+q[1],{is_active:pb.dataset.active!=='1'}).then(function(r){reload(r.message)},function(er){pb.disabled=false;toast(errText(er))});return}
  var db=t.closest('[data-prdel]');if(db){var d=db.dataset.prdel.split(':');if(!db.dataset.sure){db.dataset.sure='1';db.textContent='Click again to delete';setTimeout(function(){if(db.isConnected){delete db.dataset.sure;db.textContent='Delete'}},4000);return}
    db.disabled=true;api('/tica-api/organizer/'+(d[0]==='code'?'codes':'links')+'/'+d[1]+'/delete').then(function(r){reload(r.message)},function(er){db.disabled=false;db.textContent='Delete';delete db.dataset.sure;toast(errText(er))});return}
  var checkout=t.closest('[data-checkoutprom]'),pay=t.closest('[data-payprom]'),cancel=t.closest('[data-cancelprom]');
  if(checkout){prGoPaystack(checkout.dataset.checkoutprom,checkout).catch(function(er){checkout.disabled=false;checkout.textContent='Pay with Paystack';toast(errText(er))});return}
  if(pay){PR.menu=null;PR.payCampaign=pay.dataset.payprom;$('od-promo-pay-f').reset();$('od-promo-pay-m').textContent='';$('pr-pay-dlg').showModal();$('od-promo-password').focus();return}
  if(cancel){if(!cancel.dataset.sure){cancel.dataset.sure='1';cancel.textContent='Click again to cancel';setTimeout(function(){if(cancel.isConnected){delete cancel.dataset.sure;cancel.textContent='Cancel'}},4000);return}cancel.disabled=true;api('/tica-api/organizer/promotions/'+cancel.dataset.cancelprom+'/cancel').then(function(r){reload(r.message)},function(er){cancel.disabled=false;toast(errText(er))})}
});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&PR.menu){PR.menu=null;drawCampaigns()}});
$('od-code-f').addEventListener('submit',function(e){e.preventDefault();var c=$('od-cn').value.trim().toUpperCase().replace(/\s+/g,''),m=$('od-code-m'),go=$('od-code-go'),v=+$('od-cv').value,type=$('od-ct').value;
  if(!PR.code||!PR.code.used){if(!/^[A-Z0-9]{3,30}$/.test(c)){m.textContent='Codes need 3 to 30 letters or numbers.';$('od-cn').focus();return}}
  if(!(v>=1)||(type==='percentage'&&v>100)){m.textContent=type==='percentage'?'Enter a percentage from 1 to 100.':'Enter an amount of at least 1.';$('od-cv').focus();return}
  var body={type:type,value:v,max_uses:+$('od-cl').value||null,event_id:+$('od-code-event').value||null,minimum_purchase:+$('od-code-min').value||0,end_date:$('od-code-end').value||null};
  if(!PR.code||!PR.code.used)body.code=c;
  go.disabled=true;m.textContent='';
  api(PR.code?'/tica-api/organizer/codes/'+PR.code.id:'/tica-api/organizer/codes',body).then(function(r){reload(PR.code?r.message:c+' is live. Share it anywhere.')},function(er){go.disabled=false;m.textContent=errText(er)})});
$('od-link-f').addEventListener('submit',function(e){e.preventDefault();var n=$('od-ln').value.trim(),m=$('od-link-m'),go=$('od-link-go');if(n.length<2){m.textContent='Give the link a name of at least 2 letters.';$('od-ln').focus();return}
  go.disabled=true;m.textContent='';
  api(PR.link?'/tica-api/organizer/links/'+PR.link.id:'/tica-api/organizer/links',{name:n,channel:$('od-link-channel').value.trim(),influencer:$('od-link-influencer').value.trim(),event_id:+$('od-link-event').value||null}).then(function(r){reload(PR.link?r.message:'Tracking link created. Copy it from the ⋯ menu.')},function(er){go.disabled=false;m.textContent=errText(er)})});
$('od-pixel-f').addEventListener('submit',function(e){e.preventDefault();var b=e.target.querySelector('[type=submit]'),id=$('od-pixel-id').value.trim(),label=$('od-pixel-label').value.trim();if(!/^[A-Za-z0-9_-]{5,100}$/.test(id)||label.length<2){$('od-pixel-m').textContent='Add a valid provider ID and label.';return}b.disabled=true;api('/tica-api/organizer/tracking-pixels',{provider:$('od-pixel-provider').value,pixel_identifier:id,label:label}).then(function(r){reload(r.message)},function(er){b.disabled=false;$('od-pixel-m').textContent=errText(er)})});

/* team */
function drawRoles(){var box=$('od-roles'),R=D.roleSummaries||{};if(!box)return;box.innerHTML=[['manager','Manager'],['finance','Finance'],['gate','Gate staff']].map(function(r){var x=R[r[0]]||{can:[],cannot:[]};return '<article class="od-role-card"><h3>'+r[1]+'</h3><p class="od-role-label can">Can</p><ul class="od-can">'+x.can.map(function(l){return '<li>'+esc(l)+'</li>'}).join('')+'</ul>'+(x.cannot.length?'<p class="od-role-label cannot">Cannot</p><ul class="od-cannot">'+x.cannot.map(function(l){return '<li>'+esc(l)+'</li>'}).join('')+'</ul>':'')+'</article>'}).join('')}drawRoles();
function drawTeam(){var team=D.team||[],list=$('od-team'),count=$('od-team-count');if(count)count.textContent=team.length+' '+(team.length===1?'person':'people');if(!team.length){list.innerHTML='<li class="od-team-empty"><i aria-hidden="true">+</i><span><b>No team members yet</b><small>Invite someone below and choose exactly what they can access.</small></span></li>';return}list.innerHTML=team.map(function(m){var editable=m.id&&CAN.team&&(m.roleKey==='manager'||m.roleKey==='finance'),avatar=m.avatar?'<img data-team-avatar src="'+esc(m.avatar)+'" alt="">':'';return '<li><i class="od-team-avatar" aria-hidden="true"><span>'+esc((m.name||'?')[0])+'</span>'+avatar+'</i><span class="od-member"><b>'+esc(m.name)+'</b><small>'+esc(m.email)+'</small></span><span class="od-role">'+(editable?'<select class="od-rolesel" data-rolefor="'+m.id+'" data-current="'+esc(m.roleKey)+'" aria-label="Role for '+esc(m.name)+'"><option value="manager"'+(m.roleKey==='manager'?' selected':'')+'>Manager</option><option value="finance"'+(m.roleKey==='finance'?' selected':'')+'>Finance</option></select>':'<b>'+esc(m.role)+'</b>')+'<small>'+esc(m.scope)+'</small></span>'+(m.id&&CAN.team?'<button class="od-lk red" type="button" data-unteam="'+m.id+'">Remove</button>':'<span class="od-role-fixed">Fixed role</span>')+'</li>'}).join('');[].forEach.call(list.querySelectorAll('[data-team-avatar]'),function(img){img.addEventListener('error',function(){this.remove()})})}
document.addEventListener('change',function(e){var s=e.target.closest('[data-rolefor]');if(!s)return;var previous=s.dataset.current||s.value;s.disabled=true;api('/tica-api/organizer/team/'+s.dataset.rolefor+'/role',{role:s.value}).then(function(){reload('Role updated. It applies from their next action.')},function(er){s.value=previous;s.disabled=false;toast(errText(er))})});
document.addEventListener('click',function(e){var b=e.target.closest('[data-unteam]');if(!b)return;if(!b.dataset.sure){b.dataset.sure='1';b.textContent='Remove access?';return}api('/tica-api/organizer/team/'+b.dataset.unteam+'/remove').then(function(){reload('Access removed.')},function(er){toast(errText(er))})});
$('od-ir').innerHTML='<option value="gate">Gate staff</option><option value="manager">Manager</option><option value="finance">Finance</option>';
$('od-inv').addEventListener('submit',function(e){e.preventDefault();var m=$('od-ie').value.trim(),b=e.target.querySelector('[type=submit]');if(m.indexOf('@')<1||m.indexOf('.')<0){$('od-inv-m').textContent='Enter a valid email address.';return}
  b.disabled=true;b.textContent='Sending invite…';$('od-inv-m').textContent='';api('/tica-api/organizer/team',{email:m,role:$('od-ir').value}).then(function(){reload('Invite sent to '+m+'.')},function(er){b.disabled=false;b.textContent='Send invite';$('od-inv-m').textContent=errText(er)})});

/* settings */
function drawSettings(){
  var p=D.profile;$('od-pn').value=p.name;$('od-pa').value=p.about;$('od-pe').value=p.email||'';$('od-pu').value=p.username||'';
  $('od-paddr').value=p.address||'';$('od-pcity').value=p.city||'';$('od-pstate').value=p.state||'';$('od-pcountry').value=p.country||'';$('od-pzip').value=p.zipCode||'';
  $('od-pi').value=p.instagram||'';$('od-pw').value=p.phone;
  $('od-pt').value=p.tiktok||'';$('od-pweb').value=p.website||'';$('od-pfb').value=p.facebook||'';$('od-px').value=p.twitter||'';$('od-pli').value=p.linkedin||'';$('od-pyt').value=p.youtube||'';
  $('od-photo-ini').textContent=D.org.initials;$('od-pcp-row').hidden=true;var img=$('od-photo-preview');img.onerror=function(){img.hidden=true};if(p.photoUrl){img.src=p.photoUrl;img.hidden=false}else{img.removeAttribute('src');img.hidden=true}

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

/* create page uses the organiser's saved defaults */
function ceDefaults(){var pr=D.prefs,f=document.getElementById('create-form');if(!f)return;var me=f.querySelector('[name=c-payer][value=me]'),bu=f.querySelector('[name=c-payer][value=buyer]');if(me&&bu){me.checked=!pr.buyers_pay_fee;bu.checked=!!pr.buyers_pay_fee}var l=$('c-left');if(l)l.checked=!!pr.show_remaining;var w=$('c-wait');if(w)w.checked=!!pr.waitlist;var t=$('c-trans');if(t)t.checked=!!pr.transfers}ceDefaults();
/* Create event: offer the organiser's saved registration forms. */
function ceForms(){var sel=$('c-reg-form'),box=$('ce-reg-pick');if(!sel||!box)return;var L=(D&&D.registrationForms)||[],keep=sel.value;
  var empty=$('ce-reg-empty');if(empty)empty.hidden=!!L.length;
  if(!L.length){box.hidden=true;sel.innerHTML='<option value="">Select a saved form</option>';return}
  sel.innerHTML='<option value="">Select a saved form</option>'+L.map(function(x){return '<option value="'+esc(x.id)+'">'+esc(x.name)+' · '+x.fields+' field'+(x.fields===1?'':'s')+'</option>'}).join('');
  var editForm=TICA_DATA.builderEdit&&TICA_DATA.builderEdit.event&&TICA_DATA.builderEdit.event.registration_form_id;
  sel.value=keep||editForm||'';box.hidden=false;ceFormPicked()}
function ceFormPicked(){var sel=$('c-reg-form'),note=$('c-reg-form-note');if(!sel||!note)return;note.textContent=sel.value?'This form is attached in addition to every ticket type above.':'Choose the form attendees complete with their ticket.'}
document.addEventListener('change',function(e){if(e.target.id==='c-reg-form')ceFormPicked()});
ceForms();
/* Defaults changed in Settings apply to the next new event without a reload, as long as nothing has been typed yet. */
window.addEventListener('tica:navigate',function(){var n=$('c-name');if(location.pathname==='/create'&&n&&!n.value.trim())ceDefaults()});
/* platform rules from the admin: hide features that are switched off */
(function(){var P=(TICA_DATA.currency||{}).platform,f=document.getElementById('create-form');if(!P||!f)return;
  function drop(el){if(!el)return;var l=el.closest('label,li')||el;l.hidden=true;l.style.display='none';if(el.checked){el.checked=false}}
  if(!P.online)drop(f.querySelector('[name=c-mode][value=Online]'));if(!P.hybrid)drop(f.querySelector('[name=c-mode][value=Hybrid]'));
  if(!P.registration){drop(document.getElementById('c-use-registration'));drop(f.querySelector('[name=c-entry][value=corporate]'))}
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
var CD=TICA_DATA.currency||{},OPTS=CD.options||[],CUR=CD.symbol||'₦',MAX_TYPES=Math.max(1,+TICA_DATA.ticketTypeLimit||50);
var ADMIN=TICA_DATA.adminBuilder||null,EDIT=TICA_DATA.builderEdit||null;
var requestedType=(new URLSearchParams(location.search).get('type')||'').toLowerCase();
if(requestedType==='online'||requestedType==='venue'){
  var requestedMode=f.querySelector('[name=c-mode][value="'+(requestedType==='online'?'Online':'In person')+'"]');
  if(requestedMode)requestedMode.checked=true;
}
if(ADMIN){
  var adminPanel=$('ce-admin'),owner=$('c-owner'),assignOwner=$('c-assign-owner'),ownerPicker=$('c-owner-picker');adminPanel.hidden=false;document.body.classList.add('admin-builder');
  function ownerOptions(){var selected=owner.value,list=(ADMIN.organizers||[]).slice().sort(function(a,b){return String(a.name||'').localeCompare(String(b.name||''))});owner.innerHTML='<option value="">Choose an organizer</option>'+list.map(function(o){return '<option value="'+o.id+'">'+esc(o.name)+(o.email?' · '+esc(o.email):'')+'</option>'}).join('');if([].some.call(owner.options,function(o){return o.value===selected}))owner.value=selected;assignOwner.disabled=!list.length;if(!list.length)owner.options[0].text='No organizers available'}
  function syncOwnerAssignment(clearValue){var assigned=assignOwner.checked;ownerPicker.hidden=!assigned;assignOwner.setAttribute('aria-expanded',String(assigned));owner.disabled=!assigned;if(!assigned&&clearValue!==false)owner.value=''}
  ownerOptions();
  assignOwner.addEventListener('change',function(){syncOwnerAssignment(true)});syncOwnerAssignment(false);
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
  [].forEach.call(f.querySelectorAll('label[for^="c-price"],label[for^="c-teb"]:not([for^="c-tebe"])'),function(l){l.textContent=(/teb/.test(l.htmlFor)?'Early bird price':'Price')+' ('+CUR+')'});
  var n=$('c-cur-note'),paid=o.paid;n.textContent=paid?'Buyers pay in '+o.code+'. It\'s set from the country, and you can change it.':o.code+' can\'t take card payments yet, so tickets must be free. Choose another currency to sell paid tickets.';
  n.className='hint'+(paid?'':' ce-warn');}
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function mode(){return (f.querySelector('[name=c-mode]:checked')||{}).value||'In person'}
function payer(){return (f.querySelector('[name=c-payer]:checked')||{}).value||'buyer'}
function entry(){return (f.querySelector('[name=c-entry]:checked')||{}).value||'ticketing'}

/* categories from Tica */
(function(){var sel=$('c-cat'),cats=TICA_DATA.categories||[];if(!cats.length)return;sel.innerHTML=cats.map(function(c){return '<option value="'+c.id+'">'+esc(c.name)+'</option>'}).join('')})();

/* optional people and agenda: one flexible programme for conferences and shows */
var programmeNo=0,agendaNo=0;
function programmeType(){return (f.querySelector('[name=c-programme-type]:checked')||{}).value||'speaker'}
function personInitials(name){return String(name||'Guest').trim().split(/\s+/).slice(0,2).map(function(x){return x.charAt(0)}).join('').toUpperCase()||'G'}
function programmeCopy(){var artists=programmeType()==='artist',title=$('ce-people-title'),add=$('ce-add-person');if(title)title.textContent=artists?'Featured line-up':'Featured speakers';if(add)add.textContent=artists?'+ Add artist':'+ Add speaker';[].forEach.call($('ce-people').querySelectorAll('.ce-person'),function(row){var role=row.querySelector('.ce-person-role-label'),topic=row.querySelector('.ce-person-topic-label');if(role)role.textContent=artists?'Role or billing':'Role or organisation';if(topic)topic.textContent=artists?'Set or performance':'Talk or topic'})}
function refreshProgrammePeople(){var rows=[].slice.call($('ce-people').querySelectorAll('.ce-person'));[].forEach.call($('ce-agenda-editor').querySelectorAll('.ce-agenda-person'),function(sel){var keep=sel.value;sel.innerHTML='<option value="">No linked person</option>'+rows.map(function(row,i){var name=row.querySelector('.ce-person-name').value.trim()||('Person '+(i+1));return '<option value="'+esc(row.dataset.key)+'">'+esc(name)+'</option>'}).join('');if([].some.call(sel.options,function(o){return o.value===keep}))sel.value=keep});programmeCopy()}
function personRow(data){data=data||{};programmeNo++;var key='programme-person-'+programmeNo,row=document.createElement('article'),photo=data.image?'<img src="'+esc(data.image)+'" alt="">':'<span>'+esc(personInitials(data.name))+'</span>';
  row.className='ce-person';row.dataset.key=key;row.dataset.pid=data.id||'';row.innerHTML='<button class="ce-person-remove" type="button" aria-label="Remove '+esc(data.name||'this person')+'">×</button><div class="ce-person-media"><div class="ce-person-photo" aria-hidden="true">'+photo+'</div><label class="ce-photo-pick" for="ce-person-image-'+programmeNo+'">Add portrait<input id="ce-person-image-'+programmeNo+'" class="ce-person-image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label><button class="ce-clear-photo" type="button"'+(data.image?'':' hidden')+'>Remove photo</button></div><div class="ce-person-fields"><div class="two"><div class="inp"><label for="ce-person-name-'+programmeNo+'">Name</label><input id="ce-person-name-'+programmeNo+'" class="ce-person-name" maxlength="120" value="'+esc(data.name||'')+'" placeholder="Full name"></div><div class="inp"><label class="ce-person-role-label" for="ce-person-role-'+programmeNo+'">Role or organisation</label><input id="ce-person-role-'+programmeNo+'" class="ce-person-role" maxlength="160" value="'+esc(data.role||'')+'" placeholder="Founder, host or headline artist"></div></div><div class="inp"><label class="ce-person-topic-label" for="ce-person-topic-'+programmeNo+'">Talk or topic</label><input id="ce-person-topic-'+programmeNo+'" class="ce-person-topic" maxlength="240" value="'+esc(data.topic||'')+'" placeholder="What they are speaking or performing about"></div><div class="inp"><label for="ce-person-bio-'+programmeNo+'">Short introduction</label><textarea id="ce-person-bio-'+programmeNo+'" class="ce-person-bio" rows="2" maxlength="1200" placeholder="A short bio for the event page">'+esc(data.bio||'')+'</textarea></div></div>';
  $('ce-people').appendChild(row);programmeCopy();refreshProgrammePeople();return row}
function agendaRow(data){data=data||{};agendaNo++;var row=document.createElement('article');row.className='ce-agenda-row';row.innerHTML='<button class="ce-agenda-remove" type="button" aria-label="Remove this agenda item">×</button><div class="two"><div class="inp"><label for="ce-agenda-title-'+agendaNo+'">Session or performance</label><input id="ce-agenda-title-'+agendaNo+'" class="ce-agenda-title" maxlength="180" value="'+esc(data.title||'')+'" placeholder="Keynote, panel, live set or break"></div><div class="inp"><label for="ce-agenda-person-'+agendaNo+'">Speaker or artist</label><select id="ce-agenda-person-'+agendaNo+'" class="ce-agenda-person"><option value="">No linked person</option></select></div></div><div class="three"><div class="inp"><label for="ce-agenda-start-'+agendaNo+'">Starts</label><input id="ce-agenda-start-'+agendaNo+'" class="ce-agenda-start" type="datetime-local" value="'+esc(data.starts_at||'')+'"></div><div class="inp"><label for="ce-agenda-end-'+agendaNo+'">Ends</label><input id="ce-agenda-end-'+agendaNo+'" class="ce-agenda-end" type="datetime-local" value="'+esc(data.ends_at||'')+'"></div><div class="inp"><label for="ce-agenda-place-'+agendaNo+'">Room or stage</label><input id="ce-agenda-place-'+agendaNo+'" class="ce-agenda-location" maxlength="160" value="'+esc(data.location||'')+'" placeholder="Main hall"></div></div><div class="inp"><label for="ce-agenda-desc-'+agendaNo+'">What happens</label><textarea id="ce-agenda-desc-'+agendaNo+'" class="ce-agenda-description" rows="2" maxlength="1200" placeholder="A short description for guests">'+esc(data.description||'')+'</textarea></div>';
  $('ce-agenda-editor').appendChild(row);refreshProgrammePeople();if(data.person_index!=null){var people=$('ce-people').querySelectorAll('.ce-person');if(people[data.person_index])row.querySelector('.ce-agenda-person').value=people[data.person_index].dataset.key}return row}
$('c-programme').addEventListener('change',function(){var on=this.checked;this.setAttribute('aria-expanded',String(on));$('ce-programme-fields').hidden=!on;if(on&&!$('ce-people').children.length)personRow();if(on)setTimeout(function(){var first=$('ce-people').querySelector('.ce-person-name');if(first)first.focus()},0)});
[].forEach.call(f.querySelectorAll('[name=c-programme-type]'),function(r){r.addEventListener('change',programmeCopy)});
$('ce-add-person').addEventListener('click',function(){personRow().querySelector('.ce-person-name').focus()});
$('ce-add-agenda').addEventListener('click',function(){var row=agendaRow({starts_at:$('c-date').value&&$('c-time').value?$('c-date').value+'T'+$('c-time').value:''});row.querySelector('.ce-agenda-title').focus()});
$('ce-people').addEventListener('input',function(e){if(e.target.classList.contains('ce-person-name')){var row=e.target.closest('.ce-person'),ph=row.querySelector('.ce-person-photo');if(ph&&!ph.querySelector('img'))ph.innerHTML='<span>'+esc(personInitials(e.target.value))+'</span>';refreshProgrammePeople()}});
$('ce-people').addEventListener('change',function(e){if(!e.target.classList.contains('ce-person-image')||!e.target.files[0])return;var row=e.target.closest('.ce-person'),file=e.target.files[0];if(!/^image\/(jpeg|png|webp|gif)$/.test(file.type)||file.size>25*1024*1024){e.target.value='';setCreateMessage(file.size>25*1024*1024?'Use a portrait smaller than 25 MB.':'Use a JPG, PNG, WebP or GIF portrait.','error');return}if(file.size>1024*1024)setCreateMessage('Large portrait selected. TICA will optimize it before upload.','working');var reader=new FileReader();reader.onload=function(){row.querySelector('.ce-person-photo').innerHTML='<img src="'+esc(reader.result)+'" alt="">';row.querySelector('.ce-clear-photo').hidden=false;row.dataset.removeImage='0'};reader.readAsDataURL(file)});
$('ce-people').addEventListener('click',function(e){var row=e.target.closest('.ce-person');if(!row)return;if(e.target.closest('.ce-person-remove')){row.remove();refreshProgrammePeople();return}if(e.target.closest('.ce-clear-photo')){row.querySelector('.ce-person-image').value='';row.querySelector('.ce-person-photo').innerHTML='<span>'+esc(personInitials(row.querySelector('.ce-person-name').value))+'</span>';row.dataset.removeImage='1';e.target.hidden=true}});
$('ce-agenda-editor').addEventListener('click',function(e){var b=e.target.closest('.ce-agenda-remove');if(b)b.closest('.ce-agenda-row').remove()});


/* each ticket type gets its own advanced options under the row */
function tierOptions(n){
  var k=n>1?n:'';
  return '<div class="ce-x">'+
  '<section class="ce-ticket-option ce-early"><label class="ce-choice-row"><input class="ce-early-on" type="checkbox" aria-expanded="false"><span><b>Offer an early-bird price</b><small>Set a lower price that ends automatically on a date you choose.</small></span></label><div class="two ce-option-fields ce-early-fields" hidden><div class="inp"><label for="c-teb'+k+'">Early-bird price ('+esc(CUR)+')</label><input id="c-teb'+k+'" class="ce-teb" type="number" min="0" step="0.01" placeholder="e.g. 4000" aria-describedby="c-teb'+k+'-error"><small id="c-teb'+k+'-error" class="ce-field-error" aria-live="polite" hidden></small></div><div class="inp"><label for="c-tebe'+k+'">Early bird ends</label><input id="c-tebe'+k+'" class="ce-tebe" type="datetime-local"></div></div></section>'+
  '<section class="ce-ticket-option ce-seating"><label class="ce-choice-row"><input class="ce-seat-on" type="checkbox" aria-expanded="false"><span><b>Use assigned seat numbers</b><small>Organize buyers by numbered seat. Every seat uses this ticket price.</small></span></label><input class="ce-seat-mode" type="hidden" value="general_admission"><div class="ce-seat-editor" hidden><div class="ce-seat-legend"><span><i class="available"></i>Available</span><span><i class="accessible"></i>Accessible</span><span><i class="unavailable"></i>Unavailable</span></div><div class="ce-seat-sections"></div><button class="ce-add ce-add-section" type="button">+ Add seating section</button><p class="hint">Click a seat to mark it unavailable; click again for accessible seating.</p></div></section>'+
  '<ul class="od-sw ce-more-ticket"><li><span><b>More ticket settings</b><small>Sales window, inventory, numbering and ticket appearance</small></span><input class="ck-sw ce-xsw" type="checkbox" aria-label="More settings for this ticket" aria-expanded="false"></li></ul>'+
  '<div class="ce-xo" hidden><div class="inp"><label for="c-tdesc'+k+'">What\'s included</label><input id="c-tdesc'+k+'" class="ce-tdesc" type="text" maxlength="300" placeholder="Fast-track entry and the VIP area"></div>'+
  '<div class="three"><div class="inp"><label for="c-tmin'+k+'">Minimum per order</label><input id="c-tmin'+k+'" class="ce-tmin" type="number" min="1" step="1" value="1"></div><div class="inp"><label for="c-tmax'+k+'">Maximum per order</label><input id="c-tmax'+k+'" class="ce-tmax" type="number" min="1" step="1" placeholder="No limit"></div><div class="inp"><label for="c-tunl'+k+'">Inventory</label><select id="c-tunl'+k+'" class="ce-tunl"><option value="limited">Limited, as above</option><option value="unlimited">Unlimited</option></select></div></div>'+
  '<div class="two"><div class="inp"><label for="c-tsales'+k+'">Sales start</label><input id="c-tsales'+k+'" class="ce-tsales" type="datetime-local"></div><div class="inp"><label for="c-tends'+k+'">Sales end</label><input id="c-tends'+k+'" class="ce-tends" type="datetime-local"></div></div>'+
  '<div class="two"><div class="inp"><label for="c-tvis'+k+'">Visibility</label><select id="c-tvis'+k+'" class="ce-tvis"><option value="public">Shown to buyers</option><option value="hidden">Hidden, direct allocation only</option></select></div><div class="inp"><label for="c-tstatus'+k+'">Sales status</label><select id="c-tstatus'+k+'" class="ce-tstatus"><option value="on_sale">On sale</option><option value="paused">Paused</option></select></div></div>'+
  '<section class="ce-number"><div class="ce-subhead"><div><b>Automatic ticket numbers</b><small>Each paid or complimentary ticket gets a permanent number.</small></div><input class="ck-sw ce-num-on" type="checkbox" checked aria-label="Automatic ticket numbering"></div><div class="three ce-num-fields"><div class="inp"><label for="c-prefix'+k+'">Prefix</label><input id="c-prefix'+k+'" class="ce-prefix" maxlength="20" placeholder="REG"></div><div class="inp"><label for="c-num-start'+k+'">Starts at</label><input id="c-num-start'+k+'" class="ce-num-start" type="number" min="1" value="1"></div><div class="inp"><label for="c-padding'+k+'">Digits</label><select id="c-padding'+k+'" class="ce-padding"><option value="3">001</option><option value="4">0001</option><option value="5">00001</option></select></div></div><div class="two ce-num-fields"><div class="inp"><label for="c-num-format'+k+'">Format</label><input id="c-num-format'+k+'" class="ce-num-format" value="{PREFIX}-{NUMBER}" maxlength="80"><small>Use {PREFIX} and {NUMBER}. Example: REG-001.</small></div><div class="inp"><label for="c-num-scope'+k+'">Sequence</label><select id="c-num-scope'+k+'" class="ce-num-scope"><option value="category">Start separately for this ticket type</option><option value="event">Share one sequence across the event</option></select><small>Event-wide numbering continues across every ticket type.</small></div></div></section>'+
  '<section class="ce-design"><b>Ticket appearance</b><div class="two"><div class="inp"><label for="c-design-color'+k+'">Accent colour</label><input id="c-design-color'+k+'" class="ce-design-color" type="color" value="#245fd3"></div><div class="ce-design-fields"><label class="ce-check"><input class="ce-design-logo" type="checkbox" checked> Event logo</label><label class="ce-check"><input class="ce-design-attendee" type="checkbox" checked> Attendee name</label><label class="ce-check"><input class="ce-design-category" type="checkbox" checked> Ticket category</label><label class="ce-check"><input class="ce-design-number" type="checkbox" checked> Ticket number</label><label class="ce-check"><input class="ce-design-section" type="checkbox" checked> Section</label><label class="ce-check"><input class="ce-design-row" type="checkbox" checked> Row</label><label class="ce-check"><input class="ce-design-seat" type="checkbox" checked> Seat number</label><label class="ce-check"><input class="ce-design-date" type="checkbox" checked> Event date</label><label class="ce-check"><input class="ce-design-venue" type="checkbox" checked> Event venue</label><label class="ce-check"><input class="ce-design-status" type="checkbox" checked> Ticket status</label></div></div><p class="hint">Event name, secure ticket ID and QR verification always stay on the ticket.</p></section></div></div>';
}
f.querySelector('.ce-tier').insertAdjacentHTML('beforeend',tierOptions(1));
function validateEarlyBird(tier){
  var fields=tier.querySelectorAll(':scope>.inp input'),toggle=tier.querySelector('.ce-early-on'),early=tier.querySelector('.ce-teb'),error=tier.querySelector('.ce-field-error');
  if(!early||!error)return '';
  early.removeAttribute('aria-invalid');error.hidden=true;error.textContent='';early.removeAttribute('max');
  if(!toggle||!toggle.checked||early.value.trim()==='')return '';
  var ticketPrice=Number(fields[1]&&fields[1].value),earlyPrice=Number(early.value),message='';
  if(!(ticketPrice>0))message='Set a paid ticket price before adding an early-bird price.';
  else{early.max=String(Math.max(0,ticketPrice-.01));if(earlyPrice<0||earlyPrice>=ticketPrice)message='Enter an early-bird price below '+N(ticketPrice)+'.'}
  if(message){early.setAttribute('aria-invalid','true');error.textContent=message;error.hidden=false}
  return message;
}
$('ce-tiers').addEventListener('input',function(e){var tier=e.target.closest('.ce-tier');if(tier&&(e.target.classList.contains('ce-teb')||e.target===tier.querySelectorAll(':scope>.inp input')[1]))validateEarlyBird(tier)});
$('ce-tiers').addEventListener('change',function(e){var sw=e.target.closest('.ce-xsw');if(sw){sw.closest('.ce-x').querySelector('.ce-xo').hidden=!sw.checked;sw.setAttribute('aria-expanded',String(sw.checked))}var early=e.target.closest('.ce-early-on');if(early){early.closest('.ce-ticket-option').querySelector('.ce-early-fields').hidden=!early.checked;early.setAttribute('aria-expanded',String(early.checked));validateEarlyBird(early.closest('.ce-tier'))}var seats=e.target.closest('.ce-seat-on');if(seats){var tier=seats.closest('.ce-tier'),modeInput=tier.querySelector('.ce-seat-mode'),editor=tier.querySelector('.ce-seat-editor');modeInput.value=seats.checked?'reserved':'general_admission';editor.hidden=!seats.checked;seats.setAttribute('aria-expanded',String(seats.checked));if(seats.checked&&!tier.querySelector('.ce-seat-section'))seatSection(tier,'Main floor',3,10)}});

function upd(){
  setCur();
  var useRegistration=$('c-use-registration'),m=mode(),p=Math.max(0,parseFloat($('c-price').value)||0),fee=Math.round(p*window.TICA_FEE_PCT)/100,reg=!!(useRegistration&&useRegistration.checked);
  if(useRegistration)useRegistration.setAttribute('aria-expanded',String(reg));
  f.querySelector('.ce-in').hidden=m==='Online';f.querySelector('.ce-on').hidden=m==='In person';
  f.querySelector('.ce-sell').hidden=false;f.querySelector('.ce-reg').hidden=!reg;
  var tiers=f.querySelectorAll('#ce-tiers .ce-tier');$('ce-add').hidden=(m==='Online'&&tiers.length>=1)||tiers.length>=MAX_TYPES;
  $('ce-ex-b').textContent=p?'Buyer pays '+N(p+fee)+'. You get '+N(p)+'.':'Free tickets have no fee.';
  $('ce-ex-m').textContent=p?'Buyer pays '+N(p)+'. You get '+N(p-fee)+'.':'Free tickets have no fee.';
  var priced=$('c-price').value.trim()!=='',pf=$('p-fee');pf.hidden=!priced;
  pf.textContent=!priced?'':!p?'Free, no booking fee':payer()==='buyer'?'+ '+N(fee)+' booking fee at checkout':'Booking fee included';
  var tag=$('p-mode');tag.hidden=m==='In person';tag.textContent=m==='Hybrid'?'In person and online':'Online';
  if(m==='Online'){var t=$('c-time').value;$('p-meta').textContent=[t,'Online on '+$('c-plat').value].filter(Boolean).join(', ')}
  f.querySelector('.ce-qx').hidden=!$('c-askq').checked;
  var pr=$('p-price');pr.hidden=!priced;pr.textContent=!priced?'':p>0?N(p):'Free';
  var b=TICA_DATA.organizer&&TICA_DATA.organizer.payout,bx=$('ce-bank');
  if(b&&b.verified){bx.classList.add('ok');bx.innerHTML='<span><b>Payout account</b><small>'+esc(b.name)+', '+esc(b.bank)+' •••• '+esc(String(b.num).slice(-4))+'. Verified</small></span><a class="btn btn-line" href="/dashboard/payouts">Change</a>'}
}
f.addEventListener('input',upd);f.addEventListener('change',upd);

var n=1;
$('ce-add').addEventListener('click',function(){
  if(f.querySelectorAll('#ce-tiers .ce-tier').length>=MAX_TYPES){$('c-msg').textContent='This event has reached the platform limit of '+MAX_TYPES+' ticket types.';return}
  n++;var d=document.createElement('div');d.className='ce-tier ce-ticket-row';
  d.innerHTML='<div class="inp"><label for="c-tier'+n+'">Ticket name</label><input id="c-tier'+n+'" type="text" placeholder="VIP" list="ce-ticket-presets"></div><div class="inp"><label for="c-price'+n+'">Price ('+esc(CUR)+')</label><input id="c-price'+n+'" type="number" min="0" step="500" value="15000"><small>Leave at 0 for a free event.</small></div><div class="inp"><label for="c-qty'+n+'">Quantity</label><input id="c-qty'+n+'" type="number" min="1" value="50"><small>Leave empty for unlimited tickets.</small></div><button class="ce-rm" type="button" aria-label="Remove this ticket type">×</button>'+tierOptions(n);
  $('ce-tiers').appendChild(d);d.querySelector('input').focus();upd();
});
$('ce-tiers').addEventListener('click',function(e){var r=e.target.closest('.ce-rm');if(r&&r.parentElement&&r.parentElement.classList.contains('ce-tier')){r.closest('.ce-tier').remove();upd()}});

var seatSectionUid=0,seatRowUid=0;
function seatSection(tier,name,rows,count){
  var box=tier.querySelector('.ce-seat-sections'),s=document.createElement('section'),sid='ce-section-'+(++seatSectionUid);s.className='ce-seat-section';
  s.innerHTML='<div class="ce-seat-head"><div class="inp"><label for="'+sid+'-name">Section name</label><input id="'+sid+'-name" class="ce-section-name" maxlength="120" value="'+esc(name||'Main floor')+'"></div><div class="inp"><label for="'+sid+'-kind">Section type</label><select id="'+sid+'-kind" class="ce-section-kind"><option value="seated">Numbered seats</option><option value="standing">Standing zone</option><option value="general_admission">General admission zone</option></select></div><div class="ce-seat-price-note" aria-label="Seat price uses the ticket price"><span>Seat price</span><b>Uses ticket price</b><small>Kept in sync automatically</small></div><button class="ce-rm ce-rm-section" type="button" aria-label="Remove seating section">×</button></div><div class="ce-seat-rows"></div><div class="ce-seat-empty" hidden><b>No rows in this section</b><small>Add a row or capacity group to continue.</small></div><button class="ce-add ce-add-row" type="button">+ Add row or capacity group</button>';
  box.appendChild(s);for(var i=0;i<(rows||1);i++)seatRow(s,String.fromCharCode(65+i),count||10);refreshSeatSection(s);return s;
}
function refreshSeatSection(section){var empty=section.querySelector('.ce-seat-empty');if(empty)empty.hidden=!!section.querySelector('.ce-seat-row')}
function nextRowLabel(section){var used={};[].forEach.call(section.querySelectorAll('.ce-row-label'),function(input){used[input.value.trim().toUpperCase()]=true});for(var i=0;i<26;i++){var label=String.fromCharCode(65+i);if(!used[label])return label}return 'Row '+(section.querySelectorAll('.ce-seat-row').length+1)}
function seatRow(section,label,count,values){
  var r=document.createElement('div'),rid='ce-row-'+(++seatRowUid);r.className='ce-seat-row';
  r.innerHTML='<div class="ce-row-tools"><div class="inp"><label for="'+rid+'-label">Row</label><input id="'+rid+'-label" class="ce-row-label" maxlength="40" value="'+esc(label||'A')+'"></div><div class="inp"><label for="'+rid+'-count">Seats</label><input id="'+rid+'-count" class="ce-seat-count" type="number" min="1" max="500" value="'+(count||10)+'"></div><button class="ce-rm ce-rm-row" type="button" aria-label="Remove row">×</button></div><div class="ce-seat-grid" role="group" aria-label="Seats"></div>';
  section.querySelector('.ce-seat-rows').appendChild(r);renderSeatButtons(r,values);refreshSeatSection(section);return r;
}
function renderSeatButtons(row,values){var grid=row.querySelector('.ce-seat-grid'),count=Math.max(1,Math.min(500,+row.querySelector('.ce-seat-count').value||1)),old=values||[].map.call(grid.children,function(b){return {label:b.dataset.label,state:b.dataset.state}});grid.innerHTML='';for(var i=0;i<count;i++){var v=old[i]||{},state=v.state||(v.accessible?'accessible':v.status==='unavailable'?'unavailable':'available'),b=document.createElement('button');b.type='button';b.className='ce-seat '+state;b.dataset.state=state;b.dataset.label=v.label||String(i+1);b.textContent=b.dataset.label;b.setAttribute('aria-label','Seat '+b.dataset.label+', '+b.dataset.state);grid.appendChild(b)}}
$('ce-tiers').addEventListener('change',function(e){var tier=e.target.closest('.ce-tier');if(!tier)return;if(e.target.classList.contains('ce-seat-mode')){var ed=tier.querySelector('.ce-seat-editor');ed.hidden=e.target.value!=='reserved';if(!ed.hidden&&!tier.querySelector('.ce-seat-section'))seatSection(tier,'Main floor',3,10)}if(e.target.classList.contains('ce-seat-count'))renderSeatButtons(e.target.closest('.ce-seat-row'))});
$('ce-tiers').addEventListener('click',function(e){var tier=e.target.closest('.ce-tier');if(!tier)return;if(e.target.closest('.ce-add-section')){var section=seatSection(tier,'Section '+(tier.querySelectorAll('.ce-seat-section').length+1),1,10);section.querySelector('.ce-section-name').focus();return}if(e.target.closest('.ce-add-row')){var s=e.target.closest('.ce-seat-section'),row=seatRow(s,nextRowLabel(s),10);row.querySelector('.ce-row-label').focus();return}if(e.target.closest('.ce-rm-section')){var addSection=tier.querySelector('.ce-add-section');e.target.closest('.ce-seat-section').remove();if(addSection)addSection.focus();return}if(e.target.closest('.ce-rm-row')){var s=e.target.closest('.ce-seat-section'),addRow=s.querySelector('.ce-add-row');e.target.closest('.ce-seat-row').remove();refreshSeatSection(s);if(addRow)addRow.focus();setCreateMessage('Row removed.');return}var seat=e.target.closest('.ce-seat');if(seat){var next=seat.dataset.state==='available'?'unavailable':seat.dataset.state==='unavailable'?'accessible':'available';seat.className='ce-seat '+next;seat.dataset.state=next;seat.setAttribute('aria-label','Seat '+seat.dataset.label+', '+next)}});

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
  fd.append('description',$('c-desc').value);fd.append('mode',m);fd.append('entry',entry());fd.append('use_registration',$('c-use-registration').checked?'1':'0');
  if($('c-multi').checked){fd.append('multiple','1');[].forEach.call($('ce-dates').children,function(r,i){var d=r.querySelector('.ce-md').value;fd.append('dates['+i+'][start_date]',d);fd.append('dates['+i+'][start_time]',r.querySelector('.ce-mt').value);if(r.querySelector('.ce-me').value){fd.append('dates['+i+'][end_date]',d);fd.append('dates['+i+'][end_time]',r.querySelector('.ce-me').value)}})}
  else{fd.append('multiple','0');['start_date:c-date','start_time:c-time','end_date:c-edate','end_time:c-etime'].forEach(function(x){var k=x.split(':');if(v(k[1]))fd.append(k[0],v(k[1]))})}
  fd.append('currency',curCode());fd.append('country',$('c-country').value);fd.append('timezone',$('c-timezone').value);
  if(m!=='Online'){fd.append('address',v('c-venue'));fd.append('city',v('c-city'));fd.append('state',v('c-state'))}
  if(m!=='In person'){fd.append('online_join_url',v('c-link'));fd.append('join_reveal_minutes',$('c-rev').value)}
  if($('c-use-registration').checked&&$('c-reg-form').value)fd.append('registration_form_id',$('c-reg-form').value);
  var si=0;[].forEach.call(f.querySelectorAll('#ce-tiers>.ce-tier'),function(t,i){
    var ins=t.querySelectorAll(':scope>.inp input'),x=t.querySelector('.ce-x'),adv=x&&x.querySelector('.ce-xsw').checked,early=x&&x.querySelector('.ce-early-on').checked,reserved=x&&x.querySelector('.ce-seat-on').checked,unl=adv&&x.querySelector('.ce-tunl').value==='unlimited';
    if(t.dataset.tid)fd.append('tiers['+i+'][id]',t.dataset.tid);fd.append('tiers['+i+'][name]',ins[0].value.trim());fd.append('tiers['+i+'][price]',ins[1].value||'0');if(!unl&&ins[2].value)fd.append('tiers['+i+'][quantity]',ins[2].value);
    var g=function(c){var el=x.querySelector(c);return el?el.value.trim():''},ck=function(c){var el=x.querySelector(c);return el&&el.checked?'1':'0'};
    if(early){fd.append('tiers['+i+'][early_price]',g('.ce-teb'));fd.append('tiers['+i+'][early_ends]',g('.ce-tebe'))}
    fd.append('tiers['+i+'][seating_mode]',reserved?'reserved':'general_admission');
    if(adv){if(g('.ce-tdesc'))fd.append('tiers['+i+'][description]',g('.ce-tdesc'));fd.append('tiers['+i+'][min_per_order]',g('.ce-tmin')||'1');if(g('.ce-tmax'))fd.append('tiers['+i+'][max_per_order]',g('.ce-tmax'));if(g('.ce-tsales'))fd.append('tiers['+i+'][sales_start]',g('.ce-tsales'));if(g('.ce-tends'))fd.append('tiers['+i+'][sales_end]',g('.ce-tends'));fd.append('tiers['+i+'][ticket_visibility]',g('.ce-tvis'));fd.append('tiers['+i+'][sales_status]',g('.ce-tstatus'));fd.append('tiers['+i+'][numbering_enabled]',ck('.ce-num-on'));fd.append('tiers['+i+'][numbering_scope]',g('.ce-num-scope'));if(g('.ce-prefix'))fd.append('tiers['+i+'][number_prefix]',g('.ce-prefix'));fd.append('tiers['+i+'][number_start]',g('.ce-num-start')||'1');fd.append('tiers['+i+'][number_padding]',g('.ce-padding')||'3');fd.append('tiers['+i+'][number_format]',g('.ce-num-format')||'{PREFIX}-{NUMBER}');fd.append('tiers['+i+'][design_settings][accent]',g('.ce-design-color'));['logo','attendee','category','number','section','row','seat','date','venue','status'].forEach(function(k){fd.append('tiers['+i+'][design_settings][show_'+k+']',ck('.ce-design-'+k))})}
    if(reserved)[].forEach.call(x.querySelectorAll('.ce-seat-section'),function(section){fd.append('seat_map['+si+'][name]',section.querySelector('.ce-section-name').value.trim());fd.append('seat_map['+si+'][kind]',section.querySelector('.ce-section-kind').value);fd.append('seat_map['+si+'][ticket_index]',String(i));[].forEach.call(section.querySelectorAll('.ce-seat-row'),function(row,ri){fd.append('seat_map['+si+'][rows]['+ri+'][label]',row.querySelector('.ce-row-label').value.trim());[].forEach.call(row.querySelectorAll('.ce-seat'),function(seat,sj){fd.append('seat_map['+si+'][rows]['+ri+'][seats]['+sj+'][label]',seat.dataset.label);fd.append('seat_map['+si+'][rows]['+ri+'][seats]['+sj+'][status]',seat.dataset.state==='unavailable'?'unavailable':'available');fd.append('seat_map['+si+'][rows]['+ri+'][seats]['+sj+'][accessible]',seat.dataset.state==='accessible'?'1':'0')})});si++})
  });
  fd.append('fee_payer',payer()==='me'?'organizer':'buyer');fd.append('visibility',$('c-vis').value);fd.append('refund_policy',$('c-ref').value);
  fd.append('show_remaining',$('c-left').checked?'1':'0');fd.append('waitlist_enabled',$('c-wait').checked?'1':'0');fd.append('allow_transfers',$('c-trans').checked?'1':'0');fd.append('countdown',$('c-cd').checked?'1':'0');
  fd.append('ask_question',$('c-askq').checked?'1':'0');fd.append('attendee_question',v('c-question'));
  fd.append('meta_description',v('c-seo'));fd.append('meta_keywords',v('c-keys'));
  if($('c-flyer').files[0])fd.append('flyer',$('c-flyer').files[0]);
  fd.append('programme_enabled',$('c-programme').checked?'1':'0');fd.append('programme_type',programmeType());
  if($('c-programme').checked){var people=[].slice.call($('ce-people').querySelectorAll('.ce-person'));people.forEach(function(row,i){var add=function(k,c){var el=row.querySelector(c);if(el&&el.value.trim())fd.append('programme_people['+i+']['+k+']',el.value.trim())};if(row.dataset.pid)fd.append('programme_people['+i+'][id]',row.dataset.pid);add('name','.ce-person-name');add('role','.ce-person-role');add('topic','.ce-person-topic');add('bio','.ce-person-bio');if(row.dataset.removeImage==='1')fd.append('programme_people['+i+'][remove_image]','1');var image=row.querySelector('.ce-person-image');if(image&&image.files[0])fd.append('programme_people['+i+'][image]',image.files[0])});
    [].forEach.call($('ce-agenda-editor').querySelectorAll('.ce-agenda-row'),function(row,i){var add=function(k,c){var el=row.querySelector(c);if(el&&el.value.trim())fd.append('agenda_items['+i+']['+k+']',el.value.trim())};add('title','.ce-agenda-title');add('location','.ce-agenda-location');add('description','.ce-agenda-description');add('starts_at','.ce-agenda-start');add('ends_at','.ce-agenda-end');var key=row.querySelector('.ce-agenda-person').value;if(key){var pi=people.findIndex(function(person){return person.dataset.key===key});if(pi>-1)fd.append('agenda_items['+i+'][person_index]',String(pi))}})}
  if(ADMIN){fd.append('organizer_id',$('c-owner').value);fd.append('is_featured',$('c-featured').checked?'1':'0');if(EDIT)fd.append('status',$('c-status').value)}
  return fd;
}
var EVENT_UPLOAD_LIMIT=3.75*1024*1024;
function eventImageName(name){return String(name||'event-image').replace(/\.[^.]+$/,'').replace(/[^a-z0-9_-]+/gi,'-').replace(/^-+|-+$/g,'')+'.webp'}
function optimizeEventImage(file,maxDimension,targetBytes){
  return new Promise(function(resolve,reject){
    if(!file||!/^image\/(jpeg|png|webp|gif)$/.test(file.type)){reject(new Error('Use JPG, PNG, WebP or GIF images for your event.'));return}
    var url=URL.createObjectURL(file),img=new Image();
    function done(){URL.revokeObjectURL(url)}
    img.onerror=function(){done();reject(new Error('We could not read one of the event images. Choose another image and try again.'))};
    img.onload=function(){
      var scale=Math.min(1,maxDimension/Math.max(img.naturalWidth||img.width,img.naturalHeight||img.height)),w=Math.max(1,Math.round((img.naturalWidth||img.width)*scale)),h=Math.max(1,Math.round((img.naturalHeight||img.height)*scale)),canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');
      if(!ctx){done();reject(new Error('Your browser could not optimize the event images. Use a smaller image and try again.'));return}
      canvas.width=w;canvas.height=h;ctx.drawImage(img,0,0,w,h);done();
      var qualities=[.86,.74,.62,.5],attempt=0;
      function encode(){canvas.toBlob(function(blob){
        if(!blob){reject(new Error('Your browser could not optimize the event images. Use a smaller image and try again.'));return}
        if(blob.size<=targetBytes||attempt===qualities.length-1){resolve(blob);return}
        attempt++;encode();
      },'image/webp',qualities[attempt])}
      encode();
    };
    img.src=url;
  });
}
function eventPayloadSize(fd){var total=0;fd.forEach(function(value,key){total+=String(key).length+80;if(value instanceof Blob)total+=value.size;else total+=String(value).length});return total}
function prepareEventPayload(draft){
  var fd=payload(draft),jobs=[],flyer=$('c-flyer').files[0];
  if(flyer)jobs.push(optimizeEventImage(flyer,1600,1400*1024).then(function(blob){fd.set('flyer',blob,eventImageName(flyer.name))}));
  if($('c-programme').checked)[].forEach.call($('ce-people').querySelectorAll('.ce-person'),function(row,i){var input=row.querySelector('.ce-person-image'),file=input&&input.files[0];if(file)jobs.push(optimizeEventImage(file,900,350*1024).then(function(blob){fd.set('programme_people['+i+'][image]',blob,eventImageName(file.name))}))});
  return Promise.all(jobs).then(function(){if(eventPayloadSize(fd)>EVENT_UPLOAD_LIMIT)throw new Error('Your event images are still too large to upload together. Use fewer or smaller images, then try again.');return fd});
}
/* step by step */
var STAGES=['ce-basics','ce-where','ce-tix','ce-more','ce-review'],at=0,reached=0;
var STEPNAME=['Details','Type & location','Tickets & fees','Line-up & media','Preview'];
function stageOf(id){var el=$(id);if(!el)return 0;var fs=el.closest('fieldset');var i=fs?STAGES.indexOf(fs.id):0;return i<0?0:i}
function stage(i,focus){
  at=Math.max(0,Math.min(STAGES.length-1,i));reached=Math.max(reached,at);
  STAGES.forEach(function(id,k){$(id).hidden=k!==at});
  [].forEach.call(f.querySelectorAll('.ce-steps a'),function(a,k){if(k===at)a.setAttribute('aria-current','step');else a.removeAttribute('aria-current');a.classList.toggle('done',k<at||k<=reached&&k!==at)});
  $('ce-progress').textContent='Step '+(at+1)+' of '+STAGES.length+': '+STEPNAME[at];
  var cur=f.querySelector('.ce-steps a[aria-current=step]'),bar=f.querySelector('.ce-steps');if(cur&&bar&&bar.scrollWidth>bar.clientWidth)bar.scrollLeft=Math.max(0,cur.offsetLeft-bar.offsetLeft-16);
  var last=at===STAGES.length-1;$('ce-back').hidden=at===0;$('ce-next').hidden=last;$('ce-draft').hidden=false;$('ce-publish').hidden=!last;
  if(last)summary();
  setCreateMessage('');
  var top=f.querySelector('.ce-steps');if(top&&top.getBoundingClientRect().top<0)top.scrollIntoView({behavior:'smooth',block:'start'});
  if(focus){var x=$(STAGES[at]).querySelector('input:not([type=radio]):not([type=checkbox]),select,textarea');if(x)x.focus({preventScroll:true})}
}
function need(id,msg){var el=$(id);if(el&&!String(el.value).trim()){$('c-msg').textContent=msg;el.focus();return false}return true}
function check(i){
  var m=mode();
  if(i===0){if($('c-name').value.trim().length<3){$('c-msg').textContent='Give your event a name first.';$('c-name').focus();return false}return true}
  if(i===1){
    if($('c-multi').checked){var rows=$('ce-dates').children;if(!rows.length||[].some.call(rows,function(r){return !r.querySelector('.ce-md').value||!r.querySelector('.ce-mt').value})){$('c-msg').textContent='Add a date and start time for each day.';return false}}
    else if(!(need('c-date','Choose the date your event starts.')&&need('c-time','Choose the time your event starts.')))return false;
    if(m!=='Online'&&!(need('c-venue','Enter the venue.')&&need('c-city','Enter the city.')))return false;if(m!=='In person'&&!need('c-link','Add the link people use to join.'))return false;return true}
  if(i===2){if($('c-use-registration').checked){if(ADMIN&&!$('c-owner').value){$('c-msg').textContent='Assign an organizer before adding a registration form.';assignOwner.checked=true;syncOwnerAssignment(false);owner.focus();return false}if(!$('c-reg-form').value){$('c-msg').textContent=(TICA_DATA.registrationForms||[]).length?'Choose the registration form to attach.':'Create a registration form first, then return to attach it.';var target=(TICA_DATA.registrationForms||[]).length?$('c-reg-form'):$('ce-reg-empty').querySelector('a');target.focus();return false}}var tiers=f.querySelectorAll('#ce-tiers .ce-tier'),paid=false,ok=true;
    [].forEach.call(tiers,function(t){var ins=t.querySelectorAll(':scope>.inp input');if(!ins[0].value.trim()&&ok){ok=false;$('c-msg').textContent='Give every ticket type a name.';ins[0].focus()}else if(ins[1].value.trim()===''&&ok){ok=false;$('c-msg').textContent='Enter a price for every ticket. Use 0 for free tickets.';ins[1].focus()}var early=t.querySelector('.ce-early-on'),earlyError='';if(ok&&early&&early.checked&&!t.querySelector('.ce-teb').value){ok=false;$('c-msg').textContent='Enter the early-bird price.';t.querySelector('.ce-teb').focus()}else if(ok&&early&&early.checked&&(earlyError=validateEarlyBird(t))){ok=false;$('c-msg').textContent=earlyError;t.querySelector('.ce-teb').focus()}else if(ok&&early&&early.checked&&!t.querySelector('.ce-tebe').value){ok=false;$('c-msg').textContent='Choose when the early-bird price ends.';t.querySelector('.ce-tebe').focus()}var sm=t.querySelector('.ce-seat-mode');if(ok&&sm&&sm.value==='reserved'&&!t.querySelector('.ce-seat-section')){ok=false;$('c-msg').textContent='Add a seating section for every reserved ticket type.';t.querySelector('.ce-seat-on').focus()}else if(ok&&sm&&sm.value==='reserved'){var emptySection=[].filter.call(t.querySelectorAll('.ce-seat-section'),function(section){return !section.querySelector('.ce-seat-row')})[0];if(emptySection){ok=false;$('c-msg').textContent='Add at least one row to every seating section.';emptySection.querySelector('.ce-add-row').focus()}}if(parseFloat(ins[1].value)>0)paid=true});
    if(!ok)return false;if(paid&&!curOf(curCode()).paid){$('c-msg').textContent=curCode()+' can\'t take card payments yet. Make the tickets free or change the currency in step 2.';return false}return true}
  if(i===3){if($('c-askq').checked&&!need('c-question','Write the question you want to ask.'))return false;if($('c-programme').checked){var people=$('ce-people').querySelectorAll('.ce-person');if(!people.length){$('c-msg').textContent='Add at least one speaker or artist, or turn the event programme off.';$('ce-add-person').focus();return false}var unnamed=[].filter.call(people,function(row){return !row.querySelector('.ce-person-name').value.trim()})[0];if(unnamed){$('c-msg').textContent='Give every speaker or artist a name.';unnamed.querySelector('.ce-person-name').focus();return false}var badAgenda=[].filter.call($('ce-agenda-editor').querySelectorAll('.ce-agenda-row'),function(row){return !row.querySelector('.ce-agenda-title').value.trim()})[0];if(badAgenda){$('c-msg').textContent='Give every agenda item a title.';badAgenda.querySelector('.ce-agenda-title').focus();return false}}return true}
  return true;
}
function summary(){
  var m=mode(),o=curOf(curCode()),tiers=[].map.call(f.querySelectorAll('#ce-tiers .ce-tier'),function(t){var ins=t.querySelectorAll(':scope>.inp input');return esc(ins[0].value.trim()||'Ticket')+', '+(parseFloat(ins[1].value)>0?esc(N(+ins[1].value)):'Free')+(ins[2]&&ins[2].value?', '+esc(ins[2].value)+' available':'')});
  var when=$('c-multi').checked?[].map.call($('ce-dates').children,function(r){return r.querySelector('.ce-md').value+' '+r.querySelector('.ce-mt').value}).join('; '):($('c-date').value+' '+$('c-time').value);
  var rows=[['Event',esc($('c-name').value.trim())],['When',esc(when)],['Timezone',esc($('c-timezone').selectedOptions[0].text)],['Where',m==='Online'?'Online, '+esc($('c-plat').value):esc([$('c-venue').value,$('c-city').value,$('c-country').value].filter(Boolean).join(', '))+(m==='Hybrid'?' and online':'')],['Currency',esc(o.code+' ('+o.symbol+')')],['Tickets',tiers.join('<br>')],['Registration form',$('c-use-registration').checked?esc($('c-reg-form').selectedOptions[0].text):'Not added'],['Programme',$('c-programme').checked?$('ce-people').children.length+' '+(programmeType()==='artist'?'line-up artist':'speaker')+($('ce-people').children.length===1?'':'s'):'Not added'],['Booking fee',payer()==='buyer'?'Buyers pay it':'Included in your prices'],['Who can find it',esc($('c-vis').selectedOptions[0].text)],['Flyer',$('c-flyer').files[0]?esc($('c-flyer').files[0].name):(EDIT&&EDIT.event.flyer?'Current flyer':'TICA neutral artwork')]];
  if(ADMIN)rows.splice(1,0,['Owner',$('c-owner').value?esc($('c-owner').selectedOptions[0].text):'TICA · platform-managed'],['Featured',$('c-featured').checked?'Yes':'No']);
  $('ce-sum').innerHTML=rows.map(function(r){return '<div><dt>'+r[0]+'</dt><dd>'+r[1]+'</dd></div>'}).join('');
}
$('ce-next').addEventListener('click',function(){if(check(at))stage(at+1,true)});
$('ce-back').addEventListener('click',function(){stage(at-1,true)});
f.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target.tagName==='INPUT'&&at<STAGES.length-1){e.preventDefault();if(check(at))stage(at+1,true)}});
var FIELDS={organizer_id:'c-owner',is_featured:'c-featured',currency:'c-cur',country:'c-country',timezone:'c-timezone',name:'c-name',category_id:'c-cat',start_date:'c-date',start_time:'c-time',end_date:'c-edate',end_time:'c-etime',address:'c-venue',city:'c-city',online_join_url:'c-link',flyer:'c-flyer',attendee_question:'c-question',dates:'c-multi',tiers:'ce-add',programme_people:'ce-add-person',agenda_items:'ce-add-agenda'};
function setCreateMessage(text,state){var msg=$('c-msg');msg.textContent=text||'';msg.className='ce-feedback'+(state?' is-'+state:'')}
function showCreateSuccess(r,draft){
  var panel=$('ce-success'),title=$('ce-success-title'),copy=$('ce-success-copy'),manage=$('ce-success-manage'),view=$('ce-success-view'),kicker=panel.querySelector('.ce-success-kicker');
  kicker.textContent=EDIT?'Changes saved':draft?'Draft saved':'Event created';title.textContent=EDIT?'Your changes are saved':draft?'Your draft is ready':r.name+' is live';
  copy.textContent=EDIT?'The latest event details are now saved.':draft?'Continue whenever you are ready from Events › Drafts.':'The event was created successfully and is ready to manage.';
  manage.href=r.redirect_url||r.registration_url||(ADMIN?ADMIN.backUrl:'/dashboard/events');manage.textContent=draft?'Open drafts':'Manage event';
  view.href=r.url||'#';view.hidden=draft||!r.url;panel.hidden=false;panel.focus();
}
function save(draft){
  var msg=$('c-msg'),btns=f.querySelectorAll('.ce-go .btn');setCreateMessage(draft?'Saving your draft…':'Publishing your event…','working');
  [].forEach.call(btns,function(b){b.disabled=true});
  prepareEventPayload(draft).then(function(fd){
    var req={method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':TICA_DATA.csrf,'X-Requested-With':'XMLHttpRequest'},body:fd};
    return EDIT?fetch(EDIT.endpoint,req):fetch(ADMIN?ADMIN.endpoint:'/tica-api/events',req);
  })
  .then(function(r){return r.text().then(function(text){var j={};if(text)try{j=JSON.parse(text)}catch(ignore){}if(!r.ok){var message=r.status===413?'Your event images are too large to upload together. Use fewer or smaller images, then try again.':j.message||'We couldn\'t save your event. Try again.';var e=new Error(message);e.data=j;e.status=r.status;throw e}return j})})
  .then(function(r){
    if(!r||!r.event_id||!r.name){var incomplete=new Error('Your session may have expired. Sign in again, then publish the event.');incomplete.data=r||{};throw incomplete}
    window.TICA_NEW={name:r.name,status:r.status};
    setCreateMessage('');showCreateSuccess(r,draft);
  },function(er){
    [].forEach.call(btns,function(b){b.disabled=false});
    var errs=er.data&&er.data.errors||{},k=Object.keys(errs)[0],text=k?errs[k][0]:er.message;
    var id=k&&(FIELDS[k]||FIELDS[k.split('.')[0]]);if(id)stage(stageOf(id));else if(k&&/^tiers/.test(k))stage(2);setCreateMessage(text||'We couldn\'t save your event. Try again.','error');
    if(k&&/^tiers\.\d+\./.test(k)){var t=f.querySelectorAll('#ce-tiers .ce-tier')[+k.split('.')[1]];if(t){t.scrollIntoView({behavior:'smooth',block:'center'});var x=t.querySelector('input');if(x)x.focus({preventScroll:true})}}
    else if(id&&$(id)){$(id).scrollIntoView({behavior:'smooth',block:'center'});$(id).focus({preventScroll:true})}
    else msg.scrollIntoView({behavior:'smooth',block:'center'});
  });
}
f.addEventListener('submit',function(e){e.preventDefault();if(at<STAGES.length-1){if(check(at))stage(at+1,true);return}for(var k=0;k<STAGES.length-1;k++){if(!check(k)){stage(k,false);check(k);return}}save(false)});
$('ce-draft').addEventListener('click',function(){if($('c-name').value.trim().length<3){$('c-msg').textContent='Give your event a name first.';$('c-name').focus();return}save(true)});
f.querySelector('.ce-steps').addEventListener('click',function(e){var a=e.target.closest('[data-ce]');if(!a)return;e.preventDefault();e.stopPropagation();var to=STAGES.indexOf(a.dataset.ce);
  if(to<=at||to<=reached){stage(to,true);return}for(var k=at;k<to;k++){if(!check(k)){stage(k,false);return}}stage(to,true)},true);
window.addEventListener('tica:navigate',function(){if(/^\/create/.test(location.pathname))setTimeout(function(){upd();stage(at)},0)});
/* edit mode: fill the builder with the saved event */
function fillEdit(){
  if(!EDIT)return;var E=EDIT.event,set=function(id,v){var el=$(id);if(el&&v!=null)el.value=v};
  var h=f.querySelector('h1.display');if(h)h.textContent='Edit event';
  var ld=f.querySelector('p.lead');if(ld)ld.textContent='Change anything below and save. Tickets that have sold keep their price.';
  var ap=$('ce-admin');if(ap&&ADMIN){[].forEach.call(f.querySelectorAll('.ce-admin-edit'),function(x){x.hidden=false});$('c-status').value=E.status;
    var del=$('ce-delete');if(del)del.addEventListener('click',function(){if(!window.confirm('Delete "'+E.name+'"? This removes the event, its tickets and its page, and can\'t be undone.'))return;del.disabled=true;
      var fd=new FormData();fd.append('_token',TICA_DATA.csrf);fetch(EDIT.deleteUrl,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':TICA_DATA.csrf},body:fd}).then(function(){location.href=ADMIN.backUrl},function(){del.disabled=false;$('c-msg').textContent='We couldn\'t delete the event. Try again.'})});}
  if(ADMIN){set('c-owner',E.organizer_id||'');assignOwner.checked=!!E.organizer_id;syncOwnerAssignment(false);$('c-featured').checked=!!E.is_featured}
  set('c-name',E.name);if(E.category_id)set('c-cat',E.category_id);set('c-age',E.age_restriction);set('c-desc',E.description);set('c-timezone',E.timezone||'Africa/Lagos');
  var mr=f.querySelector('[name=c-mode][value="'+E.mode+'"]');if(mr)mr.checked=true;
  var er=f.querySelector('[name=c-entry][value="'+E.entry+'"]');if(er)er.checked=true;
  [].forEach.call(f.querySelectorAll('[name=c-entry]'),function(x){x.disabled=true});
  var registrationToggle=$('c-use-registration');if(registrationToggle){registrationToggle.checked=!!E.registration_form_id;registrationToggle.disabled=E.entry==='corporate';registrationToggle.setAttribute('aria-expanded',String(registrationToggle.checked));$('ce-registration-fields').hidden=!registrationToggle.checked;set('c-reg-form',E.registration_form_id||'');ceFormPicked()}
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
    if(x&&(t.description||t.max_per_order||t.quantity==null||t.number_prefix||t.sales_start||t.sales_end)){var sw=x.querySelector('.ce-xsw');sw.checked=true;sw.setAttribute('aria-expanded','true');x.querySelector('.ce-xo').hidden=false;
      x.querySelector('.ce-tdesc').value=t.description||'';x.querySelector('.ce-tmax').value=t.max_per_order||'';x.querySelector('.ce-tunl').value=t.quantity==null?'unlimited':'limited';
      x.querySelector('.ce-tmin').value=t.min_per_order||1;x.querySelector('.ce-tsales').value=t.sales_start||'';x.querySelector('.ce-tends').value=t.sales_end||'';x.querySelector('.ce-tvis').value=t.ticket_visibility||'public';x.querySelector('.ce-tstatus').value=t.sales_status||'on_sale';x.querySelector('.ce-num-on').checked=t.numbering_enabled!==false;x.querySelector('.ce-num-scope').value=t.numbering_scope||'category';x.querySelector('.ce-prefix').value=t.number_prefix||'';x.querySelector('.ce-num-start').value=t.number_start||1;x.querySelector('.ce-padding').value=String(t.number_padding||3);x.querySelector('.ce-num-format').value=t.number_format||'{PREFIX}-{NUMBER}';if(t.design_settings){x.querySelector('.ce-design-color').value=t.design_settings.accent||'#245fd3';['logo','attendee','category','number','section','row','seat','date','venue','status'].forEach(function(k){var el=x.querySelector('.ce-design-'+k),v=t.design_settings['show_'+k];if(el)el.checked=v!==false&&v!=='0'&&v!==0})}}
    if(x&&t.early_price!=null){x.querySelector('.ce-early-on').checked=true;x.querySelector('.ce-early-on').setAttribute('aria-expanded','true');x.querySelector('.ce-early-fields').hidden=false;x.querySelector('.ce-teb').value=t.early_price;x.querySelector('.ce-tebe').value=t.early_ends||''}
    if(x&&t.seating_mode==='reserved'){x.querySelector('.ce-seat-on').checked=true;x.querySelector('.ce-seat-on').setAttribute('aria-expanded','true');x.querySelector('.ce-seat-mode').value='reserved';x.querySelector('.ce-seat-editor').hidden=false}
    if(t.sold>0||t.locked){ins[1].readOnly=true;var rm=row.querySelector('.ce-rm');if(rm)rm.remove();
      row.insertAdjacentHTML('beforeend','<p class="ce-sold">'+(t.locked?'This ticket has options set in the old editor, so it stays as it is here.':t.sold+' sold, so the price stays the same and it can\'t be removed. The quantity can\'t go below '+t.sold+'.')+'</p>');
      if(t.locked)[].forEach.call(row.querySelectorAll('input,select'),function(el){el.disabled=true})}
  });
  (E.seat_map||[]).forEach(function(section){var tier=f.querySelectorAll('#ce-tiers>.ce-tier')[section.ticket_index||0];if(!tier)return;var editor=tier.querySelector('.ce-seat-editor');editor.hidden=false;var s=seatSection(tier,section.name,0,0);s.querySelector('.ce-section-kind').value=section.kind||'seated';s.querySelector('.ce-seat-rows').innerHTML='';(section.rows||[]).forEach(function(row){seatRow(s,row.label,(row.seats||[]).length,row.seats)})});
  var pay=f.querySelector('[name=c-payer][value='+(E.fee_payer==='organizer'?'me':'buyer')+']');if(pay)pay.checked=true;
  set('c-vis',E.visibility);set('c-ref',E.refund_policy);
  $('c-left').checked=!!E.show_remaining;$('c-wait').checked=!!E.waitlist_enabled;$('c-trans').checked=!!E.allow_transfers;$('c-cd').checked=!!E.countdown;
  $('c-askq').checked=!!E.attendee_question;set('c-question',E.attendee_question);set('c-seo',E.meta_description);set('c-keys',E.meta_keywords);
  if(E.flyer){$('p-flyer').innerHTML='';var img=new Image();img.alt='Your flyer';img.src=E.flyer;$('p-flyer').appendChild(img);
    var fh=$('c-flyer').closest('.inp');if(fh)fh.insertAdjacentHTML('afterend','<p class="hint">Your current flyer stays unless you choose a new one.</p>')}
  if(E.programme_enabled){$('c-programme').checked=true;$('c-programme').setAttribute('aria-expanded','true');$('ce-programme-fields').hidden=false;var pr=f.querySelector('[name=c-programme-type][value="'+(E.programme_type||'speaker')+'"]');if(pr)pr.checked=true;(E.programme_people||[]).forEach(function(person){personRow(person)});(E.agenda_items||[]).forEach(function(item){agendaRow(item)});programmeCopy()}
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
  if (window.TICA_ADMIN) return;
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
      if (![...select.options].some((option) => option.value === country)) select.add(new Option(country, country));
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
    const options = [{ value: 'all', label: 'All cities' }, ...cities.map((city) => ({ value: city, label: city }))];
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

(() => {
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  document.querySelectorAll('[data-event-reel]').forEach((reel) => {
    const slides = [...reel.querySelectorAll('[data-event-slide]')];
    if (slides.length < 2) return;

    let active = 0;
    let timer = null;

    const select = (index) => {
      active = (index + slides.length) % slides.length;
      slides.forEach((slide, slideIndex) => {
        const selected = slideIndex === active;
        slide.classList.toggle('is-active', selected);
        slide.setAttribute('aria-hidden', String(!selected));
      });
    };

    const stop = () => {
      if (timer) window.clearInterval(timer);
      timer = null;
    };

    const start = () => {
      stop();
      if (!reduceMotion.matches && !document.hidden) {
        timer = window.setInterval(() => select(active + 1), 4400);
      }
    };

    reel.addEventListener('pointerenter', stop);
    reel.addEventListener('pointerleave', start);
    reel.addEventListener('focusin', stop);
    reel.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', start);
    reduceMotion.addEventListener?.('change', start);
    select(0);
    start();
  });
})();
