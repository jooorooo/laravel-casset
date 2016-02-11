<?php

namespace Simexis\Casset;

class Html {

	/**
	 * @var boolean whether to render special attributes value. Defaults to true. Can be set to false for HTML5.
	 * @since 1.1.13
	 */
	public static $renderSpecialAttributesValue=true;
	
	/**
	 * @var boolean whether to close single tags. Defaults to true. Can be set to false for HTML5.
	 * @since 1.1.13
	 */
	public static $closeSingleTags=false;
	
	public static function encode($text)
	{
		if(is_array($text)) {
			$tmp = [];
			foreach($text AS $k=>$v) {
				$tmp[$k] = static::encode($v);
			}
			return $tmp;
		}
		return htmlspecialchars($text,ENT_QUOTES,'utf-8');
	}

	/**
	 * Generates an HTML element.
	 * @param string $tag the tag name
	 * @param array $htmlOptions the element attributes. The values will be HTML-encoded using {@link encode()}.
	 * If an 'encode' attribute is given and its value is false,
	 * the rest of the attribute values will NOT be HTML-encoded.
	 * Since version 1.1.5, attributes whose value is null will not be rendered.
	 * @param mixed $content the content to be enclosed between open and close element tags. It will not be HTML-encoded.
	 * If false, it means there is no body content.
	 * @param boolean $closeTag whether to generate the close tag.
	 * @return string the generated HTML element tag
	 */
	public static function tag($tag,$htmlOptions=array(),$content=false,$closeTag=true)
	{
		$html='<' . $tag . self::renderAttributes($htmlOptions);
		if($content===false)
			return $closeTag && self::$closeSingleTags ? $html.' />' : $html.'>';
		else
			return $closeTag ? $html.'>'.$content.'</'.$tag.'>' : $html.'>'.$content;
	}
	
	/**
	 * Renders the HTML tag attributes.
	 * Since version 1.1.5, attributes whose value is null will not be rendered.
	 * Special attributes, such as 'checked', 'disabled', 'readonly', will be rendered
	 * properly based on their corresponding boolean value.
	 * @param array $htmlOptions attributes to be rendered
	 * @return string the rendering result
	 */
	public static function renderAttributes($htmlOptions, $prefix = null)
	{
		static $specialAttributes=array(
			'async'=>1,
			'autofocus'=>1,
			'autoplay'=>1,
			'checked'=>1,
			'controls'=>1,
			'declare'=>1,
			'default'=>1,
			'defer'=>1,
			'disabled'=>1,
			'formnovalidate'=>1,
			'hidden'=>1,
			'ismap'=>1,
			'loop'=>1,
			'multiple'=>1,
			'muted'=>1,
			'nohref'=>1,
			'noresize'=>1,
			'novalidate'=>1,
			'open'=>1,
			'readonly'=>1,
			'required'=>1,
			'reversed'=>1,
			'scoped'=>1,
			'seamless'=>1,
			'selected'=>1,
			'typemustmatch'=>1,
		);

		if($htmlOptions===array())
			return '';

		$html='';
		if(isset($htmlOptions['encode']))
		{
			$raw=!$htmlOptions['encode'];
			unset($htmlOptions['encode']);
		}
		else
			$raw=false;

		foreach($htmlOptions as $name=>$value)
		{
			if(isset($specialAttributes[$name]))
			{
				if($value)
				{
					$html .= ' ' . $name;
					if(self::$renderSpecialAttributesValue)
						$html .= '="' . $name . '"';
				}
			}
			elseif($value)
				$html .= ' ' . $prefix . $name . '="' . ($raw ? $value : self::encode($value)) . '"';
		}

		return $html;
	}

	/**
	 * Generates a meta tag that can be inserted in the head section of HTML page.
	 * @param string $content content attribute of the meta tag
	 * @param string $name name attribute of the meta tag. If null, the attribute will not be generated
	 * @param string $httpEquiv http-equiv attribute of the meta tag. If null, the attribute will not be generated
	 * @param array $options other options in name-value pairs (e.g. 'scheme', 'lang')
	 * @return string the generated meta tag
	 */
	public static function metaTag($content,$name=null,$httpEquiv=null,$options=array())
	{
		if($name!==null)
			$options['name']=$name;
		if($httpEquiv!==null)
			$options['http-equiv']=$httpEquiv;
		$options['content']=$content;
		return self::tag('meta',$options);
	}

