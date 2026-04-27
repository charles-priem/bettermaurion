from playwright.sync_api import sync_playwright
import json
import os
import time

# =====================================================================
# 🎯 CONFIGURATION DU SNIPER AUTOMATIQUE
# =====================================================================
MON_JSESSIONID = "" # À récupérer depuis les cookies de ton navigateur après t'être connecté à Aurion

# 5 semaines : S0 (actuelle), S1, S2, S3, S4
NB_SEMAINES = 5 

# On définit le premier onglet avec les traductions possibles
ONGLET_SCHEDULES = ["Schedules", "Les plannings", "Voir planning"]

MISSIONS = [
    # ==========================================
    # 🟦 ADIMAKER
    # # ==========================================
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ADIMAKER"],
         "classe": "Planning ADIMAKER Lille A1"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ADIMAKER"],
         "classe": "Planning ADIMAKER Lille A2"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ADIMAKER"],
         "classe": "Planning ADIMAKER Bordeaux A1"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ADIMAKER"],
         "classe": "Planning ADIMAKER Bordeaux A2"
     },
    # # ==========================================
    # # CPI
    # # ==========================================
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning CPI"],
         "classe": "Planning CPI Lille A1"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning CPI"],
         "classe": "Planning CPI Lille A2"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning CPI"],
         "classe": "Planning CPI Lille A3"
     },
    # # ==========================================
    # #  HEI INGÉNIEUR
    # # ==========================================
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning HEI Ingénieur"],
         "classe": "HEI Ingénieur A3"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning HEI Ingénieur"],
         "classe": "HEI Ingénieur A4"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning HEI Ingénieur"],
         "classe": "HEI Ingénieur A5"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning HEI Ingénieur"],
         "classe": "HEI Ingénieur en Alternance A3"
     },
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning HEI Ingénieur"],
         "classe": "HEI Ingénieur en Alternance A4"
     },
    {
       "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning HEI Ingénieur"],
      "classe": "HEI Ingénieur en Alternance A5"
    },
    # ==========================================
    # 🟧 ISA
    # ==========================================
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISA Ingénieur"],
        "classe": "Planning ISA Ingénieur A1"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISA Ingénieur"],
        "classe": "Planning ISA Ingénieur A2"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISA Ingénieur"],
        "classe": "Planning ISA Ingénieur A3"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISA Ingénieur"],
        "classe": "Planning ISA Ingénieur A4"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISA Ingénieur"],
        "classe": "Planning ISA Ingénieur A5"
    },
    {
       "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISA Ingénieur"],
       "classe": "Planning ISA Ingénieur par Alternance A3"
   },
   {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISA Ingénieur"],
       "classe": "Planning ISA Ingénieur par Alternance A4"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISA Ingénieur"],
        "classe": "Planning ISA Ingénieur par Alternance A5"
    },
    # ==========================================
    # 🟧 ISEN AP
    # ==========================================
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN AP"],
        "classe": "Planning ISEN AP3"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN AP"],
        "classe": "Planning ISEN AP4"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN AP"],
        "classe": "Planning ISEN AP5"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN AP"],
        "classe": "Planning ISEN Bordeaux AP3"
    },
   {
     "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN AP"],
        "classe": "Planning ISEN Bordeaux AP4"
    },
   {
       "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN AP"],
        "classe": "Planning ISEN Bordeaux AP5"
    },
    # ==========================================
    # 🟧 ISEN CIR
    # ==========================================
     {
         "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN CIR"],
        "classe": "Planning ISEN CIR1"
     },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN CIR"],
       "classe": "Planning ISEN CIR2"
     },
     {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN CIR"],
       "classe": "Planning ISEN CIR3"
     },
    # ==========================================
    # 🟧 ISEN CPG
    # ==========================================
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN CPG"],
        "classe": "Planning ISEN CPG1"
    },
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN CPG"],
        "classe": "Planning ISEN CPG2"
    },
    # ==========================================
    # 🟧 ISEN CSI
    # ==========================================
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN CSI"],
        "classe": "Planning ISEN CSI3"
    },
    # ==========================================
    # 🟧 ISEN Master
    # ==========================================
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN Master"],
        "classe": "Planning ISEN M1"
    },  
    {
        "chemin_menu": [ONGLET_SCHEDULES, "Plannings Groupés par Promotion", "Planning ISEN Master"],
        "classe": "Planning ISEN M2"
    },
]
# =====================================================================

