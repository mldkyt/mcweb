<?php
$adminUser = getenv('ADMIN_USER');
$adminPass = getenv('ADMIN_PASS');

if (
    !isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== $adminUser ||
    $_SERVER['PHP_AUTH_PW'] !== $adminPass
) {
    header('WWW-Authenticate: Basic realm="restricted", charset="UTF-8"');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$host = getenv('DB_POSTGRES_HOST');
$user = getenv('DB_POSTGRES_USER');
$pass = getenv('DB_POSTGRES_PASSWORD');
$name = getenv('DB_POSTGRES_NAME');
$port = getenv('DB_POSTGRES_PORT') ?: '5432';

$conn = pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    if (!ctype_digit($id)) {
        echo "failed to parse data: ID is not a number";
        exit;
    }
    $id = (int)$id;

    if (isset($_POST['delete']) && $_POST['delete'] === 'on') {
        $res = pg_query_params($conn, "SELECT file_name FROM gallery_items WHERE id = $1", [$id]);
        $item = pg_fetch_assoc($res);

        if (!$item) {
            echo "failed to update record";
            exit;
        }

        if (!unlink($item['file_name'])) {
            echo "failed to delete file";
            exit;
        }

        pg_query_params($conn, "DELETE FROM gallery_items WHERE id = $1", [$id]);

        header("Location: /admin/gallery", true, 303);
        exit;
    }

    $name = $_POST['name'] ?? '';
    if (strlen($name) < 2 || strlen($name) > 16) {
        echo "failed to parse data: name is out of range of 2;16";
        exit;
    }

    $description = $_POST['description'] ?? '';
    if (strlen($description) < 10 || strlen($description) > 2000) {
        echo "failed to parse data: description is out of range of 10;2000";
        exit;
    }

    $approved = isset($_POST['approved']) && $_POST['approved'] === 'on';

    $res = pg_query_params($conn, "SELECT id FROM gallery_items WHERE id = $1", [$id]);
    if (!pg_fetch_assoc($res)) {
        echo "failed to update record";
        exit;
    }

    pg_query_params(
        $conn,
        "UPDATE gallery_items SET user_name = $1, description = $2, approved = $3 WHERE id = $4",
        [$name, $description, $approved ? 't' : 'f', $id]
    );

    header("Location: /admin/gallery", true, 303);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../../components/header.php'; ?>
</head>
<body>
    <img src="/spawn.webp" alt="Screenshot of a spawn build. There's a staircase on the front resembling a trans flag and a road to the left resembling a trans flag. There's 2 hearts, a trans one and a lesbian one on top. There's 7 beacons forming sort of a T themed with the lesbian and trans flags. The build has walls mostly out of glass, so it's see through. The inside is mostly empty." id="background">
    <br>
    <br>
    <?php include '../../components/meta.php'; ?>
    <main>
        <h1>Admin gallery</h1>

        <?php 
        $res = pg_query($conn, "SELECT id, file_name, user_name, description, approved FROM gallery_items ORDER BY created_at DESC");

        while ($row = pg_fetch_assoc($res)): ?>
        <div style="border: 1px solid white; margin: 4px; padding: 4px;">
            <form action="/admin/gallery" method="post">
                <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                <img src="<?= htmlspecialchars($row['url']) ?>" alt="<?= htmlspecialchars($row['description']) ?>">
                <p>Posted by <input type="text" value="<?= htmlspecialchars($row['user_name']) ?>" name="name" id="name"></p>
                <p>Description:</p>
                <textarea name="description" id="description"><?= htmlspecialchars($row['description']) ?></textarea>
                <br>
                <input type="checkbox" name="approved" id="approved" <?= $row['approved'] === 't' ? 'checked' : '' ?>>
                <label for="approved">Is approved</label>
                <input type="checkbox" name="delete" id="delete">
                <label for="delete">Delete</label>
                <br>
                <button type="submit">Save</button>
            </form>
        </div>
        <?php endwhile; ?>
    </main>
</body>
</html>