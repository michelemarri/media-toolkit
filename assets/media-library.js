/**
 * Media Library Cloud Storage Integration
 * 
 * Handles:
 * - Cloud storage info display in attachment details modal
 * - Row action handlers (upload, re-upload, download)
 * - Bulk action confirmation
 */
(function ($, wp) {
    'use strict';

    // Utility: Format bytes to human readable
    window.s3OffloadMedia = window.s3OffloadMedia || {};

    s3OffloadMedia.formatBytes = function (bytes, decimals = 1) {
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
            $link.text(s3OffloadMedia.strings.uploading);

            $.ajax({
                url: s3OffloadMedia.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_upload_single',
                    attachment_id: attachmentId,
                    nonce: s3OffloadMedia.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $link.text(s3OffloadMedia.strings.success);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        $link.text(s3OffloadMedia.strings.error);
                        alert(response.data.message || 'Upload failed');
                        setTimeout(function () {
                            $link.text(originalText);
                        }, 2000);
                    }
                },
                error: function () {
                    $link.text(s3OffloadMedia.strings.error);
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

            if (!confirm(s3OffloadMedia.strings.confirmReupload)) {
                return;
            }

            var originalText = $link.text();
            $link.text(s3OffloadMedia.strings.uploading);

            $.ajax({
                url: s3OffloadMedia.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_reupload',
                    attachment_id: attachmentId,
                    nonce: s3OffloadMedia.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $link.text(s3OffloadMedia.strings.success);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        $link.text(s3OffloadMedia.strings.error);
                        alert(response.data.message || 'Upload failed');
                        setTimeout(function () {
                            $link.text(originalText);
                        }, 2000);
                    }
                },
                error: function () {
                    $link.text(s3OffloadMedia.strings.error);
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

            if (!confirm(s3OffloadMedia.strings.confirmDownload)) {
                return;
            }

            var originalText = $link.text();
            $link.text(s3OffloadMedia.strings.downloading);

            $.ajax({
                url: s3OffloadMedia.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'media_toolkit_download_from_s3',
                    attachment_id: attachmentId,
                    nonce: s3OffloadMedia.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $link.text(s3OffloadMedia.strings.success);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    } else {
                        $link.text(s3OffloadMedia.strings.error);
                        alert(response.data.message || 'Download failed');
                        setTimeout(function () {
                            $link.text(originalText);
                        }, 2000);
                    }
                },
                error: function () {
                    $link.text(s3OffloadMedia.strings.error);
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

        // Extend the TwoColumn view to add S3 info
        var AttachmentDetailsTwoColumn = wp.media.view.Attachment.Details.TwoColumn;

        if (AttachmentDetailsTwoColumn) {
            wp.media.view.Attachment.Details.TwoColumn = AttachmentDetailsTwoColumn.extend({
                render: function () {
                    // Call parent render
                    AttachmentDetailsTwoColumn.prototype.render.apply(this, arguments);

                    // Add S3 info after render
                    this.renderS3Info();

                    return this;
                },

                renderS3Info: function () {
                    var self = this;
                    var model = this.model;
                    var data = model.toJSON();

                    // Get the template
                    var template = wp.template('mt-offload-details');

                    if (!template) {
                        return;
                    }

                    // Remove existing sections (S3 and AI)
                    this.$('.mt-offload-section').remove();
                    this.$('.settings.mt-offload-section').remove();
                    this.$('.mt-ai-section').remove();
                    this.$('.settings.mt-ai-section').remove();

                    // Add S3 info after the settings section
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

                    this.bindS3Actions();
                },

                bindS3Actions: function () {
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
                            url: s3OffloadMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_upload_single',
                                attachment_id: attachmentId,
                                nonce: s3OffloadMedia.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    // Update model data
                                    model.set('s3Offload', $.extend({}, model.get('s3Offload'), {
                                        migrated: true,
                                        s3Key: response.data.s3Key,
                                        s3Url: response.data.s3Url
                                    }));

                                    // Re-render
                                    self.renderS3Info();
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
                            url: s3OffloadMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_reupload',
                                attachment_id: attachmentId,
                                nonce: s3OffloadMedia.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    // Update model data
                                    model.set('s3Offload', $.extend({}, model.get('s3Offload'), {
                                        s3Url: response.data.s3Url
                                    }));

                                    // Show success briefly
                                    $btn.find('.dashicons').removeClass('animate-spin');
                                    $btn.text('✓ Synced');

                                    setTimeout(function () {
                                        self.renderS3Info();
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

                        if (!confirm(s3OffloadMedia.strings.confirmDownload)) {
                            return;
                        }

                        $btn.prop('disabled', true);
                        $btn.find('.dashicons').removeClass('dashicons-download').addClass('dashicons-update animate-spin');

                        $.ajax({
                            url: s3OffloadMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_download_from_s3',
                                attachment_id: attachmentId,
                                nonce: s3OffloadMedia.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    // Update model data
                                    model.set('s3Offload', $.extend({}, model.get('s3Offload'), {
                                        localExists: true
                                    }));

                                    // Re-render
                                    self.renderS3Info();
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
                        $btn.text(s3OffloadMedia.strings.generating || 'Generating...');

                        $.ajax({
                            url: s3OffloadMedia.ajaxUrl,
                            method: 'POST',
                            data: {
                                action: 'media_toolkit_ai_generate_single',
                                attachment_id: attachmentId,
                                nonce: s3OffloadMedia.nonce,
                                overwrite: false
                            },
                            success: function (response) {
                                console.log('AI Generate Response:', response);
                                if (response.success) {
                                    var metadata = response.data.metadata;
                                    
                                    if (metadata) {
                                        // Update Backbone model first - this syncs with WordPress
                                        if (metadata.title) {
                                            model.set('title', metadata.title);
                                        }
                                        if (metadata.alt_text) {
                                            model.set('alt', metadata.alt_text);
                                        }
                                        if (metadata.caption) {
                                            model.set('caption', metadata.caption);
                                        }
                                        if (metadata.description) {
                                            model.set('description', metadata.description);
                                        }
                                        
                                        // Save model to persist changes
                                        model.save();
                                        
                                        // Also update DOM fields directly for immediate feedback
                                        var $modal = $btn.closest('.media-modal, .attachment-details, .media-frame');
                                        if (!$modal.length) {
                                            $modal = $(document);
                                        }
                                        
                                        // Update title
                                        var $titleField = $modal.find('[data-setting="title"]');
                                        if ($titleField.length && metadata.title) {
                                            $titleField.val(metadata.title).trigger('change');
                                        }
                                        
                                        // Update alt text
                                        var $altField = $modal.find('[data-setting="alt"]');
                                        if ($altField.length && metadata.alt_text) {
                                            $altField.val(metadata.alt_text).trigger('change');
                                        }
                                        
                                        // Update caption
                                        var $captionField = $modal.find('[data-setting="caption"]');
                                        if ($captionField.length && metadata.caption) {
                                            $captionField.val(metadata.caption).trigger('change');
                                        }
                                        
                                        // Update description
                                        var $descField = $modal.find('[data-setting="description"]');
                                        if ($descField.length && metadata.description) {
                                            $descField.val(metadata.description).trigger('change');
                                        }
                                        
                                        console.log('Fields updated:', {
                                            title: $titleField.length,
                                            alt: $altField.length,
                                            caption: $captionField.length,
                                            description: $descField.length
                                        });
                                    }

                                    // Update model AI metadata status
                                    model.set('aiMetadata', $.extend({}, model.get('aiMetadata'), {
                                        generated: true,
                                        hasAltText: true,
                                        hasCaption: true
                                    }));

                                    $btn.text(s3OffloadMedia.strings.aiGenerated || 'Generated!');
                                    
                                    // Re-render after delay
                                    setTimeout(function () {
                                        self.renderS3Info();
                                    }, 1500);
                                } else {
                                    console.error('AI Generate Error:', response.data);
                                    var errorMsg = (response.data && response.data.message) 
                                        ? response.data.message 
                                        : (response.data && response.data.error) 
                                            ? response.data.error 
                                            : (s3OffloadMedia.strings.aiError || 'Generation failed');
                                    alert(errorMsg);
                                    $btn.prop('disabled', false).html(originalHtml);
                                }
                            },
                            error: function (xhr, status, error) {
                                console.error('AI Generate AJAX Error:', { xhr: xhr, status: status, error: error });
                                var errorMsg = s3OffloadMedia.strings.aiError || 'Generation failed';
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
                }
            });
        }

        // Also extend the single column view for edit media page
        var AttachmentDetails = wp.media.view.Attachment.Details;

        if (AttachmentDetails) {
            wp.media.view.Attachment.Details = AttachmentDetails.extend({
                render: function () {
                    AttachmentDetails.prototype.render.apply(this, arguments);
                    this.renderS3Info();
                    return this;
                },

                renderS3Info: function () {
                    var self = this;
                    var data = this.model.toJSON();
                    var template = wp.template('mt-offload-details');

                    if (!template || !data.s3Offload) {
                        return;
                    }

                    this.$('.mt-offload-section').remove();
                    this.$('.settings.mt-offload-section').remove();

                    var $info = this.$('.attachment-info');
                    if ($info.length) {
                        $info.append(template(data));

                        // Bind actions using the same method
                        if (wp.media.view.Attachment.Details.TwoColumn.prototype.bindS3Actions) {
                            wp.media.view.Attachment.Details.TwoColumn.prototype.bindS3Actions.call(this);
                        }
                    }
                }
            });
        }
    }

})(jQuery, wp);
