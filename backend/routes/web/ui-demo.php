<?php

use Illuminate\Support\Facades\Route;

Route::get('/ui-alerts', function () { return view('ui-kit.ui.ui-alerts'); })->name('ui-alerts');
Route::get('/ui-accordion', function () { return view('ui-kit.ui.ui-accordion'); })->name('ui-accordion');
Route::get('/ui-avatar', function () { return view('ui-kit.ui.ui-avatar'); })->name('ui-avatar');
Route::get('/ui-badges', function () { return view('ui-kit.ui.ui-badges'); })->name('ui-badges');
Route::get('/ui-borders', function () { return view('ui-kit.ui.ui-borders'); })->name('ui-borders');
Route::get('/ui-buttons', function () { return view('ui-kit.ui.ui-buttons'); })->name('ui-buttons');
Route::get('/ui-buttons-group', function () { return view('ui-kit.ui.ui-buttons-group'); })->name('ui-buttons-group');
Route::get('/ui-breadcrumb', function () { return view('ui-kit.ui.ui-breadcrumb'); })->name('ui-breadcrumb');
Route::get('/ui-cards', function () { return view('ui-kit.ui.ui-cards'); })->name('ui-cards');
Route::get('/ui-carousel', function () { return view('ui-kit.ui.ui-carousel'); })->name('ui-carousel');
Route::get('/ui-colors', function () { return view('ui-kit.ui.ui-colors'); })->name('ui-colors');
Route::get('/ui-dropdowns', function () { return view('ui-kit.ui.ui-dropdowns'); })->name('ui-dropdowns');
Route::get('/ui-grid', function () { return view('ui-kit.ui.ui-grid'); })->name('ui-grid');
Route::get('/ui-images', function () { return view('ui-kit.ui.ui-images'); })->name('ui-images');
Route::get('/ui-lightbox', function () { return view('ui-kit.ui.ui-lightbox'); })->name('ui-lightbox');
Route::get('/ui-media', function () { return view('ui-kit.ui.ui-media'); })->name('ui-media');
Route::get('/ui-modals', function () { return view('ui-kit.ui.ui-modals'); })->name('ui-modals');
Route::get('/ui-offcanvas', function () { return view('ui-kit.ui.ui-offcanvas'); })->name('ui-offcanvas');
Route::get('/ui-pagination', function () { return view('ui-kit.ui.ui-pagination'); })->name('ui-pagination');
Route::get('/ui-popovers', function () { return view('ui-kit.ui.ui-popovers'); })->name('ui-popovers');
Route::get('/ui-progress', function () { return view('ui-kit.ui.ui-progress'); })->name('ui-progress');
Route::get('/ui-placeholders', function () { return view('ui-kit.ui.ui-placeholders'); })->name('ui-placeholders');
Route::get('/ui-rangeslider', function () { return view('ui-kit.ui.ui-rangeslider'); })->name('ui-rangeslider');
Route::get('/ui-spinner', function () { return view('ui-kit.ui.ui-spinner'); })->name('ui-spinner');
Route::get('/ui-sweetalerts', function () { return view('ui-kit.ui.ui-sweetalerts'); })->name('ui-sweetalerts');
Route::get('/ui-nav-tabs', function () { return view('ui-kit.ui.ui-nav-tabs'); })->name('ui-nav-tabs');
Route::get('/ui-toasts', function () { return view('ui-kit.ui.ui-toasts'); })->name('ui-toasts');
Route::get('/ui-tooltips', function () { return view('ui-kit.ui.ui-tooltips'); })->name('ui-tooltips');
Route::get('/ui-typography', function () { return view('ui-kit.ui.ui-typography'); })->name('ui-typography');
Route::get('/ui-video', function () { return view('ui-kit.ui.ui-video'); })->name('ui-video');
Route::get('/ui-ribbon', function () { return view('ui-kit.ui.ui-ribbon'); })->name('ui-ribbon');
Route::get('/ui-clipboard', function () { return view('ui-kit.ui.ui-clipboard'); })->name('ui-clipboard');
Route::get('/ui-drag-drop', function () { return view('ui-kit.ui.ui-drag-drop'); })->name('ui-drag-drop');
Route::get('/ui-rating', function () { return view('ui-kit.ui.ui-rating'); })->name('ui-rating');
Route::get('/ui-text-editor', function () { return view('ui-kit.ui.ui-text-editor'); })->name('ui-text-editor');
Route::get('/ui-swiperjs', function () { return view('ui-kit.ui.ui-swiperjs'); })->name('ui-swiperjs');
Route::get('/ui-counter', function () { return view('ui-kit.ui.ui-counter'); })->name('ui-counter');
Route::get('/ui-scrollbar', function () { return view('ui-kit.ui.ui-scrollbar'); })->name('ui-scrollbar');
Route::get('/ui-stickynote', function () { return view('ui-kit.ui.ui-stickynote'); })->name('ui-stickynote');
Route::get('/ui-timeline', function () { return view('ui-kit.ui.ui-timeline'); })->name('ui-timeline');

