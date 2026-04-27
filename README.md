# BetterMoroomia - Portail Emploi du Temps Junia/Aurion

**BetterMoroomia** est une application web PHP permettant de consulter facilement l’emploi du temps issu d’Aurion/Junia :  
• par promotion/groupe (ex : CIR2 A, AP3, etc.)  
• par planning personnalisé (emploi du temps individuel)

## Fonctionnalités principales

- 🔍 **Recherche & Consultation** des plannings par promo/groupe
- 🗓️ **Réservations de salles** : possibilité de réserver une salle/des salles pour un ou plusieurs créneau de 2h
- 🎨 **Filtres intelligents** : cours, TD, TP, projet, exam
- 👤 **Planning perso** (_optionnel_ : par saisie identifiant Aurion)
- 🚀 **Navigation rapide** : sélection semaine, promo, etc.

---

## Installation


**Clonage du projet**

   ```bash
   git clone https://github.com/charles-priem/bettermaurion.git
   cd bettermaurion
   ```


**Configuration**
Dans un premier temps veillez à mettre le dossier du projet dans votre dossier htdoc de votre MAMP
Une fois cela fait ouvrer votre phpMyAdmin ```http://localhost/phpMyAdmin5/``` puis créer une nouvelle base de données :bettermaurion. Ensuite insérez le fichier: bettermaurion.sql compris dans le dossier 📁 bdd du projet.

Une fois cela fait vous pouvez ensuite aller dans votre localhost
```http://localhost/ProjetBetterMoroomia/Pages/```
Vous pouvez ensuite créer un compte avec vos identifiants aurion et mot de passe aurion afin d'avoir accès à votre emplois du temps personnel compris sur le site.

---
🔒**Panel admin:**
Afin d'accéder au panel admin vous avez deux possibilitées :
• Via PHPMYADMIN : vous pouvez vous mettre admin via la table user de php my admin

• Utiliser le compte admin déjà présent dans la base de données : 
mail : fz@student.junia.com
Password : password 
Et une fois connecté, vous aurez accès au panel admin .
