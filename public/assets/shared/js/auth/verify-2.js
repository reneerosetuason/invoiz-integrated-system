(function(){
  var btn = document.getElementById('resendBtn'), tag = document.getElementById('resendTimer'), s = 60;
  btn.disabled = true; btn.classList.add('opacity-40');
  var t = setInterval(function(){
    s--;
    if(s <= 0){ clearInterval(t); btn.disabled = false; btn.classList.remove('opacity-40'); tag.textContent = ''; }
    else { tag.textContent = '(' + s + 's)'; }
  }, 1000);
  var otp = document.getElementById('otp');
  otp.addEventListener('input', function(){ otp.value = otp.value.replace(/\D/g,'').slice(0,6); });
})();
