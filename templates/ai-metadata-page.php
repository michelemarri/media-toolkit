<?php
/**
 * AI Metadata page template
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$plugin = \Metodo\MediaToolkit\media_toolkit();
$ai_manager = $plugin->get_ai_manager();
$admin_ai = new \Metodo\MediaToolkit\Admin\Admin_AI_Metadata(
    $ai_manager,
    $plugin->get_metadata_generator()
);

$stats = $admin_ai->get_stats();
$state = $admin_ai->get_state();
$is_available = $admin_ai->is_available();
$cost_estimate = $admin_ai->get_cost_estimate(true);

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';

$bannerPath = MEDIA_TOOLKIT_PATH . 'assets/images/banner-1544x500.png';
$bannerUrl = MEDIA_TOOLKIT_URL . 'assets/images/banner-1544x500.png';
$hasBanner = file_exists($bannerPath);
?>

<div class="wrap mt-wrap">
    <div class="flex flex-col gap-6 max-w-7xl mx-auto py-5 px-6">
    <?php if ($hasBanner): ?>
    <!-- Hero Banner -->
    <div class="mt-hero">
        <img src="<?php echo esc_url($bannerUrl); ?>" alt="Media Toolkit" class="mt-hero-banner">
        <div class="mt-hero-overlay">
            <h1 class="mt-hero-title"><?php esc_html_e('AI Metadata', 'media-toolkit'); ?></h1>
            <p class="mt-hero-description"><?php esc_html_e('Generate alt text, titles, captions and descriptions using AI', 'media-toolkit'); ?></p>
            <span class="mt-hero-version">v<?php echo esc_html(MEDIA_TOOLKIT_VERSION); ?></span>
        </div>
    </div>
    <?php else: ?>
    <!-- Header -->
    <header>
        <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 tracking-tight mb-2">
            <span class="mt-logo">
                <span class="dashicons dashicons-format-image"></span>
            </span>
            <?php esc_html_e('AI Metadata', 'media-toolkit'); ?>
        </h1>
        <p class="text-lg text-gray-500 max-w-xl"><?php esc_html_e('Generate alt text, titles, captions and descriptions using AI', 'media-toolkit'); ?></p>
    </header>
    <?php endif; ?>

    <?php if (!$is_available): ?>
    <!-- Warning: No Provider Configured -->
    <div class="flex gap-3 p-4 rounded-xl bg-amber-50 text-amber-800 border border-amber-200">
        <span class="dashicons dashicons-warning text-amber-600 flex-shrink-0 mt-0.5"></span>
        <div>
            <strong class="block text-sm font-semibold mb-1"><?php esc_html_e('No AI Provider Configured', 'media-toolkit'); ?></strong>
            <p class="text-sm opacity-90 m-0">
                <?php 
                printf(
                    esc_html__('Please configure at least one AI provider in %s to use this feature.', 'media-toolkit'),
                    '<a href="' . esc_url(admin_url('admin.php?page=media-toolkit-settings&tab=ai-providers')) . '" class="font-medium underline">' . esc_html__('Settings → AI Providers', 'media-toolkit') . '</a>'
                );
                ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <nav class="flex flex-wrap gap-1 p-1 bg-gray-100 rounded-xl">
        <a href="?page=media-toolkit-ai-metadata&tab=dashboard" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'dashboard' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
            <span class="dashicons dashicons-dashboard"></span>
            <?php esc_html_e('Dashboard', 'media-toolkit'); ?>
        </a>
        <a href="?page=media-toolkit-ai-metadata&tab=generate" class="flex items-center gap-2 px-5 py-3 text-sm font-medium rounded-lg transition-all whitespace-nowrap <?php echo $active_tab === 'generate' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700 hover:bg-white/50'; ?>">
            <span class="dashicons dashicons-admin-customizer"></span>
            <?php esc_html_e('Generate', 'media-toolkit'); ?>
        </a>
    </nav>

    <!-- Tab Content -->
    <div class="bg-gray-100 rounded-xl p-6 animate-fade-in">
        <?php if ($active_tab === 'dashboard'): ?>
            <!-- ==================== DASHBOARD TAB ==================== -->
            
            <!-- Stats Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-6">
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gray-100 text-gray-600">
                            <span class="dashicons dashicons-format-image"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Total Images', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html(number_format($stats['total_images'])); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('With Alt Text', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html(number_format($stats['with_alt_text'])); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-red-100 text-red-600">
                            <span class="dashicons dashicons-warning"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Missing Alt Text', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-red-600"><?php echo esc_html(number_format($stats['without_alt_text'])); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-100 text-blue-600">
                            <span class="dashicons dashicons-superhero-alt"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('AI Generated', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-gray-900"><?php echo esc_html(number_format($stats['ai_generated_count'])); ?></span>
                </div>
                
                <div class="p-5 bg-white border border-gray-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-purple-100 text-purple-600">
                            <span class="dashicons dashicons-chart-pie"></span>
                        </div>
                        <span class="text-sm text-gray-500"><?php esc_html_e('Completeness', 'media-toolkit'); ?></span>
                    </div>
                    <span class="block text-2xl font-bold text-purple-600"><?php echo esc_html($stats['overall_completeness']); ?>%</span>
                </div>
            </div>

            <!-- Field Completeness Cards -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm mb-6">
                <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <span class="dashicons dashicons-chart-bar text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Field Completeness', 'media-toolkit'); ?></h3>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Alt Text -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700"><?php esc_html_e('Alt Text', 'media-toolkit'); ?></span>
                                <span class="text-sm font-bold text-gray-900"><?php echo esc_html($stats['pct_alt_text']); ?>%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: <?php echo esc_attr($stats['pct_alt_text']); ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500"><?php echo esc_html(number_format($stats['with_alt_text'])); ?> / <?php echo esc_html(number_format($stats['total_images'])); ?></p>
                        </div>
                        
                        <!-- Title -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700"><?php esc_html_e('Title', 'media-toolkit'); ?></span>
                                <span class="text-sm font-bold text-gray-900"><?php echo esc_html($stats['pct_title']); ?>%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full transition-all" style="width: <?php echo esc_attr($stats['pct_title']); ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500"><?php echo esc_html(number_format($stats['with_title'])); ?> / <?php echo esc_html(number_format($stats['total_images'])); ?></p>
                        </div>
                        
                        <!-- Caption -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700"><?php esc_html_e('Caption', 'media-toolkit'); ?></span>
                                <span class="text-sm font-bold text-gray-900"><?php echo esc_html($stats['pct_caption']); ?>%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-500 rounded-full transition-all" style="width: <?php echo esc_attr($stats['pct_caption']); ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500"><?php echo esc_html(number_format($stats['with_caption'])); ?> / <?php echo esc_html(number_format($stats['total_images'])); ?></p>
                        </div>
                        
                        <!-- Description -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700"><?php esc_html_e('Description', 'media-toolkit'); ?></span>
                                <span class="text-sm font-bold text-gray-900"><?php echo esc_html($stats['pct_description']); ?>%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-purple-500 rounded-full transition-all" style="width: <?php echo esc_attr($stats['pct_description']); ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500"><?php echo esc_html(number_format($stats['with_description'])); ?> / <?php echo esc_html(number_format($stats['total_images'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Field Guidelines -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <div class="flex items-center gap-3">
                        <span class="dashicons dashicons-info text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Field Guidelines', 'media-toolkit'); ?></h3>
                    </div>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900"><?php esc_html_e('Field', 'media-toolkit'); ?></th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900"><?php esc_html_e('Visibility', 'media-toolkit'); ?></th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900"><?php esc_html_e('SEO Impact', 'media-toolkit'); ?></th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900"><?php esc_html_e('Length', 'media-toolkit'); ?></th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-900"><?php esc_html_e('Use Case', 'media-toolkit'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900"><?php esc_html_e('Title', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Media Library, sometimes tooltip', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700"><?php esc_html_e('Low', 'media-toolkit'); ?></span></td>
                                    <td class="px-4 py-3 text-gray-600">50-70 char</td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Identification', 'media-toolkit'); ?></td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900"><?php esc_html_e('Alt Text', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Screen readers, alt attribute', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700"><?php esc_html_e('High', 'media-toolkit'); ?></span></td>
                                    <td class="px-4 py-3 text-gray-600">Max 125 char</td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Accessibility + Image SEO', 'media-toolkit'); ?></td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900"><?php esc_html_e('Caption', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Below image in frontend', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700"><?php esc_html_e('Medium', 'media-toolkit'); ?></span></td>
                                    <td class="px-4 py-3 text-gray-600">150-250 char</td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Reader engagement', 'media-toolkit'); ?></td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900"><?php esc_html_e('Description', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Attachment page, crawlers', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700"><?php esc_html_e('Medium-High', 'media-toolkit'); ?></span></td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Unlimited', 'media-toolkit'); ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?php esc_html_e('Full context + keywords', 'media-toolkit'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'generate'): ?>
            <!-- ==================== GENERATE TAB ==================== -->
            
            <?php if (!$is_available): ?>
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <span class="dashicons dashicons-admin-plugins text-6xl text-gray-300 mb-4"></span>
                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php esc_html_e('AI Provider Required', 'media-toolkit'); ?></h3>
                <p class="text-gray-500 max-w-md mb-6">
                    <?php esc_html_e('Configure at least one AI provider (OpenAI, Claude, or Gemini) to start generating metadata.', 'media-toolkit'); ?>
                </p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=media-toolkit-settings&tab=ai-providers')); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg transition-all">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Configure AI Providers', 'media-toolkit'); ?>
                </a>
            </div>
            <?php else: ?>
            
            <!-- Settings & Controls Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Cost Estimation Card -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                        <span class="dashicons dashicons-money-alt text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Cost Estimation', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-5">
                            <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="ai-only-empty" checked>
                                <span class="mt-toggle-slider"></span>
                                <div>
                                    <span class="block text-sm font-semibold text-gray-900"><?php esc_html_e('Only empty fields', 'media-toolkit'); ?></span>
                                    <span class="block text-xs text-gray-500"><?php esc_html_e('Generate only for images missing metadata', 'media-toolkit'); ?></span>
                                </div>
                            </label>
                        </div>

                        <div id="cost-estimate-panel" class="p-5 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <span class="block text-xs text-gray-500 mb-1"><?php esc_html_e('Images to process', 'media-toolkit'); ?></span>
                                    <span class="block text-2xl font-bold text-gray-900" id="estimate-images"><?php echo esc_html(number_format($stats['without_alt_text'])); ?></span>
                                </div>
                                <div>
                                    <span class="block text-xs text-gray-500 mb-1"><?php esc_html_e('Estimated cost', 'media-toolkit'); ?></span>
                                    <span class="block text-2xl font-bold text-emerald-600" id="estimate-cost">
                                        $<?php echo esc_html(number_format($cost_estimate['total'], 2)); ?>
                                    </span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">
                                <?php 
                                printf(
                                    esc_html__('Using %s at ~$%s per image', 'media-toolkit'),
                                    '<strong>' . esc_html($cost_estimate['provider'] ?: 'N/A') . '</strong>',
                                    esc_html(number_format($cost_estimate['per_image'], 4))
                                ); 
                                ?>
                            </p>
                        </div>
                        
                        <button type="button" class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all" id="btn-refresh-estimate">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh Estimate', 'media-toolkit'); ?>
                        </button>
                    </div>
                </div>

                <!-- Batch Processing Controls -->
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                        <span class="dashicons dashicons-controls-play text-gray-700"></span>
                        <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Batch Generation', 'media-toolkit'); ?></h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label for="ai-batch-size" class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Batch Size', 'media-toolkit'); ?></label>
                                <select id="ai-batch-size" class="mt-select w-full">
                                    <option value="5"><?php esc_html_e('5 images per batch', 'media-toolkit'); ?></option>
                                    <option value="10" selected><?php esc_html_e('10 images per batch', 'media-toolkit'); ?></option>
                                    <option value="25"><?php esc_html_e('25 images per batch', 'media-toolkit'); ?></option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e('Overwrite', 'media-toolkit'); ?></label>
                                <label class="mt-toggle inline-flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" id="ai-overwrite">
                                    <span class="mt-toggle-slider"></span>
                                    <span class="text-sm text-gray-600"><?php esc_html_e('Overwrite existing', 'media-toolkit'); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3">
                            <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 text-base font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-start-ai-generation">
                                <span class="dashicons dashicons-admin-customizer"></span>
                                <?php esc_html_e('Start Generation', 'media-toolkit'); ?>
                            </button>
                            
                            <div class="flex gap-2">
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-pause-ai-generation" disabled>
                                    <span class="dashicons dashicons-controls-pause"></span>
                                    <?php esc_html_e('Pause', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-resume-ai-generation" disabled>
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php esc_html_e('Resume', 'media-toolkit'); ?>
                                </button>
                                <button type="button" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-all" id="btn-stop-ai-generation" disabled>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Cancel', 'media-toolkit'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="ai-generation-status" class="<?php echo $state['status'] !== 'idle' ? '' : 'hidden'; ?> mt-5">
                            <div class="space-y-3">
                                <!-- Progress Bar -->
                                <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-700"><?php esc_html_e('Progress', 'media-toolkit'); ?></span>
                                        <span class="inline-flex items-center px-2.5 py-1 text-sm font-bold text-white bg-gray-800 rounded-full" id="ai-progress-percentage">0%</span>
                                    </div>
                                    <div class="w-full h-3 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500 ease-out" id="ai-progress-bar" style="width: 0%"></div>
                                    </div>
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-xs text-gray-500">
                                            <span id="ai-processed-count"><?php echo esc_html($state['processed']); ?></span> / <span id="ai-total-count"><?php echo esc_html($state['total_files']); ?></span> <?php esc_html_e('images', 'media-toolkit'); ?>
                                        </span>
                                        <span class="<?php echo ($state['failed'] ?? 0) > 0 ? '' : 'hidden'; ?> px-2 py-0.5 text-xs font-medium rounded mt-badge-error" id="ai-failed-badge">
                                            <span id="ai-failed-count"><?php echo esc_html($state['failed'] ?? 0); ?></span> <?php esc_html_e('failed', 'media-toolkit'); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Status Info -->
                                <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200">
                                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100">
                                        <span class="dashicons dashicons-clock text-gray-600"></span>
                                    </div>
                                    <div>
                                        <span class="block text-xs text-gray-500"><?php esc_html_e('Status', 'media-toolkit'); ?></span>
                                        <span class="text-sm font-semibold text-gray-900" id="ai-status-text"><?php echo esc_html(ucfirst($state['status'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Generation Log -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-b from-gray-50 to-gray-100 border-b border-gray-200">
                    <span class="dashicons dashicons-media-text text-gray-700"></span>
                    <h3 class="text-base font-semibold text-gray-900"><?php esc_html_e('Generation Log', 'media-toolkit'); ?></h3>
                </div>
                <div class="mt-terminal">
                    <div class="mt-terminal-header">
                        <div class="flex gap-2">
                            <span class="mt-terminal-dot mt-terminal-dot-red"></span>
                            <span class="mt-terminal-dot mt-terminal-dot-yellow"></span>
                            <span class="mt-terminal-dot mt-terminal-dot-green"></span>
                        </div>
                        <span class="mt-terminal-title"><?php esc_html_e('ai-generation.log', 'media-toolkit'); ?></span>
                    </div>
                    <div class="mt-terminal-body" id="ai-generation-log">
                        <div class="mt-terminal-line">
                            <span class="mt-terminal-prompt">$</span>
                            <span class="mt-terminal-text mt-terminal-muted"><?php esc_html_e('Generation log will appear here...', 'media-toolkit'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="text-center text-sm text-gray-400 py-6">
        <p>
            <?php printf(esc_html__('Developed by %s', 'media-toolkit'), '<a href="https://metodo.dev" target="_blank" rel="noopener" class="font-medium hover:text-accent-500">Michele Marri - Metodo.dev</a>'); ?>
            &bull;
            <?php printf(esc_html__('Version %s', 'media-toolkit'), MEDIA_TOOLKIT_VERSION); ?>
        </p>
    </footer>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="mt-modal-overlay" style="display:none;">
    <div class="mt-modal">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900" id="confirm-title"><?php esc_html_e('Confirm Action', 'media-toolkit'); ?></h3>
            <button type="button" class="flex items-center justify-center w-8 h-8 border-0 bg-transparent text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-all cursor-pointer modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="p-6">
            <p id="confirm-message" class="text-sm text-gray-600"></p>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200">
            <button type="button" class="inline-flex items-center px-4 py-2 border-0 text-sm font-medium text-gray-600 bg-transparent hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-all cursor-pointer" id="btn-confirm-no"><?php esc_html_e('Cancel', 'media-toolkit'); ?></button>
            <button type="button" class="inline-flex items-center px-5 py-2 border-0 text-sm font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-lg shadow-sm transition-all cursor-pointer" id="btn-confirm-yes"><?php esc_html_e('Yes, Continue', 'media-toolkit'); ?></button>
        </div>
    </div>
</div>

