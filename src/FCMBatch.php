<?php

namespace Quantomtech\LaravelFirebaseBatchMessaging;

use Exception;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FCMBatch
{
    /**
     * Maximum payload that can be per API call as per documented
     */
    const MAX_PAYLOAD = 500;

    /**
     * Batch file folder path
     * 
     */
    protected $tempFolderPath;

    /**
     * JSON file downloaded when creating Google Cloud Service Account
     * 
     * @var string
     */
    protected $credentialFilePath;

    /**
     * Notification sound file name
     * 
     * @var string
     */
    protected $soundFileName;

    /**
     * Batch file unique name 
     * 
     * @var string
     */
    protected $batchFileTempName;


    /**
     * Payload array to be map
     * 
     */
    public $payload = [];

    /**
     * 
     */
    public function __construct()
    {
        $this->credentialFilePath = config('fcm-batch.credential_path');
        $this->tempFolderPath = config('fcm-batch.temp_file_path');
        $this->soundFileName = config('fcm-batch.fcm_sound');

        $this->batchFileTempName = 'batch_request_' . Str::random(5) . '.txt';
    }

    /**
     * Append boundry to request file
     * 
     */
    private function appendBoundry(): void
    {
        Storage::disk('local')->append($this->tempFolderPath . $this->batchFileTempName, "\n--subrequest_boundary");
    }

    /**
     * Append payload to request file
     * 
     */
    private function appendPayload(string $json):void
    {
        Storage::disk('local')->append($this->tempFolderPath . $this->batchFileTempName, "\n--subrequest_boundary\nContent-Type: application/http\nContent-Transfer-Encoding: binary\n\nPOST /v1/projects/meniaga-app/messages:send\nContent-Type: application/json\naccept: application/json\n\n" . $json);
    }

    /**
     * Remove generated request file
     */
    private function removeTempRequestFile(): void
    {
        Storage::disk('local')->delete($this->tempFolderPath . $this->batchFileTempName);
    }

    /**
     * Create OAuth JWT Token
     * Maximum validity is 1 Hour
     * 
     * @param int $periodSeconds
     */
    public function createJWTToken(int $periodSeconds = 3600): string
    {
        if($periodSeconds > 3600) {
            throw new Exception('Valid JWT period cannot more than 1 Hour.');
        }

        $content = file_get_contents($this->credentialFilePath);
        $jsonObj = json_decode($content);

        $iat = time();
        $exp = $iat + $periodSeconds;

        return  JWT::encode([
                    "iss" => $jsonObj->client_email,
                    "aud" => $jsonObj->token_uri,
                    "scope" => 'https://www.googleapis.com/auth/firebase.messaging',
                    'iat' => $iat,
                    'exp' => $exp
                ], $jsonObj->private_key, "RS256");
    }

    /**
     * Fetch API access token
     */
    private function getAccessToken(): string
    {
        $jwtToken = $this->createJWTToken();

        $res =  Http::acceptJson()
                ->asForm()
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwtToken
                ]);

        if($res->successful()) {

            /**
             * Sometimes, trailing dots returned, need to trim
             * https://stackoverflow.com/questions/68654502/why-am-i-getting-a-jwt-with-a-bunch-of-periods-dots-back-from-google-oauth
             * 
             */
            $body = $res->json();

            return rtrim($body["access_token"], '.');
        }

        throw new Exception('Failed to fetch access token. Body: ' . $res->body());
    }


    /**
     * Construct IOS Payload
     * 
     * @return $this
     */
    private function addIosPayload(
        string $fcmToken, 
        string $title, 
        string $body, 
        ?array $data
    ){
        $setPayload = [
            'message' =>[
                'token' => $fcmToken,
                'notification' =>[
                    'title' => $title,
                    'body' => $body
                ],
            ]
        ];

        if($data){
            $setPayload['message']['data'] = $data;
        }

        $this->payload[] = $setPayload;

        return $this;
    }

    /**
     * Construct Android Payload
     * 
     * @return $this
     */
    private function addAndroidPayload(
        string $fcmToken, 
        string $title, 
        string $body, 
        ?array $data
    ){
        $setPayload = [
            'message' =>[
                'token' => $fcmToken,
                'notification' =>[
                    'title' => $title,
                    'body' => $body
                ],
                'android' => [
                    'collapse_key' => $title,
                    'priority' => 'high',
                    'ttl' => '0s',
                    'notification' => [
                        "sound" => $this->soundFileName
                    ]
                ]
            ]
        ];

        if($data){
            $setPayload['message']['data'] = $data;
        }

        $this->payload[] = $setPayload;

        return $this;
    }

    /**
     * Add payload
     * https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages
     * 
     * @param  string title
     * @param string body
     * @param array data
     * 
     * @return $this
     */
    public function addPayload(
        string $fcmToken,
        string $title,
        string $body,
        array $data = null,
        bool $IosPayload = false
    ) {
        if($IosPayload) {
            return $this->addIosPayload( $fcmToken, $title, $body, $data);
        } 
        
        return $this->addAndroidPayload( $fcmToken, $title, $body, $data);
    }

    /**
     * Add custom payload data
     * https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages
     * 
     * @param array $data    require 'title', 'body' in array
     * 
     * @return $this
     */
    public function addCustomPayload(array $payload)
    {
        $token = Arr::get($payload, 'message.token', null);
        $title = Arr::get($payload, 'message.notification.title', null);
        $body = Arr::get($payload, 'message.notification.body', null);

        if(is_null($token) || is_null($title) || is_null($body)){
            throw new Exception('"fcm token", "body" and "title" are required');
        }

        $this->payload[] = $payload;

        return $this;
    }

    /**
     * Send batch payload
     * 
     */
    public function send(): Response
    {        
        // check total payload
        if(count($this->payload) > self::MAX_PAYLOAD) {
            throw new Exception("Total Payload per API call cannot exceed ". self::MAX_PAYLOAD ." messages");
        }

        // patch payload to file
        foreach ($this->payload as $payload) {
            $this->appendPayload(json_encode($payload));
        }

        $this->appendBoundry();

        // send
        $accessToken = $this->getAccessToken();

        $contents = Storage::disk('local')->get($this->tempFolderPath . $this->batchFileTempName);

        $res =  Http::attach('attachment', $contents)
                ->withHeaders([
                    'Authorization' => "Bearer " . $accessToken,
                    'Content-Type' => 'multipart/mixed; boundary="subrequest_boundary"'
                ])
                ->post('https://fcm.googleapis.com/batch');


        // remove temp file
        $this->removeTempRequestFile();

        return $res;
    }
}