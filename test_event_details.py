import re
import http.cookiejar
from urllib.parse import urlencode
from urllib.request import HTTPCookieProcessor, Request, build_opener

# --- Configuration ---
AURION_BASE = 'https://aurion.junia.com'
LOGIN_URL = f'{AURION_BASE}/login'
PLANNING_URL = f'{AURION_BASE}/faces/Planning.xhtml'

# --- Identifiants (à remplir) ---
# Mettez vos identifiants ici pour le test
USERNAME = "charles.priem@student.junia.com"
PASSWORD = "#" 

# --- ID de l'événement à tester ---
# Cet ID est un exemple, nous le trouverons dynamiquement plus tard
# Il correspond à l'ID de l'événement sur lequel on veut "cliquer"
EVENT_ID_TO_TEST = "form:j_idt118:20:j_idt123" # Exemple, peut changer

def make_opener():
    """Crée un opener qui gère les cookies."""
    jar = http.cookiejar.CookieJar()
    opener = build_opener(HTTPCookieProcessor(jar))
    return opener, jar

def request_text(opener, request):
    """Exécute une requête et retourne le contenu textuel."""
    with opener.open(request) as response:
        raw = response.read()
        encoding = response.headers.get_content_charset() or 'utf-8'
        return raw.decode(encoding, errors='replace')

def login(opener, username, password):
    """Se connecte à Aurion."""
    print("1. Connexion à Aurion...")
    body = urlencode({'username': username, 'password': password, 'j_idt28': ''}).encode('utf-8')
    request = Request(LOGIN_URL, data=body, method='POST')
    request.add_header('Content-Type', 'application/x-www-form-urlencoded')
    request_text(opener, request)
    print("   => Connecté.")

def get_planning_state(opener):
    """Récupère le ViewState et idInit de la page de planning."""
    print("2. Récupération du ViewState...")
    request = Request(PLANNING_URL, method='GET')
    html = request_text(opener, request)
    
    view_state_match = re.search(r'name="javax\.faces\.ViewState"[^>]*value="([^"]+)"', html)
    id_init_match = re.search(r'name="form:idInit"[^>]*value="([^"]+)"', html)
    
    if not view_state_match:
        raise RuntimeError("ViewState non trouvé.")
        
    view_state = view_state_match.group(1)
    id_init = id_init_match.group(1) if id_init_match else ''
    print(f"   => ViewState trouvé ({len(view_state)} chars).")
    return view_state, id_init

def fetch_event_details(opener, view_state, event_id):
    """Simule le clic sur un événement pour obtenir les détails."""
    print(f"3. Récupération des détails pour l'événement ID: {event_id}...")
    body = urlencode({
        'javax.faces.partial.ajax': 'true',
        'javax.faces.source': event_id,
        'javax.faces.partial.execute': '@all',
        'javax.faces.partial.render': 'form:j_idt232', # Le panneau de détails
        'form:j_idt232': 'form:j_idt232',
        'form': 'form',
        'javax.faces.ViewState': view_state,
    }).encode('utf-8')

    request = Request(PLANNING_URL, data=body, method='POST')
    request.add_header('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8')
    request.add_header('Faces-Request', 'partial/ajax')
    request.add_header('X-Requested-With', 'XMLHttpRequest')
    
    xml_response = request_text(opener, request)
    print("   => Réponse reçue.")
    return xml_response

def main():
    """Fonction principale du script."""
    opener, _ = make_opener()
    
    try:
        login(opener, USERNAME, PASSWORD)
        view_state, _ = get_planning_state(opener)
        
        # Utiliser un ID d'événement statique pour ce premier test
        # Dans le futur, on extraira cet ID dynamiquement
        xml_data = fetch_event_details(opener, view_state, EVENT_ID_TO_TEST)
        
        print("\n--- RÉPONSE XML BRUTE ---")
        print(xml_data)
        
    except Exception as e:
        print(f"\nUne erreur est survenue: {e}")

if __name__ == "__main__":
    main()
