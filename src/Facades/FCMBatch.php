<?php

namespace Quantomtech\LaravelFirebaseBatchMessaging\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Quantomtech\LaravelFirebaseBatchMessaging\FCMBatch addPayload(string $fcmToken, string $title, string $body, array $data = null, bool $IosPayload = false)
 * @method static \Quantomtech\LaravelFirebaseBatchMessaging\FCMBatch addCustomPayload(array $payload)
 * @method static \Illuminate\Http\Client\Response send()
 * @method static string getAccessToken()
 * @method static string createJWTToken(string $periodSeconds=3600)
 *
 * @see \Quantomtech\LaravelFirebaseBatchMessaging\FCMBatch
 */
class FCMBatch extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'qt.fcm.batch';
    }
}