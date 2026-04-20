#!/usr/bin/env python3
"""
Aurion capture helper.

What it does:
- Opens Aurion in Chrome via Selenium.
- Keeps a persistent request-body watcher across page refreshes.
- Clicks the planning labels one by one.
- Captures idInit, submenu id, and the code table rows into JSON.

Usage:
    python aurion_capture_python.py --output aurion-all-plannings.json

Optional:
    python aurion_capture_python.py --manual-login
    python aurion_capture_python.py --manual-capture
    python aurion_capture_python.py --username YOUR_EMAIL --password YOUR_PASSWORD
"""

from __future__ import annotations

import argparse
import json
import re
import time
import unicodedata
from getpass import getpass
from pathlib import Path
from typing import Dict, Iterable, List, Optional
from urllib.parse import parse_qs

from selenium import webdriver
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

BASE_URL = "https://aurion.junia.com"
LOGIN_URL = f"{BASE_URL}/login"
CHOIX_URL = f"{BASE_URL}/faces/ChoixPlanning.xhtml"
DEFAULT_OUTPUT = "aurion-all-plannings.json"

PLANNING_GROUPS = [
    ("Planning ADIMAKER", ["Planning ADIMAKER Bordeaux A1", "Planning ADIMAKER Bordeaux A2", "Planning ADIMAKER Lille A1", "Planning ADIMAKER Lille A2"]),
    ("Planning CPI", ["Planning CPI Lille A1", "Planning CPI Lille A2", "Planning CPI Lille A3"]),
    ("Planning HEI Ingenieur", ["HEI Ingenieur A3", "HEI Ingenieur A4", "HEI Ingenieur A5", "HEI Ingenieur en Alternance A3", "HEI Ingenieur en Alternance A4", "HEI Ingenieur en Alternance A5"]),
    ("Planning HEI Prepa", []),
    ("Planning ISA Enviro", []),
    ("Planning ISA Ingenieur", []),
    ("Planning ISEN AP", []),
    ("Planning ISEN CIR", []),
    ("Planning ISEN CNB", ["Planning ISEN CNB1", "Planning ISEN CNB2", "Planning ISEN CNB3"]),
    ("Planning ISEN CPG", ["Planning ISEN CPG1", "Planning ISEN CPG2"]),
    ("Planning ISEN CSI", []),
    ("Planning ISEN Master", []),
]

VISIBLE_ROOTS_JS = """
return Array.from(document.querySelectorAll('aside, nav, .layout-menu, .ui-menu, .ui-tree, .ui-tree-container, .sidebar, .sidebar-menu, .menu, .menu-container, body'));
"""

WATCHER_JS = r"""
(() => {
  if (window.__aurion_python_watcher_installed__) {
    return;
  }
  window.__aurion_python_watcher_installed__ = true;

  const BODY_KEY = '__AURION_LAST_REQUEST_BODY__';
  const META_KEY = '__AURION_LAST_REQUEST_META__';

  function serializeBody(data) {
    try {
      if (!data) {
        return '';
      }
      if (typeof data === 'string') {
        return data;
      }
      if (typeof URLSearchParams !== 'undefined' && data instanceof URLSearchParams) {
        return data.toString();
      }
      if (typeof FormData !== 'undefined' && data instanceof FormData) {
        return new URLSearchParams(data).toString();
      }
      return String(data);
    } catch (error) {
      return '';
    }
  }

  function persist(url, method, body) {
    try {
      sessionStorage.setItem(BODY_KEY, body || '');
      sessionStorage.setItem(META_KEY, JSON.stringify({
        timestamp: new Date().toISOString(),
        url: url || '',
        method: method || '',
        body: body || '',
      }));
    } catch (error) {
      // ignore
    }
  }

  const originalOpen = XMLHttpRequest.prototype.open;
  const originalSend = XMLHttpRequest.prototype.send;

  XMLHttpRequest.prototype.open = function(method, url, ...rest) {
    this.__aurion_method = method;
    this.__aurion_url = url;
    return originalOpen.call(this, method, url, ...rest);
  };

  XMLHttpRequest.prototype.send = function(data) {
    const method = String(this.__aurion_method || '').toUpperCase();
    const url = String(this.__aurion_url || '');
    if (/ChoixPlanning\.xhtml|Planning\.xhtml/i.test(url)) {
      persist(url, method, serializeBody(data));
    }
    return originalSend.call(this, data);
  };

  if (typeof window.fetch === 'function') {
    const originalFetch = window.fetch.bind(window);
    window.fetch = function(input, init) {
      try {
        const url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
        const method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
        const body = serializeBody(init && init.body ? init.body : (input && input.body ? input.body : ''));
        if (/ChoixPlanning\.xhtml|Planning\.xhtml/i.test(url)) {
          persist(url, method, body);
        }
      } catch (error) {
        // ignore
      }
      return originalFetch(input, init);
    };
  }
})();
"""

