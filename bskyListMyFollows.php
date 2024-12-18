<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=str_replace(["@",'bsky.app'],["",'bsky.social'],$_POST['handle']);;
    $BSKY_PWTEST=$_POST['apppassword'];
    ?>

    <?php
    require "./bsky-core.php";
    ?>
    
    <?php
    //Run this crap
    //init the connection
    $pdsURL=getPDS($BSKY_HANDLETEST);
    if ($pdsURL==''){
        echo 'Invalid Username Entered. Make sure it is the full name, including the domain suffix (e.g. user.bsky.social, not just user).';
    }
    else {
        $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST,$pdsURL);

        if($bluesky->hasApiKey()){

            //get did for the  user from which to copy
        
            $args = [
                'actor' => $bluesky->getAccountDid()
            ];

            if ($tUsr=$bluesky->request('GET','app.bsky.actor.getProfile',$args)){

                
                //get all the follows for that user
                $tDID=$tUsr->did;
                //get follows of the user
                $cursor='';
                $arrFoll=[];
                do {
                    $args=['actor'=>$tDID,'limit'=>100,'cursor'=>$cursor];
                    $res=$bluesky->request('GET','app.bsky.graph.getFollows',$args);
                    $arrFoll=array_merge($arrFoll,(array)$res->follows);
                    $cursor=$res->cursor;
                }
                while ($cursor);
            

                //create a new list for the user 
                $args=[  'collection' => 'app.bsky.graph.list',
                'repo' => $bluesky->getAccountDid(),
                'record' => [
                    'createdAt' => date('c'),
                    '$type' => 'app.bsky.graph.list',
                    'purpose' => 'app.bsky.graph.defs#curatelist',
                    'name'=>'My Follows ' . date('Y-m-d') ,
                    'description'=> "List created from my follows, powered by https://nws-bot.us/bskyListMyFollows.php and @wandrme.paxex.aero.",
                    ],
                ];
                if($data2 = $bluesky->request('POST', 'com.atproto.repo.createRecord', $args)){
                    $newListURI=$data2->uri;
                    //Now Loop the collection of follows into the new list
                    foreach($arrFoll as $acct){
                        //add to the list
                        $args=[
                            'collection' => 'app.bsky.graph.listitem',
                            'repo' => $bluesky->getAccountDid(),
                            'record' => [
                                'subject'=>$acct->did,
                                'createdAt' => date('c'),
                                '$type' => 'app.bsky.graph.listitem',
                                'list'=> $newListURI
                            ],
                        ];
                        $res=$bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
                    }
                }
            
            }

            $bluesky=null;
            echo "Import Complete";
        }
        else{
            echo "Error connecting to your account. Please check the username and app password and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create a list from all the people you follow</title>
</head>
<body>
    <h1>Create a list from all the people you follow</h1>
    <?php
require "./app-pw.php";
?><form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
include "./footer.php";
?>
