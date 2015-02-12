<style>
	.error_field {
		color: red;
	}
	
	#form_list.form-table td {
		vertical-align: top;
		position: relative;
	}
	
	#form_list th {
		padding-top: 15px;
	}
	
	.form_name input {
		margin: 0 8px;
	}
	
	.binder_bar {
		position: absolute;
		overflow: hidden;
		background: #fff;
		padding: 10px  10px 10px 10px;
		border-radius: 8px;
		top: 0px;
		z-index: 1000;
	}
	
	.binder_bar a {
	    display: block;
		text-align: right;
		margin-top: 3px;
		cursor: pointer;
		position: relative;
	}
	
	.binder_bar a img {
		display: none;
		position: absolute;
		left: 0px;
	}
</style>
<div class="wrap">
<h2>Autosend Form Tracking</h2>
<form enctype="multipart/form-data" method="post" action="<?php echo admin_url()?>admin-post.php">
<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row">
				<label for="pdf_file">Autosend APP key</label>
			</th>
			<td >
				<input type="text" name="autodesk_key" value="<?php echo $key;?>">
				<input type="button" name="set_key" id="set_api_key" class="button button-default" value="Set Key">
				<img src="<?php echo admin_url("images/wpspin_light.gif")?>" id="loader_key" style="margin:0 10px; display:none">
					<img src="<?php echo admin_url("images/yes.png") ?>" id="accept_key" style="margin:0 10px; display:none">
				<p class="description" id="appkey_feedback" style="color:red"></p>
			</td>
			<td>
				<p class="description">
					You can find your APP key on
					<a href="https://app.autosend.io/integration/?i=api" target="_blank">https://app.autosend.io/integration/?i=api</a><br>
					Also you can create a new AS account <a href="https://app.autosend.io/join" target="_blank">https://app.autosend.io/join</a>
				</p>
				<p class="description">
					For step by step directions on how to use and install: <a href="http://autosend.io/faq"  target="_blank">http://autosend.io/faq</a>
				<p/>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="pdf_file">Enter a page url to get forms</label>
			</th>
			<td id="page_url_td">
				<input type="text" name="page_url" value="" placeholder='PAGE URL'>
				<input type="button" name="get_forms" id="get_forms" class="button button-default" value="Get Forms">
				<img src="<?php echo admin_url("images/wpspin_light.gif")?>" id="loader_form" style="margin:0  0 0 2px; visibility: hidden">
			</td>
			<td>
				
			</td>
		</tr>
	</tbody>
