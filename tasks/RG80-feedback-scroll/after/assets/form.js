(()=>{

'use strict';
const questions=RGFS.form.questions;
const form=document.querySelector('#feedback-form'),root=document.querySelector('#questions'),live=document.querySelector('#live');let completed=false;const answers={};
const optionIcons={"◎": "<rect x=\"4\" y=\"4\" width=\"16\" height=\"16\" rx=\"5\"/><circle cx=\"12\" cy=\"12\" r=\"3.5\"/><circle cx=\"17\" cy=\"7\" r=\".6\"/>", "⌕": "<circle cx=\"10.5\" cy=\"10.5\" r=\"6.5\"/><path d=\"m16 16 5 5\"/>", "▷": "<rect x=\"3\" y=\"5\" width=\"18\" height=\"14\" rx=\"4\"/><path d=\"m10 9 5 3-5 3Z\"/>", "♡": "<path d=\"M12 20 4.5 13A5 5 0 0 1 12 6a5 5 0 0 1 7.5 7Z\"/>", "⚑": "<path d=\"M4 13V7l13-4v14L4 13Zm0 0 3 7h3l-2-6M20 7l2-1m-2 7 2 1\"/>", "↗": "<path d=\"M5 19 19 5M5 5h14v14\"/>", "?": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M9.5 9a2.5 2.5 0 1 1 4 2c-1 .6-1.5 1-1.5 2m0 3h.01\"/>", "◷": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 6v6l4 2\"/>", "▧": "<rect x=\"3\" y=\"3\" width=\"18\" height=\"18\" rx=\"3\"/><circle cx=\"8\" cy=\"8\" r=\"1.5\"/><path d=\"m4 18 6-6 4 4 3-3 4 4\"/>", "↔": "<path d=\"M3 12h18M7 8l-4 4 4 4m10-8 4 4-4 4\"/>", "₹": "<path d=\"M6 5h13M6 9h13M8 5c8 0 8 8 0 8H6l10 7\"/>", "↶": "<path d=\"M4 9h10a6 6 0 0 1 0 12h-3M8 5 4 9l4 4\"/>", "✓": "<path d=\"m5 12 4 4L19 6\"/>", "✳": "<path d=\"M12 2v20M2 12h20M5 5l14 14M5 19 19 5\"/>", "〰": "<path d=\"M2 12c4-12 6 12 10 0s6 12 10 0\"/>", "!": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 6v7m0 4h.01\"/>", "—": "<path d=\"M5 12h14\"/>", "✧": "<path d=\"m12 2 3 7 7 3-7 3-3 7-3-7-7-3 7-3Z\"/>", "+": "<path d=\"M12 4v16M4 12h16\"/>", "ϟ": "<path d=\"m14 2-9 12h7l-2 8 9-12h-7Z\"/>", "▤": "<rect x=\"4\" y=\"3\" width=\"16\" height=\"18\" rx=\"2\"/><path d=\"M8 8h8M8 12h8M8 16h5\"/>"};
const optionIcon=s=>`<svg viewBox="0 0 24 24" aria-hidden="true">${optionIcons[s]||optionIcons["✧"]}</svg>`;
questions.forEach((q,i)=>{const f=document.createElement('fieldset');f.className='question';f.id='q-'+q.id;f.style.setProperty('--accent',`var(${q.color})`);f.setAttribute('aria-labelledby','title-'+q.id);f.setAttribute('aria-describedby','help-'+q.id+' error-'+q.id);f.innerHTML=`<div class="q-head"><span class="number" aria-hidden="true">${String(i+1).padStart(2,'0')}</span><div><h3 class="q-title" id="title-${q.id}">${q.title}</h3><p class="help" id="help-${q.id}">${q.help}</p></div><span class="answered" aria-hidden="true">✓</span></div><div class="options ${q.kind}">${q.options.map((o,j)=>`<label class="pick"><input type="${q.multi?'checkbox':'radio'}" name="${q.id}" value="${j}" aria-label="${o}"><span class="tile">${q.kind==='rating'?`<span class="rating-number" aria-hidden="true">${String(j+1).padStart(2,'0')}</span>`:q.kind==='recommend'?'':`<span class="symbol" aria-hidden="true">${optionIcon(q.symbols[j])}</span>`}<span>${o}</span><span class="mark" aria-hidden="true">✓</span></span></label>`).join('')}</div>${q.id==='checkout'?'<div class="follow" id="checkout-follow" hidden><p>What got in the way? <span style="font-weight:400">(Optional)</span></p><div class="options" id="checkout-parts"></div></div>':''}<p class="q-error" id="error-${q.id}"></p>`;root.append(f);});
const railLabels=['Website ease','Found us','Finding products','Clear information','Checkout','Why Roun Groun','What to improve','Tell a friend'];
document.querySelector('#question-links').innerHTML=questions.map((q,i)=>`<li><a href="#q-${q.id}" data-question-link="${q.id}" aria-label="${q.title} — not answered"><span class="rail-step" aria-hidden="true">${String(i+1).padStart(2,'0')}</span><span>${railLabels[i]}</span></a></li>`).join('');
const parts=['Address','Delivery cost','Coupon','Payment','Something else'];document.querySelector('#checkout-parts').innerHTML=parts.map((p,i)=>`<label class="pick"><input type="checkbox" name="checkout_part" value="${i}" aria-label="${p}"><span class="tile"><span>${p}</span><span class="mark" aria-hidden="true">✓</span></span></label>`).join('');
function announce(s){live.textContent=s;}
function update(){let n=0;questions.forEach(q=>{const fs=document.querySelector('#q-'+q.id),checked=[...fs.querySelectorAll(`input[name="${q.id}"]:checked`)];answers[q.id]=checked.map(x=>q.options[Number(x.value)]);const done=checked.length>0;fs.classList.toggle('done',done);if(done){n++;fs.classList.remove('invalid');fs.removeAttribute('aria-invalid');document.querySelector('#error-'+q.id).textContent='';}});document.querySelector('#count').textContent=`${n} of 8 answered`;document.querySelector('#answered-count').textContent=n;document.querySelector('#remaining-count').textContent=8-n;document.querySelector('#mobile-ratio').textContent=n+'/8';document.querySelector('#mobile-left').textContent=(8-n)+' left';document.querySelector('.survey-tracker').style.setProperty('--answered-progress',(n/8*100)+'%');document.querySelector('.track').setAttribute('aria-valuetext',n+' answered, '+(8-n)+' left');questions.forEach((q,i)=>{const a=document.querySelector(`[data-question-link="${q.id}"]`),done=answers[q.id].length>0;a.classList.toggle('is-done',done);a.setAttribute('aria-label',q.title+(done?' — answered':' — not answered'));a.querySelector('.rail-step').textContent=done?'✓':String(i+1).padStart(2,'0');});document.querySelector('#fill').style.width=(n/8*100)+'%';document.querySelector('.track').setAttribute('aria-valuenow',n);document.querySelector('#progress-note').textContent=n===8?'Ready when you are':n>4?'Nearly there':'A few taps to go';if(n===8)document.querySelector('#form-summary').textContent='';else if(document.querySelector('#form-summary').textContent)document.querySelector('#form-summary').textContent=`${8-n} ${8-n===1?'question needs':'questions need'} a tap. Look for the red line.`;const follow=document.querySelector('#checkout-follow');follow.hidden=!['A bit tricky','I got stuck'].includes(answers.checkout[0]);if(follow.hidden)follow.querySelectorAll('input').forEach(x=>x.checked=false);return n;}
root.addEventListener('change',e=>{const input=e.target;if(!input.matches('input'))return;const q=questions.find(q=>q.id===input.name);if(q&&q.multi){const fs=input.closest('fieldset');if(input.checked&&q.exclusive){const ex=q.options.indexOf(q.exclusive);if(Number(input.value)===ex)fs.querySelectorAll(`input[name="${q.id}"]`).forEach(x=>{if(x!==input)x.checked=false;});else if(ex>=0)fs.querySelector(`input[value="${ex}"]`).checked=false;}if(q.max&&fs.querySelectorAll(`input[name="${q.id}"]:checked`).length>q.max){input.checked=false;document.querySelector('#error-'+q.id).textContent=`Pick up to ${q.max}. Tap one again to swap it.`;announce(`Pick up to ${q.max}. Tap one again to swap it.`);return;}document.querySelector('#error-'+q.id).textContent='';}update();});
function scrollToEl(el){el.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'start'});}
// Keep the start link and desktop question tracker on the same scroll path as
// validation/receipt navigation. Native fragment jumps otherwise ignore smooth
// scrolling when the document's scroll-behavior is auto.
document.querySelector('.rg-feedback').addEventListener('click',event=>{
 if(event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
 const link=event.target.closest('a[href^="#"]');
 if(!link||link.hasAttribute('download')||(link.target&&link.target!=='_self'))return;
 const hash=link.getAttribute('href');if(!hash||hash==='#')return;
 let id;try{id=decodeURIComponent(hash.slice(1));}catch(_){return;}
 const target=document.getElementById(id),scope=document.querySelector('.rg-feedback');
 if(!target||!scope.contains(target)||!target.getClientRects().length)return;
 event.preventDefault();
 if(location.hash!==hash)history.pushState(null,'',hash);
 // Preserve keyboard/assistive navigation without causing a second instant jump.
 if(!target.hasAttribute('tabindex'))target.setAttribute('tabindex','-1');
 target.focus({preventScroll:true});
 scrollToEl(target);
});
// Saving is confirmed by the server before showing success or starting confetti.
// Repeat requests recover the same durable entry; no client-side reward decisions.
let sending=false;
function showSaved(message,animate){
 completed=true;document.querySelector('#finish').hidden=true;document.querySelector('#success').hidden=false;
 root.querySelectorAll('input').forEach(x=>x.disabled=true);
 document.querySelector('#saved-note').textContent=message;
 document.querySelector('#progress-note').textContent='All done!';
 if(animate){celebrate();const s=document.querySelector('#success');s.focus({preventScroll:true});scrollToEl(s);}
 announce(message);
}
form.addEventListener('submit',async e=>{
 e.preventDefault();if(completed||sending)return;
 const n=update();
 if(n<8){questions.forEach(q=>{if(!answers[q.id].length){const fs=document.querySelector('#q-'+q.id);fs.classList.add('invalid');fs.setAttribute('aria-invalid','true');document.querySelector('#error-'+q.id).textContent='Pick an answer here.';}});const first=document.querySelector('.question.invalid');document.querySelector('#form-summary').textContent=`${8-n} ${8-n===1?'question needs':'questions need'} a tap. Look for the red line.`;first.querySelector('input').focus({preventScroll:true});scrollToEl(first);announce('Pick an answer for each question. Nothing has been sent.');return;}
 const payload={};questions.forEach(q=>{payload[q.id]=[...root.querySelectorAll(`input[name="${q.id}"]:checked`)].map(x=>'o'+x.value);});
 payload.checkout_part=[...root.querySelectorAll('input[name="checkout_part"]:checked')].map(x=>'o'+x.value);
 sending=true;const button=form.querySelector('.send');button.disabled=true;form.setAttribute('aria-busy','true');document.querySelector('#form-summary').textContent='Saving your feedback…';
 try{
  const res=await fetch(RGFS.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':RGFS.nonce},body:JSON.stringify({form:RGFS.form.slug,version:RGFS.form.version,schema_hash:RGFS.form.hash,started:RGFS.started,token:RGFS.token,order_id:RGFS.order_id,campaign:RGFS.campaign,answers:payload,device:innerWidth<=600?'phone':innerWidth<=1000?'tablet':'desktop',website:form.elements.website.value})});
  const data=await res.json();if(!res.ok||!data.id)throw new Error(data.message||'We could not confirm the save. Please try again.');
  document.querySelector('#form-summary').textContent='';showSaved(data.message,!data.already_saved);
 }catch(err){document.querySelector('#form-summary').textContent=err.message||'Please check your connection and try again. Your choices are still here.';announce(document.querySelector('#form-summary').textContent);}
 finally{sending=false;button.disabled=false;form.removeAttribute('aria-busy');}
});
let confettiCanvas=null,confettiFrame=0,confettiTimer=0;
function clearCelebration(){cancelAnimationFrame(confettiFrame);clearTimeout(confettiTimer);if(confettiCanvas)confettiCanvas.remove();confettiCanvas=null;confettiFrame=0;}
function celebrate(){
 clearCelebration();const canvas=document.createElement('canvas');canvas.className='completion-confetti';canvas.setAttribute('aria-hidden','true');document.querySelector('.rg-feedback').append(canvas);confettiCanvas=canvas;
 const w=window.innerWidth,h=window.innerHeight,dpr=Math.min(window.devicePixelRatio||1,1.5);canvas.width=Math.ceil(w*dpr);canvas.height=Math.ceil(h*dpr);canvas.style.width=w+'px';canvas.style.height=h+'px';const ctx=canvas.getContext('2d');if(!ctx){clearCelebration();return;}ctx.scale(dpr,dpr);
 const colours=['#f36c4e','#72383d','#af7fe8','#c4df46','#55bb9b','#4f90d9','#f6bc45','#e66a9e'];
 const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
 const pieces=Array.from({length:reduced?28:100},(_,i)=>({x:Math.random()*w,y:reduced?Math.random()*h:Math.random()*h*.55-h*.45,vx:(Math.random()-.5)*2.5,vy:2.1+Math.random()*2.8,a:Math.random()*Math.PI,spin:(Math.random()-.5)*.12,colour:colours[i%colours.length],size:5+Math.random()*4}));
 function draw(step){ctx.clearRect(0,0,w,h);for(const p of pieces){if(step){p.x+=p.vx*step;p.y+=p.vy*step;p.a+=p.spin*step;}ctx.save();ctx.translate(p.x,p.y);ctx.rotate(p.a);ctx.fillStyle=p.colour;ctx.fillRect(-p.size/2,-p.size/4,p.size,p.size*.5);ctx.restore();}}
 if(reduced){draw(0);confettiTimer=setTimeout(clearCelebration,1300);return;}
 let start=0,last=0;function frame(t){if(!confettiCanvas||document.hidden){clearCelebration();return;}if(!start)start=t;const delta=last?Math.min((t-last)/16.67,2):1;last=t;draw(delta);if(t-start<2200)confettiFrame=requestAnimationFrame(frame);else clearCelebration();}confettiFrame=requestAnimationFrame(frame);
}
document.addEventListener('visibilitychange',()=>{if(document.hidden)clearCelebration();});


const feedbackRoot=document.querySelector('.rg-feedback'),tracker=document.querySelector('.survey-tracker');
function syncHeaderClearance(){
 const header=document.querySelector('#rghc-header'),admin=document.querySelector('#wpadminbar');
 const fallback=matchMedia('(max-width:500px)').matches?66:74;
 const headerHeight=header?header.getBoundingClientRect().height:fallback;
 const adminHeight=admin?admin.getBoundingClientRect().height:0;
 feedbackRoot.style.setProperty('--feedback-clearance',Math.ceil(Math.max(headerHeight,fallback)+adminHeight+18)+'px');
}
// The question stack defines sticky travel. Receipt/submit/privacy/footer height
// never extends it. ResizeObserver covers conditional questions and font reflow.
const boundary=document.querySelector('.tracker-boundary');
function syncQuestionBoundary(){
 const questionsBottom=root.getBoundingClientRect().bottom;
 const start=document.querySelector('.survey-layout').getBoundingClientRect().top;
 boundary.style.setProperty('--question-boundary',Math.max(0,Math.ceil(questionsBottom-start))+'px');
}
syncQuestionBoundary();window.addEventListener('resize',syncQuestionBoundary,{passive:true});
if('ResizeObserver' in window){new ResizeObserver(syncQuestionBoundary).observe(root);}
if(document.fonts?.ready)document.fonts.ready.then(syncQuestionBoundary);
syncHeaderClearance();window.addEventListener('resize',syncHeaderClearance,{passive:true});
if('ResizeObserver' in window){const headerObserver=new ResizeObserver(syncHeaderClearance);for(const selector of ['#rghc-header','#wpadminbar']){const el=document.querySelector(selector);if(el)headerObserver.observe(el);}}
if('IntersectionObserver' in window){const railObserver=new IntersectionObserver(entries=>{tracker.classList.toggle('in-survey',entries[0].isIntersecting);},{rootMargin:'-100px 0px -24px 0px'});railObserver.observe(root);}else tracker.classList.add('in-survey');


if(RGFS.existing){
 for(const [qid,opts] of Object.entries(RGFS.existing.answers||{}))for(const key of opts){
  const input=root.querySelector('input[name="'+qid+'"][value="'+key.slice(1)+'"]');if(input)input.checked=true;
 }
 update();showSaved(RGFS.existing.message,false);
}
})();