CODE_REGEX = re.compile(r"^\d{4}_[A-Z0-9_]+$", re.IGNORECASE)


def normalize_text(value: str) -> str:
    text = unicodedata.normalize('NFD', value or '')
    text = ''.join(ch for ch in text if unicodedata.category(ch) != 'Mn')
    text = re.sub(r'\s+', ' ', text)
    return text.strip().lower()


def flatten_targets() -> List[str]:
    targets: List[str] = []
    for parent, children in PLANNING_GROUPS:
        targets.append(parent)
        targets.extend(children)
    return targets


def build_driver(headless: bool = False) -> webdriver.Chrome:
    options = Options()
    options.add_argument('--start-maximized')
    options.add_argument('--disable-notifications')
    options.add_argument('--disable-popup-blocking')
    options.add_argument('--no-default-browser-check')
    options.add_argument('--disable-blink-features=AutomationControlled')
    if headless:
        options.add_argument('--headless=new')
    options.page_load_strategy = 'eager'

    driver = webdriver.Chrome(options=options)
    driver.set_page_load_timeout(60)
    driver.execute_cdp_cmd('Page.addScriptToEvaluateOnNewDocument', {'source': WATCHER_JS})
    return driver


def js_find_visible_text(driver: webdriver.Chrome, target_text: str):
    script = r"""
const target = arguments[0];
const normalize = (value) => (value || '')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/\s+/g, ' ')
  .trim()
  .toLowerCase();
const isVisible = (node) => {
    if (!node) {
        return false;
    }
    const style = window.getComputedStyle(node);
    if (!style || style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
        return false;
    }
    return node.getClientRects().length > 0;
};
const expected = normalize(target);
const candidates = [];
const roots = Array.from(document.querySelectorAll('aside, nav, .layout-menu, .ui-menu, .ui-tree, .ui-tree-container, .sidebar, .sidebar-menu, .menu, .menu-container, body'));
for (const root of roots) {
  const nodes = root.querySelectorAll('*');
  for (const node of nodes) {
        const rawTexts = [
            node.getAttribute ? node.getAttribute('aria-label') : '',
            node.getAttribute ? node.getAttribute('title') : '',
            node.innerText,
            node.textContent,
        ].filter(Boolean);

        let matchedText = '';
        let exact = false;
        let partial = false;

        for (const rawText of rawTexts) {
            const text = String(rawText).trim();
            if (!text) {
                continue;
            }

            const normalized = normalize(text);
            if (!normalized) {
                continue;
            }

            if (normalized === expected) {
                matchedText = text;
                exact = true;
                partial = true;
                break;
            }

            if (normalized.includes(expected)) {
                if (!matchedText || normalize(matchedText).length > normalized.length) {
                    matchedText = text;
                    partial = true;
                }
            }
        }

        if (!partial) {
            continue;
        }

    const clickable = node.closest('a,button,[role="button"],li') || node;
        if (clickable && isVisible(clickable)) {
            candidates.push({
                clickable,
                textLength: normalize(matchedText).length,
                exact,
            });
    }
  }
}
if (candidates.length === 0) {
    return null;
}

candidates.sort((a, b) => {
    if (a.exact !== b.exact) {
        return a.exact ? -1 : 1;
    }
    if (a.textLength !== b.textLength) {
        return a.textLength - b.textLength;
    }
    return 0;
});

return candidates[0].clickable;
"""
    return driver.execute_script(script, target_text)


