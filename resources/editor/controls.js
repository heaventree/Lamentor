// Control widgets for the Lamentor editor. Each renderer takes the control's
// schema entry (as dumped from Elementor) plus a context and returns a DOM node.
//
// ctx = {
//   value,                      // current effective value of this control
//   set(value),                 // store a new value (empty/default values are dropped by the editor)
//   kit,                        // global colours/typography
//   upload(file) -> Promise<{url,id,alt}>,
//   uid(),                      // unique id for labels
// }

export const h = (tag, props = {}, ...children) => {
    const el = document.createElement(tag);
    for (const [key, value] of Object.entries(props || {})) {
        if (value === null || value === undefined || value === false) continue;
        if (key === 'class') el.className = value;
        else if (key === 'style' && typeof value === 'object') Object.assign(el.style, value);
        else if (key === 'dataset') Object.assign(el.dataset, value);
        else if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2).toLowerCase(), value);
        else if (key === 'value') el.value = value;
        else if (key === 'checked') el.checked = !!value;
        else if (value === true) el.setAttribute(key, '');
        else el.setAttribute(key, value);
    }
    for (const child of children.flat(Infinity)) {
        if (child === null || child === undefined || child === false) continue;
        el.append(child.nodeType ? child : document.createTextNode(String(child)));
    }
    return el;
};

const clone = (v) => JSON.parse(JSON.stringify(v ?? null));
const isObj = (v) => v && typeof v === 'object' && !Array.isArray(v);
const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

/** Options may be {value: label | {title, icon}} or a list. @returns {Array<{value,label,icon?}>} */
const optionList = (options) => {
    if (Array.isArray(options)) return options.map((o) => ({ value: o, label: String(o) }));
    return Object.entries(options || {}).map(([value, o]) => (isObj(o) ? { value, label: o.title ?? value, icon: o.icon } : { value, label: String(o) }));
};

const GOOGLE_FONTS = ['Roboto', 'Roboto Slab', 'Open Sans', 'Lato', 'Montserrat', 'Poppins', 'Inter', 'Playfair Display', 'Merriweather', 'Raleway', 'Nunito', 'Oswald', 'Lora', 'Source Sans Pro', 'Ubuntu', 'Rubik', 'Work Sans', 'DM Sans', 'Arial', 'Helvetica', 'Georgia', 'Times New Roman', 'Verdana', 'Tahoma', 'Trebuchet MS'];
const ENTRANCE_ANIMATIONS = ['fadeIn', 'fadeInDown', 'fadeInUp', 'fadeInLeft', 'fadeInRight', 'zoomIn', 'zoomInDown', 'zoomInUp', 'zoomInLeft', 'zoomInRight', 'bounceIn', 'bounceInDown', 'bounceInUp', 'bounceInLeft', 'bounceInRight', 'slideInDown', 'slideInUp', 'slideInLeft', 'slideInRight', 'rotateIn', 'lightSpeedIn', 'rollIn'];
const HOVER_ANIMATIONS = ['grow', 'shrink', 'pulse', 'pulse-grow', 'pulse-shrink', 'push', 'pop', 'bounce-in', 'bounce-out', 'rotate', 'grow-rotate', 'float', 'sink', 'bob', 'hang', 'wobble-horizontal', 'wobble-vertical', 'buzz', 'buzz-out'];
const ICON_LIBRARIES = [['fa-solid', 'Font Awesome – Solid'], ['fa-regular', 'Font Awesome – Regular'], ['fa-brands', 'Font Awesome – Brands'], ['eicons', 'Elementor icons']];

// --- small building blocks -------------------------------------------------

const textInput = (value, onInput, attrs = {}) => h('input', { type: 'text', class: 'lm-input', value: value ?? '', onInput: (e) => onInput(e.target.value), ...attrs });

const numberInput = (value, onInput, attrs = {}) => h('input', {
    type: 'number', class: 'lm-input lm-num', value: value ?? '', step: 'any',
    onInput: (e) => onInput(e.target.value === '' ? '' : Number(e.target.value)), ...attrs,
});

const unitSelect = (units, current, onChange) => (units && units.length > 1
    ? h('select', { class: 'lm-unit', onChange: (e) => onChange(e.target.value) }, units.map((u) => h('option', { value: u, selected: u === current }, u)))
    : h('span', { class: 'lm-unit lm-unit-static' }, units?.[0] ?? current ?? ''));

