// Runs the BERI pages outside the design canvas.
// Each page keeps its markup in <template id="dc-template"> ({{holes}}, <sc-for>, <sc-if>) and its logic in a
// <script type="text/x-dc"> Component class. This renders the markup into #dc-root and patches the live DOM
// on every setState, so elements (videos, revealed sections) keep their state between renders.
(function () {
  'use strict';

  const HOLE = /\{\{\s*([^}]+?)\s*\}\}/g;
  // Page links in the designs point at canvas file names; map them to the published files.
  const PAGES = { 'home-color-images': 'index.html', 'beri-premium': 'index.html', cliq: 'cliq.html', 'crush-v2': 'crush.html', crush: 'crush.html', 'cirql-v2': 'cirql.html', cirql: 'cirql.html', eliquid: 'eliquid.html', authenticate: 'authenticate.html', wholesale: 'wholesale.html', support: 'support.html' };

  function lookup(scope, path) {
    if (path === 'true') return true;
    if (path === 'false') return false;
    if (path === 'null') return null;
    let v = scope;
    for (const key of path.split('.')) {
      if (v == null) return undefined;
      v = v[key];
    }
    return v;
  }

  function fill(str, scope) {
    return str.replace(HOLE, (m, path) => {
      const v = lookup(scope, path);
      return v == null || v === false ? '' : String(v);
    });
  }

  function soleHole(str) {
    const m = /^\s*\{\{\s*([^}]+?)\s*\}\}\s*$/.exec(str);
    return m ? m[1] : null;
  }

  // On the WordPress theme, window.BERI_PAGES maps the same names to the site's real page URLs.
  function fixHref(v) {
    const wp = window.BERI_PAGES || null;
    return v.replace(/^([A-Za-z0-9-]+)\.dc\.html/, (m, name) => {
      const key = name.toLowerCase();
      if (wp && wp[key]) return wp[key];
      return PAGES[key] || key + '.html';
    });
  }

  // Subtrees with no {{holes}}, <sc-for> or <sc-if> never change between renders. They are cloned once and
  // skipped on every later render, so a scroll frame only rebuilds the parts of the page that can change.
  const STATIC = new WeakMap();
  function isStatic(node) {
    if (STATIC.has(node)) return STATIC.get(node);
    let ok;
    if (node.nodeType === 3) ok = node.nodeValue.indexOf('{{') < 0;
    else if (node.nodeType !== 1) ok = true;
    else if (node.localName === 'sc-for' || node.localName === 'sc-if') ok = false;
    else {
      ok = !Array.from(node.attributes).some((a) => a.value.indexOf('{{') >= 0);
      if (ok) for (const c of node.childNodes) { if (!isStatic(c)) { ok = false; break; } }
    }
    STATIC.set(node, ok);
    return ok;
  }

  function cloneStatic(node) {
    const el = node.cloneNode(true);
    const all = [el].concat(Array.from(el.querySelectorAll('*')));
    all.forEach((e) => {
      Array.from(e.attributes).forEach((a) => { if (a.name.startsWith('hint-')) e.removeAttribute(a.name); });
      if (e.hasAttribute('href')) e.setAttribute('href', fixHref(e.getAttribute('href')));
      if (e.localName === 'video' && e.hasAttribute('muted')) e.muted = true;
    });
    el.__dcStat = node;
    return el;
  }

  // Template -> virtual nodes: { text } or { tag, ns, attrs: [[name, value]], on: {event: fn}, kids }.
  function build(node, scope, out) {
    if (node.nodeType === 3) {
      out.push({ text: fill(node.nodeValue, scope) });
      return;
    }
    if (node.nodeType !== 1) return;
    if (node.parentNode && isStatic(node)) {
      out.push({ stat: node });
      return;
    }
    const tag = node.localName;
    if (tag === 'sc-for') {
      const list = lookup(scope, soleHole(node.getAttribute('list')) || '') || [];
      const as = node.getAttribute('as');
      list.forEach((item) => {
        const inner = Object.create(scope);
        inner[as] = item;
        node.childNodes.forEach((c) => build(c, inner, out));
      });
      return;
    }
    if (tag === 'sc-if') {
      if (lookup(scope, soleHole(node.getAttribute('value')) || '')) node.childNodes.forEach((c) => build(c, scope, out));
      return;
    }
    const v = { tag: tag, ns: node.namespaceURI, attrs: [], on: {}, kids: [] };
    for (const a of Array.from(node.attributes)) {
      if (a.name.startsWith('hint-')) continue;
      const path = soleHole(a.value);
      if (path) {
        const val = lookup(scope, path);
        if (/^on[a-z]/i.test(a.name) && typeof val === 'function') {
          let ev = a.name.slice(2).toLowerCase();
          if (ev === 'change' && (tag === 'input' || tag === 'textarea')) ev = 'input';
          v.on[ev] = val;
          continue;
        }
        if (val === true) v.attrs.push([a.name, '']);
        else if (val == null || val === false) continue;
        else v.attrs.push([a.name, a.name === 'href' ? fixHref(String(val)) : String(val)]);
      } else {
        const val = a.value.indexOf('{{') >= 0 ? fill(a.value, scope) : a.value;
        v.attrs.push([a.name, a.name === 'href' ? fixHref(val) : val]);
      }
    }
    node.childNodes.forEach((c) => build(c, scope, v.kids));
    out.push(v);
  }

  function bindEvents(el, on) {
    el.__dcOn = on;
    el.__dcBound = el.__dcBound || {};
    for (const ev of Object.keys(on)) {
      if (el.__dcBound[ev]) continue;
      el.__dcBound[ev] = true;
      el.addEventListener(ev, (e) => {
        const fn = el.__dcOn && el.__dcOn[ev];
        if (fn) fn(e);
      });
    }
  }

  function create(v) {
    if (v.stat) return cloneStatic(v.stat);
    if ('text' in v) return document.createTextNode(v.text);
    const el = v.ns && v.ns !== 'http://www.w3.org/1999/xhtml' ? document.createElementNS(v.ns, v.tag) : document.createElement(v.tag);
    v.attrs.forEach(([n, val]) => el.setAttribute(n, val));
    el.__dcAttrs = v.attrs.map((a) => a[0]);
    if (v.tag === 'video' && v.attrs.some((a) => a[0] === 'muted')) el.muted = true;
    bindEvents(el, v.on);
    v.kids.forEach((k) => el.appendChild(create(k)));
    return el;
  }

  function sameKind(el, v) {
    if (v.stat) return el.__dcStat === v.stat;
    if ('text' in v) return el.nodeType === 3;
    return el.nodeType === 1 && el.localName === v.tag && el.namespaceURI === (v.ns || 'http://www.w3.org/1999/xhtml');
  }

  function patch(el, v) {
    if (v.stat) return;
    if ('text' in v) {
      if (el.nodeValue !== v.text) el.nodeValue = v.text;
      return;
    }
    const names = v.attrs.map((a) => a[0]);
    // Only remove attributes this runtime set; page scripts may add their own (data-in, playsinline...).
    (el.__dcAttrs || []).forEach((n) => { if (names.indexOf(n) < 0) el.removeAttribute(n); });
    v.attrs.forEach(([n, val]) => {
      if (el.getAttribute(n) !== val) el.setAttribute(n, val);
      if (n === 'value' && 'value' in el && el.value !== val && document.activeElement !== el) el.value = val;
    });
    el.__dcAttrs = names;
    bindEvents(el, v.on);
    patchKids(el, v.kids);
  }

  function patchKids(parent, kids) {
    const cur = Array.from(parent.childNodes);
    kids.forEach((v, i) => {
      const el = cur[i];
      if (!el) parent.appendChild(create(v));
      else if (sameKind(el, v)) patch(el, v);
      else parent.replaceChild(create(v), el);
    });
    for (let i = kids.length; i < cur.length; i++) parent.removeChild(cur[i]);
  }

  class DCLogic {
    constructor(props) {
      this.props = props || {};
      this.state = {};
    }
    setState(change) {
      const next = typeof change === 'function' ? change(this.state, this.props) : change;
      this.state = Object.assign({}, this.state, next);
      this.__queue();
    }
    forceUpdate() {
      this.__queue();
    }
  }

  function start() {
    const tpl = document.getElementById('dc-template');
    const root = document.getElementById('dc-root');
    const script = document.querySelector('script[type="text/x-dc"]');
    if (!tpl || !root || !script) return;
    const Component = new Function('DCLogic', script.textContent + '\nreturn Component;')(DCLogic);
    const props = {};
    try {
      const spec = JSON.parse(script.getAttribute('data-props') || '{}');
      Object.keys(spec).forEach((k) => { if (k.charAt(0) !== '$' && spec[k] && 'default' in spec[k]) props[k] = spec[k].default; });
    } catch (e) { /* no props */ }
    const inst = new Component(props);
    let mounted = false;
    let queued = false;
    const render = () => {
      queued = false;
      const scope = Object.assign({}, inst.props, inst.state, inst.renderVals ? inst.renderVals() : {});
      const out = [];
      tpl.content.childNodes.forEach((c) => build(c, scope, out));
      patchKids(root, out);
      if (mounted && inst.componentDidUpdate) inst.componentDidUpdate();
    };
    inst.__queue = () => {
      if (!mounted || queued) return;
      queued = true;
      Promise.resolve().then(render);
    };
    render();
    mounted = true;
    if (inst.componentDidMount) inst.componentDidMount();
  }

  // iPhones block autoplay in Low Power Mode and only load a video's frames once it has played. On the first
  // touch, start the looping videos that should be playing and briefly play/pause the scroll-driven ones.
  function unlockVideos() {
    document.removeEventListener('touchstart', unlockVideos, true);
    document.removeEventListener('click', unlockVideos, true);
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('video').forEach((v) => {
      const mode = v.getAttribute('data-mode');
      const r = v.getBoundingClientRect();
      const onScreen = r.bottom > 0 && r.top < (window.innerHeight || 800);
      if (mode === 'play' || (v.loop && onScreen)) {
        if (v.paused) { const p = v.play(); if (p && p.catch) p.catch(() => {}); }
      } else if ((mode === 'scrub' || v.id === 'hero-video') && v.readyState < 2) {
        const p = v.play();
        if (p && p.then) p.then(() => v.pause(), () => {});
      }
    });
  }
  document.addEventListener('touchstart', unlockVideos, { capture: true, passive: true });
  document.addEventListener('click', unlockVideos, true);

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
