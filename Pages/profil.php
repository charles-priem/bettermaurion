<?php
session_start();
require_once '../php/config.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Récupérer les données de l'utilisateur
$stmt = $conn->prepare('SELECT user_id, firstname, lastname, email, photo_profil, promotion FROM users WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Traiter les modifications de profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $photo_profil = $user['photo_profil']; // Garder la photo existante
    
    // Gérer le upload de photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/profils/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($file_ext), $allowed_exts)) {
            $new_filename = 'profil_' . $user_id . '.' . $file_ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_filename)) {
                $photo_profil = $new_filename;
            } else {
                $error_message = 'Erreur lors du téléchargement de la photo.';
            }
        } else {
            $error_message = 'Format de fichier non autorisé. Utilisez JPG, PNG ou GIF.';
        }
    }
    
    // Validation
    if (empty($firstname) || empty($lastname)) {
        $error_message = 'Le prénom et le nom sont obligatoires.';
    } else {
        // Mettre à jour le profil
        $stmt = $conn->prepare('UPDATE users SET firstname = ?, lastname = ?, photo_profil = ? WHERE user_id = ?');
        $stmt->bind_param('sssi', $firstname, $lastname, $photo_profil, $user_id);
        
        if ($stmt->execute()) {
            $success_message = 'Profil mis à jour avec succès !';
            $_SESSION['firstname'] = $firstname;
            $_SESSION['lastname'] = $lastname;
            $_SESSION['photo_profil'] = $photo_profil;
            $user['firstname'] = $firstname;
            $user['lastname'] = $lastname;
            $user['photo_profil'] = $photo_profil;
        } else {
            $error_message = 'Erreur lors de la mise à jour du profil.';
        }
        $stmt->close();
    }
}