const field = (label, node, cls = '') => h('label', { class: `lm-mini ${cls}` }, node, h('span', {}, label));

// --- renderers ---------------------------------------------------------------

const R = {};

R.text = R.number_text = (c, ctx) => textInput(ctx.value, ctx.set, { placeholder: c.placeholder || '' });

R.number = (c, ctx) => numberInput(ctx.value, ctx.set, { min: c.min, max: c.max, step: c.step ?? 'any', placeholder: c.placeholder || '' });

R.textarea = (c, ctx) => h('textarea', { class: 'lm-input', rows: c.rows || 4, placeholder: c.placeholder || '', onInput: (e) => ctx.set(e.target.value) }, ctx.value ?? '');

R.code = (c, ctx) => h('textarea', { class: 'lm-input lm-code', rows: 8, spellcheck: 'false', onInput: (e) => ctx.set(e.target.value) }, ctx.value ?? '');

R.select = (c, ctx) => {
    const opts = optionList(c.options);
    return h('select', { class: 'lm-input', onChange: (e) => ctx.set(e.target.value) },
        opts.map((o) => h('option', { value: o.value, selected: String(o.value) === String(ctx.value ?? '') }, o.label)));
};
R.select2 = R.select;

R.choose = (c, ctx) => {
    const opts = optionList(c.options);
    const wrap = h('div', { class: 'lm-choose' });
    opts.forEach((o) => {
        const active = String(ctx.value ?? '') === String(o.value);
        wrap.append(h('button', {
            type: 'button', class: `lm-choose-btn${active ? ' is-active' : ''}`, title: o.label,
            // Like Elementor, clicking the active option clears it unless the control sets toggle:false.
            onClick: () => ctx.set(active && c.toggle !== false ? '' : o.value),
        }, o.icon ? h('i', { class: o.icon, 'aria-hidden': 'true' }) : o.label));
    });
    return wrap;
};

R.switcher = (c, ctx) => {
    const on = c.return_value ?? 'yes';
    return h('label', { class: 'lm-switch' },
        h('input', { type: 'checkbox', checked: String(ctx.value ?? '') === String(on), onChange: (e) => ctx.set(e.target.checked ? on : '') }),
        h('span', { class: 'lm-switch-track' }),
        h('span', { class: 'lm-switch-label' }, c.label_on && String(ctx.value ?? '') === String(on) ? c.label_on : (c.label_off || '')));
};

R.popover_toggle = (c, ctx) => {
    const on = c.return_value ?? 'yes';
    return h('label', { class: 'lm-switch' },
        h('input', { type: 'checkbox', checked: !!ctx.value && String(ctx.value) !== '', onChange: (e) => ctx.set(e.target.checked ? on : '') }),
        h('span', { class: 'lm-switch-track' }));
};

R.color = (c, ctx) => {
    const globals = ctx.kit ? [...(ctx.kit.system_colors || []), ...(ctx.kit.custom_colors || [])] : [];
    const current = ctx.global || '';
    const text = textInput(ctx.value ?? '', (v) => ctx.set(v), { placeholder: '#rrggbb or rgba()' });
    const picker = h('input', {
        type: 'color', class: 'lm-color',
        value: /^#[0-9a-f]{6}$/i.test(ctx.value || '') ? ctx.value : '#000000',
        onInput: (e) => { text.value = e.target.value; ctx.set(e.target.value); },
    });
    const clear = h('button', { type: 'button', class: 'lm-link', onClick: () => { text.value = ''; ctx.setGlobal?.(null); ctx.set(''); } }, 'Clear');
    const wrap = h('div', { class: 'lm-color-wrap' }, h('div', { class: 'lm-row' }, picker, text, clear));
    if (c.global && ctx.setGlobal && globals.length) {
        wrap.append(h('div', { class: 'lm-swatches', title: 'Global colours' }, globals.map((g) => h('button', {
            type: 'button', class: `lm-swatch${current === `globals/colors?id=${g._id}` ? ' is-active' : ''}`, title: g.title,
            style: { background: g.color }, onClick: () => ctx.setGlobal(`globals/colors?id=${g._id}`),
        }))));
    }
    return wrap;
};

