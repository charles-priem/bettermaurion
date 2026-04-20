<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nom – Login</title>
  <link rel="stylesheet" href="../css/style_connexion.css" />

</head>
<body>
  <h1 class="logo"></h1>
  <div class="card">
    <h2 class="card__title" id="card-title">Login</h2>
    <p class="card__subtitle" id="card-subtitle">Enter your Aurion credentials to access the app</p>
    <div class="form-group">
      <label for="email" id="email-label">Email</label>
      <input
        type="email"
        id="email"
        value=""
        placeholder="name.surname@student.junia.com"
        autocomplete="email"
      />
    </div>

    <!-- Password -->
    <div class="form-group">
      <label for="password" id="password-label">Password</label>
      <div class="input-wrap">
        <input type="password" id="password" value="" placeholder="password1234" autocomplete="current-password"/>
        <button class="toggle-pw" id="toggle-pw" aria-label="Show / hide password" onclick="togglePassword()">
           
        </button>
      </div>
    </div>

    <button class="btn-submit" id="submit-btn">Se connecter</button>
  </div>


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
      document.getElementById('lang-label').lastChild.textContent = ` ${t.langLabel}`;
      document.getElementById('toggle-pw').setAttribute('aria-label', t.togglePwAria);
    }

    document.addEventListener('DOMContentLoaded', () => {
      const activeBtn = document.querySelector('.lang-btn.active');
      if (activeBtn) setLang(activeBtn);
    });
  </script>
  <div>
    <button onclick="window.location.href='../Pages/index.php'">Back to Home</button>
  </div>
</body>
</html>