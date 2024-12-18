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
        echo '<div style="color:red;">Invalid Username. Ensure the full name includes the domain suffix.</div>';
    } else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST, $pdsURL);
        if ($bluesky->hasApiKey()) {
            $args = ['actor' => $bluesky->getAccountDid()];
            if ($tUsr = $bluesky->request('GET', 'app.bsky.actor.getProfile', $args)) {
                $tDID = $tUsr->did;
                $cursor = '';
                $arrFoll = [];
                do {
                    $args = ['actor' => $tDID, 'limit' => 100, 'cursor' => $cursor];
                    $res = $bluesky->request('GET', 'app.bsky.graph.getFollows', $args);
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
                        'description' => "List created from my follows, powered by Bluesky Tools",
                    ],
                ];
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
                    }
                }
                echo '<div style="color:green;">Import Complete!</div>';
            }
            $bluesky = null;
        } else {
            echo '<div style="color:red;">Error connecting to your account. Please check credentials.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create a list from all the people you follow</title>
    <script>
        function disableButton() {
            document.getElementById('submit-btn').disabled = true;
        }
    </script>
</head>
<body>
    <h1>Create a list from all the people you follow</h1>
    <nav>
        <strong>Other Tools:</strong>
        <a href="bskyFollowBoost.php">Follow Boost</a> |
        <a href="bskyFollowMyList.php">Follow My List</a> |
        <a href="bskyJoinDate.php">Join Date Checker</a> |
        <a href="bskyList2List.php">List to List</a> |
        <a href="bskyList2ModList.php">List to Mod List</a> |
        <a href="bskyList2StarterPack.php">List to Starter Pack</a> |
        <a href="bskyListCombiner.php">List Combiner</a> |
        <a href="bskyListMyFollows.php">List My Follows</a> |
        <a href="bskySPMerge.php">SP Merge</a> |
        <a href="bskyStarterPack.php">Starter Pack</a>
    </nav>
    <form action="" method="POST" onsubmit="disableButton()">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <input type="submit" id="submit-btn" name="submit" value="Submit">
    </form>
</body>
</html>
