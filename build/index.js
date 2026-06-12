(function (wp) {
    'use strict';

    if (!wp || !wp.element || !wp.components || !wp.apiFetch || !wp.i18n || !wp.domReady) {
        var fallbackRoot = document.getElementById('acf-image-auto-filler-app');
        if (fallbackRoot) {
            fallbackRoot.innerHTML = '<div class="notice notice-error inline"><p>De WordPress React admin-onderdelen konden niet worden geladen. Herlaad de pagina of controleer of WordPress scripts correct worden geladen.</p></div>';
        }
        return;
    }

    var el = wp.element.createElement;
    var useEffect = wp.element.useEffect;
    var useMemo = wp.element.useMemo;
    var useState = wp.element.useState;
    var render = wp.element.render || function (component, target) {
        if (wp.element.createRoot) {
            wp.element.createRoot(target).render(component);
        }
    };
    var __ = wp.i18n.__;
    var apiFetch = wp.apiFetch;
    var components = wp.components;
    var FALLBACK_UI = {
        control: 'aiaf-fallback-control',
        checkbox: 'aiaf-fallback-checkbox',
        toggle: 'aiaf-fallback-toggle',
        flex: 'aiaf-flex-fallback',
        flexItem: 'aiaf-flex-item-fallback'
    };
    function passthrough(tagName, className) {
        return function (props) {
            props = props || {};
            var children = props.children;
            var attrs = Object.assign({}, props);
            delete attrs.children;
            if (className) {
                attrs.className = attrs.className ? attrs.className + ' ' + className : className;
            }
            return el(tagName, attrs, children);
        };
    }
    function ButtonFallback(props) {
        props = props || {};
        var className = 'button';
        if (props.variant === 'primary') { className += ' button-primary'; }
        if (props.variant === 'secondary') { className += ' button-secondary'; }
        if (props.variant === 'link') { className += ' button-link'; }
        if (props.className) { className += ' ' + props.className; }
        return el('button', { type: 'button', className: className, onClick: props.onClick, disabled: !!props.disabled }, props.children);
    }
    function NoticeFallback(props) {
        props = props || {};
        var status = props.status || 'info';
        return el('div', { className: 'notice notice-' + status + ' inline' }, el('p', null, props.children));
    }
    function SelectControlFallback(props) {
        props = props || {};
        var id = props.id || ('aiaf-select-' + String(props.label || '').replace(/[^a-z0-9]+/gi, '-').toLowerCase());
        return el('div', { className: FALLBACK_UI.control },
            el('label', { className: props.hideLabelFromVision ? 'screen-reader-text' : '', htmlFor: id }, props.label),
            el('select', { id: id, value: props.value || '', disabled: !!props.disabled, onChange: function (event) { if (props.onChange) { props.onChange(event.target.value); } } },
                (props.options || []).map(function (option) { return el('option', { key: option.value, value: option.value }, option.label); })
            )
        );
    }
    function TextControlFallback(props) {
        props = props || {};
        var id = props.id || ('aiaf-text-' + String(props.label || '').replace(/[^a-z0-9]+/gi, '-').toLowerCase());
        return el('label', { className: FALLBACK_UI.control, htmlFor: id },
            props.label,
            el('input', { id: id, type: 'text', value: props.value || '', placeholder: props.placeholder || '', onChange: function (event) { if (props.onChange) { props.onChange(event.target.value); } } })
        );
    }
    function CheckboxControlFallback(props) {
        props = props || {};
        return el('label', { className: FALLBACK_UI.checkbox },
            el('input', { type: 'checkbox', checked: !!props.checked, onChange: function (event) { if (props.onChange) { props.onChange(event.target.checked); } } }),
            ' ', props.label
        );
    }
    function ToggleControlFallback(props) {
        props = props || {};
        return el('div', { className: FALLBACK_UI.toggle },
            el(CheckboxControlFallback, { label: props.label, checked: props.checked, onChange: props.onChange }),
            props.help && el('p', { className: 'description' }, props.help)
        );
    }
    var Button = components.Button || ButtonFallback;
    var Card = components.Card || passthrough('div', 'components-card');
    var CardBody = components.CardBody || passthrough('div', 'components-card__body');
    var CardHeader = components.CardHeader || passthrough('div', 'components-card__header');
    var CheckboxControl = components.CheckboxControl || CheckboxControlFallback;
    var Flex = components.Flex || passthrough('div', FALLBACK_UI.flex);
    var FlexItem = components.FlexItem || passthrough('div', FALLBACK_UI.flexItem);
    var Notice = components.Notice || NoticeFallback;
    var Spinner = components.Spinner || function () { return el('span', { className: 'spinner is-active' }); };
    var SelectControl = components.SelectControl || SelectControlFallback;
    var TextControl = components.TextControl || TextControlFallback;
    var Modal = components.Modal || passthrough('div', 'components-modal__frame');
    var TabPanel = components.TabPanel || function (props) { return el('div', null, props.children); };
    var ToggleControl = components.ToggleControl || ToggleControlFallback;

    var UI = {
        app: 'aiaf-app',
        loading: 'aiaf-loading',
        mainFlow: 'aiaf-main-flow aiaf-main-flow-minimal',
        stepper: 'aiaf-stepper aiaf-stepper-compact',
        stepNumber: 'aiaf-step-number',
        stepText: 'aiaf-step-text',
        card: 'aiaf-card',
        cardWide: 'aiaf-card aiaf-card--wide',
        cardNarrow: 'aiaf-card aiaf-card--narrow',
        summary: 'aiaf-compact-summary',
        summaryActions: 'aiaf-compact-summary-actions',
        contentFields: 'aiaf-content-fields',
        emptyState: 'aiaf-empty-state',
        contentToolbar: 'aiaf-content-toolbar',
        contentToolbarCount: 'aiaf-content-toolbar-count',
        contentToolbarActions: 'aiaf-content-toolbar-actions',
        postList: 'aiaf-post-list',
        postOption: 'aiaf-post-option',
        postOptionSelected: 'aiaf-post-option is-selected',
        optionStrip: 'aiaf-option-strip',
        fieldActions: 'aiaf-field-actions',
        fieldList: 'aiaf-field-list',
        fieldRow: 'aiaf-field-row',
        fieldRowSelected: 'aiaf-field-row is-selected',
        fieldMain: 'aiaf-field-main',
        fieldBadges: 'aiaf-field-badges',
        techDetails: 'aiaf-tech-details',
        currentThumb: 'aiaf-current-thumb',
        imageGrid: 'aiaf-image-grid',
        imageTile: 'aiaf-image-tile',
        imagePreviewButton: 'aiaf-image-preview-button',
        imageActions: 'aiaf-image-actions',
        batchWarning: 'aiaf-batch-warning',
        mappingToolbar: 'aiaf-mapping-toolbar',
        mappingTableWrap: 'aiaf-mapping-table-wrap',
        mappingTable: 'aiaf-mapping-table',
        inlineImageButton: 'aiaf-inline-image aiaf-inline-image-button',
        muted: 'aiaf-muted',
        actionBar: 'aiaf-action-bar',
        actionSummary: 'aiaf-action-summary',
        actionButtons: 'aiaf-action-buttons',
        controlPanel: 'aiaf-control-panel',
        previewPlaceholder: 'aiaf-preview-placeholder',
        auditDetails: 'aiaf-audit-details',
        resultMessages: 'aiaf-result-messages',
        resultList: 'aiaf-result-list',
        resultRow: 'aiaf-result-row',
        resultRowSkipped: 'aiaf-result-row is-skipped',
        resultThumb: 'aiaf-result-thumb',
        auditList: 'aiaf-audit-list',
        auditRow: 'aiaf-audit-row',
        confirmContent: 'aiaf-confirm-content',
        modalActions: 'aiaf-modal-actions',
        largePreview: 'aiaf-large-preview',
        badge: 'aiaf-badge',
        badgeSuccess: 'aiaf-badge aiaf-badge-success',
        badgeWarning: 'aiaf-badge aiaf-badge-warning',
        badgeDanger: 'aiaf-badge aiaf-badge-danger',
        badgeNeutral: 'aiaf-badge aiaf-badge-neutral'
    };

    var settings = window.AIAFSettings || {};
    var canViewAuditLog = !!settings.canViewAuditLog;
    apiFetch.use(apiFetch.createNonceMiddleware(settings.nonce || ''));

    function request(path, options) {
        options = options || {};
        return apiFetch(Object.assign({ path: '/acf-image-auto-filler/v1' + path }, options));
    }

    function normalize(text) {
        return String(text || '').toLowerCase().replace(/\.[a-z0-9]+$/i, '').replace(/[^a-z0-9]+/g, ' ').trim();
    }

    function csvEscape(value) {
        var text = String(value == null ? '' : value);
        if (/^[=+\-@\t\r]/.test(text)) {
            text = '\'' + text;
        }
        return '"' + text.replace(/"/g, '""') + '"';
    }

    function downloadCsv(data) {
        data = data || {};
        var rows = [['post_id', 'post_title', 'field_label', 'field_name', 'field_key', 'attachment_id', 'attachment_title', 'status', 'reason']];
        (data.filled || []).forEach(function (item) {
            rows.push([
                item.post_id || '', item.post_title || '', item.field_label || '', item.field_name || '', item.field_key || '',
                item.attachment_id || '', item.attachment_title || '', item.executed ? 'executed' : (item.will_overwrite ? 'will_overwrite' : 'will_fill'), ''
            ]);
        });
        (data.skipped || []).forEach(function (item) {
            rows.push([item.post_id || '', '', item.field_label || '', item.field_name || '', item.field_key || '', item.attachment_id || '', '', item.status || 'skipped', item.reason || '']);
        });
        var csv = rows.map(function (row) { return row.map(csvEscape).join(','); }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'acf-image-auto-filler-dry-run.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    function makeSignature(data) {
        return JSON.stringify(data || {});
    }

    function statusClass(status) {
        if (status === 'success' || status === 'fill' || status === 'ready') { return UI.badgeSuccess; }
        if (status === 'warning' || status === 'overwrite' || status === 'existing' || status === 'stale') { return UI.badgeWarning; }
        if (status === 'danger' || status === 'error') { return UI.badgeDanger; }
        return UI.badgeNeutral;
    }

    function StatusBadge(props) {
        return el('span', { className: statusClass(props.status) }, props.children || props.label);
    }

    function SummaryFromData(data) {
        data = data || {};
        return {
            fill: (data.filled || []).length,
            skip: (data.skipped || []).length,
            errors: (data.errors || []).length,
            overwrite: (data.filled || []).filter(function (item) { return !!item.will_overwrite; }).length,
            rolledBack: (data.rolledBack || []).length
        };
    }

    function AppHeader() {
        return null;
    }

    function Stepper(props) {
        var steps = [
            { label: __('Content', 'acf-image-auto-filler'), complete: props.hasContent },
            { label: __('Velden', 'acf-image-auto-filler'), complete: props.hasFields },
            { label: __('Afbeeldingen', 'acf-image-auto-filler'), complete: props.hasImages },
            { label: __('Controle', 'acf-image-auto-filler'), complete: props.hasPreview && !props.previewStale },
            { label: __('Uitvoeren', 'acf-image-auto-filler'), complete: props.hasResult }
        ];

        return el('ol', { className: UI.stepper, 'aria-label': __('Workflow stappen', 'acf-image-auto-filler') }, steps.map(function (step, index) {
            var state = index === props.current ? 'is-current' : (step.complete ? 'is-complete' : 'is-disabled');
            return el('li', { key: step.label, className: state, 'aria-current': index === props.current ? 'step' : undefined },
                el('span', { className: UI.stepNumber, 'aria-hidden': 'true' }, String(index + 1)),
                el('span', { className: UI.stepText }, step.label)
            );
        }));
    }

    function App() {
        var [postTypes, setPostTypes] = useState([]);
        var [posts, setPosts] = useState([]);
        var [postSearch, setPostSearch] = useState('');
        var [selectedPostType, setSelectedPostType] = useState('');
        var [selectedPosts, setSelectedPosts] = useState([]);
        var [fields, setFields] = useState([]);
        var [selectedFieldKeys, setSelectedFieldKeys] = useState([]);
        var [fieldMapping, setFieldMapping] = useState({});
        var [mappingFilter, setMappingFilter] = useState('all');
        var [images, setImages] = useState([]);
        var [overwrite, setOverwrite] = useState(false);
        var [includeGroups, setIncludeGroups] = useState(false);
        var [useFeaturedImage, setUseFeaturedImage] = useState(false);
        var [preview, setPreview] = useState(null);
        var [previewSignature, setPreviewSignature] = useState('');
        var [result, setResult] = useState(null);
        var [auditLog, setAuditLog] = useState([]);
        var [hasRollback, setHasRollback] = useState(false);
        var [previewImage, setPreviewImage] = useState(null);
        var [loading, setLoading] = useState(false);
        var [error, setError] = useState('');
        var [confirmOpen, setConfirmOpen] = useState(false);
        var [acfActive, setAcfActive] = useState(settings.acfActive !== false);

        var selectedPost = selectedPosts.length ? selectedPosts[0] : 0;
        var postTypeOptions = postTypes.map(function (type) { return { label: type.label, value: type.slug }; });
        var selectedFields = fields.filter(function (field) { return selectedFieldKeys.indexOf(field.key) !== -1; });
        var canPreview = selectedPosts.length > 0 && images.length > 0 && (selectedFieldKeys.length > 0 || useFeaturedImage);
        var imageOptions = [{ label: __('Automatisch / geen handmatige keuze', 'acf-image-auto-filler'), value: '0' }].concat(images.map(function (image) {
            return { label: image.title + ' (#' + image.id + ')', value: String(image.id) };
        }));

        function mutationData() {
            return {
                post_id: selectedPost,
                post_ids: selectedPosts,
                attachment_ids: images.map(function (image) { return image.id; }),
                field_keys: selectedFieldKeys,
                manual_mapping: buildManualMappingPayload(),
                overwrite_existing: overwrite,
                include_groups: includeGroups,
                use_featured_image: useFeaturedImage
            };
        }

        var currentSignature = useMemo(function () {
            return makeSignature(mutationData());
        }, [selectedPost, selectedPosts.join(','), images.map(function (img) { return img.id; }).join(','), selectedFieldKeys.join(','), JSON.stringify(fieldMapping), overwrite, includeGroups, useFeaturedImage]);

        var previewStale = !!preview && previewSignature !== currentSignature;
        var activeData = result || preview;
        var summary = SummaryFromData(activeData);
        var step = !selectedPosts.length ? 0 : (!selectedFieldKeys.length && !useFeaturedImage ? 1 : (!images.length ? 2 : (!preview || previewStale ? 3 : 4)));

        function apiError(err) {
            setError(err && err.message ? err.message : __('Er ging iets mis. Probeer opnieuw of controleer de invoer.', 'acf-image-auto-filler'));
        }

        function refreshRollbackStatus() {
            request('/rollback-status').then(function (response) {
                setHasRollback(!!response.hasRollback);
            }).catch(function () {});
        }

        function loadAuditLog() {
            if (canViewAuditLog) {
                request('/audit-log').then(function (response) {
                    setAuditLog(response.items || []);
                }).catch(function () {});
            } else {
                setAuditLog([]);
            }
            refreshRollbackStatus();
        }

        function clearPreview() {
            setPreview(null);
            setPreviewSignature('');
            setResult(null);
        }

        useEffect(function () {
            setLoading(true);
            request('/post-types')
                .then(function (response) {
                    var items = response.postTypes || [];
                    setPostTypes(items);
                    if (items.length && !selectedPostType) {
                        setSelectedPostType(items[0].slug);
                    }
                })
                .catch(apiError)
                .finally(function () { setLoading(false); });
            loadAuditLog();
        }, []);

        useEffect(function () {
            if (!selectedPostType) { return; }
            setLoading(true);
            setSelectedPosts([]);
            setFields([]);
            setSelectedFieldKeys([]);
            setFieldMapping({});
            clearPreview();
            request('/posts?post_type=' + encodeURIComponent(selectedPostType) + '&search=' + encodeURIComponent(postSearch))
                .then(function (response) { setPosts(response.posts || []); })
                .catch(apiError)
                .finally(function () { setLoading(false); });
        }, [selectedPostType, postSearch]);

        useEffect(function () {
            if (!selectedPost) {
                setFields([]);
                return;
            }
            setLoading(true);
            clearPreview();
            request('/fields?post_id=' + encodeURIComponent(selectedPost) + '&include_groups=' + (includeGroups ? '1' : '0'))
                .then(function (response) {
                    var items = response.fields || [];
                    setAcfActive(response.acfActive !== false);
                    setFields(items);
                    setSelectedFieldKeys(items.filter(function (field) { return !field.has_value; }).map(function (field) { return field.key; }));
                    setFieldMapping({});
                })
                .catch(apiError)
                .finally(function () { setLoading(false); });
        }, [selectedPost, includeGroups]);

        function openMediaLibrary() {
            if (!window.wp || !window.wp.media) {
                setError(__('De WordPress Media Library kon niet worden geladen.', 'acf-image-auto-filler'));
                return;
            }
            var frame = window.wp.media({
                title: __('Selecteer afbeeldingen', 'acf-image-auto-filler'),
                button: { text: __('Gebruik geselecteerde afbeeldingen', 'acf-image-auto-filler') },
                multiple: true,
                library: { type: 'image' }
            });
            frame.on('select', function () {
                var selected = frame.state().get('selection').toJSON().map(function (item) {
                    var filename = item.filename || item.title || '';
                    return {
                        id: item.id,
                        title: item.title || filename || ('#' + item.id),
                        filename: filename,
                        thumbnail: (item.sizes && item.sizes.thumbnail && item.sizes.thumbnail.url) ? item.sizes.thumbnail.url : item.url,
                        medium: (item.sizes && item.sizes.medium && item.sizes.medium.url) ? item.sizes.medium.url : item.url
                    };
                });
                setImages(selected);
                clearPreview();
            });
            frame.open();
        }

        function togglePost(postId, checked) {
            postId = parseInt(postId, 10) || 0;
            if (!postId) { return; }
            setSelectedPosts(function (current) {
                if (checked) {
                    return current.indexOf(postId) === -1 ? current.concat([postId]) : current;
                }
                return current.filter(function (id) { return id !== postId; });
            });
            clearPreview();
        }

        function selectVisiblePosts() {
            var visibleIds = posts.map(function (post) { return post.id; }).filter(Boolean);
            setSelectedPosts(function (current) {
                var next = current.slice();
                visibleIds.forEach(function (postId) {
                    if (next.indexOf(postId) === -1) { next.push(postId); }
                });
                return next;
            });
            clearPreview();
        }

        function deselectVisiblePosts() {
            var visibleIds = posts.map(function (post) { return post.id; }).filter(Boolean);
            setSelectedPosts(function (current) {
                return current.filter(function (postId) { return visibleIds.indexOf(postId) === -1; });
            });
            clearPreview();
        }

        function selectPostsByStatus(statuses) {
            statuses = statuses || [];
            var visibleIds = posts.filter(function (post) { return statuses.indexOf(post.status) !== -1; }).map(function (post) { return post.id; }).filter(Boolean);
            setSelectedPosts(function (current) {
                var next = current.slice();
                visibleIds.forEach(function (postId) {
                    if (next.indexOf(postId) === -1) { next.push(postId); }
                });
                return next;
            });
            clearPreview();
        }

        function toggleField(fieldKey, checked) {
            setSelectedFieldKeys(function (current) {
                if (checked) {
                    return current.indexOf(fieldKey) === -1 ? current.concat([fieldKey]) : current;
                }
                return current.filter(function (key) { return key !== fieldKey; });
            });
            setFieldMapping(function (mapping) {
                if (checked) { return mapping; }
                var copy = Object.assign({}, mapping);
                delete copy[fieldKey];
                return copy;
            });
            clearPreview();
        }

        function selectEmptyFields() {
            setSelectedFieldKeys(fields.filter(function (field) { return !field.has_value; }).map(function (field) { return field.key; }));
            clearPreview();
        }

        function selectAllFields() {
            setSelectedFieldKeys(fields.map(function (field) { return field.key; }));
            clearPreview();
        }

        function deselectAllFields() {
            setSelectedFieldKeys([]);
            setFieldMapping({});
            clearPreview();
        }

        function setFieldImage(fieldKey, attachmentId) {
            attachmentId = parseInt(attachmentId, 10) || 0;
            setFieldMapping(function (mapping) {
                var copy = Object.assign({}, mapping);
                if (attachmentId > 0) {
                    copy[fieldKey] = attachmentId;
                } else {
                    delete copy[fieldKey];
                }
                return copy;
            });
            if (attachmentId > 0) {
                setSelectedFieldKeys(function (current) {
                    return current.indexOf(fieldKey) === -1 ? current.concat([fieldKey]) : current;
                });
            }
            clearPreview();
        }

        function removeImage(imageId) {
            setImages(function (currentImages) {
                return currentImages.filter(function (image) { return image.id !== imageId; });
            });
            setFieldMapping(function (mapping) {
                var copy = {};
                Object.keys(mapping).forEach(function (key) {
                    if (mapping[key] !== imageId) { copy[key] = mapping[key]; }
                });
                return copy;
            });
            clearPreview();
        }

        function moveImage(index, direction) {
            setImages(function (currentImages) {
                var next = currentImages.slice();
                var target = index + direction;
                if (target < 0 || target >= next.length) { return currentImages; }
                var tmp = next[index];
                next[index] = next[target];
                next[target] = tmp;
                return next;
            });
            clearPreview();
        }

        function applyFilenameMatching() {
            if (!fields.length || !images.length) { return; }
            var next = {};
            fields.forEach(function (field) {
                var label = normalize(field.label + ' ' + field.name + ' ' + (field.path || ''));
                var match = images.find(function (image) {
                    var imageText = normalize((image.filename || '') + ' ' + (image.title || ''));
                    var fieldName = normalize(field.name);
                    var fieldLabel = normalize(field.label);
                    return imageText && (label.indexOf(imageText) !== -1 || (fieldName && imageText.indexOf(fieldName) !== -1) || (fieldLabel && imageText.indexOf(fieldLabel) !== -1));
                });
                if (match) { next[field.key] = match.id; }
            });
            setFieldMapping(next);
            setSelectedFieldKeys(fields.filter(function (field) { return !!next[field.key]; }).map(function (field) { return field.key; }));
            clearPreview();
        }

        function buildManualMappingPayload() {
            var payload = {};
            selectedPosts.forEach(function (postId) {
                Object.keys(fieldMapping).forEach(function (fieldKey) {
                    payload[selectedPosts.length > 1 ? String(postId) + ':' + fieldKey : fieldKey] = fieldMapping[fieldKey];
                });
            });
            return payload;
        }

        function runPreview() {
            if (!canPreview) { return; }
            var data = mutationData();
            setLoading(true);
            setError('');
            setResult(null);
            request('/preview', { method: 'POST', data: data })
                .then(function (response) {
                    setPreview(response);
                    setPreviewSignature(makeSignature(data));
                })
                .catch(apiError)
                .finally(function () { setLoading(false); });
        }

        function refreshFields() {
            if (!selectedPost) { return Promise.resolve(); }
            return request('/fields?post_id=' + encodeURIComponent(selectedPost) + '&include_groups=' + (includeGroups ? '1' : '0')).then(function (response) {
                setAcfActive(response.acfActive !== false);
                setFields(response.fields || []);
            });
        }

        function runFill() {
            setConfirmOpen(false);
            if (!canPreview || !preview || previewStale) { return; }
            setLoading(true);
            setError('');
            request('/fill', { method: 'POST', data: mutationData() })
                .then(function (response) {
                    setResult(response);
                    setPreview(null);
                    setPreviewSignature('');
                    setHasRollback(!!response.rollbackRunId);
                    loadAuditLog();
                    return refreshFields();
                })
                .catch(apiError)
                .finally(function () { setLoading(false); });
        }

        function rollbackLast() {
            setLoading(true);
            setError('');
            request('/rollback-last', { method: 'POST', data: {} })
                .then(function (response) {
                    setResult({ executed: true, filled: [], skipped: [], errors: response.errors || [], rolledBack: response.rolledBack || [] });
                    setHasRollback(!!response.hasRollback);
                    loadAuditLog();
                    return refreshFields();
                })
                .catch(apiError)
                .finally(function () { setLoading(false); });
        }

        function maybeRunFill() {
            if (!preview || previewStale) { return; }
            if (overwrite || selectedPosts.length > 1 || summary.overwrite > 0) {
                setConfirmOpen(true);
                return;
            }
            runFill();
        }

        function resetToContent() {
            setSelectedPosts([]);
            setFields([]);
            setSelectedFieldKeys([]);
            setFieldMapping({});
            setImages([]);
            clearPreview();
        }

        function resetToFields() {
            setSelectedFieldKeys([]);
            setFieldMapping({});
            clearPreview();
        }

        function resetToImages() {
            setImages([]);
            setFieldMapping({});
            clearPreview();
        }

        var autoTargetFieldCount = selectedFields.filter(function (field) { return overwrite || !field.has_value; }).length;

        var activeCard = step === 0 ? el(ContentCard, {
            postTypeOptions: postTypeOptions,
            selectedPostType: selectedPostType,
            setSelectedPostType: setSelectedPostType,
            postSearch: postSearch,
            setPostSearch: setPostSearch,
            posts: posts,
            selectedPosts: selectedPosts,
            togglePost: togglePost,
            selectVisiblePosts: selectVisiblePosts,
            deselectVisiblePosts: deselectVisiblePosts,
            selectPostsByStatus: selectPostsByStatus
        }) : (step === 1 ? el(FieldsCard, {
            selectedPost: selectedPost,
            fields: fields,
            selectedFieldKeys: selectedFieldKeys,
            toggleField: toggleField,
            includeGroups: includeGroups,
            setIncludeGroups: function (value) { setIncludeGroups(!!value); },
            useFeaturedImage: useFeaturedImage,
            setUseFeaturedImage: function (value) { setUseFeaturedImage(!!value); clearPreview(); },
            selectEmptyFields: selectEmptyFields,
            selectAllFields: selectAllFields,
            deselectAllFields: deselectAllFields,
            setPreviewImage: setPreviewImage
        }) : (step === 2 ? el(ImagesCard, {
            images: images,
            openMediaLibrary: openMediaLibrary,
            removeImage: removeImage,
            moveImage: moveImage,
            setPreviewImage: setPreviewImage,
            fieldCount: autoTargetFieldCount,
            selectedFieldCount: selectedFields.length,
            useFeaturedImage: useFeaturedImage,
            overwrite: overwrite
        }) : el(MappingCard, {
            fields: fields,
            selectedFieldKeys: selectedFieldKeys,
            images: images,
            imageOptions: imageOptions,
            fieldMapping: fieldMapping,
            mappingFilter: mappingFilter,
            setMappingFilter: setMappingFilter,
            setFieldImage: setFieldImage,
            overwrite: overwrite,
            setOverwrite: function (value) { setOverwrite(!!value); clearPreview(); },
            useFeaturedImage: useFeaturedImage,
            applyFilenameMatching: applyFilenameMatching,
            preview: preview,
            result: result,
            auditLog: auditLog,
            canViewAuditLog: canViewAuditLog,
            downloadCsv: downloadCsv,
            setPreviewImage: setPreviewImage
        })));

        return el('div', { className: UI.app },
            !acfActive && el(Notice, { status: 'warning', isDismissible: false }, __('Advanced Custom Fields is niet actief of niet volledig geladen. ACF-velden vullen is uitgeschakeld, maar featured-image-only kan nog worden gebruikt.', 'acf-image-auto-filler')),
            error && el(Notice, { status: 'error', onRemove: function () { setError(''); } }, error),
            loading && el('div', { className: UI.loading }, el(Spinner, null), el('span', null, __('Bezig...', 'acf-image-auto-filler'))),
            el(Stepper, {
                current: step,
                hasContent: selectedPosts.length > 0,
                hasFields: selectedFieldKeys.length > 0 || useFeaturedImage,
                hasImages: images.length > 0,
                hasPreview: !!preview,
                previewStale: previewStale,
                hasResult: !!result
            }),
            previewStale && el(Notice, { status: 'warning', isDismissible: false }, __('De preview is verouderd. Vernieuw de preview voordat je uitvoert.', 'acf-image-auto-filler')),

            el('div', { className: UI.mainFlow },
                selectedPosts.length > 0 && el(CompactSummary, {
                    selectedPosts: selectedPosts,
                    selectedFields: selectedFields,
                    images: images,
                    useFeaturedImage: useFeaturedImage,
                    onContent: resetToContent,
                    onFields: resetToFields,
                    onImages: resetToImages
                }),
                selectedPosts.length > 1 && step >= 3 && el(BatchNotice, { selectedPosts: selectedPosts, selectedFields: selectedFields, hasRollback: hasRollback }),
                activeCard
            ),

            (step >= 3 || hasRollback) && el(ActionBar, {
                canPreview: canPreview,
                loading: loading,
                onPreview: runPreview,
                onFill: maybeRunFill,
                preview: preview,
                previewStale: previewStale,
                summary: summary,
                selectedPosts: selectedPosts,
                selectedFields: selectedFields,
                images: images,
                useFeaturedImage: useFeaturedImage,
                hasRollback: hasRollback,
                onRollback: rollbackLast
            }),

            confirmOpen && el(Modal, { title: __('Wijzigingen uitvoeren?', 'acf-image-auto-filler'), onRequestClose: function () { setConfirmOpen(false); } },
                el('div', { className: UI.confirmContent },
                    el(Notice, { status: (overwrite || summary.overwrite > 0) ? 'warning' : 'info', isDismissible: false }, __('Controleer dit goed: na uitvoeren worden afbeeldingen gekoppeld aan ACF-velden. De laatste actie kan daarna worden teruggedraaid.', 'acf-image-auto-filler')),
                    el('ul', null,
                        el('li', null, selectedPosts.length + ' ' + __('post(s)', 'acf-image-auto-filler')),
                        el('li', null, summary.fill + ' ' + __('veld(en) worden gevuld', 'acf-image-auto-filler')),
                        el('li', null, summary.overwrite + ' ' + __('overschrijving(en)', 'acf-image-auto-filler')),
                        el('li', null, summary.skip + ' ' + __('overgeslagen item(s)', 'acf-image-auto-filler'))
                    ),
                    el('div', { className: UI.modalActions },
                        el(Button, { variant: 'secondary', onClick: function () { setConfirmOpen(false); } }, __('Annuleren', 'acf-image-auto-filler')),
                        el(Button, { variant: 'primary', onClick: runFill }, __('Ja, uitvoeren', 'acf-image-auto-filler'))
                    )
                )
            ),
            previewImage && el(Modal, { title: previewImage.title || __('Afbeeldingsvoorbeeld', 'acf-image-auto-filler'), onRequestClose: function () { setPreviewImage(null); } },
                el('div', { className: UI.largePreview }, el('img', { src: previewImage.medium || previewImage.thumbnail, alt: previewImage.title || '' }))
            )
        );
    }

    function CompactSummary(props) {
        return el('div', { className: UI.summary, 'aria-label': __('Korte samenvatting', 'acf-image-auto-filler') },
            el('span', null, props.selectedPosts.length + ' ' + __('post(s)', 'acf-image-auto-filler')),
            el('span', null, (props.selectedFields.length + (props.useFeaturedImage ? 1 : 0)) + ' ' + __('veld(en)', 'acf-image-auto-filler')),
            el('span', null, props.images.length + ' ' + __('afbeelding(en)', 'acf-image-auto-filler')),
            el('div', { className: UI.summaryActions },
                el(Button, { variant: 'link', onClick: props.onContent }, __('Content wijzigen', 'acf-image-auto-filler')),
                el(Button, { variant: 'link', onClick: props.onFields }, __('Velden wijzigen', 'acf-image-auto-filler')),
                props.images.length > 0 && el(Button, { variant: 'link', onClick: props.onImages }, __('Afbeeldingen wijzigen', 'acf-image-auto-filler'))
            )
        );
    }

    function BatchNotice(props) {
        return el('div', { className: UI.batchWarning, role: 'region', 'aria-label': __('Batchmodus waarschuwing', 'acf-image-auto-filler') },
            el(Notice, { status: 'warning', isDismissible: false },
                el('strong', null, __('Batchmodus actief', 'acf-image-auto-filler')),
                el('p', null, __('Je past meerdere posts tegelijk aan. Controleer extra goed of deze posts dezelfde ACF-velden gebruiken.', 'acf-image-auto-filler')),
                el('ul', null,
                    el('li', null, props.selectedPosts.length + ' ' + __('geselecteerde post(s)', 'acf-image-auto-filler')),
                    el('li', null, props.selectedFields.length + ' ' + __('geselecteerde veld(en)', 'acf-image-auto-filler')),
                    el('li', null, props.hasRollback ? __('Laatste actie terugdraaien is beschikbaar na uitvoeren.', 'acf-image-auto-filler') : __('Rollbackstatus wordt gecontroleerd voordat je uitvoert.', 'acf-image-auto-filler'))
                )
            )
        );
    }

    function ContentCard(props) {
        var selectedVisibleCount = props.posts.filter(function (post) { return props.selectedPosts.indexOf(post.id) !== -1; }).length;
        var publishedCount = props.posts.filter(function (post) { return post.status === 'publish'; }).length;
        var draftCount = props.posts.filter(function (post) { return post.status === 'draft' || post.status === 'pending' || post.status === 'future'; }).length;
        return el(Card, { className: UI.cardWide, id: 'aiaf-content' },
            el(CardHeader, null,
                el('div', null,
                    el('h2', null, __('Content kiezen', 'acf-image-auto-filler')),
                    el('p', null, __('Kies eerst het post type en selecteer daarna één of meerdere items. Je kunt alle zichtbare items in één keer selecteren.', 'acf-image-auto-filler'))
                )
            ),
            el(CardBody, null,
                el('div', { className: UI.contentFields },
                    el(SelectControl, { label: __('Post type', 'acf-image-auto-filler'), value: props.selectedPostType, options: props.postTypeOptions, onChange: props.setSelectedPostType }),
                    el(TextControl, { label: __('Zoeken', 'acf-image-auto-filler'), value: props.postSearch, onChange: props.setPostSearch, placeholder: __('Zoek op titel...', 'acf-image-auto-filler') })
                ),
                props.posts.length === 0 && el('div', { className: UI.emptyState }, __('Geen bewerkbare content gevonden. Kies een ander post type of pas je zoekterm aan.', 'acf-image-auto-filler')),
                props.posts.length > 0 && el('div', { className: UI.contentToolbar, role: 'region', 'aria-label': __('Content selectie-acties', 'acf-image-auto-filler') },
                    el('div', { className: UI.contentToolbarCount },
                        el('strong', null, selectedVisibleCount + ' / ' + props.posts.length),
                        el('span', null, __('zichtbare items geselecteerd', 'acf-image-auto-filler'))
                    ),
                    el('div', { className: UI.contentToolbarActions },
                        el(Button, { variant: 'secondary', onClick: props.selectVisiblePosts }, __('Alle zichtbare selecteren', 'acf-image-auto-filler')),
                        el(Button, { variant: 'secondary', onClick: props.deselectVisiblePosts, disabled: selectedVisibleCount === 0 }, __('Zichtbare deselecteren', 'acf-image-auto-filler')),
                        publishedCount > 0 && el(Button, { variant: 'secondary', onClick: function () { props.selectPostsByStatus(['publish']); } }, __('Alle gepubliceerde selecteren', 'acf-image-auto-filler')),
                        draftCount > 0 && el(Button, { variant: 'secondary', onClick: function () { props.selectPostsByStatus(['draft', 'pending', 'future']); } }, __('Alle concepten selecteren', 'acf-image-auto-filler'))
                    )
                ),
                props.posts.length > 0 && el('div', { className: UI.postList, role: 'group', 'aria-label': __('Beschikbare posts', 'acf-image-auto-filler') }, props.posts.map(function (post) {
                    return el('label', { className: props.selectedPosts.indexOf(post.id) !== -1 ? UI.postOptionSelected : UI.postOption, key: post.id },
                        el('input', { type: 'checkbox', checked: props.selectedPosts.indexOf(post.id) !== -1, onChange: function (event) { props.togglePost(post.id, event.target.checked); } }),
                        el('span', null, el('strong', null, post.title), el('small', null, '#' + post.id + ' · ' + post.status + (post.date ? ' · ' + post.date : '')))
                    );
                }))
            )
        );
    }

    function FieldsCard(props) {
        return el(Card, { className: UI.cardNarrow, id: 'aiaf-fields' },
            el(CardHeader, null, el('h2', null, __('Velden kiezen', 'acf-image-auto-filler'))),
            el(CardBody, null,
                el('div', { className: UI.optionStrip },
                    el(ToggleControl, { label: __('Velden binnen ACF-groepen meenemen', 'acf-image-auto-filler'), checked: props.includeGroups, onChange: props.setIncludeGroups }),
                    el(ToggleControl, { label: __('Uitgelichte afbeelding optioneel vullen', 'acf-image-auto-filler'), checked: props.useFeaturedImage, onChange: props.setUseFeaturedImage })
                ),
                !props.selectedPost && el('div', { className: UI.emptyState }, __('Selecteer eerst content. Daarna worden de velden van de eerste geselecteerde post getoond.', 'acf-image-auto-filler')),
                props.selectedPost > 0 && props.fields.length === 0 && el('div', { className: UI.emptyState }, __('Geen geschikte ACF-afbeeldingsvelden gevonden.', 'acf-image-auto-filler')),
                props.fields.length > 0 && el('div', null,
                    el(Flex, { className: UI.fieldActions, justify: 'flex-start', gap: 2, wrap: true },
                        el(FlexItem, null, el(Button, { variant: 'secondary', onClick: props.selectEmptyFields }, __('Alle lege velden selecteren', 'acf-image-auto-filler'))),
                        el(FlexItem, null, el(Button, { variant: 'secondary', onClick: props.selectAllFields }, __('Alles selecteren', 'acf-image-auto-filler'))),
                        el(FlexItem, null, el(Button, { variant: 'secondary', onClick: props.deselectAllFields }, __('Alles deselecteren', 'acf-image-auto-filler')))
                    ),
                    el('div', { className: UI.fieldList }, props.fields.map(function (field) {
                        var selected = props.selectedFieldKeys.indexOf(field.key) !== -1;
                        return el('div', { className: selected ? UI.fieldRowSelected : UI.fieldRow, key: field.key },
                            el('div', { className: UI.fieldMain },
                                el(CheckboxControl, { label: field.label, checked: selected, onChange: function (checked) { props.toggleField(field.key, checked); } }),
                                el('div', { className: UI.fieldBadges },
                                    el(StatusBadge, { status: field.has_value ? 'existing' : 'success' }, field.has_value ? __('Gevuld', 'acf-image-auto-filler') : __('Leeg', 'acf-image-auto-filler')),
                                    field.scope === 'group' && el(StatusBadge, { status: 'neutral' }, __('Veldgroep', 'acf-image-auto-filler'))
                                ),
                                el('details', { className: UI.techDetails },
                                    el('summary', null, __('Technische details', 'acf-image-auto-filler')),
                                    el('code', null, field.name),
                                    el('code', null, field.path || field.key)
                                )
                            ),
                            field.current_thumbnail && el('button', { type: 'button', className: UI.currentThumb, onClick: function () { props.setPreviewImage({ title: field.current_title, medium: field.current_thumbnail }); }, 'aria-label': __('Huidige afbeelding bekijken', 'acf-image-auto-filler') + ': ' + (field.current_title || field.label) }, el('img', { src: field.current_thumbnail, alt: '' }))
                        );
                    }))
                )
            )
        );
    }

    function ImagesCard(props) {
        var targetCount = props.fieldCount || 0;
        var autoImageCount = Math.max(0, props.images.length - (props.useFeaturedImage ? 1 : 0));
        var tooMany = targetCount > 0 && autoImageCount > targetCount;
        var tooFew = targetCount > 0 && props.images.length > 0 && autoImageCount < targetCount;
        return el(Card, { className: UI.cardNarrow, id: 'aiaf-images' },
            el(CardHeader, null, el('h2', null, __('Afbeeldingen kiezen', 'acf-image-auto-filler'))),
            el(CardBody, null,
                el(Button, { variant: 'primary', onClick: props.openMediaLibrary }, props.images.length ? __('Afbeeldingen wijzigen', 'acf-image-auto-filler') : __('Afbeeldingen kiezen uit Media Library', 'acf-image-auto-filler')),
                props.images.length === 0 && el('div', { className: UI.emptyState }, __('Nog geen afbeeldingen geselecteerd.', 'acf-image-auto-filler')),
                props.selectedFieldCount > targetCount && !props.overwrite && el(Notice, { status: 'info', isDismissible: false }, __('Gevulde ACF-velden worden niet meegeteld zolang vervangen uit staat. Automatische afbeeldingen worden alleen aan lege geselecteerde velden gekoppeld.', 'acf-image-auto-filler')),
                tooMany && el(Notice, { status: 'warning', isDismissible: false }, __('Er zijn meer automatische ACF-afbeeldingen dan automatisch te vullen ACF-velden. Extra afbeeldingen worden overgeslagen tenzij je handmatig mapt.', 'acf-image-auto-filler')),
                tooFew && el(Notice, { status: 'info', isDismissible: false }, __('Er zijn minder automatische ACF-afbeeldingen dan automatisch te vullen velden. Niet alle velden krijgen automatisch een afbeelding.', 'acf-image-auto-filler')),
                props.images.length > 0 && el('div', { className: UI.imageGrid }, props.images.map(function (image, index) {
                    return el('div', { className: UI.imageTile, key: image.id },
                        el('button', { type: 'button', className: UI.imagePreviewButton, onClick: function () { props.setPreviewImage(image); }, 'aria-label': __('Afbeelding groter bekijken', 'acf-image-auto-filler') + ': ' + (image.title || ('#' + image.id)) }, el('img', { src: image.thumbnail, alt: image.title || '' })),
                        el('strong', null, image.title),
                        el('small', null, '#' + image.id),
                        el('div', { className: UI.imageActions },
                            el(Button, { variant: 'secondary', size: 'small', disabled: index === 0, onClick: function () { props.moveImage(index, -1); } }, __('Omhoog', 'acf-image-auto-filler')),
                            el(Button, { variant: 'secondary', size: 'small', disabled: index === props.images.length - 1, onClick: function () { props.moveImage(index, 1); } }, __('Omlaag', 'acf-image-auto-filler')),
                            el(Button, { variant: 'link', isDestructive: true, onClick: function () { props.removeImage(image.id); } }, __('Verwijderen', 'acf-image-auto-filler'))
                        )
                    );
                }))
            )
        );
    }

    function MappingCard(props) {
        var filterOptions = [
            { label: __('Alles tonen', 'acf-image-auto-filler'), value: 'all' },
            { label: __('Alleen geselecteerde velden', 'acf-image-auto-filler'), value: 'selected' },
            { label: __('Alleen overschrijvingen', 'acf-image-auto-filler'), value: 'overwrite' },
            { label: __('Alleen lege velden', 'acf-image-auto-filler'), value: 'empty' },
            { label: __('Alleen zonder gekoppelde afbeelding', 'acf-image-auto-filler'), value: 'missing' }
        ];
        var autoImages = props.useFeaturedImage ? props.images.slice(1) : props.images;
        var autoImageIndex = 0;
        var rows = props.fields.map(function (field, index) {
            var selectedIndex = props.selectedFieldKeys.indexOf(field.key);
            var selected = selectedIndex !== -1;
            var manual = props.fieldMapping[field.key] || 0;
            var manualImage = props.images.find(function (image) { return image.id === manual; }) || null;
            var canReceiveImage = selected && (props.overwrite || !field.has_value);
            var autoImage = null;
            if (selected && !manual && canReceiveImage) {
                autoImage = autoImages[autoImageIndex] || null;
                if (autoImage) { autoImageIndex++; }
            }
            var chosenImage = canReceiveImage ? (manualImage || autoImage) : null;
            var willOverwrite = selected && field.has_value && !!chosenImage && !!props.overwrite;
            var missing = selected && canReceiveImage && !chosenImage;
            var status = !selected ? 'disabled' : (!canReceiveImage ? 'existing' : (missing ? 'missing' : (willOverwrite ? 'overwrite' : 'fill')));
            return { field: field, index: index, selectedIndex: selectedIndex, selected: selected, manual: manual, chosenImage: chosenImage, willOverwrite: willOverwrite, missing: missing, status: status, canReceiveImage: canReceiveImage };
        }).filter(function (row) {
            if (props.mappingFilter === 'selected') { return row.selected; }
            if (props.mappingFilter === 'overwrite') { return row.willOverwrite; }
            if (props.mappingFilter === 'empty') { return !row.field.has_value; }
            if (props.mappingFilter === 'missing') { return row.missing; }
            return true;
        });

        function statusLabel(row) {
            if (!row.selected) { return __('Niet geselecteerd', 'acf-image-auto-filler'); }
            if (!row.canReceiveImage && row.field.has_value) { return __('Wordt overgeslagen: heeft al afbeelding', 'acf-image-auto-filler'); }
            if (row.missing) { return __('Controle nodig', 'acf-image-auto-filler'); }
            if (row.willOverwrite) { return __('Wordt overschreven', 'acf-image-auto-filler'); }
            if (row.field.has_value) { return __('Ongewijzigd of handmatig gekozen', 'acf-image-auto-filler'); }
            return __('Wordt gevuld', 'acf-image-auto-filler');
        }

        function statusType(row) {
            if (!row.selected) { return 'neutral'; }
            if (!row.canReceiveImage && row.field.has_value) { return 'neutral'; }
            if (row.missing || row.willOverwrite) { return 'warning'; }
            return 'success';
        }

        return el(Card, { className: UI.cardWide, id: 'aiaf-mapping' },
            el(CardHeader, null, el('div', null, el('h2', null, __('Koppeling controleren', 'acf-image-auto-filler')), el('p', null, __('Controleer de koppeling voordat je de wijzigingen opslaat.', 'acf-image-auto-filler')))),
            el(CardBody, null,
                el('div', { className: UI.mappingToolbar },
                    el(Button, { variant: 'secondary', onClick: props.applyFilenameMatching, disabled: !props.fields.length || !props.images.length }, __('Match op bestandsnaam', 'acf-image-auto-filler')),
                    el(Button, { variant: 'secondary', onClick: function () { props.preview && props.downloadCsv(props.preview); }, disabled: !props.preview }, __('CSV downloaden', 'acf-image-auto-filler')),
                    el(ToggleControl, { label: __('Bestaande afbeeldingen vervangen', 'acf-image-auto-filler'), help: __('Alleen aanzetten als je gevulde afbeeldingsvelden bewust wilt vervangen.', 'acf-image-auto-filler'), checked: props.overwrite, onChange: props.setOverwrite }),
                    el(SelectControl, { label: __('Mapping filteren', 'acf-image-auto-filler'), value: props.mappingFilter, options: filterOptions, onChange: props.setMappingFilter })
                ),
                props.fields.length === 0 && el('div', { className: UI.emptyState }, __('Nog geen velden om te koppelen. Kies eerst content en velden.', 'acf-image-auto-filler')),
                props.fields.length > 0 && rows.length === 0 && el('div', { className: UI.emptyState }, __('Geen velden binnen dit filter. Kies een ander filter om meer regels te zien.', 'acf-image-auto-filler')),
                props.fields.length > 0 && rows.length > 0 && el('div', { className: UI.mappingTableWrap },
                    el('table', { className: UI.mappingTable },
                        el('caption', null, __('Controleer per veld welke afbeelding wordt gekoppeld voordat je uitvoert.', 'acf-image-auto-filler')),
                        el('thead', null, el('tr', null,
                            el('th', { scope: 'col' }, __('Veld', 'acf-image-auto-filler')),
                            el('th', { scope: 'col' }, __('Huidig', 'acf-image-auto-filler')),
                            el('th', { scope: 'col' }, __('Nieuwe afbeelding', 'acf-image-auto-filler')),
                            el('th', { scope: 'col' }, __('Status', 'acf-image-auto-filler')),
                            el('th', { scope: 'col' }, __('Handmatige keuze', 'acf-image-auto-filler'))
                        )),
                        el('tbody', null, rows.map(function (row) {
                            var field = row.field;
                            return el('tr', { key: field.key, className: row.selected ? '' : 'is-disabled' },
                                el('td', { 'data-label': __('Veld', 'acf-image-auto-filler') }, el('strong', null, field.label), el('small', null, field.scope === 'group' ? __('Veldgroep', 'acf-image-auto-filler') : __('Normaal veld', 'acf-image-auto-filler')),
                                    el('details', { className: UI.techDetails }, el('summary', null, __('Technische details', 'acf-image-auto-filler')), el('code', null, field.name), el('code', null, field.path || field.key))
                                ),
                                el('td', { 'data-label': __('Huidig', 'acf-image-auto-filler') }, el(StatusBadge, { status: field.has_value ? 'existing' : 'success' }, field.has_value ? __('Heeft al afbeelding', 'acf-image-auto-filler') : __('Leeg', 'acf-image-auto-filler'))),
                                el('td', { 'data-label': __('Nieuwe afbeelding', 'acf-image-auto-filler') }, row.selected && row.chosenImage ? el('button', { type: 'button', className: UI.inlineImageButton, onClick: function () { props.setPreviewImage(row.chosenImage); }, 'aria-label': __('Nieuwe afbeelding groter bekijken', 'acf-image-auto-filler') + ': ' + row.chosenImage.title }, el('img', { src: row.chosenImage.thumbnail, alt: '' }), el('span', null, row.chosenImage.title)) : el('span', { className: UI.muted }, row.selected ? (!row.canReceiveImage && row.field.has_value ? __('Wordt overgeslagen', 'acf-image-auto-filler') : __('Nog geen afbeelding gekoppeld', 'acf-image-auto-filler')) : __('Niet geselecteerd', 'acf-image-auto-filler'))),
                                el('td', { 'data-label': __('Status', 'acf-image-auto-filler') }, el(StatusBadge, { status: statusType(row) }, statusLabel(row))),
                                el('td', { 'data-label': __('Handmatige keuze', 'acf-image-auto-filler') }, el(SelectControl, { label: __('Afbeelding kiezen voor', 'acf-image-auto-filler') + ' ' + field.label, hideLabelFromVision: true, value: String(row.manual || 0), options: props.imageOptions, onChange: function (value) { props.setFieldImage(field.key, value); } }))
                            );
                        }))
                    )
                ),
                el(ResultTabs, { preview: props.preview, result: props.result, auditLog: props.auditLog, canViewAuditLog: props.canViewAuditLog })
            )
        );
    }

    function ActionBar(props) {
        return el('div', { className: UI.actionBar, role: 'region', 'aria-label': __('Actiebalk', 'acf-image-auto-filler'), 'aria-live': 'polite' },
            el('div', { className: UI.actionSummary },
                el('strong', null, props.selectedPosts.length + ' ' + __('post(s)', 'acf-image-auto-filler')),
                el('span', null, (props.selectedFields.length + (props.useFeaturedImage ? 1 : 0)) + ' ' + __('veld(en)', 'acf-image-auto-filler')),
                el('span', null, props.images.length + ' ' + __('afbeelding(en)', 'acf-image-auto-filler')),
                props.preview && !props.previewStale && el(StatusBadge, { status: props.summary.errors ? 'error' : 'ready' }, props.summary.fill + ' ' + __('wijziging(en) klaar', 'acf-image-auto-filler')),
                props.previewStale && el(StatusBadge, { status: 'stale' }, __('Preview verouderd', 'acf-image-auto-filler'))
            ),
            el('div', { className: UI.actionButtons },
                el(Button, { variant: 'secondary', onClick: props.onPreview, disabled: !props.canPreview || props.loading }, props.preview ? __('Preview vernieuwen', 'acf-image-auto-filler') : __('Preview maken', 'acf-image-auto-filler')),
                props.hasRollback && el(Button, { variant: 'secondary', onClick: props.onRollback, disabled: props.loading }, __('Laatste actie terugdraaien', 'acf-image-auto-filler')),
                el(Button, { variant: 'primary', onClick: props.onFill, disabled: !props.preview || props.previewStale || props.loading }, __('Wijzigingen uitvoeren', 'acf-image-auto-filler'))
            )
        );
    }

    function ResultTabs(props) {
        var active = props.result || props.preview;
        return el('div', { className: UI.controlPanel },
            !active && el('div', { className: UI.previewPlaceholder }, __('Maak eerst een preview om de koppeling te controleren.', 'acf-image-auto-filler')),
            active && el('div', null,
                el('h3', null, props.result ? __('Resultaat', 'acf-image-auto-filler') : __('Preview', 'acf-image-auto-filler')),
                el(ResultMessages, { data: active }),
                el(ResultList, { data: active })
            ),
            props.canViewAuditLog && props.auditLog && props.auditLog.length > 0 && el('details', { className: UI.auditDetails },
                el('summary', null, __('Actiegeschiedenis', 'acf-image-auto-filler')),
                el(AuditLog, { items: props.auditLog || [] })
            )
        );
    }

    function ResultMessages(props) {
        var data = props.data || {};
        return el('div', { className: UI.resultMessages },
            data.rollbackRunId && el(Notice, { status: 'success', isDismissible: false }, __('Rollback-data opgeslagen voor deze actie.', 'acf-image-auto-filler')),
            data.rolledBack && data.rolledBack.length > 0 && el(Notice, { status: 'success', isDismissible: false }, data.rolledBack.length + ' ' + __('item(s) teruggedraaid.', 'acf-image-auto-filler')),
            data.errors && data.errors.length > 0 && el(Notice, { status: 'error', isDismissible: false }, data.errors.join(' ')),
            data.skipped && data.skipped.length > 0 && el(Notice, { status: 'warning', isDismissible: false }, data.skipped.map(function (item) { return item.reason; }).join(' ')),
            (!data.errors || !data.errors.length) && (!data.skipped || !data.skipped.length) && el(Notice, { status: 'success', isDismissible: false }, __('Alles ziet er goed uit. Er zijn geen fouten of overgeslagen velden.', 'acf-image-auto-filler'))
        );
    }

    function ResultList(props) {
        var data = props.data || {};
        var filled = data.filled || [];
        var skipped = data.skipped || [];
        var rolledBack = data.rolledBack || [];
        return el('div', { className: UI.resultList },
            filled.length > 0 && filled.map(function (item, index) {
                return el('div', { className: UI.resultRow, key: item.post_id + '-' + item.field_key + '-' + item.attachment_id + '-' + index },
                    el('div', { className: UI.resultThumb }, item.thumbnail && el('img', { src: item.thumbnail, alt: '' })),
                    el('div', null, el('strong', null, item.field_label), el('small', null, '#' + item.post_id + ' · ' + __('Afbeeldings-ID', 'acf-image-auto-filler') + ' #' + item.attachment_id + (item.attachment_title ? ' · ' + item.attachment_title : ''))),
                    el(StatusBadge, { status: item.executed ? 'success' : (item.will_overwrite ? 'overwrite' : 'fill') }, item.executed ? __('Gevuld', 'acf-image-auto-filler') : (item.will_overwrite ? __('Wordt overschreven', 'acf-image-auto-filler') : __('Wordt gevuld', 'acf-image-auto-filler')))
                );
            }),
            skipped.length > 0 && skipped.map(function (item, index) {
                return el('div', { className: UI.resultRow + ' is-skipped', key: 'skip-' + index },
                    el('div', null, el('strong', null, (item.post_id ? '#' + item.post_id + ' · ' : '') + (item.field_label || __('Overgeslagen', 'acf-image-auto-filler'))), el('small', null, item.reason)),
                    el(StatusBadge, { status: item.status === 'unchanged' ? 'neutral' : 'warning' }, item.status === 'unchanged' ? __('Ongewijzigd', 'acf-image-auto-filler') : __('Overgeslagen', 'acf-image-auto-filler'))
                );
            }),
            rolledBack.length > 0 && rolledBack.map(function (item, index) {
                return el('div', { className: UI.resultRow, key: 'rollback-' + index },
                    el('div', null, el('strong', null, '#' + item.post_id + ' · ' + (item.field_label || item.field_key)), el('small', null, __('Teruggezet naar vorige attachment ID:', 'acf-image-auto-filler') + ' ' + (item.previous_attachment_id || 'leeg'))),
                    el(StatusBadge, { status: 'success' }, __('Rollback', 'acf-image-auto-filler'))
                );
            })
        );
    }

    function AuditLog(props) {
        var items = props.items || [];
        if (!items.length) { return el('p', { className: UI.emptyState }, __('Nog geen actiegeschiedenis beschikbaar.', 'acf-image-auto-filler')); }
        return el('div', { className: UI.auditList }, items.map(function (item) {
            var date = item.created_at ? new Date(item.created_at * 1000).toLocaleString() : '';
            return el('div', { className: UI.auditRow, key: item.run_id }, el('strong', null, item.run_id), el('span', null, date), el('span', null, item.item_count + ' ' + __('wijziging(en)', 'acf-image-auto-filler')));
        }));
    }

    wp.domReady(function () {
        var root = document.getElementById('acf-image-auto-filler-app');
        if (!root) {
            return;
        }

        try {
            if (typeof render === 'function') {
                render(el(App), root);
            } else {
                root.innerHTML = '<div class="notice notice-error inline"><p>De WordPress React renderer kon niet worden gestart.</p></div>';
            }
        } catch (error) {
            if (window.console && window.console.error) {
                window.console.error('ACF Image Auto Filler admin app error:', error);
            }
            root.innerHTML = '<div class="notice notice-error inline"><p>De admin-interface kon niet worden gestart. Controleer de browser-console voor de technische foutmelding.</p></div>';
        }
    });
})(window.wp || {});