def extraire_json_propre(texte):
    debut = texte.find('{"events"')
    if debut == -1: return None
    accolades_ouvertes = 0
    fin = -1
    for i in range(debut, len(texte)):
        if texte[i] == '{': accolades_ouvertes += 1
        elif texte[i] == '}':
            accolades_ouvertes -= 1
            if accolades_ouvertes == 0:
                fin = i + 1
                break
    if fin != -1: return texte[debut:fin]
    return None

def clic_menu(page, textes_possibles):
    """Cherche un lien dans le menu latéral (supporte les listes pour le multilingue)"""
    if isinstance(textes_possibles, str):
        textes_possibles = [textes_possibles]
        
    for texte in textes_possibles:
        lien = page.locator(f'a.ui-menuitem-link:has-text("{texte}")').first
        if lien.count() > 0:
            lien.scroll_into_view_if_needed()
            lien.click(force=True)
            page.wait_for_timeout(1000)
            return True
            
    return False

def cocher_tableau(page):
    print("   ⏳ Attente du tableau de sélection...")
    try:
        page.locator('.colCaseACocher').first.wait_for(state="visible", timeout=10000)
    except:
        print("   ⚠️ Le tableau n'est pas apparu.")
        return False

    page_num = 1
    while True:
        print(f"   ➡️ Cochage des cases de la page {page_num}...")
        
        # 1. On cherche la case "Tout sélectionner" (dans l'en-tête)
        case_tout_selectionner = page.locator('th.colCaseACocher .ui-chkbox-box').first
        
        if case_tout_selectionner.count() > 0:
            classes_case = case_tout_selectionner.get_attribute("class") or ""
            # On clique seulement si elle n'est pas déjà cochée
            if "ui-state-active" not in classes_case:
                case_tout_selectionner.click(force=True)
                page.wait_for_timeout(500) # Petite pause pour laisser Aurion valider
        else:
            # Sécurité : S'il n'y a pas de case globale, on coche toutes les cases de la page une par une
            cases = page.locator('td.colCaseACocher .ui-chkbox-box')
            for i in range(cases.count()):
                c = cases.nth(i)
                if "ui-state-active" not in (c.get_attribute("class") or ""):
                    c.click(force=True)
                    page.wait_for_timeout(100)

        # 2. Vérification de la pagination (y a-t-il une page suivante ?)
        bouton_suivant = page.locator('.ui-paginator-next').first
        
        if bouton_suivant.count() > 0:
            classes_bouton = bouton_suivant.get_attribute("class") or ""
            # Si le bouton n'est PAS grisé (disabled), on peut cliquer dessus
            if "ui-state-disabled" not in classes_bouton:
                bouton_suivant.click(force=True)
                print(f"   🔄 Passage à la page suivante...")
                page.wait_for_timeout(2000) # Attente du chargement AJAX de la nouvelle page
                page_num += 1
                continue # On relance la boucle pour cocher la nouvelle page
        
        # S'il n'y a pas de bouton ou qu'il est grisé, on a fini !
        break

    print("   ✅ Toutes les pages ont été cochées avec succès !")
    return True