	/**
	 * Generates a link tag that can be inserted in the head section of HTML page.
	 * Do not confuse this method with {@link link()}. The latter generates a hyperlink.
	 * @param string $relation rel attribute of the link tag. If null, the attribute will not be generated.
	 * @param string $type type attribute of the link tag. If null, the attribute will not be generated.
	 * @param string $href href attribute of the link tag. If null, the attribute will not be generated.
	 * @param string $media media attribute of the link tag. If null, the attribute will not be generated.
	 * @param array $options other options in name-value pairs
	 * @return string the generated link tag
	 */
	public static function linkTag($relation=null,$type=null,$href=null,$media=null,$options=array())
	{
		if($relation!==null)
			$options['rel']=$relation;
		if($type!==null)
			$options['type']=$type;
		if($href!==null)
			$options['href']=$href;
		if($media!==null)
			$options['media']=$media;
		return self::tag('link',$options);
	}

	/**
	 * Links to the specified CSS file.
	 * @param string $url the CSS URL
	 * @param string $media the media that this CSS should apply to.
	 * @return string the CSS link.
	 */
	public static function cssFile($url,$media='')
	{
		return self::linkTag('stylesheet','text/css',$url,$media!=='' ? $media : null);
	}

	/**
	 * Encloses the given JavaScript within a script tag.
	 * @param string $text the JavaScript to be enclosed
	 * @param array $htmlOptions additional HTML attributes (see {@link tag})
	 * @return string the enclosed JavaScript
	 */
	public static function script($text,array $htmlOptions=array())
	{
		$defaultHtmlOptions=array(
			'type'=>'text/javascript',
		);
		$htmlOptions=array_merge($defaultHtmlOptions,$htmlOptions);
		return self::tag('script',$htmlOptions,"\n/*<![CDATA[*/\n{$text}\n/*]]>*/\n");
	}

	/**
	 * Encloses the given CSS content with a CSS tag.
	 * @param string $text the CSS content
	 * @param string $media the media that this CSS should apply to.
	 * @return string the CSS properly enclosed
	 */
	public static function css($text,$media='')
	{
		if($media!=='')
			$media=' media="'.$media.'"';
		return "<style type=\"text/css\"{$media}>\n/*<![CDATA[*/\n{$text}\n/*]]>*/\n</style>";
	}

	/**
	 * Includes a JavaScript file.
	 * @param string $url URL for the JavaScript file
	 * @param array $htmlOptions additional HTML attributes (see {@link tag})
	 * @return string the JavaScript file tag
	 */
	public static function scriptFile($url,array $htmlOptions=array())
	{
		$defaultHtmlOptions=array(
			'type'=>'text/javascript',
			'src'=>$url
		);
		$htmlOptions=array_merge($defaultHtmlOptions,$htmlOptions);
		return self::tag('script',$htmlOptions,'');
	}

	/**
	 * Generates an image tag.
	 * @param string $src the image URL
	 * @param string $alt the alternative text display
	 * @param array $htmlOptions additional HTML attributes (see {@link tag}).
	 * @return string the generated image tag
	 */
	public static function image($src,$alt='',$htmlOptions=array())
	{
		$htmlOptions['src']=$src;
		$htmlOptions['alt']=$alt;
		return self::tag('img',$htmlOptions);
	}

	/**
	 * Generates an open HTML element.
	 * @param string $tag the tag name
	 * @param array $htmlOptions the element attributes. The values will be HTML-encoded using {@link encode()}.
	 * If an 'encode' attribute is given and its value is false,
	 * the rest of the attribute values will NOT be HTML-encoded.
	 * Since version 1.1.5, attributes whose value is null will not be rendered.
	 * @return string the generated HTML element tag
	 */
	public static function openTag($tag,$htmlOptions=array())
	{
		return '<' . $tag . self::renderAttributes($htmlOptions) . '>';
	}

	/**
	 * Generates a close HTML element.
	 * @param string $tag the tag name
	 * @return string the generated HTML element tag
	 */
	public static function closeTag($tag)
	{
		return '</'.$tag.'>';
	}
	
}