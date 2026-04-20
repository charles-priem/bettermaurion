from playwright.sync_api import sync_playwright
import time

def automatiser_aurion():
    MON_JSESSIONID = "SESSIONID_VALUE_FROM_BROWSER"

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=False, slow_mo=100) 
        context = browser.new_context()

        context.add_cookies([{
            "name": "JSESSIONID",
            "value": MON_JSESSIONID,
            "domain": "aurion.junia.com",
            "path": "/"
        }])

        page = context.new_page()

        donnees_interceptees = []
        
        def intercepter_reponse(response):
            if "Planning.xhtml" in response.url and response.status == 200:
                try:
                    texte = response.text()
                    # MOUCHARD : On imprime le début de chaque réponse du serveur
                    print(f"📡 [Réseau] Réponse interceptée ({len(texte)} caractères) : {texte[:100]}...")
                    
                    if '"events"' in texte:
                        print("\n🎯 BINGO ! JSON TACTIQUE INTERCEPTÉ !")
                        donnees_interceptees.append(texte)
                except Exception:
                    pass

        page.on("response", intercepter_reponse)

        print("🌐 Navigation vers l'accueil d'Aurion...")
        page.goto("https://aurion.junia.com/")
        
        print("\n" + "="*50)
        print("🧑‍💻 À TOI DE JOUER (LE CYBORG) :")
        print("1. Va jusqu'à la liste des cases (ex: ISEN M1).")
        print("2. NE COCHE RIEN !")
        print("3. Reviens ici et appuie sur ENTRÉE.")
        print("="*50)
        
        input("\n👉 Appuie sur ENTRÉE quand tu es devant les cases...")

        print("\n🤖 LE ROBOT PREND LE RELAIS !")
        
        cpt = 1
        while True:
            cases_vides = page.locator('.ui-chkbox-box:not(.ui-state-active)')
            nb_restantes = cases_vides.count()
            
            if nb_restantes == 0:
                # --- LE DOUBLE CHECK ANTI-FANTÔME ---
                print("⏳ Vérification (Attente de la fin du chargement PrimeFaces)...")
                page.wait_for_timeout(2000)
                if page.locator('.ui-chkbox-box:not(.ui-state-active)').count() == 0:
                    print("✅ Toutes les cases sont officiellement cochées !")
                    break
                else:
                    print("👻 Fausse alerte, l'écran était juste en train de charger !")
                    continue
                # ------------------------------------
                
            print(f"   -> Clic sur la case {cpt} (il en reste {nb_restantes})...")
            try:
                cases_vides.first.click(force=True)
                page.wait_for_timeout(1000)
                cpt += 1
            except Exception as e:
                print("⚠️ Impossible de cliquer sur la case.")
                break
                
                print("🚀 Clic sur 'View schedule'...")
        bouton_view = page.locator('span.ui-button-text:has-text("View")')
        if bouton_view.count() > 0:
            bouton_view.first.click(force=True)
        else:
            print("⚠️ Bouton 'View' introuvable. On clique sur le premier bouton par défaut.")
            page.locator('.ui-button-text').first.click(force=True)

        print("⏳ Le serveur Aurion génère le planning géant (ça peut prendre 15-20 secondes)...")
        
        # --- L'ATTENTE DU SNIPER ---
        # Le robot vérifie chaque seconde si l'intercepteur a attrapé le JSON
        json_trouve = False
        for i in range(30): # On attend 30 secondes MAX
            if len(donnees_interceptees) > 0:
                print(f"⚡ JSON reçu après {i} secondes d'attente !")
                json_trouve = True
                break
            page.wait_for_timeout(1000) # On attend 1 seconde et on revérifie
            
        if not json_trouve:
            print("⚠️ Le serveur a mis plus de 30 secondes ou n'a rien renvoyé...")

        # Petite pause pour laisser le temps au navigateur de dessiner les cases visuellement (pour le fun)
        page.wait_for_timeout(2000) 

        # --- SAUVEGARDE FINALE ---
        if len(donnees_interceptees) > 0:
            print("\n💾 Sauvegarde des données interceptées...")
            with open("interception_robot.txt", "w", encoding="utf-8") as f:
                f.write(donnees_interceptees[-1])
            print("✅ Données sauvegardées dans interception_robot.txt ! C'EST GAGNÉ !")
        else:
            print("\n❌ Aucun JSON n'a été intercepté. Le serveur a peut-être planté sous le poids des 21 classes.")

        print("🎉 Fermeture du navigateur.")
        browser.close()

if __name__ == "__main__":
    automatiser_aurion()