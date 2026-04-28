Cruinn.BlockTypes.register('site-logo', {
    label: 'Site Logo',
    tag: 'div',
    isLayout: false,
    hasContent: true,
    getContent: function (blockItem) {
        return {
            src:     (blockItem.querySelector('[name="content_src"]') || {}).value || '',
            alt:     (blockItem.querySelector('[name="content_alt"]') || blockItem.querySelector('.block-alt-input') || {}).value || '',
            linkUrl: (blockItem.querySelector('[name="content_link_url"]') || {}).value || '/',
        };
    },
    populatePanel: function (g, blockItem) {
        var srcInp  = blockItem.querySelector('[name="content_src"]');
        var altInp  = blockItem.querySelector('[name="content_alt"]');
        var linkInp = blockItem.querySelector('[name="content_link_url"]');
        var pSrc    = g.querySelector('[data-content-prop="src"]');
        var pAlt    = g.querySelector('[data-content-prop="alt"]');
        var pLink   = g.querySelector('[data-content-prop="linkUrl"]');
        if (srcInp  && pSrc)  { pSrc.value  = srcInp.value;  }
        if (altInp  && pAlt)  { pAlt.value  = altInp.value;  }
        if (linkInp && pLink) { pLink.value  = linkInp.value; }
    },
});
