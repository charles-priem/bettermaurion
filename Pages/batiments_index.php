<?php
session_start();
require_once '../php/config.php';
include_once 'header.php';
// Récupération de tous les bâtiments (si la table existe)
$batiments = [];
$result = $conn->query("SELECT building_id, name FROM buildings ORDER BY building_id ASC");
if ($result && $result->num_rows > 0) {
    $batiments = $result->fetch_all(MYSQLI_ASSOC);
}

// Mappage des images pour chaque bâtiment
$images_map = [
    1 => ['img' => '../images/Ic1_horizontal_1.jpg', 'alt' => 'Îlot Colson 1', 'id' => 'ic1'],
    2 => ['img' => '../images/Isen_horizontal_2.JPG', 'alt' => 'Îlot Colson 2', 'id' => 'ic2'],
    3 => ['img' => '../images/Isa_vertical_1.JPG', 'alt' => 'Albert Le Grand', 'id' => 'alg'],
    4 => ['img' => '../images/IMG_0943.JPG', 'alt' => 'Norbert Ségard', 'id' => 'ns'],
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="../css/styles.php">
    <title>Bâtiments - Junia Salles</title>
    <link rel="icon" href="../images/Logo_Roomia.png" type="image/x-icon">
    <style>
        #page_batiments {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 40px 20px;
            max-width: 1200px;
            margin: 40px auto;
        }

        #page_batiments a {
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0;
        }

        #page_batiments a:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        #page_batiments img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }

        #page_batiments h1 {
            font-size: 24px;
            margin: 0 0 15px 0;
            padding: 0 20px 20px 20px;
            color: #1a1a1a;
            text-align: center;
            width: 100%;
        }

        #page_batiments a:hover h1 {
            color: #0066cc;
        }
    </style>
</head>
<body>


    <main id="page_batiments">
        <?php foreach ($batiments as $batiment): 
            $batID = $batiment["building_id"];
            $bat = $batiment["name"];
            $img_data = $images_map[$batID] ?? ['img' => '#', 'alt' => $bat, 'id' => 'bat' . $batID];
        ?>
            <a href="?id=<?= $batID ?>">
                <img id="<?= $img_data['id'] ?>" src="<?= $img_data['img'] ?>" alt="<?= $img_data['alt'] ?>">
                <h1><?= htmlspecialchars($bat) ?></h1>
            </a>
        <?php endforeach; ?>
    </main>

</body>
</html>
