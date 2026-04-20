<?php
session_start();
require_once '../php/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($email) || empty($password)) {
        $error = 'Email et mot de passe requis.';
    } else {
        $stmt = $conn->prepare('SELECT user_id, firstname, lastname, password, photo_profil FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $email;
                $_SESSION['firstname'] = $user['firstname'];
                $_SESSION['lastname'] = $user['lastname'];
                $_SESSION['photo_profil'] = $user['photo_profil'];
                header('Location: ../Pages/index.php');
                exit();
            } else {
                $error = 'Email ou mot de passe incorrect.';
            }
        } else {
            $error = 'Email ou mot de passe incorrect.';
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
  <title>Nom – Login</title>
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
    <h2 class="card__title" id="card-title">Login</h2>
    <p class="card__subtitle" id="card-subtitle">Enter your Aurion credentials to access the app</p>
    
    <?php if ($error): ?>
      <div style="color: #d32f2f; background: #ffebee; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    
    <form method="POST" id="login-form">
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
          <input type="password" id="password" name="password" value="" placeholder="password1234" autocomplete="current-password" required/>
          <button class="toggle-pw" id="toggle-pw" aria-label="Show / hide password" type="button" onclick="togglePassword()">
             
          </button>
        </div>
      </div>

      <button class="btn-submit" type="submit" id="submit-btn">Se connecter</button>
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

    const translations = {
      en: {
        pageTitle: 'Nom – Login',
        cardTitle: 'Login',
        subtitle: 'Enter your Junia credentials to access the app',
        emailLabel: 'Email',
        emailPlaceholder: 'name.surname@student.junia.com',
        passwordLabel: 'Password',
        passwordPlaceholder: 'password1234',
        submitButton: 'Log in',
        backToHomeButton: 'Back to Home',
        signUpButton: 'Don\'t have an account? Sign up',
        langLabel: 'Language',
        togglePwAria: 'Show / hide password'
      },
      fr: {
        pageTitle: 'Nom– Connexion',
        cardTitle: 'Connexion',
        subtitle: 'Entrez vos identifiants Junia pour accéder à l\'application',
        emailLabel: 'E-mail',
        emailPlaceholder: 'prenom.nom@student.junia.com',
        passwordLabel: 'Mot de passe',
        passwordPlaceholder: 'motdepasse1234',
        submitButton: 'Se connecter',
        backToHomeButton: 'Retour à l\'accueil',
        signUpButton: 'Vous n\'avez pas de compte ? S\'inscrire',
        langLabel: 'Langue',
        togglePwAria: 'Afficher / masquer le mot de passe'
      },
      es: {
        pageTitle: 'Nom – Inicio de sesión',
        cardTitle: 'Iniciar sesión',
        subtitle: 'Introduce tus credenciales de Junia para acceder a la aplicación',
        emailLabel: 'Correo electrónico',
        emailPlaceholder: 'nombre.apellido@student.junia.com',
        passwordLabel: 'Contraseña',
        passwordPlaceholder: 'contrasena1234',
        submitButton: 'Iniciar sesión',
        backToHomeButton: 'Volver al inicio',
        signUpButton: '¿No tienes cuenta? Registrarse',
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
      document.getElementById('email-label').textContent = t.emailLabel;
      document.getElementById('email').placeholder = t.emailPlaceholder;
      document.getElementById('password-label').textContent = t.passwordLabel;
      document.getElementById('password').placeholder = t.passwordPlaceholder;
      document.getElementById('submit-btn').textContent = t.submitButton;
      document.getElementById('back-home-btn').textContent = t.backToHomeButton;
      document.getElementById('sign-up-btn').textContent = t.signUpButton;
      document.getElementById('lang-label').lastChild.textContent = ` ${t.langLabel}`;
      document.getElementById('toggle-pw').setAttribute('aria-label', t.togglePwAria);
    }

    document.addEventListener('DOMContentLoaded', () => {
      const activeBtn = document.querySelector('.lang-btn.active');
      if (activeBtn) setLang(activeBtn);
    });
  </script>
  <div class="bottom-links">
    <button class="lang-btn" onclick="window.location.href='../Pages/index.php'" id="back-home-btn">Back to Home</button>
    <button class="lang-btn" onclick="window.location.href='../Pages/inscription.php'" id="sign-up-btn">Don't have an account? Sign up</button>
  </div>
</body>
</html>