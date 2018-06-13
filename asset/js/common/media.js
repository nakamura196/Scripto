$(document).ready(function() {

// Handle the watchlist toggle.
var watchlist = $('.watch-list');
var watchedIcon = watchlist.children('.watchlist.button.watched');
var notWatchedIcon = watchlist.children('.watchlist.button').not('.watched');
var watching = watchlist.data('watching');

watchlist.children('.watchlist.button').on('click', function(e) {
    e.preventDefault();
    watching = (1 === watching) ? 0 : 1;
    $.post(watchlist.data('url'), {'watching': watching})
        .done(function(data) {
            watchedIcon.toggle();
            notWatchedIcon.toggle();
            if (watching) {
                watchlist.children('.watch.success').fadeIn('slow').delay(2000).fadeOut('slow');
            } else {
                watchlist.children('.unwatch.success').fadeIn('slow').delay(2000).fadeOut('slow');
            }
        });
});

// Apply panzoom and featherlight.
if ($('.image.panzoom-container').length) {

    var storedPanzoomStyle = '';
    var storedRotateStyle = '';

    Scripto.applyPanzoom($('.media-render'));

    $('.full-screen').featherlight('.wikitext-featherlight', {
        beforeOpen: function() {
            $('.media-render').panzoom('destroy');
        },
        afterOpen: function() {
            Scripto.applyPanzoom($('.featherlight-content .media-render'));

            // Apply wikitext editor to lightbox textarea.
            if ($('#wikitext-editor-text').length) {
                var lmlEditor = new LmlEditor(
                    $('.featherlight-content #wikitext-editor-text')[0],
                    $('.featherlight-content #wikitext-editor-buttons')[0],
                );
                $('.featherlight-content #wikitext-editor-buttons').empty();
                lmlEditor.addMediawikiButtons();
            }
        },
        beforeClose: function() {
            storedPanzoomStyle = $('.featherlight-content .media-render').attr('style');
            storedRotateStyle = $('.featherlight-content .panzoom-container img').attr('style');
            $('.featherlight-content .media-render').panzoom('destroy');
            $('.media-render').attr('style', storedPanzoomStyle);
            $('.panzoom-container img').attr('style', storedRotateStyle);

            // Copy value of lightbox textarea to original textarea.
            if ($('#wikitext-editor-text').length) {
                $('#wikitext #wikitext-editor-text').val($('.featherlight-content #wikitext-editor-text').val());
            }
        },
        afterClose: function() {
            Scripto.applyPanzoom($('.media-render'));
        }
    });

    $('.panzoom-container').on('click', '.rotate-left', function(e) {
        e.preventDefault();
        var panzoomImg = $(this).parents('.panzoom-container').find('img');
        Scripto.setRotation(panzoomImg, 'left');
    });

    $('.panzoom-container').on('click', '.rotate-right', function(e) {
        e.preventDefault();
        var panzoomImg = $(this).parents('.panzoom-container').find('img');
        Scripto.setRotation(panzoomImg, 'right');
    });

    $('.panzoom-container').on('click', '.reset', function(e) {
        e.preventDefault();
        var panzoomImg = $(this).parents('.panzoom-container').find('img');
        panzoomImg.css('transform', 'none');
    });
} else {
    $('.full-screen').featherlight('.wikitext-featherlight');
}

// Handle the layout buttons.
$('.layout button').click(function(e) {
    $('.layout button').toggleClass('active');
    $('.wikitext-featherlight').toggleClass('horizontal').toggleClass('vertical');
    $('.layout button:disabled').removeAttr('disabled');
    $('.layout button.active').attr('disabled', true);
});

});
