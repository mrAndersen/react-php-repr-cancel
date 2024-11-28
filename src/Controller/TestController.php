<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use function React\Async\await;

class TestController extends AbstractController
{

    #[Route('/test')]
    public function index(): JsonResponse
    {
        $start = microtime(true);
        $result = $this->performHttpReq();

        return $this->json([
            'result' => $result,
            'elapsed' => (microtime(true) - $start) * 1000
        ]);
    }

    protected function performHttpReq(): int
    {
        $browser = new Browser();

        $timeout = 30;
        $method = "GET";
        $url = "https://ident.me";
        $headers = [];
        $body = "";

        /** @var ResponseInterface $response */
        $response = await(
            $browser
                ->withResponseBuffer(1024 * 1024 * 32)
                ->withTimeout($timeout)
                ->withFollowRedirects(10)
                ->withRejectErrorResponse(false)
                ->request($method, $url, $headers, $body)
        );

        return $response->getStatusCode();
    }
}