R.slider = (c, ctx) => {
    const units = c.size_units && c.size_units.length ? c.size_units : [ctx.value?.unit || 'px'];
    const value = isObj(ctx.value) ? ctx.value : {};
    const unit = value.unit || units[0];
    const range = (c.range && (c.range[unit] || c.range[Object.keys(c.range)[0]])) || null;
    const write = (patch) => ctx.set({ unit, size: value.size ?? '', sizes: value.sizes ?? [], ...patch });
    const number = numberInput(value.size ?? '', (v) => { if (slider) slider.value = v === '' ? (range?.min ?? 0) : v; write({ size: v }); }, { min: range?.min, max: range?.max, step: range?.step ?? 'any' });
    const slider = range
        ? h('input', { type: 'range', class: 'lm-range', min: range.min ?? 0, max: range.max ?? 100, step: range.step ?? 1, value: value.size === '' || value.size === undefined ? (range.min ?? 0) : value.size, onInput: (e) => { number.value = e.target.value; write({ size: Number(e.target.value) }); } })
        : null;
    return h('div', { class: 'lm-slider' }, slider, number, c.size_units ? unitSelect(units, unit, (u) => write({ unit: u })) : null);
};

R.dimensions = (c, ctx) => {
    const units = c.size_units && c.size_units.length ? c.size_units : ['px'];
    const value = isObj(ctx.value) ? ctx.value : {};
    const sides = ['top', 'right', 'bottom', 'left'];
    const state = { isLinked: value.isLinked === undefined ? true : !!value.isLinked, unit: value.unit || units[0] };
    const inputs = {};
    const read = () => ({ unit: state.unit, isLinked: state.isLinked, ...Object.fromEntries(sides.map((s) => [s, inputs[s].value === '' ? '' : Number(inputs[s].value)])) });
    const wrap = h('div', { class: 'lm-dims' });
    sides.forEach((side) => {
        inputs[side] = h('input', {
            type: 'number', class: 'lm-input lm-num', step: 'any', value: value[side] ?? '', 'aria-label': side,
            onInput: (e) => {
                if (state.isLinked) sides.forEach((s) => { inputs[s].value = e.target.value; });
                ctx.set(read());
            },
        });
        wrap.append(h('label', { class: 'lm-dim' }, inputs[side], h('span', {}, side)));
    });
    const link = h('button', {
        type: 'button', class: `lm-link-toggle${state.isLinked ? ' is-active' : ''}`, title: 'Link values together',
        onClick: () => { state.isLinked = !state.isLinked; link.classList.toggle('is-active', state.isLinked); ctx.set(read()); },
    }, state.isLinked ? '🔗' : '⛓');
    return h('div', { class: 'lm-dims-wrap' }, wrap, h('div', { class: 'lm-dims-side' }, link, c.size_units ? unitSelect(units, state.unit, (u) => { state.unit = u; ctx.set(read()); }) : null));
};

R.gaps = (c, ctx) => {
    const units = c.size_units && c.size_units.length ? c.size_units : ['px'];
    const value = isObj(ctx.value) ? ctx.value : {};
    const state = { isLinked: value.isLinked === undefined ? true : !!value.isLinked, unit: value.unit || units[0] };
    const col = numberInput(value.column ?? '', () => push('column'));
    const row = numberInput(value.row ?? '', () => push('row'));
    const push = (changed) => {
        if (state.isLinked) (changed === 'column' ? row : col).value = (changed === 'column' ? col : row).value;
        ctx.set({ unit: state.unit, isLinked: state.isLinked, column: col.value === '' ? '' : Number(col.value), row: row.value === '' ? '' : Number(row.value) });
    };
    const link = h('button', { type: 'button', class: `lm-link-toggle${state.isLinked ? ' is-active' : ''}`, onClick: () => { state.isLinked = !state.isLinked; link.classList.toggle('is-active', state.isLinked); } }, state.isLinked ? '🔗' : '⛓');
    return h('div', { class: 'lm-gaps' }, field('Column', col), field('Row', row), link, unitSelect(units, state.unit, (u) => { state.unit = u; push('column'); }));
};

R.url = (c, ctx) => {
    const value = isObj(ctx.value) ? { ...ctx.value } : { url: '' };
    const write = (patch) => { Object.assign(value, patch); ctx.set({ ...value }); };
    return h('div', { class: 'lm-url' },
        textInput(value.url ?? '', (v) => write({ url: v }), { placeholder: c.placeholder || 'Paste URL or type' }),
        h('div', { class: 'lm-row' },
            h('label', { class: 'lm-check' }, h('input', { type: 'checkbox', checked: !!value.is_external, onChange: (e) => write({ is_external: e.target.checked ? 'on' : '' }) }), ' Open in new window'),
            h('label', { class: 'lm-check' }, h('input', { type: 'checkbox', checked: !!value.nofollow, onChange: (e) => write({ nofollow: e.target.checked ? 'on' : '' }) }), ' Add nofollow')));
};

