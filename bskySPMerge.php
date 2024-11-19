<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=str_replace(["@",'bsky.app'],["",'bsky.social'],$_POST['handle']);
    $BSKY_PWTEST=$_POST['apppassword'];
    $packURL=$_POST['packurl'];
    $listURL=$_POST['packsrcurl'];

    function bsky_SP2StarterPack($bsky, $spAT,$listAT) {

        //read the source list for accounts to add to the pack list     

        $cursor='';
        $sourceList=[];
        do {
            $args=['list'=>$listAT,'limit'=>100,'cursor'=>$cursor];
            $res=$bsky->request('GET','app.bsky.graph.getList',$args);
            $sourceList=array_merge($sourceList,(array)$res->items);
            $cursor=$res->cursor;

        }
        while ($cursor);

        if($sourceList){
            foreach($sourceList as $listItem){
                //Add the user to the pack list
                $args=[  'collection' => 'app.bsky.graph.listitem',
                'repo' => $bsky->getAccountDid(),
                'record' => [
                    'createdAt' => date('c'),
                    '$type' => 'app.bsky.graph.listitem',
                    'subject'=> $listItem->subject->did,
                    'list'=> $spAT,
                    ],
                ];
                $bsky->request('POST', 'com.atproto.repo.createRecord', $args);
            }
        }
        else {
        echo "Couldn't find that starter pack. Please check the URL and try again.";
        }
    }


    function bskySPs($bsky, $userHandle,$packID) {
        //get the pack at: URI from the base URL
    
        $args = [
            'actor' => $userHandle
        ];

            if($data = $bsky->request('GET', 'app.bsky.graph.getActorStarterPacks', $args)){
                #iterate the JSON, looking for the pack with the ID that was passed in
                
                foreach ($data->starterPacks as $item){
                    $packURI=$item->uri;
                    $arrPackCheck=explode('/',$packURI);
                    $packCode=$arrPackCheck[count($arrPackCheck)-1];
                    if ($packCode==$packID){
                        //get the list at from the pack
                        $args = [
                            'starterPack' => $packURI
                        ];
                        if($data = $bsky->request('GET', 'app.bsky.graph.getStarterPack', $args)){
                            //get the parameters from the pack
                            $packListUri=$data->starterPack->list->uri;
                            return $packListUri;
                        }
                    }
                }
            } else {
                return false;
            }
            return false;
    }

    function bskyListATs($bsky, $userHandle,$listID) {
        //get the pack at: URI from the base URL
    
        $args = [
            'actor' => $userHandle, 'limit'=>100
        ];

            if($data = $bsky->request('GET', 'app.bsky.graph.getLists', $args)){
                #iterate the JSON, looking for the pack with the ID that was passed in
                
                foreach ($data->lists as $item){
                    $listURI=$item->uri;
                    $arrlistCheck=explode('/',$listURI);
                    $listCode=$arrlistCheck[count($arrlistCheck)-1];
                    if ($listCode==$listID){
                        return  $listURI;
                    }
                }
            } else {
                return '';
            }
    }



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

        $packURL=deParam($packURL);
        $listURL=deParam($listURL);
        if($bluesky->hasApiKey()){

            //handle short URLs for the pack
            $packURL = str_replace('bsky.app/starter-pack-short','go.bsky.app',$packURL);
            if (strpos($packURL,"go.bsky.app")>0  ){
                $packURL=curlGetFullUrl($packURL);
            }

            $listURL = str_replace('bsky.app/starter-pack-short','go.bsky.app',$listURL);
            if (strpos($listURL,"go.bsky.app")>0  ){
                $listURL=curlGetFullUrl($listURL);
            }


            $arrPack=explode('/',$packURL);
            $userHandle=$arrPack[count($arrPack)-2];
            $packID=$arrPack[count($arrPack)-1];

            $arrList=explode('/',$listURL);
            $listUserHandle=$arrList[count($arrList)-2];
            $listID=$arrList[count($arrList)-1];



            $packAT=bskySPs($bluesky,$userHandle,$packID);
            $listAT=bskySPs($bluesky,$listUserHandle,$listID); // bskyListATs($bluesky,$listUserHandle,$listID);
            if ($packAT!='' && $listAT!=''){
                //Came back with an at: URI, so I can now fetch the Starter Pack and parse for the list details inside
                bsky_SP2StarterPack($bluesky,$packAT,$listAT);
            }
            else{
                echo "Could not find that Starter Pack. Please check the URL and try again.";
            }

            $bluesky=null;
            echo "<p>Import Complete. Check the Starter Pack tab on your user profile for more details.</p>";
        }
        else{
            echo "<p>Error connecting to your account. Please check the username and app password and try again.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Merge Starter Packs together</title>
</head>
<body>
    <h1>Merge Starter Packs together</h1>
    <?php
require "./app-pw.php";
?><form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Starter Pack to grow URL (i.e. target): <input type="text" name="packurl" placeholder="https://bsky.app/starter-pack/wandrme.paxex.aero/3l6stg6xfrc23" required></p>
        <p>Starter pack that is to be included URL (i.e. source): <input type="text" name="packsrcurl" placeholder="https://bsky.app/starter-pack/wandrme.paxex.aero/3l6stg6xfrc23" required></p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <p>*Note: You can only have 150 accounts in a SP.</p>
    <?php
include "./footer.php";
?>