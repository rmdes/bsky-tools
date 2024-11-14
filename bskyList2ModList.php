<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=str_replace("@","",$_POST['handle']);
    $BSKY_PWTEST=$_POST['apppassword'];
    $listURL=$_POST['sourceurl'];

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

            $data = curl_exec($c);
            curl_close($c);

            return json_decode($data, false, 512, JSON_THROW_ON_ERROR);
        }
    }

    function bsky_List2ModList($bsky, $listAT) {

        //read the old list for accounts to add to the list
        $cursor='';
        $spList=[];
        do {
            $args=['list'=>$listAT,'limit'=>100,'cursor'=>$cursor];
            $res=$bsky->request('GET','app.bsky.graph.getList',$args);
            $spList=array_merge($spList,(array)$res->items);
            $cursor=$res->cursor;
            $packListName=$res->list->name;
        }
        while ($cursor);
        
        $args=[  'collection' => 'app.bsky.graph.list',
        'repo' => $bsky->getAccountDid(),
        'record' => [
            'createdAt' => date('c'),
            '$type' => 'app.bsky.graph.list',
            'purpose' => 'app.bsky.graph.defs#modlist',
            'name'=>$packListName .'-Mod' ,
            'description'=> "ModList imported from the User List at ". $_POST['sourceurl'] . ", powered by https://nws-bot.us/bskyList2ModList.php and @wandrme.paxex.aero.",
            ],
        ];

        if($data2 = $bsky->request('POST', 'com.atproto.repo.createRecord', $args)){
            $newListURI=$data2->uri;

   

            if($spList){
                foreach($spList as $listItem){
                    //Add the user to the pack list
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
                return ($newListURI);
            }
            else {
            echo "Couldn't find that source list. Please check the URL and try again.";
            }
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

//Run this crap
    $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST);

    if($bluesky){

        //handle short URLs for the pack
        $packURL = str_replace('bsky.app/starter-pack-short','go.bsky.app',$packURL);
        if (strpos($packURL,"go.bsky.app")>0  ){
            $packURL=curlGetFullUrl($packURL);
        }


        $arrList=explode('/',$listURL);
        $listUserHandle=$arrList[count($arrList)-3];
        $listID=$arrList[count($arrList)-1];



        $listAT=bskyListATs($bluesky,$listUserHandle,$listID);
        if ($listAT!=''){
            //Came back with an at: URI, so I can now fetch the Starter Pack and parse for the list details inside
            if ($newList=bsky_List2ModList($bluesky,$listAT)){
                $arrList=explode('/',$newList);
                $listID=$arrList[count($arrList)-1];
                echo('<p><a target="_blank" href="https://bsky.app/profile/'. $BSKY_HANDLETEST .'/lists/' .$listID.'">New Mod List Created</a></p>');
            }
        }
        else{
            echo "Could not find that Source Lise. Please check the URL and try again.";
        }

        $bluesky=null;
        echo "<p>Import Complete</p>";
    }
    else{
        echo "Error connecting to your account. Please check the username and app password and try again.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migrate Content List to Mod List</title>
</head>
<body>
    <h1>Migrate Content List to Mod List</h1>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Source List URL: <input type="text" name="sourceurl" placeholder="https://bsky.app/profile/wandrme.paxex.aero/lists/3jzxvt5ms372z" required></p>
        <input type="submit" name="submit" value="Submit">
    </form>
<hr />
<ul>
<li><a href="./bskyListCombiner.php">Import members from one list into another, existing list.</a></li>
<li><a href="./bskyList2StarterPack.php">Convert a BSky List to a Starter Pack.</a></li>
<li><a href="./bskyStarterPack.php">Convert a BSky Starter Pack to a List.</a></li>
    <li><a href="./bskyFollowBoost.php">Follow a random chunk of users from someone else's list.</a></li>
    <li><a href="./bskySPMerge.php">Merge Starter Packs</a></li>
    <li><a href="./bskyList2ModList.php">Migrate Content List to Mod List</a></li>
    

</ul>
<hr />
<p><a href="https://github.com/sbm12/bsky-Pack2List" target="_blank">Link to source code</a>. I'm not logging anything on this server, at least not on purpose, but you should still probably use an App Password then delete it when you're done.</p>
</body>
</html>
