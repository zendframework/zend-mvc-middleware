<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Mvc\Middleware\TestAsset;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;

use function class_exists;

class MiddlewareAbstractFactory implements AbstractFactoryInterface
{
    public $classmap = [
        'test' => Middleware::class,
    ];

    public function canCreate(ContainerInterface $container, $name)
    {
        if (! isset($this->classmap[$name])) {
            return false;
        }

        $classname = $this->classmap[$name];
        return class_exists($classname);
    }

    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $classname = $this->classmap[$name];
        return new $classname;
    }
}
