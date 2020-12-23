<?php


namespace phpu\facade;

use think\Facade;

class ThinkCaptcha extends Facade
{
    protected static function getFacadeClass()
    {
        return \phpu\ThinkCaptcha::class;
    }

}