def click_text(driver: webdriver.Chrome, target_text: str) -> bool:
    try:
        element = js_find_visible_text(driver, target_text)
        if element is None:
            return False
        driver.execute_script('arguments[0].scrollIntoView({block: "center", inline: "center"});', element)
        driver.execute_script(
            """
const element = arguments[0];
element.click();
element.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
""",
            element,
        )
        return True
    except Exception:
        return False


def open_planning_menu(driver: webdriver.Chrome) -> None:
    """Open the sidebar path that reveals the planning tree."""
    for target in ('Schedules', 'Plannings Groupés par Promotion'):
        for _ in range(3):
            if click_text(driver, target):
                time.sleep(1)
                break
            time.sleep(0.5)


def prepare_choice_page(driver: webdriver.Chrome) -> None:
    driver.get(CHOIX_URL)
    wait_for_choice_page(driver)
    time.sleep(0.8)
    open_planning_menu(driver)
    expand_visible_tree(driver)
    time.sleep(0.6)


def wait_for_planning_page(driver: webdriver.Chrome, previous_id_init: str, timeout: int = 30) -> None:
    wait = WebDriverWait(driver, timeout)

    def ready(d: webdriver.Chrome) -> bool:
        try:
            if 'Planning.xhtml' not in d.current_url:
                return False
            current_id = read_id_init(d)
            return bool(current_id) and current_id != previous_id_init
        except Exception:
            return False

    wait.until(ready)


def select_target_path(driver: webdriver.Chrome, labels: List[str]) -> bool:
    for label in labels:
        if not click_text(driver, label):
            return False
        time.sleep(0.6)
        expand_visible_tree(driver)
        time.sleep(0.3)
    return True


def triggerScheduleView(driver: webdriver.Chrome) -> bool:
    if not (click_text(driver, 'Select all') or click_text(driver, 'Select')):
        return False

    time.sleep(0.5)
    return click_text(driver, 'View schedule') or click_text(driver, 'View Schedule')


def capture_selected_planning(driver: webdriver.Chrome, target_label: str, output_path: Path, seen_ids: set[str], entries: List[Dict[str, object]]) -> bool:
    selection_code_rows = extract_code_rows(driver)
    previous_id = read_id_init(driver)
    if not triggerScheduleView(driver):
        print('  - view schedule failed')
        return False

    wait_for_planning_page(driver, previous_id)
    time.sleep(0.8)

    snapshot = capture_snapshot(driver, target_label)
    snapshot['selectionCodes'] = selection_code_rows
    snapshot['selectionCodesCount'] = len(selection_code_rows)
    snapshot_id = str(snapshot.get('idInit', ''))
    if snapshot_id and snapshot_id not in seen_ids:
        entries.append(snapshot)
        seen_ids.add(snapshot_id)
        save_output(output_path, entries, driver.current_url)
        print(f"  - captured idInit={snapshot_id}, codes={snapshot.get('codesCount', 0)}")
        return True

    print(f"  - duplicate or empty idInit ({snapshot_id})")
    return False


def wait_for_choice_page(driver: webdriver.Chrome, timeout: int = 30) -> None:
    wait = WebDriverWait(driver, timeout)
    wait.until(lambda d: d.execute_script('return document.readyState') in ('interactive', 'complete'))
    wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "input[name='form:idInit']")))


def read_id_init(driver: webdriver.Chrome) -> str:
    try:
        element = driver.find_element(By.CSS_SELECTOR, "input[name='form:idInit']")
        value = element.get_attribute('value') or ''
        if value.strip():
            return value.strip()
    except Exception:
        pass

    try:
        body = read_last_request_body(driver)
        params = parse_qs(body, keep_blank_values=True)
        return str(params.get('form:idInit', [''])[0]).strip()
    except Exception:
        return ''


