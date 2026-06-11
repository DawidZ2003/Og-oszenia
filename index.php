<?php
session_start();

$link = mysqli_connect("127.0.0.1", "dm81079_z20", "Dawidek7003#", "dm81079_z20");

if (!$link) {
    die("Błąd połączenia z bazą danych");
}

mysqli_set_charset($link, "utf8mb4");

$current_user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_property']) && isset($_SESSION['user_id'])) {

    $property_id = intval($_POST['property_id']);
    $user_id = intval($_SESSION['user_id']);

    if ($is_admin) {
        $stmt = mysqli_prepare($link, "DELETE FROM properties WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $property_id);
    } else {
        $stmt = mysqli_prepare($link, "DELETE FROM properties WHERE id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $property_id, $user_id);
    }

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

$properties = mysqli_query($link, "
    SELECT 
        p.id,
        p.title,
        p.user_id,
        p.price,
        p.city,
        c.name AS category_name,
        u.username
    FROM properties p
    JOIN users u ON p.user_id = u.id
    JOIN property_categories c ON p.category_id = c.id
    ORDER BY p.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Portal nieruchomości</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f6f8;
        }

        .property-photo {
            height: 450px;
            object-fit: cover;
        }

        .info-card {
            border: 0;
            border-radius: 14px;
        }

        .spatial-box {
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 16px;
            height: 100%;
            background: #fff;
        }

        .sidebar {
            max-height: calc(100vh - 80px);
            overflow-y: auto;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="index.php">🏠 Portal sprzedaży nieruchomości</a>

    <div class="text-white d-flex align-items-center gap-2">
        <?php if (isset($_SESSION['username'])): ?>

            <span>Zalogowany: <b><?= htmlspecialchars($_SESSION['username']) ?></b></span>
            <a href="wyloguj.php" class="btn btn-danger btn-sm">Wyloguj</a>

        <?php else: ?>

            <a href="logowanie.php" class="btn btn-success btn-sm">Logowanie</a>
            <a href="rejestracja.php" class="btn btn-primary btn-sm">Rejestracja</a>

        <?php endif; ?>
    </div>
</nav>

<div class="container-fluid mt-3 mb-4">
    <div class="row">

        <div class="col-md-3 border-end sidebar">
            <h5>Ogłoszenia sprzedaży</h5>

            <?php if (isset($_SESSION['user_id'])): ?>

                <a href="create_property_form.php" class="btn btn-primary w-100 mb-2">
                    ➕ Dodaj ogłoszenie
                </a>

                <?php if ($is_admin): ?>
                    <a href="zarzadzanie_uzytkownikami.php"
                       class="btn btn-warning w-100 mb-3">
                        👥 Zarządzanie użytkownikami
                    </a>
                <?php endif; ?>

            <?php endif; ?>

            <div class="list-group">
                <?php while ($p = mysqli_fetch_assoc($properties)): ?>

                    <div class="list-group-item">
                        <a class="text-decoration-none d-block"
                           href="index.php?property_id=<?= intval($p['id']) ?>">

                            <b><?= htmlspecialchars($p['title']) ?></b>

                            <br>

                            <span class="badge bg-success">
                                <?= htmlspecialchars($p['category_name']) ?>
                            </span>

                            <br>

                            <small class="text-muted">
                                <?= htmlspecialchars($p['city']) ?>
                            </small>

                            <br>

                            <small class="text-muted">
                                Cena:
                                <?= number_format($p['price'], 2, ',', ' ') ?> zł
                            </small>

                            <br>

                            <small class="text-muted">
                                Autor:
                                <?= htmlspecialchars($p['username']) ?>
                            </small>
                        </a>

                        <?php if (
                            isset($_SESSION['user_id']) &&
                            (
                                intval($_SESSION['user_id']) === intval($p['user_id'])
                                || $is_admin
                            )
                        ): ?>
                            <form method="post" class="mt-2" onsubmit="return confirm('Usunąć ogłoszenie?');">
                                <input type="hidden" name="property_id" value="<?= intval($p['id']) ?>">

                                <button type="submit" name="delete_property" class="btn btn-danger btn-sm w-100">
                                    Usuń ogłoszenie
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                <?php endwhile; ?>
            </div>
        </div>

        <div class="col-md-9">

            <?php
            if (isset($_GET['property_id'])) {

                $id = intval($_GET['property_id']);

                $stmt = mysqli_prepare($link, "
                    SELECT 
                        p.*,
                        c.name AS category_name,
                        u.username
                    FROM properties p
                    JOIN property_categories c ON p.category_id = c.id
                    JOIN users u ON p.user_id = u.id
                    WHERE p.id = ?
                ");

                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);

                $propertyResult = mysqli_stmt_get_result($stmt);
                $propertyInfo = mysqli_fetch_assoc($propertyResult);

                if (!$propertyInfo) {
                    echo "<div class='alert alert-danger'>Nie znaleziono ogłoszenia.</div>";
                } else {

                    $title = htmlspecialchars($propertyInfo['title']);
                    $description = nl2br(htmlspecialchars($propertyInfo['description']));
                    $category = htmlspecialchars($propertyInfo['category_name']);
                    $owner = htmlspecialchars($propertyInfo['username']);
                    $price = number_format($propertyInfo['price'], 2, ',', ' ');

                    $city = htmlspecialchars($propertyInfo['city']);
                    $postal_code = htmlspecialchars($propertyInfo['postal_code'] ?? '');
                    $street = htmlspecialchars($propertyInfo['street'] ?? '');

                    $latitude = $propertyInfo['latitude'] ?? null;
                    $longitude = $propertyInfo['longitude'] ?? null;

                    $google_maps_link = htmlspecialchars($propertyInfo['google_maps_link'] ?? '');
                    $geoportal_link = htmlspecialchars($propertyInfo['geoportal_link'] ?? '');
                    $geoportal_krajowy_link = htmlspecialchars($propertyInfo['geoportal_krajowy_link'] ?? '');
                    $flood_map_link = htmlspecialchars($propertyInfo['flood_map_link'] ?? '');
                    $air_quality_link = htmlspecialchars($propertyInfo['air_quality_link'] ?? '');

                    $stmtPhoto = mysqli_prepare($link, "
                        SELECT filepath
                        FROM property_photos
                        WHERE property_id = ?
                        LIMIT 1
                    ");

                    mysqli_stmt_bind_param($stmtPhoto, "i", $id);
                    mysqli_stmt_execute($stmtPhoto);

                    $photoResult = mysqli_stmt_get_result($stmtPhoto);
                    $photo = mysqli_fetch_assoc($photoResult);

                    echo "<div class='card shadow-sm mb-4 info-card'>";

                    if ($photo) {
                        $img = htmlspecialchars($photo['filepath']);

                        echo "
                            <img src='$img'
                                 class='card-img-top property-photo'
                                 alt='Zdjęcie nieruchomości'>
                        ";
                    } else {
                        echo "
                            <div class='bg-light d-flex align-items-center justify-content-center'
                                 style='height:300px;'>
                                <span class='text-muted'>Brak zdjęcia nieruchomości</span>
                            </div>
                        ";
                    }

                    echo "
                        <div class='card-body'>
                            <h3 class='mb-3'>$title</h3>

                            <p class='mb-1'><strong>Kategoria:</strong> $category</p>
                            <p class='mb-1'><strong>Autor ogłoszenia:</strong> $owner</p>
                            <p class='mb-1'><strong>Cena:</strong> $price zł</p>

                            <p class='mb-1'>
                                <strong>Lokalizacja:</strong> $city
                    ";

                    if (!empty($postal_code)) {
                        echo ", $postal_code";
                    }

                    if (!empty($street)) {
                        echo ", $street";
                    }

                    echo "
                            </p>

                            <hr>

                            <p class='mb-0'>
                                <strong>Opis:</strong><br>
                                $description
                            </p>
                        </div>
                    </div>
                    ";

                    echo "<div class='card shadow-sm mb-4 info-card'>";
                    echo "<div class='card-header bg-white fw-bold'>🧭 Mapy i analizy lokalizacji</div>";
                    echo "<div class='card-body'>";

                    echo "<div class='d-flex flex-wrap gap-2 mb-4'>";

                    if (!empty($google_maps_link)) {
                        echo "
                        <a href='$google_maps_link' target='_blank' class='btn btn-outline-primary btn-sm'>
                            📍 Google Maps
                        </a>
                        ";
                    }

                    if (!empty($geoportal_link)) {
                        echo "
                        <a href='$geoportal_link' target='_blank' class='btn btn-outline-success btn-sm'>
                            🗺️ Geoportal 360
                        </a>
                        ";
                    }

                    if (!empty($geoportal_krajowy_link)) {
                        echo "
                        <a href='$geoportal_krajowy_link' target='_blank' class='btn btn-outline-dark btn-sm'>
                            🇵🇱 Geoportal Krajowy
                        </a>
                        ";
                    }

                    if (!empty($flood_map_link)) {
                        echo "
                        <a href='$flood_map_link' target='_blank' class='btn btn-outline-info btn-sm'>
                            🌊 Zarządzanie kryzysowe
                        </a>
                        ";
                    }

                    if (!empty($air_quality_link)) {
                        echo "
                        <a href='$air_quality_link' target='_blank' class='btn btn-outline-secondary btn-sm'>
                            🌫️ Jakość powietrza 
                        </a>
                        ";
                    }

                    echo "</div>";

                    echo "
                        <div class='row g-3'>
                            <div class='col-md-3'>
                                <div class='spatial-box'>
                                    <div class='fw-bold mb-1'>📍 Lokalizacja</div>
                                    <small class='text-muted'>
                                        $city";

                    if (!empty($street)) {
                        echo ", $street";
                    }

                    echo "
                                    </small>
                                </div>
                            </div>

                            <div class='col-md-3'>
                                <div class='spatial-box'>
                                    <div class='fw-bold mb-1'>💰 Cena nieruchomości</div>
                                    <small class='text-muted'>$price zł</small>
                                </div>
                            </div>

                            <div class='col-md-3'>
                                <div class='spatial-box'>
                                    <div class='fw-bold mb-1'>🗺️ Granice działek</div>
                                    <small class='text-muted'>
                                        Dostępne przez Geoportal 360 lub Geoportal Krajowy.
                                    </small>
                                </div>
                            </div>

                        </div>
                        
                        <br>
                    ";
                    
                    

if (!empty($latitude) && !empty($longitude)) {
    $lat = htmlspecialchars($latitude);
    $lng = htmlspecialchars($longitude);

    echo "
    <div class='card mb-4'>
        <div class='card-header'>
            Interaktywna lokalizacja nieruchomości — Google Maps
        </div>

        <div class='card-body p-0'>
            <iframe
                width='100%'
                height='350'
                style='border:0;'
                loading='lazy'
                allowfullscreen
                src='https://maps.google.com/maps?q=$lat,$lng&z=16&output=embed'>
            </iframe>
        </div>
    </div>
    ";
}

                    echo "</div>";
                    echo "</div>";
                }

            } else {
                echo "<div class='card shadow-sm info-card'>";
                echo "<div class='card-body'>";
                echo "<h4>Wybierz ogłoszenie z menu</h4>";
                echo "<p class='text-muted mb-0'>Możesz przeglądać oferty sprzedaży nieruchomości jako gość.</p>";
                echo "</div>";
                echo "</div>";
            }
            ?>

        </div>

    </div>
</div>

</body>
</html>

<?php mysqli_close($link); ?>