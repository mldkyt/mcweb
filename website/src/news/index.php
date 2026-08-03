<?php

$host = getenv("DB_POSTGRES_HOST");
$user = getenv("DB_POSTGRES_USER");
$pass = getenv("DB_POSTGRES_PASSWORD");
$name = getenv("DB_POSTGRES_NAME");
$port = getenv("DB_POSTGRES_PORT") ?: "5432";

$conn = pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "../../components/meta.php"; ?>
</head>
<body>
    <img src="/spawn.webp" alt="Screenshot of a spawn build. There's a staircase on the front resembling a trans flag and a road to the left resembling a trans flag. There's 2 hearts, a trans one and a lesbian one on top. There's 7 beacons forming sort of a T themed with the lesbian and trans flags. The build has walls mostly out of glass, so it's see through. The inside is mostly empty." id="background">
    <br>
    <br>
    <?php include "../../components/header.php"; ?>
    <main>
        <h1>Server News</h1>

        <?php
        $res = pg_query($conn, "SELECT id, title, body, publisher FROM news ORDER BY created_at DESC");

        while ($row = pg_fetch_assoc($res)): ?>
        <div style="border: 1px solid white; margin: 4px; padding: 4px;">
            <h1><?= htmlspecialchars($row["title"]) ?></h1>

            <?php
            function apply_bold($str)
            {
                $split = explode("**", $str);
                if (sizeof($split) == 1) {
                    return $str;
                }
                $output = "";
                for ($i = 0; $i < sizeof($split); $i++) {
                    if ($i == sizeof($split) - 1) {
                        $output .= $split[$i];
                        continue;
                    }

                    if ($i % 2 == 0) {
                        $output .= $split[$i] . "<strong>";
                    } else {
                        $output .= $split[$i] . "</strong>";
                    }
                }
                return $output;
            }

            function apply_italic($str)
            {
                $split = explode("*", $str);
                if (sizeof($split) == 1) {
                    return $str;
                }
                $output = "";
                for ($i = 0; $i < sizeof($split); $i++) {
                    if ($i == sizeof($split) - 1) {
                        $output .= $split[$i];
                        continue;
                    }

                    if ($i % 2 == 0) {
                        $output .= $split[$i] . "<i>";
                    } else {
                        $output .= $split[$i] . "</i>";
                    }
                }
                return $output;
            }

            $bodyLines = explode("\n", $row["body"]);

            foreach ($bodyLines as $line) {
                if (str_starts_with($line, "# ")) {
                    echo "<h2>" . htmlspecialchars(substr($line, 2)) . "</h2>";
                } elseif (str_starts_with($line, "## ")) {
                    echo "<h3>" . htmlspecialchars(substr($line, 2)) . "</h3>";
                } elseif (str_starts_with($line, "### ")) {
                    echo "<h4>" . htmlspecialchars(substr($line, 2)) . "</h4>";
                } else {
                    echo "<p>" . apply_italic(apply_bold(htmlspecialchars($line))) . "</p>";
                }
            }
            ?>

            <p><small>By <?= htmlspecialchars($row["publisher"]) ?>, published by <?= htmlspecialchars(date("Y-m-d H:i:s", $row["created_at"])) ?></small></p>
        </div>
        <?php endwhile;
        ?>
    </main>
</body>
</html>
