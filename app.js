/* ============================================================
   Filipino Cookbook Client — consumes the API built by Rillera
   Repo: https://github.com/exilleon/filipino-cookbook-api-rillera
   ============================================================ */

const el = (id) => document.getElementById(id);

const state = {
  apiBase: el('apiBase').value.trim(),
  token: el('apiToken').value.trim(),
  foods: [],
  categories: [],
  origins: [],
  ingredients: [],
  activeCategory: 'all',
  activeOrigin: 'all',
  searchTerm: '',
};

function authHeaders() {
  return {
    'Authorization': `Bearer ${state.token}`,
    'Accept': 'application/json',
  };
}

async function apiGet(path) {
  const res = await fetch(`${state.apiBase}${path}`, { headers: authHeaders() });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(body.message || `Request failed (${res.status})`);
  }
  const json = await res.json();
  return json.data ?? json;
}

/* ---------------- Loading skeleton ---------------- */
function renderSkeleton(count = 6) {
  const grid = el('grid');
  grid.innerHTML = '';
  for (let i = 0; i < count; i++) {
    const card = document.createElement('div');
    card.className = 'card card--skeleton';
    card.innerHTML = `
      <div class="sk" style="width:60px;height:10px;margin-bottom:8px;"></div>
      <div class="sk" style="width:80%;height:20px;margin-bottom:10px;"></div>
      <div class="sk" style="width:50px;height:16px;"></div>`;
    grid.appendChild(card);
  }
}

/* ---------------- Connection ---------------- */
async function connect() {
  state.apiBase = el('apiBase').value.trim().replace(/\/$/, '');
  state.token = el('apiToken').value.trim();
  const status = el('connStatus');
  status.textContent = 'connecting…';
  status.className = 'conn-status';
  renderSkeleton();
  el('statusLine').textContent = 'Fetching the menu board…';

  try {
    const [foods, categories, origins, ingredients] = await Promise.all([
      apiGet('/foods'),
      apiGet('/categories'),
      apiGet('/origins'),
      apiGet('/ingredients'),
    ]);
    state.foods = Array.isArray(foods) ? foods : [];
    state.categories = Array.isArray(categories) ? categories : [];
    state.origins = Array.isArray(origins) ? origins : [];
    state.ingredients = Array.isArray(ingredients) ? ingredients : [];

    status.textContent = 'connected';
    status.className = 'conn-status ok';

    buildChipRow(el('chipRow'), state.categories, 'category', (label) => `#${label}`);
    buildChipRow(el('originRow'), state.origins, 'origin', (label) => `📍 ${label}`, true);
    renderGrid();
  } catch (err) {
    status.textContent = 'connection failed';
    status.className = 'conn-status err';
    el('statusLine').textContent = `Couldn't reach the API — ${err.message}. Check the connection settings above.`;
    el('grid').innerHTML = '';
  }
}

/* ---------------- Chip filters ---------------- */
function labelOf(item) {
  return item.category_name || item.origin_name || item.name || String(item.category_id ?? item.origin_id ?? item.id ?? '');
}

function buildChipRow(container, items, kind, formatter, isOrigin = false) {
  const allBtn = container.querySelector('[data-category], [data-origin]');
  container.innerHTML = '';

  const all = document.createElement('button');
  all.className = 'chip chip--active';
  all.textContent = isOrigin ? 'All origins' : 'All dishes';
  all.addEventListener('click', () => setFilter(kind, 'all', container));
  container.appendChild(all);

  const seen = new Set();
  items.forEach((item) => {
    const label = labelOf(item);
    if (seen.has(label)) return;
    seen.add(label);
    const chip = document.createElement('button');
    chip.className = 'chip';
    chip.textContent = formatter(label);
    chip.addEventListener('click', () => setFilter(kind, label, container));
    container.appendChild(chip);
  });
}

function setFilter(kind, value, container) {
  if (kind === 'category') state.activeCategory = value;
  else state.activeOrigin = value;

  [...container.children].forEach((c) => c.classList.remove('chip--active'));
  const idx = value === 'all' ? 0 : [...container.children].findIndex((c) => c.textContent.includes(value));
  if (idx >= 0) container.children[idx].classList.add('chip--active');

  renderGrid();
}

