<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Mvc\Middleware;

use Exception;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\Response;
use Zend\Mvc\Application;
use Zend\Mvc\Exception\InvalidMiddlewareException;
use Zend\Mvc\MvcEvent;
use Zend\Psr7Bridge\Psr7Response;
use Zend\Stratigility\MiddlewarePipe;

class MiddlewareListener extends AbstractListenerAggregate
{
    /**
     * Attach listeners to an event manager
     *
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 1);
    }

    /**
     * Listen to the "dispatch" event
     *
     * @return null|Response
     */
    public function onDispatch(MvcEvent $event)
    {
        if (null !== $event->getResult()) {
            return null;
        }

        $routeMatch = $event->getRouteMatch();
        $middleware = $routeMatch->getParam('middleware', false);
        if (false === $middleware) {
            return null;
        }

        $request        = $event->getRequest();
        $application    = $event->getApplication();
        $response       = $application->getResponse();
        $serviceManager = $application->getServiceManager();

        $psr7ResponsePrototype = Psr7Response::fromZend($response);

        try {
            $pipe = $this->createPipeFromSpec(
                $serviceManager,
                $psr7ResponsePrototype,
                is_array($middleware) ? $middleware : [$middleware]
            );
        } catch (InvalidMiddlewareException $invalidMiddlewareException) {
            $return = $this->marshalInvalidMiddleware(
                Application::ERROR_MIDDLEWARE_CANNOT_DISPATCH,
                $invalidMiddlewareException->toMiddlewareName(),
                $event,
                $application,
                $invalidMiddlewareException
            );
            $event->setResult($return);
            return $return;
        }

        $caughtException = null;
        try {
            $return = (new MiddlewareController(
                $pipe,
                $psr7ResponsePrototype,
                $application->getServiceManager()->get('EventManager'),
                $event
            ))->dispatch($request, $response);
        } catch (Throwable $ex) {
            $caughtException = $ex;
        } catch (Exception $ex) {  // @TODO clean up once PHP 7 requirement is enforced
            $caughtException = $ex;
        }

        if ($caughtException !== null) {
            $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
            $event->setError(Application::ERROR_EXCEPTION);
            $event->setParam('exception', $caughtException);

            $events  = $application->getEventManager();
            $results = $events->triggerEvent($event);
            $return  = $results->last();
            if (! $return) {
                $return = $event->getResult();
            }
        }

        $event->setError('');

        if (! $return instanceof PsrResponseInterface) {
            $event->setResult($return);
            return $return;
        }
        $response = Psr7Response::toZend($return);
        $event->setResult($response);
        return $response;
    }

    /**
     * Create a middleware pipe from the array spec given.
     *
     * @param mixed[] $middlewaresToBePiped
     *      List of string ids for middlewares in container, instances of MiddlewareInterface
     *      or callable middlewares
     * @return MiddlewarePipe
     * @throws InvalidMiddlewareException
     */
    private function createPipeFromSpec(
        ContainerInterface $serviceLocator,
        ResponseInterface $responsePrototype,
        array $middlewaresToBePiped
    ) {
        $pipe = new MiddlewarePipe();
        $pipe->setResponsePrototype($responsePrototype);
        foreach ($middlewaresToBePiped as $middlewareToBePiped) {
            if (null === $middlewareToBePiped) {
                throw InvalidMiddlewareException::fromNull();
            }

            $middlewareName = is_string($middlewareToBePiped) ? $middlewareToBePiped : get_class($middlewareToBePiped);

            if (is_string($middlewareToBePiped) && $serviceLocator->has($middlewareToBePiped)) {
                $middlewareToBePiped = $serviceLocator->get($middlewareToBePiped);
            }
            if (! $middlewareToBePiped instanceof MiddlewareInterface && ! is_callable($middlewareToBePiped)) {
                throw InvalidMiddlewareException::fromMiddlewareName($middlewareName);
            }

            $pipe->pipe($middlewareToBePiped);
        }
        return $pipe;
    }

    /**
     * Marshal a middleware not callable exception event
     *
     * @param  string $type
     * @param  string $middlewareName
     * @return mixed
     */
    protected function marshalInvalidMiddleware(
        $type,
        $middlewareName,
        MvcEvent $event,
        Application $application,
        Exception $exception = null
    ) {
        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError($type);
        $event->setController($middlewareName);
        $event->setControllerClass('Middleware not callable: ' . $middlewareName);
        if ($exception !== null) {
            $event->setParam('exception', $exception);
        }

        $events  = $application->getEventManager();
        $results = $events->triggerEvent($event);
        $return  = $results->last();
        if (! $return) {
            $return = $event->getResult();
        }
        return $return;
    }
}
