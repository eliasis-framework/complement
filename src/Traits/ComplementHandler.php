<?php
/**
 * PHP library for adding addition of complements for Eliasis Framework.
 *
 * @author    Josantonius <hello@josantonius.com>
 * @copyright 2017 - 2018 (c) Josantonius - Eliasis Complement
 * @license   https://opensource.org/licenses/MIT - The MIT License (MIT)
 * @link      https://github.com/Eliasis-Framework/Complement
 * @since     1.0.9
 */
namespace Eliasis\Complement\Traits;

use Eliasis\Complement\Exception\ComplementException;
use Eliasis\Framework\App;
use Josantonius\File\File;
use Josantonius\Url\Url;

/**
 * Complement handler class.
 */
trait ComplementHandler
{
    /**
     * Parameters required for the complement configuration file.
     *
     * @var array
     */
    protected static $required = [
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
     * @since 1.1.0
     *
     * @param string $option → option name or options array
     * @param mixed  $value  → value/s
     *
     * @return mixed
     */
    public function setOption($option, $value)
    {
        if (! is_array($value)) {
            return $this->complement[$option] = $value;
        }

        if (array_key_exists($option, $value)) {
            $this->complement[$option] = array_merge_recursive(
                $this->complement[$option],
                $value
            );
        } else {
            foreach ($value as $key => $value) {
                $this->complement[$option][$key] = $value;
            }
        }

        return $this->complement[$option];
    }

    /**
     * Set complement option/s.
     *
     * This method will be removed in future versions, instead you should use setOption().
     *
     * @deprecated 1.0.9
     *
     * @param string $option → option name or options array
     * @param mixed  $value  → value/s
     *
     * @return mixed
     */
    public function set($option, $value)
    {
        trigger_error(
            'The "Complement->set()" is deprecated, instead you should use "Complement->setOption()".',
            E_USER_ERROR
        );

        return $this->setOption($option, $value);
    }

    /**
     * Get complement option/s.
     *
     * @param mixed $param/s
     *
     * @return mixed
     */
    public function getOption(...$params)
    {
        $key = array_shift($params);

        $col[] = isset($this->complement[$key]) ? $this->complement[$key] : 0;

        if (! count($params)) {
            return ($col[0]) ? $col[0] : '';
        }

        foreach ($params as $param) {
            $col = array_column($col, $param);
        }

        return (isset($col[0])) ? $col[0] : '';
    }

    /**
     * Get complement option/s.
     *
     * This method will be removed in future versions, instead you should use getOption().
     *
     * @deprecated 1.0.9
     *
     * @param mixed $param/s
     *
     * @return mixed
     */
    public function get(...$params)
    {
        return $this->getOption(...$params);
    }

    /**
     * Get complement controller instance.
     *
     * @since 1.1.0
     *
     * @param array $class     → class name
     * @param array $namespace → namespace index
     *
     * @return object|false → class instance or false
     */
    public function getControllerInstance($class, $namespace = '')
    {
        if (isset($this->complement['namespaces'])) {
            if (isset($this->complement['namespaces'][$namespace])) {
                $namespace = $this->complement['namespaces'][$namespace];

                $_class = $namespace . $class . '\\' . $class;

                if (class_exists($_class)) {
                    return call_user_func([$_class, 'getInstance']);
                }

                return false;
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
     * Get complement controller instance.
     *
     * This method will be removed in future versions, instead you should use getControllerInstance().
     *
     * @deprecated 1.0.9
     *
     * @param array $class     → class name
     * @param array $namespace → namespace index
     *
     * @return object|false → class instance or false
     */
    public function instance($class, $namespace = '')
    {
        trigger_error(
            'The "Complement->getInstance()" is deprecated, instead you should use "Complement->getControllerInstance()".',
            E_USER_ERROR
        );

        return $this->getControllerInstance($class, $namespace);
    }

    /**
     * Set complement.
     *
     * @param string $complement → complement settings
     * @param string $path       → complement path
     *
     * @uses \Eliasis\Complement\Traits\ComplementState->getStates()
     * @uses \Eliasis\Complement\Traits\ComplementState->getState()
     * @uses \Eliasis\Complement\Traits\ComplementState->setState()
     * @uses \Eliasis\Complement\Traits\ComplementAction->getAction()
     * @uses \Eliasis\Complement\Traits\ComplementAction->setAction()
     * @uses \Eliasis\Complement\Traits\ComplementAction->addActions()
     * @uses \Eliasis\Complement\Traits\ComplementAction->doActions()
     */
    private function setComplement($complement, $path)
    {
        $this->getStates();
        $this->setComplementParams($complement, $path);

        $state = $this->getState();
        $action = $this->getAction($state);

        $this->setAction($action);
        $this->setState($state);
        $this->getSettings();

        $states = ['active', 'outdated'];

        if (in_array($action, self::$hooks, true) || in_array($state, $states, true)) {
            $this->addRoutes();
            $this->addActions();
            $this->doActions($action);
        }
    }

    /**
     * Check required params and set complement params.
     *
     * @param string $complement → complement settings
     * @param string $path       → complement path
     *
     * @uses \Eliasis\Complement\Complement->$complement
     * @uses \Eliasis\Complement\Traits\ComplementHandler::getType()
     * @uses \Eliasis\Complement\Traits\ComplementAction->$hooks
     *
     * @throws ComplementException → complement configuration file error
     */
    private function setComplementParams($complement, $path)
    {
        $params = array_intersect_key(
            array_flip(self::$required),
            $complement
        );

        $slug = explode('.', basename($complement['config-file']));
        $default['slug'] = $slug[0];
        $complementType = self::getType('strtoupper');
        $path = App::$complementType() . $default['slug'] . '/';

        if (count($params) != 10) {
            $msg = self::getType('ucfirst') . " configuration file isn't correct";
            throw new ComplementException($msg . ': ' . $path . '.', 816);
        }

        $default['url-import'] = '';
        $default['hooks-controller'] = 'Launcher';
        $default['path']['root'] = Url::addBackSlash($path);
        $default['folder'] = $default['slug'] . '/';
        $lang = $this->_getLanguage();

        if (isset($complement['name'][$lang])) {
            $complement['name'] = $complement['name'][$lang];
        }

        if (isset($complement['description'][$lang])) {
            $complement['description'] = $complement['description'][$lang];
        }

        $this->complement = array_merge($default, $complement);

        $this->_setImage();
    }

    /**
     * Get settings.
     *
     * @uses \Eliasis\Complement\Complement->$complement → complement settings
     */
    private function getSettings()
    {
        $rootPath = $this->complement['path']['root'];
        $configFile = $rootPath . 'config/';

        if (is_dir($configFile) && $handle = scandir($configFile)) {
            $files = array_slice($handle, 2);

            foreach ($files as $file) {
                $content = require $configFile . $file;
                $merge = array_merge($this->complement, $content);
                $this->complement = $merge;
            }
        }

        $this->complement['path']['root'] = $rootPath;
        $this->complement['path']['config'] = $configFile;
    }

    /**
     * Gets the current locale.
     *
     * @uses \get_locale() → gets the current locale in WordPress
     */
    private function _getLanguage()
    {
        $wpLang = (function_exists('get_locale')) ? get_locale() : null;
        $browserLang = @$_SERVER['HTTP_ACCEPT_LANGUAGE'] ?: null;

        return  substr($wpLang ?: $browserLang ?: 'en', 0, 2);
    }

    /**
     * Set image url.
     *
     * @uses \Eliasis\Framework\App::COMPLEMENT()
     * @uses \Eliasis\Framework\App::COMPLEMENT_URL()
     * @uses \Eliasis\Complement\Complement->$complement
     * @uses \Eliasis\Complement\Traits\ComplementHandler::getType()
     */
    private function _setImage()
    {
        $slug = $this->complement['slug'];

        $complementType = self::getType('strtoupper');
        $complementPath = App::$complementType();
        $complementUrl = $complementType . '_URL';
        $complementUrl = App::$complementUrl();

        $file = 'public/images/' . $slug . '.png';
        $filepath = $complementPath . $slug . '/' . $file;

        $url = 'https://raw.githubusercontent.com/Eliasis-Framework/Complement/';

        $directory = $complementUrl . $slug . '/' . $file;

        $repository = rtrim($this->complement['url-import'], '/') . "/$file";

        $default = $url . 'master/src/public/images/default.png';

        if (File::exists($filepath)) {
            $this->complement['image'] = $directory;
        } elseif (File::exists($repository)) {
            $this->complement['image'] = $repository;
        } else {
            $this->complement['image'] = $default;
        }
    }

    /**
     * Get complement type.
     *
     * @param string $mode   → ucfirst|strtoupper|strtolower
     * @param bool   $plural → plural|singular
     *
     * @return object → complement instance
     */
    private static function getType($mode = 'strtolower', $plural = true)
    {
        $namespace = get_called_class();
        $class = explode('\\', $namespace);
        $complement = strtolower(array_pop($class) . ($plural ? 's' : ''));

        switch ($mode) {
            case 'ucfirst':
                return ucfirst($complement);
            case 'strtoupper':
                return strtoupper($complement);
            default:
                return $complement;
        }
    }

    /**
     * Add complement routes if exists.
     *
     * @uses \Josantonius\Router\Router::addRoute
     * @uses \Eliasis\Complement\Complement->$complement
     */
    private function addRoutes()
    {
        if (class_exists($Router = 'Josantonius\Router\Router')) {
            if (isset($this->complement['routes'])) {
                $Router::addRoute($this->complement['routes']);
            }
        }
    }
}
