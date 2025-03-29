jQuery(document).ready(function($) {
    
    function load_svgc_captcha(){
        
		$("#SVGCaptchaContainer").load(captchaObject.ajaxurl,{
			
			action : 'svgc_captcha_reload',
			reload : 'reload'
            
		},function(response, status, xhr) {
            
			$("#SVGCaptchaLoader").css('display','none');
            
            $("#svgc-reload").css('display','inline-block');
            
			if( status == "error" ){
				
				var msg = "Sorry but there was an error: ";
				
				//console.log(msg + xhr.status + " " + xhr.statusText);
			} 
		});
    }
    
    load_svgc_captcha();
    
	$("#svgc-reload").on('click',function() {
		
        $("#svgc-reload").css('display','none');
        
        $("#SVGCaptchaContainer svg").css('display','none');
       
        $("#SVGCaptchaLoader").css('display','inline-block');
        
        load_svgc_captcha();
	});
});