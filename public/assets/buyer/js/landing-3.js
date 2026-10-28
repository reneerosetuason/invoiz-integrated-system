(function(){
  function tick(){
    var el = document.getElementById('deal-countdown');
    if(!el) return;
    var now = new Date(), end = new Date(now); end.setHours(23,59,59,999);
    var s = Math.max(0, Math.floor((end - now) / 1000));
    var h = String(Math.floor(s/3600)).padStart(2,'0'), m = String(Math.floor(s%3600/60)).padStart(2,'0'), ss = String(s%60).padStart(2,'0');
    el.textContent = h + ':' + m + ':' + ss;
  }
  tick(); setInterval(tick, 1000);
})();
