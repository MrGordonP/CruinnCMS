Cruinn.BlockTypes.register('gallery', {
    label: 'Gallery',
    tag: 'div',
    isLayout: false,
    hasContent: false,
    getContent: function (blockItem) {
        var galleryEditor = blockItem.querySelector('.block-gallery-editor');
        return {
            images: galleryEditor ? JSON.parse(galleryEditor.dataset.images || '[]') : [],
        };
    },
});
