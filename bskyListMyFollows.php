<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
$apiLog = []; // Array to log API requests and responses

if (isset($_POST['submit'])) {

    $BSKY_HANDLETEST = str_replace(["@", 'bsky.app'], ["", 'bsky.social'], $_POST['handle']);
    $BSKY_PWTEST = $_POST['apppassword'];

    require "./bsky-core.php";

    // Initialize connection
    $pdsURL = getPDS($BSKY_HANDLETEST);
    if ($pdsURL == '') {
        echo '<div style="color:red;">Invalid Username Entered. Make sure it is the full name, including the domain suffix (e.g., user.bsky.social).</div>';
    } else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST, $pdsURL);

        if ($bluesky->hasApiKey()) {

            $args = ['actor' => $bluesky->getAccountDid()];
            $apiLog[] = ["Request" => "GET app.bsky.actor.getProfile", "Args" => $args];

            if ($tUsr = $bluesky->request('GET', 'app.bsky.actor.getProfile', $args)) {

                $tDID = $tUsr->did;
                $cursor = '';
                $arrFoll = [];
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollows', $args);
                    $apiLog[] = ["Request" => "GET app.bsky.graph.getFollows", "Args" => $args];
                    $arrFoll = array_merge($arrFoll, (array)$res->follows);
                    $cursor = $res->cursor;
                } while ($cursor);

                $args = [
                    'collection' => 'app.bsky.graph.list',
                    'repo' => $bluesky->getAccountDid(),
                    'record' => [
                        'createdAt' => date('c'),
                        '$type' => 'app.bsky.graph.list',
                        'purpose' => 'app.bsky.graph.defs#curatelist',
                        'name' => 'My Follows ' . date('Y-m-d'),
                        'description' => "List created from my follows, powered by Bluesky List Creator.",
                    ],
                ];
                $apiLog[] = ["Request" => "POST com.atproto.repo.createRecord", "Args" => $args];

                if ($data2 = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args)) {
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
                }
                echo "<div style='color:green;'>Import Complete!</div>";
            }
            $bluesky = null;
        } else {
            echo '<div style="color:red;">Error connecting to your account. Please check the username and app password and try again.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create a list from all the people you follow</title>
    <script>
        function disableButton() {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            document.getElementById('status').innerHTML = 'Processing... Please wait.';
        }
    </script>
</head>
<body>
    <h1>Create a list from all the people you follow</h1>
    <form action="" method="POST" onsubmit="disableButton()">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <input type="submit" id="submit-btn" name="submit" value="Submit">
    </form>
    <div id="status" style="font-weight: bold;"></div>
    <hr>

    <?php if (!empty($apiLog)): ?>
        <h2>API Requests Log:</h2>
        <div style="background-color: #f8f9fa; padding: 10px; border: 1px solid #ddd;">
            <?php foreach ($apiLog as $log): ?>
                <pre><?php echo htmlspecialchars(json_encode($log, JSON_PRETTY_PRINT)); ?></pre>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>
