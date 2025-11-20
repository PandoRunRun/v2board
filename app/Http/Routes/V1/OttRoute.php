<?php

namespace App\Http\Routes\V1;

use Illuminate\Contracts\Routing\Registrar;

class OttRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'admin/ott',
            'middleware' => 'admin'
        ], function ($router) {
            $router->post('/account/save', 'V1\\Admin\\OttController@saveAccount');
            $router->post('/account/drop', 'V1\\Admin\\OttController@dropAccount');
            $router->get('/account/fetch', 'V1\\Admin\\OttController@fetchAccount');
            $router->get('/user/fetch', 'V1\\Admin\\OttController@fetchUsers');
            $router->post('/user/bind', 'V1\\Admin\\OttController@bindUser');
            $router->post('/user/unbind', 'V1\\Admin\\OttController@unbindUser');
            
            // Renewal Routes
            $router->get('/renewal/fetch', 'V1\\Admin\\OttRenewalController@fetch');
            $router->post('/renewal/save', 'V1\\Admin\\OttRenewalController@save');
            $router->post('/renewal/drop', 'V1\\Admin\\OttRenewalController@drop');
            $router->post('/renewal/import', 'V1\\Admin\\OttRenewalController@importCurrentUsers');
        });

        $router->group([
            'prefix' => 'user/ott',
            'middleware' => 'client'
        ], function ($router) {
            $router->get('/account/fetch', 'V1\\User\\OttController@fetchAccount');
            $router->get('/message/fetch', 'V1\\User\\OttController@fetchMessage');
            $router->get('/renewal/fetch', 'V1\\User\\OttController@fetchRenewal');
        });

        $router->group([
            'prefix' => 'server/ott',
        ], function ($router) {
            $router->post('/webhook', 'V1\\Server\\OttController@webhook');
        });
    }
}
