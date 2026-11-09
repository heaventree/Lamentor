// Lamentor visual editor. No build step: plain ES modules.
//
// - The left panel is generated from the control schemas dumped out of Elementor's own widget classes.
// - The canvas is an iframe showing HTML/CSS rendered by the server (POST /api/render), so what you
//   edit is exactly what the Laravel renderer outputs.
// - The document is an Elementor-format element tree; everything is an edit of that JSON.

import { h, renderControl, STRUCTURAL, NO_LABEL, clone, isObj, debounce } from './controls.js';

const cfg = window.LAMENTOR;
const root = document.getElementById('lm-app');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const assets = `${cfg.assetsUrl}/assets`;
const DEVICES = { desktop: { label: 'Desktop', icon: '🖥', width: '100%' }, tablet: { label: 'Tablet', icon: '▭', width: '768px' }, mobile: { label: 'Mobile', icon: '▯', width: '375px' } };

const S = {
    tree: clone(cfg.document.data) || [],
    title: cfg.document.title || '',
    selected: null,
    device: 'desktop',
    widgets: [],
    schemas: new Map(),
    kit: {},
    undo: [],
    redo: [],
    dirty: false,
    saving: false,
    leftTab: 'add',
    settingsTab: null,
    openSections: {},
    innerTabs: {},
    collapsed: new Set(),
    lastKey: null,
    lastAt: 0,
    renderToken: 0,
    status: '',
    frameReady: false,
};

// ---------------------------------------------------------------- http

async function http(method, path, body, isForm = false) {
    const res = await fetch(cfg.api + path, {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}) },
        body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
    });
    const text = await res.text();
    let json = null;
    try { json = text ? JSON.parse(text) : null; } catch { /* not JSON */ }
    if (!res.ok) throw new Error(json?.message || json?.errors?.file?.[0] || `Request failed (${res.status})`);
    return json;
}

const upload = (file) => {
    const form = new FormData();
    form.append('file', file);
    return http('POST', '/media', form, true).catch((e) => { throw new Error(e.message.includes('422') ? 'File type not allowed' : e.message); });
};

// ---------------------------------------------------------------- tree helpers

const newId = () => Math.random().toString(16).slice(2, 9);
const typeOf = (node) => (node.elType === 'widget' ? node.widgetType : node.elType);
const isContainer = (node) => ['container', 'section', 'column'].includes(node.elType);
const acceptsChildren = (node) => node.elType === 'container' || node.elType === 'column';

function find(id, list = S.tree, parent = null) {
    for (let i = 0; i < list.length; i++) {
        if (list[i].id === id) return { node: list[i], list, index: i, parent };
        const hit = find(id, list[i].elements || [], list[i]);
        if (hit) return hit;
    }
    return null;
}

function withNewIds(node) {
    const copy = clone(node);
    (function walk(n) { n.id = newId(); (n.elements || []).forEach(walk); })(copy);
    return copy;
}

function isDescendant(ancestor, id) {
    return !!find(id, ancestor.elements || []);
}

const widgetMeta = (name) => S.widgets.find((w) => w.name === name);
const labelOf = (node) => (node.elType === 'widget' ? (widgetMeta(node.widgetType)?.title || node.widgetType) : ({ container: 'Container', section: 'Section', column: 'Column' }[node.elType] || node.elType));
const iconOf = (node) => (node.elType === 'widget' ? (widgetMeta(node.widgetType)?.icon || 'eicon-apps') : ({ container: 'eicon-container', section: 'eicon-section', column: 'eicon-column' }[node.elType] || 'eicon-apps'));

// ---------------------------------------------------------------- schema / settings

async function loadSchema(type) {
    if (!S.schemas.has(type)) S.schemas.set(type, http('GET', `/schema/controls/${encodeURIComponent(type)}`));
    return S.schemas.get(type);
}

/** Saved settings merged over defaults, array values key by key (as the PHP side does). */
function effective(node, schema) {
    const out = {};
    for (const [name, def] of Object.entries(schema.defaults || {})) {
        const saved = node.settings?.[name];
        out[name] = saved === undefined ? clone(def) : (isObj(def) && isObj(saved) && Object.keys(def).length ? { ...clone(def), ...saved } : saved);
    }
    return { ...out, ...Object.fromEntries(Object.entries(node.settings || {}).filter(([k]) => !(k in out))) };
}

const sameValue = (a, b) => JSON.stringify(a ?? '') === JSON.stringify(b ?? '');

function setSetting(node, schema, name, value) {
    node.settings = node.settings || {};
    const def = schema.defaults?.[name];
    const empty = value === '' || value === null || value === undefined || (Array.isArray(value) && value.length === 0 && !(def && def.length));
    if (empty || sameValue(value, def)) delete node.settings[name];
    else node.settings[name] = value;
}

