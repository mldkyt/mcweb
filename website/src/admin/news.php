<?php
$adminUser = getenv("ADMIN_USER");
$adminPass = getenv("ADMIN_PASS");

if (!isset($_SERVER["PHP_AUTH_USER"]) || $_SERVER["PHP_AUTH_USER"] !== $adminUser || $_SERVER["PHP_AUTH_PW"] !== $adminPass) {
    header('WWW-Authenticate: Basic realm="restricted", charset="UTF-8"');
    http_response_code(401);
    echo "Unpublisherized";
    exit();
}

$host = getenv("DB_POSTGRES_HOST");
$user = getenv("DB_POSTGRES_USER");
$pass = getenv("DB_POSTGRES_PASSWORD");
$name = getenv("DB_POSTGRES_NAME");
$port = getenv("DB_POSTGRES_PORT") ?: "5432";

$conn = pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["id"])) {
        $id = $_POST["id"];
        if (!ctype_digit($id)) {
            echo "failed to parse data: ID is not a number";
            exit();
        }
        $id = (int) $id;

        if (isset($_POST["delete"]) && $_POST["delete"] === "on") {
            $res = pg_query_params($conn, "SELECT file_name FROM news WHERE id = $1", [$id]);
            $item = pg_fetch_assoc($res);

            if (!$item) {
                echo "failed to delete record";
                exit();
            }

            pg_query_params($conn, "DELETE FROM news WHERE id = $1", [$id]);

            header("Location: /admin/news.php", true, 303);
            exit();
        }

        $title = $_POST["title"] ?? "";
        if (strlen($title) < 2 || strlen($title) > 40) {
            echo "failed to parse data: title is out of range of 2;16";
            exit();
        }

        $body = $_POST["body"] ?? "";
        if (strlen($body) < 10 || strlen($body) > 2000) {
            echo "failed to parse data: body is out of range of 10;2000";
            exit();
        }

        $publisher = $_POST["publisher"] ?? "";
        if (strlen($publisher) < 2 || strlen($publisher) > 40) {
            echo "failed to parse data: publisher is out of range of 2;16";
            exit();
        }

        $res = pg_query_params($conn, "SELECT id FROM news WHERE id = $1", [$id]);
        if (!pg_fetch_assoc($res)) {
            echo "failed to update record";
            exit();
        }

        pg_query_params($conn, "UPDATE news SET title = $1, body = $2, publisher = $3 WHERE id = $4", [$title, $body, $publisher, $id]);

        header("Location: /admin/news.php", true, 303);
        exit();
    } else {
        $title = $_POST["title"] ?? "";
        if (strlen($title) < 2 || strlen($title) > 40) {
            echo "failed to parse data: title is out of range of 2;16";
            exit();
        }

        $body = $_POST["body"] ?? "";
        if (strlen($body) < 10 || strlen($body) > 2000) {
            echo "failed to parse data: body is out of range of 10;2000";
            exit();
        }

        $publisher = $_POST["publisher"] ?? "";
        if (strlen($publisher) < 2 || strlen($publisher) > 40) {
            echo "failed to parse data: publisher is out of range of 2;16";
            exit();
        }

        pg_query_params($conn, "INSERT INTO news(title, body, publisher) VALUES($1, $2, $3);", [$title, $body, $publisher]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../components/header.php"; ?>
</head>
<body>
    <img src="/spawn.webp" alt="Screenshot of a spawn build. There's a staircase on the front resembling a trans flag and a road to the left resembling a trans flag. There's 2 hearts, a trans one and a lesbian one on top. There's 7 beacons forming sort of a T themed with the lesbian and trans flags. The build has walls mostly out of glass, so it's see through. The inside is mostly empty." id="background">
    <br>
    <br>
    <?php include "../../components/meta.php"; ?>
    <main>
        <h1>Admin news</h1>

        <h2>New shitpost</h2>

        <form action="/admin/news.php" method="post">
            <label for="title<?= $res["id"] ?>">Title of the post</label>
            <input type="text" name="title">
            <br />
            <label for="body<?= $res["id"] ?>">Body of the post</label>
            <br />
            <textarea name="body" id="body<?= $res["id"] ?>"></textarea>
            <br>
            <label for="publisher<?= $res["id"] ?>">publisher</label>
            <input type="text" name="publisher">
            <br>
            <button type="submit">Save</button>
        </form>

        <h2>Existing shitposts</h2>

        <?php
        $res = pg_query($conn, "SELECT id, title, body, publisher FROM news ORDER BY created_at DESC");

        while ($row = pg_fetch_assoc($res)): ?>
        <div style="border: 1px solid white; margin: 4px; padding: 4px;">
            <form action="/admin/news.php" method="post">
                <input type="hidden" name="id" value="<?= htmlspecialchars($row["id"]) ?>">
                <label for="title<?= $row["id"] ?>">Title of the post</label>
                <input type="text" name="title" value="<?= htmlspecialchars($row["title"]) ?>">
                <br />
                <label for="body<?= $row["id"] ?>">Body of the post</label>
                <br />
                <textarea name="body" id="body<?= $row["id"] ?>"><?= htmlspecialchars($row["body"]) ?></textarea>
                <br>
                <label for="publisher<?= $row["id"] ?>">publisher</label>
                <input type="text" name="publisher" value="<?= htmlspecialchars($row["publisher"]) ?>">
                <br />
                <input type="checkbox" name="delete" id="delete">
                <label for="delete">Delete</label>
                <br />
                <button type="submit">Save</button>
            </form>
        </div>
        <?php endwhile;
        ?>
    </main>
</body>
</html>