/* ---------------- Search ---------------- */
async function runSearch() {
  const term = el('searchInput').value.trim();
  state.searchTerm = term;
  if (!term) { renderGrid(); return; }

  el('statusLine').textContent = `Searching for “${term}”…`;
  try {
    const results = await apiGet(`/foods/search/${encodeURIComponent(term)}`);
    state.foods = Array.isArray(results) ? results : (results ? [results] : []);
    state.activeCategory = 'all';
    state.activeOrigin = 'all';
    renderGrid(true);
  } catch (err) {
    el('statusLine').textContent = `Search failed — ${err.message}`;
  }
}

/* ---------------- Grid rendering ---------------- */
function matchesFilters(food) {
  const cat = food.category_name;
  const org = food.origin_name;
  const catOk = state.activeCategory === 'all' || cat === state.activeCategory;
  const orgOk = state.activeOrigin === 'all' || org === state.activeOrigin;
  return catOk && orgOk;
}

function renderGrid(skipFilters = false) {
  const grid = el('grid');
  const empty = el('emptyState');
  const list = skipFilters ? state.foods : state.foods.filter(matchesFilters);

  grid.innerHTML = '';
  if (list.length === 0) {
    empty.hidden = false;
    el('statusLine').textContent = 'No dishes loaded yet.';
    return;
  }
  empty.hidden = true;
  el('statusLine').textContent = `${list.length} dish${list.length === 1 ? '' : 'es'} on the board`;

  list.forEach((food) => {
    const card = document.createElement('div');
    card.className = 'card';
    const origin = food.origin_name ?? '—';
    const category = food.category_name ?? '—';
    card.innerHTML = `
      <div class="card__origin">${escapeHtml(origin)}</div>
      <div class="card__name">${escapeHtml(food.food_name ?? 'Unnamed dish')}</div>
      <span class="card__category">${escapeHtml(category)}</span>
    `;
    card.addEventListener('click', () => openDrawer(food));
    grid.appendChild(card);
  });
}

/* ---------------- Detail drawer ---------------- */
async function openDrawer(food) {
  const drawer = el('drawer');
  const body = el('drawerBody');
  drawer.hidden = false;
  body.innerHTML = `<p class="drawer__section-title">Loading…</p>`;

  try {
    const detail = await apiGet(`/foods/${food.food_id}`);
    const d = detail && detail.food_id ? detail : food;
    const ingredients = d.ingredients ?? [];

    body.innerHTML = `
      <div class="drawer__origin">${escapeHtml(d.origin_name ?? '—')}</div>
      <div class="drawer__name">${escapeHtml(d.food_name ?? 'Unnamed dish')}</div>
      <div class="drawer__tags">
        <span class="drawer__tag">${escapeHtml(d.category_name ?? '—')}</span>
      </div>
      ${d.instructions ? `<p style="font-size:13.5px;color:var(--chalk-dim);line-height:1.5;">${escapeHtml(d.instructions)}</p>` : ''}
      <div class="drawer__section-title">Ingredients</div>
      <ul class="drawer__ingredients">
        ${
          Array.isArray(ingredients) && ingredients.length
            ? ingredients.map((i) => `<li>${escapeHtml(typeof i === 'string' ? i : (i.ingredient_name ?? JSON.stringify(i)))}</li>`).join('')
            : '<li>Not listed by the API for this dish.</li>'
        }
      </ul>
      <button id="deleteDishBtn" class="pill-btn" style="margin-top:16px;border-color:var(--annatto);color:var(--annatto-lt);">🗑 Delete this dish</button>
      <p id="deleteStatus" class="form-status"></p>
    `;
    el('deleteDishBtn').addEventListener('click', () => deleteDish(d.food_id, d.food_name));
  } catch (err) {
    body.innerHTML = `<p class="drawer__section-title">Couldn't load details</p><p style="font-size:13px;color:var(--muted);">${escapeHtml(err.message)}</p>`;
  }
}