/** Port of Elementor's Controls_Stack::is_control_visible. */
function visible(control, values, controls) {
    if (control.conditions && !checkConditions(control.conditions, values)) return false;
    if (!control.condition) return true;
    for (const [key, expected] of Object.entries(control.condition)) {
        const m = key.match(/([a-z_\-0-9]+)(?:\[([a-z_]+)])?(!?)$/i);
        if (!m) continue;
        const [, pure, sub, bang] = m;
        if (values[pure] === undefined || values[pure] === null) return false;
        const respBoth = control.responsive && controls[pure]?.responsive;
        const suffix = respBoth ? deviceSuffix(control) : '';
        let actual = respBoth ? (values[pure + suffix] ?? values[pure]) : values[pure];
        if (sub && isObj(actual)) { if (actual[sub] === undefined) return false; actual = actual[sub]; }
        if (!actual) {
            let parent = controls[pure + suffix]?.parent;
            while (parent) {
                actual = values[parent];
                if (actual) { if (!isObj(actual)) break; if (sub && actual[sub] !== undefined) { actual = actual[sub]; if (actual !== '') break; } }
                parent = controls[parent]?.parent;
            }
        }
        let contains;
        if (Array.isArray(expected) && expected.length) contains = expected.includes(actual);
        else if (Array.isArray(actual) && actual.length) contains = actual.includes(expected);
        else contains = actual === expected;
        if (bang ? contains : !contains) return false;
    }
    return true;
}

function deviceSuffix(control) {
    const d = control.responsive?.max || control.responsive?.min;
    return !d || d === 'desktop' ? '' : `_${d}`;
}

function checkConditions(c, values) {
    const or = c.relation === 'or';
    for (const term of c.terms || []) {
        let ok;
        if (term.terms) ok = checkConditions(term, values);
        else {
            const m = String(term.name).match(/(\w+)(?:\[(\w+)])?/);
            let v = values[m[1]];
            if (m[2]) v = isObj(v) ? v[m[2]] : undefined;
            const r = term.value;
            ok = ({ '==': () => v == r, '!=': () => v != r, '!==': () => v !== r, in: () => [].concat(r).includes(v), '!in': () => ![].concat(r).includes(v), '<': () => v < r, '<=': () => v <= r, '>': () => v > r, '>=': () => v >= r })[term.operator]?.() ?? (v === r);
        }
        if (or && ok) return true;
        if (!or && !ok) return false;
    }
    return !or;
}

const forDevice = (control, device) => !control.responsive || (control.responsive.max || 'desktop') === device;

// ---------------------------------------------------------------- history & mutation

function mutate(fn, key = null) {
    const before = JSON.stringify(S.tree);
    fn();
    const after = JSON.stringify(S.tree);
    if (before === after) return false;
    const now = Date.now();
    if (!(key && S.lastKey === key && now - S.lastAt < 900)) {
        S.undo.push(before);
        if (S.undo.length > 100) S.undo.shift();
    }
    S.lastKey = key; S.lastAt = now; S.redo = []; S.dirty = true; S.status = '';
    scheduleRender();
    paintTopbar();
    if (S.leftTab === 'navigator') paintLeft();
    return true;
}

function undo() {
    if (!S.undo.length) return;
    S.redo.push(JSON.stringify(S.tree));
    S.tree = JSON.parse(S.undo.pop());
    afterHistory();
}

function redo() {
    if (!S.redo.length) return;
    S.undo.push(JSON.stringify(S.tree));
    S.tree = JSON.parse(S.redo.pop());
    afterHistory();
}

function afterHistory() {
    S.dirty = true; S.lastKey = null;
    if (S.selected && !find(S.selected)) S.selected = null;
    scheduleRender(); paintTopbar(); paintLeft();
}

// ---------------------------------------------------------------- element operations

function defaultNode(kind, parentNode) {
    if (kind === 'container') return { id: newId(), elType: 'container', settings: {}, elements: [], isInner: !!parentNode };
    return { id: newId(), elType: 'widget', widgetType: kind, settings: {}, elements: [] };
}

/** Insert `node` at `target` ({list, index, owner}); root-level widgets get wrapped in a container like Elementor does. */
function place(node, target) {
    let list = target.list;
    let index = target.index;
    if (list === S.tree && node.elType === 'widget') {
        const wrapper = defaultNode('container', null);
        wrapper.elements.push(node);
        node = wrapper;
    }
    if (node.elType === 'container') node.isInner = list !== S.tree;
    list.splice(index, 0, node);
    return node;
}

function addElement(kind, target) {
    let created;
    mutate(() => {
        const node = defaultNode(kind, target.owner || null);
        created = place(node, target);
    });
    if (created) select(kind === 'container' ? created.id : (created.elType === 'widget' ? created.id : created.elements[0].id));
}

function removeElement(id) {
    const hit = find(id);
    if (!hit) return;
    mutate(() => hit.list.splice(hit.index, 1));
    if (S.selected === id) select(null);
}

function duplicateElement(id) {
    const hit = find(id);
    if (!hit) return;
    let copy;
    mutate(() => { copy = withNewIds(hit.node); hit.list.splice(hit.index + 1, 0, copy); });
    if (copy) select(copy.id);
}

