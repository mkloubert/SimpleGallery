
/* additional scripts */

jQuery(function() {
    $SimpleGallery.funcs.ajax('echo', {
        'data': {
            's': 'Marcel Kloubert'
        },

        'success': function(ctx) {
            alert('ECHO: ' + ctx.data);
        }
    });
});
