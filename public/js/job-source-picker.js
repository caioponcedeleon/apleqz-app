(function () {
    'use strict';

    var pickerMode = 'item';
    var itemSelector = '';
    var highlightEl = null;
    var pickerEnabled = true;

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
            return tag + '.' + classes.map(function (className) {
                return CSS.escape(className);
            }).join('.');
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

        return tag + ':nth-child(' + (siblings.indexOf(element) + 1) + ')';
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

    function buildItemSelector(element) {
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

    function findItemContainer(element) {
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

    function clearHighlight() {
        if (highlightEl) {
            highlightEl.classList.remove('job-source-picker-highlight');
            highlightEl = null;
        }
    }

    function setHighlight(element) {
        clearHighlight();

        if (!element || element.nodeType !== 1 || !pickerEnabled) {
            return;
        }

        highlightEl = element;
        highlightEl.classList.add('job-source-picker-highlight');
    }

    function injectStyles() {
        if (document.getElementById('job-source-picker-styles')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'job-source-picker-styles';
        style.textContent = '.job-source-picker-highlight{outline:2px solid #f59e0b!important;outline-offset:2px!important;cursor:crosshair!important;}';
        (document.head || document.documentElement).appendChild(style);
    }

    window.addEventListener('message', function (event) {
        if (!event.data || event.data.type !== 'job-source-picker-config') {
            return;
        }

        pickerMode = event.data.mode || 'item';
        itemSelector = event.data.itemSelector || '';
        pickerEnabled = event.data.enabled !== false;
    });

    document.addEventListener('mouseover', function (event) {
        if (!pickerEnabled) {
            return;
        }

        var target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        setHighlight(target);
    }, true);

    document.addEventListener('mouseout', function () {
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

        if (pickerMode === 'item') {
            postToParent({
                mode: 'item',
                selector: buildItemSelector(target),
                scope: 'document',
                tagName: target.tagName,
                matchCount: (function () {
                    try {
                        return document.querySelectorAll(buildItemSelector(target)).length;
                    } catch (error) {
                        return 0;
                    }
                })(),
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
