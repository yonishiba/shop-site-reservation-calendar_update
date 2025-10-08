<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * テンプレートエンジン
 */
class Tmpl {
    private $vars = array();
    public function __set($name, $value) {
        $this->vars[$name] = $value;
    }
    public function __get($name) {
        return array_key_exists($name, $this->vars) ? $this->vars[$name] : null;
    }
    public function __isset($name) {
        return isset($this->vars[$name]);
    }
    function render($tplFile) {
        $_ = $this;
        include(RCAL_DIR . "templates/{$tplFile}");
    }
}