<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
$apiLog = []; // Array to log API requests and responses
$statusMessage = "";

if (isset($_POST['submit'])) {
    $BSKY_HANDLETEST = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['handle']);
    $BSKY_PWTEST = $_POST['apppassword'];
    require "./bsky-core.php";

    $pdsURL = getPDS($BSKY_HANDLETEST);
    if ($pdsURL == '') {
        $statusMessage = '<div style="color:red;">Invalid Username. Ensure the full name includes the domain suffix.</div>';
    } else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST, $pdsURL);
        if ($bluesky->hasApiKey()) {
            $args = ['actor' => $bluesky->getAccountDid()];
            $apiLog[] = ["Request" => "GET app.bsky.actor.getProfile", "Args" => $args];

            try {
                $tUsr = $bluesky->request('GET', 'app.bsky.actor.getProfile', $args);
                $tDID = $tUsr->did;
                $cursor = '';
                $arrFoll = [];
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollows', $args);
                    $apiLog[] = ["Request" => "GET app.bsky.graph.getFollows", "Args" => $args, "Response" => $res];
                    $arrFoll = array_merge($arrFoll, (array)$res->follows);
                    $cursor = $res->cursor;
                } while ($cursor);

                // Create list
                $args = [
                    'collection' => 'app.bsky.graph.list',
                    'repo' => $bluesky->getAccountDid(),
                    'record' => [
                        'createdAt' => date('c'),
                        '$type' => 'app.bsky.graph.list',
                        'purpose' => 'app.bsky.graph.defs#curatelist',
                        'name' => 'My Follows ' . date('Y-m-d'),
                        'description' => "List created from my follows using Bluesky Tools",
                    ],
                ];
                $data2 = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                $apiLog[] = ["Request" => "POST com.atproto.repo.createRecord", "Args" => $args, "Response" => $data2];
                $newListURI = $data2->uri;

                foreach ($arrFoll as $acct) {
                    $args = [
                        'collection' => 'app.bsky.graph.listitem',
                        'repo' => $bluesky->getAccountDid(),
                        'record' => [
                            'subject' => $acct->did,
                            'createdAt' => date('c'),
                            '$type' => 'app.bsky.graph.listitem',
                            'list' => $newListURI,
                        ],
                    ];
                    $bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                    $apiLog[] = ["Request" => "POST com.atproto.repo.createRecord", "Args" => $args];
                }
                $statusMessage = '<div style="color:green;">Import Complete!</div>';
            } catch (Exception $e) {
                $statusMessage = '<div style="color:red;">Error: ' . $e->getMessage() . '</div>';
            }
        } else {
            $statusMessage = '<div style="color:red;">Error connecting to your account. Please check credentials.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bluesky Tools - My Follows</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .menu { margin-bottom: 20px; }
        .menu a { margin-right: 15px; text-decoration: none; color: blue; }
        .api-log { background: #f8f9fa; padding: 10px; border: 1px solid #ddd; margin-top: 20px; }
    </style>
    <script>
        function disableButton() {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            document.getElementById('status').innerHTML = 'Processing... Please wait.';
        }
    </script>
</head>
<body>
    <h1>Bluesky Tools - Create List from Follows</h1>
    <div class="menu">
        <strong>Other Tools:</strong>
        <a href="bskyFollowBoost.php">Follow Boost</a>
        <a href="bskyFollowMyList.php">Follow My List</a>
        <a href="bskyJoinDate.php">Join Date Checker</a>
        <a href="bskyList2List.php">List to List</a>
        <a href="bskyList2ModList.php">List to Mod List</a>
        <a href="bskyList2StarterPack.php">List to Starter Pack</a>
        <a href="bskyListCombiner.php">List Combiner</a>
        <a href="bskyListMyFollows.php">List My Follows</a>
        <a href="bskySPMerge.php">SP Merge</a>
        <a href="bskyStarterPack.php">Starter Pack</a>
    </div>

    <form action="" method="POST" onsubmit="disableButton()">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <input type="submit" id="submit-btn" name="submit" value="Submit">
    </form>
    <div id="status" style="font-weight: bold; margin-top: 10px;">
        <?php echo $statusMessage; ?>
    </div>

    <?php if (!empty($apiLog)): ?>
        <div class="api-log">
            <h2>API Requests Log:</h2>
            <?php foreach ($apiLog as $log): ?>
                <pre><?php echo htmlspecialchars(json_encode($log, JSON_PRETTY_PRINT)); ?></pre>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>
