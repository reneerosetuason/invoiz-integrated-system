(function(){
  var box = document.getElementById('chatSearch');
  if(!box) return;
  box.addEventListener('input', function(){
    var q = box.value.toLowerCase().trim();
    document.querySelectorAll('#convList > a').forEach(function(a){
      a.style.display = (!q || (a.getAttribute('data-search') || '').indexOf(q) !== -1) ? '' : 'none';
    });
  });
})();
