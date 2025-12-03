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

                    // Remove existing S3 info
                    this.$('.mt-offload-section').remove();
                    this.$('.settings.mt-offload-section').remove();

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
