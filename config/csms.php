<?php

/*
|--------------------------------------------------------------------------
| CSMS Release Identity
|--------------------------------------------------------------------------
|
| The single source of truth for the application's name and release
| version. /health, the shared page layout and the home page all read these
| values through config('csms.*'); none of them hold a copy. To publish a
| new release, change 'version' here and nowhere else.
|
| These values are intentionally not read from .env: the release version
| belongs to the code being deployed, not to the environment it runs in.
| APP_NAME in .env remains the framework's own setting.
|
*/

return [

    'name' => 'Community Services Management System',

    'short_name' => 'CSMS',

    'version' => '1.0.0',

];
