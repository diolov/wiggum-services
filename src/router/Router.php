<?php
namespace wiggum\services\router;

use \InvalidArgumentException;
use \wiggum\http\Request;
use \wiggum\exceptions\PageNotFoundException;
use \wiggum\exceptions\InternalErrorException;
use \FastRoute\Dispatcher;
use \FastRoute\RouteCollector;

class Router extends \wiggum\foundation\Router {
    
    protected $dispatcher;
    protected $route;
    protected $routes = [];
    protected $routeGroups = [];
    protected $routeCounter;
    protected $registeredMiddleware;
    protected $registeredfilters;
    
    
    /**
     *
     * @param string $name
     * @param \Closure $closure
     */
    public function registerMiddleware($name, $closure) {
        $this->registeredMiddleware[$name] = $closure;
    }
    
    /**
     *
     * @param string $name
     * @param \Closure $closure
     */
    public function registerFilter($name, $closure) {
        $this->registeredfilters[$name] = $closure;
    }
    
    /**
     * 
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function get($pattern, $handler) {
        return $this->map(['GET'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function post($pattern, $handler) {
        return $this->map(['POST'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function put($pattern, $handler) {
        return $this->map(['PUT'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function patch($pattern, $handler) {
        return $this->map(['PATCH'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function delete($pattern, $handler) {
        return $this->map(['DELETE'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function options($pattern, $handler) {
        return $this->map(['OPTIONS'], $pattern, $handler);
    }

    /**
     *
     * @param string $pattern
     * @param mixed $handler
     * @return \wiggum\services\router\Route
     */
    public function any($pattern, $handler) {
        return $this->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $handler);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \wiggum\foundation\Router::map()
     */
    public function map($methods, $pattern, $handler) {
        
        if (!is_string($pattern)) {
            throw new InvalidArgumentException('Route pattern must be a string');
        }
        
        $methods = is_string($methods) ? [$methods] : $methods;
        $methods = array_map("strtoupper", $methods);
        $filters = [];
        $middleware = [];
        
        // Process Groups
        if ($this->routeGroups) {
            $groupPattern = '';
            foreach ($this->routeGroups as $group) {
                $groupPattern .= $group->getPattern();
                $filters = array_merge($filters, $group->getFilters());
                $middleware = array_merge($middleware, $group->getMiddleware());
            }
            
            $pattern = $groupPattern . $pattern;
        }
        
        // Add route
        $route = new Route($methods, $pattern, $handler, $this->routeCounter);
        $route->setFilters($filters);
        $route->setMiddleware($middleware);
        
        $this->routes[$route->getIdentifier()] = $route;
        $this->routeCounter++;
        
        return $route;
    }
	
	
	/* Groups */
	
    /**
     * 
     * @param unknown $attributes
     * @param unknown $callable
     * @return \wiggum\services\router\RouteGroup
     */
	public function group($attributes, $callable) {
	    $group = $this->pushGroup($attributes, $callable);
	    $group($this);
	    $this->popGroup();
	    return $group;
	}
	
	/**
	 * 
	 * @return array[]|string[]
	 */
/*	protected function processGroups() {
	    $filters = [];
	    $middleware = [];
	    $pattern = '';
	    
	    foreach ($this->routeGroups as $group) {
	        $pattern .= $group->getPattern();
	        $filters = array_merge($filters, $group->getFilters());
	        $middleware = array_merge($middleware, $group->getMiddleware());

	        
	    }
	    return ['pattern' => $pattern, 'filters' => $filters, 'middleware' => $middleware ];
	}
*/
	
	/**
	 * 
	 * @param unknown $attributes
	 * @param unknown $callable
	 * @return \wiggum\services\router\RouteGroup
	 */
	protected function pushGroup($attributes, $callable) {
	    $group = new RouteGroup($attributes, $callable);
	    array_push($this->routeGroups, $group);
	    return $group;
	}
	
	/**
	 * 
	 * @return boolean|\wiggum\services\router\RouteGroup
	 */
	protected function popGroup() {
	    $group = array_pop($this->routeGroups);
	    return $group instanceof RouteGroup ? $group : false;
	}
	
	
	/* runners */
	