</table>
<table class="form-table" id="form_list"></table>
<script>
	jQuery(function($){
		
		var pageID = 0;
		var selectVars = {
			'submit': 'Submits Form',
			'view'	: 'Views Page',
			'c_button': 'Clicks Button',
			'c_link': 'Clicks Link'
		};
		
		var fieldBinderList = {
		};
		
		function setFormRoleBinded(role, formID) {
			if (!fieldBinderList[formID]) {
				fieldBinderList[formID] = {
					email: true,
					phone: true,
					name: true
				};
			}
			fieldBinderList[formID][role] = false;
		}
		var binderBar = null;
		
		function removeBinderBar() {
			if (binderBar) {
				binderBar.remove();
				binderBar = null;
			}
		}
		
		function getBinderBar(name, formID, cb) {
			
			if (binderBar) {
				removeBinderBar();
			}
			
			if (!fieldBinderList[formID]) {
				fieldBinderList[formID] = {
					email: true,
					phone: true,
					name: true
				};
			}
			
			var loader = $('<img src="<?php echo admin_url("images/wpspin_light.gif")?>" class="load_binding">').hide();
			var div = $('<div>', { 'class': 'binder_bar' });
			var select = $('<select>', { name: name }).on('change', function() {
				
				var value = $(this).val();
				if (!value) {
					return;
				}
				
				loader.show(); $('.error_binding').remove();
				
				var data = {
					'action'	: 'set_field_role',
					'name'		: name,
					'role'		: value,
					'form_id'	: formID,
					'page_id'	: pageID
				};
				
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: data,
					dataType: "json",
					success: function(resp) {
						loader.hide();
						if (resp.data) {
							fieldBinderList[formID] = resp.data;
							binderBar.remove();
							cb(value);
						}
						else if (resp.error) {
							binderBar.append($('<p class="error_binding">'+resp.error+'</p>'))
						}
						else {
							
							binderBar.append($('<p class="error_binding">Error has occurred.</p>'))
						}
						
					},
					error: function() {
						loader.hide()
						binderBar.append($('<p class="error_binding">Error has occurred.</p>'))
					}
				});
			});
			
			select.append( $('<option>', { value: '', html: 'Select field role' }) );
			
			for(i in fieldBinderList[formID]) {
				if (!fieldBinderList[formID][i]) {
					continue;
				}
				
				select.append( $('<option>', { value: i, html: i }) );
			}
			
			binderBar = div;
			div.append(select).append( $('<a>', { html: 'Cancel', href: '#' }).click(function() { div.remove(); binderBar = null;  return false; }).append(loader) );
			return div;
		}
		
		$('#form_list').on('click', '.bind_field', function() {
			var self = this;
			var name = $(self).attr('href').replace('#','');
		
			var formID = $(self).parents('tr').attr('id').replace('form_','');
			var tmpl = getBinderBar(name, formID , function(value) {
				$(self).html(value);
			});
			var pos = $(self).position();
			var width = $(self).width();
			$(self).closest('td').append(tmpl.css( { left: pos.left+width }));
		});
		
		$('#get_forms').on('click', function() {
			$('#loader_form').css('visibility','visible');
			$('.error_field').remove();
			$("#form_list").empty();
			var data = {
				'action': 'get_forms',
				'page_url': $('[name="page_url"]').val()
			};
			
			$.post(ajaxurl, data, function(resp) {
				$('#loader_form').css('visibility','hidden');
				if (resp.error) {
					$('#page_url_td').append('<p class="error_field">'+resp.error+'</p>')
					return;
				}
				
				var form_list = resp.data;
				
				if (!form_list) {
					$('#page_url_td').append('<p class="error_field">Forms on page was not found.</p>')
					return;
				}
				
				$formList = $('#form_list');
				pageID = resp.pageID;

				for(i in form_list) {				
					var form = form_list[i];		
					var tr = $('<tr>', { id: 'form_' + form['Form ID'] });
					var checked = (form.event) ? 'checked="checked"' : "";
					tr.append('<th class="form_name">'+form['Form ID']+'<p><label for="t-form'+i+'">Track?</label> <input type="checkbox" '+checked+' id="t-form'+i+'" class="trackform" value="'+form['Form ID']+'"></p></th>');
					tr.appendTo($formList);
					var td = $('<td>', { 'html': '<b>Form fields</b>' });
					
					for(child in form.children) {
						
						var formChild = form.children[child];
						var fText = [];
						
						fText.push(formChild.type);
						
						var bind = ""; var bndTxt = "";
						
						if (formChild.name ) {
							fText.push(formChild.name);
							
							if(formChild.role) {
								bndTxt = formChild.role;
								setFormRoleBinded(formChild.role, form['Form ID']);
							} else if(formChild.type != 'a') {
								bndTxt = 'Set field role';
							}
							
							bind = '<a href="#'+formChild.name+'" class="bind_field">'+bndTxt+'</a>';
						}
						
						td.append('<p>' + fText.join(' - ') + ' ' + bind + '</p>');
					}
					
					tr.append(td);
					
					var select = $('<select>', { name: form['Form ID']+'_event' });

					for(i in selectVars) {
						var attrs = { html: selectVars[i], value: i };
						if (i == form.event) {
							attrs.selected = "selected";
						}
						$('<option>',attrs ).appendTo(select)
					}
					
					var td = $('<td>', {html: '<b>Event</b>'}).appendTo(tr)
					$("<p>").append(select).appendTo(td);
					
					var loader = $('<img src="<?php echo admin_url("images/wpspin_light.gif") ?>" class="load_event">').hide();
					var accept = $('<img src="<?php echo admin_url("images/yes.png")?>" class="loaded_event">').hide();
					
					select.on('change', function() {
						var self = $(this);
						$('.error_event').remove();
						$('.loaded_event').hide();
						
						var data = {
							'action'	: 'set_event',
							'event'		:  $(this).val(),
							'form_id'	:  $(this).attr('name').replace('_event', ''),
							'page_id'	: pageID,
						};
						
						ldr = self.parent().find('.load_event').show();
						
						$.ajax({
							url: ajaxurl,
							type: "POST",
							data: data,
							dataType: "json",
							success: function(resp) {
								ldr.hide();
								if (resp.data) {
									self.parent().find('.loaded_event').show();
								}
								else
									self.after($('<p class="error_event">Form is not tracked.</p>'))
								
							},
							error: function() {
								ldr.hide();
								self.after($('<p class="error_event">Error has occurred.</p>'))
							}
						});
						
					}).after(loader)
						.after(accept);
				}
			}, 'json');
		});
		
		$('#set_api_key').on('click', function() {
			var key = $('[name="autodesk_key"]').val();

			var data = {
				'action'	: 'set_key',
				'key'		: key
			};
			$('#loader_key').show();
			$.ajax({
				url: ajaxurl,
				type: "POST",
				data: data,
				dataType: "json",
				success: function(resp) {
					$('#loader_key').hide();
					$('#accept_key').show();
				},
				error: function() {
					$('#loader_key').hide();
					$('#appkey_feedback').html('Error has occurred.')
				}
			})
		});
		
		$('#form_list').on('click', '.trackform', function() {
			var formID = $(this).val();
			var track = ($(this).attr('checked')) ? 1 : 0 ;
			var data = {
				'action'	: 'set_form',
				'form_id'	: formID,
				'track'		: track,
				'page_id'	: pageID,
				'event'		: $('#form_'+formID).find('select[name="'+formID+'_event"]').val()
			};
			
			removeBinderBar();	
			
			$.post(ajaxurl, data, function(resp) {
				
			},'json')
		});
	})
</script>