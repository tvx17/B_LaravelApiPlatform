(() => {
  const excluded =
    'script,style,noscript,template,code,pre,kbd,samp,textarea,input,select,[contenteditable]:not([contenteditable="false"]),[translate="no"],[data-leseklar-ignore]';
  const blocks = new Set([
    'ADDRESS',
    'ARTICLE',
    'ASIDE',
    'BLOCKQUOTE',
    'BODY',
    'DD',
    'DIV',
    'DL',
    'DT',
    'FIGCAPTION',
    'FIGURE',
    'FOOTER',
    'FORM',
    'H1',
    'H2',
    'H3',
    'H4',
    'H5',
    'H6',
    'HEADER',
    'LI',
    'MAIN',
    'NAV',
    'OL',
    'P',
    'SECTION',
    'TABLE',
    'TD',
    'TH',
    'TR',
    'UL',
  ]);
  const attributes = [
    'aria-label',
    'aria-description',
    'aria-valuetext',
    'alt',
    'title',
  ];

  function groups(root) {
    const result = [];
    let current = [];
    const flush = () => {
      if (current.length) result.push(current);
      current = [];
    };
    function visit(node) {
      if (node.nodeType === 3) {
        current.push(node);
        return;
      }
      if (node.nodeType !== 1 && node.nodeType !== 9) return;
      if (node.nodeType === 1 && node.matches(excluded)) {
        flush();
        return;
      }
      const boundary =
        blocks.has(node.tagName) ||
        node.tagName === 'BR' ||
        node.tagName === 'HR';
      if (boundary) flush();
      for (const child of node.childNodes) visit(child);
      if (boundary) flush();
    }
    if (root.nodeType === 1 && root.closest(excluded)) return [];
    visit(root);
    flush();
    return result;
  }

  function createProcessor(document, host) {
    const textState = new WeakMap();
    const attributeState = new WeakMap();

    function original(state, value) {
      return state && state.rendered === value ? state.original : value;
    }

    function scan(root, settings) {
      let changed = 0;
      for (const nodes of groups(root)) {
        const sources = nodes.map((node) =>
          original(textState.get(node), node.data),
        );
        // Limit regex input size. Long blocks are handled one node at a time.
        const output =
          sources.join('').length <= 30000
            ? LeseklarEngine.transformParts(sources, settings, host)
            : sources.map((text) =>
                text.length <= 30000
                  ? LeseklarEngine.transform(text, settings, host)
                  : text,
              );
        nodes.forEach((node, index) => {
          textState.set(node, {
            original: sources[index],
            rendered: output[index],
          });
          if (node.data !== output[index]) {
            node.data = output[index];
            changed++;
          }
        });
      }
      const elements = [
        ...(root.nodeType === 1 ? [root] : []),
        ...root.querySelectorAll('*'),
      ];
      for (const element of elements) {
        if (element.closest(excluded)) continue;
        const states = attributeState.get(element) || {};
        for (const name of attributes) {
          if (!element.hasAttribute(name)) {
            delete states[name];
            continue;
          }
          const current = element.getAttribute(name);
          const source = original(states[name], current);
          const value =
            settings.accessibleLabels && source.length <= 30000
              ? LeseklarEngine.transform(source, settings, host)
              : source;
          states[name] = { original: source, rendered: value };
          if (value !== current) {
            element.setAttribute(name, value);
            changed++;
          }
        }
        attributeState.set(element, states);
      }
      return changed;
    }

    function restore() {
      const walker = document.createTreeWalker(document, 4);
      while (walker.nextNode()) {
        const node = walker.currentNode;
        const state = textState.get(node);
        if (state && node.data === state.rendered) node.data = state.original;
        textState.delete(node);
      }
      for (const element of document.querySelectorAll('*')) {
        const states = attributeState.get(element);
        for (const [name, state] of Object.entries(states || {})) {
          if (element.getAttribute(name) === state.rendered)
            element.setAttribute(name, state.original);
        }
        attributeState.delete(element);
      }
    }

    function scope(node) {
      let element = node.nodeType === 1 ? node : node.parentElement;
      if (!element || element.closest(excluded)) return null;
      while (element.parentElement && !blocks.has(element.tagName))
        element = element.parentElement;
      return element;
    }
    return { scan, restore, scope };
  }
  globalThis.LeseklarDOM = { createProcessor };
})();
