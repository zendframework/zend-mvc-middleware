<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Mvc\Middleware\TestAsset;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class Middleware
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next = null)
    {
        $response->getBody()->write(__CLASS__);
        return $response;
    }
}
