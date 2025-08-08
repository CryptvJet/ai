async function loadOverview(){
  const range=document.getElementById('range-select').value;
  const r=await api('stats/overview?range='+range);
  const cards=document.getElementById('stats-cards');
  cards.innerHTML='';
  if(!r.ok) return;
  const data=r.data;
  ['novas','finished_novas','climax_groups','avg_time_to_climax_sec'].forEach(k=>{
    const div=document.createElement('div');
    div.className='card';
    div.textContent=k+': '+data[k];
    cards.appendChild(div);
  });
  loadTimeseries();
}

async function loadTimeseries(){
  const range=document.getElementById('range-select').value;
  const metric=document.getElementById('metric-select').value;
  const r=await api('stats/timeseries?metric='+metric+'&range='+range);
  const canvas=document.getElementById('chart');
  const ctx=canvas.getContext('2d');
  ctx.clearRect(0,0,canvas.width,canvas.height);
  if(!r.ok) return;
  const data=r.data||[];
  const max=Math.max(...data.map(d=>d.value),1);
  const step=canvas.width/data.length;
  ctx.beginPath();
  data.forEach((d,i)=>{
    const x=i*step;
    const y=canvas.height-(d.value/max*canvas.height);
    if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
  });
  ctx.strokeStyle='#6B2FA1';
  ctx.stroke();
}
