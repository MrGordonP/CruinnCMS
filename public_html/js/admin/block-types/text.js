Cruinn.BlockTypes.register('text', {
    label: 'Text',
    tag: 'div',
    isLayout: false,
    hasContent: false,
    getContent: function (blockItem) {
        var rteEditor = blockItem.querySelector('.rte-editor');
        var rteSrc    = blockItem.querySelector('.rte-source-textarea');
        if (rteEditor && rteSrc && rteEditor.style.display !== 'none') {
            rteSrc.value = rteEditor.innerHTML;
        }
        return { html: rteSrc ? rteSrc.value : '' };
    },
});
