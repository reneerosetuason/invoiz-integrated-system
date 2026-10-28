(function(){
                var box = document.getElementById('threadSearch');
                if(!box) return;
                box.addEventListener('input', function(){
                    var q = box.value.toLowerCase().trim();
                    document.querySelectorAll('#threadList > a').forEach(function(a){
                        a.style.display = (!q || (a.getAttribute('data-search') || '').indexOf(q) !== -1) ? '' : 'none';
                    });
                });
            })();
