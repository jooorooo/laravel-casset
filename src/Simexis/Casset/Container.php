<?php

namespace Simexis\Casset;

use Closure;
use Less_Parser;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class Container
{
	/**
	 * Container name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Public path.
	 *
	 * @var string
	 */
	public $public_path;

	/**
	 * Assets path.
	 *
	 * @var string
	 */
	public $assets_path;

	/**
	 * Compile path.
	 *
	 * @var string
	 */
	public $cache_path;

	/**
	 * Casset controller route.
	 *
	 * @var string
	 */
	public $route;

	/**
	 * Whether or not to combine resources into a single file.
	 *
	 * @var boolean
	 */
	public $combine;

	/**
	 * Whether or not to minify resources (combined resources only).
	 *
	 * @var boolean
	 */
	public $minify;

	/**
	 * URL of CDN to use for assets.
	 *
	 * @var string
	 */
	public $cdn;

	/**
	 * Version number to append to each controller URL.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * All of the registered assets.
	 *
	 * @var array
	 */
	public $assets = array();

	/**
	 * Array of global dependencies which all assets are checked against.
	 *
	 * @var array
	 */
	public $dependencies = array();

	/**
	 * Local cache of all assets and whether or not they needed processing.
	 *
	 * @var array
	 */
	protected static $needs_processing = array();

	/**
	 * Local cache of all assets that have already been processed.
	 *
	 * @var array
	 */
	protected static $processed = array();

	/**
	 * Local cache of paths.
	 *
	 * @var array
	 */
	protected static $cached_paths = array();

	public $collections = array();
	public $show_refer;

	/*
	 * array
	 */
	protected $parameters = [];

	/**
	 * Initialize an instance of this class.
	 *
	 * @param string $name Name of container.
	 *
	 * @return void
	 */
	public function __construct($name)
	{
		$this->name    = $name;
		$this->route   = Config::get('casset.route');
		$this->combine = Config::get('casset.combine', true);
		$this->minify  = Config::get('casset.minify', true);
		$this->cdn     = rtrim(Config::get('casset.cdn', ''), '/');

		$this->public_path = public_path();
		$this->assets_path = $this->public_path
			. '/'
			. trim(Config::get('casset.assets_dir', 'assets'), '/');
		$this->cache_path  = $this->public_path
			. '/'
			. trim(Config::get('casset.cache_dir', 'assets/cache'), '/');

		$this->version = Config::get('casset.controller_version');
		if ($this->route && $this->version && $this->version instanceof Closure) {
			$v = $this->version;
			$this->version = (string) $v();
		}

		$this->collections = Config::get('casset.collections', array());
		$this->show_refer  = Config::get('casset.show_refer', true);
	}

	/**
	 * Add an asset (of any type) to the container.
	 *
	 * Accepts a source relative to the configured 'assets_dir'.
	 *   eg: 'js/jquery.js'
	 *
	 * Also accepts a source relative to a package.
	 *   eg: 'package::js/file.js'
	 *
	 * @param string $source       Relative path to file.
	 * @param array  $attributes   Attribuets array.
	 * @param array  $dependencies Dependencies array.
	 *
	 * @return Container
	 */
	public function add($source, array $attributes = array(), array $dependencies = array())
	{

		if( is_array($source) )
		{
			$sources = $source;
			foreach($sources AS $source)
			{
				if( isset($this->collections[$source]) ) $source = $this->collections[$source];
				$this->add($source, $attributes, $dependencies);
			}
		}
		else
		{
			if( isset($this->collections[$source]) ) $source = $this->collections[$source];

			if( is_array($source) )
			{
				$this->add( $source, $attributes, $dependencies );
			}
			else
			{
				$ext = pathinfo($source, PATHINFO_EXTENSION);
				$this->assets[$source] = compact('ext', 'source', 'attributes', 'dependencies');
			}
		}

		return $this;
	}

	/*
	 * Add inline javascript parameters
	 */
	public function params(array $parameters) {
		$this->parameters = array_merge($this->parameters, $parameters);
	}

	/*
	 * Return empty string for error ignore
	 */
	public function __toString() {
		return '';
	}

	/**
	 * Get the Html links to all of the registered CSS assets.
	 *
	 * @return string
	 */
	public function styles()
	{
		$assets = array();
		$assets_external_cdn = array();

		foreach ($this->assets as $asset) {
			if ('css' !== $asset['ext'] && 'less' !== $asset['ext']) {
				continue;
			}

			if( strpos($asset['source'],'http:') !== false || substr($asset['source'],0,2) == '//' )
			{
				$assets_external_cdn[] = $asset;
			}
			else
			{
				$assets[] = $this->route ? $asset : $this->process($asset);
			}

		}

		if (empty($assets) && empty($assets_external_cdn)) {
			return '';
		}

		if ($this->route) {
			return $this->prepareForController(array_merge($assets,$assets_external_cdn), 'style');
		}

		$links = array();
		if( ! empty($assets_external_cdn) )
		{
			foreach($assets_external_cdn as $asset)
			{
				$links[] = Html::cssFile($asset['source'], $asset['attributes']);
			}
		}

		if( ! empty($assets) )
		{
			if ($this->combine) {
				$assets = $this->combine($assets, 'style');
			}

			foreach ($assets as $asset) {
				$url = $this->cdn ? $this->cdn . $asset['url'] : asset($asset['url']);
				$links[] = Html::cssFile($url, $asset['attributes']);
			}
		}

		return implode('', $links);
	}

	/**
	 * Get the Html links to all of the registered JavaScript assets.
	 *
	 * @return string
	 */
	public function scripts()
	{
		$assets = array();
		$assets_external_cdn = array();

		foreach ($this->assets as $asset) {
			if ('js' !== $asset['ext']) {
				continue;
			}

			if( strpos($asset['source'],'http:') !== false || substr($asset['source'],0,2) == '//' )
			{
				$assets_external_cdn[] = $asset;
			}
			else
			{
				$assets[] = $this->route ? $asset : $this->process($asset);
			}
		}

		if (empty($assets) && empty($assets_external_cdn)) {
			return '';
		}

		if ($this->route) {
			return $this->prepareForController(array_merge($assets,$assets_external_cdn), 'script');
		}

		$scripts = array();

        if($this->parameters) {
            $globals = $objects = [];
            foreach($this->parameters AS $key => $data) {
                if(strpos($key, '.')) {
                    $p = explode('.', $key);
                    array_pop($p);
                    $elm = '';
                    foreach($p AS $e=>$n) {
                        $elm .= ($elm?'.':'') . $n;
                        if($e) {
                            $objects[] = $elm . " = " . Encode::encode('js:' . $elm . ' || {}');
                        } else {
                            $globals[] = $elm . " = " . Encode::encode('js:' . $elm . ' || {}');
                        }
                    }
                    $objects[] = $key . " = " . Encode::encode($data);
                } else {
                    $globals[] = $key . " = " . Encode::encode($data);
                }
            }
            if($globals)
                $scripts[] = Html::script('var ' . implode(",\n", $globals) . ';');
            if($objects)
                $scripts[] = Html::script(implode(",\n", $objects) . ';');
        }

		if( ! empty($assets_external_cdn) )
		{
			foreach($assets_external_cdn as $asset)
			{
                $scripts[] = Html::scriptFile($asset['source'], $asset['attributes']);
			}
		}

		if( ! empty($assets ) )
		{
			if ($this->combine) {
				$assets = $this->combine($assets, 'script');
			}

			foreach ($assets as $asset) {
				$url = $this->cdn ? $this->cdn . $asset['url'] : asset($asset['url']);
                $scripts[] = Html::scriptFile($url, $asset['attributes']);
			}
		}

		return implode('', $scripts);
	}

	/**
	 * Prepare the given assets to be rendered to call the Casset controller
	 * and return the Html link to that resource.
	 *
	 * @param array  $assets
	 * @param string $type
	 *
	 * @return string
	 */
	public function prepareForController($assets, $type)
	{
		$controller_url = $this->cdn ? $this->cdn . '/' : '/';
		$controller_url .= $this->route . '/' . $type;
		$controller_url .= '?c=' . urlencode($this->name);

		$links = array();

		foreach ($assets as &$asset) {
			$attributes = $asset['attributes'];
			unset($asset['attributes']);
			unset($asset['ext']);

			if (empty($asset['dependencies'])) {
				unset($asset['dependencies']);
			}

			if (!$this->combine) {
				if( ! isset($asset['mark_external_cdn']) )
				{
					$url = $controller_url . '&files=' . base64_encode(json_encode(array($asset)));
					$url .= $this->version ? '&v=' . $this->version : '';
				}
				else
				{
					$url = $asset['source'];
				}

				if ('style' == $type) {
					$links[] = Html::cssFile($url, $attributes);
				}
				else {
					$links[] = Html::scriptFile($url, $attributes);
				}
			}
		}

		if ($this->combine)
		{
			//filter external cnd
			foreach($assets as $key => $asset)
			{
				if( isset($asset['mark_external_cdn']) )
				{
					$url = $asset['source'];
					$links[] = ('style' == $type) ? Html::cssFile($url) : Html::scriptFile($url);
					unset($assets[$key]);
				}
			}

			$url = $controller_url . '&files=' . base64_encode(json_encode($assets));
			$url .= $this->version ? '&v=' . $this->version : '';

			if ('style' == $type) {
				$links[] = Html::cssFile($url);
			}
			else {
				$links[] = Html::scriptFile($url);
			}
		}

		return implode('', $links);
	}

	/**
	 * Process and return the contents for this container for the
	 * requested file type.
	 *
	 * @param string $type 'style' or 'script'
	 *
	 * @return string
	 */
	public function content($type)
	{
		$assets = array();

		foreach ($this->assets as $asset) {
			if ('style' == $type && 'css' !== $asset['ext'] && 'less' !== $asset['ext']) {
				continue;
			}
			else if ('script' == $type && 'js' !== $asset['ext']) {
				continue;
			}

			$assets[] = $this->process($asset);
		}

		if (empty($assets)) {
			return '';
		}

		if (count($assets) > 1 || $this->minify) {
			$assets = $this->combine($assets, $type);
		}

		$content = array();

		foreach ($assets as $asset) {
			$content[] = File::get(array_get($asset, 'path'));
		}

		return implode("\n\n", $content);
	}

	/**
	 * Add an image asset to the container.
	 *
	 * @param string $source
	 * @param string $alt
	 * @param array  $attributes
	 *
	 * @return string
	 */
	public function image($source, $alt = null, $attributes = array())
	{
		$url = $source;

		if (false === strstr($source, '://') && '//' !== substr($source, 0, 2)) {
			$url = $this->cdn($source);
		}

		return Html::image($url, $alt, $attributes);
	}

	/**
	 * Get the URL to the CDN for an asset.
	 *
	 * @param string $source
	 *
	 * @return string
	 */
	public function cdn($source)
	{
		$url = str_ireplace($this->public_path, '', $this->assets_path . '/' . ltrim($source, '/'));
		$url = $this->cdn ? $this->cdn . $url : $url;
		return $url;
	}

	/**
	 * Add a global dependency.
	 *
	 * @param string $source Relative path to file.
	 *
	 * @return Container
	 */
	public function dependency($source)
	{
		$this->dependencies[pathinfo($source, PATHINFO_EXTENSION)][] = $source;
	}

	/**
	 * Get the full path to the given asset source. Will try to load from a
	 * package/workbench if prefixed with: "{package_name}::".
	 *
	 * @param string $source Asset source.
	 *
	 * @return string
	 */
	public function path($source)
	{
		if (false === stristr($source, '::')) {
			return $this->assets_path . '/' . ltrim($source, '/');
		}

		$source_parts = explode('::', $source);
		$package_name = current($source_parts);

		// Is this relative to the public dir?
		$path = '/public/' . ltrim(end($source_parts), '/');
		if ('/' === substr(end($source_parts), 0, 1)) {
			$path = end($source_parts);
		}

		// Check local cache first.
		if (array_key_exists($package_name, static::$cached_paths)) {
			return static::$cached_paths[$package_name] . $path;
		}

		$finder = \Symfony\Component\Finder\Finder::create();

		// Try to find in workbench, first.
		$workbench = base_path() . '/workbench';
		if (file_exists($workbench . '/' . $package_name . $path)) {
			return $workbench . '/' . $package_name . $path;
		}

		// Use package path.
		$vendor = base_path() . '/vendor';
		return $vendor . '/' . $package_name . $path;
	}

	/**
	 * Return the public path to the given asset.
	 * If the file is not in the public directory,
	 * or if it needs to be compiled (less, etc...), then the
	 * public cache path is returned.
	 *
	 * @param array $asset Asset array.
	 *
	 * @return string
	 */
	public function publicPath(array $asset)
	{
		$path          = $this->path($asset['source']);
		$is_public     = (bool) stristr($path, $this->public_path);
		$compiled_exts = array('less');

		if ($is_public && !in_array($asset['ext'], $compiled_exts)) {
			return $path;
		}

		$cache_path = $this->cache_path
			. '/'
			. str_replace(array('/', '::'), '-', $asset['source']);

		$cache_path .= ('less' === $asset['ext']) ? '.css' : '';

		return $cache_path;
	}

	/**
	 * Returns whether or not a file needs to be processed.
	 *
	 * @param array $asset Asset array.
	 *
	 * @return boolean
	 */
	public function needsProcessing(array $asset)
	{
		if (isset(static::$needs_processing[$asset['source']])) {
			return static::$needs_processing[$asset['source']];
		}

		$path          = $this->path($asset['source']);
		$is_public     = (bool) stristr($path, $this->public_path);
		$compiled_exts = array('less');

		// Any dependencies that need processing?
		$dependencies = isset($asset['dependencies']) ? $asset['dependencies'] : array();
		$dependencies = array_unique(array_merge(array_get($this->dependencies, $asset['ext'], array()), $dependencies));
		if (!empty($dependencies)) {
			foreach ($dependencies as $dep_source) {
				if (!empty(static::$needs_processing[$dep_source])) {
					return static::$needs_processing[$asset['source']] = true;
				}
			}
		}

		// This file does not require processing.
		if ($is_public && !in_array($asset['ext'], $compiled_exts)) {
			return static::$needs_processing[$asset['source']] = false;
		}

		$cache_path = $this->publicPath($asset);

		// Does file exist?
		if (!File::exists($cache_path)) {
			return static::$needs_processing[$asset['source']] = true;
		}

		// Is cached file newer than the original?
		if (File::lastModified($cache_path) >= File::lastModified($path)) {
			return static::$needs_processing[$asset['source']] = false;
		}

		// Check md5 to see if content is the same.
		if ($f = fopen($cache_path, 'r')) {
			$line = (string) fgets($f);
			fclose($f);

			if (false !== strstr($line, '*/')) {
				$md5 = trim(str_replace(array('/*', '*/'), '', $line));

				if (32 == strlen($md5)) {
					$file_md5 = md5_file($path);

					// Skip compiling and touch existing file.
					if ($file_md5 === $md5) {
						touch($cache_path);
						return false;
					}
				}
			}
		}

		return static::$needs_processing[$asset['source']] = true;
	}

	/**
	 * Process the given asset.
	 * Make public, if needed.
	 * Compile, if needed (less, etc...).
	 *
	 * Returns a valid asset.
	 *
	 * @param array $asset Asset array.
	 *
	 * @return array [url, attributes]
	 */
	public function process(array $asset)
	{
		// Any dependencies that need processing?
		$dependencies = isset($asset['dependencies']) ? $asset['dependencies'] : array();
		$dependencies = array_unique(array_merge(array_get($this->dependencies, $asset['ext'], array()), $dependencies));
		if (!empty($dependencies)) {
			foreach ($dependencies as $dep_source) {
				if ($asset['source'] == $dep_source) {
					continue;
				}

				$this->process(array(
					'source' => $dep_source,
					'ext'    => pathinfo($dep_source, PATHINFO_EXTENSION),
				));
			}
		}

		$path        = $this->path($asset['source']);
		$public_path = $this->publicPath($asset);

		if (empty(static::$processed[$asset['source']]) && $this->needsProcessing($asset)) {
			if (File::exists($path)) {
				File::put($public_path, $this->compile($path));
			}
		}

		$asset['path'] = $public_path;
		$asset['url']  = str_ireplace($this->public_path, '', $public_path);

		static::$processed[$asset['source']] = true;

		return $asset;
	}

	/**
	 * Compile and return the content for the given asset according to it's
	 * extension.
	 *
	 * @param string $path Asset path.
	 *
	 * @return string
	 */
	public function compile($path)
	{
		switch (pathinfo($path, PATHINFO_EXTENSION)) {
			case 'less':
				$less = new Less_Parser;
				$content = '/*' . md5(File::get($path)) . "*/\n" . $less->parseFile($path)->getCss();

				break;

			default:
				$content = File::get($path);
		}

		return $content;
	}

	/**
	 * Combine the given array of assets. Minify, if enabled.
	 * Returns new array containing one asset.
	 *
	 * @param array  $assets Array of assets.
	 * @param string $type   File type (script, style).
	 *
	 * @return array
	 */
	public function combine(array $assets, $type)
	{
		$paths = array();
		$lastmod = 0;
		foreach ($assets as $asset) {
			$paths[] = $asset['path'];
			$mod = @File::lastModified($asset['path']);
			if ($mod > $lastmod) {
				$lastmod = $mod;
			}
		}

		if( $this->show_refer )
			$file = $this->cache_path . '/casset-' . md5(implode(',', $paths) . $lastmod) . '-' . $this->name;
		else
			$file = $this->cache_path . '/' . md5(implode(',', $paths) . $lastmod);

		$file .= ('script' === $type) ? '.js' : '.css';

		$combine = false;
		if (!File::exists($file)) {
			$combine = true;
		}
		else if (File::lastModified($file) < $lastmod) {
			$combine = true;
		}

		if ($combine) {
			$content = '';

			foreach ($assets as $asset) {
				if (!File::exists($asset['path'])) {
					continue;
				}

				$c = File::get($asset['path']);

                if($asset['ext'] == 'css' && preg_match_all('~url\(([\"\'^\)]*)?([^\)]*)([\"\'^\)]*)?\)~i', $c, $match)) {
                    foreach($match[2] AS $row=> $link) {
                        $link = UrlFormat::toAbsolute(asset($asset['url']), $link);
                        $c = str_replace($match[0][$row], 'url("' . preg_replace('~^https?:~i', '', $link) . '")', $c);
                    }
                }

				if ($this->minify
					&& !(stripos($asset['source'], '.min')
						|| stripos($asset['source'], '-min')
					)
				) {
					switch ($type) {
						case 'style':
							$min = new \CSSmin();
							$c = $min->run($c);
							#$c = Compressors\Css::process($c);
							break;

						case 'script':
							$c = \JSMin::minify($c);
							#$c = Compressors\Js::minify($c);
							break;
					}
				}

				if( $this->show_refer )
				{
					$content .= "/* {$asset['source']} */\n$c\n\n";
				}
				else
				{
					$content .= "/* --- */\n$c\n\n";
				}
			}

			File::put($file, $content);
		}

		return array(array(
			'path'       => $file,
			'attributes' => array(),
			'url'        => str_ireplace($this->public_path, '', $file),
		));
	}
}