// Gérer la déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Junia Emploi du Temps</title>   
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .profile-container {
            max-width: 700px;
            margin: 40px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }

        .profile-container h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #1a1a1a;
        }

        .profile-container p {
            color: #666;
            margin-bottom: 30px;
        }

        .profile-photo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 3px solid #1a1a1a;
            object-fit: cover;
            margin-bottom: 15px;
        }

        .photo-upload input[type="file"] {
            display: none;
        }

        .photo-upload-label {
            background-color: #f5f0f0;
            color: #1a1a1a;
            padding: 10px 20px;
            border-radius: 5px;
            border: 1px solid #ddd;
            cursor: pointer;
            font-size: 14px;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .photo-upload-label:hover {
            background-color: #ede6e6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1a1a1a;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: 'Roboto', serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1a1a1a;
            box-shadow: 0 0 0 2px rgba(26, 26, 26, 0.1);
        }

        .form-group input[type="email"],
        .form-group input.readonly {
            background-color: #f5f0f0;
            cursor: not-allowed;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .btn-primary {
            background-color: #1a1a1a;
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.8;
        }

        .btn-danger {
            background-color: #d32f2f;
            color: white;
        }

        .btn-danger:hover {
            opacity: 0.8;
        }

        .info-box {
            background-color: #f5f0f0;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #1a1a1a;
        }

        .info-box p {
            font-size: 14px;
            color: #333;
            margin: 5px 0;
        }

        .info-box strong {
            color: #1a1a1a;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #1a1a1a;
            opacity: 0.6;
            transition: opacity 0.3s;
        }

        .back-link:hover {
            opacity: 1;
        }

        @media (max-width: 600px) {
            .profile-container {
                margin: 20px;
                padding: 20px;
            }

            .profile-container h1 {
                font-size: 24px;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>
    <div class="profile-container">
        <h1 id="title">Mon Profil</h1>
        <p id="subtitle">Gérez vos informations personnelles</p>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="profile-photo-section">
            <?php if (!empty($user['photo_profil'])): ?>
                <img src="../images/profils/<?php echo htmlspecialchars($user['photo_profil']); ?>" alt="Photo de profil" class="profile-photo">
            <?php else: ?>
                <div style="width: 120px; height: 120px; border-radius: 50%; background: #ddd; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                    <span style="color: #999; font-size: 14px;">Pas de photo</span>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="info-box">
                <p><strong id="email-label">Email :</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <div class="form-group">
                <label for="firstname" id="firstname-label">Prénom</label>
                <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
            </div>

            <div class="form-group">
                <label for="lastname" id="lastname-label">Nom</label>
                <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
            </div>

            <div class="form-group">
                <label for="photo" id="photo-label">Photo de profil</label>
                <div class="photo-upload">
                    <label for="photo-input" class="photo-upload-label">
                        <span id="upload-btn-text">Choisir une image</span>
                    </label>
                    <input type="file" id="photo-input" name="photo" accept="image/*">
                </div>
                <p style="font-size: 12px; color: #999; margin-top: 8px;" id="file-hint">Formats acceptés: JPG, PNG, GIF (max 5MB)</p>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary" id="save-btn">Enregistrer les modifications</button>
                <a href="profil.php?logout=true" class="btn btn-danger" id="logout-btn">Déconnexion</a>
            </div>
        </form>

        <a href="index.php" class="back-link" id="back-link">← Retour à l'accueil</a>
    </div>

    <script>
        // Traductions multilingues
        const translations = {
            fr: {
                title: 'Mon Profil',
                subtitle: 'Gérez vos informations personnelles',
                'email-label': 'Email :',
                'promotion-label': 'Promotion :',
                'firstname-label': 'Prénom',
                'lastname-label': 'Nom',
                'photo-label': 'Photo de profil',
                'file-hint': 'Formats acceptés: JPG, PNG, GIF (max 5MB)',
                'upload-btn-text': 'Choisir une image',
                'save-btn': 'Enregistrer les modifications',
                'logout-btn': 'Déconnexion',
                'back-link': '← Retour à l\'accueil'
            },
            en: {
                title: 'My Profile',
                subtitle: 'Manage your personal information',
                'email-label': 'Email:',
                'promotion-label': 'Promotion:',
                'firstname-label': 'First Name',
                'lastname-label': 'Last Name',
                'photo-label': 'Profile Photo',
                'file-hint': 'Accepted formats: JPG, PNG, GIF (max 5MB)',
                'upload-btn-text': 'Choose Image',
                'save-btn': 'Save Changes',
                'logout-btn': 'Logout',
                'back-link': '← Back to Home'
            },
            es: {
                title: 'Mi Perfil',
                subtitle: 'Administra tu información personal',
                'email-label': 'Correo electrónico:',
                'promotion-label': 'Promoción:',
                'firstname-label': 'Nombre',
                'lastname-label': 'Apellido',
                'photo-label': 'Foto de Perfil',
                'file-hint': 'Formatos aceptados: JPG, PNG, GIF (máx 5MB)',
                'upload-btn-text': 'Elegir Imagen',
                'save-btn': 'Guardar Cambios',
                'logout-btn': 'Cerrar Sesión',
                'back-link': '← Volver al Inicio'
            }
        };

        // Obtenir la langue depuis localStorage (par défaut français)
        let currentLang = localStorage.getItem('language') || 'fr';

        // Fonction pour changer la langue
        function setLanguage(lang) {
            currentLang = lang;
            localStorage.setItem('language', lang);
            updateLanguage();
        }

        // Fonction pour mettre à jour les textes
        function updateLanguage() {
            const lang = translations[currentLang] || translations.fr;
            
            for (const [key, value] of Object.entries(lang)) {
                const element = document.getElementById(key);
                if (element) {
                    if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                        element.placeholder = value;
                    } else {
                        element.textContent = value;
                    }
                }
            }
        }

        // Initialiser la langue au chargement
        updateLanguage();

        // Gérer le changement de fichier
        const photoInput = document.getElementById('photo-input');
        photoInput.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(event) {
                    const img = document.querySelector('.profile-photo');
                    if (img) {
                        img.src = event.target.result;
                    }
                };
                
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
