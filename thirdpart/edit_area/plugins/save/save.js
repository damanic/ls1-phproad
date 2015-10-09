/**
 * Adds the save button
 */  
var EditArea_save= {
	/**
	 * Get called once this file is loaded (editArea still not initialized)
	 *
	 * @return nothing	 
	 */	 	 	
	init: function(){
	}
	/**
	 * Returns the HTML code for a specific control string or false if this plugin doesn't have that control.
	 * A control can be a button, select list or any other HTML item to present in the EditArea user interface.
	 * Language variables such as {$lang_somekey} will also be replaced with contents from
	 * the language packs.
	 * 
	 * @param {string} ctrl_name: the name of the control to add	  
	 * @return HTML code for a specific control or false.
	 * @type string	or boolean
	 */	
	,get_control_html: function(ctrl_name){
		return false;
	}
	/**
	 * Get called once EditArea is fully loaded and initialised
	 *	 
	 * @return nothing
	 */	 	 	
	,onload: function(){ 
	}
	
	/**
	 * Is called each time the user touch a keyboard key.
	 *	 
	 * @param (event) e: the keydown event
	 * @return true - pass to next handler in chain, false - stop chain execution
	 * @type boolean	 
	 */
	,onkeydown: function(e){
		var str= String.fromCharCode(e.keyCode);
		// desactivate the "f" character
		if( str=="S" && (CtrlPressed(e) || this.metaPressed(e))){
			editArea.execCommand('save');

			if (e.stopPropagation) e.stopPropagation();
			else e.cancelBubble = true;

			if (e.preventDefault) e.preventDefault();
			else e.returnValue = false;
			return false;
		}
		return true;
	}
	
	,metaPressed: function (e) {
		if (window.event) {
			return (window.event.metaKey);
		} else {
			return (e.metaKey);
		}
	}
	
	/**
	 * Executes a specific command, this function handles plugin commands.
	 *
	 * @param {string} cmd: the name of the command being executed
	 * @param {unknown} param: the parameter of the command	 
	 * @return true - pass to next handler in chain, false - stop chain execution
	 * @type boolean	
	 */
	,execCommand: function(cmd, param){
		return true;
	}
};

// Adds the plugin class to the list of available EditArea plugins
editArea.add_plugin("save", EditArea_save);