const mediaControl = (c, ctx, value, write, accept) => {
    const preview = h('div', { class: 'lm-media-preview' });
    const paint = (v) => {
        preview.replaceChildren();
        if (v.url && /\.(mp4|webm)(\?|$)/i.test(v.url)) preview.append(h('video', { src: v.url, muted: true }));
        else if (v.url && /\.(mp3|ogg|wav)(\?|$)/i.test(v.url)) preview.append(h('span', {}, '♪ ' + v.url.split('/').pop()));
        else if (v.url) preview.append(h('img', { src: v.url, alt: '' }));
        else preview.append(h('span', {}, 'No file'));
    };
    paint(value);
    const status = h('span', { class: 'lm-hint' });
    const url = textInput(value.url ?? '', (v) => { write({ url: v }); paint({ ...value, url: v }); }, { placeholder: 'https://… or upload' });
    const file = h('input', {
        type: 'file', accept, class: 'lm-file',
        onChange: async (e) => {
            const f = e.target.files[0];
            if (!f) return;
            status.textContent = 'Uploading…';
            try {
                const media = await ctx.upload(f);
                url.value = media.url; write({ url: media.url, id: media.id ?? '', alt: media.alt ?? '', source: 'library' }); paint(media); status.textContent = '';
            } catch (err) { status.textContent = err.message || 'Upload failed'; }
            e.target.value = '';
        },
    });
    return { preview, url, file, status };
};

R.media = (c, ctx) => {
    const value = isObj(ctx.value) ? { ...ctx.value } : { url: '', id: '' };
    const write = (patch) => { Object.assign(value, patch); ctx.set({ ...value }); };
    const m = mediaControl(c, ctx, value, write, c.media_types ? c.media_types.map((t) => `${t}/*`).join(',') : 'image/*,video/*,audio/*');
    const alt = textInput(value.alt ?? '', (v) => write({ alt: v }), { placeholder: 'Alt text' });
    const clear = h('button', { type: 'button', class: 'lm-link', onClick: () => { m.url.value = ''; write({ url: '', id: '' }); m.preview.replaceChildren(h('span', {}, 'No file')); } }, 'Remove');
    return h('div', { class: 'lm-media' }, m.preview, m.url, h('div', { class: 'lm-row' }, m.file, clear), m.status, alt);
};

R.gallery = (c, ctx) => {
    let items = Array.isArray(ctx.value) ? clone(ctx.value) : [];
    const list = h('div', { class: 'lm-gallery' });
    const commit = () => { ctx.set(clone(items)); paint(); };
    const paint = () => {
        list.replaceChildren(...items.map((it, i) => h('div', { class: 'lm-gallery-item' },
            h('img', { src: it.url, alt: '' }),
            h('button', { type: 'button', class: 'lm-x', title: 'Remove', onClick: () => { items.splice(i, 1); commit(); } }, '×'))));
    };
    paint();
    const status = h('span', { class: 'lm-hint' });
    const file = h('input', {
        type: 'file', accept: 'image/*', multiple: true, class: 'lm-file',
        onChange: async (e) => {
            status.textContent = 'Uploading…';
            try {
                for (const f of e.target.files) items.push(await ctx.upload(f));
                commit(); status.textContent = '';
            } catch (err) { status.textContent = err.message || 'Upload failed'; }
            e.target.value = '';
        },
    });
    const urls = h('textarea', { class: 'lm-input', rows: 3, placeholder: 'Or paste image URLs, one per line' });
    const add = h('button', { type: 'button', class: 'lm-btn', onClick: () => { urls.value.split('\n').map((s) => s.trim()).filter(Boolean).forEach((u) => items.push({ url: u, id: '' })); urls.value = ''; commit(); } }, 'Add URLs');
    return h('div', { class: 'lm-media' }, list, file, status, urls, add);
};

