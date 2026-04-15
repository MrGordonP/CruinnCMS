Cruinn.BlockTypes.register('site-title', {
    label: 'Site Title',
    tag: 'div',
    isLayout: false,
    hasContent: true,
    getContent: function (blockItem) {
        return {
            tagline:    (blockItem.querySelector('[name="content_tagline"]')     || {}).value || '',
            taglineTag: (blockItem.querySelector('[name="content_tagline_tag"]') || {}).value || 'p',
        };
    },
    populatePanel: function (g, blockItem) {
        var tInp   = blockItem.querySelector('[name="content_tagline"]');
        var tagInp = blockItem.querySelector('[name="content_tagline_tag"]');
        var pTag   = g.querySelector('[data-content-prop="tagline"]');
        var pTT    = g.querySelector('[data-content-prop="taglineTag"]');
        if (tInp   && pTag) { pTag.value = tInp.value;   }
        if (tagInp && pTT)  { pTT.value  = tagInp.value; }
    },
});
