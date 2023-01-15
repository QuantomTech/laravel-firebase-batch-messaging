# Batch Messaging using Firebase FCM API
This is based on [Firebase Doc](https://firebase.google.com/docs/cloud-messaging/send-message#send-messages-to-multiple-devices.). Usually, using [Channel](https://laravel.com/docs/8.x/notifications#specifying-delivery-channels) will result in one API call per one message although queued. Considering bulk users with more than thousands, this largely overwhelm API calls. This package implement simple method to send batch max 500 messages per API call.


## Installation

Install using composer :
```code
composer require quantomtech/laravel-firebase-batch-messaging
```

Publish config File :
```code
php artisan vendor:publish --provider="Quantomtech\LaravelFirebaseBatchMessaging\Providers\FCMBatchServiceProvider"
```

## Setup Credentials

Define Google Service Account JSON path inside .env:
```code
FCMB_SERVICE_JSON_BASE_PATH='/var/www/my-laravel-project/service-app-firebase.json'
```

Optional configuration inside .env :
```code
# Is the filename for your notification sound file.
FCMB_SOUND="my_custom_noti_sound.wav'

# Is the path to generate temporary file for the package
FCMB_TEMP_FOLDER=
```

## Sample Use

Considering 1000 users, 2 batch jobs are queued as Firebase only allow 500 messages max per API call.

SendFCMJob.php to queue tasks :
```code
<?php

class SendNotificationReferralJob implements ShouldQueue
{
    use Dispatchable, 
        InteractsWithQueue, 
        Queueable, 
        SerializesModels;
    
    protected $userIds;
    protected $title;
    protected $message;
        
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $userIds)
    {
        $this->userIds  = $userIds;
        $this->title    = trans("notification.share_title");
        $this->message  = trans("notification.share_body");
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            User::whereIn('id', $this->userIds)
                ->select(['fcm_token', 'apns_token'])
                ->each(function($user)
                {
                    $this->batchService->addPayload(
                        $user->fcm_token, 
                        $this->title, 
                        $this->message, 
                        [
                            "key" => "foo",
                        ],
                        (! is_null($user->apns_token)
                    );
                });

            $this->batchService->send();
        }
        catch (Exception $e){
            //
        }
    }

```

Dispatching SendFCMJob in Controller :

```code
<?php

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;


class NotificationController extends Controller
{
    /**
     * Send notification to users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        User::query()
          ->select(['id'])
          ->chunkById(500, function($users) {
              SendFCMJob::dispatch($ids);
          });
    }
}
```
