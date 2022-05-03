<?php

	/**
	 * Returns HTML string containing the opening <em>form</em> tag
	 * with <em>action</em> attribute pointing to the current page. 
	 * The following code outputs the <em>form</em> tag with specified <em>id</em> attribute.
	 * <pre>
	 * <?= open_form(array('id'=>'my_form')) ?>
	 * ...
	 * </pre>
	 * The default value for the <em>enctype</em> attribute is <em>multipart/form-data</em> The default value for the <em>method</em>
	 * attribute is <em>post</em>. Use the <em>$attributes</em> parameter to override the default values.
	 *
	 * The function also creates a hidden input element with name <em>ls_session_key</em> inside the form.
	 * It is required for some built-in page actions. Below is a default generated markup string:
	 * <pre>
	 * <form enctype="multipart/form-data" action="{current page url}" method="post">
	 *   <input type="hidden" name="ls_session_key" value="{current session key}"/>
	 * </pre>
	 *
	 * @documentable
	 * @package cms.helpers
	 * @see close_form()
	 * @author LSAPP - MJMAN
	 * @param array $attributes an optional associative array of attributes for the <em>form</em> tag.
	 * @return string returns HTML markup for the form element.
	 */
	function open_form($attributes = array())
	{
		$attributes = array_merge(array(
			'id'=>null,
			'onsubmit'=>null,
			'enctype'=>'multipart/form-data'
		), $attributes);

		$result = Phpr_Form::open_tag($attributes);
		$session_key = post('ls_session_key', uniqid('lsk', true));
		$result .= "\n".'<input type="hidden" name="ls_session_key" value="'.h($session_key).'"/>';
		
		return $result;
	}
	
	if(!function_exists('close_form')) {
		/**
		 * Returns HTML string containing the closing <em>form</em> tag. 
		 * Using this function is not required. You can use it together with 
		 * {@link open_form()} function to avoid open/close tag mismatch error messages in IDEs. 
		 * The following code opens and closes a form.
		 * <pre>
		 * <?= open_form() ?>
		 * ...
		 * <?= close_form() ?>
		 * </pre>
		 *
		 * @documentable
		 * @package cms.helpers
		 * @see open_form()
		 * @author LSAPP - MJMAN
		 * @return string returns HTML markup for the closing form tag.
		 */
		function close_form() {
			$result = Phpr_Form::close_tag();
			return $result;
		}
	}
	
	/**
	 * Returns a message previously set by a page action. 
	 * Flash messages are designed for passing messages from action to pages (partials and layouts), 
	 * for example for displaying form validation errors. 
	 * If you use standard POST method (not AJAX) for your forms, you need to place flash_message() 
	 * function above the form to display error messages generated by a page action. If flash message
	 * is not empty, it is wrapped into a paragraph element with the class attribute value <em>"flash" + message type</em>, 
	 * for example <em>&lt;p class="flash error"&gt;</em>. Possible values for the message types are <em>error</em> 
	 * and <em>success</em>. If the flash message is empty, the function returns an empty string.
	 * 
	 * If the <em>flash_partial</em> variable is presented in the POST array, the function renders a partial 
	 * instead of returning the message string. Please read the 
	 * {@link https://lsdomainexpired.mjman.net/docs/lemonstand_front_end_javascript_framework LSAPP front-end JavaScript framework} article 
	 * to learn about the flash_partial feature.
	 *
	 * The following code outputs the form tag and a flash message.
	 * <pre>
	 * <?= open_form() ?>
	 * <?= flash_message() ?>
	 * ...
	* </pre>
	 * @documentable
	 * @package cms.helpers
	 * @see https://lsdomainexpired.mjman.net/docs/handling_errors/ Handling errors
	 * @see https://lsdomainexpired.mjman.net/docs/lemonstand_front_end_javascript_framework LSAPP front-end JavaScript framework
	 * @author LSAPP - MJMAN
	 * @return string returns the flash message HTML markup or empty string.
	 */
	function flash_message()
	{
		if (array_key_exists('system', Phpr::$session->flash->flash))
		{
			$system_message = Phpr::$session->flash['system'];

			if (strpos($system_message, 'flash_partial') !== false && !array_key_exists('error', Phpr::$session->flash->flash))
			{
				$partial_name = substr($system_message, 14);
				$success_message = array_key_exists('success', Phpr::$session->flash->flash) ? Phpr::$session->flash->flash['success'] : null;
				
				Cms_Controller::get_instance()->render_partial($partial_name, array('message'=>$success_message));

				Phpr::$session->flash->now();
				return;
			}
		}

		return Cms_Html::flash();
	}
	
	/**
	 * Inserts WYSIWYG content block into a page. 
	 * Use it for creating editable areas on pages. Please read {@link https://lsdomainexpired.mjman.net/docs/creating_editable_blocks/ Creating editable blocks}
	 * article for details about the function usage.
	 * The following code creates two content blocks on a page.
	 * <pre>
	 * <? content_block('our_goals', 'Our goals') ?>
	 * <? content_block('our_contacts', 'Our contacts') ?>
	 * </pre>
	 * @documentable
	 * @package cms.helpers
	 * @author LSAPP - MJMAN
	 * @param string $code specifies a content block code. This parameter used by LSAPP for identifying code blocks. 
	 * Content block codes can contain only digits, Latin letters and underscore characters.
	 * @param string $name specifies a content block name. Name is used as a title for WYSIWYG editor on the Edit Page Content page.
	 * @see content_block()
	 * @see text_content_block()
	 * @see https://lsdomainexpired.mjman.net/docs/creating_editable_blocks/ Creating editable blocks
	 */
	function content_block($code, $name)
	{
		echo Cms_Html::content_block($code, $name);
	}
	
	/**
	 * Inserts plain text content block into a page. 
	 * Use it for creating editable areas on pages. Please read {@link https://lsdomainexpired.mjman.net/docs/creating_editable_blocks/ Creating editable blocks}
	 * article for details about the function usage.
	 * The following code creates a plain text content block on a page.
	 * <pre>
	 * <? text_content_block('address', 'Our address') ?>
	 * </pre>
	 * @documentable
	 * @package cms.helpers
	 * @author LSAPP - MJMAN
	 * @param string $code specifies a content block code. This parameter used by LSAPP for identifying code blocks. 
	 * Content block codes can contain only digits, Latin letters and underscore characters.
	 * @param string $name specifies a content block name. Name is used as a title for a text area on the Edit Page Content page.
	 * @see content_block()
	 * @see global_content_block()
	 * @see https://lsdomainexpired.mjman.net/docs/creating_editable_blocks/ Creating editable blocks
	 */
	function text_content_block($code, $name)
	{
		echo h(Cms_Html::content_block($code, $name));
	}
	
	/**
	 * Inserts global WYSIWYG content block into a page, layout or partial. 
	 * You can manage global content blocks on the CMS/Content page. Please read the 
	 * {@link https://lsdomainexpired.mjman.net/docs/global_content_blocks Global content blocks} article for usage details.
	 * @documentable
	 * @package cms.helpers
	 * @author LSAPP - MJMAN
	 * @param string $code Specifies the content block code.
	 * Block code can contain only latin characters, numbers and signs <nobr><em>_, -, /,</em> :, and <em>.</em></nobr>
	 * @param boolean $return_content Indicates that the function should return the block content instead of outputting it to the browser.
	 * @return mixed Returns the block content if $return_content parameter value is TRUE.
	 * @see content_block()
	 * @see https://lsdomainexpired.mjman.net/docs/global_content_blocks Global content blocks
	 */
	function global_content_block($code, $return_content = false)
	{
		return Cms_Html::global_content_block($code, $return_content);
	}
	
	if(!function_exists('site_url'))
	{
		/**
		 * Returns an absolute URL to a site page specified with the parameter.
		 * <span class="note">LSAPP defines this function only if it has not been defined by another 
		 * application (for example WordPress). If the function is defined by another application, you access 
		*  LSAPP's version of the function with Cms_Html helper class: Cms_Html::site_url().</span>
		 * The function is similar to the {@link root_url()} function with the default TRUE value of $add_host_name_and_protocol
		 * parameter.
		 * The following code outputs an absolute URL of the Contacts page:
		 * <pre>Contacts page: <?= site_url('/contacts') ?></pre>
		 * You can do the same with {@link root_url() }function:
		 * <pre>Contacts page: <?= root_url('/contacts', true) ?></pre>
		 * @documentable
		 * @package cms.helpers
		 * @author LSAPP - MJMAN
		 * @see root_url()
		 * @param string $url Specifies the URL to process.
		 * @param boolean Indicates whether the URL should contain the host name and protocol.
		 * @return string Returns the absolute URL with the host and protocol name.
		 */
		function site_url($url = "", $add_host_name_and_protocol = true)
		{
			return Cms_Html::site_url($url, $add_host_name_and_protocol);
		}
	}

	/**
	 * Outputs HTML links to the JavaScript and CSS files required for the LSAPP front-end framework. 
	 * Call this function inside the HEAD element of pages where you are going to use LSAPP AJAX calls.
	 * @documentable
	 * @package cms.helpers
	 * @author LSAPP - MJMAN
	 * @deprecated Use {@link Cms_Controller::js_combine()} and {@link Cms_Controller::css_combine()} methods instead.
	 * @see https://lsdomainexpired.mjman.net/docs/combining_and_minifying_javascript_and_css_files/ Combining and minifying JavaScript and CSS resources.
	 * @see Cms_Controller::js_combine()
	 * @see Cms_Controller::css_combine()
	 * @param string $src_mode Specifies whether resource files should be included in the source code mode (not minified and not combined).
	 * @return string Returns HTML markup containing links to the resource files.
	 */
	function include_resources($src_mode = false)
	{
		return Cms_Html::include_resources($src_mode);
	}
	
	function process_ls_tags($str)
	{
		return Core_String::process_ls_tags($str);
	}

	/**
	 * Returns file URL relative to the currently active theme resources directory.
	 * This function is similar to {@link resource_url()}, but it returns URLs for 
	 * files in the active theme resources directory where as resource_url() returns
	 * URLs for files in the global resources directory.
	 *
	 * The following example outputs an image tag with <em>src</em> attribute pointing
	 * to a file in the theme resources directory.
	 * <pre><img src="<?= theme_resource_url('i/icons/rss.png') ?>"/></pre>
	 * The next code example creates a LINK element pointing to a CSS file in the active theme.
	 * <pre><link rel="stylesheet" type="text/css" href="<?= theme_resource_url('css/wiki.css) ?>" /></pre>
	 * Note that you can use resource combining methods of {@link Cms_Controller} class {@link Cms_Controller::js_combine()} 
	 * and {@link Cms_Controller::css_combine()} for creating links to CSS and JavaScript files
	 * from an active theme.
	 * 
	 * @documentable
	 * @package cms.helpers
	 * @author LSAPP - MJMAN
	 * @see resource_url()
	 * @see Cms_Controller::js_combine()
	 * @see Cms_Controller::css_combine()
	 * @param string $path Specifies a path to the file in the theme resources directory.
	 * @param boolean $root_url Determines whether the returned URL should be relative to the LSAPP domain root.
	 * @param string $add_host_name_and_protocol Indicates whether the URL should contain the host name and protocol. 
	 * This parameter works only if the $root_url parameter is true.
	 * @return string Returns the resource file URL.
	 */
	function theme_resource_url($path, $root_url = true, $add_host_name_and_protocol = false)
	{
		return Cms_Html::theme_resource_url($path, $root_url, $add_host_name_and_protocol);
	}
	
	/**
	 * Returns resource file URL relative to the website resources directory.
	 * The default resources directory location is <em>/resources</em>, but it can be changed
	 * on <em>System/Settings/CMS Settings</em> page. This function resolves resource
	 * file URLs taking into account the real location of the resources directory.
	 *
	 * The following example outputs an image tag with <em>src</em> attribute pointing
	 * to a file in the resources directory. Using the resource_url() function guarantees
	 * that the image is displayed even if the resources directory location is changed.
	 * <pre><img src="<?= resource_url('i/lsapp_logo.png') ?>"/></pre>
	 * @documentable
	 * @package cms.helpers
	 * @author LSAPP - MJMAN
	 * @see theme_resource_url()
	 * @param string $path Specifies a path to the file in the resources directory.
	 * @param boolean $root_url Determines whether the returned URL should be relative to the LSAPP domain root.
	 * @param string $add_host_name_and_protocol Indicates whether the URL should contain the host name and protocol. 
	 * This parameter works only if the $root_url parameter is true.
	 * @return string Returns the resource file URL.
	 */
	function resource_url($path, $root_url = true, $add_host_name_and_protocol = false)
	{
		return Cms_Html::resource_url($path, $root_url, $add_host_name_and_protocol);
	}

?>