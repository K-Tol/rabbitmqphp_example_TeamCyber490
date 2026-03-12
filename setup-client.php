<?php

use Symfony\Component\EventDispatcher\EventDispatcher;
use Tmdb\Client;
use Tmdb\Event\BeforeRequestEvent;
use Tmdb\Event\Listener\Request\AcceptJsonRequestListener;
use Tmdb\Event\Listener\Request\ApiTokenRequestListener;
use Tmdb\Event\Listener\Request\ContentTypeJsonRequestListener;
use Tmdb\Event\Listener\RequestListener;
use Tmdb\Event\RequestEvent;
use Tmdb\Token\Api\BearerToken;

$token = new BearerToken(TMDB_BEARER_TOKEN);
$ed = new EventDispatcher();

$client = new Client([
    'api_token'        => $token,
    'event_dispatcher' => ['adapter' => $ed],
]);

$requestListener = new RequestListener($client->getHttpClient(), $ed);
$ed->addListener(RequestEvent::class, $requestListener);

$apiTokenListener = new ApiTokenRequestListener($client->getToken());
$ed->addListener(BeforeRequestEvent::class, $apiTokenListener);

$acceptJsonListener = new AcceptJsonRequestListener();
$ed->addListener(BeforeRequestEvent::class, $acceptJsonListener);

$jsonContentTypeListener = new ContentTypeJsonRequestListener();
$ed->addListener(BeforeRequestEvent::class, $jsonContentTypeListener);

return $client;