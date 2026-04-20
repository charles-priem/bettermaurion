<?php
session_start();
require_once '../php/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm-password'] ?? '');
    
    // Validation
    if (empty($firstname) || empty($lastname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Tous les champs sont requis.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } else {
        // Vérifier si l'email existe déjà
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Cet email est déjà enregistré.';
        } else {
            // Insérer le nouvel utilisateur
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (firstname, lastname, email, password, photo_profil) VALUES (?, ?, ?, ?, ?)');
            $photo = 'default_profile.png';
            $stmt->bind_param('sssss', $firstname, $lastname, $email, $hashed_password, $photo);
            
            if ($stmt->execute()) {
                $success = 'Inscription réussie! Redirection vers la connexion...';
                header('refresh:2; url=connexion.php');
            } else {
                $error = 'Erreur lors de l\'inscription. Veuillez réessayer.';
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nom – Inscription</title>
  <link rel="stylesheet" href="../css/style_connexion.css" />
  <style>
    .bottom-links {
      display: flex;
      flex-direction: column;
      gap: 10px;
      align-items: center;
      margin-top: 20px;
    }
    .bottom-links button {
      width: 200px;
    }
  </style>

</head>
<body>
  <h1 class="logo"></h1>
  <div class="card">
    <h2 class="card__title" id="card-title">Inscription</h2>
    <p class="card__subtitle" id="card-subtitle">Create your account to access the app</p>
    
    <?php if ($error): ?>
      <div style="color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div style="color: #388e3c; background: #e8f5e9; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>
    
    <form method="POST" id="signup-form">
      <div class="form-group">
        <label for="firstname" id="firstname-label">First Name</label>
        <input
          type="text"
          id="firstname"
          name="firstname"
          value=""
          placeholder="John"
          autocomplete="given-name"
          required
        />
      </div>

      <div class="form-group">
        <label for="lastname" id="lastname-label">Last Name</label>
        <input
          type="text"
          id="lastname"
          name="lastname"
          value=""
          placeholder="Doe"
          autocomplete="family-name"
          required
        />
      </div>

      <div class="form-group">
        <label for="email" id="email-label">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          value=""
          placeholder="name.surname@student.junia.com"
          autocomplete="email"
          required
        />
      </div>

      <!-- Password -->
      <div class="form-group">
        <label for="password" id="password-label">Password</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" value="" placeholder="password1234" autocomplete="new-password" required/>
          <button class="toggle-pw" id="toggle-pw" type="button" aria-label="Show / hide password" onclick="togglePassword()">
             
          </button>
        </div>
      </div>

      <!-- Confirm Password -->
      <div class="form-group">
        <label for="confirm-password" id="confirm-password-label">Confirm Password</label>
        <div class="input-wrap">
          <input type="password" id="confirm-password" name="confirm-password" value="" placeholder="password1234" autocomplete="new-password" required/>
          <button class="toggle-pw" id="toggle-pw-confirm" type="button" aria-label="Show / hide password" onclick="togglePasswordConfirm()">
             
          </button>
        </div>
      </div>

      <button class="btn-submit" type="submit" id="submit-btn">S'inscrire</button>
    </form>

  <div class="lang-switcher">
    <div class="lang-label" id="lang-label">
      <!-- translate icon -->
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
           viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 8l6 6"/>
        <path d="M4 14l6-6 2-3"/>
        <path d="M2 5h12"/>
        <path d="M7 2h1"/>
        <path d="M22 22l-5-10-5 10"/>
        <path d="M14 18h6"/>
      </svg>
      Language
    </div>
    <div class="lang-options">
      <button class="lang-btn" data-lang="fr" onclick="setLang(this)">French</button>
      <button class="lang-btn active" data-lang="en" onclick="setLang(this)">English</button>
      <button class="lang-btn" data-lang="es" onclick="setLang(this)">Spanish</button>
    </div>
  </div>

  <div class="bottom-links">
    <button class="lang-btn" onclick="window.location.href='../Pages/index.php'" id="back-home-btn">Back to Home</button>
    <button class="lang-btn" onclick="window.location.href='../Pages/connexion.php'" id="login-link-btn">Already have an account? Log in</button>
  </div>

  <script>
    function togglePassword() {
      const input   = document.getElementById('password');
      const iconOn  = document.getElementById('icon-eye');
      const iconOff = document.getElementById('icon-eye-off');
      const isHidden = input.type === 'password';
      input.type      = isHidden ? 'text' : 'password';
      iconOn.style.display  = isHidden ? 'none'  : '';
      iconOff.style.display = isHidden ? ''      : 'none';
    }

    function togglePasswordConfirm() {
      const input   = document.getElementById('confirm-password');
      const iconOn  = document.getElementById('icon-eye');
      const iconOff = document.getElementById('icon-eye-off');
      const isHidden = input.type === 'password';
      input.type      = isHidden ? 'text' : 'password';
      iconOn.style.display  = isHidden ? 'none'  : '';
      iconOff.style.display = isHidden ? ''      : 'none';
    }

    const translations = {
      en: {
        pageTitle: 'Nom – Sign Up',
        cardTitle: 'Sign Up',
        subtitle: 'Create your account to access the app',
        firstnameLabel: 'First Name',
        firstnamePlaceholder: 'John',
        lastnameLabel: 'Last Name',
        lastnamePlaceholder: 'Doe',
        emailLabel: 'Email',
        emailPlaceholder: 'name.surname@student.junia.com',
        passwordLabel: 'Password',
        passwordPlaceholder: 'password1234',
        confirmPasswordLabel: 'Confirm Password',
        confirmPasswordPlaceholder: 'password1234',
        submitButton: 'Sign Up',
        backToHomeButton: 'Back to Home',
        loginLinkButton: 'Already have an account? Log in',
        langLabel: 'Language',
        togglePwAria: 'Show / hide password'
      },
      fr: {
        pageTitle: 'Nom – Inscription',
        cardTitle: 'Inscription',
        subtitle: 'Créez votre compte pour accéder à l\'application',
        firstnameLabel: 'Prénom',
        firstnamePlaceholder: 'Jean',
        lastnameLabel: 'Nom',
        lastnamePlaceholder: 'Dupont',
        emailLabel: 'E-mail',
        emailPlaceholder: 'prenom.nom@student.junia.com',
        passwordLabel: 'Mot de passe',
        passwordPlaceholder: 'motdepasse1234',
        confirmPasswordLabel: 'Confirmer le mot de passe',
        confirmPasswordPlaceholder: 'motdepasse1234',
        submitButton: 'S\'inscrire',
        backToHomeButton: 'Retour à l\'accueil',
        loginLinkButton: 'Vous avez déjà un compte ? Se connecter',
        langLabel: 'Langue',
        togglePwAria: 'Afficher / masquer le mot de passe'
      },
      es: {
        pageTitle: 'Nom – Registrarse',
        cardTitle: 'Registrarse',
        subtitle: 'Crea tu cuenta para acceder a la aplicación',
        firstnameLabel: 'Nombre',
        firstnamePlaceholder: 'Juan',
        lastnameLabel: 'Apellido',
        lastnamePlaceholder: 'García',
        emailLabel: 'Correo electrónico',
        emailPlaceholder: 'nombre.apellido@student.junia.com',
        passwordLabel: 'Contraseña',
        passwordPlaceholder: 'contrasena1234',
        confirmPasswordLabel: 'Confirmar contraseña',
        confirmPasswordPlaceholder: 'contrasena1234',
        submitButton: 'Registrarse',
        backToHomeButton: 'Volver al inicio',
        loginLinkButton: '¿Ya tienes una cuenta? Inicia sesión',
        langLabel: 'Idioma',
        togglePwAria: 'Mostrar / ocultar contraseña'
      }
    };

    function setLang(btn) {
      document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const lang = btn.dataset.lang || 'en';
      const t = translations[lang] || translations.en;

      document.documentElement.lang = lang;
      document.title = t.pageTitle;
      document.getElementById('card-title').textContent = t.cardTitle;
      document.getElementById('card-subtitle').textContent = t.subtitle;
      document.getElementById('firstname-label').textContent = t.firstnameLabel;
      document.getElementById('firstname').placeholder = t.firstnamePlaceholder;
      document.getElementById('lastname-label').textContent = t.lastnameLabel;
      document.getElementById('lastname').placeholder = t.lastnamePlaceholder;
      document.getElementById('email-label').textContent = t.emailLabel;
      document.getElementById('email').placeholder = t.emailPlaceholder;
      document.getElementById('password-label').textContent = t.passwordLabel;
      document.getElementById('password').placeholder = t.passwordPlaceholder;
      document.getElementById('confirm-password-label').textContent = t.confirmPasswordLabel;
      document.getElementById('confirm-password').placeholder = t.confirmPasswordPlaceholder;
      document.getElementById('submit-btn').textContent = t.submitButton;
      document.getElementById('back-home-btn').textContent = t.backToHomeButton;
      document.getElementById('login-link-btn').textContent = t.loginLinkButton;
      document.getElementById('lang-label').lastChild.textContent = ` ${t.langLabel}`;
      document.getElementById('toggle-pw').setAttribute('aria-label', t.togglePwAria);
      document.getElementById('toggle-pw-confirm').setAttribute('aria-label', t.togglePwAria);
    }

    document.addEventListener('DOMContentLoaded', () => {
      const activeBtn = document.querySelector('.lang-btn.active');
      if (activeBtn) setLang(activeBtn);
    });
  </script>
</body>
</html>
