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
  // Texto grande: letra más grande y en negrita. Se guarda en este dispositivo y se aplica antes de pintar.
  var TEXT='agora-text';
  function textGet(){try{return localStorage.getItem(TEXT)==='large'?'large':'normal'}catch(e){return 'normal'}}
  function textApply(){if(textGet()==='large')document.documentElement.dataset.text='large';else delete document.documentElement.dataset.text}
  textApply();
  window.agoraText={get:textGet,set:function(v){try{v==='large'?localStorage.setItem(TEXT,'large'):localStorage.removeItem(TEXT)}catch(e){}textApply()}};
  document.addEventListener('DOMContentLoaded',apply);
})();
