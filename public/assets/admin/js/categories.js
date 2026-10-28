var invoizData_6730a9eb7a = JSON.parse(document.currentScript.dataset.config);

  (function(){
    const cats = invoizData_6730a9eb7a[0];
    const input = document.getElementById('parentSearch');
    const hidden = document.getElementById('parentId');
    const dropdown = document.getElementById('parentDropdown');
    const selected = document.getElementById('parentSelected');
    const selectedName = document.getElementById('parentSelectedName');
    let activeOption = -1;
    let visibleCats = [];

    function setExpanded(expanded){
      input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      if(!expanded) input.removeAttribute('aria-activedescendant');
    }

    function setActive(index){
      const options = dropdown.querySelectorAll('[role="option"]');
      if(!options.length){ activeOption = -1; input.removeAttribute('aria-activedescendant'); return; }
      activeOption = Math.max(0, Math.min(index, options.length - 1));
      options.forEach((option, i)=>{
        const active = i === activeOption;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      input.setAttribute('aria-activedescendant', options[activeOption].id);
      options[activeOption].scrollIntoView({block:'nearest'});
    }

    function render(list){
      visibleCats = list;
      activeOption = -1;
      dropdown.innerHTML = '';
      input.removeAttribute('aria-activedescendant');
      if(list.length === 0){
        const empty = document.createElement('div');
        empty.className = 'parent-empty';
        empty.textContent = 'No categories found';
        empty.setAttribute('aria-disabled','true');
        dropdown.appendChild(empty);
      } else {
        list.forEach((c,index)=>{
          const option = document.createElement('div');
          option.id = 'parent-option-' + c.id;
          option.className = 'parent-option';
          option.setAttribute('role','option');
          option.setAttribute('aria-selected','false');
          option.textContent = c.name;
          option.addEventListener('mouseenter', ()=> setActive(index));
          option.addEventListener('mousedown', (event)=> event.preventDefault());
          option.addEventListener('click', ()=> selectCat(c));
          dropdown.appendChild(option);
        });
      }
      dropdown.style.display = 'block';
      setExpanded(true);
    }

    function selectCat(c){
      hidden.value = c.id;
      selectedName.textContent = c.name;
      selected.style.display = 'flex';
      input.value = '';
      dropdown.style.display = 'none';
      setExpanded(false);
    }

    window.clearParent = function(){
      hidden.value = '';
      selected.style.display = 'none';
      input.value = '';
      input.focus();
    };

    input.addEventListener('focus', ()=> render(cats));
    input.addEventListener('input', ()=>{
      const q = input.value.toLowerCase().trim();
      const filtered = q ? cats.filter(c=> c.name.toLowerCase().includes(q)) : cats;
      render(filtered);
    });
    input.addEventListener('keydown', (event)=>{
      if(event.key === 'ArrowDown' || event.key === 'ArrowUp'){
        event.preventDefault();
        if(dropdown.style.display === 'none'){
          const q = input.value.toLowerCase().trim();
          render(q ? cats.filter(c=>c.name.toLowerCase().includes(q)) : cats);
        }
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        setActive(activeOption < 0 ? (direction > 0 ? 0 : visibleCats.length - 1) : activeOption + direction);
      } else if(event.key === 'Enter' && activeOption >= 0 && visibleCats[activeOption]){
        event.preventDefault();
        selectCat(visibleCats[activeOption]);
      } else if(event.key === 'Escape' && dropdown.style.display !== 'none'){
        event.preventDefault();
        dropdown.style.display = 'none';
        setExpanded(false);
      }
    });
    document.addEventListener('click', (e)=>{
      if(!e.target.closest('#parentSearch') && !e.target.closest('#parentDropdown') && !e.target.closest('#parentSelected')){
        dropdown.style.display = 'none';
        setExpanded(false);
      }
    });
  })();