R.icons = (c, ctx) => {
    const value = isObj(ctx.value) ? { ...ctx.value } : { value: '', library: '' };
    const write = (patch) => { Object.assign(value, patch); if (!value.library && value.value) value.library = 'fa-solid'; ctx.set({ ...value }); };
    const preview = h('i', { class: typeof value.value === 'string' ? value.value : '', 'aria-hidden': 'true' });
    return h('div', { class: 'lm-icons' },
        h('div', { class: 'lm-row' }, preview,
            textInput(typeof value.value === 'string' ? value.value : '', (v) => { preview.className = v; write({ value: v }); }, { placeholder: 'e.g. fas fa-star' })),
        h('select', { class: 'lm-input', onChange: (e) => write({ library: e.target.value }) }, ICON_LIBRARIES.map(([v, l]) => h('option', { value: v, selected: v === (value.library || 'fa-solid') }, l))),
        h('button', { type: 'button', class: 'lm-link', onClick: () => { preview.className = ''; ctx.set({ value: '', library: '' }); } }, 'Remove'));
};

R.font = (c, ctx) => {
    const id = ctx.uid();
    return h('div', {}, textInput(ctx.value ?? '', ctx.set, { list: id, placeholder: 'Font family' }),
        h('datalist', { id }, GOOGLE_FONTS.map((f) => h('option', { value: f }))));
};

R.animation = (c, ctx) => {
    const id = ctx.uid();
    const opts = c.options ? optionList(c.options).map((o) => o.value) : ENTRANCE_ANIMATIONS;
    return h('div', {}, textInput(ctx.value ?? '', ctx.set, { list: id, placeholder: 'none' }), h('datalist', { id }, ['none', ...opts].map((f) => h('option', { value: f }))));
};

R.hover_animation = (c, ctx) => {
    const id = ctx.uid();
    return h('div', {}, textInput(ctx.value ?? '', ctx.set, { list: id, placeholder: 'none' }), h('datalist', { id }, HOVER_ANIMATIONS.map((f) => h('option', { value: f }))));
};

R.box_shadow = (c, ctx) => shadowControl(ctx, ['horizontal', 'vertical', 'blur', 'spread'], c);
R.text_shadow = (c, ctx) => shadowControl(ctx, ['horizontal', 'vertical', 'blur'], c);

