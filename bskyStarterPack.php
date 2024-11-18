<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=str_replace(["@",'bsky.app'],["",'bsky.social'],$_POST['handle']);
    $BSKY_PWTEST=$_POST['apppassword'];
    $packURL=$_POST['packurl'];
    $listType=$_POST['listtype'];
    $listURL=$_POST['listurl'];
    


    class BlueskyApi
    {
        private ?string $accountDid = null;
        private ?string $apiKey = null;
        private string $apiUri;

        public function __construct(?string $handle = null, ?string $app_password = null, string $api_uri = 'https://bsky.social/xrpc/')
        {
            $this->apiUri = $api_uri;

            if (($handle) && ($app_password)) {
                // GET DID AND API KEY FROM HANDLE AND APP PASSWORD
                $args = [
                    'identifier' => $handle,
                    'password' => $app_password,
                ];
                $data = $this->request('POST', 'com.atproto.server.createSession', $args);

                $this->accountDid = $data->did;
                $this->apiKey = $data->accessJwt;
            }
        }

        /**
         * Get the current account DID
         *
         * @return string
         */
        public function getAccountDid(): ?string
        {
            return $this->accountDid;
        }

        /**
         * Set the account DID for future requests
         *
         * @param string|null $account_did
         * @return void
         */
        public function setAccountDid(?string $account_did): void
        {
            $this->accountDid = $account_did;
        }

        /**
         * Set the API key for future requests
         *
         * @param string|null $api_key
         * @return void
         */
        public function setApiKey(?string $api_key): void
        {
            $this->apiKey = $api_key;
        }

        /**
         * Return whether an API key has been set
         *
         * @return bool
         */
        public function hasApiKey(): bool
        {
            return $this->apiKey !== null;
        }

        /**
         * Make a request to the Bluesky API
         *
         * @param string $type
         * @param string $request
         * @param array $args
         * @param string|null $body
         * @param string|null $content_type
         * @return mixed|object
         * @throws \JsonException
         */
        public function request(string $type, string $request, array $args = [], ?string $body = null, string $content_type = null)
        {
            $url = $this->apiUri . $request;

            if (($type === 'GET') && (count($args))) {
                $url .= '?' . http_build_query($args);
            } elseif (($type === 'POST') && (!$content_type)) {
                $content_type = 'application/json';
            }

            $headers = [];
            if ($this->apiKey) {
                $headers[] = 'Authorization: Bearer ' . $this->apiKey;
            }

            if ($content_type) {
                $headers[] = 'Content-Type: ' . $content_type;

                if (($content_type === 'application/json') && (count($args))) {
                    $body = json_encode($args, JSON_THROW_ON_ERROR);
                    $args = [];
                }
            }

            $c = curl_init();
            curl_setopt($c, CURLOPT_URL, $url);

            if (count($headers)) {
                curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
            }

            switch ($type) {
                case 'POST':
                    curl_setopt($c, CURLOPT_POST, 1);
                    break;
                case 'GET':
                    curl_setopt($c, CURLOPT_HTTPGET, 1);
                    break;
                default:
                    curl_setopt($c, CURLOPT_CUSTOMREQUEST, $type);
            }

            if ($body) {
                curl_setopt($c, CURLOPT_POSTFIELDS, $body);
            } elseif (($type !== 'GET') && (count($args))) {
                curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($args, JSON_THROW_ON_ERROR));
            }

            curl_setopt($c, CURLOPT_HEADER, 0);
            curl_setopt($c, CURLOPT_VERBOSE, 0);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);

            $data = curl_exec($c);
            curl_close($c);

            return json_decode($data, false, 512, JSON_THROW_ON_ERROR);
        }
    }


    function curlGetFullUrl(string $url)
    {
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_HTTPGET, 1);
        curl_setopt($c, CURLOPT_HEADER, 0);
        curl_setopt($c, CURLOPT_VERBOSE, 0);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);

        $data = curl_exec($c);
        curl_close($c);

        if(strpos($data,"og:url")){
            $data = substr($data,strpos($data,"og:url")+17);
            $data = substr($data,0,strpos($data,'"'));
            return $data;
        }
        else
        { return '';}

        
    }


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
    function deParam($url){
        if (strpos($url,'?')>-1){
            //There's a ? in the url so kill that and everything after it
            $url=substr($url,0,strpos($url,'?'));
        }
        return $url;
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

//Run this crap
    $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST);
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Convert BSky Starter Pack to List</title>
</head>
<body>
    <h1>Convert a BSky Starter Pack to a List</h1>
    <form action="" method="POST">
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
