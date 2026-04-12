<?php $page = 'ui-swiperjs'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper cardhead">
    <div class="content">

        <!-- Page Header -->
        <div class="page-header">
            <div class="row">
                <div class="col-sm-12">
                    <h3 class="page-title">Clipboard</h3>
                </div>
            </div>
        </div>
        <!-- /Page Header -->

        <!-- Start::row-1 -->
        <div class="row">
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Basic Swiper
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper swiper-basic">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Swiper With Navigation
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper swiper-navigation">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                          </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Swiper with Pagination
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper pagination">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Dynamic Pagination
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper pagination-dynamic">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Pagination With Progress
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper pagination-progress">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Pagination Fraction
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper pagination-fraction">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-pagination"></div>
                          </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Custom Paginatioin
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper custom-pagination">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-pagination"></div>
                          </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Scrollbar Swiper
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper scrollbar-swiper">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-scrollbar"></div>
                          </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Vertical Swiper
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper vertical swiper-vertical">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Mouse Wheel Control
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper vertical vertical-mouse-control">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Keyboard Control
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper keyboard-control">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">Nested Swiper</div>
                    </div>
                    <div class="card-body">
                        <div class="swiper swiper-horizontal1">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                              <div class="swiper-slide">
                                <div class="swiper vertical swiper-vertical1">
                                  <div class="swiper-wrapper">
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                  </div>
                                  <div class="swiper-pagination"></div>
                                </div>
                              </div>
                              <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                              <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Effect Cube
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper swiper-effect-cube">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Effect Fade
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper swiper-fade">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Effect Flip
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper swiper-flip">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                    <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--End::row-1 -->

        <!-- Start:: row-2 -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Effect Coverflow
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper swiper-overflow">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End:: row-2 -->

        <!-- Start:: row-3 -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            Thumbs Gallery
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="swiper swiper-preview">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                        <div class="swiper swiper-view">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-03.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-04.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-05.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-01.jpg') }}" alt="Img"></div>
                                <div class="swiper-slide"><img src="{{ URL::asset('build/img/img-02.jpg') }}" alt="Img"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End:: row-3 -->

    </div>
</div>
@endsection