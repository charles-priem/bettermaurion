import requests
import json
import time
import re
from bs4 import BeautifulSoup
from datetime import datetime, timedelta

class AurionScraper:
    def __init__(self, jsessionid, viewstate):
        self.session = requests.Session()
        self.planning_url = "https://aurion.junia.com/faces/Planning.xhtml"
        self.session.cookies.set("JSESSIONID", jsessionid, domain="aurion.junia.com")
        self.viewstate = viewstate
        
        self.headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
            "Accept": "application/xml, text/xml, */*; q=0.01",
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            "Faces-Request": "partial/ajax",
            "X-Requested-With": "XMLHttpRequest",
            "Origin": "https://aurion.junia.com",
            "Referer": self.planning_url
        }

    def fetch_events(self, planning_id, offset_semaine=0):
        today = datetime.now()
        monday = today - timedelta(days=today.weekday()) + timedelta(weeks=offset_semaine)
        start_ms = int(monday.replace(hour=0, minute=0, second=0, microsecond=0).timestamp() * 1000)
        end_ms = start_ms + (7 * 24 * 60 * 60 * 1000)
        
        payload = {
            "javax.faces.partial.ajax": "true",
            "javax.faces.source": "form:j_idt118",
            "javax.faces.partial.execute": "form:j_idt118",
            "javax.faces.partial.render": "form:j_idt118",
            "form:j_idt118": "form:j_idt118",
            "form:j_idt118_start": str(start_ms),
            "form:j_idt118_end": str(end_ms),
            "form": "form",
            "form:largeurDivCenter": "1200", 
            "form:idInit": planning_id,
            "form:date_input": monday.strftime("%d/%m/%Y"),        
            "form:week": f"{monday.isocalendar()[1]}-{monday.year}",                
            "form:j_idt118_view": "agendaWeek",   
            "form:offsetFuseauNavigateur": "-7200000",
            "form:onglets_activeIndex": "0",
            "form:onglets_scrollState": "0",
            "javax.faces.ViewState": self.viewstate
        }
        
        res = self.session.post(self.planning_url, data=payload, headers=self.headers)
        
        try:
            soup = BeautifulSoup(res.text, 'xml')
            
            # NOUVEAU PARSING : On cherche dans la balise update id="form:j_idt118"
            update_node = soup.find('update', {'id': 'form:j_idt118'})
            if update_node:
                # Le texte dans l'update ressemble à {"events" : [...]}
                # On utilise regex pour nettoyer un peu au cas où et on parse
                content = update_node.text.strip()
                try:
                    data = json.loads(content)
                    if 'events' in data:
                        return data['events']
                except json.JSONDecodeError:
                    print("\n   ⚠️ Erreur en lisant le JSON trouvé dans la réponse.")
            
            return []
        except Exception as e:
            print(f"\n   💥 Erreur de parsing Python : {e}")
            return []

if __name__ == "__main__":
    # =====================================================================
    # LES 3 VALEURS DE LA CAPTURE EN OR (A CHANGER !)
    # =====================================================================
    MON_JSESSIONID = "SESSIONID_VALUE_FROM_BROWSER" # À récupérer depuis les cookies de ton navigateur après t'être connecté à Aurion
    MON_VIEWSTATE  = "8279927131606983072:4842894885179017549"
    MON_ID_INIT    = "webscolaapp.Planning_-971073698254589519" # À vérifier s'il a changé
    
    print("🚀 Lancement du scraping massif...")
    scraper = AurionScraper(MON_JSESSIONID, MON_VIEWSTATE)
    
    tous_les_plannings = {"ISEN_M1_GLOBAL": []}
    
    for semaine in range(0, 4):
        print(f"  -> Extraction Semaine +{semaine}...")
        events = scraper.fetch_events(MON_ID_INIT, semaine)
        tous_les_plannings["ISEN_M1_GLOBAL"].extend(events)
        print(f"     ✅ {len(events)} cours trouvés !")
        time.sleep(1)
        
    with open('plannings_aurion.json', 'w', encoding='utf-8') as f:
        json.dump(tous_les_plannings, f, ensure_ascii=False, indent=4)
    print("\n🎉 Terminé ! Le JSON géant t'attend !")