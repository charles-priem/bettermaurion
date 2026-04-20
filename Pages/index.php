<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Junia Salles – Accueil</title>
  <link rel="stylesheet" href="../css/style.css" />
</head>
<body>

  <header class="header">
    <nav class="nav">
      <ul class="nav_list">
              <li class="nav_item _dropdown">
                <button class="dropbtn">Emploi du temps <i class="fa fa-caret-down"></i></button>
                <div class="dropdown-content">
                  <a href="../Pages/EDT_perso.php">Mon emploi du temps</a>
                  <a href="../Pages/EDT_promotions.php">Emploi du temps par promotions</a>
                </div>
              </li>
              <li class="nav_item_dropdown">
                <button class="dropbtn">Bâtiments <i class="fa fa-caret-down"></i></button>
                <div class="dropdown-content">
                  <a href="#">IC1 </a>
                  <a href="#">IC2 </a>
                  <a href="#">ALG </a>
                  <a href="#">MF </a>
                </div>
              </li>
              <li class="nav_item_dropdown">
                <button class="dropbtn">Services junia <i class="fa fa-caret-down"></i></button>
                <div class="dropdown-content">
                  <a href="#">Aurion</a>
                  <a href="#">Junia learning</a>
                  <a href="#">OneDrive</a>
                </div>
              </li>
        <li class="nav_connection">
          <?php if (isset($_SESSION['user_id'])): ?>
            <a href="../Pages/profil.php">
              <img src="../uploads/<?= $_SESSION['photo_profil']; ?>" alt="Photo de Profil" class="profile-photo" width="40" height="40" style="border-radius: 50%; border: 2px solid #fff;">
            </a>
            <a href="../Pages/profil.php?logout=true"><button>Se déconnecter</button></a>
          <?php else: ?>
            <a class="nav_connection" href="../Pages/connexion.php">Connexion</a>
            <a href="../Pages/inscription.php">S'inscrire</a>
          <?php endif; ?>
        </li>
      </ul>
    </nav>
  </header>

  <main>




    <div class="image_fond_acceuil">
        <img src="#" alt="Image de fond" class="image_fond_acceuil_img"/>
    </div>

  </main>

</body>
</html>
