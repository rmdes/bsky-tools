<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=$_POST['handle'];
    $BSKY_PWTEST=$_POST['apppassword'];
    $targetUser=$_POST['targetUser'];
    $numAccts=$_POST['numAccts'];
    $copyCap=.1; //Limit to only copying 10% of the follows from the account; adjust as desired 


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


    if($bluesky){

        //get did for the  user from which to copy
       
        $args = [
            'actor' => $targetUser
        ];

        if ($tUsr=$bluesky->request('GET','app.bsky.actor.getProfile',$args)){

            
            //get all the follows for that user
            $tDID=$tUsr->did;
            $tFCount=$tUsr->followsCount;
            //get follows of that user
            $arrFoll=[];
            $args = [
                'actor' => $tDID,
                'limit'=>100
            ];
            $follows=json_decode(json_encode($bluesky->request('GET','app.bsky.graph.getFollows',$args)),true);
            $arrFoll=array_merge($follows['follows'],$arrFoll);
            if($follows['cursor']){
                $args = [
                    'actor' => $tDID,
                    'limit'=>100,
                    'cursor'=>$follows['cursor']
                ];
                $follows=json_decode(json_encode($bluesky->request('GET','app.bsky.graph.getFollows',$args)),true);
                $arrFoll=array_merge($follows['follows'],$arrFoll);    
            }
        
            //Now Loop to NN accounts (or % cap) and add those as follows 
            $numTries=min(round(count($arrFoll)/$copyCap),$numAccts);
            $randAccts=array_rand($arrFoll,$numTries);
            foreach($randAccts as $acct){
                //add as a follow
                $args=[
                    'collection' => 'app.bsky.graph.follow',
                    'repo' => $bluesky->getAccountDid(),
                    'record' => [
                        'subject'=>$arrFoll[$acct]['did'],
                        'createdAt' => date('c'),
                        '$type' => 'app.bsky.graph.follow',
                    ],
                ];
                $res=$bluesky->request('POST', 'com.atproto.repo.createRecord', $args);
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
    <title>Follow a random chunk of users from someone else's list</title>
</head>
<body>
    <h1>Follow a random chunk of users from someone else's list</h1>
    <form action="" method="POST">
        <p>Your BSky Handle: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
        <p>Your BSky <a href="https://bsky.app/settings/app-passwords" target="_blank">App Password</a>: <input type="password" name="apppassword" placeholder="abcd-1234-fghi-5678" required></p>
        <p>User from whom to poach follows: <input type="text" name="targetUser" placeholder="username.doman.name" required></p>
        <p>How many random users to follow?: <input type="text" name="numAccts" placeholder="25" required> *Up to 10% of their total follows or this number, whichever is larger, will be followed.</p> 
        <input type="submit" name="submit" value="Submit">
    </form>
    <hr />
    <ul>
    <li><a href="./bskyListCombiner.php">Import members from one list into another, existing list.</a></li>
    <li><a href="./bskyList2StarterPack.php">Convert a BSky List to a Starter Pack.</a></li>
    <li><a href="./bskyStarterPack.php">Convert a BSky Starter Pack to a List.</a></li>
    <li><a href="./bskyFollowBoost.php">Follow a random chunk of users from someone else's list.</a></li>
    <li><a href="./bskySPMerge.php">Merge Starter Packs</a></li>
    </ul>
<hr />
<p><a href="https://github.com/sbm12/bsky-Pack2List" target="_blank">Link to source code</a>. I'm not logging anything on this server, at least not on purpose, but you should still probably use an App Password then delete it when you're done.</p>
</body>
</html>