	/**
	 * 
	 * @param Request $request
	 * @throws PageNotFoundException
	 * @throws InternalErrorException
	 * @return \wiggum\services\router\unknown[]|mixed[]
	 */
	public function lookup(Request $request) {
	    
	    $routeInfo = $this->dispatch($request);
	    
	    switch ($routeInfo[0]) {
	        case Dispatcher::NOT_FOUND:
	            // ... 404 Not Found
	            
	            throw new PageNotFoundException();
	            
	            break;
	        case Dispatcher::METHOD_NOT_ALLOWED:
	            // ... 405 Method Not Allowed
	            
	            $allowedMethods = $routeInfo[1];
	            throw new InternalErrorException('Method Not Allowed ['.implode(', ', $allowedMethods).' accepted], Ref: '.$request->getContextPath(), 405);
	            
	            break;
	        case Dispatcher::FOUND:
	            
	            $identifier = $routeInfo[1];
	            $parameters = $routeInfo[2];
	            
	            $route = $this->routes[$identifier];
	            $route->setParameters($parameters);
	            
	            return $route;
	            
	            break;
	    }
	    
	    throw new PageNotFoundException();
	}
	
	/**
	 * 
	 * @param Request $request
	 * @return unknown
	 */
	public function dispatch(Request $request) {
	    
	    return $this->createDispatcher()->dispatch(
	        $request->getMethod(),
	        $request->getContextPath()
	    );
	    
	}
	
	/**
	 * 
	 * @return unknown
	 */
	protected function createDispatcher() {
	    
	    if ($this->dispatcher) {
	        return $this->dispatcher;
	    }
	    
	    $routeDefinitionCallback = function (RouteCollector $r) {
	        foreach ($this->routes as $route) {
	            $r->addRoute($route->getMethods(), $route->getPattern(), $route->getIdentifier());
	        }
	    };
	    
	    $this->dispatcher = \FastRoute\simpleDispatcher($routeDefinitionCallback);

	    return $this->dispatcher;
	}
	
	/**
	 * 
	 * @param unknown $route
	 * @param unknown $parameters
	 * @return unknown[]|mixed[]
	 */
	public function process($route) {
	    
	    $handler = $route->getCallable();
	    $parameters = $route->getParameters();
	    
	    $actions = [];
	    
	    if (is_string($handler)) {
	        $routeSegments = explode('@', $handler);
	        
	        $actions['classPath'] = $routeSegments[0];
	        
	        if (isset($routeSegments[1]) && $routeSegments[1] != '') {
	            $actions['method'] = $routeSegments[1];
	        }
	        
	        if (!empty($parameters)) {
	            $actions['parameters'] = $parameters;
	        }
	        
	        
	    } else if (is_array($handler)) {
	        $routeSegments = explode('@', $handler['classPath']);
	        
	        $actions['classPath'] = $routeSegments[0];
	         
	        if (isset($routeSegments[1]) && $routeSegments[1] != '') {
	            $actions['method'] = $routeSegments[1];
	        }
	        
	        if (!empty($parameters)) {
	            $actions['parameters'] = $parameters;
	        }
	        
	        if (isset($handler['properties'])) {
	            $actions['properties'] = $handler['properties'];
	        }
	        
	    } else if (is_callable($handler)) {
	        $actions = (array) call_user_func_array($handler, [$parameters]);
	    }
	    
	    //run filters
	    if (!empty($route->getFilters())) {
	        $filters = $this->filterArray($this->registeredfilters, $route->getFilters());
	        foreach ($filters as $filter) {
	            $actions = $filter($actions);
	        }
	    }
	    
	    return $actions;
	}
	
	public function preProcess($route) {
	    // Attach additional route middleware to app
	    if (!empty($route->getMiddleware())) {
	        $middlewares = $this->filterArray($this->registeredMiddleware, $route->getMiddleware());
	        foreach ($middlewares as $middleware) {
	            $this->app->addMiddleware($middleware);
	        }
	    }
	    return $route;
	}

	
	/* Helpers */
	
	/**
	 * 
	 * @param unknown $array
	 * @param unknown $allowed
	 * @return array
	 */
	private function filterArray($array, $allowed) {
	    return array_intersect_key($array, array_flip($allowed));
	}
	
}