<?php

namespace App\Command;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;
use function React\Async\async;

#[AsCommand(
    name: 'app:run',
    description: 'Run HTTP server',
)]
class RunCommand extends Command
{

    public function __construct(
        protected readonly KernelInterface $kernel,
        protected readonly LoggerInterface $logger,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = $this->logger;
        $loop = Loop::get();
        $addr = "0.0.0.0:8081";

        $httpServer = $this->createHttpServerAndListen($addr);
        $logger->info(sprintf('Http server at %s', $addr));
        $loop->run();

        return 0;
    }

    public function createHttpServerAndListen(string $addr): HttpServer
    {
        $httpFoundationFactory = new HttpFoundationFactory();
        $psr7Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr7Factory, $psr7Factory, $psr7Factory, $psr7Factory);

        $logger = $this->logger;
        $kernel = $this->kernel;

        $callback = static function (ServerRequestInterface $request) use ($logger, $kernel, $httpFoundationFactory, $psrHttpFactory) {
            try {
                $symfonyRequest = $httpFoundationFactory->createRequest($request);
                $symfonyRequest->attributes->set('id', uniqid('', true));
                $sfResponse = $kernel->handle($symfonyRequest);
            } catch (Throwable $e) {
                $logger->emergency($e);
                $text = $kernel->getEnvironment() === 'prod' ? '' : sprintf("%s\n\n%s", $e->getMessage(), $e->getTraceAsString());

                return new Response(
                    500,
                    [],
                    $text
                );
            }

            return $psrHttpFactory->createResponse($sfResponse);
        };

        $httpServer = new HttpServer(async($callback));
        $socketServer = new SocketServer($addr);

        $socketServer->on('connection', static function (ConnectionInterface $connection) use ($logger): void {
            $id = uniqid('', true);
            $logger->debug(sprintf('New connection %s', $id));

            $connection->on('close', static function () use ($logger, $id): void {
                $logger->debug(sprintf('Connection %s close', $id));
            });
        });

        $httpServer->listen($socketServer);

        return $httpServer;
    }
}
