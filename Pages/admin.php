<?php
session_start();
require_once '../php/config.php';

// 🔐 Sécurité admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php');
    exit;
}

/* ===========================
   ACTIONS
=========================== */

// BAN USER
if (isset($_GET['ban'])) {
    $id = intval($_GET['ban']);
    if ($id != $_SESSION['user_id']) {
        $conn->query("UPDATE users SET is_banned = 1 WHERE user_id = $id");
    }
}

// UNBAN USER
if (isset($_GET['unban'])) {
    $id = intval($_GET['unban']);
    $conn->query("UPDATE users SET is_banned = 0 WHERE user_id = $id");
}

// DELETE RESERVATION ⭐ NOUVEAU
if (isset($_GET['delete_reservation'])) {
    $id = intval($_GET['delete_reservation']);

    $stmt = $conn->prepare("DELETE FROM room_reservations WHERE reservation_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

// ADD BUILDING
if (isset($_POST['add_building'])) {
    $stmt = $conn->prepare("INSERT INTO buildings (name, code, location) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $_POST['name'], $_POST['code'], $_POST['location']);
    $stmt->execute();
}

// ADD ROOM
if (isset($_POST['add_room'])) {
    $stmt = $conn->prepare("INSERT INTO rooms (room_name, building_id, capacity) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $_POST['room_name'], $_POST['building_id'], $_POST['capacity']);
    $stmt->execute();
}

/* ===========================
   DATA
=========================== */

$users = $conn->query("SELECT * FROM users");

$buildings = $conn->query("SELECT * FROM buildings");

$rooms = $conn->query("
    SELECT r.*, b.name as building_name 
    FROM rooms r 
    LEFT JOIN buildings b ON r.building_id = b.building_id
");

// ⭐ Réservations
$reservations = $conn->query("
    SELECT rr.*, u.firstname, u.lastname
    FROM room_reservations rr
    LEFT JOIN users u ON rr.user_id = u.user_id
    ORDER BY rr.start_time DESC
");

// STATS
$totalUsers = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$totalRooms = $conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
$totalBuildings = $conn->query("SELECT COUNT(*) as c FROM buildings")->fetch_assoc()['c'];
$totalReservations = $conn->query("SELECT COUNT(*) as c FROM room_reservations")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>

<style>
body {
    margin:0;
    font-family: Arial;
    background:#f4f6f9;
}

.sidebar {
    width:220px;
    height:100vh;
    background:#1a1a1a;
    color:white;
    position:fixed;
    padding:20px;
}

.sidebar a {
    display:block;
    color:white;
    text-decoration:none;
    margin:10px 0;
    opacity:0.7;
}
.sidebar a:hover { opacity:1; }

.main {
    margin-left:240px;
    padding:30px;
}

.cards {
    display:flex;
    gap:20px;
    margin-bottom:30px;
}

.card {
    flex:1;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

.section {
    background:white;
    padding:20px;
    border-radius:10px;
    margin-bottom:30px;
}

table {
    width:100%;
    border-collapse: collapse;
}

th, td {
    padding:10px;
    border-bottom:1px solid #ddd;
}

th {
    background:#1a1a1a;
    color:white;
}

.btn {
    padding:5px 10px;
    border-radius:5px;
    text-decoration:none;
    font-size:12px;
    display:inline-block;
}

.ban { background:red; color:white; }
.unban { background:green; color:white; }

input, select {
    padding:8px;
    margin:5px 0;
    width:100%;
}

form { margin-top:10px; }
</style>
</head>

<body>

<div class="sidebar">
    <h2>Admin</h2>
    <a href="#dashboard">Dashboard</a>
    <a href="#users">Utilisateurs</a>
    <a href="#buildings">Bâtiments</a>
    <a href="#rooms">Salles</a>
    <a href="#reservations">Réservations</a>
</div>

<div class="main">

<!-- DASHBOARD -->
<h1 id="dashboard">Dashboard</h1>

<div class="cards">
    <div class="card"><h3>Utilisateurs</h3><p><?= $totalUsers ?></p></div>
    <div class="card"><h3>Salles</h3><p><?= $totalRooms ?></p></div>
    <div class="card"><h3>Bâtiments</h3><p><?= $totalBuildings ?></p></div>
    <div class="card"><h3>Réservations</h3><p><?= $totalReservations ?></p></div>
</div>

<!-- USERS -->
<div class="section" id="users">
<h2>Utilisateurs</h2>

<table>
<tr><th>ID</th><th>Nom</th><th>Email</th><th>Status</th><th>Action</th></tr>

<?php while($u = $users->fetch_assoc()): ?>
<tr>
<td><?= $u['user_id'] ?></td>
<td><?= $u['firstname'] ?> <?= $u['lastname'] ?></td>
<td><?= $u['email'] ?></td>
<td><?= $u['is_banned'] ? 'Banni' : 'Actif' ?></td>
<td>
<?php if(!$u['is_banned']): ?>
<a class="btn ban" href="?ban=<?= $u['user_id'] ?>">Ban</a>
<?php else: ?>
<a class="btn unban" href="?unban=<?= $u['user_id'] ?>">Unban</a>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- BUILDINGS -->
<div class="section" id="buildings">
<h2>Bâtiments</h2>

<form method="POST">
<input type="text" name="name" placeholder="Nom" required>
<input type="text" name="code" placeholder="Code">
<input type="text" name="location" placeholder="Lieu">
<button name="add_building">Ajouter</button>
</form>

<table>
<tr><th>ID</th><th>Nom</th><th>Code</th><th>Lieu</th></tr>

<?php while($b = $buildings->fetch_assoc()): ?>
<tr>
<td><?= $b['building_id'] ?></td>
<td><?= $b['name'] ?></td>
<td><?= $b['code'] ?></td>
<td><?= $b['location'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- ROOMS -->
<div class="section" id="rooms">
<h2>Salles</h2>

<form method="POST">
<input type="text" name="room_name" placeholder="Nom salle" required>

<select name="building_id">
<?php
$buildings2 = $conn->query("SELECT * FROM buildings");
while($b = $buildings2->fetch_assoc()): ?>
<option value="<?= $b['building_id'] ?>"><?= $b['name'] ?></option>
<?php endwhile; ?>
</select>

<input type="number" name="capacity" placeholder="Capacité">
<button name="add_room">Ajouter</button>
</form>

<table>
<tr><th>ID</th><th>Nom</th><th>Bâtiment</th><th>Capacité</th></tr>

<?php while($r = $rooms->fetch_assoc()): ?>
<tr>
<td><?= $r['room_id'] ?></td>
<td><?= $r['room_name'] ?></td>
<td><?= $r['building_name'] ?></td>
<td><?= $r['capacity'] ?></td>
</tr>
<?php endwhile; ?>
</table>
</div>

<!-- ⭐ RESERVATIONS -->
<div class="section" id="reservations">
<h2>Réservations des salles</h2>

<table>
<tr>
<th>ID</th>
<th>Utilisateur</th>
<th>Salle</th>
<th>Bâtiment</th>
<th>Début</th>
<th>Fin</th>
<th>Durée</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php while($res = $reservations->fetch_assoc()): ?>
<tr>
<td><?= $res['reservation_id'] ?></td>
<td><?= $res['firstname'] ?> <?= $res['lastname'] ?></td>
<td><?= $res['room_name'] ?></td>
<td><?= $res['building_code'] ?></td>
<td><?= $res['start_time'] ?></td>
<td><?= $res['end_time'] ?></td>
<td><?= $res['duration_minutes'] ?> min</td>
<td><?= $res['status'] ?></td>
<td>
<a class="btn ban"
   href="?delete_reservation=<?= $res['reservation_id'] ?>"
   onclick="return confirm('Supprimer cette réservation ?')">
   Supprimer
</a>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

</div>

</body>
</html>