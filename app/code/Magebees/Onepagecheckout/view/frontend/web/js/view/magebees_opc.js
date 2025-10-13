require(
	['jquery','Magento_Ui/js/modal/modal'],
		function($,modal)
		{
			var options = {
				type: 'popup',
				responsive: true,
				innerScroll: true,
				buttons: [{
					text: $.mage.__('Close'),
					class: 'opcloginpopup',
					click: function () {
						this.closeModal();
					}
				}]
			};
			var popup = modal(options, $('#popup-login'));
			$("#opcLogin").on('click',function(){ 
				$("#popup-login").modal("openModal");
			});
			$("#forgotform").on('click',function(){ 
				$("#Onepagecheckout-forgot-popup").show();
				$("#Onepagecheckout-login-popup").hide();
			}); 
			$("#loginback").on('click',function(){ 
				$("#Onepagecheckout-forgot-popup").hide();
				$("#Onepagecheckout-login-popup").show();
			}); 
			var url = $("#forgoturl").val();
			
			$('#forgot_form').on('click', '#reset_password', function(event){
				var email = document.getElementById("osc_email_address").value;
				if(!email){
					alert("Please enter your email.");
					return;
				}				
				if(isEmail(email)) { 
					$.ajax({
						url : url,
						data: { email : email },
						dataType: 'json',
						type: 'post',
						showLoader:true,
						success: function(data){
							$('#result').html(data);
						}
					});
				}else{
					alert("Please enter a valid email address (Ex: johndoe@domain.com).");
					return false;
				}				
			});
		}		
    );
	function isEmail(email) {
	  var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
	  return regex.test(email);
	}
	
	require(['jquery'],
	function(jQuery) {
		jQuery("#mg-login-form").submit(function(e) {
			jQuery(".opcerrwrap").hide();
			if(jQuery("#mg-login-form #email").val() == ""){
				return;
			}
			if(jQuery("#mg-login-form #pass").val() == ""){
				return;
			}
			if(jQuery('#mg-login-form #captcha_user_login').length)
			{
				if(jQuery("#mg-login-form #captcha_user_login").val() == ""){
					return;
				}
			}
			if(jQuery('#mg-login-form #g-recaptcha-response').length)
			{
				if(jQuery("#mg-login-form #g-recaptcha-response").val() == ""){
					return;
				}
			}
			
			e.preventDefault();
			var form = jQuery(this);
			var url = form.attr('action');
			jQuery.ajax({
				type: "POST",
				url: url,
				data: form.serialize(),
				showLoader: true,
				success: function(response)
				{
					if(response.error == true){
						if(response.type == "captcha"){
							setTimeout(function(){ 
								jQuery( "#Onepagecheckout-login-popup .captcha-reload" ).trigger( "click" );
							}, 3000);
						}
						jQuery(".opcerrormsg").html(response.message);
						jQuery(".opcerrwrap").show();
					}else{
						location.reload();
					}
				}
			 });
		});
	});