function moveElement(id, delta) {
    const hit = find(id);
    if (!hit) return;
    const to = hit.index + delta;
    if (to < 0 || to >= hit.list.length) return;
    mutate(() => { const [n] = hit.list.splice(hit.index, 1); hit.list.splice(to, 0, n); });
}

/** Drop target ({list, index, owner}) from a hovered element and the pointer position. */
function targetFor(el, clientY, clientX, forWidget = true) {
    if (!el) return { list: S.tree, index: S.tree.length, owner: null, mode: 'inside' };
    const hit = find(el.dataset.id);
    if (!hit) return { list: S.tree, index: S.tree.length, owner: null, mode: 'inside' };
    const rect = el.getBoundingClientRect();
    const rel = rect.height ? (clientY - rect.top) / rect.height : 0.5;
    const edge = acceptsChildren(hit.node) ? 0.16 : 0.5;
    if (acceptsChildren(hit.node) && rel >= edge && rel <= 1 - edge) {
        return { list: hit.node.elements, index: hit.node.elements.length, owner: hit.node, mode: 'inside', el };
    }
    const before = rel < 0.5;
    return { list: hit.list, index: hit.index + (before ? 0 : 1), owner: hit.parent, mode: before ? 'before' : 'after', el };
}

function moveInto(id, target) {
    const hit = find(id);
    if (!hit || (target.owner && (target.owner.id === id || isDescendant(hit.node, target.owner.id)))) return;
    mutate(() => {
        let index = target.index;
        if (hit.list === target.list && hit.index < index) index -= 1;
        hit.list.splice(hit.index, 1);
        let node = hit.node;
        if (target.list === S.tree && node.elType === 'widget') {
            const wrapper = defaultNode('container', null);
            wrapper.elements.push(node);
            node = wrapper;
        }
        if (node.elType === 'container') node.isInner = target.list !== S.tree;
        target.list.splice(index, 0, node);
    });
}

function insertTarget() {
    // Where clicking a palette item puts the new element: inside the selected container, after the selected widget, else at the end.
    const sel = S.selected && find(S.selected);
    if (sel && acceptsChildren(sel.node)) return { list: sel.node.elements, index: sel.node.elements.length, owner: sel.node };
    if (sel) return { list: sel.list, index: sel.index + 1, owner: sel.parent };
    return { list: S.tree, index: S.tree.length, owner: null };
}

// ---------------------------------------------------------------- selection

function select(id, { focusPanel = true } = {}) {
    S.selected = id;
    if (id && focusPanel) { S.leftTab = 'edit'; S.settingsTab = null; }
    if (!id && S.leftTab === 'edit') S.leftTab = 'add';
    paintLeft();
    decorateCanvas();
}

// ---------------------------------------------------------------- UI shell

const els = {};

function buildShell() {
    els.title = h('input', { class: 'lm-title', value: S.title, 'aria-label': 'Title', onChange: async (e) => {
        S.title = e.target.value;
        try { await http('PUT', `/documents/${cfg.document.id}`, { title: S.title }); flash('Title saved'); } catch (err) { flash(err.message); }
    } });
    els.status = h('span', { class: 'lm-status' });
    els.undo = h('button', { class: 'lm-tbtn', title: 'Undo (Ctrl+Z)', onClick: undo }, '↶');
    els.redo = h('button', { class: 'lm-tbtn', title: 'Redo (Ctrl+Y)', onClick: redo }, '↷');
    els.devices = h('div', { class: 'lm-devices' }, Object.entries(DEVICES).map(([key, d]) => h('button', {
        class: 'lm-tbtn', dataset: { device: key }, title: d.label, onClick: () => setDevice(key),
    }, d.icon)));
    els.save = h('button', { class: 'lm-save', onClick: save }, 'Save');
    els.left = h('div', { class: 'lm-left' });
    els.frame = h('iframe', { class: 'lm-frame', title: 'Canvas' });
    els.frameWrap = h('div', { class: 'lm-frame-wrap' }, els.frame);

    root.replaceChildren(
        h('header', { class: 'lm-top' },
            cfg.backUrl ? h('a', { class: 'lm-tbtn', href: cfg.backUrl, title: 'Back' }, '←') : null,
            h('span', { class: 'lm-brand' }, 'Lamentor'),
            els.title,
            h('span', { class: 'lm-spacer' }),
            els.status, els.undo, els.redo, els.devices,
            h('a', { class: 'lm-tbtn', href: cfg.previewUrl, target: '_blank', rel: 'noopener', title: 'Preview in a new tab' }, '👁'),
            h('a', { class: 'lm-tbtn', href: `${cfg.api}/documents/${cfg.document.id}/export`, target: '_blank', rel: 'noopener', title: 'Export as Elementor template' }, '⭳'),
            h('button', { class: 'lm-tbtn', title: 'Revision history', onClick: showHistory }, '🕘'),
            els.save),
        h('div', { class: 'lm-main' }, els.left, h('main', { class: 'lm-canvas' }, els.frameWrap)));
}

function flash(message, ms = 2500) {
    S.status = message;
    paintTopbar();
    setTimeout(() => { if (S.status === message) { S.status = ''; paintTopbar(); } }, ms);
}