def read_last_request_body(driver: webdriver.Chrome) -> str:
    try:
        body = driver.execute_script('return sessionStorage.getItem("__AURION_LAST_REQUEST_BODY__") || "";')
        return str(body or '')
    except Exception:
        return ''


def read_planning_label(driver: webdriver.Chrome) -> str:
        try:
                label = driver.execute_script(
                        r"""
const matches = [
    /\bPlanning\s+[A-Za-z0-9À-ÿ'()\- ]+/i,
    /\bSchedule\s+[A-Za-z0-9À-ÿ'()\- ]+/i,
];

const ignoreRe = /(My Schedule|My Documents|Education|Account Balance|International Break|Search|Advanced search|Select|View schedule|View Schedule)/i;
const selectors = ['h1', 'h2', 'h3', '.ui-panel-title', '.page-title', '.ui-widget-header', '.breadcrumb', '.ui-breadcrumb'];

for (const selector of selectors) {
    for (const node of document.querySelectorAll(selector)) {
        const text = String(node.innerText || node.textContent || '').trim();
        if (!text || ignoreRe.test(text)) {
            continue;
        }
        if (matches.some((re) => re.test(text))) {
            return text;
        }
    }
}

const lines = String(document.body ? document.body.innerText || '' : '')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

for (const line of lines) {
    if (ignoreRe.test(line)) {
        continue;
    }
    if (matches.some((re) => re.test(line))) {
        return line;
    }
}

return '';
"""
                )
                return str(label or '').strip()
        except Exception:
                return ''


def emit_planning_heartbeat(driver: webdriver.Chrome, current_id: str) -> None:
        planning_label = read_planning_label(driver) or 'Planning'
        print(f"[heartbeat] planning={planning_label} | idInit={current_id}", flush=True)


def parse_submenu_id(body: str) -> str:
    params = parse_qs(body or '', keep_blank_values=True)
    return str(params.get('webscolaapp.Sidebar.ID_SUBMENU', [''])[0]).strip()


def parse_request_fields(body: str) -> Dict[str, str]:
    params = parse_qs(body or '', keep_blank_values=True)
    return {key: values[0] if values else '' for key, values in params.items()}


def extract_code_rows(driver: webdriver.Chrome) -> List[Dict[str, str]]:
    rows: List[Dict[str, str]] = []
    try:
        table_rows = driver.find_elements(By.CSS_SELECTOR, 'table tr')
    except Exception:
        return rows

    for row in table_rows:
        try:
            if not row.is_displayed():
                continue
            cells = [cell.text.strip() for cell in row.find_elements(By.CSS_SELECTOR, 'th,td') if cell.text.strip()]
            if len(cells) < 2:
                continue
            code = cells[0]
            if not CODE_REGEX.match(code):
                continue
            rows.append(
                {
                    'code': code,
                    'label': cells[1] if len(cells) > 1 else '',
                    'validUntil': cells[2] if len(cells) > 2 else '',
                    'kind': cells[3] if len(cells) > 3 else '',
                }
            )
        except Exception:
            continue

    return rows


def wait_for_page_update(driver: webdriver.Chrome, previous_id_init: str, timeout: int = 25) -> None:
    wait = WebDriverWait(driver, timeout)

    def ready(d: webdriver.Chrome) -> bool:
      try:
          d.execute_script('return document.readyState')
          current_id = read_id_init(d)
          if current_id and current_id != previous_id_init:
              return True
          rows = extract_code_rows(d)
          if rows:
              return True
          return False
      except Exception:
          return False

    try:
        wait.until(ready)
    except TimeoutException:
        time.sleep(1.5)


