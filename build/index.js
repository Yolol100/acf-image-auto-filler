(function (wp) {
    'use strict';

    function replaceChildren(node, children) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
        children.forEach(function (child) {
            node.appendChild(child);
        });
    }

    function createElement(tagName, className, text) {
        var node = document.createElement(tagName);
        if (className) {
            node.className = className;
        }
        if (typeof text === 'string') {
            node.textContent = text;
        }
        return node;
    }

    function showStaticError(root, title, message, compact) {
        var shell = createElement('div', compact ? 'notice notice-error inline' : 'aiaf-loading-shell');

        if (compact) {
            var paragraph = createElement('p', '', message);
            shell.appendChild(paragraph);
            replaceChildren(root, [shell]);
            return;
        }

        var card = createElement('div', 'aiaf-loading-card aiaf-loading-card--error');
        card.setAttribute('role', 'alert');

        var header = createElement('div', 'aiaf-loading-header');
        var mark = createElement('span', 'aiaf-loading-mark aiaf-loading-mark--error', '!');
        mark.setAttribute('aria-hidden', 'true');
        header.appendChild(mark);
        header.appendChild(createElement('span', 'aiaf-loading-kicker', 'ACF Image Auto Filler'));

        var copy = createElement('div', 'aiaf-loading-copy');
        copy.appendChild(createElement('strong', '', title));
        copy.appendChild(createElement('span', '', message));

        card.appendChild(header);
        card.appendChild(copy);
        shell.appendChild(card);
        replaceChildren(root, [shell]);
    }

    if (!wp || !wp.element || !wp.components || !wp.apiFetch || !wp.i18n || !wp.domReady) {
        var fallbackRoot = document.getElementById('acf-image-auto-filler-app');
        if (fallbackRoot) {
            showStaticError(
                fallbackRoot,
                'Interface niet geladen',
                'De WordPress React-onderdelen konden niet worden geladen. Herlaad de pagina of controleer of WordPress scripts correct worden geladen.',
                false
            );
        }
        return;
    }

    var el = wp.element.createElement;
    var useEffect = wp.element.useEffect;
    var useMemo = wp.element.useMemo;
    var useRef = wp.element.useRef;
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
    function NativeSelectField(props) {
        props = props || {};
        var id = props.id || ('aiaf-native-select-' + String(props.label || '').replace(/[^a-z0-9]+/gi, '-').toLowerCase());
        var className = 'aiaf-native-select-control';
        if (props.className) {
            className += ' ' + props.className;
        }
        return el('div', { className: className },
            el('label', { htmlFor: id }, props.label),
            el('div', { className: 'aiaf-native-select-wrap' },
                el('select', { id: id, value: props.value || '', disabled: !!props.disabled, onChange: function (event) { if (props.onChange) { props.onChange(event.target.value); } } },
                    (props.options || []).map(function (option) { return el('option', { key: option.value, value: option.value }, option.label); })
                )
            )
        );
    }
    var TextControl = components.TextControl || TextControlFallback;
    var Modal = components.Modal || passthrough('div', 'components-modal__frame');
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
    var canMutateTool = settings.canMutateTool !== false;
    var woocommerceActive = !!settings.woocommerceActive;
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


    function normalizeResultText(text) {
        if (!text) { return ''; }
        var value = String(text);
        var replacements = {
            'Selected fields unavailable': 'Geselecteerde velden niet beschikbaar',
            'The selected ACF image fields are not available for this post.': 'De geselecteerde ACF Image fields zijn niet beschikbaar voor dit item.',
            'No eligible fields': 'Geen geschikte afbeeldingsvelden',
            'No eligible ACF image fields were found for this post.': 'Er zijn geen geschikte ACF Image fields gevonden voor dit item.',
            'No eligible ACF image fields were found for this item.': 'Er zijn geen geschikte ACF Image fields gevonden voor dit item.',
            'ACF is not active or required ACF functions are unavailable.': 'ACF is niet actief of vereiste ACF-functies zijn niet beschikbaar.',
            'No valid image attachments were selected.': 'Er zijn geen geldige afbeeldingen geselecteerd.',
            'Field already has a value.': 'Dit veld heeft al een waarde.',
            'No image selected for this field.': 'Voor dit veld is geen afbeelding geselecteerd.',
            'Extra images': 'Extra afbeeldingen',
            'Could not update field': 'Veld kon niet worden bijgewerkt',
            'Featured image': 'Uitgelichte afbeelding',
            'Featured image already contains this image.': 'Uitgelichte afbeelding bevat deze afbeelding al.',
            'Featured image already has a value.': 'Uitgelichte afbeelding heeft al een waarde.',
            'Featured image already has a value': 'Uitgelichte afbeelding heeft al een waarde.',
            'Featured image already contains this image': 'Uitgelichte afbeelding bevat deze afbeelding al.'
        };
        Object.keys(replacements).sort(function (a, b) {
            return b.length - a.length;
        }).forEach(function (needle) {
            value = value.split(needle).join(replacements[needle]);
        });
        value = value.replace(/(\d+) selected image\(s\) were not used because there were not enough eligible fields\./, '$1 geselecteerde afbeelding(en) zijn niet gebruikt omdat er niet genoeg geschikte velden zijn.');
        return value;
    }

    function resultItemMeta(item) {
        var parts = [];
        if (item.attachment_id) { parts.push(__('Afbeelding', 'acf-image-auto-filler') + ' #' + item.attachment_id); }
        if (item.attachment_title) { parts.push(item.attachment_title); }
        if (item.post_title) { parts.push(item.post_title); }
        return parts.join(' · ');
    }

    function LoadingOverlay(props) {
        var className = 'aiaf-loading-shell aiaf-loading-shell--overlay';
        if (props && props.fullscreen) {
            className += ' aiaf-loading-shell--fullscreen';
        }
        return el('div', { className: className, role: 'status', 'aria-live': 'polite' },
            el('div', { className: 'aiaf-loading-card' },
                el('div', { className: 'aiaf-loading-header' },
                    el('span', { className: 'aiaf-loading-mark', 'aria-hidden': 'true' }, el('span', { className: 'aiaf-loading-spinner' })),
                    el('span', { className: 'aiaf-loading-kicker' }, __('ACF Image Auto Filler', 'acf-image-auto-filler'))
                ),
                el('div', { className: 'aiaf-loading-copy' },
                    el('strong', null, props && props.title ? props.title : __('Gegevens laden', 'acf-image-auto-filler')),
                    el('span', null, props && props.message ? props.message : __('Even geduld. De interface blijft geblokkeerd tot de huidige data klaar is.', 'acf-image-auto-filler'))
                ),
                el('div', { className: 'aiaf-loading-progress', 'aria-hidden': 'true' }, el('span', null))
            )
        );
    }

    function Stepper(props) {
        var steps = [
            { label: __('Content', 'acf-image-auto-filler'), complete: props.hasContent },
            { label: props.featuredOnly ? __('Doel', 'acf-image-auto-filler') : __('Velden', 'acf-image-auto-filler'), complete: props.hasFields },
            { label: __('Afbeeldingen gebruikt', 'acf-image-auto-filler'), complete: props.hasImages },
            { label: __('Controle', 'acf-image-auto-filler'), complete: props.hasPreview && !props.previewStale },
            { label: __('Uitvoeren', 'acf-image-auto-filler'), complete: props.hasResult }
        ];

        return el('ol', { className: UI.stepper, 'aria-label': __('Workflow stappen', 'acf-image-auto-filler') }, steps.map(function (step, index) {
            var enabled = index <= props.progress;
            var state = index === props.current ? 'is-current' : (enabled ? 'is-complete' : 'is-disabled');
            var content = [
                el('span', { className: UI.stepNumber, 'aria-hidden': 'true', key: 'number' }, String(index + 1)),
                el('span', { className: UI.stepText, key: 'text' }, step.label)
            ];
            return el('li', { key: step.label, className: state, 'aria-current': index === props.current ? 'step' : undefined },
                enabled && index !== props.current ?
                    el('button', { type: 'button', className: 'aiaf-step-button', onClick: function () { props.onStep(index); }, disabled: !!props.loading, 'aria-label': __('Ga naar stap', 'acf-image-auto-filler') + ' ' + String(index + 1) + ': ' + step.label }, content) :
                    el('span', { className: 'aiaf-step-static' }, content)
            );
        }));
    }

    function StepNavigation(props) {
        if (props.current <= 0 && props.current >= props.progress) {
            return null;
        }

        return el('nav', { className: 'aiaf-step-navigation', 'aria-label': __('Stapnavigatie', 'acf-image-auto-filler') },
            el('div', { className: 'aiaf-step-navigation-actions' },
                props.current > 0 && el(Button, { className: 'aiaf-step-navigation-button', variant: 'secondary', onClick: props.onPrevious, disabled: !!props.loading }, __('Vorige stap', 'acf-image-auto-filler')),
                el('span', { className: 'aiaf-step-navigation-spacer', 'aria-hidden': 'true' }),
                props.current < props.progress && el(Button, { className: 'aiaf-step-navigation-button', variant: 'primary', onClick: props.onNext, disabled: !!props.loading }, __('Volgende stap', 'acf-image-auto-filler'))
            )
        );
    }

    function App() {
        var [postTypes, setPostTypes] = useState([]);
        var [posts, setPosts] = useState([]);
        var [postSearch, setPostSearch] = useState('');
        var [debouncedPostSearch, setDebouncedPostSearch] = useState('');
        var [postPage, setPostPage] = useState(1);
        var [postsHasMore, setPostsHasMore] = useState(false);
        var [postsTotal, setPostsTotal] = useState(0);
        var [selectedPostType, setSelectedPostType] = useState('');
        var [selectedPosts, setSelectedPosts] = useState([]);
        var [fields, setFields] = useState([]);
        var [selectedFieldKeys, setSelectedFieldKeys] = useState([]);
        var [fieldMapping, setFieldMapping] = useState({});
        var [mappingFilter, setMappingFilter] = useState('all');
        var [images, setImages] = useState([]);
        var [overwrite, setOverwrite] = useState(true);
        var [includeGroups, setIncludeGroups] = useState(false);
        var [useFeaturedImage, setUseFeaturedImage] = useState(false);
        var [preview, setPreview] = useState(null);
        var [previewSignature, setPreviewSignature] = useState('');
        var [result, setResult] = useState(null);
        var [auditLog, setAuditLog] = useState([]);
        var [hasRollback, setHasRollback] = useState(false);
        var [previewImage, setPreviewImage] = useState(null);
        var [loadingCount, setLoadingCount] = useState(0);
        var [blockingLoadingCount, setBlockingLoadingCount] = useState(0);
        var [loadingMessage, setLoadingMessage] = useState('');
        var [initializing, setInitializing] = useState(true);
        var loading = initializing || loadingCount > 0;
        var visibleLoading = initializing || blockingLoadingCount > 0;

        function beginLoading(message, blocking) {
            if (blocking) {
                setLoadingMessage(message || '');
                setBlockingLoadingCount(function (count) { return count + 1; });
            }
            setLoadingCount(function (count) { return count + 1; });
        }

        function endLoading(blocking) {
            if (blocking) {
                setBlockingLoadingCount(function (count) {
                    var next = Math.max(0, count - 1);
                    if (next === 0) { setLoadingMessage(''); }
                    return next;
                });
            }
            setLoadingCount(function (count) { return Math.max(0, count - 1); });
        }
        var [error, setError] = useState('');
        var [confirmOpen, setConfirmOpen] = useState(false);
        var [acfActive, setAcfActive] = useState(settings.acfActive !== false);
        var postsRequestId = useRef(0);

        var selectedPost = selectedPosts.length ? selectedPosts[0] : '';
        var postTypeOptions = postTypes
            .filter(function (type) { return type && type.slug && (type.slug !== 'product' || woocommerceActive); })
            .map(function (type) { return { label: type.label, value: type.slug }; });
        var selectedPostTypeData = postTypes.find(function (type) { return type && type.slug === selectedPostType; });
        var selectedPostTypeSupportsFeaturedImage = !!(selectedPostTypeData && selectedPostTypeData.supportsFeaturedImage);
        var featuredOnlyMode = !acfActive;
        var selectedFields = acfActive ? fields.filter(function (field) { return selectedFieldKeys.indexOf(field.key) !== -1; }) : [];
        var canPreview = selectedPosts.length > 0 && images.length > 0 && ((acfActive && selectedFieldKeys.length > 0) || (useFeaturedImage && selectedPostTypeSupportsFeaturedImage));
        var imageOptions = [{ label: __('Automatisch / geen handmatige keuze', 'acf-image-auto-filler'), value: '0' }].concat(images.map(function (image) {
            return { label: image.title + ' (#' + image.id + ')', value: String(image.id) };
        }));

        function mutationData() {
            return {
                content_id: selectedPost,
                content_ids: selectedPosts,
                post_id: Number.isInteger(selectedPost) ? selectedPost : 0,
                post_ids: selectedPosts.filter(function (id) { return Number.isInteger(id); }),
                attachment_ids: images.map(function (image) { return image.id; }),
                field_keys: acfActive ? selectedFieldKeys : [],
                manual_mapping: acfActive ? buildManualMappingPayload() : {},
                overwrite_existing: overwrite,
                include_groups: acfActive && includeGroups,
                use_featured_image: selectedPostTypeSupportsFeaturedImage && useFeaturedImage
            };
        }

        var currentSignature = useMemo(function () {
            return makeSignature(mutationData());
        }, [selectedPost, selectedPosts.join(','), images.map(function (img) { return img.id; }).join(','), selectedFieldKeys.join(','), JSON.stringify(fieldMapping), overwrite, includeGroups, useFeaturedImage, acfActive, selectedPostTypeSupportsFeaturedImage]);

        var previewStale = !!preview && previewSignature !== currentSignature;
        var activeData = result || preview;
        var summary = SummaryFromData(activeData);
        var hasExecutableChanges = summary.fill > 0 || summary.overwrite > 0;
        var progressStep = result ? 4 : (!selectedPosts.length ? 0 : (!selectedFieldKeys.length && !(useFeaturedImage && selectedPostTypeSupportsFeaturedImage) ? 1 : (!images.length ? 2 : 3)));
        var [manualStep, setManualStep] = useState(null);
        var step = manualStep === null ? progressStep : Math.min(manualStep, progressStep);

        var aiafMainFlowRef = useRef(null);
        var aiafStepMountedRef = useRef(false);
        useEffect(function () {
            if (!aiafStepMountedRef.current) { aiafStepMountedRef.current = true; return; }
            var node = aiafMainFlowRef.current;
            if (node && typeof node.focus === 'function') { node.focus(); }
        }, [step]);


        useEffect(function () {
            if (manualStep !== null && manualStep > progressStep) {
                setManualStep(progressStep);
            }
        }, [manualStep, progressStep]);

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

        function loadPosts(page, append) {
            page = page || 1;
            var requestId = ++postsRequestId.current;
            beginLoading();
            return request('/posts?post_type=' + encodeURIComponent(selectedPostType) + '&search=' + encodeURIComponent(debouncedPostSearch) + '&page=' + encodeURIComponent(String(page)) + '&per_page=100')
                .then(function (response) {
                    if (requestId !== postsRequestId.current) { return; }
                    var incoming = response.posts || [];
                    setPostPage(Number(response.page || page));
                    setPostsHasMore(!!response.hasMore);
                    setPostsTotal(Number(response.total || incoming.length));
                    setPosts(function (current) {
                        if (!append) { return incoming; }
                        var seen = {};
                        current.forEach(function (item) { seen[String(item.id)] = true; });
                        return current.concat(incoming.filter(function (item) {
                            var key = String(item.id);
                            if (seen[key]) { return false; }
                            seen[key] = true;
                            return true;
                        }));
                    });
                })
                .catch(apiError)
                .finally(function () {
                    if (requestId === postsRequestId.current && !append) {
                        setInitializing(false);
                    }
                    endLoading();
                });
        }

        useEffect(function () {
            beginLoading();
            request('/post-types')
                .then(function (response) {
                    var items = (response.postTypes || []).filter(function (type) {
                        return type && type.slug && (type.slug !== 'product' || woocommerceActive);
                    });
                    setPostTypes(items);
                    if (items.length && !selectedPostType) {
                        setSelectedPostType(items[0].slug);
                    } else if (!items.length) {
                        setInitializing(false);
                    }
                })
                .catch(function (err) {
                    apiError(err);
                    setInitializing(false);
                })
                .finally(function () { endLoading(); });
            loadAuditLog();
        }, []);

        useEffect(function () {
            if (!postTypes.length) {
                if (selectedPostType) {
                    setSelectedPostType('');
                }
                return;
            }

            var allowedSlugs = postTypes.map(function (type) { return type.slug; });
            if (selectedPostType && allowedSlugs.indexOf(selectedPostType) === -1) {
                setSelectedPostType(postTypes[0].slug);
            }
        }, [postTypes.map(function (type) { return type.slug; }).join(','), selectedPostType]);

        useEffect(function () {
            var timer = window.setTimeout(function () {
                setDebouncedPostSearch(postSearch);
            }, 300);
            return function () { window.clearTimeout(timer); };
        }, [postSearch]);

        useEffect(function () {
            if (useFeaturedImage && !selectedPostTypeSupportsFeaturedImage) {
                setUseFeaturedImage(false);
            }
        }, [useFeaturedImage, selectedPostTypeSupportsFeaturedImage]);

        useEffect(function () {
            if (acfActive) { return; }
            if (selectedFieldKeys.length > 0) { setSelectedFieldKeys([]); }
            if (Object.keys(fieldMapping).length > 0) { setFieldMapping({}); }
            if (includeGroups) { setIncludeGroups(false); }
            if (selectedPostTypeSupportsFeaturedImage && !useFeaturedImage) {
                setUseFeaturedImage(true);
                clearPreview();
            }
        }, [acfActive, selectedFieldKeys.length, Object.keys(fieldMapping).length, includeGroups, selectedPostTypeSupportsFeaturedImage, useFeaturedImage]);

        useEffect(function () {
            if (!selectedPostType) { return; }
            setSelectedPosts([]);
            setFields([]);
            setSelectedFieldKeys([]);
            setFieldMapping({});
            setPostPage(1);
            setPostsHasMore(false);
            setPostsTotal(0);
            clearPreview();
            loadPosts(1, false);
        }, [selectedPostType, debouncedPostSearch]);

        useEffect(function () {
            if (!selectedPost) {
                setFields([]);
                return;
            }
            beginLoading();
            clearPreview();
            request('/fields?content_id=' + encodeURIComponent(String(selectedPost)) + '&include_groups=' + (includeGroups ? '1' : '0'))
                .then(function (response) {
                    var responseAcfActive = response.acfActive !== false;
                    var items = responseAcfActive ? (response.fields || []) : [];
                    setAcfActive(responseAcfActive);
                    setFields(items);
                    setSelectedFieldKeys(responseAcfActive ? items.filter(function (field) { return !field.has_value; }).map(function (field) { return field.key; }) : []);
                    setFieldMapping({});
                    if (!responseAcfActive) {
                        setIncludeGroups(false);
                        if (selectedPostTypeSupportsFeaturedImage) { setUseFeaturedImage(true); }
                    }
                })
                .catch(apiError)
                .finally(function () { endLoading(); });
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
            frame.on('open', function () {
                var selection = frame.state().get('selection');
                images.forEach(function (img) {
                    if (!img || !img.id) { return; }
                    var attachment = window.wp.media.attachment(img.id);
                    if (attachment) { attachment.fetch(); selection.add(attachment); }
                });
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
                setManualStep(null);
                clearPreview();
            });
            frame.open();
        }

        function togglePost(postId, checked) {
            postId = typeof postId === 'number' ? postId : String(postId || '');
            if (!postId) { return; }
            setSelectedPosts(function (current) {
                if (checked) {
                    return current.indexOf(postId) === -1 ? current.concat([postId]) : current;
                }
                return current.filter(function (id) { return id !== postId; });
            });
            clearPreview();
        }

        function loadMorePosts() {
            if (!selectedPostType || !postsHasMore || loading) { return; }
            loadPosts(postPage + 1, true);
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
            beginLoading();
            setError('');
            setResult(null);
            request('/preview', { method: 'POST', data: data })
                .then(function (response) {
                    setPreview(response);
                    setPreviewSignature(makeSignature(data));
                })
                .catch(apiError)
                .finally(function () { endLoading(); });
        }

        function refreshFields() {
            if (!selectedPost) { return Promise.resolve(); }
            return request('/fields?content_id=' + encodeURIComponent(String(selectedPost)) + '&include_groups=' + (acfActive && includeGroups ? '1' : '0')).then(function (response) {
                var responseAcfActive = response.acfActive !== false;
                setAcfActive(responseAcfActive);
                setFields(responseAcfActive ? (response.fields || []) : []);
                if (!responseAcfActive) {
                    setSelectedFieldKeys([]);
                    setFieldMapping({});
                    setIncludeGroups(false);
                    if (selectedPostTypeSupportsFeaturedImage) { setUseFeaturedImage(true); }
                }
            });
        }

        function runFill() {
            setConfirmOpen(false);
            if (!canMutateTool) {
                setError(__('Je hebt geen toestemming om afbeeldingen in te vullen.', 'acf-image-auto-filler'));
                return;
            }
            if (!canPreview || !preview || previewStale) { return; }
            beginLoading();
            setError('');
            request('/fill', { method: 'POST', data: mutationData() })
                .then(function (response) {
                    setResult(response);
                    setManualStep(null);
                    setPreview(null);
                    setPreviewSignature('');
                    setHasRollback(!!response.rollbackRunId);
                    loadAuditLog();
                    return refreshFields();
                })
                .catch(apiError)
                .finally(function () { endLoading(); });
        }

        function rollbackLast() {
            if (!canMutateTool) {
                setError(__('Je hebt geen toestemming om acties terug te draaien.', 'acf-image-auto-filler'));
                return;
            }
            beginLoading();
            setError('');
            request('/rollback-last', { method: 'POST', data: {} })
                .then(function (response) {
                    setResult({ executed: true, filled: [], skipped: [], errors: response.errors || [], rolledBack: response.rolledBack || [] });
                    setManualStep(null);
                    setHasRollback(!!response.hasRollback);
                    loadAuditLog();
                    return refreshFields();
                })
                .catch(apiError)
                .finally(function () { endLoading(); });
        }

        function rollbackRun(runId) {
            if (!canMutateTool) {
                setError(__('Je hebt geen toestemming om acties terug te draaien.', 'acf-image-auto-filler'));
                return;
            }
            if (!runId) { return; }
            beginLoading();
            setError('');
            request('/rollback-run', { method: 'POST', data: { run_id: runId } })
                .then(function (response) {
                    setResult({ executed: true, filled: [], skipped: [], errors: response.errors || [], rolledBack: response.rolledBack || [] });
                    setManualStep(null);
                    setHasRollback(!!response.hasRollback);
                    loadAuditLog();
                    return refreshFields();
                })
                .catch(apiError)
                .finally(function () { endLoading(); });
        }

        function maybeRunFill() {
            if (!canMutateTool) {
                setError(__('Je hebt geen toestemming om afbeeldingen in te vullen.', 'acf-image-auto-filler'));
                return;
            }
            if (!preview || previewStale) { return; }
            if (!hasExecutableChanges) {
                setConfirmOpen(true);
                return;
            }
            if (overwrite || selectedPosts.length > 1 || summary.overwrite > 0) {
                setConfirmOpen(true);
                return;
            }
            runFill();
        }

        function goToStep(targetStep) {
            targetStep = Math.max(0, Math.min(progressStep, targetStep));
            setManualStep(targetStep);
        }

        function goPreviousStep() {
            goToStep(step - 1);
        }

        function goNextStep() {
            goToStep(step + 1);
        }

        function resetToContent() {
            setManualStep(null);
            setSelectedPosts([]);
            setFields([]);
            setSelectedFieldKeys([]);
            setFieldMapping({});
            setImages([]);
            clearPreview();
        }

        function resetToFields() {
            setManualStep(1);
            setSelectedFieldKeys([]);
            setFieldMapping({});
            clearPreview();
        }

        function resetToImages() {
            setManualStep(2);
            setImages([]);
            setFieldMapping({});
            clearPreview();
        }

        var autoTargetFieldCount = acfActive ? selectedFields.filter(function (field) { return overwrite || !field.has_value; }).length : 0;

        var activeCard = step === 0 ? el(ContentCard, {
            postTypeOptions: postTypeOptions,
            selectedPostType: selectedPostType,
            setSelectedPostType: setSelectedPostType,
            postSearch: postSearch,
            setPostSearch: setPostSearch,
            posts: posts,
            postsHasMore: postsHasMore,
            postsTotal: postsTotal,
            loading: loading,
            selectedPosts: selectedPosts,
            togglePost: togglePost,
            selectVisiblePosts: selectVisiblePosts,
            deselectVisiblePosts: deselectVisiblePosts,
            selectPostsByStatus: selectPostsByStatus,
            loadMorePosts: loadMorePosts,
            featuredOnly: featuredOnlyMode
        }) : (step === 1 ? el(FieldsCard, {
            selectedPost: selectedPost,
            fields: fields,
            selectedFieldKeys: selectedFieldKeys,
            toggleField: toggleField,
            includeGroups: includeGroups,
            setIncludeGroups: function (value) { setIncludeGroups(!!value); },
            useFeaturedImage: useFeaturedImage,
            featuredImageSupported: selectedPostTypeSupportsFeaturedImage,
            setUseFeaturedImage: function (value) { setUseFeaturedImage(selectedPostTypeSupportsFeaturedImage && !!value); clearPreview(); },
            selectEmptyFields: selectEmptyFields,
            selectAllFields: selectAllFields,
            deselectAllFields: deselectAllFields,
            setPreviewImage: setPreviewImage,
            acfActive: acfActive
        }) : (step === 2 ? el(ImagesCard, {
            images: images,
            openMediaLibrary: openMediaLibrary,
            removeImage: removeImage,
            moveImage: moveImage,
            setPreviewImage: setPreviewImage,
            fieldCount: autoTargetFieldCount,
            selectedFieldCount: selectedFields.length,
            useFeaturedImage: useFeaturedImage,
            overwrite: overwrite,
            featuredOnly: featuredOnlyMode
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
            canMutateTool: canMutateTool,
            downloadCsv: downloadCsv,
            setPreviewImage: setPreviewImage,
            hasRollback: hasRollback,
            onRollback: rollbackLast,
            onRollbackRun: rollbackRun,
            loading: loading,
            canPreview: canPreview,
            onPreview: runPreview,
            onFill: maybeRunFill,
            summary: summary,
            selectedPosts: selectedPosts,
            selectedFields: selectedFields,
            previewStale: previewStale,
            featuredOnly: featuredOnlyMode,
            onGoContent: function () { goToStep(0); },
            onGoFields: function () { goToStep(1); },
            onNewRun: resetToContent
        })));

        if (step === 4) {
            activeCard = el(ResultScreen, {
                result: result,
                summary: summary,
                selectedPosts: selectedPosts,
                selectedFields: selectedFields,
                images: images,
                useFeaturedImage: useFeaturedImage,
                auditLog: auditLog,
                canViewAuditLog: canViewAuditLog,
                canMutateTool: canMutateTool,
                hasRollback: hasRollback,
                onRollback: rollbackLast,
                onRollbackRun: rollbackRun,
                onPreview: runPreview,
                onNewRun: resetToContent,
                loading: loading,
                featuredOnly: featuredOnlyMode
            });
        }

        if (initializing) {
            return el('div', { className: UI.app + ' aiaf-app--initial-loading', 'aria-busy': 'true' },
                el(LoadingOverlay, { fullscreen: true })
            );
        }

        return el('div', { className: UI.app, 'aria-busy': loading ? 'true' : 'false' },
            visibleLoading && el(LoadingOverlay, { fullscreen: true, title: initializing ? __('Gegevens laden', 'acf-image-auto-filler') : __('Bewerking bezig', 'acf-image-auto-filler'), message: loadingMessage || __('Even geduld. De interface blijft geblokkeerd tot de huidige bewerking klaar is.', 'acf-image-auto-filler') }),
            error && el('div', { role: 'alert' }, el(Notice, { status: 'error', onRemove: function () { setError(''); } }, error)),
            el(Stepper, {
                current: step,
                progress: progressStep,
                loading: loading,
                onStep: goToStep,
                hasContent: selectedPosts.length > 0,
                hasFields: selectedFieldKeys.length > 0 || useFeaturedImage,
                hasImages: images.length > 0,
                hasPreview: !!preview,
                previewStale: previewStale,
                hasResult: !!result,
                featuredOnly: featuredOnlyMode
            }),
            previewStale && el(Notice, { status: 'warning', isDismissible: false }, __('De preview is verouderd. Vernieuw de preview voordat je uitvoert.', 'acf-image-auto-filler')),

            el('div', { className: UI.mainFlow, ref: aiafMainFlowRef, tabIndex: -1 },
                selectedPosts.length > 1 && step >= 3 && el(BatchNotice, { selectedPosts: selectedPosts, selectedFields: selectedFields, hasRollback: hasRollback, featuredOnly: featuredOnlyMode }),
                activeCard,
                step < 4 && el(StepNavigation, {
                    current: step,
                    progress: progressStep,
                    loading: loading,
                    onPrevious: goPreviousStep,
                    onNext: goNextStep
                })
            ),


            confirmOpen && el(Modal, { title: hasExecutableChanges ? (featuredOnlyMode ? __('Uitgelichte afbeelding nu instellen?', 'acf-image-auto-filler') : __('Afbeeldingen nu invullen?', 'acf-image-auto-filler')) : (featuredOnlyMode ? __('Uitgelichte afbeelding staat al goed', 'acf-image-auto-filler') : __('Geen nieuwe velden om te vullen', 'acf-image-auto-filler')), className: 'aiaf-confirm-modal ' + (hasExecutableChanges ? 'aiaf-confirm-modal--execute' : 'aiaf-confirm-modal--no-changes'), onRequestClose: function () { setConfirmOpen(false); }, shouldCloseOnClickOutside: false },
                el('div', { className: UI.confirmContent },
                    el('div', { className: 'aiaf-confirm-lead' + (hasExecutableChanges ? '' : ' aiaf-confirm-lead--no-changes') },
                        el('span', { className: 'aiaf-confirm-icon', 'aria-hidden': 'true' }, hasExecutableChanges ? '!' : 'i'),
                        el('div', null,
                            el('h3', null, hasExecutableChanges ? __('Laatste controle vóór uitvoering', 'acf-image-auto-filler') : __('Geen wijziging nodig', 'acf-image-auto-filler')),
                            el('p', null, hasExecutableChanges ? __('Controleer kort wat er wordt aangepast. Na uitvoering kun je deze run vanuit het auditlog terugdraaien.', 'acf-image-auto-filler') : (featuredOnlyMode ? __('De gekozen afbeelding is al ingesteld als uitgelichte afbeelding. Er hoeft niets te worden aangepast.', 'acf-image-auto-filler') : __('De geselecteerde afbeelding is al gekoppeld aan de gekozen item(s). Er zijn daarom geen ACF-afbeeldingsvelden aangepast.', 'acf-image-auto-filler')))
                        )
                    ),
                    el('dl', { className: 'aiaf-confirm-summary' + (hasExecutableChanges ? '' : ' aiaf-confirm-summary--no-changes'), 'aria-label': __('Samenvatting van deze actie', 'acf-image-auto-filler') },
                        el('div', null,
                            el('dt', null, __('Geselecteerd', 'acf-image-auto-filler')),
                            el('dd', null, selectedPosts.length)
                        ),
                        el('div', null,
                            el('dt', null, __('Te vullen', 'acf-image-auto-filler')),
                            el('dd', null, summary.fill)
                        ),
                        el('div', { className: summary.overwrite > 0 ? 'is-warning' : '' },
                            el('dt', null, __('Overschrijven', 'acf-image-auto-filler')),
                            el('dd', null, summary.overwrite)
                        ),
                        el('div', null,
                            el('dt', null, __('Overgeslagen', 'acf-image-auto-filler')),
                            el('dd', null, summary.skip)
                        )
                    ),
                    hasExecutableChanges && (overwrite || summary.overwrite > 0) && el('div', { className: 'aiaf-confirm-risk', role: 'status' },
                        el('strong', null, __('Vervangen is ingeschakeld', 'acf-image-auto-filler')),
                        el('span', null, featuredOnlyMode ? __('Bestaande uitgelichte afbeeldingen kunnen worden overschreven.', 'acf-image-auto-filler') : __('Bestaande afbeeldingsvelden kunnen worden overschreven.', 'acf-image-auto-filler'))
                    ),
                    el('div', { className: UI.modalActions + (hasExecutableChanges ? '' : ' aiaf-modal-actions--no-changes') },
                        hasExecutableChanges && el(Button, { className: 'aiaf-confirm-button aiaf-confirm-cancel', variant: 'secondary', onClick: function () { setConfirmOpen(false); } }, __('Annuleren', 'acf-image-auto-filler')),
                        !hasExecutableChanges && el(Button, { className: 'aiaf-confirm-button aiaf-confirm-adjust', variant: 'secondary', onClick: function () { setConfirmOpen(false); goToStep(2); } }, __('Andere selectie kiezen', 'acf-image-auto-filler')),
                        hasExecutableChanges ? el(Button, { className: 'aiaf-confirm-button aiaf-confirm-submit', variant: 'primary', onClick: runFill }, featuredOnlyMode ? __('Uitgelichte afbeelding instellen', 'acf-image-auto-filler') : __('Afbeeldingen invullen', 'acf-image-auto-filler')) : el(Button, { className: 'aiaf-confirm-button aiaf-confirm-close', variant: 'primary', onClick: function () { setConfirmOpen(false); } }, __('Sluiten', 'acf-image-auto-filler'))
                    )
                )
            ),
            previewImage && el(Modal, { title: previewImage.title || __('Afbeeldingsvoorbeeld', 'acf-image-auto-filler'), onRequestClose: function () { setPreviewImage(null); } },
                el('div', { className: UI.largePreview }, el('img', { src: previewImage.medium || previewImage.thumbnail, alt: previewImage.title || '' }))
            )
        );
    }

    function BatchNotice(props) {
        return el('div', { className: UI.batchWarning, role: 'region', 'aria-label': __('Batchmodus waarschuwing', 'acf-image-auto-filler') },
            el(Notice, { status: 'warning', isDismissible: false },
                el('strong', null, __('Batchmodus actief', 'acf-image-auto-filler')),
                el('p', null, props.featuredOnly ? __('Je past meerdere items tegelijk aan. Controleer extra goed of bij alle items dezelfde uitgelichte afbeelding gewenst is.', 'acf-image-auto-filler') : __('Je past meerdere posts tegelijk aan. Controleer extra goed of deze posts dezelfde ACF-velden gebruiken.', 'acf-image-auto-filler')),
                el('ul', null,
                    el('li', null, props.selectedPosts.length + ' ' + __('geselecteerde item(s)', 'acf-image-auto-filler')),
                    el('li', null, props.featuredOnly ? __('Uitgelichte afbeelding', 'acf-image-auto-filler') : props.selectedFields.length + ' ' + __('geselecteerde veld(en)', 'acf-image-auto-filler')),
                    el('li', null, props.hasRollback ? __('Terugdraaien is beschikbaar na uitvoeren.', 'acf-image-auto-filler') : __('Rollbackstatus wordt gecontroleerd voordat je uitvoert.', 'acf-image-auto-filler'))
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
                    el('p', null, props.featuredOnly ? __('Kies een contenttype en selecteer één of meerdere items. In featured-image-only modus worden alleen items met ondersteuning voor uitgelichte afbeeldingen getoond.', 'acf-image-auto-filler') : __('Kies eerst het contenttype en selecteer daarna één of meerdere items. Alleen ondersteunde post types en taxonomieën met bruikbare ACF-afbeeldingsvelden worden getoond.', 'acf-image-auto-filler'))
                )
            ),
            el(CardBody, null,
                el('div', { className: UI.contentFields },
                    el(NativeSelectField, { className: 'aiaf-post-type-control', id: 'aiaf-post-type-select', label: __('Contenttype', 'acf-image-auto-filler'), value: props.selectedPostType, options: props.postTypeOptions, onChange: props.setSelectedPostType, disabled: props.loading }),
                    el(TextControl, { className: 'aiaf-search-control', label: __('Zoeken', 'acf-image-auto-filler'), value: props.postSearch, onChange: props.setPostSearch, placeholder: __('Zoek op titel...', 'acf-image-auto-filler') })
                ),
                props.posts.length === 0 && el('div', { className: UI.emptyState }, __('Geen bewerkbare items gevonden. Kies een ander contenttype of pas je zoekterm aan.', 'acf-image-auto-filler')),
                props.posts.length > 0 && el('div', { className: UI.contentToolbar, role: 'region', 'aria-label': __('Content selectie-acties', 'acf-image-auto-filler') },
                    el('div', { className: UI.contentToolbarCount },
                        el('strong', { className: 'aiaf-selection-pill' }, selectedVisibleCount + ' ' + (selectedVisibleCount === 1 ? __('item geselecteerd', 'acf-image-auto-filler') : __('items geselecteerd', 'acf-image-auto-filler'))),
                        props.postsTotal > props.posts.length && el('span', null, props.posts.length + ' ' + __('van', 'acf-image-auto-filler') + ' ' + props.postsTotal + ' ' + __('geladen', 'acf-image-auto-filler'))
                    ),
                    el('div', { className: UI.contentToolbarActions },
                        el(Button, { variant: 'tertiary', className: 'aiaf-toolbar-ghost-button', onClick: props.selectVisiblePosts }, __('Zichtbare items selecteren', 'acf-image-auto-filler')),
                        publishedCount > 0 && selectedVisibleCount < publishedCount && el(Button, { variant: 'tertiary', className: 'aiaf-toolbar-ghost-button', onClick: function () { props.selectPostsByStatus(['publish']); } }, __('Gepubliceerd selecteren', 'acf-image-auto-filler')),
                        selectedVisibleCount > 0 && el(Button, { variant: 'tertiary', className: 'aiaf-toolbar-ghost-button', onClick: props.deselectVisiblePosts }, __('Selectie wissen', 'acf-image-auto-filler')),
                        draftCount > 0 && el(Button, { variant: 'tertiary', className: 'aiaf-toolbar-ghost-button', onClick: function () { props.selectPostsByStatus(['draft', 'pending', 'future']); } }, __('Concepten selecteren', 'acf-image-auto-filler')),
                        props.postsHasMore && el(Button, { variant: 'secondary', onClick: props.loadMorePosts, disabled: props.loading, accessibleWhenDisabled: true }, __('Meer laden', 'acf-image-auto-filler'))
                    )
                ),
                props.posts.length > 0 && el('div', { className: UI.postList, role: 'group', 'aria-label': __('Beschikbare posts', 'acf-image-auto-filler') }, props.posts.map(function (post) {
                    return el('label', { className: props.selectedPosts.indexOf(post.id) !== -1 ? UI.postOptionSelected : UI.postOption, key: post.id },
                        el('input', { type: 'checkbox', checked: props.selectedPosts.indexOf(post.id) !== -1, onChange: function (event) { props.togglePost(post.id, event.target.checked); } }),
                        el('span', null, el('strong', null, post.title), el('small', null, post.meta || ('#' + post.id + ' · ' + post.status + (post.date ? ' · ' + post.date : ''))))
                    );
                }))
            )
        );
    }

    function FieldsCard(props) {
        var featuredOnly = props.acfActive === false;
        var targetSupported = props.featuredImageSupported === true;
        return el(Card, { className: UI.cardNarrow + ' aiaf-fields-card', id: 'aiaf-fields' },
            el(CardHeader, null,
                el('div', null,
                    el('h2', null, featuredOnly ? __('Uitgelichte afbeelding', 'acf-image-auto-filler') : __('Velden kiezen', 'acf-image-auto-filler')),
                    el('p', null, featuredOnly ? __('Selecteer de content en kies daarna de afbeelding die als uitgelichte afbeelding ingesteld moet worden.', 'acf-image-auto-filler') : __('Selecteer welke afbeeldingsvelden automatisch gevuld mogen worden.', 'acf-image-auto-filler'))
                )
            ),
            el(CardBody, null,
                el('section', { className: 'aiaf-field-settings-panel', 'aria-label': featuredOnly ? __('Beschikbaar doel', 'acf-image-auto-filler') : __('Veldopties', 'acf-image-auto-filler') },
                    el('div', { className: 'aiaf-field-option-list' },
                        featuredOnly && el('div', { className: targetSupported ? 'aiaf-field-option-row is-featured-only is-enabled' : 'aiaf-field-option-row is-featured-only is-disabled' },
                            el('div', { className: 'aiaf-field-option-copy' },
                                el('span', { className: 'aiaf-field-option-title' }, __('Uitgelichte afbeelding', 'acf-image-auto-filler')),
                                el('span', { className: 'aiaf-field-option-description' }, targetSupported ? __('Actief: de eerste gekozen afbeelding wordt ingesteld als uitgelichte afbeelding.', 'acf-image-auto-filler') : __('Niet beschikbaar: dit contenttype ondersteunt geen uitgelichte afbeelding.', 'acf-image-auto-filler'))
                            ),
                            el('div', { className: 'aiaf-field-option-control' },
                                el(StatusBadge, { status: targetSupported ? 'success' : 'warning' }, targetSupported ? __('Actief', 'acf-image-auto-filler') : __('Niet beschikbaar', 'acf-image-auto-filler'))
                            )
                        ),
                        !featuredOnly && el('div', { className: 'aiaf-field-option-row' },
                            el('div', { className: 'aiaf-field-option-copy' },
                                el('span', { className: 'aiaf-field-option-title' }, __('ACF-groepen meenemen', 'acf-image-auto-filler')),
                                el('span', { className: 'aiaf-field-option-description' }, __('Gebruik dit wanneer afbeeldingen direct binnen een ACF Group staan.', 'acf-image-auto-filler'))
                            ),
                            el('div', { className: 'aiaf-field-option-control' },
                                el(ToggleControl, { className: 'aiaf-field-toggle', label: __('ACF-groepen meenemen', 'acf-image-auto-filler'), checked: props.includeGroups, onChange: props.setIncludeGroups })
                            )
                        ),
                        !featuredOnly && el('div', { className: 'aiaf-field-option-row' },
                            el('div', { className: 'aiaf-field-option-copy' },
                                el('span', { className: 'aiaf-field-option-title' }, __('Uitgelichte afbeelding vullen', 'acf-image-auto-filler')),
                                el('span', { className: 'aiaf-field-option-description' }, props.featuredImageSupported ? __('Gebruik de eerste gekozen afbeelding als featured image.', 'acf-image-auto-filler') : __('Niet beschikbaar voor dit contenttype.', 'acf-image-auto-filler'))
                            ),
                            el('div', { className: 'aiaf-field-option-control' },
                                el(ToggleControl, { className: 'aiaf-field-toggle', label: __('Uitgelichte afbeelding vullen', 'acf-image-auto-filler'), checked: props.featuredImageSupported && props.useFeaturedImage, onChange: props.setUseFeaturedImage, disabled: !props.featuredImageSupported })
                            )
                        )
                    ),
                    !featuredOnly && (!props.selectedPost || props.fields.length > 0) && el('div', { className: 'aiaf-field-support-summary', role: 'note' },
                        el('span', { className: 'aiaf-field-support-icon', 'aria-hidden': 'true' }, 'i'),
                        el('div', { className: 'aiaf-field-support-content' },
                            el('span', { className: 'aiaf-field-support-title' }, __('Ondersteund', 'acf-image-auto-filler')),
                            el('span', { className: 'aiaf-field-support-text' },
                                props.includeGroups ? __('ACF Image fields en directe Image fields binnen ACF Groups.', 'acf-image-auto-filler') : __('ACF Image fields. Zet ACF-groepen aan voor directe group-velden.', 'acf-image-auto-filler')
                            )
                        ),
                        el('span', { className: 'aiaf-field-support-muted' }, __('Repeater, flexible content, gallery en clone worden overgeslagen.', 'acf-image-auto-filler'))
                    )
                ),
                !props.selectedPost && el('div', { className: 'aiaf-field-empty-state', role: 'status' },
                    el('span', { className: 'aiaf-field-empty-icon', 'aria-hidden': 'true' }, 'i'),
                    el('div', null,
                        el('strong', null, __('Selecteer eerst content', 'acf-image-auto-filler')),
                        el('p', null, featuredOnly ? __('Daarna kun je direct de uitgelichte afbeelding instellen.', 'acf-image-auto-filler') : __('Daarna tonen we hier alleen de afbeeldingsvelden die veilig gevuld kunnen worden.', 'acf-image-auto-filler'))
                    )
                ),
                featuredOnly && props.selectedPost && el('div', { className: targetSupported ? 'aiaf-field-empty-state is-featured-only' : 'aiaf-field-empty-state is-warning', role: 'status' },
                    el('span', { className: 'aiaf-field-empty-icon', 'aria-hidden': 'true' }, targetSupported ? '✓' : 'i'),
                    el('div', null,
                        el('strong', null, targetSupported ? __('Uitgelichte afbeelding beschikbaar', 'acf-image-auto-filler') : __('Geen ondersteund doel beschikbaar', 'acf-image-auto-filler')),
                        targetSupported && el('ul', { className: 'aiaf-field-goal-list' },
                            el('li', null, __('Uitgelichte afbeelding', 'acf-image-auto-filler'))
                        ),
                        el('p', null, targetSupported ? __('Kies hierna een afbeelding; alleen de uitgelichte afbeelding wordt aangepast.', 'acf-image-auto-filler') : __('Kies een contenttype dat uitgelichte afbeeldingen ondersteunt.', 'acf-image-auto-filler'))
                    )
                ),
                !featuredOnly && props.selectedPost && props.fields.length === 0 && el('div', { className: 'aiaf-field-empty-state', role: 'status' },
                    el('span', { className: 'aiaf-field-empty-icon', 'aria-hidden': 'true' }, 'i'),
                    el('div', null,
                        el('strong', null, props.useFeaturedImage ? __('Beschikbare doelen', 'acf-image-auto-filler') : __('Geen ACF-afbeeldingsvelden gevonden', 'acf-image-auto-filler')),
                        props.useFeaturedImage && el('ul', { className: 'aiaf-field-goal-list' },
                            el('li', null, __('Uitgelichte afbeelding', 'acf-image-auto-filler'))
                        ),
                        el('p', null, (props.useFeaturedImage && props.featuredImageSupported) ? __('Geen ACF Image fields gevonden voor dit contenttype. De uitgelichte afbeelding kan wel worden gevuld.', 'acf-image-auto-filler') : __('Controleer of deze content ACF Image fields heeft. Staat de afbeelding direct in een group, zet dan ACF-groepen aan.', 'acf-image-auto-filler'))
                    ),
                    !props.includeGroups && el(Button, { variant: 'secondary', onClick: function () { props.setIncludeGroups(true); } }, __('ACF-groepen meenemen', 'acf-image-auto-filler'))
                ),
                !featuredOnly && props.fields.length > 0 && el('div', null,
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
        var featuredOnly = props.featuredOnly === true;
        var targetCount = props.fieldCount || 0;
        var autoImageCount = Math.max(0, props.images.length - (props.useFeaturedImage ? 1 : 0));
        var tooMany = !featuredOnly && targetCount > 0 && autoImageCount > targetCount;
        var tooFew = !featuredOnly && targetCount > 0 && props.images.length > 0 && autoImageCount < targetCount;
        var hasImages = props.images.length > 0;
        return el(Card, { className: UI.cardNarrow + ' aiaf-images-card', id: 'aiaf-images' },
            el(CardHeader, null,
                el('div', { className: 'aiaf-images-card-header' },
                    el('div', null,
                        el('h2', null, __('Afbeeldingen kiezen', 'acf-image-auto-filler')),
                        el('p', null, featuredOnly ? __('Kies de afbeelding die als uitgelichte afbeelding wordt gebruikt.', 'acf-image-auto-filler') : __('Selecteer de afbeeldingen die straks automatisch aan de gekozen velden worden gekoppeld.', 'acf-image-auto-filler'))
                    ),
                    el('span', { className: hasImages ? 'aiaf-media-count-pill is-filled' : 'aiaf-media-count-pill' },
                        hasImages ? props.images.length + ' ' + __('geselecteerd', 'acf-image-auto-filler') : __('Nog leeg', 'acf-image-auto-filler')
                    )
                )
            ),
            el(CardBody, null,
                el('div', { className: hasImages ? 'aiaf-media-hero has-images' : 'aiaf-media-hero' },
                    el('div', { className: 'aiaf-media-hero-copy' },
                        el('div', { className: 'aiaf-media-icon', 'aria-hidden': 'true' }, '▣'),
                        el('div', null,
                            el('strong', null, hasImages ? __('Afbeeldingen klaar voor koppeling', 'acf-image-auto-filler') : __('Nog geen afbeeldingen gekozen', 'acf-image-auto-filler')),
                            el('p', null, featuredOnly ? __('De eerste afbeelding in de selectie wordt gebruikt als uitgelichte afbeelding.', 'acf-image-auto-filler') : (hasImages ? __('Je kunt de volgorde aanpassen of afbeeldingen vervangen voordat je de koppeling controleert.', 'acf-image-auto-filler') : __('Kies afbeeldingen uit de Media Library. De volgorde bepaalt de automatische koppeling met de geselecteerde velden.', 'acf-image-auto-filler')))
                        )
                    ),
                    el(Button, { variant: 'secondary', className: 'aiaf-media-action-button', onClick: props.openMediaLibrary }, hasImages ? __('Selectie aanpassen', 'acf-image-auto-filler') : __('Media Library openen', 'acf-image-auto-filler'))
                ),
                !featuredOnly && props.selectedFieldCount > targetCount && !props.overwrite && el(Notice, { status: 'info', isDismissible: false }, __('Gevulde ACF-velden worden niet meegeteld zolang vervangen uit staat. Automatische afbeeldingen worden alleen aan lege geselecteerde velden gekoppeld.', 'acf-image-auto-filler')),
                featuredOnly && props.images.length > 1 && el(Notice, { status: 'info', isDismissible: false }, __('Featured-image-only gebruikt alleen de eerste geselecteerde afbeelding. Extra afbeeldingen worden overgeslagen.', 'acf-image-auto-filler')),
                tooMany && el(Notice, { status: 'warning', isDismissible: false }, __('Er zijn meer automatische ACF-afbeeldingen dan automatisch te vullen ACF-velden. Extra afbeeldingen worden overgeslagen tenzij je handmatig mapt.', 'acf-image-auto-filler')),
                tooFew && el(Notice, { status: 'info', isDismissible: false }, __('Er zijn minder automatische ACF-afbeeldingen dan automatisch te vullen velden. Niet alle velden krijgen automatisch een afbeelding.', 'acf-image-auto-filler')),
                hasImages && el('div', { className: 'aiaf-media-strip', role: 'list', 'aria-label': __('Geselecteerde afbeeldingen', 'acf-image-auto-filler') }, props.images.map(function (image, index) {
                    return el('div', { className: 'aiaf-media-card', key: image.id, role: 'listitem' },
                        el('button', { type: 'button', className: UI.imagePreviewButton, onClick: function () { props.setPreviewImage(image); }, 'aria-label': __('Afbeelding groter bekijken', 'acf-image-auto-filler') + ': ' + (image.title || ('#' + image.id)) }, el('img', { src: image.thumbnail, alt: image.title || '' })),
                        el('div', { className: 'aiaf-media-card-body' },
                            el('strong', null, image.title || __('Afbeelding', 'acf-image-auto-filler')),
                            el('small', null, '#' + image.id + ' · ' + (index + 1) + '/' + props.images.length),
                            el('span', { className: 'aiaf-media-selected-badge' }, __('Geselecteerd', 'acf-image-auto-filler'))
                        ),
                        el('div', { className: UI.imageActions },
                            props.images.length > 1 && el(Button, { variant: 'secondary', size: 'small', disabled: index === 0, onClick: function () { props.moveImage(index, -1); } }, __('Omhoog', 'acf-image-auto-filler')),
                            props.images.length > 1 && el(Button, { variant: 'secondary', size: 'small', disabled: index === props.images.length - 1, onClick: function () { props.moveImage(index, 1); } }, __('Omlaag', 'acf-image-auto-filler')),
                            el(Button, { variant: 'secondary', size: 'small', isDestructive: true, onClick: function () { props.removeImage(image.id); } }, __('Verwijderen', 'acf-image-auto-filler'))
                        )
                    );
                }))
            )
        );
    }

    function MappingCard(props) {
        var featuredOnly = props.featuredOnly === true;
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

        var hasMappingInputs = featuredOnly ? props.useFeaturedImage : props.fields.length > 0;
        var hasReviewOutput = !!props.preview || !!props.result;
        var canMatchByFilename = !featuredOnly && props.fields.length > 0 && props.images.length > 0;
        var canDownloadCsv = !!props.preview;

        return el(Card, { className: UI.cardWide, id: 'aiaf-mapping' },
            el(CardHeader, { className: 'aiaf-review-header' },
                el('div', { className: 'aiaf-review-heading' },
                    el('h2', null, __('Koppeling controleren', 'acf-image-auto-filler')),
                    el('p', null, featuredOnly ? __('Controleer de uitgelichte afbeelding voordat je wijzigingen uitvoert.', 'acf-image-auto-filler') : __('Controleer de voorgestelde koppelingen voordat je wijzigingen uitvoert.', 'acf-image-auto-filler'))
                ),
                el(ActionBar, {
                    canPreview: props.canPreview,
                    canMutateTool: props.canMutateTool,
                    loading: props.loading,
                    onPreview: props.onPreview,
                    onFill: props.onFill,
                    preview: props.preview,
                    previewStale: props.previewStale,
                    summary: props.summary,
                    selectedPosts: props.selectedPosts,
                    selectedFields: props.selectedFields,
                    images: props.images,
                    useFeaturedImage: props.useFeaturedImage,
                    featuredOnly: featuredOnly
                })
            ),
            el(CardBody, null,
                el('div', { className: UI.mappingToolbar },
                    el('section', { className: 'aiaf-review-option-card' + (props.overwrite ? ' is-enabled' : ''), 'aria-labelledby': 'aiaf-overwrite-title', 'aria-describedby': 'aiaf-overwrite-help' },
                        el('div', { className: 'aiaf-review-option-copy' },
                            el('strong', { id: 'aiaf-overwrite-title' }, props.overwrite ? __('Vervangen ingeschakeld', 'acf-image-auto-filler') : __('Bestaande afbeeldingen behouden', 'acf-image-auto-filler')),
                            el('span', { id: 'aiaf-overwrite-help' }, props.overwrite ? (featuredOnly ? __('Bestaande uitgelichte afbeeldingen worden overschreven.', 'acf-image-auto-filler') : __('Bestaande afbeeldingsvelden worden overschreven.', 'acf-image-auto-filler')) : __('Standaard aan. Zet alleen uit als bestaande afbeeldingen moeten blijven staan.', 'acf-image-auto-filler'))
                        ),
                        el(ToggleControl, { label: __('Bestaande afbeeldingen vervangen', 'acf-image-auto-filler'), hideLabelFromVision: true, checked: props.overwrite, onChange: props.setOverwrite })
                    ),
                    !featuredOnly && el('section', { className: 'aiaf-review-tools-card', 'aria-labelledby': 'aiaf-review-tools-title' },
                        el('div', { className: 'aiaf-review-tools-head' },
                            el('strong', { id: 'aiaf-review-tools-title' }, __('Koppelingen', 'acf-image-auto-filler')),
                            el('span', null, __('Filter, match of exporteer de voorgestelde koppelingen.', 'acf-image-auto-filler'))
                        ),
                        el('div', { className: 'aiaf-review-tools-row' },
                            el('div', { className: 'aiaf-review-filter' },
                                el(SelectControl, { label: __('Toon koppelingen', 'acf-image-auto-filler'), value: props.mappingFilter, options: filterOptions, onChange: props.setMappingFilter })
                            ),
                            el('div', { className: 'aiaf-review-actions' },
                                el(Button, { variant: 'secondary', onClick: props.applyFilenameMatching, disabled: !canMatchByFilename, accessibleWhenDisabled: true, description: !canMatchByFilename ? __('Kies eerst velden en afbeeldingen om automatisch op bestandsnaam te matchen.', 'acf-image-auto-filler') : undefined }, __('Match op bestandsnaam', 'acf-image-auto-filler')),
                                el(Button, { variant: 'secondary', onClick: function () { props.preview && props.downloadCsv(props.preview); }, disabled: !canDownloadCsv, accessibleWhenDisabled: true, description: !canDownloadCsv ? __('Maak eerst een preview voordat je een CSV kunt downloaden.', 'acf-image-auto-filler') : undefined }, __('CSV downloaden', 'acf-image-auto-filler'))
                            )
                        )
                    )
                ),
                props.fields.length === 0 && !hasReviewOutput && el('div', { className: 'aiaf-review-empty-state', role: 'status', 'aria-live': 'polite' },
                    el('span', { className: 'aiaf-review-empty-icon', 'aria-hidden': 'true' }, 'i'),
                    el('div', { className: 'aiaf-review-empty-copy' },
                        el('strong', null, props.useFeaturedImage ? __('Alleen uitgelichte afbeelding', 'acf-image-auto-filler') : __('Nog niets om te controleren', 'acf-image-auto-filler')),
                        el('span', null, props.useFeaturedImage ? (featuredOnly ? __('Maak een preview om te controleren wat er met de uitgelichte afbeelding gebeurt.', 'acf-image-auto-filler') : __('Dit contenttype heeft geen ACF-afbeeldingsvelden. Maak een preview om te controleren wat er met de uitgelichte afbeelding gebeurt.', 'acf-image-auto-filler')) : __('Kies eerst content en velden. Daarna tonen we hier de voorgestelde koppelingen.', 'acf-image-auto-filler'))
                    ),
                    !props.useFeaturedImage && el('div', { className: 'aiaf-review-empty-actions', 'aria-label': __('Ga naar eerdere stappen', 'acf-image-auto-filler') },
                        el(Button, { className: 'aiaf-action-button aiaf-review-empty-button', variant: 'primary', onClick: props.onGoContent }, __('Content kiezen', 'acf-image-auto-filler')),
                        el(Button, { className: 'aiaf-action-button aiaf-review-empty-button', variant: 'secondary', onClick: props.onGoFields }, __('Velden kiezen', 'acf-image-auto-filler'))
                    )
                ),
                props.fields.length > 0 && rows.length === 0 && !hasReviewOutput && el('div', { className: 'aiaf-review-empty-state', role: 'status', 'aria-live': 'polite' },
                    el('span', { className: 'aiaf-review-empty-icon', 'aria-hidden': 'true' }, 'i'),
                    el('div', { className: 'aiaf-review-empty-copy' },
                        el('strong', null, __('Geen koppelingen binnen dit filter', 'acf-image-auto-filler')),
                        el('span', null, __('Kies een ander filter om meer regels te zien.', 'acf-image-auto-filler'))
                    )
                ),
                props.fields.length > 0 && rows.length > 0 && el('div', { className: UI.mappingTableWrap },
                    el('table', { className: UI.mappingTable, 'aria-describedby': 'aiaf-mapping-caption' },
                        el('caption', { id: 'aiaf-mapping-caption' }, __('Controleer per veld welke afbeelding wordt gekoppeld voordat je uitvoert.', 'acf-image-auto-filler')),
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
                el(ResultTabs, { preview: props.preview, result: props.result, auditLog: props.auditLog, canViewAuditLog: props.canViewAuditLog, readyForPreview: hasMappingInputs, hasRollback: props.hasRollback, canMutateTool: props.canMutateTool, onRollback: props.onRollback, onRollbackRun: props.onRollbackRun, onNewRun: props.onNewRun, loading: props.loading })
            )
        );
    }

    function ActionBar(props) {
        function summaryPill(count, singular, plural, extraClass) {
            var label = Number(count) === 1 ? singular : plural;
            return el('span', { className: 'aiaf-summary-pill' + (extraClass ? ' ' + extraClass : '') },
                el('span', { className: 'aiaf-summary-count' }, count),
                el('span', { className: 'aiaf-summary-label' }, label)
            );
        }

        var noChangesRequired = !!props.preview && !props.previewStale && props.summary.fill === 0 && props.summary.overwrite === 0;

        return el('div', { className: UI.actionBar, role: 'region', 'aria-label': __('Actiebalk', 'acf-image-auto-filler'), 'aria-live': 'polite' },
            el('div', { className: UI.actionSummary },
                summaryPill(props.selectedPosts.length, __('item', 'acf-image-auto-filler'), __('items', 'acf-image-auto-filler'), 'is-primary-count'),
                summaryPill(props.selectedFields.length + (props.useFeaturedImage ? 1 : 0), props.featuredOnly ? __('doel', 'acf-image-auto-filler') : __('veld', 'acf-image-auto-filler'), props.featuredOnly ? __('doelen', 'acf-image-auto-filler') : __('velden', 'acf-image-auto-filler')),
                summaryPill(props.images.length, __('afbeelding', 'acf-image-auto-filler'), __('afbeeldingen', 'acf-image-auto-filler')),
                props.preview && !props.previewStale && el(StatusBadge, { status: props.summary.errors ? 'error' : (noChangesRequired ? 'neutral' : 'ready') }, noChangesRequired ? __('Geen wijzigingen nodig', 'acf-image-auto-filler') : props.summary.fill + ' ' + __('wijziging(en) klaar', 'acf-image-auto-filler')),
                props.previewStale && el(StatusBadge, { status: 'stale' }, __('Preview verouderd', 'acf-image-auto-filler'))
            ),
            el('div', { className: UI.actionButtons },
                el(Button, { className: 'button aiaf-action-button aiaf-action-button-preview', variant: 'secondary', onClick: props.onPreview, disabled: !props.canPreview || props.loading }, props.preview ? __('Preview vernieuwen', 'acf-image-auto-filler') : __('Preview maken', 'acf-image-auto-filler')),
                el(Button, { className: 'button button-primary aiaf-action-button aiaf-action-button-fill', variant: 'primary', onClick: props.onFill, disabled: !props.canMutateTool || !props.preview || props.previewStale || props.loading || noChangesRequired }, noChangesRequired ? __('Geen wijzigingen nodig', 'acf-image-auto-filler') : (props.featuredOnly ? __('Uitgelichte afbeelding instellen', 'acf-image-auto-filler') : __('Afbeeldingen invullen', 'acf-image-auto-filler')))
            )
        );
    }

    function ResultScreen(props) {
        var result = props.result || {};
        var featuredOnly = props.featuredOnly === true;
        var summary = props.summary || SummaryFromData(result);
        var itemCount = props.selectedPosts ? props.selectedPosts.length : 0;
        var fieldCount = (props.selectedFields ? props.selectedFields.length : 0) + (props.useFeaturedImage ? 1 : 0);
        var imageCount = props.images ? props.images.length : 0;
        var hasErrors = summary.errors > 0;
        var hasChanges = summary.fill > 0 || summary.rolledBack > 0;
        var hasSkipped = summary.skip > 0;
        var statusTitle = hasErrors ? __('Uitvoering afgerond met aandachtspunten', 'acf-image-auto-filler') : (hasChanges ? __('Uitvoering afgerond', 'acf-image-auto-filler') : __('Geen wijzigingen nodig', 'acf-image-auto-filler'));
        var statusText = hasErrors ? __('Er zijn aandachtspunten gevonden. Controleer de melding hieronder.', 'acf-image-auto-filler') : (hasChanges ? (featuredOnly ? __('De uitgelichte afbeelding is verwerkt. Je kunt deze run vanuit het auditlog terugdraaien.', 'acf-image-auto-filler') : __('De geselecteerde koppelingen zijn verwerkt. Je kunt deze run vanuit het auditlog terugdraaien.', 'acf-image-auto-filler')) : (featuredOnly ? __('De uitgelichte afbeelding stond al goed. Er is niets aangepast.', 'acf-image-auto-filler') : __('Alles was al ingevuld. Er zijn geen velden aangepast.', 'acf-image-auto-filler')));
        var statusClass = hasErrors ? ' is-error' : (hasChanges ? ' is-success' : ' is-neutral');

        return el(Card, { className: UI.cardWide + ' aiaf-result-screen', id: 'aiaf-result-screen' },
            el(CardHeader, { className: 'aiaf-result-screen-header' },
                el('div', null,
                    el('h2', null, statusTitle),
                    el('p', null, hasChanges ? (featuredOnly ? __('De featured-image-only run is klaar. Controleer hieronder de uitkomst en rollback-optie.', 'acf-image-auto-filler') : __('De uitvoering is klaar. Controleer hieronder de belangrijkste uitkomst en vervolgactie.', 'acf-image-auto-filler')) : __('Er zijn geen nieuwe wijzigingen uitgevoerd. Controleer hieronder de korte samenvatting.', 'acf-image-auto-filler'))
                )
            ),
            el(CardBody, null,
                el('section', { className: 'aiaf-result-hero' + statusClass, role: 'status', 'aria-live': 'polite' },
                    el('span', { className: 'aiaf-result-hero-icon', 'aria-hidden': 'true' }, hasErrors ? '!' : (hasChanges ? '✓' : 'i')),
                    el('div', { className: 'aiaf-result-hero-copy' },
                        el('span', null, statusText)
                    )
                ),
                el('dl', { className: 'aiaf-result-summary-grid aiaf-result-summary-grid--simple', 'aria-label': __('Resultaatsamenvatting', 'acf-image-auto-filler') },
                    el('div', null,
                        el('dt', null, __('Items gecontroleerd', 'acf-image-auto-filler')),
                        el('dd', null, itemCount)
                    ),
                    el('div', { className: summary.fill > 0 ? 'is-success' : '' },
                        el('dt', null, __('Wijzigingen', 'acf-image-auto-filler')),
                        el('dd', null, summary.fill)
                    ),
                    el('div', { className: summary.errors > 0 ? 'is-error' : '' },
                        el('dt', null, __('Fouten', 'acf-image-auto-filler')),
                        el('dd', null, summary.errors)
                    ),
                    el('div', { className: summary.skip > 0 ? 'is-warning' : '' },
                        el('dt', null, __('Overgeslagen', 'acf-image-auto-filler')),
                        el('dd', null, summary.skip)
                    )
                ),
                (hasErrors || (hasChanges && hasSkipped)) && el('section', { className: 'aiaf-result-section', 'aria-labelledby': 'aiaf-result-feedback-title' },
                    el('div', { className: 'aiaf-result-section-head' },
                        el('h3', { id: 'aiaf-result-feedback-title' }, __('Aandachtspunten', 'acf-image-auto-filler'))
                    ),
                    el(ResultMessages, { data: result, compact: true })
                ),
                (!hasErrors && !hasChanges && hasSkipped) && el('section', { className: 'aiaf-result-note', 'aria-label': __('Controle', 'acf-image-auto-filler') },
                    el(ResultMessages, { data: result, compact: true })
                ),
                hasChanges && el('section', { className: 'aiaf-result-section aiaf-result-section--processed', 'aria-labelledby': 'aiaf-result-items-title' },
                    el('div', { className: 'aiaf-result-section-head' },
                        el('h3', { id: 'aiaf-result-items-title' }, featuredOnly ? __('Verwerkte uitgelichte afbeelding', 'acf-image-auto-filler') : __('Verwerkte wijziging', 'acf-image-auto-filler'))
                    ),
                    el(ResultList, { data: result, limit: 3 })
                ),
                hasChanges && props.canViewAuditLog && props.auditLog && props.auditLog.length > 0 && el(LastActionCard, { items: props.auditLog, hasRollback: props.hasRollback, canMutateTool: props.canMutateTool, onRollback: props.onRollback, onRollbackRun: props.onRollbackRun, loading: props.loading }),
                el('div', { className: 'aiaf-result-actions' },
                    el(Button, { className: 'aiaf-action-button aiaf-action-button-preview', variant: 'secondary', onClick: props.onPreview, disabled: props.loading }, __('Nog een preview maken', 'acf-image-auto-filler')),
                    el(Button, { className: 'aiaf-action-button aiaf-action-button-fill aiaf-action-button-new-selection', variant: 'primary', onClick: props.onNewRun, disabled: props.loading }, __('Nieuwe selectie starten', 'acf-image-auto-filler'))
                )
            )
        );
    }

    function ResultTabs(props) {
        var active = props.result || props.preview;
        var summary = SummaryFromData(active || {});
        var showRestart = !props.result && !!props.preview && summary.fill === 0 && summary.skip > 0;
        if (!active && !props.readyForPreview) { return null; }
        return el('div', { className: UI.controlPanel },
            !active && el('div', { className: 'aiaf-review-empty-state aiaf-review-preview-state', role: 'status', 'aria-live': 'polite' }, el('span', { className: 'aiaf-review-empty-icon', 'aria-hidden': 'true' }, 'i'), el('div', { className: 'aiaf-review-empty-copy' }, el('strong', null, __('Preview nog niet gemaakt', 'acf-image-auto-filler')), el('span', null, __('Maak eerst een preview om de koppeling te controleren voordat je wijzigingen uitvoert.', 'acf-image-auto-filler')))),
            active && el('div', { className: 'aiaf-preview-results' },
                el('h3', null, props.result ? __('Resultaat', 'acf-image-auto-filler') : __('Preview', 'acf-image-auto-filler')),
                el(ResultMessages, { data: active }),
                el(ResultList, { data: active }),
                showRestart && el('div', { className: 'aiaf-result-preview-actions' },
                    el(Button, { className: 'button button-primary aiaf-action-button aiaf-action-button-fill aiaf-action-button-restart', variant: 'primary', onClick: props.onNewRun, disabled: props.loading }, __('Opnieuw beginnen', 'acf-image-auto-filler'))
                ),
                props.canViewAuditLog && props.auditLog && props.auditLog.length > 0 && el('section', { className: 'aiaf-result-section', 'aria-labelledby': 'aiaf-audit-log-title' },
                    el('div', { className: 'aiaf-result-section-head' }, el('h3', { id: 'aiaf-audit-log-title' }, __('Auditlog', 'acf-image-auto-filler'))),
                    el(AuditLog, { items: props.auditLog, canMutateTool: props.canMutateTool, onRollbackRun: props.onRollbackRun, loading: props.loading })
                )
            )
        );
    }

    function ResultMessages(props) {
        var data = props.data || {};
        var messages = [];
        if (!props.compact && data.rollbackRunId) { messages.push({ type: 'success', text: __('Run opgeslagen. Terugdraaien is beschikbaar vanuit het auditlog.', 'acf-image-auto-filler') }); }
        if (!props.compact && data.rolledBack && data.rolledBack.length > 0) { messages.push({ type: 'success', text: data.rolledBack.length + ' ' + __('item(s) teruggedraaid.', 'acf-image-auto-filler') }); }
        if (data.errors && data.errors.length > 0) { messages.push({ type: 'error', text: data.errors.join(' ') }); }
        if (data.skipped && data.skipped.length > 0) { messages.push({ type: 'warning', text: data.skipped.map(function (item) { return normalizeResultText(item.reason); }).join(' ') }); }
        if (!messages.length && !props.compact) { messages.push({ type: 'success', text: __('Alles ziet er goed uit. Er zijn geen fouten of overgeslagen items.', 'acf-image-auto-filler') }); }
        if (!messages.length) { return null; }

        return el('div', { className: UI.resultMessages, role: 'status', 'aria-live': 'polite' },
            messages.map(function (message, index) {
                return el('div', { className: 'aiaf-result-message is-' + message.type, key: message.type + '-' + index },
                    el('span', { className: 'aiaf-result-message-icon', 'aria-hidden': 'true' }, message.type === 'success' ? '✓' : (message.type === 'error' ? '!' : 'i')),
                    el('span', null, normalizeResultText(message.text))
                );
            })
        );
    }

    function ResultList(props) {
        var data = props.data || {};
        var filled = data.filled || [];
        var skipped = data.skipped || [];
        var rolledBack = data.rolledBack || [];
        var limit = props.limit || 0;
        if (limit > 0) {
            filled = filled.slice(0, limit);
            skipped = skipped.slice(0, limit);
            rolledBack = rolledBack.slice(0, limit);
        }
        return el('div', { className: UI.resultList },
            filled.length > 0 && filled.map(function (item, index) {
                return el('div', { className: UI.resultRow, key: item.post_id + '-' + item.field_key + '-' + item.attachment_id + '-' + index },
                    el('div', { className: UI.resultThumb }, item.thumbnail && el('img', { src: item.thumbnail, alt: '' })),
                    el('div', { className: 'aiaf-result-row-copy' }, el('strong', null, normalizeResultText(item.field_label || __('Afbeelding', 'acf-image-auto-filler'))), el('small', null, resultItemMeta(item))),
                    el(StatusBadge, { status: item.executed ? 'success' : (item.will_overwrite ? 'overwrite' : 'fill') }, item.field_key === '_thumbnail_id' ? (item.executed ? __('Ingesteld', 'acf-image-auto-filler') : (item.will_overwrite ? __('Wordt overschreven', 'acf-image-auto-filler') : __('Wordt ingesteld', 'acf-image-auto-filler'))) : (item.executed ? __('Gevuld', 'acf-image-auto-filler') : (item.will_overwrite ? __('Wordt overschreven', 'acf-image-auto-filler') : __('Wordt gevuld', 'acf-image-auto-filler'))))
                );
            }),
            skipped.length > 0 && skipped.map(function (item, index) {
                var skippedTitle = normalizeResultText(item.field_label || __('Overgeslagen', 'acf-image-auto-filler'));
                var skippedMeta = normalizeResultText(item.reason || __('Geen wijziging nodig.', 'acf-image-auto-filler'));
                return el('div', { className: UI.resultRow + ' is-skipped', key: 'skip-' + index },
                    el('div', { className: UI.resultThumb, 'aria-hidden': 'true' }, '–'),
                    el('div', { className: 'aiaf-result-row-copy' }, el('strong', null, skippedTitle), el('small', null, skippedMeta)),
                    el(StatusBadge, { status: item.status === 'unchanged' ? 'neutral' : 'warning' }, item.status === 'unchanged' ? __('Ongewijzigd', 'acf-image-auto-filler') : __('Overgeslagen', 'acf-image-auto-filler'))
                );
            }),
            rolledBack.length > 0 && rolledBack.map(function (item, index) {
                return el('div', { className: UI.resultRow, key: 'rollback-' + index },
                    el('div', { className: 'aiaf-result-row-copy' }, el('strong', null, normalizeResultText(item.field_label || __('Veld teruggedraaid', 'acf-image-auto-filler'))), el('small', null, item.previous_attachment_id ? __('Vorige afbeelding hersteld:', 'acf-image-auto-filler') + ' #' + item.previous_attachment_id : __('Vorige lege waarde hersteld.', 'acf-image-auto-filler'))),
                    el(StatusBadge, { status: 'success' }, __('Rollback', 'acf-image-auto-filler'))
                );
            })
        );
    }

    function LastActionCard(props) {
        var items = props.items || [];
        var item = items[0] || null;
        if (!item) { return null; }
        var date = item.created_at ? new Date(item.created_at * 1000).toLocaleString() : '';
        var count = Number(item.item_count || 0);
        var countLabel = count === 1 ? __('1 wijziging', 'acf-image-auto-filler') : count + ' ' + __('wijzigingen', 'acf-image-auto-filler');
        var canRollback = item.can_rollback !== false;
        return el('section', { className: canRollback ? 'aiaf-last-action-card' : 'aiaf-last-action-card is-restored', 'aria-labelledby': 'aiaf-last-action-title' },
            el('div', { className: 'aiaf-last-action-main' },
                el('span', { className: 'aiaf-last-action-icon', 'aria-hidden': 'true' }, '↺'),
                el('div', { className: 'aiaf-last-action-copy' },
                    el('strong', { id: 'aiaf-last-action-title' }, __('Laatste actie', 'acf-image-auto-filler')),
                    el('span', null, date ? date + ' · ' + countLabel : countLabel),
                    !canRollback && el('span', { className: 'aiaf-status-pill aiaf-status-pill--muted' }, __('Al teruggedraaid', 'acf-image-auto-filler'))
                )
            ),
            el('div', { className: 'aiaf-last-action-actions' },
                props.hasRollback && props.canMutateTool && canRollback && el(Button, { className: 'aiaf-action-button aiaf-action-button-rollback', variant: 'secondary', onClick: function () { props.onRollbackRun ? props.onRollbackRun(item.run_id) : props.onRollback(); }, disabled: props.loading }, __('Terugdraaien', 'acf-image-auto-filler'))
            )
        );
    }

    function AuditLog(props) {
        var items = props.items || [];
        if (!items.length) { return el('p', { className: UI.emptyState }, __('Nog geen actiegeschiedenis beschikbaar.', 'acf-image-auto-filler')); }
        return el('div', { className: UI.auditList }, items.map(function (item, index) {
            var date = item.created_at ? new Date(item.created_at * 1000).toLocaleString() : '';
            var count = Number(item.item_count || 0);
            var countLabel = count === 1 ? __('1 wijziging', 'acf-image-auto-filler') : count + ' ' + __('wijzigingen', 'acf-image-auto-filler');
            var canRollback = item.can_rollback !== false;
            return el('div', { className: canRollback ? UI.auditRow : UI.auditRow + ' is-restored', key: item.run_id || index },
                el('strong', null, index === 0 ? __('Laatste actie', 'acf-image-auto-filler') : __('Eerdere actie', 'acf-image-auto-filler')),
                el('span', null, date),
                el('span', null, countLabel),
                el('span', { className: canRollback ? 'aiaf-status-pill' : 'aiaf-status-pill aiaf-status-pill--muted' }, canRollback ? __('Beschikbaar voor rollback', 'acf-image-auto-filler') : __('Al teruggedraaid', 'acf-image-auto-filler')),
                el('details', { className: 'aiaf-last-action-details' }, el('summary', null, __('Technische details', 'acf-image-auto-filler')), el('code', null, item.run_id || '')),
                props.canMutateTool && item.run_id && canRollback && el(Button, { className: 'aiaf-action-button aiaf-action-button-rollback', variant: 'secondary', onClick: function () { props.onRollbackRun(item.run_id); }, disabled: props.loading }, __('Deze run terugdraaien', 'acf-image-auto-filler'))
            );
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
                showStaticError(root, '', 'De WordPress React renderer kon niet worden gestart.', true);
            }
        } catch (error) {
            showStaticError(root, 'Interface niet gestart', 'De plugin-interface kon niet worden gestart. Vernieuw de pagina en controleer of alle pluginbestanden correct zijn geladen.', false);
        }
    });
})(window.wp || {});
