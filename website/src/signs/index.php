
<?php

$host = getenv('DB_POSTGRES_HOST');
$user = getenv('DB_POSTGRES_USER');
$pass = getenv('DB_POSTGRES_PASSWORD');
$name = getenv('DB_POSTGRES_NAME');
$port = getenv('DB_POSTGRES_PORT') ?: '5432';

$conn = pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

function get_remote_addr() {
  if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
    return  $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
    return $_SERVER['REMOTE_ADDR'];
  } else if (array_key_exists('HTTP_CLIENT_IP', $_SERVER)) {
    return $_SERVER['HTTP_CLIENT_IP'];
  }

  return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST["name"]) || !isset($_POST["text"])) {
        echo "missing post parameters";
        exit;
    }

    $name = $_POST["name"];
    if (strlen($name) <= 2 || strlen($name) >= 16) {
        echo "Name is too short or too long";
        exit;
    }

    $text = $_POST["text"];
    if (strlen($text) < 2 || strlen($text) > 128) {
        echo "text is too short or too long";
        exit;
    }

    $res = pg_query_params($conn, "INSERT INTO signs(ip, user_name, text) VALUES ($1, $2, $3)", [get_remote_addr(), $name, $text]);
    if (!$res) {
        echo "server error when adding sign";
        exit;
    }
    
    header("Location: /signs/", true, 303);
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../../components/meta.php'; ?>

    <style>
        div {
            display: flex;
        }

        span {
            padding: 4px;
            border: 1px solid white;
            margin: 2px;
        }
    </style>
</head>
<body>
    <img src="/spawn.webp" alt="Screenshot of a spawn build. There's a staircase on the front resembling a trans flag and a road to the left resembling a trans flag. There's 2 hearts, a trans one and a lesbian one on top. There's 7 beacons forming sort of a T themed with the lesbian and trans flags. The build has walls mostly out of glass, so it's see through. The inside is mostly empty." id="background">
    <br>
    <br>
    <?php require '../../components/header.php'; ?>

    <main>
        <h1>Sign board</h1>

        <p>You can put up signs here. Think of this like a sign board in a Minecraft world.</p>

        <?php
        $res = pg_query_params($conn, "SELECT COUNT(id) AS is_unapproved FROM signs WHERE approved = false AND ip = $1", [get_remote_addr()]);
        $row = pg_fetch_assoc($res);
        if ($row['is_unapproved'] == 0) : ?>
            <form action="/signs/" method="post">
                <label for="name">Name</label> <br>
                <input type="text" name="name" id="name" placeholder="catgirl" min="2" max="16"> <br>
                <label for="text">Text</label> <br>
                <input type="text" min="2" max="128" id="text" name="text"> <br>
                <button type="submit">Send!</button>
            </form>
            <p>Once sent the form will disappear until your post gets approved</p>
        <?php else : ?>
            <p>Please wait for your sign to get approved by an admin.</p>
        <?php endif; ?>

        <div>
            <?php 
            $res = pg_query($conn, "SELECT user_name, text FROM signs WHERE approved = true ORDER BY created_at DESC");
            while ($row = pg_fetch_assoc($res)) : ?>
            <span><strong><?= htmlspecialchars($row['user_name']); ?></strong> <br> <?= htmlspecialchars($row['text']); ?></span>
            <?php endwhile; ?>
        </div>
    </main>
</body>
</html>