def sniper_final():
    os.makedirs("plannings", exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False, slow_mo=50) 
        context = browser.new_context()
        context.add_cookies([{
            "name": "JSESSIONID", "value": MON_JSESSIONID, 
            "domain": "aurion.junia.com", "path": "/"
        }])

        page = context.new_page()
        
        donnees_interceptees = []
        def intercepter_reponse(response):
            if response.status == 200:
                try:
                    texte = response.text()
                    if '"events"' in texte and '"id"' in texte: 
                        donnees_interceptees.append(texte)
                except: pass

        page.on("response", intercepter_reponse)

        for index, mission in enumerate(MISSIONS):
            nom_classe = mission["classe"]
            chemin = mission["chemin_menu"]
            tous_les_cours = []
            
            print(f"\n" + "="*50)
            print(f"🚀 MISSION {index + 1}/{len(MISSIONS)} : {nom_classe}")
            print("="*50)

            print("🌐 Rechargement du Menu Principal...")
            page.goto("https://aurion.junia.com/faces/MainMenuPage.xhtml")
            page.wait_for_timeout(2000)

            chemin_ok = True
            for etape in chemin:
                nom_affichage = etape[0] if isinstance(etape, list) else etape
                print(f"   📂 Clic menu : {nom_affichage}")
                if not clic_menu(page, etape):
                    print(f"   ❌ Onglet '{nom_affichage}' introuvable.")
                    chemin_ok = False
                    break

            if not chemin_ok: continue

            print(f"   🎯 Clic menu final : {nom_classe}")
            if not clic_menu(page, nom_classe):
                print(f"   ❌ Classe '{nom_classe}' introuvable dans le menu.")
                continue

            if not cocher_tableau(page):
                print(f"   ❌ Impossible de trouver ou cocher la case dans le tableau.")
                continue
            
            page.wait_for_timeout(1000)

            print(f"   ⏳ Début de l'aspiration sur {NB_SEMAINES} semaines...")
            for semaine in range(NB_SEMAINES):
                donnees_interceptees.clear() 
                
                if semaine == 0:
                    # 💡 Recherche multi-langues du bouton de validation
                    selecteur_bouton = (
                        'span.ui-button-text:has-text("View schedule"), '
                        'span.ui-button-text:has-text("Voir le planning"), '
                        'span.ui-button-text:has-text("Voir planning")'
                    )
                    bouton = page.locator(selecteur_bouton).first
                    
                    if bouton.count() > 0:
                        bouton.click(force=True)
                        print("      ✅ Bouton 'View schedule / Voir' cliqué !")
                    else:
                        print("      ❌ Bouton de validation introuvable.")
                        break
                else:
                    bouton_suivant = page.locator('.fc-next-button').first
                    if bouton_suivant.count() > 0:
                        bouton_suivant.click(force=True)
                    else:
                        print("      ⚠️ Bouton '>' introuvable.")
                        break

                # --- ATTENTE INTELLIGENTE ---
                donnees_semaine_valides = None
                for _ in range(15): 
                    if len(donnees_interceptees) > 0:
                        json_brut = extraire_json_propre(donnees_interceptees[-1])
                        if json_brut:
                            try:
                                # On essaie de le lire. Si c'est la fausse réponse de chargement, ça plantera ici
                                test_json = json.loads(json_brut.replace("\\'", "'"))
                                if "events" in test_json:
                                    donnees_semaine_valides = test_json
                                    break # C'est le bon JSON ! On sort de l'attente !
                            except:
                                pass # C'était un faux positif, on continue d'attendre
                    page.wait_for_timeout(1000)

                # --- TRAITEMENT ---
                if donnees_semaine_valides:
                    cours = donnees_semaine_valides.get("events", [])
                    tous_les_cours.extend(cours)
                    print(f"      ✅ S{semaine} : {len(cours)} cours aspirés.")
                else:
                    print(f"      ❌ Timeout ou erreur de chargement pour la S{semaine}.")
                
                time.sleep(1)

            nom_fichier = nom_classe.replace("Planning ", "").replace(" ", "_").replace("/", "_")
            chemin_sauvegarde = f"plannings/{nom_fichier}_COMPLET.json"
            with open(chemin_sauvegarde, "w", encoding="utf-8") as f_out:
                json.dump({"events": tous_les_cours}, f_out, indent=4, ensure_ascii=False)
                
            print(f"   💾 Mission terminée ! {len(tous_les_cours)} cours dans '{chemin_sauvegarde}'.")

        print("\n🎉 TOUTES LES MISSIONS SONT TERMINÉES !")
        browser.close()

if __name__ == "__main__":
    sniper_final()
