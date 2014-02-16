ka.FieldTypes.Content = new Class({
    Extends: ka.FieldAbstract,

    options: {
        /**
         * If we display the save buttons etc.
         */
        standalone: false
    },

    preview: 0,
    currentNode: null,
    currentDomain: null,

    lastContents: null,

    createLayout: function() {
        this.mainLayout = new ka.Layout(this.getContainer(), {
            layout: [
                {columns: [null], height: 50},
                {columns: [null, 180]}
            ],
            splitter: [
                [2, 2, 'left']
            ]
        });

        this.mainLayout.getCell(1, 1).addClass('ka-ActionBar ka-Field-Content-ActionBar');
        this.mainLayout.getTd(1, 1).set('colspan', 2);

        this.headerLayout = new ka.Layout(this.mainLayout.getCell(1, 1), {
            fixed: false,
            layout: [
                {columns: [null, 100]}
            ]
        });
        this.headerLayout.getCell(1, 1).addClass('ka-ActionBar-left');

        if (this.options.standalone) {
            this.treeButtonGroup = new ka.ButtonGroup(this.headerLayout.getCell(1, 1));
            this.treeBtn = this.treeButtonGroup.addButton(t('Tree'), '#icon-tree', this.toggleTree.bind(this));
        }

        this.buttonGroup = new ka.ButtonGroup(this.headerLayout.getCell(1, 1));
        this.layoutBtn = this.buttonGroup.addButton(t(''), '#icon-layout');
        this.listBtn = this.buttonGroup.addButton(t(''), '#icon-list-4');

        this.layoutBtn.setPressed(true);

        this.headerLayout.getCell(1, 2).setStyle('text-align', 'right');
        this.headerLayout.getCell(1, 2).setStyle('white-space', 'nowrap');

        this.layoutSelectionLabel = new Element('span', {
            text: t('Layout:'),
            style: 'line-height: 30px; display: inline-block; padding-right: 5px;'
        }).inject(this.headerLayout.getCell(1, 2));

        this.layoutSelectionContainer = new Element('span').inject(this.headerLayout.getCell(1, 2));

        if (this.options.standalone) {
            this.actionGroup = new ka.ButtonGroup(this.headerLayout.getCell(1, 2));

            this.actionGroup.addButton(t('Reset'), '#icon-escape');
            this.actionGroup.addButton(t('Versions'), '#icon-history');

            this.saveBtn = new ka.Button(t('Save')).setButtonStyle('blue').addEvent('click', this.saveStandalone.bind(this)).inject(this.headerLayout.getCell(1, 2));
        } else {
            this.mainLayout.getCell(1, 1).addClass('ka-Field-content-actionBar');
        }

        this.win.setTitle(t('Home'));

        this.editableAreaContainer = new Element('div', {
            style: 'position: absolute; left: 0px; right: 15px; top: 0px; bottom: 0px;'
        }).inject(this.mainLayout.getCell(2, 1));

        this.editableAreaLayout = new ka.Layout(this.editableAreaContainer, {
            layout: [
                {columns: [null], height: 30},
                {columns: [null]}
            ]
        });

        this.optionsContainer = new Element('div').inject(this.editableAreaLayout.getCell(1, 1));

        this.zoomContainer = new Element('div', {
            style: 'float: right;'
        }).inject(this.optionsContainer);

        new Element('span', {
            text: t('Zoom:'),
            style: 'padding-right: 5px; line-height: 30px;'
        }).inject(this.zoomContainer);

        this.slider = new ka.Slider(this.zoomContainer, {
            steps: 100
        });

        this.zoomValue = new Element('span', {
            text: '100%',
            style: 'padding-left: 5px; line-height: 30px;'
        }).inject(this.zoomContainer);

        this.slider.setValue(100);

        this.toggleFullscreen = new Element('a', {
            'class': 'ka-button-icon icon-expand-5',
            style: 'padding: 0 15px; margin-left: 15px; margin-right: 0px; border-left: 1px solid #ddd; border-right: 1px solid #ddd;',
            title: t('Toogle Fullscreen')
        }).inject(this.zoomContainer);

        var iframeContainer = this.editableAreaLayout.getCell(2, 1);

        this.iframe = new IFrame({
            frameborder: 0,
            style: 'display: block; border: 0; height: 100%; width: 100%;'
        }).inject(iframeContainer);

        iframeContainer.setStyle('border-top', '1px solid #E9E9E9');
        iframeContainer.addClass('ka-scrolling');

        if (this.options.standalone) {
            this.domainSelection = new ka.Select(this.headerLayout.getCell(1, 1), {
                object: 'kryncms/domain',
                onChange: function(item) {
                    this.loadEditor(this.domainSelection.getValue());
                }.bind(this)
            });
        }

        this.iframeOverlay = new Element('div', {
            'class': 'ka-Full',
            styles: {
                opacity: 0.01,
                backgroundColor: '#fff'
            }
        });

        var splitter = this.mainLayout.getSplitter(2, 2, 'left');
        splitter.addEvent('start', function() {
            this.iframeOverlay.inject(iframeContainer);
        }.bind(this));

        splitter.addEvent('end', function() {
            this.iframeOverlay.dispose()
        }.bind(this));

        this.slider.addEvent('change', function(step) {
            if (0 == step) step = 1;
            this.zoomValue.set('text', step + '%');
            var val = step / 100;
            document.id(this.iframe.contentWindow.document.body).setStyle('zoom', step + '%');
        }.bind(this));

        this.renderSidebar();
        this.setupEvents();
    },

    toggleTree: function() {
        if (this.treeBtn.isPressed()) {
            this.treeBtn.setPressed(false);
            this.hideTree();
        } else {
            this.treeBtn.setPressed(true);
            this.showTree();
        }
    },

    hideTree: function() {
        if (this.treeContainer) {
            this.treeContainer.destroy();
            delete this.treeContainer;
        }
    },

    showTree: function() {
        this.hideTree();

        this.treeContainer = new Element('div', {

        });
    },

    /**
     *
     * @returns {Element}
     */
    getOptionsContainer: function() {
        return this.optionsContainer;
    },

    setupEvents: function() {
        var typeField;
        if (this.getField().getForm() && (typeField = this.getField().getForm().getField('type'))) {
//            typeField.addEvent('change', function() {
//                this.setValue();
//            }.bind(this));
        }
    },

    setValue: function(value, internal) {
        if (this.getField().getForm()) {
            this.setValueFromForm(value, internal);
        } else {
            this.setValueDefault(value, internal);
        }
    },

    setValueDefault: function(contents, internal) {
        if (!contents) {
            contents = this.lastContents;
        } else {
            this.lastContents = contents;
        }

        this.loadEditor(this.domainSelection.getValue(), this.currentNode, contents);
    },

    setValueFromForm: function(value, internal) {
        var originValue = this.getField().getForm().getOriginValue();

        var typeValue = this.getField().getForm().getValue('type');

        if (0 != typeValue && 1 != typeValue) {
            return;
        }

        this.reloadLayoutSelection(value.theme || originValue.domain.theme, value.layout);

        this.currentNode = originValue.id;
        this.currentDomain = originValue.domainId;
        this.loadEditor(originValue.domainId, originValue.id, value  ? value.content : null);
    },

    onLayoutSelectFirst: function(layout) {
        this.firstSelectedLayout = layout;
    },

    onLayoutChange: function(layout) {
        this.reloadEditor();
    },

    reloadLayoutSelection: function(themeId, layoutId) {
        this.layoutSelectionContainer.empty();

        this.layoutSelection = new ka.Field({
            noWrapper: true,
            type: 'layout',
            value: layoutId,
            options: {
                onChange: this.onLayoutChange.bind(this),
                onSelectFirst: this.onLayoutSelectFirst.bind(this),
                theme: themeId
            }
        }, this.layoutSelectionContainer);
    },

    reloadEditor: function() {
        this.loadEditor();
    },

    loadEditor: function(domainId, nodeId, contents) {
        var options = {
            standalone: this.options.standalone
        };

        if (!contents) {
            contents = this.lastContents;
        }

        this.lastContents = contents;

        var targetLayout = this.layoutSelection ? this.layoutSelection.getValue() : this.firstSelectedLayout;

        if (this.currentNode && this.currentNode == nodeId && this.currentLayout == targetLayout && this.getEditor()) {
            this.getEditor().setValue(contents);
            return;
        }

//        console.log('loadEditor', this.currentLayout, targetLayout, '/', this.currentNode, nodeId);

        var id = (Math.random() * 10 * (Math.random() * 10)).toString(36).slice(3);

        if (this.lastKrynEditorLoader) {
            window.removeEvent('krynEditorLoaded', this.lastKrynEditorLoader);
        }
        this.lastKrynEditorLoader = function(editor) {
            if (editor && editor.getId() == id) {
                this.setEditor(editor);
                editor.setContentField(this);

                this.currentNode = editor.getNodeId();
                this.currentLayout = editor.getLayout();
//                console.log('krynEditorLoaded', this.currentLayout, this.currentNode);

                if (!this.options.standalone) {
                    editor.deactivateLinks();
                    editor.setValue(contents);
                } else {
                    this.reloadLayoutSelection(editor.getTheme(), editor.getLayout());
                }
            }
        }.bind(this);
        window.addEvent('krynEditorLoaded', this.lastKrynEditorLoader);

        if (!nodeId && this.currentNode) {
            nodeId = this.currentNode;
        }

        if (!domainId && this.currentDomain) {
            domainId = this.currentDomain;
        }

        console.log(this.layoutSelection);
        var params = {
            '_kryn_editor': 1,
            '_kryn_editor_id': id,
            '_kryn_editor_node': nodeId,
            '_kryn_editor_domain': domainId,
            '_kryn_editor_layout': !this.layoutSelection || this.layoutSelection.isDisabled() ? null : targetLayout,
            '_kryn_editor_options': options
        };

        this.iframe.set('src', _path + '?' + Object.toQueryString(params));
    },

    saveStandalone: function() {
        if (this.editor) {

            this.saveBtn.startLoading(t('Saving ...'));

            if (this.lastSaveRq) {
                this.lastSaveRq.cancel();
            }

            var value = [];
            var progressWatchManager = new ka.ProgressWatchManager({
                onDone: function(progressWatch) {
                    value.push(progressWatch.getValue());
                },
                onAllDone: function() {
                    this.saveBtn.setProgress(100);
                    this.lastSaveRq = new Request.JSON({url: this.getUrl(),
                        onFailure: function(pResponse) {
                            this.saveBtn.failedLoading(t('Failed!'));
                            this.saveBtn.setProgress(false);
                        }.bind(this),
                        onComplete: function(pResponse) {
                            this.saveBtn.doneLoading(t('Saved!'));
                            this.saveBtn.setProgress(false);
                        }.bind(this)
                    }).post({_method: 'patch', content: value, layout: this.layoutSelection.getValue()});
                }.bind(this),

                onAllProgress: function(progress) {
                    this.saveBtn.setProgress(progress);
                }.bind(this)
            });

            this.saveBtn.startLoading(t('Saving ...'));
            this.editor.getValue(progressWatchManager);
        }
    },

    //    getValue: function(progressWatch) {
    //        if (this.editor) {
    //            var value = [];
    //            var progressWatchManager = new ka.ProgressWatchManager({
    //                onDone: function(progressWatch) {
    //                    value.push(progressWatch.getValue());
    //                },
    //                onAllDone: function() {
    //                    progressWatch.done(value);
    //                }.bind(this),
    //
    //                onAllProgress: function(progress) {
    //                    progressWatch.progress(progress);
    //                }.bind(this)
    //            });
    //
    //            return this.editor.getValue(progressWatchManager);
    //        } else {
    //            return progressWatch.done(this.lastContents);
    //        }
    //    },

    getValue: function(progressWatch) {
        return this.lastContents;
    },

    getUrl: function() {
        return _pathAdmin + 'object/kryncms/node/' + this.editor.options.node.id;
    },

    selectElement: function(element) {
        this.select(element.kaContentInstance);
    },

    select: function(content) {
        if (this.lastContent === content) return;

        this.deselect();

        this.inspectorContainer.setStyle('color');
        this.inspectorContainer.setStyle('text-align');

        if (content.value) {
            this.inspectorTitle.set('text', tf('Inspector (%s)', content.value.type));
        } else {
            this.inspectorTitle.set('text', t('Inspector'));
        }
        content.setSelected(true);

        this.lastContent = content;
    },

    getSelected: function() {
        return this.lastContent;
    },

    deselect: function() {
        if (this.lastContent) {
            this.lastContent.setSelected(false);
            delete this.lastContent;
        }

        this.nothingSelected();
    },

    setEditor: function(editor) {
        this.editor = editor;
    },

    getEditor: function() {
        return this.editor;
    },

    renderSidebar: function() {
        this.sidebar = new Element('div', {
            'class': 'ka-normalize ka-scrolling ka-editor-sidebar'
        }).inject(this.mainLayout.getCell(2, 2), 'top');

        new Element('div', {
            text: t('Show slots'),
            style: 'cursor: default',
            'class': 'ka-editor-sidebar-item icon-checkbox-partial'
        }).addEvent('mouseover', function() {
                if (this.editor)
                    this.editor.highlightSlots(true);
            }.bind(this)).addEvent('mouseout', function() {
                if (this.editor)
                    this.editor.highlightSlots(false);
            }.bind(this)).inject(this.sidebar);

        this.showPreview = new Element('div', {
            text: t('Toggle preview'),
            'class': 'ka-editor-sidebar-item icon-eye-4'
        }).addEvent('click', function() {
                this.togglePreview();
            }.bind(this)).inject(this.sidebar);

        this.inspector = new Element('div', {
            'class': 'ka-editor-inspector'
        }).inject(this.sidebar);

        this.inspectorTitle = new Element('div', {
            'class': 'ka-editor-inspector-title',
            text: t('Inspector')
        }).inject(this.inspector);

        this.inspectorContainer = new Element('div', {
            'class': 'ka-editor-inspector-container',
            text: t('Nothing selected.'),
            style: 'color: gray; text-align: center;'
        }).inject(this.inspector);

        this.contentElements = new Element('div', {
            'class': 'ka-editor-contentElements'
        }).inject(this.sidebar);

        this.contentElementsTitle = new Element('div', {
            'class': 'ka-editor-contentElements-title',
            text: t('Content elements')
        }).inject(this.contentElements);

        this.contentElementsContainer = new Element('div', {
            'class': 'ka-editor-contentElements-container'
        }).inject(this.contentElements);

        Object.each(ka.ContentTypes, function(content, type) {
            this.addContentTypeIcon(type, content);
        }.bind(this));

        this.plugins = new Element('div', {
            'class': 'ka-editor-plugins'
        }).inject(this.sidebar);

        this.pluginsTitle = new Element('div', {
            'class': 'ka-editor-plugins-title',
            text: t('Plugins')
        }).inject(this.plugins);

        this.pluginsContainer = new Element('div', {
            'class': 'ka-editor-plugins-container'
        }).inject(this.plugins);

        Object.each(ka.settings.configs, function(config, bundleName) {
            this.addPlugins(bundleName, config);
        }.bind(this));

        this.contentElementsContainer.addEvent('click', function() {
            this.teaseSlotBubbles();
        }.bind(this));
        this.pluginsContainer.addEvent('click', function() {
            this.teaseSlotBubbles();
        }.bind(this));
    },

    teaseSlotBubbles: function() {
        this.getEditor().highlightSlotsBubbles(true);
        (function() {
            this.getEditor().highlightSlotsBubbles(false);
        }).delay(1000, this);
    },

    togglePreview: function() {
        var active = ++this.preview % 2;

        if (active) {
            this.showPreview.addClass('ka-editor-sidebar-item-active');
        } else {
            this.showPreview.removeClass('ka-editor-sidebar-item-active');
        }

        if (this.editor) {
            this.editor.setPreview(active);
        }
    },

    nothingSelected: function() {
        this.inspectorContainer.set('text', t('Nothing selected.'));
        this.inspectorContainer.setStyle('color', 'gray');
        this.inspectorContainer.setStyle('text-align', 'center');
    },

    getInspectorContainer: function() {
        return this.inspectorContainer;
    },

    addPlugins: function(bundleName, config) {
        var self = this;
        if (config.plugins) {
            var a;

            new Element('div', {
                'class': 'ka-editor-plugins-subTitle',
                text: config.label || bundleName
            }).inject(this.pluginsContainer);

            Object.each(config.plugins, function(plugin, pluginId) {
                a = new Element('div', {
                    draggable: true,
                    text: plugin.label || plugin.id,
                    'class': 'ka-editor-sidebar-item ka-editor-sidebar-draggable ' + (plugin.icon || 'icon-cube-2')
                }).inject(this.pluginsContainer);

                a.addListener('dragstart', function(e) {
                    self.dragStart(this, e);
                });
                a.addListener('dragend', function(e) {
                    self.dragEnd(this, e);
                });

                a.kaContentType = 'plugin';
                a.kaContentValue = {bundle: bundleName, plugin: pluginId};
            }.bind(this));
        }
    },

    addContentTypeIcon: function(pType, pContent) {
        var self = this;

        var a = new Element('div', {
            text: pContent.label,
            draggable: true,
            'class': 'ka-editor-sidebar-item ka-editor-sidebar-draggable ' + (pContent.icon || '')
        }).inject(this.contentElementsContainer);

        a.addListener('dragstart', function(e) {
            self.dragStart(this, e);
        });
        a.addListener('dragend', function(e) {
            self.dragEnd(this, e);
        });

        a.kaContentType = pType;
    },

    dragStart: function(item, e) {
        var data = {};
        data.type = item.kaContentType;

        if (item.kaContentValue) {
            data.content = item.kaContentValue;
        }

        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.setData('application/json', JSON.encode(data));

        if (this.editor) {
            this.editor.highlightSlotsBubbles(true);
        }
    },

    dragEnd: function(item, e) {
        if (this.editor) {
            this.editor.highlightSlotsBubbles(false);
        }
    }

});