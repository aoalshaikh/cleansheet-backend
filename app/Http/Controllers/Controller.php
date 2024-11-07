<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function middleware($middleware, array $options = [])
    {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = [
                'middleware' => $m,
                'options' => &$options
            ];
        }

        return new class($options) {
            protected $options;

            public function __construct(&$options)
            {
                $this->options = &$options;
            }

            public function only($only)
            {
                $this->options['only'] = is_array($only) ? $only : func_get_args();
                return $this;
            }

            public function except($except)
            {
                $this->options['except'] = is_array($except) ? $except : func_get_args();
                return $this;
            }
        };
    }
}
