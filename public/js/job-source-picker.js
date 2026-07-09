(function () {
    'use strict';

    var pickerMode = 'item';
    var itemSelector = '';
    var itemMode = 'single';
    var itemGroupParts = [];
    var highlightEl = null;
    var highlightEls = [];
    var pickerEnabled = true;
    var pinnedSelector = '';
    var pinnedMatchIndex = 0;
    var tooltipEl = null;
    var tooltipText = '';

    function postToParent(payload) {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage(Object.assign({ type: 'job-source-picker' }, payload), '*');
        }
    }

    function stableClasses(element) {
        return Array.prototype.slice.call(element.classList || [])
            .filter(function (className) {
                return className
                    && className !== 'job-source-picker-highlight'
                    && !/^js-/.test(className);
            });
    }

    function simpleSelector(element) {
        if (!element || element.nodeType !== 1) {
            return '';
        }

        if (element.id && !/^\d/.test(element.id)) {
            return '#' + CSS.escape(element.id);
        }

        var tag = element.tagName.toLowerCase();
        var classes = stableClasses(element).slice(0, 3);

        if (classes.length > 0) {
            var classSelector = tag + '.' + classes.map(function (className) {
                return CSS.escape(className);
            }).join('.');

            if (similarSiblingCount(element) >= 2) {
                var classParent = element.parentElement;

                if (classParent) {
                    return classSelector + ':nth-child(' + (Array.prototype.indexOf.call(classParent.children, element) + 1) + ')';
                }
            }

            return classSelector;
        }

        var parent = element.parentElement;

        if (!parent) {
            return tag;
        }

        var siblings = Array.prototype.filter.call(parent.children, function (child) {
            return child.tagName === element.tagName;
        });

        if (siblings.length === 1) {
            return tag;
        }

        return tag + ':nth-of-type(' + (siblings.indexOf(element) + 1) + ')';
    }

    function classOnlySelector(element) {
        if (!element || element.nodeType !== 1) {
            return '';
        }

        if (element.id && !/^\d/.test(element.id)) {
            return '#' + CSS.escape(element.id);
        }

        var tag = element.tagName.toLowerCase();
        var classes = stableClasses(element);

        if (classes.length > 0) {
            return tag + '.' + classes.map(function (className) {
                return CSS.escape(className);
            }).join('.');
        }

        return tag;
    }

    function selectorMatchCount(selector) {
        if (!selector) {
            return 0;
        }

        try {
            return document.querySelectorAll(selector).length;
        } catch (error) {
            return 0;
        }
    }

    function buildPathSelector(element, stopAt) {
        var segments = [];
        var current = element;

        while (current && current !== stopAt && current.nodeType === 1) {
            segments.unshift(simpleSelector(current));
            current = current.parentElement;
        }

        return segments.join(' > ');
    }

    function similarSiblingCount(element) {
        var parent = element.parentElement;

        if (!parent) {
            return 0;
        }

        var tag = element.tagName;
        var classes = stableClasses(element);

        return Array.prototype.filter.call(parent.children, function (child) {
            if (child.nodeType !== 1 || child.tagName !== tag) {
                return false;
            }

            if (classes.length === 0) {
                return stableClasses(child).length === 0;
            }

            var childClasses = stableClasses(child);

            return classes.every(function (className) {
                return childClasses.indexOf(className) !== -1;
            });
        }).length;
    }

    function tableRowSelector(element) {
        if (!element || !element.closest) {
            return null;
        }

        var row = element.closest('tr');

        if (!row || !row.parentElement || row.parentElement.tagName !== 'TBODY') {
            return null;
        }

        var table = row.closest('table');

        if (!table) {
            return 'tbody > tr';
        }

        var tableSelector = classOnlySelector(table);
        var selector = tableSelector + ' tbody tr';
        var count = selectorMatchCount(selector);

        if (count >= 2) {
            return selector;
        }

        return 'table tbody tr';
    }

    function buildItemSelector(element) {
        var tableSelector = tableRowSelector(element);

        if (tableSelector && selectorMatchCount(tableSelector) >= 2) {
            return tableSelector;
        }

        var current = element;

        while (current && current.nodeType === 1 && current !== document.body) {
            if (similarSiblingCount(current) >= 2) {
                return classOnlySelector(current);
            }

            current = current.parentElement;
        }

        current = element;

        while (current && current.nodeType === 1 && current !== document.body) {
            var selector = classOnlySelector(current);

            try {
                if (document.querySelectorAll(selector).length >= 2) {
                    return selector;
                }
            } catch (error) {
                // Ignore invalid selectors while walking up the tree.
            }

            current = current.parentElement;
        }

        return classOnlySelector(element) || simpleSelector(element);
    }

    function buildItemCandidate(element) {
        var tableSelector = tableRowSelector(element);

        if (tableSelector) {
            return {
                selector: tableSelector,
                matchCount: selectorMatchCount(tableSelector),
            };
        }

        var selector = buildItemSelector(element);

        return {
            selector: selector,
            matchCount: selectorMatchCount(selector),
        };
    }

    function buildItemCandidates(element) {
        var seen = {};
        var candidates = [];
        var current = element;

        while (current && current.nodeType === 1 && current !== document.body) {
            var candidate = buildItemCandidate(current);

            if (candidate.selector && !seen[candidate.selector]) {
                seen[candidate.selector] = true;
                candidates.push(candidate);
            }

            current = current.parentElement;
        }

        return candidates;
    }

    function buildInteractionSelector(element) {
        if (!element || element.nodeType !== 1) {
            return '';
        }

        if (element.id && !/^\d/.test(element.id)) {
            return '#' + CSS.escape(element.id);
        }

        var tag = element.tagName.toLowerCase();
        var classes = stableClasses(element).slice(0, 4);

        if (classes.length > 0) {
            var classSelector = tag + '.' + classes.map(function (className) {
                return CSS.escape(className);
            }).join('.');

            if (selectorMatchCount(classSelector) === 1) {
                return classSelector;
            }
        }

        var role = element.getAttribute('role');
        var ariaLabel = element.getAttribute('aria-label');

        if (role && ariaLabel) {
            var ariaSelector = tag + '[role="' + role.replace(/"/g, '\\"') + '"][aria-label="' + ariaLabel.replace(/"/g, '\\"') + '"]';

            if (selectorMatchCount(ariaSelector) >= 1) {
                return ariaSelector;
            }
        }

        return classOnlySelector(element) || simpleSelector(element);
    }

    function buildGroupPartSelector(element) {
        var cell = element.closest('td, th');

        if (cell) {
            var row = cell.parentElement;

            if (row && row.tagName === 'TR') {
                var tbody = row.parentElement;

                if (tbody && tbody.tagName === 'TBODY') {
                    var table = row.closest('table');

                    if (table) {
                        var tableSelector = classOnlySelector(table);
                        var index = Array.prototype.indexOf.call(row.children, cell) + 1;

                        return tableSelector + ' tbody tr > td:nth-child(' + index + ')';
                    }
                }
            }
        }

        return buildItemSelector(element);
    }

    function buildDetailFieldSelector(element) {
        if (!element || element.nodeType !== 1) {
            return '';
        }

        if (element.id && !/^\d/.test(element.id)) {
            return '#' + CSS.escape(element.id);
        }

        var classSelector = classOnlySelector(element);

        if (classSelector && selectorMatchCount(classSelector) === 1) {
            return classSelector;
        }

        return buildPathSelector(element, document.documentElement);
    }

    function matchIndexForElement(selector, element) {
        var elements;

        try {
            elements = document.querySelectorAll(selector);
        } catch (error) {
            return 0;
        }

        for (var index = 0; index < elements.length; index += 1) {
            if (elements[index] === element) {
                return index;
            }
        }

        for (var matchIndex = 0; matchIndex < elements.length; matchIndex += 1) {
            if (elements[matchIndex].contains && elements[matchIndex].contains(element)) {
                return matchIndex;
            }
        }

        return 0;
    }

    function buildDetailFieldCandidates(element) {
        var seen = {};
        var candidates = [];
        var current = element;

        while (current && current.nodeType === 1 && current !== document.documentElement) {
            var selector = buildDetailFieldSelector(current);

            if (selector && !seen[selector]) {
                seen[selector] = true;
                var matchCount = selectorMatchCount(selector);

                candidates.push({
                    selector: selector,
                    tagName: current.tagName,
                    matchCount: matchCount,
                    matchIndex: matchIndexForElement(selector, element),
                });
            }

            current = current.parentElement;
        }

        return candidates;
    }

    function lowestCommonAncestor(nodes) {
        if (!nodes || nodes.length === 0) {
            return null;
        }

        if (nodes.length === 1) {
            return nodes[0];
        }

        var ancestors = new Map();
        var first = nodes[0];

        for (var current = first; current && current.nodeType === 1; current = current.parentElement) {
            ancestors.set(current, true);
        }

        for (var index = 1; index < nodes.length; index += 1) {
            for (current = nodes[index]; current && current.nodeType === 1; current = current.parentElement) {
                if (ancestors.has(current)) {
                    return current;
                }
            }
        }

        return first;
    }

    function findGroupContainer(element) {
        if (itemMode !== 'group' || itemGroupParts.length === 0) {
            return null;
        }

        var partNodeLists = itemGroupParts.map(function (selector) {
            try {
                return Array.prototype.slice.call(document.querySelectorAll(selector));
            } catch (error) {
                return [];
            }
        });

        var count = Math.min.apply(
            null,
            partNodeLists.map(function (list) {
                return list.length;
            }).concat([0]),
        );

        for (var groupIndex = 0; groupIndex < count; groupIndex += 1) {
            var containsTarget = partNodeLists.some(function (list) {
                return list[groupIndex] && list[groupIndex].contains(element);
            });

            if (! containsTarget) {
                continue;
            }

            var nodes = partNodeLists.map(function (list) {
                return list[groupIndex];
            }).filter(Boolean);

            var ancestor = lowestCommonAncestor(nodes);

            if (ancestor instanceof Element) {
                return ancestor;
            }
        }

        return null;
    }

    function findItemContainer(element) {
        if (itemMode === 'group' && itemGroupParts.length > 0) {
            var groupContainer = findGroupContainer(element);

            if (groupContainer) {
                return groupContainer;
            }
        }

        if (!itemSelector) {
            return element;
        }

        var items = document.querySelectorAll(itemSelector);

        for (var index = 0; index < items.length; index += 1) {
            if (items[index].contains(element)) {
                return items[index];
            }
        }

        return element;
    }

    function normalizePreviewText(text) {
        return (text || '').replace(/\s+/g, ' ').trim();
    }

    function truncateText(text, maxLength) {
        if (text.length <= maxLength) {
            return text;
        }

        return text.slice(0, maxLength - 1) + '…';
    }

    function extractPreviewText(element) {
        if (!element) {
            return '';
        }

        return truncateText(normalizePreviewText(element.innerText || element.textContent || ''), 280);
    }

    function removeTooltip() {
        if (tooltipEl && tooltipEl.parentNode) {
            tooltipEl.parentNode.removeChild(tooltipEl);
        }

        tooltipEl = null;
        tooltipText = '';
    }

    function positionTooltip(element) {
        if (!tooltipEl || !element) {
            return;
        }

        var rect = element.getBoundingClientRect();
        var top = rect.bottom + 8;
        var left = Math.max(8, rect.left);
        var maxLeft = window.innerWidth - tooltipEl.offsetWidth - 8;

        tooltipEl.style.top = Math.max(8, top) + 'px';
        tooltipEl.style.left = Math.min(left, maxLeft) + 'px';
    }

    function showTooltip(element, text) {
        removeTooltip();

        if (!element || !text) {
            return;
        }

        tooltipText = text;
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'job-source-picker-tooltip';
        tooltipEl.textContent = text;
        document.body.appendChild(tooltipEl);
        positionTooltip(element);
    }

    function repositionTooltip() {
        if (tooltipEl && highlightEl) {
            positionTooltip(highlightEl);
        }
    }

    function clearHighlight() {
        highlightEls.forEach(function (element) {
            element.classList.remove('job-source-picker-highlight');
            element.classList.remove('job-source-picker-highlight-pinned');
        });
        highlightEls = [];
        highlightEl = null;
        removeTooltip();
    }

    function setHighlight(element, pinned) {
        clearHighlight();

        if (!element || element.nodeType !== 1) {
            return;
        }

        if (!pickerEnabled && !pinned) {
            return;
        }

        highlightEl = element;
        highlightEls = [element];
        highlightEl.classList.add('job-source-picker-highlight');

        if (pinned) {
            highlightEl.classList.add('job-source-picker-highlight-pinned');
        }
    }

    function applyPinnedHighlight() {
        clearHighlight();

        if (!pinnedSelector) {
            return;
        }

        var elements;

        try {
            elements = document.querySelectorAll(pinnedSelector);
        } catch (error) {
            return;
        }

        if (!elements.length) {
            return;
        }

        var element = elements[Math.min(Math.max(pinnedMatchIndex, 0), elements.length - 1)];
        setHighlight(element, true);

        var previewText = extractPreviewText(element);
        var matchPosition = elements.length > 1
            ? 'Match ' + (Math.min(Math.max(pinnedMatchIndex, 0), elements.length - 1) + 1) + ' of ' + elements.length + ' — '
            : '';

        if (!previewText) {
            previewText = '(' + (element.tagName || 'element').toLowerCase() + ')';
        }

        showTooltip(element, matchPosition + previewText);
    }

    function injectStyles() {
        if (document.getElementById('job-source-picker-styles')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'job-source-picker-styles';
        style.textContent = ''
            + '.job-source-picker-highlight{outline:2px solid #f59e0b!important;outline-offset:2px!important;cursor:crosshair!important;}'
            + '.job-source-picker-highlight-pinned{outline-color:#6366f1!important;cursor:default!important;}'
            + '.job-source-picker-tooltip{position:fixed;z-index:2147483647;max-width:min(420px,calc(100vw - 16px));padding:8px 12px;border-radius:8px;background:rgba(17,24,39,.95);color:#f9fafb;font:12px/1.45 system-ui,-apple-system,sans-serif;box-shadow:0 4px 12px rgba(0,0,0,.25);pointer-events:none;white-space:pre-wrap;word-break:break-word;}';
        (document.head || document.documentElement).appendChild(style);
    }

    window.addEventListener('message', function (event) {
        if (!event.data || event.data.type !== 'job-source-picker-config') {
            return;
        }

        pickerMode = event.data.mode || 'item';
        itemSelector = event.data.itemSelector || '';
        itemMode = event.data.itemMode || 'single';
        itemGroupParts = Array.isArray(event.data.itemGroupParts) ? event.data.itemGroupParts : [];
        pickerEnabled = event.data.enabled !== false;
        pinnedSelector = typeof event.data.pinnedSelector === 'string' ? event.data.pinnedSelector : '';
        pinnedMatchIndex = typeof event.data.pinnedMatchIndex === 'number' ? event.data.pinnedMatchIndex : 0;

        if (pinnedSelector) {
            applyPinnedHighlight();
        } else if (!pickerEnabled) {
            clearHighlight();
        }
    });

    window.addEventListener('scroll', repositionTooltip, true);
    window.addEventListener('resize', repositionTooltip);

    document.addEventListener('mouseover', function (event) {
        if (pinnedSelector) {
            return;
        }

        if (!pickerEnabled) {
            return;
        }

        var target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        setHighlight(target, false);
    }, true);

    document.addEventListener('mouseout', function () {
        if (pinnedSelector) {
            return;
        }

        clearHighlight();
    }, true);

    document.addEventListener('click', function (event) {
        if (!pickerEnabled) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        var target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        if (pickerMode === 'item_group_part') {
            var partSelector = buildGroupPartSelector(target);

            postToParent({
                mode: 'group_part',
                selector: partSelector,
                matchCount: selectorMatchCount(partSelector),
                tagName: target.tagName,
            });

            return;
        }

        if (pickerMode === 'interaction') {
            var interactionSelector = buildInteractionSelector(target);

            postToParent({
                mode: 'interaction',
                selector: interactionSelector,
                matchCount: selectorMatchCount(interactionSelector),
                tagName: target.tagName,
            });

            return;
        }

        if (pickerMode === 'detail_field') {
            var detailCandidates = buildDetailFieldCandidates(target);

            postToParent({
                mode: 'detail_field',
                selector: detailCandidates.length > 0 ? detailCandidates[0].selector : '',
                tagName: target.tagName,
                candidates: detailCandidates,
                candidateIndex: 0,
                matchIndex: detailCandidates.length > 0 ? (detailCandidates[0].matchIndex || 0) : 0,
            });

            return;
        }

        if (pickerMode === 'item') {
            var candidates = buildItemCandidates(target);

            postToParent({
                mode: 'item',
                selector: candidates.length > 0 ? candidates[0].selector : '',
                matchCount: candidates.length > 0 ? candidates[0].matchCount : 0,
                candidates: candidates,
                candidateIndex: 0,
                tagName: target.tagName,
            });

            return;
        }

        var item = findItemContainer(target);
        var selector = item === target
            ? simpleSelector(target)
            : buildPathSelector(target, item);

        postToParent({
            mode: 'field',
            selector: selector,
            scope: 'item',
            tagName: target.tagName,
        });
    }, true);

    injectStyles();
    postToParent({ ready: true });
})();
