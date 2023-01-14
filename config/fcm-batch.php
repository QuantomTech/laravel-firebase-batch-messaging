<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Location of Google Service Credential JSON
    |--------------------------------------------------------------------------
    |
    | This is the credential JSON downloaded after a 'service' is created in
    | GCP (https://console.cloud.google.com/apis/credentials?project=). Each 
    | service has defined API permissions. In this case, permissions for Cloud
    | Messaging is required.
    |
    */

    'credential_path' => env('FCMB_SERVICE_JSON_BASE_PATH'),


    /*
    |--------------------------------------------------------------------------
    | Custom Sound for Notification
    |--------------------------------------------------------------------------
    |
    | Set custom sound for your application notification.
    |
    */

    'fcm_sound' => env('FCMB_SOUND', 'default'),


    /*
    |--------------------------------------------------------------------------
    | Temporary Folder for Creating Text File
    |--------------------------------------------------------------------------
    |
    | Temporary file will be created at local storage to be use when sending
    | batch FCM. This file will contain all payloads of individual FCMs.
    |
    */

    'temp_file_path' => env('FCMB_TEMP_FOLDER', 'firebase/')
];