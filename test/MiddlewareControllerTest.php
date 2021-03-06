<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Mvc\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\Controller\MiddlewareController as DeprecatedMiddlewareController;
use Zend\Mvc\Middleware\MiddlewareController;
use Zend\Mvc\Exception\RuntimeException;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\DispatchableInterface;
use Zend\Stdlib\RequestInterface;
use Zend\Stratigility\MiddlewarePipe;

/**
 * @covers \Zend\Mvc\Middleware\MiddlewareController
 */
class MiddlewareControllerTest extends TestCase
{
    /**
     * @var MiddlewarePipe|MockObject
     */
    private $pipe;

    /**
     * @var ResponseInterface|MockObject
     */
    private $responsePrototype;

    /**
     * @var EventManagerInterface
     */
    private $eventManager;

    /**
     * @var AbstractController|MockObject
     */
    private $controller;

    /**
     * @var MvcEvent
     */
    private $event;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $this->pipe              = $this->createMock(MiddlewarePipe::class);
        $this->responsePrototype = $this->createMock(ResponseInterface::class);
        $this->eventManager      = $this->createMock(EventManagerInterface::class);
        $this->event             = new MvcEvent();
        $this->eventManager      = new EventManager();

        $this->controller = new MiddlewareController(
            $this->pipe,
            $this->responsePrototype,
            $this->eventManager,
            $this->event
        );
    }

    public function testEMHasDeprecatedControllerIdentifier()
    {
        $controller = new MiddlewareController(
            $this->pipe,
            $this->responsePrototype,
            new EventManager(),
            $this->event
        );
        $identifiers = $controller->getEventManager()->getIdentifiers();
        $this->assertContains(DeprecatedMiddlewareController::class, $identifiers);
    }

    public function testWillAssignCorrectEventManagerIdentifiers()
    {
        $identifiers = $this->eventManager->getIdentifiers();

        self::assertContains(MiddlewareController::class, $identifiers);
        self::assertContains(AbstractController::class, $identifiers);
        self::assertContains(DispatchableInterface::class, $identifiers);
    }

    public function testWillDispatchARequestAndResponseWithAGivenPipe()
    {
        $request          = new Request();
        $response         = new Response();
        $result           = $this->createMock(ResponseInterface::class);
        /* @var callable|MockObject $dispatchListener */
        $dispatchListener = $this->getMockBuilder(stdClass::class)->setMethods(['__invoke'])->getMock();

        $this->eventManager->attach(MvcEvent::EVENT_DISPATCH, $dispatchListener, 100);
        $this->eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, function () {
            self::fail('No dispatch error expected');
        }, 100);

        $dispatchListener
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(function (MvcEvent $event) use ($request, $response) {
                self::assertSame($this->event, $event);
                self::assertSame(MvcEvent::EVENT_DISPATCH, $event->getName());
                self::assertSame($this->controller, $event->getTarget());
                self::assertSame($request, $event->getRequest());
                self::assertSame($response, $event->getResponse());

                return true;
            }));

        $this->pipe->expects(self::once())->method('process')->willReturn($result);

        $controllerResult = $this->controller->dispatch($request, $response);

        self::assertSame($result, $controllerResult);
        self::assertSame($result, $this->event->getResult());
    }

    public function testWillRefuseDispatchingInvalidRequestTypes()
    {
        /* @var RequestInterface $request */
        $request          = $this->createMock(RequestInterface::class);
        $response         = new Response();
        /* @var callable|MockObject $dispatchListener */
        $dispatchListener = $this->getMockBuilder(stdClass::class)->setMethods(['__invoke'])->getMock();

        $this->eventManager->attach(MvcEvent::EVENT_DISPATCH, $dispatchListener, 100);

        $dispatchListener
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(function (MvcEvent $event) use ($request, $response) {
                self::assertSame($this->event, $event);
                self::assertSame(MvcEvent::EVENT_DISPATCH, $event->getName());
                self::assertSame($this->controller, $event->getTarget());
                self::assertSame($request, $event->getRequest());
                self::assertSame($response, $event->getResponse());

                return true;
            }));

        $this->pipe->expects(self::never())->method('process');

        $this->expectException(RuntimeException::class);

        $this->controller->dispatch($request, $response);
    }
}
