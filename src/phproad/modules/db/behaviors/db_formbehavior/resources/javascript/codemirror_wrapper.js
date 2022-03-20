var phpr_active_code_mirror = null;

var CodeMirrorWrapper = new Class({
	Implements: [Options, Events],
	
	editor: null,
	textarea: null,
	fullscreen_btn: null,
	toolbar: null,
	container_element: null,
	wrapper_element: null,
	lines_width: null,
	
	normal_scroll: null,
	normal_height: null,
	
	boundResize: null,
	
	initialize: function(textarea, language, save_callback)
	{
		var textarea_element = $(textarea);
		try
		{
			function insert(frame) {
				if (textarea_element.nextSibling)
					textarea_element.parentNode.insertBefore(frame, textarea_element.nextSibling);
				else
					textarea_element.parentNode.appendChild(frame);
			}
			
			var parsers = [];
			var stylesheets = [];
			
			language = language.trim().toLowerCase();

			switch (language)
			{
				case 'js' : 
				case 'javascript' : 
					parsers.push("tokenizejavascript.js")
					parsers.push("parsejavascript.js");
					stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/css/jscolors.css"));
				break;
				case 'xml' :
					parsers.push("parsexml.js");
				    stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/css/xmlcolors.css"));
				break;
				case 'css' :
					parsers.push("parsecss.js");
			    	stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/css/csscolors.css"));
				break;
				case 'php' :
					parsers.push("parsexml.js");
					parsers.push("../contrib/php/js/tokenizephp.js");
					parsers.push("../contrib/php/js/parsephp.js");
					
					stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/contrib/php/css/phpcolors.css"));
				break;
				case 'htm' :
				case 'html' :
				default :
					parsers.push("parsexml.js");
					parsers.push("parsecss.js");
					parsers.push("tokenizejavascript.js");
					parsers.push("parsejavascript.js");
					parsers.push("parsehtmlmixed.js");
					parsers.push("../contrib/php/js/tokenizephp.js");
					parsers.push("../contrib/php/js/parsephp.js");
					parsers.push("../contrib/php/js/parsephphtmlmixed.js");
					
					stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/css/xmlcolors.css"));
					stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/css/jscolors.css"));
					stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/css/csscolors.css"));

					stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/contrib/php/css/phpcolors.css"));
				break;
			}

			stylesheets.push(ls_root_url("phproad/thirdpart/codemirror/css/common_editor_styles.css"));

			var editor = new CodeMirror(insert, {
				height: textarea_element.getSize().y+"px",
				content: textarea_element.value,
				parserfile: parsers,
				stylesheet: stylesheets,
				path: ls_root_url("phproad/thirdpart/codemirror/js/"),
				autoMatchParens: true,
				lineNumbers: true,
				undoDelay: 200,
				tabMode: 'shift',
		        textWrapping: true,
				saveFunction: function() {
					eval(save_callback+'()');
				},
				onChange: function()
				{
					phpr_editarea_changed_callback(textarea);
				},
				initCallback: function(editor_obj){
					phpr_editarea_init_callback(textarea, editor_obj, this);

					if (typeof editor_obj.frame.contentWindow.document.body.addEventListener == "function")
					{
					 	editor_obj.frame.contentWindow.document.body.addEventListener("click", this.set_active.bind(this), false);
					 	editor_obj.frame.contentWindow.document.addEventListener("click", this.set_active.bind(this), false);
					}
					else
					{
						editor_obj.frame.contentWindow.document.body.attachEvent("onclick", this.set_active.bind(this));
						editor_obj.frame.contentWindow.document.attachEvent("onclick", this.set_active.bind(this));
					}
				}.bind(this)
			});
			textarea_element.style.display = "none";
			this.editor = editor;
			this.textarea = textarea_element;
			this.create_ui();
			
			window.addEvent('phprformsave', function(){ 
				this.value = editor.getCode();
			}.bind(textarea_element));
			
			this.boundResize = this.update_size_fullscreen.bind(this);
		} catch (e){}
	},
	
	set_active: function()
	{
		phpr_active_code_mirror = this;
	},
	
	create_ui: function()
	{
		/*
		 * Create the toolbar
		 */

		var toolbar = new Element('div', {'class': 'code_editor_toolbar'}).inject(this.textarea.getParent(), 'before');
		this.toolbar = toolbar;

		/*
		 * Create buttons
		 */

		var list = new Element('ul').inject(toolbar);
		
		var search_btn = new Element('li', {'class': 'search', 'title': 'Find'}).inject(list);
		var search_btn_link = new Element('a', {'href': 'javascript:;'}).inject(search_btn);
		search_btn_link.addEvent('click', this.search.bind(this));

		this.fullscreen_btn = new Element('li', {'class': 'fullscreen', 'title': 'Toggle fullscreen mode'}).inject(list);
		var fullscreen_btn_link = new Element('a', {'href': 'javascript:;'}).inject(this.fullscreen_btn);
		fullscreen_btn_link.addEvent('click', this.fullscreen_mode.bind(this));
		
		// var textwrap_btn = new Element('li', {'class': 'textwrap', 'title': 'Toggle text wrapping'}).inject(list);
		// var textwrap_btn_link = new Element('a', {'href': 'javascript:;'}).inject(textwrap_btn);
		// textwrap_btn_link.addEvent('click', this.textwrap.bind(this));

		new Element('div', {'class': 'clear'}).inject(toolbar, 'bottom');

		/*
		 * Find UI elements
		 */

		this.container_element = $(this.editor.frame).selectParent('div.fieldContainer');
		this.wrapper_element = $((this.editor.frame).getParent());

		var lines_enabled = this.editor.options.lineNumbers;
		this.lines_width = lines_enabled ? (this.editor.frame.getParent().getElement('.CodeMirror-line-numbers').getSize().x) : 0;

		/*
		 * Create the footer 
		 */

		var footer = new Element('div', {'class': 'code_editor_footer'}).inject(this.textarea.getParent(), 'bottom');
		this.resize_handle = new Element('div', {'class': 'resize_handle'}).inject(footer, 'bottom');
		
		new Drag(this.wrapper_element, {
			'handle': this.resize_handle,
			'modifiers': {'x': '', 'y': 'height'},
			'limit': {'y': [100, 3000]},
			onDrag: function(){ 
				this.fireEvent('resize', this);
			}.bind(this),
			onComplete: function(){
				window.fireEvent('phpr_editor_resized');
			}
		});
	},
	
	textwrap: function()
	{
		if (this.container_element.hasClass('notextwrap'))
		{
			this.editor.setTextWrapping(true);
			this.container_element.removeClass('notextwrap')
		} else
		{
			this.editor.setTextWrapping(false);
			this.container_element.addClass('notextwrap')
		}
		
		return false;
	},
	
	search: function()
	{
		var text = prompt("Enter search term:", "");
		if (!text) return;

		var first = true;
		do {
			var cursor = this.editor.getSearchCursor(text, first, true);
			first = false;
			while (cursor.findNext()) {
				cursor.select();
				if (!confirm("Search again?"))
				return;
			}
		} while (confirm("End of document reached. Start over?"));

		return false;
	},
	
	fullscreen_mode: function()
	{
		if (!this.container_element.hasClass('fullscreen'))
		{
			this.normal_scroll = window.getScroll();

			document.body.setStyle('overflow', 'hidden');
			window.scrollTo(0, 0);
			
			this.container_element.addClass('fullscreen');
			this.container_element.setStyle('position', 'absolute');
			this.normal_height = $(this.wrapper_element).getStyle('height');

			this.update_size_fullscreen.delay(20, this);
			
			window.addEvent('resize', this.boundResize);
		} else
		{
			window.removeEvent('resize', this.boundResize);

			document.body.setStyle('overflow', 'visible');
			this.container_element.removeClass('fullscreen');
			this.container_element.setStyle('position', 'static');
			this.container_element.setStyle('width', 'auto');

			$(this.wrapper_element).setStyle('width', 'auto');
			$(this.wrapper_element).setStyle('height', this.normal_height);
			
			window.scrollTo(this.normal_scroll.x, this.normal_scroll.y);
		}

		return false;
	},

	update_size_fullscreen: function()
	{
		var window_size = window.getSize();

		this.container_element.setStyle('z-index', '1000');
		this.container_element.setStyle('width', '100%');
		this.container_element.setStyle('left', 0);
		this.container_element.setStyle('top', 0);

		this.wrapper_element.setStyle('width', (window_size.x-this.lines_width) + 'px');
		this.wrapper_element.setStyle('height', (window_size.y-this.toolbar.getSize().y) + 'px');
	}
})
