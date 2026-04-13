<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" sizes="180x180" href="{{ url('build/img/apple-touch-icon1.png') }}">

<!-- Favicon -->
<link rel="shortcut icon" type="image/x-icon" href="{{ url('build/img/favicon.png') }}">

@if (!Route::is(['login', 'layout-horizontal', 'layout-detached', 'layout-modern', 'layout-horizontal-overlay', 'layout-two-column', 
'layout-hovered', 'layout-box', 'layout-horizontal-single', 'layout-horizontal-box', 'layout-horizontal-sidemenu', 'layout-vertical-transparent',
'layout-without-header','login','login-2','login-3','register','register-2','register-3','forgot-password','forgot-password-2','forgot-password-3',
'reset-password','reset-password-2','reset-password-3','email-verification','email-verification-2','email-verification-3','lock-screen','error-404',
'error-500','coming-soon','under-maintenance','under-construction','success','success-2','success-3','two-step-verification','two-step-verification-2','two-step-verification-3',
'layout-without-header', 'layout-rtl', 'layout-dark'
]))
<!-- Theme Script js -->
<script src="{{ URL::asset('build/js/theme-script.js') }}"></script>
@endif

@if (!Route::is(['layout-rtl']))
<!-- Bootstrap CSS -->
<link rel="stylesheet" href="{{ url('build/css/bootstrap.min.css') }}">
@endif

@if (Route::is(['layout-rtl']))
<!-- Bootstrap CSS -->
<link rel="stylesheet" href="{{ url('build/css/bootstrap.rtl.min.css') }}">
@endif

<!-- Fancybox CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/fancybox/jquery.fancybox.min.css') }}">

<!-- Feather CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/icons/feather/feather.css') }}">

<!-- Tabler Icon CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/icons/bootstrap/bootstrap-icons.min.css') }}">

<!-- Tabler Icon CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/tabler-icons/tabler-icons.css') }}">

@if (Route::is(['icon-remix']))
<!-- Remix Icon CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/remix/fonts/remixicon.css') }}">
@endif

<!-- Select2 CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/select2/css/select2.min.css') }}">

@if (Route::is(['maps-leaflet']))
<!-- Leaflet Maps CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/leaflet/leaflet.css') }}">
@endif

@if (Route::is(['maps-vector']))
<!-- Jsvector Maps -->
<link rel="stylesheet" href="{{ url('build/plugins/jsvectormap/css/jsvectormap.min.css') }}">
@endif

@if (Route::is(['ui-scrollbar']))
    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ url('build/plugins/scrollbar/scroll.min.css') }}">
@endif

@if (Route::is(['search-result', 'gallery']))
    <!-- Fancybox CSS -->
    <link rel="stylesheet" href="{{ url('build/plugins/fancybox/jquery.fancybox.min.css') }}">
@endif

@if (Route::is(['ui-stickynote']))
    <!-- Sticky CSS -->
    <link rel="stylesheet" href="{{ url('build/plugins/stickynote/sticky.css') }}">
@endif

<!-- Fontawesome CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/fontawesome/css/fontawesome.min.css') }}">
<link rel="stylesheet" href="{{ url('build/plugins/fontawesome/css/all.min.css') }}">

 <!-- Color Picker Css -->
<link rel="stylesheet" href="{{ url('build/plugins/flatpickr/flatpickr.min.css') }}">
<link rel="stylesheet" href="{{ url('build/plugins/@simonwep/pickr/themes/nano.min.css') }}">

<!-- Daterangepikcer CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/daterangepicker/daterangepicker.css') }}">

<!-- Owl carousel CSS -->
<link rel="stylesheet" href="{{ url('build/css/owl.carousel.min.css') }}">

<!-- Datatable CSS -->
<link rel="stylesheet" href="{{ url('build/css/dataTables.bootstrap5.min.css') }}">

<!-- Player CSS -->
<link rel="stylesheet" href="{{ url('build/css/plyr.css') }}">

<!-- Owl Carousel -->
<link rel="stylesheet" href="{{ url('build/plugins/owlcarousel/owl.carousel.min.css') }}">

<!-- Summernote CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/summernote/summernote-lite.min.css') }}">

<!-- Datetimepicker CSS -->
<link rel="stylesheet" href="{{ url('build/css/bootstrap-datetimepicker.min.css') }}">

<!-- Select2 CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/select2/css/select2.min.css') }}">

<!-- Dragula CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/dragula/css/dragula.min.css') }}">

<!-- ChartC3 CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/c3-chart/c3.min.css') }}">

<!-- Bootstrap Tagsinput CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/bootstrap-tagsinput/bootstrap-tagsinput.css') }}">

<!-- Rangeslider CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/ion-rangeslider/css/ion.rangeSlider.min.css') }}">

@if (Route::is(['icon-ionic']))
<!-- Ionic CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/icons/ionic/ionicons.css') }}">
@endif

<!-- Morris CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/morris/morris.css') }}">

<!-- Material CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/material/materialdesignicons.css') }}">

<!-- Pe7 CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/icons/pe7/pe-icon-7.css') }}">

@if (Route::is(['icon-simpleline']))
<!-- Material CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/simpleline/simple-line-icons.css') }}">
@endif

@if (Route::is(['icon-themify']))
<!-- Pe7 CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/icons/themify/themify.css') }}">
@endif

<link rel="stylesheet" href="{{ url('build/plugins/flatpickr/flatpickr.css') }}" />
<link rel="stylesheet" href="{{ url('build/plugins/bootstrap-datepicker/bootstrap-datepicker.css') }}" />
<link rel="stylesheet" href="{{ url('build/plugins/jquery-timepicker/jquery-timepicker.css') }}" />
<link rel="stylesheet" href="{{ url('build/plugins/pickr/pickr-themes.css') }}" />


<!-- Pe7 CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/icons/typicons/typicons.css') }}">

<!-- Pe7 CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/icons/flags/flags.css') }}">

<!-- Pe7 CSS -->
<link rel="stylesheet" href="{{ url('build/plugins/icons/weather/weathericons.css') }}">

<!-- Main CSS -->
<link rel="stylesheet" href="{{ url('build/css/style.css') }}">