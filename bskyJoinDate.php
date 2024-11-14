<?php
error_reporting(E_ERROR);
ini_set('display_errors', '1');
if(isset($_POST['submit'])) {


    $BSKY_HANDLETEST=$_POST['handle'];

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

    function bSkyAnonRequest(string $type, string $request, array $args = [], ?string $body = null, string $content_type = null)
    {
        $url = 'https://public.api.bsky.app/xrpc/'. $request;

        if (($type === 'GET') && (count($args))) {
            $url .= '?' . http_build_query($args);
        } elseif (($type === 'POST') && (!$content_type)) {
            $content_type = 'application/json';
        }

        if ($content_type) {
            $headers[] = 'Content-Type: ' . $content_type;

            if (($content_type === 'application/json') && (count($args))) {
                $body = json_encode($args, JSON_THROW_ON_ERROR);
                $args = [];
            }
        }
        else{
            $headers[]=[];
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

//Run this crap

$args = [
    'actor' => $BSKY_HANDLETEST
];


        if($data = bSkyAnonRequest('GET', 'app.bsky.actor.getprofile', $args)){
            echo 'User Join Date: ' . $data->createdAt;
                }
                        else{
            echo "Could not find that User. Please check the entry and try again.";
        }

        $bluesky=null;
        echo "<p>Import Complete</p>";
    
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Join Date Query</title>
</head>
<body>
    <h1>User Join Date Query</h1>
    <form action="" method="POST">
        <p>BSky Handle to QUery: <input type="text" name="handle" placeholder="user.bsky.social" required></p>
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
