<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=str_replace(["@",'bsky.app'],["",'bsky.social'],$_POST['handle']);;
    $BSKY_PWTEST=$_POST['apppassword'];

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





//Run this crap

    $bluesky = new BlueskyApi($BSKY_HANDLETEST, $BSKY_PWTEST);


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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create a list from all the people you follow</title>
</head>
<body>
    <h1>Create a list from all the people you follow</h1>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <input type="submit" name="submit" value="Submit">
    </form>
    <?php
include "./footer.php";
?>
