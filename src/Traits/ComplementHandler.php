<?php
/**
 * PHP library for adding addition of complements for Eliasis Framework.
 *
 * @author     Josantonius - hello@josantonius.com
 * @copyright  Copyright (c) 2017
 * @license    https://opensource.org/licenses/MIT - The MIT License (MIT)
 * @link       https://github.com/Eliasis-Framework/Complement
 * @since      1.0.9
 */

namespace Eliasis\Complement\Traits;

use Eliasis\App\App,
    Josantonius\File\File,
    Eliasis\Complement\Exception\ComplementException;

/**
 * Complement handler class.
 *
 * @since 1.0.9
 */
trait ComplementHandler {

    /**
     * Parameters required for the complement configuration file.
     *
     * @since 1.0.9
     *
     * @var array
     */
    protected $required = [
        'id',
        'name',
        'version',
        'description',
        'state',
        'category',
        'uri',
        'author',
        'author-uri',
        'license',
    ];

    /**
     * Set complement option/s.
     *
     * @since 1.0.9
     *
     * @param string $option → option name or options array
     * @param mixed  $value  → value/s
     *
     * @return mixed
     */
    public function set($option, $value) {

        if (!is_array($value)) {

            return $this->complement[$option] = $value;
        }

        if (array_key_exists($option, $value)) {

            $this->complement[$option] = array_merge_recursive(

                $this->complement[$option], $value
            );
        
        } else {

            foreach ($value as $key => $value) {
            
                $this->complement[$option][$key] = $value;
            }
        }

        return $this->complement[$option];        
    }

    /**
     * Get complement option/s.
     *
     * @since 1.0.9
     *
     * @param mixed $param/s
     *
     * @return mixed
     */
    public function get(...$params) {

        $key = array_shift($params);

        $col[] = isset($this->complement[$key]) ? $this->complement[$key] : 0;

        if (!count($params)) {

            return ($col[0]) ? $col[0] : '';
        }

        foreach ($params as $param) {

            $col = array_column($col, $param);
        }
        
        return (isset($col[0])) ? $col[0] : '';
    }

    /**
     * Get complement controller instance.
     *
     * @since 1.0.9
     *
     * @param array $class     → class name
     * @param array $namespace → namespace index
     *
     * @return object|false → class instance or false
     */
    public function instance($class, $namespace = '') {

        if (isset($this->complement['namespaces'])) {

            if (isset($this->complement['namespaces'][$namespace])) {

                $namespace = $this->complement['namespaces'][$namespace];

                $class = $namespace . $class . '\\' . $class;

                return call_user_func([$class, 'getInstance']);
            }

            foreach ($this->complement['namespaces'] as $key => $namespace) {

                $instance = $namespace . $class . '\\' . $class;
                
                if (class_exists($instance)) {

                    return call_user_func([$instance, 'getInstance']);
                }
            }
        }

        return false;
    }

    /**
     * Set complement.
     *
     * @since 1.0.9
     *
     * @param string $complement → complement settings
     * @param string $path       → complement path
     *
     * @uses array   ComplementState->getStates()    → get complements states
     * @uses string  ComplementState->getState()     → get complement state
     * @uses string  ComplementState->setState()     → set complement state
     * @uses boolean ComplementAction->getAction()   → get complement action
     * @uses string  ComplementAction->setAction()   → set complement action
     * @uses void    ComplementAction->_addActions() → add complement action
     * @uses void    ComplementAction->_doActions()  → execute action hooks
     *
     * @return void
     */
    private function _setComplement($complement, $path) {

        $this->getStates();

        $this->_setComplementParams($complement, $path);

        $state = $this->getState();

        $action = $this->getAction($state);

        $this->setAction($action);

        $this->setState($state);

        $this->_getSettings();

        $states = ['active', 'outdated'];

        if (in_array($action, $this->hooks) || in_array($state, $states)) {

            $this->_addRoutes();

            $this->_addActions();

            $this->_doActions($action, $state);
        }
    }

    /**
     * Check required params and set complement params.
     *
     * @since 1.0.9
     *
     * @param string $complement → complement settings
     * @param string $path       → complement path
     *
     * @uses string App::DS                  → directory separator
     * @uses string App::COMPLEMENT()        → complement path
     * @uses array  Complement->$complement  → complement settings
     * @uses string Complement::$type        → complement type
     * @uses string ComplementAction->$hooks → action hooks
     *
     * @throws ComplementException → complement configuration file error
     *
     * @return void
     */
    private function _setComplementParams($complement, $path) {

        $params = array_intersect_key(

            array_flip($this->required), 
            $complement
        );

        $slug = explode('.', basename($complement['config-file']));

        $default['slug'] = $slug[0];

        $complementType = strtoupper(self::$type);

        $path = App::$complementType() . $default['slug'] . App::DS;

        if (count($params) != 10) {

            $msg = ucfirst(self::$type) . " configuration file isn't correct";

            throw new ComplementException($msg . ': ' . $path . '.', 816);
        }

        $default['url-import'] = '';

        $default['hooks-controller'] = 'Launcher';

        $default['path']['root'] = rtrim($path, App::DS) . App::DS;

        $default['folder'] = $default['slug'] . App::DS;

        $this->complement = array_merge($default, $complement);

        $this->_setImage();
    }

    /**
     * Get settings.
     *
     * @since 1.0.9
     *
     * @uses array Complement->$complement → complement settings
     *
     * @return void
     */
    private function _getSettings() {

        $_root = $this->complement['path']['root'];

        $_config = $_root . 'config' . App::DS;

        if (is_dir($_config) && $handle = scandir($_config)) {

            $files = array_slice($handle, 2);

            foreach ($files as $file) {

                $content = require($_config . $file);

                $merge = array_merge($this->complement, $content);

                $this->complement = $merge;
            }
        }
        
        $this->complement['path']['root']   = $_root;
        $this->complement['path']['config'] = $_config;
    }

    /**
     * Set image url.
     *
     * @since 1.0.9
     *
     * @uses string App::DS
     * @uses string App::COMPLEMENT()       → complement path
     * @uses string App::COMPLEMENT_URL()   → complement url
     * @uses array  Complement->$complement → complement settings
     * @uses string Complement::$type       → complement type
     *
     * @return void
     */
    private function _setImage() {

        $slug = $this->complement['slug'];

        $complementType = strtoupper(self::$type);

        $complementPath = App::$complementType();

        $complementType .= '_URL';

        $complementUrl = App::$complementType();

        $file = 'public/images/' . $slug . '.png';

        $filepath = $complementPath . $slug . App::DS . $file;

        $url = 'https://raw.githubusercontent.com/Eliasis-Framework/Complement/';

        $directory = App::$complementUrl() . $slug . '/' . $file;

        $repository = rtrim($this->complement['url-import'], '/') . "/$file";
        
        $default = $url . 'master/public/images/default.png';

        if (File::exists($filepath)) {

            $this->complement['image'] = $directory;

        } else if (File::exists($repository)) {

            $this->complement['image'] = $repository; 
        
        } else {

            $this->complement['image'] = $default;
        }
    }

    /**
     * Add complement routes if exists.
     *
     * @since 1.0.9
     *
     * @uses array Router::addRoute         → add routes
     * @uses array Complement->$complement  → complement settings
     *
     * @return void
     */
    private function _addRoutes() {

        if (class_exists($Router = 'Josantonius\Router\Router')) {

            if (isset($this->complement['routes'])) {

                $Router::addRoute($this->complement['routes']);
            }
        }
    }
}