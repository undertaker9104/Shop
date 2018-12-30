<?php
/**
 * Created by PhpStorm.
 * User: under
 * Date: 2018/12/5
 * Time: 下午 08:25
 */
function route_class() {
    return str_replace('.','-',Route::currentRouteName());
}

function big_number($number, $scale = 2) {
    return new \Moontoast\Math\BigNumber($number,$scale);
}