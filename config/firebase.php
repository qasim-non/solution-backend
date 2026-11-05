<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Project
    |--------------------------------------------------------------------------
    |
    | The project id shown in the Firebase console. When a service account is
    | supplied the SDK can infer this value, but setting it explicitly keeps
    | behaviour predictable across environments.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Service Account Credentials
    |--------------------------------------------------------------------------
    |
    | The Admin SDK needs a service account to call FCM. Store the JSON file
    | outside version control at storage/app/firebase/service-account.json
    | (the default below) or point FIREBASE_CREDENTIALS at another path.
    |
    */

    'credentials' => env('FIREBASE_CREDENTIALS') ?: storage_path('app/firebase/service-account.json'),

    /*
    |--------------------------------------------------------------------------
    | Inline Service Account Credentials
    |--------------------------------------------------------------------------
    |
    | Some hosts (Render, Railway, Heroku, ...) cannot mount a file. In that
    | case paste the service account JSON into FIREBASE_CREDENTIALS_JSON.
    | This value is read at runtime and is never committed to the repository.
    |
    */

    'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),

    /*
    |--------------------------------------------------------------------------
    | Frontend URL
    |--------------------------------------------------------------------------
    |
    | Used to build the link that opens when a push notification is clicked.
    | Keep this in sync with the deployed React application.
    |
    */

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

];
