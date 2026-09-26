import {ApiClient,url} from './api-client.js';
export function alertMessage(message,type='error') {const box=document.getElementById('alert-container');if(box){box.textContent=message;box.className=`alert alert-${type}`;box.setAttribute('role','status');box.setAttribute('aria-live','polite');}}
export function navigate(path){if(typeof path!=='string'||!path.startsWith('/')||path.startsWith('//')||path.includes('\\'))throw new Error('Destino inválido.');const target=new URL(path,location.origin);if(target.origin!==location.origin)throw new Error('Destino inválido.');location.assign(target.href);}
document.getElementById('logout')?.addEventListener('click',async e=>{e.target.disabled=true;const r=await ApiClient.request('/api/logout',{method:'POST',body:{}});if(r.ok){sessionStorage.removeItem('ada.challenge');location.assign(url('/'));}else{e.target.disabled=false;alertMessage(r.message);}});
const page=document.querySelector('main')?.dataset.page;
try {
 if(['auth-select','auth-verify','recovery'].includes(page)) await (await import('./pages-auth.js')).init(page);
 if(page?.startsWith('admin-')) await (await import('./pages-admin.js')).init(page);
 if(page==='dashboard'){const session=await ApiClient.getSession();if(!session.authenticated)location.replace(url('/'));}
}catch(e){alertMessage(e.message);}