function shadowControl(ctx, keys, c) {
    const value = isObj(ctx.value) ? { ...ctx.value } : {};
    const write = (patch) => { Object.assign(value, patch); ctx.set({ ...value }); };
    return h('div', { class: 'lm-shadow' },
        h('div', { class: 'lm-gaps' }, keys.map((k) => field(k, numberInput(value[k] ?? 0, (v) => write({ [k]: v === '' ? 0 : v }))))),
        h('div', { class: 'lm-row' }, h('input', { type: 'color', class: 'lm-color', value: /^#[0-9a-f]{6}$/i.test(value.color || '') ? value.color : '#000000', onInput: (e) => write({ color: e.target.value }) }),
            textInput(value.color ?? 'rgba(0,0,0,0.5)', (v) => write({ color: v }))));
}

R.image_dimensions = (c, ctx) => {
    const value = isObj(ctx.value) ? { ...ctx.value } : {};
    const write = (patch) => { Object.assign(value, patch); ctx.set({ ...value }); };
    return h('div', { class: 'lm-gaps' }, field('Width', numberInput(value.width ?? '', (v) => write({ width: v }))), field('Height', numberInput(value.height ?? '', (v) => write({ height: v }))));
};

R.visual_choice = (c, ctx) => {
    const opts = optionList(c.options);
    return h('div', { class: 'lm-choose lm-visual' }, opts.map((o) => h('button', {
        type: 'button', class: `lm-choose-btn${String(ctx.value) === String(o.value) ? ' is-active' : ''}`, title: o.label, onClick: () => ctx.set(o.value),
    }, o.label)));
};

R.wysiwyg = (c, ctx) => {
    const area = h('div', { class: 'lm-wysiwyg', contenteditable: 'true', onInput: debounce(() => ctx.set(area.innerHTML), 200) });
    area.innerHTML = ctx.value ?? '';
    const cmd = (name, arg, label, title) => h('button', {
        type: 'button', class: 'lm-tool', title, onMouseDown: (e) => e.preventDefault(),
        onClick: () => { document.execCommand(name, false, arg ?? (name === 'createLink' ? (prompt('Link URL') || '') : null)); ctx.set(area.innerHTML); },
    }, label);
    return h('div', { class: 'lm-wysiwyg-wrap' },
        h('div', { class: 'lm-toolbar' }, cmd('bold', null, 'B', 'Bold'), cmd('italic', null, 'I', 'Italic'), cmd('underline', null, 'U', 'Underline'),
            cmd('insertUnorderedList', null, '•', 'Bullet list'), cmd('insertOrderedList', null, '1.', 'Numbered list'), cmd('createLink', undefined, '🔗', 'Link'), cmd('removeFormat', null, '✕', 'Clear formatting')),
        area);
};

R.repeater = (c, ctx) => {
    const fields = c.fields || {};
    let items = Array.isArray(ctx.value) ? clone(ctx.value) : [];
    const list = h('div', { class: 'lm-repeater' });
    const newId = () => Math.random().toString(16).slice(2, 9);
    const defaults = () => Object.fromEntries(Object.entries(fields).filter(([, f]) => 'default' in f).map(([k, f]) => [k, clone(f.default)]));
    const commit = () => ctx.set(clone(items));
    const paint = () => {
        list.replaceChildren(...items.map((item, index) => {
            const merged = { ...defaults(), ...item };
            const body = h('div', { class: 'lm-repeater-body' });
            Object.entries(fields).forEach(([name, f]) => {
                if (['section', 'tab', 'tabs'].includes(f.type) || f.type === 'hidden') return;
                const visible = ctx.visibleField ? ctx.visibleField(f, merged, fields) : true;
                if (!visible) return;
                body.append(ctx.fieldRow(f, merged[name], (v) => { item[name] = v; commit(); }, () => paint()));
            });
            const title = merged[c.title_field?.replace(/[{}]/g, '')] || merged.tab_title || merged.text || merged.title || `Item #${index + 1}`;
            return h('details', { class: 'lm-repeater-item', open: index === items.length - 1 && items.length === 1 ? true : null },
                h('summary', {},
                    h('span', { class: 'lm-repeater-title' }, String(title).replace(/<[^>]+>/g, '').slice(0, 40) || `Item #${index + 1}`),
                    h('span', { class: 'lm-repeater-actions' },
                        h('button', { type: 'button', class: 'lm-x', title: 'Move up', onClick: (e) => { e.preventDefault(); if (index) { [items[index - 1], items[index]] = [items[index], items[index - 1]]; commit(); paint(); } } }, '↑'),
                        h('button', { type: 'button', class: 'lm-x', title: 'Move down', onClick: (e) => { e.preventDefault(); if (index < items.length - 1) { [items[index + 1], items[index]] = [items[index], items[index + 1]]; commit(); paint(); } } }, '↓'),
                        h('button', { type: 'button', class: 'lm-x', title: 'Duplicate', onClick: (e) => { e.preventDefault(); items.splice(index + 1, 0, { ...clone(item), _id: newId() }); commit(); paint(); } }, '⧉'),
                        h('button', { type: 'button', class: 'lm-x', title: 'Delete', onClick: (e) => { e.preventDefault(); items.splice(index, 1); commit(); paint(); } }, '×'))),
                body);
        }));
    };
    paint();
    return h('div', {}, list, h('button', { type: 'button', class: 'lm-btn', onClick: () => { items.push({ ...defaults(), _id: newId() }); commit(); paint(); list.lastElementChild?.setAttribute('open', ''); } }, '+ Add item'));
};

// Structural / informational controls.
R.heading = (c) => h('div', { class: 'lm-heading' }, c.label || '');
R.divider = () => h('hr', { class: 'lm-divider' });
R.raw_html = (c) => { const d = h('div', { class: 'lm-raw' }); d.innerHTML = (c.raw || '').replace(/<script[\s\S]*?<\/script>/gi, ''); return d; };
R.alert = R.notice = (c) => { const d = h('div', { class: `lm-alert lm-alert-${c.alert_type || 'info'}` }); d.innerHTML = [c.header, c.content].filter(Boolean).join(' ').replace(/<script[\s\S]*?<\/script>/gi, ''); return d; };
R.hidden = () => null;
R.structure = () => h('p', { class: 'lm-hint' }, 'Column structure is edited on the canvas.');

/** Fallback for control types without a dedicated widget: a plain text field. */
const fallback = (c, ctx) => textInput(typeof ctx.value === 'string' ? ctx.value : '', ctx.set);

export const STRUCTURAL = new Set(['section', 'tab', 'tabs', 'hidden']);
export const NO_LABEL = new Set(['heading', 'divider', 'raw_html', 'alert', 'notice', 'switcher', 'popover_toggle']);

export function renderControl(control, ctx) {
    return (R[control.type] || fallback)(control, ctx);
}

export { clone, isObj, debounce };
