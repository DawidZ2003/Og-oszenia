<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: logowanie.php");
    exit;
}

$link = mysqli_connect("127.0.0.1", "dm81079_z20", "Dawidek7003#", "dm81079_z20");

if (!$link) {
    die("Błąd połączenia z bazą danych");
}

mysqli_set_charset($link, "utf8mb4");

$user_id = intval($_SESSION['user_id']);
$error = "";

$categories = mysqli_query($link, "
    SELECT id, name 
    FROM property_categories 
    ORDER BY name ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category_id = intval($_POST['category_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);

    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    $street = trim($_POST['street']);

    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

    if (
        empty($category_id) ||
        empty($title) ||
        empty($description) ||
        empty($price) ||
        empty($city) ||
        empty($latitude) ||
        empty($longitude)
    ) {
        $error = "Uzupełnij wymagane pola i zaznacz punkt na mapie.";
    } elseif (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $error = "Dodaj zdjęcie nieruchomości.";
    } else {

        $google_maps_link = "https://www.google.com/maps?q=" . $latitude . "," . $longitude;

        $geoportal_link = "https://geoportal360.pl/map/#clk="
            . $longitude . "," . $latitude .
            ",18&ctx=18/" . $latitude . "/" . $longitude .
            "&stl=topo";

        $geoportal_krajowy_link = sprintf(
            "https://mapy.geoportal.gov.pl/imapnext/imap/index.html?mapview=%s%%2C%s%%2C1000",
            $latitude,
            $longitude
        );

        $flood_map_link = sprintf(
            "https://mapy.geoportal.gov.pl/imapnext/imap/index.html?moduleId=modulZK&mapview=%s%%2C%s%%2C1000",
            $latitude,
            $longitude
        );

        $air_quality_link = "https://airly.org/map/pl/#" . $latitude . "," . $longitude;

        $stmt = mysqli_prepare($link, "
            INSERT INTO properties
            (
                user_id,
                category_id,
                title,
                description,
                price,
                city,
                postal_code,
                street,
                latitude,
                longitude,
                google_maps_link,
                geoportal_link,
                geoportal_krajowy_link,
                flood_map_link,
                air_quality_link
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        mysqli_stmt_bind_param(
            $stmt,
            "iissdsssddsssss",
            $user_id,
            $category_id,
            $title,
            $description,
            $price,
            $city,
            $postal_code,
            $street,
            $latitude,
            $longitude,
            $google_maps_link,
            $geoportal_link,
            $geoportal_krajowy_link,
            $flood_map_link,
            $air_quality_link
        );

        if (mysqli_stmt_execute($stmt)) {

            $property_id = mysqli_insert_id($link);
            $upload_dir = "uploads/properties/";

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_tmp = $_FILES['photo']['tmp_name'];
            $original_file_name = $_FILES['photo']['name'];
            $file_ext = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($file_ext, $allowed)) {
                $error = "Dozwolone formaty zdjęcia: JPG, JPEG, PNG, WEBP.";
            } else {

                $new_file_name = "property_" . $property_id . "." . $file_ext;
                $file_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $file_path)) {

                    $photo_title = "Zdjęcie nieruchomości";

                    $stmtPhoto = mysqli_prepare($link, "
                        INSERT INTO property_photos
                        (
                            property_id,
                            title,
                            filename,
                            filepath
                        )
                        VALUES (?, ?, ?, ?)
                    ");

                    mysqli_stmt_bind_param(
                        $stmtPhoto,
                        "isss",
                        $property_id,
                        $photo_title,
                        $new_file_name,
                        $file_path
                    );

                    if (mysqli_stmt_execute($stmtPhoto)) {
                        header("Location: index.php?property_id=" . $property_id);
                        exit;
                    } else {
                        $error = "Nie udało się zapisać zdjęcia w bazie.";
                    }

                } else {
                    $error = "Nie udało się zapisać zdjęcia na serwerze.";
                }
            }

        } else {
            $error = "Nie udało się dodać ogłoszenia.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Dodaj ogłoszenie</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <style>
        body {
            background: #f5f6f8;
        }

        #map {
            width: 100%;
            height: 420px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .form-card {
            border: 0;
            border-radius: 12px;
        }

        .form-card .card-header {
            background: #fff;
            font-weight: 600;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="index.php">🏠 Portal sprzedaży nieruchomości</a>

    <div>
        <a href="index.php" class="btn btn-light btn-sm">Powrót</a>
        <a href="wyloguj.php" class="btn btn-danger btn-sm">Wyloguj</a>
    </div>
</nav>

<div class="container mt-4 mb-5">

    <h3 class="mb-4">Dodaj ogłoszenie sprzedaży nieruchomości</h3>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <div class="card form-card shadow-sm mb-4">
            <div class="card-header">
                Podstawowe informacje
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Kategoria</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- wybierz kategorię --</option>

                            <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= intval($cat['id']) ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endwhile; ?>

                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tytuł ogłoszenia</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Cena</label>
                        <input type="number" step="0.01" name="price" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Miasto / miejscowość</label>
                        <input type="text" name="city" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Kod pocztowy</label>
                        <input type="text" name="postal_code" class="form-control">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Ulica</label>
                        <input type="text" name="street" class="form-control">
                    </div>

                </div>
            </div>
        </div>

        <div class="card form-card shadow-sm mb-4">
            <div class="card-header">
                Opis i zdjęcie
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Opis nieruchomości</label>
                    <textarea name="description" class="form-control" rows="6" required></textarea>
                </div>

                <div>
                    <label class="form-label">Zdjęcie nieruchomości</label>
                    <input type="file" name="photo" class="form-control" accept="image/*" required>
                </div>
            </div>
        </div>

        <div class="card form-card shadow-sm mb-4">
            <div class="card-header">
                Lokalizacja nieruchomości
            </div>

            <div class="card-body">
                <p class="text-muted mb-3">
                    Kliknij punkt na mapie. System utworzy linki do Google Maps, Geoportal 360,
                    Geoportalu Krajowego, Zarządzania Kryzysowego oraz Airly.
                </p>

                <div id="map" class="mb-3"></div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Szerokość geograficzna</label>
                        <input type="text" name="latitude" id="latitude" class="form-control" readonly required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Długość geograficzna</label>
                        <input type="text" name="longitude" id="longitude" class="form-control" readonly required>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="index.php" class="btn btn-outline-secondary">
                Anuluj
            </a>

            <button type="submit" class="btn btn-primary px-4">
                Dodaj ogłoszenie
            </button>
        </div>

    </form>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let map = L.map('map').setView([52.2297, 21.0122], 7);
let marker = null;

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
}).addTo(map);

map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(8);
    const lng = e.latlng.lng.toFixed(8);

    document.getElementById('latitude').value = lat;
    document.getElementById('longitude').value = lng;

    if (marker !== null) {
        map.removeLayer(marker);
    }

    marker = L.marker([lat, lng]).addTo(map)
        .bindPopup("Wybrana lokalizacja nieruchomości")
        .openPopup();
});
</script>

</body>
</html>

<?php mysqli_close($link); ?>