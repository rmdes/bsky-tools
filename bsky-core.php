<?php
    class BlueskyApi
    {
        private ?string $accountDid = null;
        private ?string $apiKey = null;
        private string $apiUri;

        public function __construct(?string $handle = null, ?string $app_password = null, string $api_uri)
        {
            $this->apiUri = $api_uri . '/xrpc/';

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
    function deParam($url){
        if (strpos($url,'?')>-1){
            //There's a ? in the url so kill that and everything after it
            $url=substr($url,0,strpos($url,'?'));
        }
        return $url;
    }
    function getPDS($uName){
    try{
        $url="https://public.api.bsky.app/xrpc/app.bsky.actor.getProfile?actor=" . strtolower($uName);
        $file = file_get_contents($url, false);
        $json = json_decode($file, true);
        $uDID=$json['did'];
        $url="https://plc.directory/". $uDID . "/log/audit";
        $file = file_get_contents($url, false);
        $json = json_decode($file, true);
        if ($json){
            usort($json, function ($a, $b) {
                return $a['createdAt'] <=> $b['createdAt'];
            });
        
            $arrSize=count($json);
            $pdsURL=$json[$arrSize-1]['operation']['services']['atproto_pds']['endpoint'];
            if (strpos($pdsURL,'bsky.network')>-1){
                $pdsURL='https://bsky.social';
            }
            return $pdsURL;
        }
        else {
            return '';
        }
    }
    catch (exception $e){
        return '';
    }

    }
?>