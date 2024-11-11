<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=$_POST['handle'];
    $BSKY_PWTEST=$_POST['apppassword'];
    $packURL=$_POST['packurl'];
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

            $data = curl_exec($c);
            curl_close($c);

            return json_decode($data, false, 512, JSON_THROW_ON_ERROR);
        }
    }





    function bsky_List2StarterPack($bsky, $spAT,$listAT) {

    
        // delete the seven temp accounts from the pack list before I start?
        
        $args=['list'=>$spAT,'limit'=>100];
        if($sourceList=$bsky->request('GET','app.bsky.graph.getList',$args)){
            foreach($sourceList->items as $listItem){


                $args=[  'collection' => 'app.bsky.graph.listitem',
                'repo' => $bsky->getAccountDid(),
                'rkey' => $listItem->uri
                ];
                $bsky->request('POST', 'com.atproto.repo.deleteRecord', $args);
            }
        }
        //read the source list for accounts to add to the pack list     
        $largs=['list'=>$listAT,'limit'=>100];

        if($sourceList=$bsky->request('GET','app.bsky.graph.getList',$largs)){
            foreach($sourceList->items as $listItem){
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


        $arrPack=explode('/',$packURL);
        $userHandle=$arrPack[count($arrPack)-2];
        $packID=$arrPack[count($arrPack)-1];

        $arrList=explode('/',$listURL);
        $listUserHandle=$arrList[count($arrList)-3];
        $listID=$arrList[count($arrList)-1];



        $packAT=bskySPs($bluesky,$userHandle,$packID);
        $listAT=bskyListATs($bluesky,$listUserHandle,$listID);
        if ($packAT!='' && $listAT!=''){
            //Came back with an at: URI, so I can now fetch the Starter Pack and parse for the list details inside
            bsky_List2StarterPack($bluesky,$packAT,$listAT);
        }
        else{
            echo "Could not find that Starter Pack. Please check the URL and try again.";
        }

        $bluesky=null;
        echo "Import Complete";
    }
    else{
        echo "Error connecting to your account. Please check the username and app password and try again.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Convert a BSky List to a Starter Pack</title>
</head>
<body>
    <h1>Convert a BSky List to a Starter Pack</h1>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>Starter Pack URL: <input type="text" name="packurl" placeholder="https://bsky.app/starter-pack/wandrme.paxex.aero/3l6stg6xfrc23" required></p>
        <p>Source List URL: <input type="text" name="listurl" placeholder="https://bsky.app/profile/wandrme.paxex.aero/lists/3jzxvt5ms372z" required></p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <p>Create a new starter pack from the Profile tab of your account. Add seven random accounts so it can save. Perform the import. Remove the original seven random accounts.</p>
    <p>*Note: Only the first 100 entries in the list will be included in the conversion!</p>
<hr />
<ul>
<li><a href="./bskyListCombiner.php">Import members from one list into another, existing list.</a></li>
<li><a href="./bskyList2StarterPack.php">Convert a BSky List to a Starter Pack.</a></li>
<li><a href="./bskyStarterPack.php">Convert a BSky Starter Pack to a List.</a></li>
    <li><a href="./bskyFollowBoost.php">Follow a random chunk of users from someone else's list.</a></li>
</ul>
<hr />
<p><a href="https://github.com/sbm12/bsky-Pack2List" target="_blank">Link to source code</a>. I'm not logging anything on this server, at least not on purpose, but you should still probably use an App Password then delete it when you're done.</p></body>
</html>
