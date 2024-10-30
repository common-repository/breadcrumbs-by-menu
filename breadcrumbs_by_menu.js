function bbm_setCookie(c_name, value, exdays) {
    var exdate = new Date();
    exdate.setDate(exdate.getDate() + exdays);
    var c_value = escape(value) + ((exdays == null) ? "" : "; expires=" + exdate.toUTCString());
    document.cookie = c_name + "=" + c_value;
}

function bbm_getCookie(c_name) {
    var i, x, y, ARRcookies = document.cookie.split(";");
    for (i = 0; i < ARRcookies.length; i++) {
        x = ARRcookies[i].substr(0, ARRcookies[i].indexOf("="));
        y = ARRcookies[i].substr(ARRcookies[i].indexOf("=") + 1);
        x = x.replace(/^\s+|\s+$/g, "");
        if (x == c_name) {
            return unescape(y);
        }
    }
}

jQuery(document).ready(function(){
	jQuery('LI[id].menu-item a:link').each(function(i){
	     jQuery(this).click(function(e){
		    e.preventDefault();
			bbm_setCookie('nav_menuitem_id',jQuery(this).parent().attr('id').replace('menu-item-',''),1);
			window.location.href = jQuery(this).attr('href');
		 });
	});
});