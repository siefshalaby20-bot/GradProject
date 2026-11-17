(function(){
  var links=[].slice.call(document.querySelectorAll('nav.cats a')),
      secs=[].slice.call(document.querySelectorAll('main section')),t=0,cur=null;
  if(!links.length)return;
  function update(){
    t=0;var y=80,id=secs[0].id;
    secs.forEach(function(s){if(s.getBoundingClientRect().top<=y)id=s.id});
    if(innerHeight+scrollY>=document.documentElement.scrollHeight-4)id=secs[secs.length-1].id;
    if(id===cur)return;cur=id;
    links.forEach(function(a){var on=a.getAttribute('href')==='#'+id;a.classList.toggle('active',on);
      if(on){a.setAttribute('aria-current','true');var u=a.parentNode.parentNode;u.scrollTo({left:a.offsetLeft-16,behavior:'smooth'})}else a.removeAttribute('aria-current')});
  }
  addEventListener('scroll',function(){if(!t)t=requestAnimationFrame(update)},{passive:true});
  update();
})();
