<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=str_replace(["@",'bsky.app'],["",'bsky.social'],$_POST['handle']);
    $BSKY_PWTEST=$_POST['apppassword'];
    $packURL=$_POST['packurl'];
    $listType=$_POST['listtype'];
    $listURL=$_POST['listurl'];
    
    function bsky_StarterPack($bsky, $spDID,$listType,$listURI) {
        //build the post
    
        $args = [
            'starterPack' => $spDID
        ];

        ($listType=='mod')?$listType='app.bsky.graph.defs#modlist':$listType='app.bsky.graph.defs#curatelist';

        // Can I access the Starter Pack?
            if($data = $bsky->request('GET', 'app.bsky.graph.getStarterPack', $args)){
                //get the parameters from the pack
                $packListUri=$data->starterPack->list->uri;
                $packListName=$data->starterPack->list->name;

                if ($listURI==''){
                //create a new list for the user with the pack details
                $args=[  'collection' => 'app.bsky.graph.list',
                        'repo' => $bsky->getAccountDid(),
                        'record' => [
                            'createdAt' => date('c'),
                            '$type' => 'app.bsky.graph.list',
                            'purpose' => $listType,
                            'name'=>$packListName .'-Pack' ,
                            'description'=> "Imported from the Starter Pack at ". $_POST['packurl'] . ", powered by https://nws-bot.us/bskyStarterPack.php and @wandrme.paxex.aero.",
                        ],
                    ];
                $data2 = $bsky->request('POST', 'com.atproto.repo.createRecord', $args);
                $newListURI=$data2->uri;
                }
                else{
                    $newListURI=$listURI;
                }
                
                //read the old list for accounts to add to the list
                $cursor='';
                $spList=[];
                do {
                    $args=['list'=>$packListUri,'limit'=>100,'cursor'=>$cursor];
                    $res=$bsky->request('GET','app.bsky.graph.getList',$args);
                    $spList=array_merge($spList,(array)$res->items);
                    $cursor=$res->cursor;
                }
                while ($cursor);

                if($spList){
                    foreach($spList as $listItem){
                        //Add the user to the list
                        $args=[  'collection' => 'app.bsky.graph.listitem',
                        'repo' => $bsky->getAccountDid(),
                        'record' => [
                            'createdAt' => date('c'),
                            '$type' => 'app.bsky.graph.listitem',
                            'subject'=> $listItem->subject->did,
                            'list'=> $newListURI,
                            ],
                        ];
                        $bsky->request('POST', 'com.atproto.repo.createRecord', $args);
                    }
                }

            } else {
                echo "Couldn't find that starter pack. Please check the URL and try again.";
            }
    }


    function bskySPs($bsky, $userHandle,$packID) {
        //build the post
    
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
                        return  $packURI;
                    }
                }
            } else {
                return '';
            }
    }


    function bskyListATs($bsky, $userHandle,$listID) {
        //get the pack at: URI from the base URL
    
        $cursor='';
        $arrLists=[];
        do {
            $args=['actor'=>$userHandle,'limit'=>100,'cursor'=>$cursor];
            $res=$bsky->request('GET','app.bsky.graph.getLists',$args);
            $arrLists=array_merge($arrLists,(array)$res->lists);
            $cursor=$res->cursor;
        }
        while ($cursor);

        foreach ($arrLists as $item){
            $listURI=$item->uri;
            $arrlistCheck=explode('/',$listURI);
            $listCode=$arrlistCheck[count($arrlistCheck)-1];
            if ($listCode==$listID){
                return  $listURI;
            }
        }

        //last resort
        return '';
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
        
        if($bluesky->hasApiKey()){
            //handle short URLs for the pack
            $packURL = str_replace('bsky.app/starter-pack-short','go.bsky.app',$packURL);
            if (strpos($packURL,"go.bsky.app")>0  ){
                $packURL=curlGetFullUrl($packURL);
            }

            $arrPack=explode('/',$packURL);
            $userHandle=$arrPack[count($arrPack)-2];
            $packID=$arrPack[count($arrPack)-1];

            //If there's a list URL see if I can get a URI for it.
            if (strlen($listURL)>2){
                $arrList=explode('/',$listURL);
                $listUserHandle=$arrList[count($arrList)-3];
                $listID=$arrList[count($arrList)-1];
                $listAT=bskyListATs($bluesky,$listUserHandle,$listID);
            }
            else {
                $listAT='';
            }

            $packAT=bskySPs($bluesky,$userHandle,$packID);
            if ($packAT!=''){
                //Came back with an at: URI, so I can now fetch the Starter Pack and parse for the list details inside
                $results=bsky_StarterPack($bluesky,$packAT,$listType,$listAT);
            }
            else{
                echo "Could not find that Starter Pack. Please check the URL and try again.";
            }

            $bluesky=null;
            ($listType=="mod")?$msg='<p>Import Complete</p><p>Check your <a href="https://bsky.app/moderation/modlists" target="_blank">Mod Lists</a> for more details.':$msg='<p>Import Complete</p><p>Check your <a href="https://bsky.app/lists" target="_blank">User Lists</a> for more details.';
            echo $msg;
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
    <title>Convert BSky Starter Pack to List</title>
</head>
<body>
    <h1>Convert a BSky Starter Pack to a List</h1>
    <?php
require "./app-pw.php";
?><form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Starter Pack URL to convert: <input type="text" name="packurl" placeholder="https://bsky.app/starter-pack/wandrme.paxex.aero/3l6stg6xfrc23" required></p>
        <p>What type of list? <input type="radio" name="listtype" value="content" checked id="content" required><label for="content">Content</label> | <input type="radio" id="mod" name="listtype" value="mod" required><label for="mod">Moderation</label></p>
        <p>Merge into existing list? <input type="text" name="listurl" placeholder="https://bsky.app/starter-pack/wandrme.paxex.aero/3l6stg6xfrc23" > (Leave blank by default; put in a list URL if you own one that the SP should merge into.)</p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
include "./footer.php";
?>
