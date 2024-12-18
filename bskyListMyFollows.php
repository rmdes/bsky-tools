<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
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
            try {
                $args = ['actor' => $bluesky->getAccountDid()];
                $tUsr = $bluesky->request('GET', 'app.bsky.actor.getProfile', $args);
                $tDID = $tUsr->did;
                $cursor = '';
                $arrFoll = [];
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollows', $args);
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
                }
                $statusMessage = '<div style="color:green;">List successfully created! Please refresh the page to submit another request.</div>';
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
    <script>
        function disableButton() {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.value = 'Processing...';
        }
    </script>
</head>
<body>
    <h1>Bluesky Tools - Create List from Follows</h1>
    <form action="" method="POST" onsubmit="disableButton()">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <input type="submit" id="submit-btn" name="submit" value="Submit">
    </form>
    <div style="margin-top: 10px; font-weight: bold;">
        <?php echo $statusMessage; ?>
    </div>
</body>
</html>