def capture_snapshot(driver: webdriver.Chrome, target_label: str) -> Dict[str, object]:
    body = read_last_request_body(driver)
    fields = parse_request_fields(body)
    code_rows = extract_code_rows(driver)
    id_init = read_id_init(driver)
    submenu_id = parse_submenu_id(body)

    return {
        'timestamp': time.strftime('%Y-%m-%dT%H:%M:%S%z'),
        'pageUrl': driver.current_url,
        'targetLabel': target_label,
        'idInit': id_init,
        'submenuId': submenu_id,
        'dateInput': fields.get('form:date_input', ''),
        'week': fields.get('form:week', ''),
        'start': fields.get('form:j_idt118_start', ''),
        'end': fields.get('form:j_idt118_end', ''),
        'rawBody': body,
        'codesCount': len(code_rows),
        'codes': code_rows,
    }


def load_existing_entries(path: Path) -> List[Dict[str, object]]:
    if not path.exists():
        return []
    try:
        data = json.loads(path.read_text(encoding='utf-8'))
        entries = data.get('entries', []) if isinstance(data, dict) else []
        return entries if isinstance(entries, list) else []
    except Exception:
        return []


def save_output(path: Path, entries: List[Dict[str, object]], page_url: str) -> None:
    payload = {
        'timestamp': time.strftime('%Y-%m-%dT%H:%M:%S%z'),
        'pageUrl': page_url,
        'totalEntries': len(entries),
        'entries': entries,
    }
    path.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding='utf-8')


def login(driver: webdriver.Chrome, username: Optional[str], password: Optional[str], manual_login: bool) -> None:
    driver.get(LOGIN_URL)
    wait = WebDriverWait(driver, 30)

    if manual_login or not username or not password:
        print('Login in the browser, then come back here and press Enter.')
        input('Press Enter after login... ')
        return

    username_field = wait.until(EC.presence_of_element_located((By.ID, 'username')))
    password_field = driver.find_element(By.ID, 'password')
    username_field.clear()
    username_field.send_keys(username)
    password_field.clear()
    password_field.send_keys(password)

    submitted = False
    for selector in ('button[type="submit"]', 'input[type="submit"]', '[name="submit"]'):
        try:
            button = driver.find_element(By.CSS_SELECTOR, selector)
            button.click()
            submitted = True
            break
        except Exception:
            continue

    if not submitted:
        password_field.submit()

    wait.until(lambda d: 'login' not in d.current_url.lower())


def expand_visible_tree(driver: webdriver.Chrome) -> None:
    toggles = driver.find_elements(
        By.CSS_SELECTOR,
        '.ui-tree-toggler, .ui-treenode-toggler, .ui-icon-triangle-1-e, .ui-icon-caret-1-e, .ui-icon-plus, [aria-label*="expand" i], [title*="expand" i], [aria-label*="open" i], [title*="open" i]'
    )
    for toggle in toggles:
        try:
            if toggle.is_displayed():
                driver.execute_script('arguments[0].click();', toggle)
                time.sleep(0.15)
        except Exception:
            continue


def run_capture(driver: webdriver.Chrome, output_path: Path) -> List[Dict[str, object]]:
    existing_entries = load_existing_entries(output_path)
    seen_ids = {str(entry.get('idInit', '')) for entry in existing_entries if isinstance(entry, dict)}
    entries: List[Dict[str, object]] = list(existing_entries)

    prepare_choice_page(driver)

    total_targets = sum(1 + len(children) for _, children in PLANNING_GROUPS)
    print(f'Targets configured: {total_targets}')

    current_index = 0
    for parent_label, children in PLANNING_GROUPS:
        current_index += 1
        print(f'[{current_index:02d}/{total_targets:02d}] {parent_label}')
        prepare_choice_page(driver)

        if not select_target_path(driver, [parent_label]):
            print('  - skip (not clickable or not visible)')
            continue

        if not capture_selected_planning(driver, parent_label, output_path, seen_ids, entries):
            continue

        for child_label in children:
            current_index += 1
            print(f'[{current_index:02d}/{total_targets:02d}] {child_label}')
            prepare_choice_page(driver)

            if not select_target_path(driver, [parent_label, child_label]):
                print('  - skip (not clickable or not visible)')
                continue

            capture_selected_planning(driver, f'{parent_label} > {child_label}', output_path, seen_ids, entries)

    save_output(output_path, entries, driver.current_url)
    return entries


