import json

def extraire_json_propre(texte):
    # On cherche le début du JSON
    debut = texte.find('{"events"')
    if debut == -1:
        return None

    # On extrait caractère par caractère en comptant les accolades { }
    # pour trouver la fin exacte du JSON sans prendre les saletés de PrimeFaces
    accolades_ouvertes = 0
    fin = -1
    
    for i in range(debut, len(texte)):
        if texte[i] == '{':
            accolades_ouvertes += 1
        elif texte[i] == '}':
            accolades_ouvertes -= 1
            if accolades_ouvertes == 0:
                fin = i + 1
                break
                
    if fin != -1:
        return texte[debut:fin]
    return None

def nettoyer_donnees():
    print("🧹 Nettoyage du fichier intercepté...")
    
    try:
        with open("interception_robot.txt", "r", encoding="utf-8") as f:
            texte = f.read()
            
        json_brut = extraire_json_propre(texte)
        
        if json_brut:
            # On remplace les caractères échappés moches de PrimeFaces
            json_brut = json_brut.replace("\\'", "'")
            
            donnees = json.loads(json_brut)
            
            with open("plannings_propres.json", "w", encoding="utf-8") as f_out:
                json.dump(donnees, f_out, indent=4, ensure_ascii=False)
                
            cours = donnees.get("events", [])
            print(f"✅ SUCCÈS ! {len(cours)} cours ont été extraits et mis au propre !")
            print("👉 Ouvre le fichier 'plannings_propres.json', il est prêt pour BetterMoroomia.")
        else:
            print("❌ Impossible de trouver le bloc JSON complet dans le texte.")
            
    except FileNotFoundError:
        print("❌ Le fichier interception_robot.txt n'existe pas.")
    except Exception as e:
        print(f"💥 Erreur lors du nettoyage : {e}")

if __name__ == "__main__":
    nettoyer_donnees()