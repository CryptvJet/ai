const adminContent=document.getElementById('admin-content');

async function loadAdmin(){
  adminContent.innerHTML=`<h2>Canned Responses</h2>
  <div id="cr-list"></div>
  <h3>Add / Edit</h3>
  <input id="cr-title" placeholder="title"><br>
  <input id="cr-tags" placeholder="tags"><br>
  <textarea id="cr-body" placeholder="body"></textarea><br>
  <button onclick="saveCR()">Save</button>`;
  loadCR();
}

async function loadCR(){
  const r=await api('admin/canned-responses');
  const div=document.getElementById('cr-list');
  div.innerHTML='';
  (r.data||[]).forEach(cr=>{
    const row=document.createElement('div');
    row.textContent=`${cr.id} ${cr.title} [${cr.tags}]`;
    const del=document.createElement('button');
    del.textContent='X';
    del.onclick=()=>deleteCR(cr.id);
    row.appendChild(del);
    div.appendChild(row);
  });
}

async function saveCR(){
  const payload={title:document.getElementById('cr-title').value,tags:document.getElementById('cr-tags').value,body:document.getElementById('cr-body').value,mode:'create'};
  await api('admin/canned-responses','POST',payload);
  loadCR();
}

async function deleteCR(id){
  await api('admin/canned-responses','POST',{id,mode:'delete'});
  loadCR();
}

document.getElementById('admin-tab').addEventListener('click',loadAdmin);
