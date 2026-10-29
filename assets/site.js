'use strict';
const toggle=document.querySelector('.menu-toggle');const nav=document.querySelector('.main-nav');
function menu(open){nav.classList.toggle('is-open',open);toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Close menu':'Open menu');}
toggle?.addEventListener('click',()=>menu(toggle.getAttribute('aria-expanded')!=='true'));
nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>menu(false)));
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&toggle.getAttribute('aria-expanded')==='true'){menu(false);toggle.focus();}});