function paintTopbar() {
    els.status.textContent = S.saving ? 'Saving…' : (S.status || (S.dirty ? 'Unsaved changes' : 'All changes saved'));
    els.status.classList.toggle('is-dirty', S.dirty && !S.saving);
    els.undo.disabled = !S.undo.length;
    els.redo.disabled = !S.redo.length;
    els.save.disabled = S.saving || !S.dirty;
    els.devices.querySelectorAll('button').forEach((b) => b.classList.toggle('is-active', b.dataset.device === S.device));
}

function setDevice(device) {
    S.device = device;
    els.frame.style.width = DEVICES[device].width;
    els.frameWrap.dataset.device = device;
    paintTopbar();
    if (S.leftTab === 'edit') paintLeft();
    setTimeout(positionBar, 50);
}

async function save() {
    if (S.saving || !S.dirty) return;
    S.saving = true; paintTopbar();
    try {
        await http('PUT', `/documents/${cfg.document.id}`, { data: S.tree });
        S.dirty = false; flash('Saved');
    } catch (e) {
        flash(`Not saved: ${e.message}`, 6000);
    } finally {
        S.saving = false; paintTopbar();
    }
}

async function showHistory() {
    let revisions = [];
    try { revisions = await http('GET', `/documents/${cfg.document.id}/revisions`); } catch (e) { return flash(e.message); }
    const close = () => modal.remove();
    const modal = h('div', { class: 'lm-modal', onClick: (e) => { if (e.target === modal) close(); } },
        h('div', { class: 'lm-dialog', role: 'dialog', 'aria-label': 'Revision history' },
            h('h3', {}, 'Revision history'),
            revisions.length ? h('ul', { class: 'lm-revisions' }, revisions.map((r) => h('li', {},
                h('span', {}, new Date(r.created_at).toLocaleString()),
                h('button', { class: 'lm-btn', onClick: async () => {
                    if (S.dirty && !confirm('Discard unsaved changes and restore this revision?')) return;
                    try {
                        const doc = await http('POST', `/documents/${cfg.document.id}/revisions/${r.id}/restore`);
                        S.tree = doc.data || []; S.undo = []; S.redo = []; S.dirty = false; S.selected = null;
                        scheduleRender(); paintLeft(); paintTopbar(); flash('Revision restored'); close();
                    } catch (e) { flash(e.message); }
                } }, 'Restore')))) : h('p', { class: 'lm-hint' }, 'No earlier revisions yet.'),
            h('button', { class: 'lm-btn', onClick: close }, 'Close')));
    document.body.append(modal);
}

// ---------------------------------------------------------------- left panel

function paintLeft() {
    const scroll = els.left.querySelector('.lm-panel-body')?.scrollTop ?? 0;
    const tab = (id, label) => h('button', { class: `lm-ltab${S.leftTab === id ? ' is-active' : ''}`, onClick: () => { S.leftTab = id; paintLeft(); } }, label);
    const body = h('div', { class: 'lm-panel-body' });
    els.left.replaceChildren(
        h('div', { class: 'lm-ltabs' }, tab('add', 'Elements'), tab('edit', 'Settings'), tab('navigator', 'Navigator')),
        body);

    if (S.leftTab === 'add') body.append(...paintPalette());
    else if (S.leftTab === 'navigator') body.append(paintNavigator());
    else paintSettings(body);
    body.scrollTop = scroll;
}

const CATEGORY_LABELS = { basic: 'Basic', general: 'General', 'pro-elements': 'Pro', 'theme-elements': 'Site', wordpress: 'Other' };

function paintPalette() {
    const search = h('input', { class: 'lm-input', type: 'search', placeholder: 'Search widgets…', value: S.search || '', onInput: (e) => { S.search = e.target.value; repaintGrid(); } });
    const gridHost = h('div', {});
    const repaintGrid = () => {
        const q = (S.search || '').toLowerCase();
        const groups = {};
        const items = [{ name: 'container', title: 'Container', icon: 'eicon-container', categories: ['basic'], keywords: ['layout', 'flex', 'grid'], kind: 'container' }, ...S.widgets];
        items.filter((w) => !q || w.title.toLowerCase().includes(q) || (w.keywords || []).some((k) => k.toLowerCase().includes(q)))
            .forEach((w) => { const cat = w.categories?.[0] || 'basic'; (groups[cat] ||= []).push(w); });
        gridHost.replaceChildren(...Object.entries(groups).map(([cat, list]) => h('section', { class: 'lm-group' },
            h('h4', {}, CATEGORY_LABELS[cat] || cat),
            h('div', { class: 'lm-grid' }, list.map((w) => h('button', {
                type: 'button', class: 'lm-widget', draggable: 'true', title: `Drag onto the canvas, or click to add ${w.title}`,
                onDragstart: (e) => { e.dataTransfer.setData('text/lamentor-new', w.name); e.dataTransfer.effectAllowed = 'copy'; },
                onClick: () => addElement(w.name, insertTarget()),
            }, h('i', { class: w.icon, 'aria-hidden': 'true' }), h('span', {}, w.title)))))));
        if (!gridHost.children.length) gridHost.append(h('p', { class: 'lm-hint' }, 'No widgets match.'));
    };
    repaintGrid();
    return [search, gridHost];
}