function closeDrawer() {
  el('drawer').hidden = true;
}

/* ---------------- Add dish (POST) ---------------- */
function openAddDishModal() {
  const catSel = el('fCategory');
  const orgSel = el('fOrigin');
  catSel.innerHTML = state.categories.map(c => `<option value="${c.category_id}">${escapeHtml(c.category_name)}</option>`).join('');
  orgSel.innerHTML = state.origins.map(o => `<option value="${o.origin_id}">${escapeHtml(o.origin_name)}</option>`).join('');
  el('addDishStatus').textContent = '';
  el('addDishForm').reset();
  el('addDishModal').hidden = false;
}

async function submitAddDish(e) {
  e.preventDefault();
  const status = el('addDishStatus');
  status.textContent = 'Submitting…';
  status.className = 'form-status';

  const ingredientNames = el('fIngredients').value.split(',').map(s => s.trim()).filter(Boolean);
  const ingredient_ids = ingredientNames
    .map(name => state.ingredients.find(i => i.ingredient_name?.toLowerCase() === name.toLowerCase()))
    .filter(Boolean)
    .map(i => i.ingredient_id);

  const payload = {
    food_name: el('fName').value.trim(),
    category_id: el('fCategory').value,
    origin_id: el('fOrigin').value,
    instructions: el('fInstructions').value.trim(),
    ingredient_ids,
  };

  try {
    const res = await fetch(`${state.apiBase}/foods`, {
      method: 'POST',
      headers: { ...authHeaders(), 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || `Request failed (${res.status})`);

    status.textContent = '✓ Added! Refreshing the board…';
    status.className = 'form-status ok';
    setTimeout(async () => {
      el('addDishModal').hidden = true;
      await connect();
    }, 900);
  } catch (err) {
    status.textContent = `✕ ${err.message}`;
    status.className = 'form-status err';
  }
}

/* ---------------- Delete dish ---------------- */
async function deleteDish(foodId, foodName) {
  const status = el('deleteStatus');
  if (!confirm(`Delete "${foodName}"? This calls DELETE /api/foods/${foodId} and cannot be undone.`)) return;

  status.textContent = 'Deleting…';
  status.className = 'form-status';
  try {
    const res = await fetch(`${state.apiBase}/foods/${foodId}`, {
      method: 'DELETE',
      headers: authHeaders(),
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || `Request failed (${res.status})`);

    status.textContent = '✓ Deleted. Refreshing the board…';
    status.className = 'form-status ok';
    setTimeout(async () => {
      closeDrawer();
      await connect();
    }, 700);
  } catch (err) {
    status.textContent = `✕ ${err.message}`;
    status.className = 'form-status err';
  }
}

/* ---------------- Utilities ---------------- */
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/* ---------------- Event wiring ---------------- */
el('reconnectBtn').addEventListener('click', connect);
el('searchBtn').addEventListener('click', runSearch);
el('searchInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') runSearch(); });
el('drawerClose').addEventListener('click', closeDrawer);
el('drawer').addEventListener('click', (e) => { if (e.target.id === 'drawer') closeDrawer(); });
el('addDishBtn').addEventListener('click', openAddDishModal);
el('addDishClose').addEventListener('click', () => { el('addDishModal').hidden = true; });
el('addDishModal').addEventListener('click', (e) => { if (e.target.id === 'addDishModal') el('addDishModal').hidden = true; });
el('addDishForm').addEventListener('submit', submitAddDish);
el('clearFilters').addEventListener('click', () => {
  state.activeCategory = 'all';
  state.activeOrigin = 'all';
  state.searchTerm = '';
  el('searchInput').value = '';
  document.querySelectorAll('.chip').forEach((c, i) => c.classList.toggle('chip--active', i === 0));
  connect();
});
el('settingsToggle').addEventListener('click', () => {
  const panel = el('settingsPanel');
  const willShow = panel.hidden;
  panel.hidden = !willShow;
  el('settingsToggle').setAttribute('aria-expanded', String(willShow));
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeDrawer(); el('addDishModal').hidden = true; } });

/* ---------------- Boot ---------------- */
connect();
