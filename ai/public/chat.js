async function loadConversations(){
  const r = await api('chat/list');
  const list = document.getElementById('conversation-list');
  list.innerHTML='';
  (r.data||[]).forEach(c=>{
    const div=document.createElement('div');
    div.textContent = c.id + ' - ' + (c.meta||'');
    div.onclick=()=>loadConversation(c.id);
    list.appendChild(div);
  });
}

async function loadConversation(id){
  const r = await api('chat/conversation?id='+id);
  const msgs=document.getElementById('messages');
  msgs.innerHTML='';
  (r.data||[]).forEach(m=>{
    const div=document.createElement('div');
    div.className='msg '+m.role;
    div.textContent=m.role+': '+m.content;
    msgs.appendChild(div);
  });
  window.currentConversation=id;
}

document.getElementById('send-btn').addEventListener('click',sendMessage);

async function sendMessage(){
  const content=document.getElementById('chat-input').value;
  if(!content) return;
  const r=await api('chat/send','POST',{conversation_id:window.currentConversation,content});
  document.getElementById('chat-input').value='';
  if(r.ok){
    loadConversation(r.data.conversation_id);
  }
}
