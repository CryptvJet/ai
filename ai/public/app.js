// Basic router and helpers
const tabs = document.querySelectorAll('#tabs button');
const sections = document.querySelectorAll('main .tab');

tabs.forEach(btn => btn.addEventListener('click', () => showTab(btn.dataset.tab)));

function showTab(name) {
  tabs.forEach(b => b.classList.toggle('active', b.dataset.tab === name));
  sections.forEach(s => s.classList.toggle('active', s.id === name));
}

async function api(path, method='GET', data) {
  const opts = {method, headers:{'Content-Type':'application/json'}};
  if(data) opts.body = JSON.stringify(data);
  const res = await fetch('/ai/api/'+path, opts);
  return res.json();
}

async function checkAuth() {
  const r = await api('auth/me');
  if(r.ok) {
    document.getElementById('admin-tab').classList.remove('hidden');
  }
}

checkAuth();

// auto refresh for analytics
let autoTimer;
document.getElementById('auto-refresh').addEventListener('change',e=>{
  clearInterval(autoTimer);
  if(e.target.checked) autoTimer=setInterval(()=>loadOverview(),300000);
});

document.getElementById('refresh-btn').addEventListener('click',()=>loadOverview());

document.addEventListener('DOMContentLoaded',()=>{
  loadConversations();
  loadOverview();
});
