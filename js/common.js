var _autosend = _autosend || []; 
	(function(){
		var a,b,c;a=function(f){return function(){
			_autosend.push([f].concat(Array.prototype.slice.call(arguments,0)))
		}};b=["identify", "track", "cb"];for(c=0;c<b.length;c++){_autosend[b[c]]=a(b[c])};
 
		var as = document.createElement('script');
		as.type = 'text/javascript';
		as.async = true;
		as.id = 'asnd-tracker';
		as.setAttribute('data-auth-key', autosend_js.key);
		as.src = 'https://app.autosend.io/static/js/v1/autosend.js';
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(as, s);
	})();
	
	if (autosend_js.user.user_id) {
		
		_autosend.identify({
			id : autosend_js.user.user_id, 
			email : autosend_js.user.email, 
			name : autosend_js.user.name,
			phone : autosend_js.user.phone
		});
	}
function validateEmail(email) { 
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
}

jQuery(function($){
	var formEvent = {
		'submit': function(form) {
			$form = $('#'+form.id);
			var formID = form.id;
			var submited = false;

			if(typeof autosend_js.user == "object" && autosend_js.user.user_id) {
				$form.on('submit', function(e) {
					_autosend.track(formID+'-submit');
				});
			}
			else {
				$form.on('submit', function(e) {
					if (submited) {
						return;
					}
					e.preventDefault();
					var $form = $(this);
					submited = true;
					
					var userData = { 'action'	: 'identity_user' };
					
					$form.find('input, select, textarea').each(function() {
						var name = $(this).attr('name');

						switch (name) {
							case form.phone:
								userData.phone = $(this).val();
								break;
							case form.email:
								userData.email = $(this).val();
								break;
							case form.name:
								userData.name = $(this).val();
								break;
							default:
								if ($(this).attr('type') != 'hidden' && $(this).attr('type') != 'submit') {
									userData[name] = $(this).val();
								}
						}
					});

					if (userData.email || userData.phone) {
						sendUserData(userData, formID, function() {
							$form.submit();
						});
					} else {
							$form.submit();
					}
				});
			}		
		},
		'view':  function(form) {
			_autosend.track(form.id+'-view');
			
			
		},
		'c_button':  function(form) {
			$form = $('#'+form.id);
			
			$form.on('click', 'button, input[type="button"]', function() {
				_autosend.track(form.id+'-click_button');
			});
			
		},
		'c_link':  function(form) {
			$form = $('#'+form.id);
		
			$form.on('click', 'a', function() {
				_autosend.track(form.id+'-click_link');
			});
		}
	};
	
	function sendUserData(userData, formID, cb) {
		$.ajax({
			url: autosend_js.ajaxurl,
			type: "POST",
			data: userData,
			dataType: "json",
			complete:  function(resp) {
				if (cb) {
					cb();
				}
			},
			success: function(resp) {
				if (resp.data) {
					userData.id = resp.data.user_id;
					userData.email = resp.data.email;
					userData.phone = resp.data.phone;
					userData.name = resp.data.name;

					_autosend.identify(userData);
					setTimeout(function() { _autosend.track(formID+'-submit'); }, 200);
				}
			}
		});
	}

	for(i in autosend_js.formList) {
		var form = autosend_js.formList[i];
		formEvent[form.event] && formEvent[form.event](form);
	}
});