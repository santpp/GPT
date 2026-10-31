// Claro, oscuro o el del sistema. Se aplica antes de pintar nada, para que la
// app no parpadee en blanco al abrirse de noche.
(function(){
  var KEY='agora-theme',media=window.matchMedia?matchMedia('(prefers-color-scheme: dark)'):null;
  function stored(){try{var v=localStorage.getItem(KEY);return v==='light'||v==='dark'?v:'auto'}catch(e){return 'auto'}}
  function apply(){
    var choice=stored(),dark=choice==='dark'||(choice==='auto'&&!!(media&&media.matches));
    document.documentElement.dataset.theme=dark?'dark':'light';
    var meta=document.querySelector('meta[name="theme-color"]');if(meta)meta.setAttribute('content',dark?'#12151f':'#282e49');
  }
  apply();
  if(media&&media.addEventListener)media.addEventListener('change',apply);
  window.agoraTheme={get:stored,set:function(v){try{v==='auto'?localStorage.removeItem(KEY):localStorage.setItem(KEY,v)}catch(e){}apply()}};
  document.addEventListener('DOMContentLoaded',apply);
})();
