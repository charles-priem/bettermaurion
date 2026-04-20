(function () {
  if (window.__AURION_ID_COLLECTOR__) {
    console.log('AURION ID collector already loaded.');
    return;
  }

  const PLANNING_GROUPS = [
    { parent: 'Planning ADIMAKER', children: ['Planning ADIMAKER Bordeaux A1', 'Planning ADIMAKER Bordeaux A2', 'Planning ADIMAKER Lille A1', 'Planning ADIMAKER Lille A2'] },
    { parent: 'Planning CPI', children: ['Planning CPI Lille A1', 'Planning CPI Lille A2', 'Planning CPI Lille A3'] },
    { parent: 'Planning HEI Ingénieur', children: ['HEI Ingénieur A3', 'HEI Ingénieur A4', 'HEI Ingénieur A5', 'HEI Ingénieur en Alternance A3', 'HEI Ingénieur en Alternance A4', 'HEI Ingénieur en Alternance A5'] },
    { parent: 'Planning HEI Prépa', children: [] },
    { parent: 'Planning ISA Enviro', children: [] },
    { parent: 'Planning ISA Ingénieur', children: [] },
    { parent: 'Planning ISEN AP', children: [] },
    { parent: 'Planning ISEN CIR', children: [] },
    { parent: 'Planning ISEN CNB', children: ['Planning ISEN CNB1', 'Planning ISEN CNB2', 'Planning ISEN CNB3'] },
    { parent: 'Planning ISEN CPG', children: ['Planning ISEN CPG1', 'Planning ISEN CPG2'] },
    { parent: 'Planning ISEN CSI', children: [] },
    { parent: 'Planning ISEN Master', children: [] },
  ];

  const CONTROL_LABELS = [
    'schedules',
    'select',
    'select all',
    'view schedule',
    'view schedule',
    'today',
    'next',
    'previous',
    'suivante',
    'precedente',
    'aujourd\'hui',
  ];

  const state = {
    entries: [],
    seen: new Set(),
    currentParent: '',
    currentChild: '',
    currentPath: '',
    lastRequestBody: '',
  };

  const STATUS_STORAGE_KEY = '__AURION_LAST_STATUS__';
  const STATUS_OVERLAY_ID = '__aurion_id_collector_status';

  try {
    const persistedEntries = JSON.parse(sessionStorage.getItem('__AURION_ID_ENTRIES__') || '[]');
    if (Array.isArray(persistedEntries)) {
      state.entries = persistedEntries;
      persistedEntries.forEach((entry) => {
        const uniqueKey = String(entry && entry.idInit ? entry.idInit : entry && entry.path ? entry.path : '');
        if (uniqueKey) {
          state.seen.add(uniqueKey);
        }
      });
    }
  } catch (error) {
    // ignore persisted state parsing errors
  }

  function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function getPlanningTargets() {
    const targets = [];
    for (const group of PLANNING_GROUPS) {
      targets.push(group.parent);
      for (const child of group.children) {
        targets.push(child);
      }
    }
    return targets;
  }

  function stripDiacritics(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function normalizeText(value) {
    return stripDiacritics(value)
      .replace(/\s+/g, ' ')
      .trim()
      .toLowerCase();
  }

  function isVisible(element) {
    if (!element) return false;
    if (element.offsetParent !== null) return true;
    const style = window.getComputedStyle(element);
    return style && style.display !== 'none' && style.visibility !== 'hidden';
  }

  function isControlText(value) {
    const text = normalizeText(value);
    if (!text) return true;
    return CONTROL_LABELS.some((label) => text.includes(label));
  }

  function getSearchRoots() {
    const selectors = [
      'aside',
      'nav',
      '.layout-menu',
      '.ui-menu',
      '.ui-tree',
      '.ui-tree-container',
      '.sidebar',
      '.sidebar-menu',
      '.menu',
      '.menu-container',
    ];

    const roots = [];
    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((node) => {
        if (isVisible(node)) {
          roots.push(node);
        }
      });
    });

    return roots.length > 0 ? roots : [document.body];
  }

  function getTreeContainers() {
    const containers = [];
    const seen = new Set();

    for (const root of getSearchRoots()) {
      const lists = root.matches('ul,ol') ? [root] : Array.from(root.querySelectorAll('ul,ol'));
      for (const list of lists) {
        if (!isVisible(list) || seen.has(list)) {
          continue;
        }

        const parent = list.parentElement;
        if (parent && parent.tagName === 'LI') {
          continue;
        }
        seen.add(list);
        containers.push(list);
      }
    }

    return containers;
  }

  function findTreeLabelElement(node) {
    if (!node) return null;

    const selectors = [
      '.ui-treenode-label',
      '.ui-menuitem-text',
      'a',
      'button',
      '[role="treeitem"]',
      'span',
    ];

    for (const selector of selectors) {
      const element = node.querySelector(selector);
      if (!element || !isVisible(element)) {
        continue;
      }

      const text = (element.innerText || element.textContent || '').trim();
      if (text) {
        return element;
      }
    }

    return null;
  }

  function extractSubmenuId(target) {
    if (!target) return '';

    const sources = [];
    const attributes = ['onclick', 'href', 'id', 'name', 'title', 'data-submenu', 'data-submenuid', 'data-id'];

    for (const attribute of attributes) {
      const value = typeof target.getAttribute === 'function' ? target.getAttribute(attribute) : '';
      if (value) {
        sources.push(value);
      }
    }

    if (target.dataset) {
      for (const value of Object.values(target.dataset)) {
        if (value) {
          sources.push(String(value));
        }
      }
    }

    sources.push(target.id || '', target.className || '', target.textContent || '');

    const patterns = [
      /webscolaapp\.Sidebar\.ID_SUBMENU=([^&'"\)\s]+)/,
      /ID_SUBMENU=([^&'"\)\s]+)/,
      /(submenu_\d+)/,
    ];

    for (const source of sources) {
      const haystack = String(source);
      for (const pattern of patterns) {
        const match = haystack.match(pattern);
        if (match) {
          return match[1] || match[0];
        }
      }
    }

    return '';
  }

  function collectPlanningTreeNodes() {
    const nodes = [];
    const seenPaths = new Set();

    function walkList(listElement, ancestors) {
      const items = Array.from(listElement.children).filter((child) => child.tagName === 'LI');

      for (const item of items) {
        if (!isVisible(item)) {
          continue;
        }

        const labelElement = findTreeLabelElement(item);
        const label = (labelElement && (labelElement.innerText || labelElement.textContent || '').trim()) || '';
        if (!label) {
          continue;
        }

        const pathParts = [...ancestors, label];
        const path = pathParts.join(' > ');
        if (seenPaths.has(path)) {
          continue;
        }
        seenPaths.add(path);

        const childList = Array.from(item.children).find((child) => child.tagName === 'UL' || child.tagName === 'OL') || null;
        const submenuId = extractSubmenuId(labelElement) || extractSubmenuId(item);
        const hasChildren = Boolean(childList && childList.querySelector('li'));

        nodes.push({
          label,
          path,
          submenuId,
          isLeaf: !hasChildren,
          depth: ancestors.length,
          item,
          element: labelElement || item,
        });

        if (childList) {
          walkList(childList, pathParts);
        }
      }
    }

    for (const container of getTreeContainers()) {
      if (container.tagName === 'UL' || container.tagName === 'OL') {
        walkList(container, []);
      }
    }

    return nodes;
  }

  function getTreeToggleElement(item) {
    if (!item) return null;

    const toggleSelectors = [
      '.ui-tree-toggler',
      '.ui-treenode-toggler',
      '.ui-icon-triangle-1-e',
      '.ui-icon-caret-1-e',
      '.ui-icon-plus',
      '.tree-toggler',
      '.toggler',
      '[aria-label*="expand" i]',
      '[title*="expand" i]',
      '[aria-label*="open" i]',
      '[title*="open" i]',
    ];

    for (const selector of toggleSelectors) {
      const match = item.querySelector(selector);
      if (match && isVisible(match)) {
        return match;
      }
    }

    const descendants = Array.from(item.querySelectorAll('span,a,button,i')).filter((element) => isVisible(element));
    for (const element of descendants) {
      const className = normalizeText(String(element.className || ''));
      const title = normalizeText(String(element.getAttribute('title') || ''));
      const aria = normalizeText(String(element.getAttribute('aria-label') || ''));
      const text = normalizeText(String(element.textContent || ''));
      const probe = `${className} ${title} ${aria} ${text}`;

      if (/(toggle|toggler|caret|triangle|expand|plus|open)/.test(probe)) {
        return element;
      }
    }

    return null;
  }

  function hasHiddenChildBranch(item) {
    if (!item) return false;

    const childList = Array.from(item.children).find((child) => child.tagName === 'UL' || child.tagName === 'OL') || null;
    if (!childList) {
      return false;
    }

    const childItems = childList.querySelectorAll('li');
    if (!childItems.length) {
      return false;
    }

    return !isVisible(childList) || childList.style.display === 'none' || childList.hidden;
  }

  async function expandTreeItem(item) {
    if (!item || !hasHiddenChildBranch(item)) {
      return false;
    }

    const toggle = getTreeToggleElement(item) || findTreeLabelElement(item);
    if (!toggle) {
      return false;
    }

    const before = collectPlanningTreeNodes().length;
    const clicked = clickElement(toggle);
    if (!clicked) {
      return false;
    }

    await sleep(700);
    const after = collectPlanningTreeNodes().length;
    return after > before;
  }

  async function expandAllPlanningBranches(maxPasses = 8) {
    let previousCount = -1;

    for (let pass = 0; pass < maxPasses; pass++) {
      const treeItems = collectPlanningTreeNodes();
      const expandableItems = treeItems.filter((node) => {
        const element = node.item || node.element;
        return element && hasHiddenChildBranch(element);
      });

      const currentCount = treeItems.length;
      if (currentCount === previousCount && expandableItems.length === 0) {
        break;
      }

      previousCount = currentCount;

      let expandedAny = false;
      for (const node of expandableItems) {
        const expanded = await expandTreeItem(node.element);
        if (expanded) {
          expandedAny = true;
          await sleep(250);
        }
      }

      if (!expandedAny) {
        break;
      }

      await sleep(600);
    }

    return collectPlanningTreeNodes();
  }

  function snapshotPlanningMenu() {
    const nodes = collectPlanningTreeNodes();
    const snapshot = nodes.map((node) => ({
      label: node.label,
      path: node.path,
      submenuId: node.submenuId,
      isLeaf: node.isLeaf,
      depth: node.depth,
    }));

    sessionStorage.setItem('__AURION_MENU_SNAPSHOT__', JSON.stringify(snapshot));
    return snapshot;
  }

  function getVisibleTextNodes(root) {
    const selectors = ['a', 'button', 'span', 'li', 'div', 'label'];
    const items = [];
    const seen = new Set();

    selectors.forEach((selector) => {
      root.querySelectorAll(selector).forEach((element) => {
        if (!isVisible(element)) return;
        const text = (element.innerText || element.textContent || '').trim();
        if (!text) return;
        const normalized = normalizeText(text);
        if (seen.has(normalized)) return;
        seen.add(normalized);
        items.push({ text, element, normalized });
      });
    });

    return items;
  }

  function findClickableTarget(element) {
    if (!element) return null;
    return element.closest('a,button,[role="button"],li') || element;
  }

  function findVisibleClickableByText(targetText, opts = {}) {
    const exact = opts.exact !== false;
    const expected = normalizeText(targetText);
    const roots = getSearchRoots();

    for (const root of roots) {
      const candidates = getVisibleTextNodes(root);
      for (const item of candidates) {
        const text = item.normalized;
        const matches = exact ? text === expected : text.includes(expected);
        if (!matches) continue;
        const clickable = findClickableTarget(item.element);
        if (clickable && isVisible(clickable)) {
          return clickable;
        }
      }
    }

    return null;
  }

  function clickElement(element) {
    if (!element) return false;
    try {
      element.scrollIntoView({ block: 'center', inline: 'center' });
    } catch (error) {
      // ignore
    }
    try {
      element.click();
      return true;
    } catch (error) {
      try {
        element.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
        return true;
      } catch (error2) {
        return false;
      }
    }
  }

  async function clickByText(targetText, opts = {}) {
    const exact = opts.exact !== false;
    const element = findVisibleClickableByText(targetText, { exact });
    if (!element) return false;
    const clicked = clickElement(element);
    if (clicked) {
      await sleep(opts.delay || 1000);
    }
    return clicked;
  }

  function bodyToParams(data) {
    if (!data) return {};

    if (typeof data === 'string') {
      return Object.fromEntries(new URLSearchParams(data));
    }

    if (typeof URLSearchParams !== 'undefined' && data instanceof URLSearchParams) {
      return Object.fromEntries(data.entries());
    }

    if (typeof FormData !== 'undefined' && data instanceof FormData) {
      return Object.fromEntries(data.entries());
    }

    if (typeof data === 'object') {
      return { ...data };
    }

    return {};
  }

  function parsePlanningResponse(responseText) {
    const match = String(responseText || '').match(/<!\[CDATA\[(.*?)\]\]>/s);
    if (!match) return null;

    const content = match[1].trim();
    try {
      return JSON.parse(content);
    } catch (error) {
      return null;
    }
  }

  function ensureStatusOverlay() {
    let overlay = document.getElementById(STATUS_OVERLAY_ID);
    if (overlay) {
      return overlay;
    }

    overlay = document.createElement('div');
    overlay.id = STATUS_OVERLAY_ID;
    overlay.style.position = 'fixed';
    overlay.style.right = '16px';
    overlay.style.bottom = '16px';
    overlay.style.zIndex = '2147483647';
    overlay.style.maxWidth = '420px';
    overlay.style.padding = '12px 14px';
    overlay.style.borderRadius = '12px';
    overlay.style.border = '1px solid rgba(74, 222, 128, 0.5)';
    overlay.style.background = 'rgba(14, 15, 20, 0.96)';
    overlay.style.color = '#e8e9ed';
    overlay.style.font = '12px/1.45 monospace';
    overlay.style.whiteSpace = 'pre-line';
    overlay.style.boxShadow = '0 12px 32px rgba(0, 0, 0, 0.35)';
    overlay.style.pointerEvents = 'none';
    overlay.textContent = 'AURION ID collector ready';

    const mount = document.body || document.documentElement;
    if (mount) {
      mount.appendChild(overlay);
    }

    return overlay;
  }

  function setStatus(message, details = '') {
    const payload = {
      timestamp: new Date().toISOString(),
      message,
      details,
    };

    sessionStorage.setItem(STATUS_STORAGE_KEY, JSON.stringify(payload));

    const overlay = ensureStatusOverlay();
    overlay.textContent = details ? `${message}\n${details}` : message;
  }

  function restoreStatus() {
    try {
      const raw = sessionStorage.getItem(STATUS_STORAGE_KEY);
      if (raw) {
        const payload = JSON.parse(raw);
        if (payload && payload.message) {
          setStatus(String(payload.message), String(payload.details || ''));
          return;
        }
      }
    } catch (error) {
      // ignore
    }

    if (state.entries.length > 0) {
      const last = state.entries[state.entries.length - 1];
      const message = `${last.codesCount || 0} codes détectés`;
      const details = [last.path || '', last.idInit ? `idInit: ${last.idInit}` : '', last.submenuId ? `submenu: ${last.submenuId}` : '']
        .filter(Boolean)
        .join(' | ');
      setStatus(message, details);
    }
  }

  function readCurrentIdInit() {
    const input = document.querySelector('input[name="form:idInit"]');
    if (input && String(input.value || '').trim()) {
      return String(input.value).trim();
    }

    const rawBody = state.lastRequestBody || sessionStorage.getItem('__AURION_LAST_REQUEST_BODY__') || '';
    if (!rawBody) {
      return '';
    }

    try {
      const params = new URLSearchParams(rawBody);
      return String(params.get('form:idInit') || '').trim();
    } catch (error) {
      return '';
    }
  }

  function readCurrentSubmenuId() {
    const rawBody = state.lastRequestBody || sessionStorage.getItem('__AURION_LAST_REQUEST_BODY__') || '';
    if (!rawBody) {
      return '';
    }

    try {
      const params = new URLSearchParams(rawBody);
      return String(params.get('webscolaapp.Sidebar.ID_SUBMENU') || '').trim();
    } catch (error) {
      return '';
    }
  }

  function extractCodeTableRows() {
    const rows = [];
    const rowElements = Array.from(document.querySelectorAll('table tr'));

    for (const row of rowElements) {
      if (!isVisible(row)) {
        continue;
      }

      const cells = Array.from(row.querySelectorAll('th,td'))
        .map((cell) => String(cell.textContent || '').replace(/\s+/g, ' ').trim())
        .filter(Boolean);

      if (cells.length < 2) {
        continue;
      }

      const code = cells[0];
      if (!/^\d{4}_[A-Z0-9_]+$/i.test(code)) {
        continue;
      }

      rows.push({
        code,
        label: cells[1] || '',
        validUntil: cells[2] || '',
        kind: cells[3] || '',
      });
    }

    return rows;
  }

  function captureSelectionSnapshot() {
    const idInit = readCurrentIdInit();
    const submenuId = readCurrentSubmenuId();
    const codeRows = extractCodeTableRows();

    const entry = {
      timestamp: new Date().toISOString(),
      pageUrl: window.location.href,
      parent: state.currentParent,
      child: state.currentChild,
      path: state.currentPath,
      idInit,
      submenuId,
      codes: codeRows,
      codesCount: codeRows.length,
      lastRequestBody: state.lastRequestBody,
    };

    storeEntry(entry);
    setStatus(
      `${entry.codesCount} codes détectés`,
      [
        entry.path || 'Chemin inconnu',
        entry.idInit ? `idInit: ${entry.idInit}` : '',
        entry.submenuId ? `submenu: ${entry.submenuId}` : '',
      ].filter(Boolean).join(' | ')
    );
    return entry;
  }

  function storeEntry(entry) {
    const key = String(entry.idInit || entry.submenuId || entry.path || [entry.parent || '', entry.child || ''].join(' > ')).trim();

    if (!key || state.seen.has(key)) {
      return;
    }

    state.seen.add(key);
    state.entries.push(entry);
    sessionStorage.setItem('__AURION_ID_ENTRIES__', JSON.stringify(state.entries));
  }

  function attachNetworkHooks() {
    if (window.__AURION_ID_COLLECTOR_HOOKED__) {
      return;
    }

    window.__AURION_ID_COLLECTOR_HOOKED__ = true;

    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
      this.__aurionMethod = method;
      this.__aurionUrl = url;
      return originalOpen.call(this, method, url, ...rest);
    };

    XMLHttpRequest.prototype.send = function (data) {
      const method = String(this.__aurionMethod || '').toUpperCase();
      const url = String(this.__aurionUrl || '');

      if (/Planning\.xhtml/i.test(url) && method === 'POST') {
        const params = bodyToParams(data);
        const pendingEntry = {
          timestamp: new Date().toISOString(),
          pageUrl: window.location.href,
          parent: state.currentParent,
          child: state.currentChild,
          path: state.currentPath,
          idInit: params['form:idInit'] || '',
          viewState: params['javax.faces.ViewState'] || '',
          dateInput: params['form:date_input'] || '',
          week: params['form:week'] || '',
          start: params['form:j_idt118_start'] || '',
          end: params['form:j_idt118_end'] || '',
          rawBody: typeof data === 'string' ? data : new URLSearchParams(params).toString(),
        };

        state.lastRequestBody = pendingEntry.rawBody;
        sessionStorage.setItem('__AURION_LAST_REQUEST_BODY__', state.lastRequestBody);

        const onLoadHandler = () => {
          const parsed = parsePlanningResponse(this.responseText);
          if (parsed && Array.isArray(parsed.events)) {
            pendingEntry.eventsCount = parsed.events.length;
          }

          storeEntry(pendingEntry);

          try {
            const currentText = document.body ? document.body.innerText : '';
            pendingEntry.pageTitle = currentText ? currentText.split('\n').slice(0, 3).join(' | ') : '';
          } catch (error) {
            // ignore
          }
        };

        this.addEventListener('load', onLoadHandler, { once: true });
      }

      return originalSend.call(this, data);
    };
  }

  function getCurrentPlanningButtons() {
    const buttons = [];
    const labels = ['select all', 'select', 'view schedule', 'view schedule'];
    labels.forEach((label) => {
      const element = findVisibleClickableByText(label, { exact: false });
      if (element) {
        buttons.push(element);
      }
    });
    return buttons;
  }

  function hasPlanningActions() {
    return Boolean(findVisibleClickableByText('view schedule', { exact: false }));
  }

  function stripPlanningPrefix(label) {
    return normalizeText(label).replace(/^planning\s+/i, '');
  }

  function discoverChildren(parentLabel) {
    const parentKey = stripPlanningPrefix(parentLabel);
    const roots = getSearchRoots();
    const results = [];
    const seen = new Set();

    roots.forEach((root) => {
      getVisibleTextNodes(root).forEach((item) => {
        const text = item.text;
        const normalized = item.normalized;

        if (!looksLikePlanningLabel(text)) return;
        if (normalized === normalizeText(parentLabel)) return;
        if (normalized === normalizeText(parentKey)) return;

        const containsParent = normalized.includes(normalizeText(parentKey));
        const looksLikeChildCode = /\b(a\d|cir\d|csi\d|cpg\d|bordeaux|lille|ingenieur|enviro|master|prepa)\b/.test(normalized);

        if (!containsParent && !looksLikeChildCode) return;

        if (seen.has(normalized)) return;
        seen.add(normalized);
        results.push(text);
      });
    });

    return results;
  }

  function looksLikePlanningLabel(text) {
    const normalized = normalizeText(text);
    if (!normalized) return false;
    if (isControlText(normalized)) return false;
    if (normalized.length > 80) return false;
    return /planning|isen|hei|cpi|isa|adimaker|cir|csi|cpg|master|enviro|prepa|ingenieur|bordeaux|lille|a\d/.test(normalized);
  }

  function setContext(parent, child) {
    state.currentParent = parent || '';
    state.currentChild = child || '';
    state.currentPath = [state.currentParent, state.currentChild].filter(Boolean).join(' > ');

    sessionStorage.setItem('__AURION_CURRENT_PARENT__', state.currentParent);
    sessionStorage.setItem('__AURION_CURRENT_CHILD__', state.currentChild);
    sessionStorage.setItem('__AURION_CURRENT_PATH__', state.currentPath);
  }

  async function triggerScheduleView() {
    const selectClicked = await clickByText('select all', { exact: false, delay: 400 })
      || await clickByText('select', { exact: false, delay: 400 });

    if (!selectClicked) {
      // Some views only expose the button after a row selection.
    }

    const viewClicked = await clickByText('view schedule', { exact: false, delay: 1500 });
    return viewClicked;
  }

  async function captureCurrentSelection(parentLabel, childLabel) {
    setContext(parentLabel, childLabel);
    await sleep(600);
    return captureSelectionSnapshot();
  }

  async function openPlanningMenu() {
    await clickByText('schedules', { exact: false, delay: 900 });
    await clickByText('plannings groupes par promotion', { exact: false, delay: 1200 });
  }

  async function collectAllIds() {
    const targets = getPlanningTargets();

    if (targets.length === 0) {
      console.warn('Aucun planning configuré dans le collecteur.');
      return state.entries.slice();
    }

    setStatus(
      `${targets.length} plannings configurés`,
      'La collecte est en cours. Le compteur affiché est le nombre total de nœuds connus, pas seulement les feuilles visibles.'
    );

    console.log(`[AURION] ${targets.length} plannings à traiter.`);

    for (const group of PLANNING_GROUPS) {
      const parentClicked = await clickByText(group.parent, { exact: true, delay: 900 });
      if (!parentClicked) {
        console.warn(`[AURION] Parent introuvable: ${group.parent}`);
        continue;
      }

      if (group.children.length === 0) {
        const parentParts = group.parent.split(' > ').map((part) => part.trim()).filter(Boolean);
        const parent = parentParts.length > 1 ? parentParts.slice(0, -1).join(' > ') : group.parent;
        const child = parentParts[parentParts.length - 1] || group.parent;
        await captureCurrentSelection(parent, child);
        await sleep(900);
        continue;
      }

      for (const childLabel of group.children) {
        const clicked = await clickByText(childLabel, { exact: true, delay: 900 });
        if (!clicked) {
          console.warn(`[AURION] Enfant introuvable: ${group.parent} > ${childLabel}`);
          continue;
        }

        await captureCurrentSelection(group.parent, childLabel);
        await sleep(900);
      }

      const parentClickedAgain = await clickByText(group.parent, { exact: true, delay: 500 });
      if (!parentClickedAgain) {
        await sleep(500);
      }
    }

    console.log('AURION ID collector finished. Use __AURION_ID_COLLECTOR__.exportJson() to download the result.');
    return state.entries.slice();
  }

  function getEntries() {
    return state.entries.slice();
  }

  function buildExportPayload() {
    return {
      timestamp: new Date().toISOString(),
      pageUrl: window.location.href,
      totalEntries: state.entries.length,
      entries: getEntries(),
    };
  }

  function downloadJson(filename, payload) {
    const json = JSON.stringify(payload, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
  }

  function exportJson(filename = 'aurion-planning-ids.json') {
    downloadJson(filename, buildExportPayload());
  }

  async function copyJson() {
    const json = JSON.stringify(buildExportPayload(), null, 2);
    try {
      await navigator.clipboard.writeText(json);
      console.log('AURION ID collector JSON copied to clipboard.');
    } catch (error) {
      console.warn('Clipboard copy failed. Falling back to a download.');
      exportJson();
    }
  }

  function clear() {
    state.entries = [];
    state.seen.clear();
    state.currentParent = '';
    state.currentChild = '';
    state.currentPath = '';
    state.lastRequestBody = '';
    sessionStorage.removeItem('__AURION_ID_ENTRIES__');
    sessionStorage.removeItem('__AURION_LAST_REQUEST_BODY__');
    sessionStorage.removeItem('__AURION_CURRENT_PARENT__');
    sessionStorage.removeItem('__AURION_CURRENT_CHILD__');
    sessionStorage.removeItem('__AURION_CURRENT_PATH__');
  }

  attachNetworkHooks();
  restoreStatus();

  window.__AURION_ID_COLLECTOR__ = {
    state,
    collectAllIds,
    getPlanningTargets,
    expandAllPlanningBranches,
    snapshotPlanningMenu,
    exportJson,
    copyJson,
    clear,
    setStatus,
    restoreStatus,
    setContext,
    getEntries,
    openPlanningMenu,
    discoverChildren,
    captureCurrentSelection,
    captureSelectionSnapshot,
    extractCodeTableRows,
    readCurrentIdInit,
    readCurrentSubmenuId,
    getCurrentPlanningButtons,
  };

  console.log('AURION ID collector loaded.');
  console.log('Open Aurion, stay on the planning tree / code table page, then run: __AURION_ID_COLLECTOR__.collectAllIds()');
})();