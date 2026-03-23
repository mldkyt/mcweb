<?php
$host = getenv('DB_POSTGRES_HOST');
$user = getenv('DB_POSTGRES_USER');
$pass = getenv('DB_POSTGRES_PASSWORD');
$name = getenv('DB_POSTGRES_NAME');
$port = getenv('DB_POSTGRES_PORT') ?: '5432';

$conn = pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['ign'] ?? '';
    if (strlen($name) <= 2 || strlen($name) >= 16) {
        echo "failed submitting: name must be between 2 and 16 characters";
        exit;
    }

    $description = $_POST['description'] ?? '';
    if (strlen($description) <= 10 || strlen($description) >= 2000) {
        echo "failed submitting: description must be between 10 and 2000 characters";
        exit;
    }

    if (!isset($_FILES['image'])) {
        echo "failed parsing file";
        exit;
    }

    $file = $_FILES['image'];
    $fileData = file_get_contents($file['tmp_name']);
    if ($fileData === false) {
        echo "error reading file";
        exit;
    }

    $contentType = mime_content_type($file['tmp_name']);
    if (strpos($contentType, 'image/') !== 0) {
        echo "failed verifying upload: invalid content-type";
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uuid = bin2hex(random_bytes(16));

    $path = "gallery/$uuid.$ext";

    if (!file_put_contents($path, $fileData)) {
        echo "failed writing data";
        exit;
    }

    pg_query_params(
        $conn,
        "INSERT INTO gallery_items (file_name, description, user_name, approved) VALUES ($1, $2, $3, false)",
        [$path, $description, $name]
    );

    header("Location: /gallery", true, 303);
    exit;
}

$res = pg_query(
    $conn,
    "SELECT file_name, description, user_name FROM gallery_items WHERE approved = true ORDER BY created_at DESC"
);

$items = [];
while ($row = pg_fetch_assoc($res)) {
    $items[] = [
        'Name' => $row['user_name'],
        'Url' => "/gallery/" . $row['file_name'],
        'Description' => $row['description']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../../components/meta.php'; ?>
</head>
<body>
    <img src="/spawn.webp" alt="Screenshot of a spawn build. There's a staircase on the front resembling a trans flag and a road to the left resembling a trans flag. There's 2 hearts, a trans one and a lesbian one on top. There's 7 beacons forming sort of a T themed with the lesbian and trans flags. The build has walls mostly out of glass, so it's see through. The inside is mostly empty." id="background">
    <br>
    <br>
    <?php include '../../components/header.php'; ?>
    <main>
        <h1>Gallery</h1>
        <p>Welcome to the gallery!</p>

        <?php foreach ($items as $item): ?>
            <img alt="<?= htmlspecialchars($item['Description']) ?>" src="<?= htmlspecialchars($item['Url']) ?>">
            <p>Image uploaded by <?= htmlspecialchars($item['Name']) ?></p>
        <?php endforeach; ?>

        <h2>Contribute</h2>

        <form action="/gallery" method="post" enctype="multipart/form-data">
            <label for="ign">Name</label>
            <br>
            <input type="text" name="ign" id="ign" placeholder="catgirl..." min="2" max="32">
            <br>

            <label for="description">Short description</label>
            <br>
            <textarea name="description" id="description" minlength="10" maxlength="2000" placeholder="Picture of a..."></textarea>
            <br>

            <label for="image">Image</label>
            <br>
            <input type="file" name="image" id="image" accept="image/*">
            <br>
            <br>
            <button type="submit">Send it!</button>
        </form>

        <p>Please note that sent gallery items will be needed to be approved by an admin. I would also appreciate if you convert your work into a JPG or WEBP using e.g. mspaint to make the website faster for others.</p>
    </main>
</body>
</html>