function paintNavigator() {
    const wrap = h('div', { class: 'lm-nav' });
    const row = (node, depth) => {
        const hasKids = (node.elements || []).length > 0;
        const open = !S.collapsed.has(node.id);
        const item = h('div', {
            class: `lm-nav-item${S.selected === node.id ? ' is-selected' : ''}`, style: { paddingLeft: `${8 + depth * 14}px` }, draggable: 'true',
            onClick: () => select(node.id, { focusPanel: false }),
            onDragstart: (e) => { e.dataTransfer.setData('text/lamentor-move', node.id); e.dataTransfer.effectAllowed = 'move'; },
            onDragover: (e) => {
                if (![...e.dataTransfer.types].some((t) => t.startsWith('text/lamentor'))) return;
                e.preventDefault();
                const r = item.getBoundingClientRect(); const rel = (e.clientY - r.top) / r.height;
                item.dataset.drop = acceptsChildren(node) && rel > 0.25 && rel < 0.75 ? 'inside' : (rel < 0.5 ? 'before' : 'after');
            },
            onDragleave: () => { delete item.dataset.drop; },
            onDrop: (e) => {
                e.preventDefault();
                const mode = item.dataset.drop; delete item.dataset.drop;
                const hit = find(node.id);
                if (!hit) return;
                const target = mode === 'inside' ? { list: node.elements, index: node.elements.length, owner: node }
                    : { list: hit.list, index: hit.index + (mode === 'before' ? 0 : 1), owner: hit.parent };
                const moving = e.dataTransfer.getData('text/lamentor-move');
                const fresh = e.dataTransfer.getData('text/lamentor-new');
                if (moving) moveInto(moving, target); else if (fresh) addElement(fresh, target);
            },
        },
        h('button', { class: `lm-caret${hasKids ? '' : ' is-empty'}`, onClick: (e) => { e.stopPropagation(); open ? S.collapsed.add(node.id) : S.collapsed.delete(node.id); paintLeft(); } }, hasKids ? (open ? '▾' : '▸') : ''),
        h('i', { class: iconOf(node), 'aria-hidden': 'true' }),
        h('span', { class: 'lm-nav-label' }, labelOf(node)),
        h('span', { class: 'lm-nav-actions' },
            h('button', { class: 'lm-x', title: 'Duplicate', onClick: (e) => { e.stopPropagation(); duplicateElement(node.id); } }, '⧉'),
            h('button', { class: 'lm-x', title: 'Delete', onClick: (e) => { e.stopPropagation(); removeElement(node.id); } }, '×')));
        wrap.append(item);
        if (hasKids && open) node.elements.forEach((child) => row(child, depth + 1));
    };
    if (!S.tree.length) wrap.append(h('p', { class: 'lm-hint' }, 'The page is empty. Add a container from the Elements tab.'));
    S.tree.forEach((n) => row(n, 0));
    return wrap;
}

const TAB_LABELS = { content: 'Content', layout: 'Layout', style: 'Style', advanced: 'Advanced', responsive: 'Responsive' };

async function paintSettings(body) {
    const hit = S.selected && find(S.selected);
    if (!hit) { body.append(h('p', { class: 'lm-hint' }, 'Select an element on the canvas to edit it.')); return; }
    const node = hit.node;
    body.append(h('p', { class: 'lm-hint' }, 'Loading…'));
    let schema;
    try { schema = await loadSchema(typeOf(node)); } catch (e) { body.replaceChildren(h('p', { class: 'lm-hint' }, `Could not load controls: ${e.message}`)); return; }
    if (S.selected !== node.id || S.leftTab !== 'edit') return;

    const controls = schema.controls;
    const values = effective(node, schema);
    const tabIds = [...new Set(Object.values(controls).map((c) => c.tab).filter(Boolean))];
    const tabId = tabIds.includes(S.settingsTab) ? S.settingsTab : tabIds[0];

    const header = h('div', { class: 'lm-edit-head' }, h('i', { class: iconOf(node), 'aria-hidden': 'true' }), h('strong', {}, `Edit ${labelOf(node)}`));
    const tabs = h('div', { class: 'lm-stabs' }, tabIds.map((t) => h('button', { class: `lm-stab${t === tabId ? ' is-active' : ''}`, onClick: () => { S.settingsTab = t; paintLeft(); } }, TAB_LABELS[t] || t)));

    const sections = [];
    const byId = {};
    for (const [name, c] of Object.entries(controls)) {
        if (c.tab !== tabId) continue;
        if (c.type === 'section') { byId[name] = { id: name, label: c.label || name, controls: [], control: c }; sections.push(byId[name]); }
    }
    for (const [name, c] of Object.entries(controls)) {
        if (c.tab !== tabId || c.type === 'section' || c.type === 'tab' || c.tabs_wrapper) continue;
        const target = byId[c.section];
        if (target) target.controls.push([name, c]);
    }

    const list = h('div', { class: 'lm-sections' });
    for (const section of sections) {
        if (!visible(section.control, values, controls)) continue;
        const key = `${node.id}:${section.id}`;
        const details = h('details', { class: 'lm-section', open: S.openSections[key] ? true : null });
        const bodyEl = h('div', { class: 'lm-section-body' });
        const fill = () => { bodyEl.replaceChildren(...renderSection(node, schema, section, values, controls)); details.dataset.filled = '1'; };
        details.addEventListener('toggle', () => { S.openSections[key] = details.open; if (details.open && !details.dataset.filled) fill(); });
        details.append(h('summary', {}, section.label), bodyEl);
        if (S.openSections[key]) fill();
        list.append(details);
    }
    if (!list.children.length) list.append(h('p', { class: 'lm-hint' }, 'Nothing to edit here.'));

    body.replaceChildren(header, tabs, list);
}

