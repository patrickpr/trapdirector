
// **************** Message display

(function (Icinga) {

    var TrapDirector= function (module) {
        this.module = module;

        this.initialize();

        this.module.icinga.logger.debug('Trapdirector module loaded');
    };

    TrapDirector.prototype = {

        initialize: function () {
        },

        displayWarning: function(message) {
            $("#footer > #notifications").html('<li class="error">'+message+'</li>');
            $('#notifications li').addClass('fading-out').delay(6000).fadeOut('slow',function() { $(this).remove(); });
	            
        },
        
        displayOK : function(message)
        {
            $("#footer > #notifications").html('<li class="success">'+message+'</li>');
            $('#notifications li').addClass('fading-out').delay(3000).fadeOut('slow',function() { $(this).remove(); });
        },
        
        ajaxCall (url, input, success, error_returned , error_server)
		{
			success = (success !== undefined && success !== null) ? 
				success 
				: function(data) { TrapDirector.prototype.displayOK('Done'); } ;
			
			error_returned = (error_returned !== undefined && error_returned !== null) ? 
				error_returned
				: function(data) { TrapDirector.prototype.displayWarning("Error : "+ data.status); };
			error_server  = (error_server !== undefined && error_server !== null) ? 
				error_server : error_returned;

			$.ajax({
					url: url,
					async: true,
					dataType: "json",
					type: "POST",
					data: input,
			        success: function (data) 
					{
						if (data.status == "OK") 
						{
							success(data);
						} 
						else 
						{
							error_returned(data);		
						}
					},
					error: function (data)
					{
						error_returned(data);
					}
			});						
			
		}

              
    };

    Icinga.availableModules.trapdirector = TrapDirector;

}(Icinga));


