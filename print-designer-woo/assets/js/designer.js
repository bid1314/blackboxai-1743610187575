(function($) {
    'use strict';

    class PrintDesigner {
        constructor() {
            this.canvas = null;
            this.selectedObject = null;
            this.config = pdwDesignerSettings.designerConfig;
            this.productId = pdwDesignerSettings.productId;
            this.variations = pdwDesignerSettings.variations;
            this.i18n = pdwDesignerSettings.i18n;

            this.init();
        }

        init() {
            // Initialize Fabric.js canvas
            this.canvas = new fabric.Canvas('pdw-canvas', {
                width: this.config.canvasWidth,
                height: this.config.canvasHeight,
                backgroundColor: '#ffffff'
            });

            this.setupEventListeners();
            this.initializeTools();
            this.setupProductVariations();
        }

        setupEventListeners() {
            // Canvas selection events
            this.canvas.on('selection:created', (e) => this.handleSelection(e));
            this.canvas.on('selection:cleared', () => this.clearProperties());

            // Tool button clicks
            $('.pdw-tool').on('click', (e) => {
                const tool = $(e.currentTarget).data('tool');
                this.handleToolClick(tool);
            });

            // Save design before adding to cart
            $('form.cart').on('submit', () => this.saveDesignToForm());
        }

        initializeTools() {
            // Text tool
            $('[data-tool="text"]').on('click', () => {
                const text = new fabric.IText('Enter text here', {
                    left: 50,
                    top: 50,
                    fontSize: 20,
                    fill: '#000000'
                });
                this.canvas.add(text);
                this.canvas.setActiveObject(text);
                text.enterEditing();
            });

            // Upload tool
            $('[data-tool="upload"]').on('click', () => {
                const input = $('<input type="file" accept="image/*" style="display: none;">');
                input.on('change', (e) => this.handleImageUpload(e));
                input.click();
            });

            // Save tool
            $('[data-tool="save"]').on('click', () => this.saveDesign());

            // Load tool
            $('[data-tool="load"]').on('click', () => this.loadDesign());
        }

        setupProductVariations() {
            if (this.variations.length > 0) {
                // Listen for variation changes
                $('form.variations_form').on('found_variation', (e, variation) => {
                    this.updateProductImage(variation.image.url);
                });
            }
        }

        handleSelection(e) {
            const selected = e.target;
            this.selectedObject = selected;
            this.updatePropertiesPanel(selected);
        }

        clearProperties() {
            this.selectedObject = null;
            $('.pdw-properties-panel').empty();
        }

        updatePropertiesPanel(object) {
            const panel = $('.pdw-properties-panel');
            panel.empty();

            if (object instanceof fabric.IText) {
                this.addTextProperties(panel, object);
            } else if (object instanceof fabric.Image) {
                this.addImageProperties(panel, object);
            }

            // Common properties
            this.addCommonProperties(panel, object);
        }

        addTextProperties(panel, text) {
            const fontSizeInput = $(`
                <div class="pdw-property">
                    <label>Font Size</label>
                    <input type="number" value="${text.fontSize}" min="1" max="200">
                </div>
            `);

            const colorInput = $(`
                <div class="pdw-property">
                    <label>Color</label>
                    <input type="color" value="${text.fill}">
                </div>
            `);

            fontSizeInput.find('input').on('change', (e) => {
                text.set('fontSize', parseInt(e.target.value));
                this.canvas.renderAll();
            });

            colorInput.find('input').on('change', (e) => {
                text.set('fill', e.target.value);
                this.canvas.renderAll();
            });

            panel.append(fontSizeInput, colorInput);
        }

        addImageProperties(panel, image) {
            const scaleInput = $(`
                <div class="pdw-property">
                    <label>Scale</label>
                    <input type="range" min="0.1" max="2" step="0.1" value="${image.scaleX}">
                </div>
            `);

            scaleInput.find('input').on('input', (e) => {
                const scale = parseFloat(e.target.value);
                image.scale(scale);
                this.canvas.renderAll();
            });

            panel.append(scaleInput);
        }

        addCommonProperties(panel, object) {
            const rotationInput = $(`
                <div class="pdw-property">
                    <label>Rotation</label>
                    <input type="range" min="0" max="360" value="${object.angle || 0}">
                </div>
            `);

            const deleteButton = $(`
                <button class="pdw-delete-btn">
                    ${this.i18n.deleteElement}
                </button>
            `);

            rotationInput.find('input').on('input', (e) => {
                object.set('angle', parseInt(e.target.value));
                this.canvas.renderAll();
            });

            deleteButton.on('click', () => {
                this.canvas.remove(object);
                this.clearProperties();
            });

            panel.append(rotationInput, deleteButton);
        }

        handleImageUpload(e) {
            const file = e.target.files[0];
            
            if (!file) return;
            
            if (!this.config.allowedFileTypes.includes(file.type)) {
                alert('Invalid file type');
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                fabric.Image.fromURL(event.target.result, (img) => {
                    // Scale image to fit canvas
                    const scale = Math.min(
                        (this.config.canvasWidth * 0.8) / img.width,
                        (this.config.canvasHeight * 0.8) / img.height
                    );
                    
                    img.scale(scale);
                    img.set({
                        left: 50,
                        top: 50
                    });
                    
                    this.canvas.add(img);
                    this.canvas.setActiveObject(img);
                });
            };
            reader.readAsDataURL(file);
        }

        updateProductImage(imageUrl) {
            // Update product preview image
            fabric.Image.fromURL(imageUrl, (img) => {
                img.set({
                    selectable: false,
                    evented: false,
                    opacity: 0.5
                });
                this.canvas.setBackgroundImage(img, this.canvas.renderAll.bind(this.canvas));
            });
        }

        saveDesign() {
            if (!pdwSettings.isLoggedIn) {
                window.location.href = pdwSettings.loginUrl;
                return;
            }

            const designData = JSON.stringify(this.canvas.toJSON());

            $.ajax({
                url: pdwSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdw_save_design',
                    nonce: pdwSettings.nonce,
                    product_id: this.productId,
                    design_data: designData
                },
                success: (response) => {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        }

        loadDesign() {
            if (!pdwSettings.isLoggedIn) {
                window.location.href = pdwSettings.loginUrl;
                return;
            }

            $.ajax({
                url: pdwSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdw_list_designs',
                    nonce: pdwSettings.nonce,
                    product_id: this.productId
                },
                success: (response) => {
                    if (response.success) {
                        this.showDesignsList(response.data.designs);
                    }
                }
            });
        }

        showDesignsList(designs) {
            const modal = $(`
                <div class="pdw-modal">
                    <div class="pdw-modal-content">
                        <h3>Saved Designs</h3>
                        <div class="pdw-designs-list"></div>
                    </div>
                </div>
            `);

            const list = modal.find('.pdw-designs-list');

            designs.forEach(design => {
                const item = $(`
                    <div class="pdw-design-item" data-id="${design.id}">
                        <span>Created: ${design.created}</span>
                        <button>Load</button>
                    </div>
                `);

                item.find('button').on('click', () => {
                    this.loadDesignById(design.id);
                    modal.remove();
                });

                list.append(item);
            });

            $('body').append(modal);
        }

        loadDesignById(designId) {
            $.ajax({
                url: pdwSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pdw_load_design',
                    nonce: pdwSettings.nonce,
                    design_id: designId
                },
                success: (response) => {
                    if (response.success) {
                        this.canvas.loadFromJSON(response.data.design_data, () => {
                            this.canvas.renderAll();
                        });
                    }
                }
            });
        }

        saveDesignToForm() {
            const designData = JSON.stringify(this.canvas.toJSON());
            $('#pdw_design_data').val(designData);
        }
    }

    // Initialize designer when document is ready
    $(document).ready(() => {
        if ($('#pdw-canvas').length) {
            new PrintDesigner();
        }
    });

})(jQuery);