/** Row for one control; shared by sections and repeater items. */
function fieldRow(control, value, set, ctxExtra = {}) {
    const ctx = {
        value, set, kit: S.kit, upload, uid: () => `lm${newId()}`, fieldRow,
        visibleField: (f, merged, all) => visible(f, merged, all), ...ctxExtra,
    };
    const node = renderControl(control, ctx);
    if (!node) return null;
    const inline = NO_LABEL.has(control.type);
    const showLabel = control.label && !['heading', 'divider', 'raw_html', 'alert', 'notice'].includes(control.type);
    const respIcon = control.responsive ? h('span', { class: 'lm-resp', title: `${DEVICES[control.responsive.max || 'desktop']?.label || ''} value` }, DEVICES[control.responsive.max || 'desktop']?.icon || '') : null;
    return h('div', { class: `lm-field lm-type-${control.type}${inline ? ' lm-inline' : ''}` },
        showLabel ? h('div', { class: 'lm-label' }, control.label, respIcon) : null,
        node,
        control.description && !['raw_html', 'alert'].includes(control.type) ? (() => { const p = h('p', { class: 'lm-hint' }); p.textContent = String(control.description).replace(/<[^>]+>/g, ''); return p; })() : null);
}

function renderSection(node, schema, section, values, controls) {
    const out = [];
    const apply = (name, control, value) => {
        const key = `${node.id}:${name}`;
        const before = visibilitySignature(node, schema);
        mutate(() => setSetting(node, schema, name, value), key);
        if (before !== visibilitySignature(node, schema)) paintLeft();
    };

    const renderOne = ([name, c]) => {
        if (STRUCTURAL.has(c.type) && c.type !== 'tabs') return null;
        if (!forDevice(c, S.device)) return null;
        if (!visible(c, effective(node, schema), controls)) return null;

        if (c.type === 'tabs') return renderInnerTabs(node, schema, name, controls, renderOne);

        const extra = {};
        if (c.global?.active && c.type === 'color') {
            extra.global = node.settings?.__globals__?.[name] || '';
            extra.setGlobal = (key) => {
                mutate(() => {
                    node.settings = node.settings || {};
                    const globals = { ...(node.settings.__globals__ || {}) };
                    if (key) { globals[name] = key; delete node.settings[name]; } else delete globals[name];
                    if (Object.keys(globals).length) node.settings.__globals__ = globals; else delete node.settings.__globals__;
                }, `${node.id}:${name}:global`);
                paintLeft();
            };
        }
        return fieldRow(c, effective(node, schema)[name], (v) => {
            if (extra.global && v) extra.setGlobal(null);
            apply(name, c, v);
        }, extra);
    };

    section.controls.forEach((entry) => { const row = renderOne(entry); if (row) out.push(row); });
    return out;
}

function renderInnerTabs(node, schema, wrapperName, controls, renderOne) {
    const tabList = Object.entries(controls).filter(([, c]) => c.type === 'tab' && c.tabs_wrapper === wrapperName);
    if (!tabList.length) return null;
    const stateKey = `${node.id}:${wrapperName}`;
    const active = S.innerTabs[stateKey] && tabList.some(([n]) => n === S.innerTabs[stateKey]) ? S.innerTabs[stateKey] : tabList[0][0];
    const bar = h('div', { class: 'lm-itabs' }, tabList.map(([n, c]) => h('button', { type: 'button', class: `lm-itab${n === active ? ' is-active' : ''}`, onClick: () => { S.innerTabs[stateKey] = n; paintLeft(); } }, c.label || n)));
    const content = h('div', { class: 'lm-itab-body' });
    Object.entries(controls).filter(([, c]) => c.tabs_wrapper === wrapperName && c.inner_tab === active && c.type !== 'tab')
        .forEach((entry) => { const row = renderOne(entry); if (row) content.append(row); });
    return h('div', { class: 'lm-inner-tabs' }, bar, content);
}

function visibilitySignature(node, schema) {
    const values = effective(node, schema);
    return Object.entries(schema.controls).filter(([, c]) => c.condition || c.conditions).map(([n, c]) => (visible(c, values, schema.controls) ? '1' : '0')).join('');
}

