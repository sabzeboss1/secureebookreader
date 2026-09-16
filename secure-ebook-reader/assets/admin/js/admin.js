/**
 * JavaScript d'administration pour Secure Ebook Reader
 */

jQuery(document).ready(function ($) {
    // 1. Gestionnaire d'ouverture et de fermeture de la modale d'accès
    $('#btn-open-grant-modal').on('click', function () {
        $('#modal-grant-access').fadeIn(150);
    });

    $('.btn-close-modal').on('click', function () {
        $('#modal-grant-access').fadeOut(150);
    });

    $(window).on('click', function (e) {
        if ($(e.target).is('#modal-grant-access')) {
            $('#modal-grant-access').fadeOut(150);
        }
    });

    // 2. Sélecteur de média WordPress pour l'image de couverture
    var fileFrame;
    $('#btn-select-cover').on('click', function (e) {
        e.preventDefault();

        if (fileFrame) {
            fileFrame.open();
            return;
        }

        fileFrame = wp.media({
            title: (typeof secureEbookAdmin !== 'undefined') ? secureEbookAdmin.media_title : 'Choisir la couverture',
            button: {
                text: (typeof secureEbookAdmin !== 'undefined') ? secureEbookAdmin.media_button : 'Utiliser cette image'
            },
            multiple: false,
            library: { type: 'image' }
        });

        fileFrame.on('select', function () {
            var attachment = fileFrame.state().get('selection').first().toJSON();
            $('#cover_image_id').val(attachment.id);

            var previewUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
            $('#cover-img-preview').attr('src', previewUrl).show();
            $('#cover-placeholder-text').hide();
            $('#btn-remove-cover').show();
        });

        fileFrame.open();
    });

    // Supprimer la couverture
    $('#btn-remove-cover').on('click', function (e) {
        e.preventDefault();
        $('#cover_image_id').val('0');
        $('#cover-img-preview').attr('src', '').hide();
        $('#cover-placeholder-text').show();
        $(this).hide();
    });
});
