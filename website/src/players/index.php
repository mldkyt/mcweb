<?php
function parseTime($seconds) {
    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);
    $seconds %= 60;

    $out = [];
    if ($days > 0) $out[] = $days . 'd';
    if ($hours > 0 || $out) $out[] = $hours . 'h';
    if ($minutes > 0 || $out) $out[] = $minutes . 'm';
    $out[] = $seconds . 's';

    return implode(' ', $out);
}

$host = getenv('DB_POSTGRES_HOST');
$user = getenv('DB_POSTGRES_USER');
$pass = getenv('DB_POSTGRES_PASSWORD');
$name = getenv('DB_POSTGRES_NAME');
$port = getenv('DB_POSTGRES_PORT') ?: '5432';

$conn = pg_connect("host=$host port=$port dbname=$name user=$user password=$pass");

$res = pg_query($conn, "SELECT name, playtime FROM players ORDER BY playtime DESC");

$players = [];
$onlinePlayers = [];

while ($row = pg_fetch_assoc($res)) {
    $name = $row['name'];
    $base = (int)$row['playtime'];

    $players[] = [
        'Name' => $name,
        'Playtime' => parseTime($base)
    ];

    $res2 = pg_query_params(
        $conn,
        "SELECT player_start_time FROM player_time_tracks WHERE player = $1 LIMIT 1",
        [$name]
    );

    if ($track = pg_fetch_assoc($res2)) {
        $extra = time() - strtotime($track['player_start_time']);
        $onlinePlayers[] = [
            'Name' => $name,
            'Playtime' => parseTime($base + $extra)
        ];
    }
}

$isOnlinePlayer = count($onlinePlayers) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../../components/meta.php'; ?>
</head>
<body>
    <img src="/spawn.webp" alt="" id="background">
    <br><br>
    <?php include '../../components/header.php'; ?>

    <main>
        <h1>Server Players</h1>

        <p>This is a list of all players on the server, categorized as online and offline.</p>

        <h2>Online players</h2>

        <?php if ($isOnlinePlayer): ?>
        <table>
            <thead>
                <tr>
                    <th>Player IGN</th>
                    <th>Playtime</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($onlinePlayers as $p): ?>
                <tr>
                    <td class="playing"><?= htmlspecialchars($p['Name']); ?></td>
                    <td><?= htmlspecialchars($p['Playtime']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>The server is lonely right now...</p>
        <?php endif; ?>

        <h2>All players</h2>

        <table>
            <thead>
                <tr>
                    <th>Player IGN</th>
                    <th>Playtime</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($players as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['Name']); ?></td>
                    <td><?= htmlspecialchars($p['Playtime']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>