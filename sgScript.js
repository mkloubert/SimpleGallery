
/* additional scripts */

jQuery(function() {
    $SimpleGallery.funcs.invokeAjax('echo', {
        'data': {
            's': 'Marcel Kloubert'
        },

        'success': function(ctx) {
            alert('ECHO: ' + ctx.data);
        }
    });
});
