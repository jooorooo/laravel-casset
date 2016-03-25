<?php

namespace Simexis\Casset;

class Encode {

	private static $value;

	public function __construct($value = null) {
		static::$value = $value;
	}

	public function __toString() {
		return static::encode ( static::$value );
	}

	public static function quote($js, $forUrl = false) {
		if ($forUrl) {
			return strtr ( $js, array (
					'%' => '%25',
					"\t" => '\t',
					"\n" => '\n',
					"\r" => '\r',
					'"' => '\"',
					'\'' => '\\\'',
					'\\' => '\\\\',
					'</' => '<\/' 
			) );
		} else {
			return strtr ( $js, array (
					"\t" => '\t',
					"\n" => '\n',
					"\r" => '\r',
					'"' => '\"',
					'\'' => '\\\'',
					'\\' => '\\\\',
					'</' => '<\/' 
			) );
		}
	}
	public static function encode($value) {
		if (is_string ( $value )) {
			if (strpos ( $value, 'js:' ) === 0) {
				return substr ( $value, 3 );
			} else {
				return "'" . static::quote ( $value ) . "'";
			}
		} else if ($value === null)
			return 'null';
		else if (is_bool ( $value )) {
			return $value ? 'true' : 'false';
		} else if (is_integer ( $value ))
			return "$value";
		else if (is_float ( $value )) {
			if ($value === - INF) {
				return 'Number.NEGATIVE_INFINITY';
			} else if ($value === INF) {
				return 'Number.POSITIVE_INFINITY';
			} else {
				return "$value";
			}
		} else if (is_object ( $value )) {
			return static::encode ( get_object_vars ( $value ) );
		} else if (is_array ( $value )) {
			$es = array ();
			if (($n = count ( $value )) > 0 && array_keys ( $value ) !== range ( 0, $n - 1 )) {
				foreach ( $value as $k => $v )
					$es [] = "'" . static::quote ( $k ) . "':" . static::encode ( $v );
				return '{' . implode ( ',', $es ) . '}';
			} else {
				foreach ( $value as $v )
					$es [] = static::encode ( $v );
				return '[' . implode ( ',', $es ) . ']';
			}
		} else {
			return '';
		}
	}
}