// ---------------------------------------------------------------- canvas

const CANVAS_CSS = `
html{scroll-behavior:auto}
body{margin:0;min-height:100vh;background:#fff}
.elementor-invisible{visibility:visible!important}
.lm-hover{outline:1px dashed #6ea8fe!important;outline-offset:-1px}
.lm-selected{outline:2px solid #2f6bff!important;outline-offset:-2px}
[data-element_type="container"]:not(:has([data-id])){min-height:90px;background:repeating-linear-gradient(45deg,rgba(47,107,255,.05) 0 10px,rgba(47,107,255,.09) 10px 20px)}
[data-element_type="column"] .elementor-widget-wrap:not(.elementor-element-populated){min-height:90px}
.lm-empty{margin:60px auto;max-width:420px;padding:48px 24px;text-align:center;font:15px/1.5 system-ui,sans-serif;color:#566;border:2px dashed #b8c4e6;border-radius:12px}
.lm-add-root{display:flex;justify-content:center;padding:20px}
.lm-add-root button{font:600 14px system-ui,sans-serif;padding:10px 18px;border:1px dashed #2f6bff;color:#2f6bff;background:#fff;border-radius:8px;cursor:pointer}
.lm-bar{position:absolute;z-index:2147483000;display:flex;gap:2px;align-items:center;background:#2f6bff;color:#fff;font:12px system-ui,sans-serif;border-radius:4px 4px 0 0;padding:0 2px;transform:translateY(-100%);white-space:nowrap}
.lm-bar span{padding:3px 8px}
.lm-bar button{all:unset;cursor:pointer;padding:3px 7px;border-radius:3px}
.lm-bar button:hover{background:rgba(255,255,255,.25)}
.lm-drop{position:absolute;z-index:2147483000;height:3px;background:#2f6bff;pointer-events:none;box-shadow:0 0 0 1px #fff}
.lm-drop-inside{outline:3px solid #2f6bff!important;outline-offset:-3px}
`;