def run_manual_capture(driver: webdriver.Chrome, output_path: Path) -> List[Dict[str, object]]:
    existing_entries = load_existing_entries(output_path)
    seen_ids = {str(entry.get('idInit', '')) for entry in existing_entries if isinstance(entry, dict)}
    entries: List[Dict[str, object]] = list(existing_entries)

    driver.get(CHOIX_URL)
    wait_for_choice_page(driver)
    print('Manual capture mode.')
    print('Open a planning in Aurion yourself, keep it on the choice page, then the script will save each new idInit automatically.')
    print('Press Ctrl+C to stop.')

    last_seen_id = ''
    last_seen_body = ''
    next_heartbeat = time.monotonic() + 5.0

    while True:
        try:
            time.sleep(0.8)

            if time.monotonic() >= next_heartbeat:
                if 'Planning.xhtml' in driver.current_url:
                    current_id = read_id_init(driver)
                    if current_id.startswith('webscolaapp.Planning_'):
                        emit_planning_heartbeat(driver, current_id)
                next_heartbeat = time.monotonic() + 5.0

            if 'Planning.xhtml' not in driver.current_url:
                continue

            current_body = read_last_request_body(driver)
            current_id = read_id_init(driver)

            if not current_id:
                continue

            if not current_id.startswith('webscolaapp.Planning_'):
                continue

            if current_id == last_seen_id or current_body == last_seen_body:
                continue

            code_rows = extract_code_rows(driver)
            if not code_rows:
                continue

            snapshot = capture_snapshot(driver, current_id)
            snapshot_id = str(snapshot.get('idInit', ''))
            if not snapshot_id or snapshot_id in seen_ids:
                last_seen_id = current_id
                last_seen_body = current_body
                continue

            entries.append(snapshot)
            seen_ids.add(snapshot_id)
            save_output(output_path, entries, driver.current_url)
            print(f"  - captured idInit={snapshot_id}, codes={snapshot.get('codesCount', 0)}")

            last_seen_id = current_id
            last_seen_body = current_body
        except KeyboardInterrupt:
            print('\nManual capture stopped.')
            break

    save_output(output_path, entries, driver.current_url)
    return entries


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description='Aurion planning capture helper')
    parser.add_argument('--output', default=DEFAULT_OUTPUT, help='Output JSON file')
    parser.add_argument('--headless', action='store_true', help='Run browser headless')
    parser.add_argument('--manual-login', action='store_true', help='Login manually in the browser')
    parser.add_argument('--manual-capture', action='store_true', help='Do not click planning items automatically; capture new ids as you click them yourself')
    parser.add_argument('--auto-capture', action='store_true', help='Use the automatic click-through capture flow')
    parser.add_argument('--username', default=None, help='Aurion username/email')
    parser.add_argument('--password', default=None, help='Aurion password')
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    output_path = Path(args.output).resolve()

    username = args.username or None
    password = args.password or None

    if not args.manual_login and (not username or not password):
        answer = input('Use manual login instead of typing credentials? [Y/n] ').strip().lower()
        if answer in ('', 'y', 'yes'):
            args.manual_login = True

    if not args.manual_login and (not username or not password):
        username = username or input('Aurion username: ').strip()
        password = password or getpass('Aurion password: ')

    driver = build_driver(headless=args.headless)

    try:
        login(driver, username, password, args.manual_login)
        if args.auto_capture:
            entries = run_capture(driver, output_path)
        else:
            entries = run_manual_capture(driver, output_path)
        print(f'Capture done: {len(entries)} entries written to {output_path}')
        return 0
    finally:
        try:
            driver.quit()
        except Exception:
            pass


if __name__ == '__main__':
    raise SystemExit(main())
