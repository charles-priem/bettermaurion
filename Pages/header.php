<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
    .dropbtn {
        text-decoration: none !important;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .nav_connection a {
        text-decoration: none;
    }
    
    .nav_connection button {
        text-decoration: none;
    }
    
    .profile-photo {
        width: 40px !important;
        height: 40px !important;
        border-radius: 50% !important;
        border: 2px solid #fff !important;
        object-fit: cover !important;
        display: inline-block !important;
    }
</style>
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
                <button class="dropbtn">Services junia <i class="fa fa-caret-down"></i></button>
                <div class="dropdown-content">
                    <a href="https://aurion.junia.com/faces/Login.xhtml">Aurion</a>
                    <a href="https://junia-learning.com/my/">Junia learning</a>
                    <a href="https://myjunia-my.sharepoint.com/">OneDrive-Junia sharepoint</a>
                </div>
            </li>
            <li class="nav_item_dropdown">
                <button class="dropbtn" onclick="window.location.href='reservation_salle.php'">Réserver une salle</button>
            </li>
            <li class="nav_connection">
               <?php if (isset($_SESSION['user_id'])): ?>

    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
        <li class="nav_item">
            <a href="admin.php" class="dropbtn">🛠️ Admin</a>
        </li>
    <?php endif; ?>

    <div class="nav_item_dropdown">
        <button class="dropbtn">
            <img src="../images/profils/<?php echo htmlspecialchars($_SESSION['photo_profil'] ?? 'default.png'); ?>" class="profile-photo">
            Mon Profil <i class="fa fa-caret-down"></i>
        </button>
                        <div class="dropdown-content">
                            <a href="profil.php">Voir mon profil</a>
                            <a href="profil.php?logout=true">Se déconnecter</a>
                        </div>
                    </div>
                <?php else: ?>
                    <button class="search-btn" style="margin-right:8px;" onclick="window.location.href='connexion.php'">Connexion</button>
                    <button class="search-btn secondary-btn" onclick="window.location.href='inscription.php'">S'inscrire</button>
                <?php endif; ?>
            </li>
        </ul>
    </nav>
</header>