function canvasSkeleton() {
    return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style id="lm-editor-css">${CANVAS_CSS}</style><style id="lm-css"></style></head>
<body class="elementor-default elementor-kit-1"><div id="lm-root"></div></body></html>`;
}

function frameDoc() { return els.frame.contentDocument; }

function ensureLinks(doc, files, fontsUrl) {
    const wanted = [...files.map((f) => `${assets}/${f}`), ...(fontsUrl ? [fontsUrl] : [])];
    const have = new Set([...doc.querySelectorAll('link[data-lm]')].map((l) => l.dataset.lm));
    for (const href of wanted) {
        if (have.has(href)) continue;
        doc.head.insertBefore(h('link', { rel: 'stylesheet', href, 'data-lm': href }), doc.getElementById('lm-editor-css'));
    }
}

let renderTimer = null;
function scheduleRender(immediate = false) {
    clearTimeout(renderTimer);
    renderTimer = setTimeout(renderCanvas, immediate ? 0 : 220);
}

async function renderCanvas() {
    if (!S.frameReady) return;
    const token = ++S.renderToken;
    try {
        const res = await http('POST', '/render', { elements: S.tree, document_id: cfg.document.id });
        if (token !== S.renderToken) return;
        paintCanvas(res);
    } catch (e) {
        flash(`Preview failed: ${e.message}`, 5000);
    }
}

function paintCanvas(res) {
    const doc = frameDoc();
    const scroller = doc.scrollingElement;
    const top = scroller.scrollTop;
    ensureLinks(doc, res.stylesheets || [], res.fontsUrl);
    doc.getElementById('lm-css').textContent = res.css || '';
    const rootEl = doc.getElementById('lm-root');
    rootEl.innerHTML = S.tree.length ? res.html : '';
    if (!S.tree.length) rootEl.append(h('div', { class: 'lm-empty' }, h('strong', {}, 'Drag a widget here'), h('div', {}, 'or add a container to start building.')));
    ensureAddRoot(doc);
    decorateCanvas();
    scroller.scrollTop = top;
}

function ensureAddRoot(doc) {
    let add = doc.getElementById('lm-add-root');
    if (!add) {
        add = h('div', { id: 'lm-add-root', class: 'lm-add-root' }, h('button', { type: 'button', onClick: () => addElement('container', { list: S.tree, index: S.tree.length, owner: null }) }, '＋ Add container'));
        doc.body.append(add);
    }
}

function decorateCanvas() {
    const doc = frameDoc();
    if (!doc || !S.frameReady) return;
    doc.querySelectorAll('.lm-selected').forEach((n) => n.classList.remove('lm-selected'));
    const target = S.selected ? doc.querySelector(`[data-id="${CSS.escape(S.selected)}"]`) : null;
    if (target) target.classList.add('lm-selected');
    positionBar();
}

function positionBar() {
    const doc = frameDoc();
    if (!doc) return;
    doc.getElementById('lm-bar')?.remove();
    const target = S.selected ? doc.querySelector(`[data-id="${CSS.escape(S.selected)}"]`) : null;
    const hit = S.selected && find(S.selected);
    if (!target || !hit) return;
    const rect = target.getBoundingClientRect();
    const win = doc.defaultView;
    const bar = h('div', { id: 'lm-bar', class: 'lm-bar', style: { left: `${rect.left + win.scrollX}px`, top: `${Math.max(rect.top + win.scrollY, 22)}px` } },
        h('span', {}, labelOf(hit.node)),
        h('button', { title: 'Move up', onClick: (e) => { e.stopPropagation(); moveElement(S.selected, -1); } }, '↑'),
        h('button', { title: 'Move down', onClick: (e) => { e.stopPropagation(); moveElement(S.selected, 1); } }, '↓'),
        h('button', { title: 'Duplicate', onClick: (e) => { e.stopPropagation(); duplicateElement(S.selected); } }, '⧉'),
        h('button', { title: 'Delete', onClick: (e) => { e.stopPropagation(); removeElement(S.selected); } }, '✕'));
    doc.body.append(bar);
}

function attachCanvasEvents(doc) {
    const closestEl = (t) => (t.closest ? t.closest('[data-id]') : null);
    let hovered = null;
    const indicator = () => doc.getElementById('lm-drop') || doc.body.appendChild(h('div', { id: 'lm-drop', class: 'lm-drop' }));
    const clearDrop = () => { doc.getElementById('lm-drop')?.remove(); doc.querySelectorAll('.lm-drop-inside').forEach((n) => n.classList.remove('lm-drop-inside')); };

    doc.addEventListener('click', (e) => {
        if (e.target.closest('.lm-bar, .lm-add-root')) return;
        e.preventDefault();
        const el = closestEl(e.target);
        select(el ? el.dataset.id : null);
    }, true);

    doc.addEventListener('mouseover', (e) => {
        const el = closestEl(e.target);
        if (hovered === el) return;
        hovered?.classList.remove('lm-hover');
        hovered = el; el?.classList.add('lm-hover');
    });
    doc.addEventListener('mouseleave', () => { hovered?.classList.remove('lm-hover'); hovered = null; });

    const isOurs = (e) => [...e.dataTransfer.types].some((t) => t.startsWith('text/lamentor'));
    doc.addEventListener('dragover', (e) => {
        if (!isOurs(e)) return;
        e.preventDefault();
        clearDrop();
        const t = targetFor(closestEl(e.target), e.clientY, e.clientX);
        if (t.mode === 'inside') { t.el?.classList.add('lm-drop-inside'); return; }
        if (!t.el) return;
        const r = t.el.getBoundingClientRect(); const win = doc.defaultView;
        Object.assign(indicator().style, { left: `${r.left + win.scrollX}px`, width: `${r.width}px`, top: `${(t.mode === 'before' ? r.top : r.bottom) + win.scrollY - 1}px` });
    });
    doc.addEventListener('dragleave', (e) => { if (!e.relatedTarget) clearDrop(); });
    doc.addEventListener('drop', (e) => {
        if (!isOurs(e)) return;
        e.preventDefault();
        const t = targetFor(closestEl(e.target), e.clientY, e.clientX);
        clearDrop();
        const fresh = e.dataTransfer.getData('text/lamentor-new');
        const moving = e.dataTransfer.getData('text/lamentor-move');
        if (fresh) addElement(fresh, t); else if (moving) moveInto(moving, t);
    });

    doc.addEventListener('keydown', onKey);
    doc.defaultView.addEventListener('scroll', () => { /* the bar is positioned in page coordinates */ });
    doc.defaultView.addEventListener('resize', positionBar);
}

function onKey(e) {
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable;
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
    if (typing) return;
    if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
    else if (mod && e.key.toLowerCase() === 'y') { e.preventDefault(); redo(); }
    else if (mod && e.key.toLowerCase() === 'd' && S.selected) { e.preventDefault(); duplicateElement(S.selected); }
    else if ((e.key === 'Delete' || e.key === 'Backspace') && S.selected) { e.preventDefault(); removeElement(S.selected); }
}

// ---------------------------------------------------------------- boot

async function boot() {
    buildShell();
    paintTopbar();
    setDevice('desktop');

    try {
        const [widgets, kit] = await Promise.all([http('GET', '/schema/widgets'), http('GET', '/kit')]);
        S.widgets = widgets.sort((a, b) => a.title.localeCompare(b.title));
        S.kit = kit;
    } catch (e) {
        root.replaceChildren(h('p', { class: 'lm-fatal' }, `The editor could not load its widget catalogue: ${e.message}`));
        return;
    }

    paintLeft();

    els.frame.addEventListener('load', () => {
        S.frameReady = true;
        attachCanvasEvents(frameDoc());
        scheduleRender(true);
    });
    els.frame.srcdoc = canvasSkeleton();

    document.addEventListener('keydown', onKey);
    window.addEventListener('beforeunload', (e) => { if (S.dirty) { e.preventDefault(); e.returnValue = ''; } });
}

boot();
