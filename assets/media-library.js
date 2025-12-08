/**
 * Media Library Cloud Storage Integration
 * 
 * Handles:
 * - Cloud storage info display in attachment details modal
 * - Image optimization controls
 * - Row action handlers (upload, re-upload, download)
 * - Bulk action confirmation
 * 
 * @version 2.5.0
 */
(function ($, wp) {
    'use strict';

    console.log('[Media Toolkit] media-library.js loaded v2.5.0');

    // Utility: Format bytes to human readable
    window.mediaToolkitMedia = window.mediaToolkitMedia || window.mtMedia || {};

    // Alias for backwards compatibility
    var mtMedia = window.mediaToolkitMedia;

    mtMedia.formatBytes = function (bytes, decimals = 1) {
        if (bytes === 0) return '0 B';

        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
    };

    // Initialize when document is ready
    $(document).ready(function () {
        initRowActions();
        initAttachmentDetails();
    });

    /**
     * Initialize row actions for list view
     */
    function initRowActions() {
        // Upload to cloud storage (new)
        $(document).on('click', '.mt-action-upload', function (e) {
            e.preventDefault();

            var $link = $(this);
            var attachmentId = $link.data('id');

            var originalText = $link.text();
            $link.text(mtMedia.strings.uploading);

            $.ajax({
                url: mtMedia.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_upload_single',
                    attachment_id: attachmentId,
                    nonce: mtMedia.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $link.text(mtMedia.strings.success);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        $link.text(mtMedia.strings.error);
                        alert(response.data.message || 'Upload failed');
                        setTimeout(function () {
                            $link.text(originalText);
                        }, 2000);
                    }
                },
                error: function () {
                    $link.text(mtMedia.strings.error);
                    setTimeout(function () {
                        $link.text(originalText);
                    }, 2000);
                }
            });
        });

        // Re-upload to cloud storage
        $(document).on('click', '.mt-action-reupload', function (e) {
            e.preventDefault();

            var $link = $(this);
            var attachmentId = $link.data('id');

            if (!confirm(mtMedia.strings.confirmReupload)) {
                return;
            }

            var originalText = $link.text();
            $link.text(mtMedia.strings.uploading);

            $.ajax({
                url: mtMedia.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_reupload',
                    attachment_id: attachmentId,
                    nonce: mtMedia.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $link.text(mtMedia.strings.success);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        $link.text(mtMedia.strings.error);
                        alert(response.data.message || 'Upload failed');
                        setTimeout(function () {
                            $link.text(originalText);
                        }, 2000);
                    }
                },
                error: function () {
                    $link.text(mtMedia.strings.error);
                    setTimeout(function () {
                        $link.text(originalText);
                    }, 2000);
                }
            });
        });

        // Download from cloud storage
        $(document).on('click', '.mt-action-download', function (e) {
            e.preventDefault();

            var $link = $(this);
            var attachmentId = $link.data('id');

            if (!confirm(mtMedia.strings.confirmDownload)) {
                return;
            }

            var originalText = $link.text();
            $link.text(mtMedia.strings.downloading);

            $.ajax({
                url: mtMedia.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_download_from_cloud',
                    attachment_id: attachmentId,
                    nonce: mtMedia.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $link.text(mtMedia.strings.success);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        $link.text(mtMedia.strings.error);
                        alert(response.data.message || 'Download failed');
                        setTimeout(function () {
                            $link.text(originalText);
                        }, 2000);
                    }
                },
                error: function () {
                    $link.text(mtMedia.strings.error);
                    setTimeout(function () {
                        $link.text(originalText);
                    }, 2000);
                }
            });
        });
    }

    /**
     * Initialize attachment details modal integration
     */
    function initAttachmentDetails() {
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            return;
        }

        // Extend the TwoColumn view to add cloud storage info
        var AttachmentDetailsTwoColumn = wp.media.view.Attachment.Details.TwoColumn;

        if (AttachmentDetailsTwoColumn) {
            wp.media.view.Attachment.Details.TwoColumn = AttachmentDetailsTwoColumn.extend({
                render: function () {
                    // Call parent render
                    AttachmentDetailsTwoColumn.prototype.render.apply(this, arguments);

                    // Add cloud storage info after render
                    this.renderCloudInfo();

                    return this;
                },

                renderCloudInfo: function () {
                    var self = this;
                    var model = this.model;
                    var data = model.toJSON();

                    // Get the template
                    var template = wp.template('mt-offload-details');

                    if (!template) {
                        return;
                    }

                    // Remove existing sections (Cloud, Optimization and AI)
                    this.$('.mt-offload-section').remove();
                    this.$('.settings.mt-offload-section').remove();
                    this.$('.mt-optimization-section').remove();
                    this.$('.settings.mt-optimization-section').remove();
                    this.$('.mt-ai-section').remove();
                    this.$('.settings.mt-ai-section').remove();

                    // Add cloud info after the settings section
                    var $settings = this.$('.attachment-info .settings');

                    if ($settings.length) {
                        $settings.after(template(data));
                    } else {
                        // Fallback: add to attachment-info
                        var $info = this.$('.attachment-info');
                        if ($info.length) {
                            $info.append(template(data));
                        }
                    }

                    this.bindCloudActions();
                },

                bindCloudActions: function () {
                    var self = this;
                    var model = this.model;

                    // Upload button (new upload)
                    this.$('.mt-btn-upload').off('click').on('click', function (e) {
                        e.preventDefault();

                        var $btn = $(this);
                        var attachmentId = $btn.data('id');

                        $btn.prop('disabled', true);
                        $btn.find('.dashicons').removeClass('dashicons-cloud-upload').addClass('dashicons-update animate-spin');

                        $.ajax({
                            url: mtMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_upload_single',
                                attachment_id: attachmentId,
                                nonce: mtMedia.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    // Update model data
                                    model.set('cloudStorage', $.extend({}, model.get('cloudStorage'), {
                                        migrated: true,
                                        storageKey: response.data.storageKey,
                                        storageUrl: response.data.storageUrl
                                    }));

                                    // Re-render
                                    self.renderCloudInfo();
                                } else {
                                    alert(response.data.message || 'Upload failed');
                                    $btn.prop('disabled', false);
                                    $btn.find('.dashicons').removeClass('dashicons-update animate-spin').addClass('dashicons-cloud-upload');
                                }
                            },
                            error: function (xhr, status, error) {
                                alert('Upload failed: ' + error);
                                $btn.prop('disabled', false);
                                $btn.find('.dashicons').removeClass('dashicons-update animate-spin').addClass('dashicons-cloud-upload');
                            }
                        });
                    });

                    // Re-upload button
                    this.$('.mt-btn-reupload').off('click').on('click', function (e) {
                        e.preventDefault();

                        var $btn = $(this);
                        var attachmentId = $btn.data('id');

                        $btn.prop('disabled', true);
                        $btn.find('.dashicons').addClass('animate-spin');

                        $.ajax({
                            url: mtMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_reupload',
                                attachment_id: attachmentId,
                                nonce: mtMedia.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    // Update model data
                                    model.set('cloudStorage', $.extend({}, model.get('cloudStorage'), {
                                        storageUrl: response.data.storageUrl
                                    }));

                                    // Show success briefly
                                    $btn.find('.dashicons').removeClass('animate-spin');
                                    $btn.text('✓ Synced');

                                    setTimeout(function () {
                                        self.renderCloudInfo();
                                    }, 1500);
                                } else {
                                    alert(response.data.message || 'Re-upload failed');
                                    $btn.prop('disabled', false);
                                    $btn.find('.dashicons').removeClass('animate-spin');
                                }
                            },
                            error: function (xhr, status, error) {
                                alert('Re-upload failed: ' + error);
                                $btn.prop('disabled', false);
                                $btn.find('.dashicons').removeClass('animate-spin');
                            }
                        });
                    });

                    // Download button
                    this.$('.mt-btn-download').off('click').on('click', function (e) {
                        e.preventDefault();

                        var $btn = $(this);
                        var attachmentId = $btn.data('id');

                        if (!confirm(mtMedia.strings.confirmDownload)) {
                            return;
                        }

                        $btn.prop('disabled', true);
                        $btn.find('.dashicons').removeClass('dashicons-download').addClass('dashicons-update animate-spin');

                        $.ajax({
                            url: mtMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_download_from_cloud',
                                attachment_id: attachmentId,
                                nonce: mtMedia.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    // Update model data
                                    model.set('cloudStorage', $.extend({}, model.get('cloudStorage'), {
                                        localExists: true
                                    }));

                                    // Re-render
                                    self.renderCloudInfo();
                                } else {
                                    alert(response.data.message || 'Download failed');
                                    $btn.prop('disabled', false);
                                    $btn.find('.dashicons').removeClass('dashicons-update animate-spin').addClass('dashicons-download');
                                }
                            },
                            error: function (xhr, status, error) {
                                alert('Download failed: ' + error);
                                $btn.prop('disabled', false);
                                $btn.find('.dashicons').removeClass('dashicons-update animate-spin').addClass('dashicons-download');
                            }
                        });
                    });

                    // AI Generate button
                    this.$('.mt-btn-generate-ai').off('click').on('click', function (e) {
                        e.preventDefault();

                        var $btn = $(this);
                        var attachmentId = $btn.data('id');
                        var originalHtml = $btn.html();

                        $btn.prop('disabled', true);
                        $btn.text(mtMedia.strings.generating || 'Generating...');

                        $.ajax({
                            url: mtMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_ai_generate_single',
                                attachment_id: attachmentId,
                                nonce: mtMedia.nonce,
                                overwrite: false
                            },
                            success: function (response) {
                                console.log('AI Generate Response:', response);
                                if (response.success) {
                                    $btn.text('✓ Generated!');
                                    // Force reload with cache bypass
                                    setTimeout(function () {
                                        window.location.href = window.location.href.split('#')[0];
                                    }, 500);
                                } else {
                                    console.error('AI Generate Error:', response.data);
                                    var errorMsg = (response.data && response.data.message)
                                        ? response.data.message
                                        : (response.data && response.data.error)
                                            ? response.data.error
                                            : (mtMedia.strings.aiError || 'Generation failed');
                                    alert(errorMsg);
                                    $btn.prop('disabled', false).html(originalHtml);
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('AI Generate AJAX Error:', { xhr: xhr, status: status, error: error });
                                var errorMsg = mtMedia.strings.aiError || 'Generation failed';
                                if (error) {
                                    errorMsg += ': ' + error;
                                }
                                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                                    errorMsg = xhr.responseJSON.data.message;
                                }
                                alert(errorMsg);
                                $btn.prop('disabled', false).html(originalHtml);
                            }
                        });
                    });

                    // Optimize button (new optimization)
                    this.$('.mt-btn-optimize').off('click').on('click', function (e) {
                        e.preventDefault();

                        var $btn = $(this);
                        var attachmentId = $btn.data('id');
                        var originalHtml = $btn.html();

                        $btn.prop('disabled', true);
                        $btn.text(mtMedia.strings.optimizing || 'Optimizing...');

                        $.ajax({
                            url: mtMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_optimize_single',
                                attachment_id: attachmentId,
                                nonce: mtMedia.nonce
                            },
                            success: function (response) {
                                console.log('Optimize Response:', response);
                                if (response.success) {
                                    $btn.text('✓ ' + (mtMedia.strings.optimized || 'Optimized!'));

                                    // Update model data with new optimization info
                                    if (response.data.optimization) {
                                        model.set('optimization', response.data.optimization);
                                    }

                                    // Re-render after a short delay
                                    setTimeout(function () {
                                        self.renderCloudInfo();
                                    }, 1000);
                                } else {
                                    console.error('Optimize Error:', response.data);
                                    var errorMsg = (response.data && response.data.message)
                                        ? response.data.message
                                        : (mtMedia.strings.optimizeError || 'Optimization failed');
                                    alert(errorMsg);
                                    $btn.prop('disabled', false).html(originalHtml);
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('Optimize AJAX Error:', { xhr: xhr, status: status, error: error });
                                var errorMsg = mtMedia.strings.optimizeError || 'Optimization failed';
                                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                                    errorMsg = xhr.responseJSON.data.message;
                                }
                                alert(errorMsg);
                                $btn.prop('disabled', false).html(originalHtml);
                            }
                        });
                    });

                    // Re-optimize button
                    this.$('.mt-btn-reoptimize').off('click').on('click', function (e) {
                        e.preventDefault();

                        var $btn = $(this);
                        var attachmentId = $btn.data('id');
                        var originalHtml = $btn.html();

                        $btn.prop('disabled', true);
                        $btn.text(mtMedia.strings.optimizing || 'Optimizing...');

                        $.ajax({
                            url: mtMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_optimize_single',
                                attachment_id: attachmentId,
                                nonce: mtMedia.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    $btn.text('✓ ' + (mtMedia.strings.optimized || 'Optimized!'));

                                    // Update model data with new optimization info
                                    if (response.data.optimization) {
                                        model.set('optimization', response.data.optimization);
                                    }

                                    // Re-render after a short delay
                                    setTimeout(function () {
                                        self.renderCloudInfo();
                                    }, 1000);
                                } else {
                                    var errorMsg = (response.data && response.data.message)
                                        ? response.data.message
                                        : (mtMedia.strings.optimizeError || 'Optimization failed');
                                    alert(errorMsg);
                                    $btn.prop('disabled', false).html(originalHtml);
                                }
                            },
                            error: function (xhr, status, error) {
                                var errorMsg = mtMedia.strings.optimizeError || 'Optimization failed';
                                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                                    errorMsg = xhr.responseJSON.data.message;
                                }
                                alert(errorMsg);
                                $btn.prop('disabled', false).html(originalHtml);
                            }
                        });
                    });
                }
            });
        }

        // Also extend the single column view for edit media page
        var AttachmentDetails = wp.media.view.Attachment.Details;

        if (AttachmentDetails) {
            wp.media.view.Attachment.Details = AttachmentDetails.extend({
                render: function () {
                    AttachmentDetails.prototype.render.apply(this, arguments);
                    this.renderCloudInfo();
                    return this;
                },

                renderCloudInfo: function () {
                    var self = this;
                    var data = this.model.toJSON();
                    var template = wp.template('mt-offload-details');

                    if (!template || !data.cloudStorage) {
                        return;
                    }

                    this.$('.mt-offload-section').remove();
                    this.$('.settings.mt-offload-section').remove();

                    var $info = this.$('.attachment-info');
                    if ($info.length) {
                        $info.append(template(data));

                        // Bind actions using the same method
                        if (wp.media.view.Attachment.Details.TwoColumn.prototype.bindCloudActions) {
                            wp.media.view.Attachment.Details.TwoColumn.prototype.bindCloudActions.call(this);
                        }
                    }
                }
            });
        }
    }

})(jQuery, wp);