// Charts
Route::get('/chart-apex', function () { return view('ui-kit.charts.chart-apex'); })->name('chart-apex');
Route::get('/chart-c3', function () { return view('ui-kit.charts.chart-c3'); })->name('chart-c3');
Route::get('/chart-flot', function () { return view('ui-kit.charts.chart-flot'); })->name('chart-flot');
Route::get('/chart-js', function () { return view('ui-kit.charts.chart-js'); })->name('chart-js');
Route::get('/chart-morris', function () { return view('ui-kit.charts.chart-morris'); })->name('chart-morris');
Route::get('/chart-peity', function () { return view('ui-kit.charts.chart-peity'); })->name('chart-peity');

// Icons
Route::get('/icon-fontawesome', function () { return view('ui-kit.icons.icon-fontawesome'); })->name('icon-fontawesome');
Route::get('/icon-feather', function () { return view('ui-kit.icons.icon-feather'); })->name('icon-feather');
Route::get('/icon-ionic', function () { return view('ui-kit.icons.icon-ionic'); })->name('icon-ionic');
Route::get('/icon-material', function () { return view('ui-kit.icons.icon-material'); })->name('icon-material');
Route::get('/icon-pe7', function () { return view('ui-kit.icons.icon-pe7'); })->name('icon-pe7');
Route::get('/icon-simpleline', function () { return view('ui-kit.icons.icon-simpleline'); })->name('icon-simpleline');
Route::get('/icon-themify', function () { return view('ui-kit.icons.icon-themify'); })->name('icon-themify');
Route::get('/icon-weather', function () { return view('ui-kit.icons.icon-weather'); })->name('icon-weather');
Route::get('/icon-typicon', function () { return view('ui-kit.icons.icon-typicon'); })->name('icon-typicon');
Route::get('/icon-flag', function () { return view('ui-kit.icons.icon-flag'); })->name('icon-flag');
Route::get('/icon-tabler', function () { return view('ui-kit.icons.icon-tabler'); })->name('icon-tabler');
Route::get('/icon-bootstrap', function () { return view('ui-kit.icons.icon-bootstrap'); })->name('icon-bootstrap');
Route::get('/icon-remix', function () { return view('ui-kit.icons.icon-remix'); })->name('icon-remix');

// Forms
Route::get('/form-checkbox-radios', function () { return view('ui-kit.forms.form-checkbox-radios'); })->name('form-checkbox-radios');
Route::get('/form-floating-labels', function () { return view('ui-kit.forms.form-floating-labels'); })->name('form-floating-labels');
Route::get('/form-grid-gutters', function () { return view('ui-kit.forms.form-grid-gutters'); })->name('form-grid-gutters');
Route::get('/form-elements', function () { return view('ui-kit.forms.form-elements'); })->name('form-elements');
Route::get('/form-select', function () { return view('ui-kit.forms.form-select'); })->name('form-select');
Route::get('/form-select2', function () { return view('ui-kit.forms.form-select2'); })->name('form-select2');
Route::get('/form-fileupload', function () { return view('ui-kit.forms.form-fileupload'); })->name('form-fileupload');
Route::get('/form-wizard', function () { return view('ui-kit.forms.form-wizard'); })->name('form-wizard');
Route::get('/form-basic-inputs', function () { return view('ui-kit.forms.form-basic-inputs'); })->name('form-basic-inputs');
Route::get('/form-input-groups', function () { return view('ui-kit.forms.form-input-groups'); })->name('form-input-groups');
Route::get('/form-horizontal', function () { return view('ui-kit.forms.form-horizontal'); })->name('form-horizontal');
Route::get('/form-vertical', function () { return view('ui-kit.forms.form-vertical'); })->name('form-vertical');
Route::get('/form-mask', function () { return view('ui-kit.forms.form-mask'); })->name('form-mask');
Route::get('/form-validation', function () { return view('ui-kit.forms.form-validation'); })->name('form-validation');
Route::get('/form-pickers', function () { return view(view: 'form-pickers'); })->name('form-pickers');

// Tables & maps
Route::get('/tables-basic', function () { return view('ui-kit.tables.tables-basic'); })->name('tables-basic');
Route::get('/data-tables', function () { return view('ui-kit.tables.data-tables'); })->name('data-tables');
Route::get('/maps-vector', function () { return view('ui-kit.maps.maps-vector'); })->name('maps-vector');
Route::get('/maps-leaflet', function () { return view('ui-kit.maps.maps-leaflet'); })->name('maps-leaflet');
